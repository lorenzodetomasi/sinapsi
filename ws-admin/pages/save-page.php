<?php
/*
 * Il backend dell'editor delle pagine: carica una pagina, ne salva una.
 *
 * Due azioni e nessuna pagina: l'editor è un'applicazione a parte e questo è
 * il suo interlocutore.
 *
 *   action=load   site, id            → il documento, e chi può toccarlo
 *   action=save   site, id, jsonld    → lo scrive, e rifà quello che ne dipende
 *
 * CHE COSA DIPENDE DA UNA PAGINA. Il gemello XML, che è quello che i template
 * leggono davvero; la mappa della lingua, che è la tabella di instradamento del
 * CMS; la mappa del sito che la compone; e il `sitemap.xml` per i motori. Il
 * primo è pigro e si rifà da sé alla prima richiesta — gli altri tre no.
 *
 * È la differenza che conta: cambiare il `wspath` di una pagina e non rifare le
 * mappe significa che la pagina risponde ancora al vecchio indirizzo e il nuovo
 * dà 404, e nulla lo segnala. Per questo il salvataggio le rigenera qui, prima
 * di rispondere, e dice nella risposta com'è andata.
 */

ini_set('display_errors', '0');
header('Content-Type: application/json');

require_once __DIR__ . '/../lib/ws-auth.php';
require_once __DIR__ . '/../lib/ws-sites.php';
require_once __DIR__ . '/../_pages.php';

/* L'editor parla JSON, come quello delle schede: il documento è un oggetto e
 * spedirlo dentro un campo di modulo vorrebbe dire codificarlo due volte. */
$in = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($in)) $in = $_POST;

$user = ws_authenticate((string)($in['credential'] ?? ''));
if ($user === null) {
    http_response_code(401);
    echo json_encode(['error' => 'Autenticazione Google fallita o token scaduto. Accedi di nuovo.']);
    exit;
}
if (!in_array($user['role'] ?? '', ['admin', 'super-admin'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Le pagine sono riservate agli amministratori. Il tuo ruolo: ' . ($user['role'] ?: 'nessuno') . '.']);
    exit;
}

$action = (string)($in['action'] ?? '');
$siteId = (string)($in['site'] ?? '');
$root = ws_admin_site_path($siteId);
if ($root === null) {
    http_response_code(400);
    echo json_encode(['error' => "Radice sconosciuta: $siteId"]);
    exit;
}

/* L'@id arriva dalla richiesta e diventa un percorso: si accetta solo la forma
 * di un @id, e mai due punti in fila. Il controllo sta qui, all'ingresso, e non
 * sparso fra le azioni - dove prima o poi ne manca uno. */
$id = trim((string)($in['id'] ?? ''), '/');
if ($id === '' || !preg_match('#^[a-z0-9][a-z0-9/_-]*$#i', $id) || strpos($id, '..') !== false) {
    http_response_code(400);
    echo json_encode(['error' => "Identificativo non valido: $id"]);
    exit;
}
$dir = "$root/$id";
$file = "$dir/index.json";

/* ---------------------------------------------------------------------------
 * Caricare
 * ------------------------------------------------------------------------- */

if ($action === 'load') {
    if (!is_file($file)) {
        http_response_code(404);
        echo json_encode(['error' => "Non trovo $id/index.json"]);
        exit;
    }
    $doc = json_decode((string)@file_get_contents($file), true);
    if (!is_array($doc)) {
        http_response_code(422);
        echo json_encode(['error' => "$id/index.json non è JSON leggibile"]);
        exit;
    }
    echo json_encode([
        'success' => true,
        'doc' => $doc,
        'site' => $siteId,
        'theme' => ws_pages_theme($root),
        /* La pagina pubblica, per il pulsante «vedi»: la sa la mappa, non
         * questo file, e comporla qui sarebbe indovinare. */
        'mount' => ws_pages_mount($siteId),
    ]);
    exit;
}

/* ---------------------------------------------------------------------------
 * Salvare
 * ------------------------------------------------------------------------- */

if ($action !== 'save') {
    http_response_code(400);
    echo json_encode(['error' => "Azione sconosciuta: $action"]);
    exit;
}

$doc = $in['jsonld'] ?? null;
if (is_string($doc)) $doc = json_decode($doc, true);
if (!is_array($doc)) {
    http_response_code(400);
    echo json_encode(['error' => 'Documento mancante o illeggibile.']);
    exit;
}

/* Questo endpoint salva PAGINE. Un documento che non è una pagina arrivato qui
 * è un errore del client, e scriverlo lo metterebbe in un posto dove l'elenco
 * delle pagine non lo troverà mai più. */
$tipi = (array)($doc['@type'] ?? []);
if (!in_array('WebPage', $tipi, true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Questo non è una pagina: manca WebPage fra i @type.']);
    exit;
}
if (trim((string)($doc['wspath'] ?? '')) === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Manca l’indirizzo (wspath): senza, la pagina non sta sulla mappa.']);
    exit;
}

$esisteva = is_file($file);

/*
 * «Aggiungi pagina» non sovrascrive mai.
 *
 * Il client dice `creating` quando sta creando, e allora una cartella che c'è
 * già è un errore, non un salvataggio. Senza questo controllo bastava scrivere
 * `/chi-siamo` in una pagina nuova per cancellare quella che esiste: il
 * confronto delle date non protegge, perché chi crea non ha una data di
 * partenza da confrontare.
 */
if (!empty($in['creating']) && $esisteva) {
    http_response_code(409);
    echo json_encode([
        'error' => "Esiste già una pagina in «{$id}». Aprila dall’elenco, oppure dalle un altro indirizzo.",
        'exists' => true, 'id' => $id,
    ]);
    exit;
}

/*
 * Due che salvano la stessa pagina.
 *
 * Il client manda la `dateModified` che aveva quando l'ha aperta. Se sul disco
 * ce n'è una diversa, qualcun altro ha salvato nel frattempo e sovrascrivere
 * vorrebbe dire cancellare il suo lavoro senza dirlo a nessuno dei due. Si
 * rifiuta e si dice perché; `force` è la risposta di chi ha guardato e decide
 * lo stesso.
 */
if ($esisteva && empty($in['force'])) {
    $suDisco = json_decode((string)@file_get_contents($file), true);
    $attesa = (string)($in['baseModified'] ?? '');
    $trovata = (string)($suDisco['dateModified'] ?? '');
    if ($attesa !== '' && $trovata !== '' && $attesa !== $trovata) {
        http_response_code(409);
        echo json_encode([
            'error' => 'La pagina è stata salvata da qualcun altro mentre la modificavi.',
            'conflict' => true, 'theirs' => $trovata, 'yours' => $attesa,
        ]);
        exit;
    }
}

/*
 * La `query`: la compone il server, sempre.
 *
 * Porta il tema del sito, che l'editor non sa e non deve sapere, il template
 * che l'editor sceglie, e il percorso del contenuto, che è dove il file sta.
 * Scritta dal client, resterebbe giusta fino al giorno in cui il sito cambia
 * tema - e da quel giorno ogni pagina salvata riporterebbe il tema vecchio.
 */
$theme = ws_pages_theme($root);
$template = preg_match('/^[a-z0-9_-]+$/i', (string)($in['template'] ?? '')) ? $in['template'] : 'page';
if ($theme !== '') {
    $doc['query'] = "/?theme=$theme&template=$template&content=$siteId/$id";
}
$doc['@id'] = $id;

/*
 * La lingua, quando non c'è.
 *
 * Una pagina senza `inLanguage` arriva al tema e lì muore: le testate si
 * cercano nella cartella della lingua, e senza quel campo il CMS non sa quale
 * sia. L'ho visto salvando la prima pagina di prova — risponde l'instradamento,
 * e poi «Attempt to read property "url" on null».
 *
 * Il valore lo sa la radice, che si chiama `sito/it_IT`: si ricava da lì e non
 * si chiede all'editor, così una pagina creata da qualunque cosa parli con
 * questo endpoint nasce comunque in grado di rispondere.
 */
if (trim((string)($doc['inLanguage'] ?? '')) === '') {
    $locale = basename($root);
    if (preg_match('/^[a-z]{2}_[A-Z]{2}$/', $locale)) {
        $doc['inLanguage'] = str_replace('_', '-', $locale);
    }
}

if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
    http_response_code(500);
    echo json_encode(['error' => "Non posso creare la cartella $id"]);
    exit;
}

$json = json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
if (@file_put_contents($file, $json) === false) {
    http_response_code(500);
    echo json_encode(['error' => "Non posso scrivere $id/index.json"]);
    exit;
}

$rep = ['success' => true, 'created' => !$esisteva, 'id' => $id, 'dateModified' => (string)($doc['dateModified'] ?? '')];

/* Il gemello, subito. È pigro e si farebbe da sé alla prima richiesta, ma
 * farlo qui significa che un errore di conversione si vede adesso, da chi ha
 * appena salvato, e non fra una settimana da un visitatore. */
require_once __DIR__ . '/../_refresh-content.php';
$rep['twin'] = ws_content_ensure_xml($file) !== '' ? 'ok' : 'non riuscito';

/* Le mappe: la lingua, il sito, e il sitemap.xml per i motori. Non sono pigre,
 * e senza questo passaggio una pagina nuova non risponde e una che ha cambiato
 * indirizzo risponde a quello vecchio. */
require_once __DIR__ . '/../refresh-sitemaps.php';
$mappe = ws_refresh_sitemaps($root, true);
$rep['sitemap'] = (string)($mappe['map']['status'] ?? '');
$rep['public'] = (string)($mappe['public']['why'] ?? '');

echo json_encode($rep);
exit;
