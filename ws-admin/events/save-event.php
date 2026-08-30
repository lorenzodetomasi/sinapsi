<?php
// Fase 4 — Salvataggio/aggiornamento EVENTO sul web (con AUTENTICAZIONE).
// Pipeline: auth (Google + users.xml, ruoli come i places) → valida JSON-LD →
// converte in XML (WSX) → valida l'XML → crea la cartella (schema c) + media/ e
// media-sources/ (0775) → scrive index.json E index.xml nella STESSA cartella.
// Riusa json-xml/functions.php e lib/ws-auth.php.
//
// action=auth → solo login/verifica ruolo (usata dal frontend). Altrimenti salva.

// Questo endpoint risponde SOLO JSON: un warning o un errore PHP stampato a video
// finirebbe in testa al corpo e il client vedrebbe "Unexpected token '<'" invece
// dell'errore vero. Gli errori si riportano dentro il JSON, non a schermo.
ini_set('display_errors', '0');

require __DIR__ . '/../json-xml/functions.php';
require_once __DIR__ . '/../lib/ws-wrap.php';
require __DIR__ . '/../lib/ws-auth.php';
require __DIR__ . '/../lib/events-index.php';
require __DIR__ . '/../lib/events-migrate.php';
require __DIR__ . '/../lib/events-normalize.php';
require __DIR__ . '/../lib/events-check.php';
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
/* L'originale da cestinare quando un @id cambia.
 *
 * Salvare crea SEMPRE una cartella nuova, perché la cartella È l'@id: cambiando
 * l'identificativo di un evento aperto restavano due cartelle, due eventi negli
 * indici e nelle categorie, e nessun modo di sapere quale fosse quello buono.
 *
 * La scelta però non si indovina qui — rinominare e duplicare sono due gesti
 * legittimi — quindi la fa chi salva, nell'editor, e arriva qui già decisa. Se non
 * arriva niente, non si tocca niente: è il comportamento di prima. */
$cestina    = trim((string)($_POST['cestina'] ?? ''), '/');

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
        'brokenRefs' => event_check_refs($contentBase),
        'by'         => $user['email'],
    ]);
    exit;
}

// Normalizzazione strutturale dei contenuti (una-tantum): rimuove serie annidate,
// completa le occorrenze mancanti, rideriva subEvent. Solo admin/super-admin. Poi rebuild.
if ($action === 'normalize-content') {
    if (!in_array($user['role'], ['admin', 'super-admin'], true)) {
        http_response_code(403);
        echo json_encode(['error' => "Solo admin o super-admin possono normalizzare i contenuti (ruolo: {$user['role']})."]);
        exit;
    }
    $contentBase = __DIR__ . '/../../ws-custom/contents/meetoo/it_IT';
    $norm = event_normalize($contentBase, true);
    event_migrate_refs($contentBase, true);
    $idx = event_index_rebuild($contentBase);
    echo json_encode([
        'success'   => true,
        'action'    => 'normalize-content',
        'normalize' => [
            'removedSeries'         => $norm['removedSeries'],
            'completedOccurrences'  => $norm['completedOccurrences'],
            'repairedSuperEvent'    => $norm['repairedSuperEvent'],
            'seriesSubEventUpdated' => $norm['seriesSubEventUpdated'],
        ],
        'index'      => $idx,
        'brokenRefs' => event_check_refs($contentBase),
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

$inviato = json_decode($payload, true);
if (!is_array($inviato)) {
    echo json_encode(['error' => 'Payload JSON non valido.']);
    exit;
}
// L'editor manda l'entità nuda; se un domani mandasse il documento intero, si
// prende comunque l'entità. Il guscio di pagina lo mette il server (sotto).
$doc = ws_wrap_entity($inviato);

// Chi è di casa: i gruppi che questa persona gestisce. Servono a tutte e due le
// domande — chi può creare, e chi può modificare quello che il gruppo organizza.
$gruppi = function_exists('ws_gruppi_gestiti') ? ws_gruppi_gestiti($base, $user['uid']) : [];

// Se l'evento esiste già: gate creator/contributor/gruppo.
$storedDoc = $exists ? json_decode((string)@file_get_contents($file), true) : null;
$stored = is_array($storedDoc) ? ws_wrap_entity($storedDoc) : null;
if (is_array($stored) && !ws_can_edit($stored, $user['uid'], $user['role'], $gruppi)) {
    http_response_code(403);
    echo json_encode(['error' => 'Non sei autorizzato a modificare questo evento (creator: ' . ws_ref_id($stored['creator'] ?? null) . '). Chiedi di essere aggiunto ai contributor, oppure fatti nominare fra chi gestisce il gruppo che lo organizza.']);
    exit;
}
// Se è NUOVO: la porta d'ingresso ha una serratura. Mancava del tutto — il gate qui
// sopra scatta solo su ciò che esiste già, quindi chiunque avesse fatto login poteva
// creare. Finché non c'era un «+» da nessuna parte la cosa era teorica; adesso il «+»
// c'è, e la stessa regola che decide se mostrarlo decide se accettare il salvataggio.
if (!is_array($stored) && !ws_can_create('events', $user['uid'], $user['role'], $gruppi)) {
    http_response_code(403);
    echo json_encode(['error' => 'Per pubblicare un evento devi gestire un gruppo su Meetoo. Scrivici e ti mettiamo in contatto con il tuo.']);
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

// Si scrive il DOCUMENTO: guscio di pagina + entità. Conserva il guscio esistente
// se c'era (con i suoi eventuali dati di pagina), altrimenti lo crea.
$documento = is_array($storedDoc) ? ws_wrap_set($storedDoc, $doc) : ws_wrap_one($doc);
$payload = json_encode($documento, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

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

/* 5-bis) l'originale, se l'editor ha chiesto di rinominare.
 *
 * SPOSTATO nel cestino, non cancellato: la cartella resta com'era — media
 * compresi — e «Ripristina» la rimette al suo posto. Si fa DOPO la scrittura del
 * nuovo: se qualcosa fosse andato storto sopra, qui non si arriva, e il documento
 * di partenza è ancora dov'era.
 *
 * Se fallisce non si dichiara fallito il salvataggio, che è già avvenuto: si dice
 * nella risposta, così chi ha salvato lo sa e può cestinare a mano. */
$cestinato = null;
if ($cestina !== '' && $cestina !== $relPath) {
    require_once __DIR__ . '/../lib/events-trash.php';
    $r = ws_trash_move($base, $cestina, $user);
    $cestinato = !empty($r['ok'])
        ? ['path' => $cestina, 'id' => $r['entry']['id'] ?? '']
        : ['path' => $cestina, 'error' => $r['error'] ?? 'spostamento fallito'];
}

// 6) aggiorna l'indice SOLO per l'evento salvato (prossimi/archivio, per-organizer,
// per-collection, per-CAP), ripulendo i raggruppamenti che non lo riguardano più e
// riattribuendo le occorrenze se si è salvata una serie: sono le tre derive che
// rendevano necessario il rebuild pieno. Il rebuild resta a disposizione in
// «Gestione eventi» per rimettere tutto in riga dopo modifiche fuori dall'editor.
// Best-effort: un errore d'indice NON invalida il salvataggio già andato a buon fine.
// L'evento è GIÀ scritto: qualunque problema qui non deve far credere che il
// salvataggio sia fallito, e non deve rompere la risposta JSON. Se la libreria sul
// server è più vecchia dell'endpoint (deploy parziale) si ripiega sul rebuild.
try {
    /* Se l'originale è uscito da `events/`, l'indice va rifatto per intero: la
     * sincronizzazione mirata sa aggiornare l'evento salvato, non sa che un altro
     * è sparito — e quello resterebbe negli elenchi e nelle pagine pubbliche.
     * È la stessa scelta che fa «Gestione eventi» quando si cestina a mano. */
    $index = (!empty($cestinato) && empty($cestinato['error']))
        ? ['mode' => 'rebuild (originale cestinato)'] + event_index_rebuild($base)
        : (function_exists('event_index_sync')
            ? event_index_sync($base, $relPath, $doc['mainEntity'] ?? $doc)
            : ['mode' => 'rebuild (lib non aggiornata)'] + event_index_rebuild($base));
} catch (\Throwable $e) {
    $index = ['error' => 'evento salvato, ma indice non aggiornato: ' . $e->getMessage()];
}

// Liste con regola: un evento appena salvato può entrare (o uscire) da una
// collezione tematica, e chi la guarda deve trovarcelo subito. Costa ~5 ms e non
// scrive nulla se non è cambiato niente. Stessa cautela dell'indice: l'evento è
// GIÀ scritto, un problema qui non deve far credere che il salvataggio sia fallito.
$liste = null;
try {
    $libListe = __DIR__ . '/../lib/ws-listrule.php';
    if (is_file($libListe)) {
        require_once $libListe;
        $r = ws_listrule_sync($base, true);
        if ($r['cambiate']) $liste = ['aggiornate' => $r['cambiate']];
    }
} catch (\Throwable $e) {
    $liste = ['error' => 'liste non aggiornate: ' . $e->getMessage()];
}

echo json_encode([
    'success'       => true,
    'path'          => $relPath,
    'cestinato'     => $cestinato,
    'folderCreated' => $created,
    'wrote'         => ['index.json', 'index.xml'],
    'index'         => $index,
    'lists'         => $liste,
    'by'            => $user['email'],
]);
