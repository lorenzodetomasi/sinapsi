<?php
// Fase 4 — Salvataggio/aggiornamento EVENTO sul web (con AUTENTICAZIONE).
// Pipeline: auth (Google + users.xml, ruoli come i places) → valida JSON-LD →
// converte in XML (WSX) → valida l'XML → crea la cartella (schema c) + media/ e
// media-sources/ (0775) → scrive index.json E index.xml nella STESSA cartella.
// Riusa json-xml/functions.php e lib/ws-auth.php.
//
// action=auth → solo login/verifica ruolo (usata dal frontend). Altrimenti salva.

require __DIR__ . '/../json-xml/functions.php';
require __DIR__ . '/../lib/ws-auth.php';
require __DIR__ . '/../lib/events-index.php';
require __DIR__ . '/../lib/events-migrate.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Solo POST']);
    exit;
}

$credential = (string)($_POST['credential'] ?? '');
$action     = (string)($_POST['action'] ?? 'save');
$payload    = (string)($_POST['payload'] ?? '');
$relPath    = trim((string)($_POST['path'] ?? ''), '/');

// --- Autenticazione ---
$user = ws_authenticate($credential);
if ($user === null) {
    http_response_code(401);
    echo json_encode(['error' => 'Autenticazione Google fallita o token scaduto. Accedi di nuovo.']);
    exit;
}

// Login frontend: restituisce identità + ruolo.
if ($action === 'auth') {
    echo json_encode(['uid' => $user['uid'], 'email' => $user['email'], 'role' => $user['role'], 'locale' => $user['locale']]);
    exit;
}

// Ricostruzione dell'indice eventi (solo admin/super-admin), invocabile dall'editor.
if ($action === 'rebuild-index') {
    if (!in_array($user['role'], ['admin', 'super-admin'], true)) {
        http_response_code(403);
        echo json_encode(['error' => "Solo admin o super-admin possono ricostruire l'indice (ruolo: {$user['role']})."]);
        exit;
    }
    $contentBase = __DIR__ . '/../../ws-custom/contents/meetoo/it_IT';
    // Normalizza i riferimenti PRIMA di indicizzare (idempotente: tocca solo i file che
    // cambiano, quindi "solo quando necessario"), poi ricostruisce l'indice.
    $mig = event_migrate_refs($contentBase, true);
    $r   = event_index_rebuild($contentBase);
    echo json_encode([
        'success'    => true,
        'action'     => 'rebuild-index',
        'normalized' => ['files' => $mig['changedFiles'], 'changes' => $mig['changes'], 'warns' => $mig['warns']],
        'index'      => $r,
        'by'         => $user['email'],
    ]);
    exit;
}

// --- Salvataggio: ruolo autorizzato ---
if (!in_array($user['role'], ['user', 'client', 'admin', 'super-admin'], true)) {
    http_response_code(403);
    echo json_encode(['error' => "Permessi insufficienti per salvare (ruolo: {$user['role']}). Chiedi di essere aggiunto agli utenti."]);
    exit;
}

// Percorso relativo consentito (schema c): events/… oppure organizations/…/events/…
if ($relPath === '' || strpos($relPath, '..') !== false ||
    !preg_match('#^(events|organizations)/[A-Za-z0-9._/\-]+$#', $relPath)) {
    echo json_encode(['error' => "Percorso non valido: '$relPath'"]);
    exit;
}

$base   = __DIR__ . '/../../ws-custom/contents/meetoo/it_IT';
$dir    = $base . '/' . $relPath;
$file   = "$dir/index.json";
$exists = is_file($file);

$doc = json_decode($payload, true);
if (!is_array($doc)) {
    echo json_encode(['error' => 'Payload JSON non valido.']);
    exit;
}

// Se l'evento esiste già: gate creator/contributor.
$stored = $exists ? json_decode((string)@file_get_contents($file), true) : null;
if (is_array($stored) && !ws_can_edit($stored, $user['uid'], $user['role'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Non sei autorizzato a modificare questo evento (creator: ' . ws_ref_id($stored['creator'] ?? null) . '). Chiedi di essere aggiunto ai contributor.']);
    exit;
}

// Campi server-authoritative: preserva creator/contributor/dateCreated esistenti,
// altrimenti (nuovo) imposta creator=utente e dateCreated=adesso; dateModified sempre.
$now = date('c');
if (is_array($stored)) {
    foreach (['creator', 'contributor', 'dateCreated'] as $k) {
        if (isset($stored[$k])) $doc[$k] = $stored[$k];
    }
    if (!isset($doc['creator'])) $doc['creator'] = ws_person_ref($user['uid']);
    if (!isset($doc['dateCreated'])) $doc['dateCreated'] = $now;
} else {
    $doc['creator'] = ws_person_ref($user['uid']);
    $doc['dateCreated'] = $now;
}
$doc['dateModified'] = $now;

$payload = json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

// 1) valida JSON
$jv = validateJsonPayload($payload);
if (empty($jv['valid'])) {
    echo json_encode(['error' => 'JSON non valido', 'stage' => 'json', 'errors' => $jv['errors']]);
    exit;
}

// 2) converte in XML (WSX, CDATA per l'XHTML)
try {
    $xml = jsonToWsx($payload);
} catch (\Throwable $e) {
    echo json_encode(['error' => 'Conversione XML fallita: ' . $e->getMessage(), 'stage' => 'to_xml']);
    exit;
}

// 3) valida l'XML
$xv = validateXmlPayload($xml);
if (empty($xv['valid'])) {
    echo json_encode(['error' => 'XML non valido', 'stage' => 'xml', 'errors' => $xv['errors']]);
    exit;
}

// 4) cartella evento + media/ e media-sources/ (0775)
$created = !is_dir($dir);
if ($created && !@mkdir($dir, 0775, true)) {
    echo json_encode(['error' => "Cartella non creabile: $relPath (permessi?)"]);
    exit;
}
@mkdir("$dir/media", 0775, true);
@mkdir("$dir/media-sources", 0775, true);

// 5) scrive index.json E index.xml
$jsonOk = @file_put_contents($file, $payload) !== false;
$xmlOk  = @file_put_contents("$dir/index.xml", $xml) !== false;
if (!$jsonOk || !$xmlOk) {
    echo json_encode(['error' => 'Scrittura fallita (permessi?)', 'json' => $jsonOk, 'xml' => $xmlOk]);
    exit;
}

// 6) rigenera l'indice COMPLETO dai contenuti (prossimi/archivio, per-organizer,
// per-collection, con ereditarietà organizer dalla serie). Un rebuild pieno evita la
// deriva incrementale (membership/organizer stantii, ribucketing per data). L'@id/percorso
// appena scritto è già su disco, quindi rientra nella scansione. Best-effort: un errore
// d'indice NON invalida il salvataggio già andato a buon fine.
$index = event_index_rebuild($base);

echo json_encode([
    'success'       => true,
    'path'          => $relPath,
    'folderCreated' => $created,
    'wrote'         => ['index.json', 'index.xml'],
    'index'         => $index,
    'by'            => $user['email'],
]);
