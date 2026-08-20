<?php
// Registrazione degli utenti agli EVENTI SINGOLI (RSVP), con login Google e controllo
// delle capienze del fieldset "Pubblico". Riusa lib/ws-auth.php (verifica token + ruolo) e
// lib/ws-users.php (profilo utente per UID). Le registrazioni vivono in events/{slug}/rsvp.json.
//
// Azioni (POST, application/x-www-form-urlencoded):
//   me            → verifica token, crea/aggiorna il profilo utente, ritorna l'utente
//   status        → capienze + conteggi (+ la tua registrazione e isAdmin se passi il token)
//   register      → registra l'utente all'evento (mode=offline|online), verificando le capienze
//   unregister    → annulla la propria registrazione
//   participants  → lista partecipanti (solo admin dell'evento) + notifiche + "nuove dall'ultima visita"
//   notify        → l'admin attiva/disattiva la notifica in-app (enabled=0|1)

require __DIR__ . '/../lib/ws-auth.php';
require __DIR__ . '/../lib/ws-users.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Solo POST']); exit; }

$base       = __DIR__ . '/../../ws-custom/contents/meetoo/it_IT';
$action     = (string)($_POST['action'] ?? '');
$credential = (string)($_POST['credential'] ?? '');
$relPath    = trim((string)($_POST['path'] ?? ''), '/');
$mode       = (string)($_POST['mode'] ?? 'offline');

function fail(int $code, string $msg) { http_response_code($code); echo json_encode(['error' => $msg]); exit; }

// --- Utente (token) ---
$user = $credential !== '' ? ws_authenticate($credential) : null;
if ($action === 'me') {
    if (!$user) fail(401, 'Login Google fallito o scaduto.');
    $p = ws_user_upsert($base, $user);
    echo json_encode(['uid' => $user['uid'], 'name' => $user['name'] ?: $user['email'], 'email' => $user['email'], 'picture' => $user['picture'], 'role' => $user['role'], 'prefs' => $p['meetoo:preferences'] ?? []]);
    exit;
}

// Preferenze utente (lingua, notifiche): salva e ritorna quelle aggiornate.
if ($action === 'prefs') {
    if (!$user) fail(401, 'Accedi con Google per salvare le preferenze.');
    $in = [];
    if (isset($_POST['language']))      $in['language'] = preg_replace('/[^a-zA-Z_-]/', '', (string)$_POST['language']);
    if (isset($_POST['notifications'])) $in['notifications'] = ((string)$_POST['notifications'] === '1');
    $prefs = ws_user_set_prefs($base, $user['uid'], $in);
    echo json_encode(['success' => true, 'prefs' => $prefs]);
    exit;
}

// --- Evento richiesto ---
if ($relPath === '' || strpos($relPath, '..') !== false || !preg_match('#^events/[A-Za-z0-9._/\-]+$#', $relPath)) fail(400, "Percorso evento non valido.");
$eventFile = "$base/$relPath/index.json";
if (!is_file($eventFile)) fail(404, 'Evento non trovato.');
$event = json_decode((string)@file_get_contents($eventFile), true);
if (!is_array($event)) fail(500, 'Evento illeggibile.');
$types = (array)($event['@type'] ?? []);
$isSeries = in_array('EventSeries', $types, true);

$rsvpFile = "$base/$relPath/rsvp.json";
function load_rsvp(string $f, string $rel): array {
    $d = is_file($f) ? json_decode((string)@file_get_contents($f), true) : null;
    if (!is_array($d)) $d = [];
    $d['@type'] = 'meetoo:RsvpList';
    $d['event'] = $rel;
    if (!isset($d['registrations']) || !is_array($d['registrations'])) $d['registrations'] = [];
    if (!isset($d['notify']) || !is_array($d['notify'])) $d['notify'] = [];
    return $d;
}
function save_rsvp(string $f, array $d): bool {
    @mkdir(dirname($f), 0775, true);
    return @file_put_contents($f, json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) !== false;
}

// Capienze dal fieldset "Pubblico".
function capacity(array $event, array $regs): array {
    $m = (string)($event['eventAttendanceMode'] ?? '');
    $mixed   = stripos($m, 'Mixed')   !== false;
    $offline = $mixed || stripos($m, 'Offline') !== false || $m === '';
    $online  = $mixed || stripos($m, 'Online')  !== false;
    $maxP = (int)($event['maximumPhysicalAttendeeCapacity'] ?? 0);
    $maxV = (int)($event['maximumVirtualAttendeeCapacity'] ?? 0);
    $maxT = (int)($event['maximumAttendeeCapacity'] ?? ($maxP + $maxV));
    $bookedBase = (int)($event['bookedAttendeeCapacity'] ?? 0);
    $offC = 0; $onC = 0;
    foreach ($regs as $r) { (($r['mode'] ?? 'offline') === 'online') ? $onC++ : $offC++; }
    $booked = $bookedBase + count($regs);
    $remaining = $maxT > 0 ? max(0, $maxT - $booked) : null; // null = non specificato / illimitato
    return [
        'attendanceMode' => $m, 'offlineAllowed' => $offline, 'onlineAllowed' => $online,
        'maxPhysical' => $maxP, 'maxVirtual' => $maxV, 'maxTotal' => $maxT,
        'bookedBase' => $bookedBase, 'booked' => $booked, 'remaining' => $remaining,
        'offlineCount' => $offC, 'onlineCount' => $onC,
        'offlineFull' => $maxP > 0 && $offC >= $maxP,
        'onlineFull' => $maxV > 0 && $onC >= $maxV,
        'full' => $remaining !== null && $remaining <= 0,
    ];
}
function my_reg(array $regs, ?array $user): ?array {
    if (!$user) return null;
    foreach ($regs as $r) if (($r['uid'] ?? '') === $user['uid']) return $r;
    return null;
}
function is_admin(array $event, ?array $user): bool {
    return $user && function_exists('ws_can_edit') && ws_can_edit($event, $user['uid'], $user['role']);
}

$rsvp = load_rsvp($rsvpFile, $relPath);

// --- STATUS (pubblico; con token aggiunge la tua registrazione e isAdmin) ---
if ($action === 'status') {
    $cap = capacity($event, $rsvp['registrations']);
    echo json_encode([
        'event' => $relPath, 'isSeries' => $isSeries, 'capacity' => $cap,
        'registered' => $user ? (my_reg($rsvp['registrations'], $user) !== null) : false,
        'myMode' => $user ? (my_reg($rsvp['registrations'], $user)['mode'] ?? null) : null,
        'isAdmin' => is_admin($event, $user),
        'count' => count($rsvp['registrations']),
    ]);
    exit;
}

// Da qui in poi serve il login.
if (!$user) fail(401, 'Accedi con Google per registrarti.');

// --- REGISTER / UNREGISTER (solo eventi singoli) ---
if ($action === 'register' || $action === 'unregister') {
    if ($isSeries) fail(400, 'La registrazione è disponibile solo per gli eventi singoli.');
    ws_user_upsert($base, $user); // memoria del profilo per accessi futuri

    if ($action === 'unregister') {
        $rsvp['registrations'] = array_values(array_filter($rsvp['registrations'], fn($r) => ($r['uid'] ?? '') !== $user['uid']));
        save_rsvp($rsvpFile, $rsvp);
        echo json_encode(['success' => true, 'registered' => false, 'capacity' => capacity($event, $rsvp['registrations'])]);
        exit;
    }

    $mode = $mode === 'online' ? 'online' : 'offline';
    $cap = capacity($event, $rsvp['registrations']);
    if ($mode === 'offline' && !$cap['offlineAllowed']) fail(400, 'Questo evento non prevede la partecipazione in presenza.');
    if ($mode === 'online'  && !$cap['onlineAllowed'])  fail(400, 'Questo evento non prevede la partecipazione da remoto.');

    $existing = my_reg($rsvp['registrations'], $user);
    if (!$existing) {
        // nuovo: verifica capienza totale e per-modalità
        if ($cap['full']) fail(409, 'Posti esauriti.');
        if ($mode === 'offline' && $cap['offlineFull']) fail(409, 'Posti in presenza esauriti.');
        if ($mode === 'online'  && $cap['onlineFull'])  fail(409, 'Posti da remoto esauriti.');
        $rsvp['registrations'][] = ['uid' => $user['uid'], 'name' => $user['name'] ?: $user['email'], 'email' => $user['email'], 'mode' => $mode, 'date' => date('c')];
    } else {
        // cambio modalità: verifica solo la nuova modalità (il totale non cambia)
        if ($existing['mode'] !== $mode) {
            if ($mode === 'offline' && $cap['offlineFull']) fail(409, 'Posti in presenza esauriti.');
            if ($mode === 'online'  && $cap['onlineFull'])  fail(409, 'Posti da remoto esauriti.');
            foreach ($rsvp['registrations'] as &$r) if (($r['uid'] ?? '') === $user['uid']) { $r['mode'] = $mode; }
            unset($r);
        }
    }
    save_rsvp($rsvpFile, $rsvp);
    echo json_encode(['success' => true, 'registered' => true, 'myMode' => $mode, 'capacity' => capacity($event, $rsvp['registrations'])]);
    exit;
}

// --- PARTICIPANTS / NOTIFY (solo admin dell'evento) ---
if ($action === 'participants' || $action === 'notify') {
    if (!is_admin($event, $user)) fail(403, 'Solo gli amministratori dell\'evento possono vedere i partecipanti.');
    $uid = $user['uid'];

    if ($action === 'notify') {
        $enabled = (string)($_POST['enabled'] ?? '1') === '1';
        $rsvp['notify'][$uid] = ['enabled' => $enabled, 'seen' => $rsvp['notify'][$uid]['seen'] ?? date('c')];
        save_rsvp($rsvpFile, $rsvp);
        echo json_encode(['success' => true, 'enabled' => $enabled]);
        exit;
    }

    // participants: elenca, calcola le "nuove dall'ultima visita", poi aggiorna 'seen'.
    $seen = $rsvp['notify'][$uid]['seen'] ?? '';
    $regs = $rsvp['registrations'];
    usort($regs, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? '')); // più recenti prima
    $newCount = $seen === '' ? 0 : count(array_filter($regs, fn($r) => strcmp($r['date'] ?? '', $seen) > 0));
    // segna come viste
    $rsvp['notify'][$uid] = ['enabled' => $rsvp['notify'][$uid]['enabled'] ?? false, 'seen' => date('c')];
    save_rsvp($rsvpFile, $rsvp);

    echo json_encode([
        'success' => true,
        'participants' => array_map(fn($r) => ['name' => $r['name'] ?? '', 'email' => $r['email'] ?? '', 'mode' => $r['mode'] ?? 'offline', 'date' => $r['date'] ?? ''], $regs),
        'count' => count($regs),
        'newCount' => $newCount,
        'notifyEnabled' => (bool)($rsvp['notify'][$uid]['enabled'] ?? false),
        'capacity' => capacity($event, $rsvp['registrations']),
    ]);
    exit;
}

fail(400, "Azione non riconosciuta: '$action'");
