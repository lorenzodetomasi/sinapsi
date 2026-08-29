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
require_once __DIR__ . '/../lib/ws-private.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Solo POST']); exit; }

$base       = __DIR__ . '/../../ws-custom/contents/meetoo/it_IT';
$action     = (string)($_POST['action'] ?? '');
$credential = (string)($_POST['credential'] ?? '');
$relPath    = trim((string)($_POST['path'] ?? ''), '/');
$mode       = (string)($_POST['mode'] ?? 'offline');

function fail(int $code, string $msg) { http_response_code($code); echo json_encode(['error' => $msg]); exit; }

/* --- Chi sei: token o sessione ---
 *
 * Due strade, una sola risposta. Il TOKEN nel corpo è quella dell'archivio e
 * dell'editor: chi ce l'ha lo manda, e vale un'ora. La SESSIONE è quella del sito
 * servito dal CMS: il cookie dice chi sei senza chiedere niente a Google a ogni
 * gesto, e dura quanto il cookie.
 *
 * Il token ha la precedenza perché è più esplicito: se una richiesta se lo porta
 * dietro, è quello che vuole usare. La sessione interviene quando non c'è.
 *
 * Con la sessione serve il gettone: il cookie il browser lo manda da solo, anche
 * per conto di un sito che non è questo. Senza gettone, un'altra pagina potrebbe
 * far mettere «mi interessa» — o peggio, iscrivere a un evento — a tua insaputa.
 */
$user = $credential !== '' ? ws_authenticate($credential) : null;
if (!$user) {
    $user = ws_autentica_sessione();
    if ($user && !ws_gettone_valido((string)($_POST['csrf'] ?? ''))) {
        fail(403, 'Richiesta non riconosciuta: ricarica la pagina e riprova.');
    }
}
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


// «Mi interessa»: file separato dalle registrazioni perché è un dato pubblico
// (il conteggio si legge senza login) e contiene SOLO gli uid, mai nomi o email.
/* DUE SEGNALI, non uno.
 *
 * «Mi interessa» e «Mi piace» rispondono a due domande diverse: la prima e' un
 * segnaposto — voglio ritrovarlo, forse ci vado — la seconda un giudizio, e vale
 * anche per un evento gia' passato a cui non si poteva andare. Tenerle nello
 * stesso elenco vorrebbe dire non poter piu' rispondere ne' all'una ne' all'altra.
 *
 * `users` resta il nome della prima: era gia' li' e i file scritti finora la
 * chiamano cosi'. */
const LIKE_LISTE = ['interesse' => 'users', 'piace' => 'loves'];
const LIKE_CAMPI = ['interesse' => 'meetoo:interestedIn', 'piace' => 'meetoo:likes'];

function likes_kind(string $k): string { return isset(LIKE_LISTE[$k]) ? $k : 'interesse'; }

function likes_file(string $base, string $rel): string { return "$base/$rel/likes.json"; }
function load_likes(string $f): array {
    $d = is_file($f) ? json_decode((string)@file_get_contents($f), true) : null;
    if (!is_array($d)) $d = [];
    foreach (LIKE_LISTE as $lista) {
        if (!isset($d[$lista]) || !is_array($d[$lista])) $d[$lista] = [];
    }
    $d["count"] = count($d["users"]);
    return $d;
}
function save_likes(string $f, array $d): bool {
    @mkdir(dirname($f), 0775, true);
    $d["count"] = count($d["users"]);
    return @file_put_contents($f, json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) !== false;
}

/* ---------------------------------------------------------------------------
 * LE VALUTAZIONI: dopo, e solo da chi c'era.
 *
 * Un giudizio vale per quello che è: il racconto di chi c'è stato. Quindi si può
 * dare solo quando l'evento è finito e solo se ci si era iscritti — non e' un
 * sondaggio d'opinione, e non e' un modo per dire la propria su una cosa che
 * non si e' vista.
 *
 * Si valutano tre cose diverse, perché sono tre esperienze diverse: l'evento
 * (com'era), chi l'ha organizzato (come l'ha tenuto) e il luogo (com'era stare
 * lì). Un posto scomodo non è colpa di chi organizza, e un bell'incontro in una
 * sala fredda resta un bell'incontro.
 *
 * Stanno in `ratings.json` accanto all'evento, una riga per bersaglio: chi ha
 * votato e quanto. La media si ricalcola leggendo, che è l'unico modo perché non
 * possa divergere dai voti.
 * ------------------------------------------------------------------------- */
function ratings_file(string $base, string $rel): string { return "$base/$rel/ratings.json"; }
function load_ratings(string $f): array {
    $d = is_file($f) ? json_decode((string)@file_get_contents($f), true) : null;
    if (!is_array($d) || !isset($d['targets']) || !is_array($d['targets'])) $d = ['targets' => []];
    return $d;
}
function save_ratings(string $f, array $d): bool {
    @mkdir(dirname($f), 0775, true);
    return @file_put_contents($f, json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) !== false;
}
/* Un voto è `{value, text, date}`. I file scritti prima avevano il numero nudo:
 * si accettano tutti e due, perché un formato che cambia non deve cancellare
 * quello che la gente aveva già detto. */
function ratings_voto($v): array {
    if (is_array($v)) {
        return ['value' => (int)($v['value'] ?? 0), 'text' => (string)($v['text'] ?? ''), 'date' => (string)($v['date'] ?? '')];
    }
    return ['value' => (int)$v, 'text' => '', 'date' => ''];
}
/** Le medie, bersaglio per bersaglio: {id: {value, count, reviews}}. */
function ratings_medie(array $d): array {
    $out = [];
    foreach ($d['targets'] as $id => $voti) {
        if (!is_array($voti) || !count($voti)) continue;
        $somma = 0; $n = 0; $scritte = 0;
        foreach ($voti as $v) {
            $x = ratings_voto($v);
            if ($x['value'] < 1) continue;
            $somma += $x['value']; $n++;
            if ($x['text'] !== '') $scritte++;
        }
        if (!$n) continue;
        $out[$id] = ['value' => round($somma / $n, 1), 'count' => $n, 'reviews' => $scritte];
    }
    return $out;
}
/** I voti di questa persona: {id: {value, text}}. */
function ratings_miei(array $d, ?array $user): array {
    if (!$user) return [];
    $out = [];
    foreach ($d['targets'] as $id => $voti) {
        if (isset($voti[$user['uid']])) {
            $x = ratings_voto($voti[$user['uid']]);
            $out[$id] = ['value' => $x['value'], 'text' => $x['text']];
        }
    }
    return $out;
}
/* Le recensioni scritte, per bersaglio, SENZA chi le ha scritte.
 *
 * Il nome di chi ha votato non esce di qui: sta nell'archivio privato, e per
 * mostrarlo ci vuole una decisione — chi scrive una recensione non ha
 * acconsentito a firmarla in pubblico solo perché l'ha scritta. Il giorno che
 * quella decisione si prende, il nome si aggiunge qui e basta. */
function ratings_recensioni(array $d): array {
    $out = [];
    foreach ($d['targets'] as $id => $voti) {
        foreach ((array)$voti as $v) {
            $x = ratings_voto($v);
            if ($x['text'] === '') continue;
            $out[$id][] = ['value' => $x['value'], 'text' => $x['text'], 'date' => $x['date']];
        }
    }
    return $out;
}
/** L'evento è già stato? Conta la fine, se c'è; se no l'inizio. */
function evento_passato(array $event): bool {
    $fine = (string)($event['endDate'] ?? $event['startDate'] ?? '');
    $t = $fine !== '' ? strtotime($fine) : 0;
    return $t > 0 && $t < time();
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
        'likes' => count(load_likes(likes_file($base, $relPath))['users']),
        'liked' => $user ? in_array($user['uid'], load_likes(likes_file($base, $relPath))['users'], true) : false,
        'loves' => count(load_likes(likes_file($base, $relPath))['loves']),
        'loved' => $user ? in_array($user['uid'], load_likes(likes_file($base, $relPath))['loves'], true) : false,
        // Le valutazioni: le medie le vedono tutti, il proprio voto solo chi l'ha
        // dato, e la possibilità di darlo solo chi c'era, a cose finite.
        'past' => evento_passato($event),
        'canRate' => $user && !$isSeries && evento_passato($event) && my_reg($rsvp['registrations'], $user) !== null,
        'ratings' => ratings_medie(load_ratings(ratings_file($base, $relPath))),
        'myRatings' => ratings_miei(load_ratings(ratings_file($base, $relPath)), $user),
        'reviews' => ratings_recensioni(load_ratings(ratings_file($base, $relPath))),
    ]);
    exit;
}

// Da qui in poi serve il login.
if (!$user) fail(401, 'Accedi con Google per registrarti.');

// --- MI INTERESSA (like) ---
// Vale per tutti gli eventi, serie comprese: interessarsi a una rassegna ha senso
// quanto interessarsi a una sua data. Il like resta anche sul profilo dell utente,
// così "gli eventi che mi interessano" è una domanda a cui si può rispondere.
if ($action === 'like') {
    // `kind` sceglie il segnale: senza, e' «mi interessa» — com'era prima che ne
    // esistesse un secondo, cosi' chi chiamava non deve cambiare.
    $kind = likes_kind((string)($_POST['kind'] ?? 'interesse'));
    $lista = LIKE_LISTE[$kind];
    ws_user_upsert($base, $user);
    $lf = likes_file($base, $relPath);
    $likes = load_likes($lf);
    $uid = $user['uid'];
    $era = in_array($uid, $likes[$lista], true);
    $likes[$lista] = $era
        ? array_values(array_filter($likes[$lista], fn($u) => $u !== $uid))
        : array_merge($likes[$lista], [$uid]);
    $likes['@type'] = 'meetoo:InterestList';
    $likes['event'] = $relPath;
    if (!save_likes($lf, $likes)) fail(500, 'Non riesco a salvare: il server ha i permessi sulla cartella dell evento?');
    ws_user_toggle_like($base, $uid, $relPath, !$era, LIKE_CAMPI[$kind]);
    echo json_encode([
        'success' => true, 'kind' => $kind, 'on' => !$era, 'count' => count($likes[$lista]),
        // I nomi di prima, per chi legge ancora quelli (le card del sito).
        'liked' => $kind === 'interesse' ? !$era : in_array($uid, $likes['users'], true),
        'likes' => count($likes['users']),
    ]);
    exit;
}

// --- VALUTA (solo a evento finito, solo da chi era iscritto) ---
if ($action === 'rate') {
    if ($isSeries) fail(400, 'Si valuta una data, non la rassegna.');
    if (!evento_passato($event)) fail(400, 'L\'evento non è ancora finito: si valuta dopo.');
    if (my_reg($rsvp['registrations'], $user) === null) fail(403, 'Puoi valutare gli eventi a cui ti eri iscritto.');
    $target = trim((string)($_POST['target'] ?? ''));
    $valore = (int)($_POST['value'] ?? 0);
    /* Due righe, non un tema: una recensione lunga non la legge nessuno, e un
     * campo senza limite è un invito a incollarci dentro qualunque cosa. */
    $testo = trim((string)($_POST['text'] ?? ''));
    if (function_exists('mb_substr')) $testo = mb_substr($testo, 0, 600);
    else $testo = substr($testo, 0, 600);
    if ($target === '') fail(400, 'Manca che cosa stai valutando.');
    // Il bersaglio è un @id del sito (l'evento stesso, un organizzatore, il luogo):
    // niente barre iniziali, niente risalite di cartella.
    if (!preg_match('#^[A-Za-z0-9._/-]+$#', $target) || strpos($target, '..') !== false) fail(400, 'Bersaglio non valido.');
    if ($valore < 0 || $valore > 5) fail(400, 'Il voto va da 1 a 5.');

    $rf = ratings_file($base, $relPath);
    $r = load_ratings($rf);
    if (!isset($r['targets'][$target]) || !is_array($r['targets'][$target])) $r['targets'][$target] = [];
    // Zero significa «tolgo il voto»: ci si può ripensare, e un voto ritirato non
    // deve restare a pesare sulla media — né lasciare in giro quello che avevo
    // scritto quando la pensavo diversamente.
    if ($valore === 0) {
        unset($r['targets'][$target][$user['uid']]);
    } else {
        $prima = isset($r['targets'][$target][$user['uid']]) ? ratings_voto($r['targets'][$target][$user['uid']]) : null;
        // Il testo si tocca solo se è stato mandato: cambiare la stella non deve
        // cancellare la recensione scritta ieri.
        $t = array_key_exists('text', $_POST) ? $testo : ($prima['text'] ?? '');
        $r['targets'][$target][$user['uid']] = ['value' => $valore, 'text' => $t, 'date' => date('c')];
    }
    $r['@type'] = 'meetoo:RatingList';
    $r['event'] = $relPath;
    if (!save_ratings($rf, $r)) fail(500, 'Non riesco a salvare la valutazione.');
    echo json_encode([
        'success' => true, 'target' => $target, 'value' => $valore,
        'ratings' => ratings_medie($r), 'reviews' => ratings_recensioni($r),
    ]);
    exit;
}

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
        // Solo l uid: nome ed email di chi si registra non finiscono in un file servito
        // dal web. Chi può vedere la lista li ottiene ricomposti dall archivio privato.
        $rsvp['registrations'][] = ['uid' => $user['uid'], 'mode' => $mode, 'date' => date('c')];
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

/* --- RICALCOLA LA MEDIA (per gli editor) ---
 *
 * La stessa funzione della manutenzione, chiamata su un bersaglio solo: serve
 * all'editor, dove `aggregateRating` è un campo che si vede e che finora si
 * poteva anche digitare. Può farlo chi ha il diritto di modificare l'evento —
 * la media di un gruppo la ricalcola chi cura gli eventi di quel gruppo. */
if ($action === 'aggregate') {
    if (!is_admin($event, $user)) fail(403, 'Non hai i permessi per ricalcolare le valutazioni.');
    require_once __DIR__ . '/../lib/ws-rating.php';
    $target = trim((string)($_POST['target'] ?? $relPath));
    if (!preg_match('#^[A-Za-z0-9._/-]+$#', $target) || strpos($target, '..') !== false) fail(400, 'Bersaglio non valido.');
    $r = ws_rating_aggiorna($base, $target, true);
    echo json_encode(['success' => true] + $r);
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
        // I nomi arrivano dall archivio privato, non dal file dell evento.
        'participants' => array_map(fn($r) => [
            'name'  => $r['name'] ?? ws_private_display_name((string)($r['uid'] ?? '')),
            'email' => $r['email'] ?? ws_private_email((string)($r['uid'] ?? '')),
            'mode' => $r['mode'] ?? 'offline', 'date' => $r['date'] ?? ''], $regs),
        'count' => count($regs),
        'newCount' => $newCount,
        'notifyEnabled' => (bool)($rsvp['notify'][$uid]['enabled'] ?? false),
        'capacity' => capacity($event, $rsvp['registrations']),
    ]);
    exit;
}

fail(400, "Azione non riconosciuta: '$action'");
