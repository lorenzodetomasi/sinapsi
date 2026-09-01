<?php
/*
 * Gestione utenti — chi c'è, che ruolo ha, quali gruppi gestisce.
 *
 * Finora i ruoli si cambiavano aprendo `users/<uid>/index.xml` con un editor di
 * testo, e chi gestisce un gruppo si scriveva a mano nel file del gruppo. Sono le
 * due leve che decidono chi può fare che cosa su tutto il sito: tenerle in un file
 * vuol dire che le può muovere solo chi sa dove sta il file.
 *
 * DUE COSE DIVERSE, e la pagina le tiene separate perché lo sono:
 *   - il RUOLO dice che cosa una persona può fare in generale (entrare in
 *     Gestione, amministrare). Sta sull'utente, e si cambia qui.
 *   - GESTIRE dice per conto di CHI o di CHE COSA può pubblicare. Un gruppo
 *     (`meetoo:manager` sul gruppo) oppure un singolo evento (`contributor`
 *     sull'evento). Si cambia da qui, che è dove ci si fa la domanda «e questa
 *     persona, che cosa tocca?».
 *
 * LA REGOLA CHE FA RISPARMIARE LAVORO: chi gestisce un gruppo gestisce già tutti
 * gli eventi che quel gruppo organizza — anche quelli in cui è uno degli
 * organizzatori e non l'unico. Non si aggiungono uno per uno, e infatti non si
 * possono nemmeno scegliere: sarebbe un elenco che si allunga da solo a ogni
 * evento nuovo e che nessuno ricorda di accorciare quando il gruppo cambia.
 *
 * Questo file è ANCHE il suo endpoint JSON (POST):
 *   action=auth     → chi sei
 *   action=list     → l'elenco
 *   action=role     → cambia il ruolo di qualcuno (solo super-admin)
 *   action=cerca    → che cosa si può ancora affidare a una persona
 *   action=gestisce → affida o toglie un gruppo o un evento
 */

ini_set('display_errors', '0');

require_once __DIR__ . '/../lib/ws-auth.php';

const RUOLI = ['logged-visitor', 'verified-visitor', 'user', 'client', 'admin', 'super-admin'];

function utenti_base(): string { return __DIR__ . '/../../ws-custom/contents/meetoo/it_IT'; }

/** Il nome di una persona, dal suo profilo. Vuoto se non l'ha mai scritto. */
function utenti_persona(string $uid): array {
    $f = utenti_base() . "/persons/$uid/index.xml";
    if (!is_file($f)) return ['name' => '', 'alternateName' => ''];
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    libxml_use_internal_errors(true);
    $ok = $dom->load($f, LIBXML_NONET);
    libxml_clear_errors();
    if (!$ok) return ['name' => '', 'alternateName' => ''];
    $x = simplexml_import_dom($dom);
    return [
        'name' => $x ? trim((string)$x->name) : '',
        'alternateName' => ($x && isset($x->alternateName)) ? trim((string)$x->alternateName) : '',
    ];
}

/** Il documento dell'utente, com'è sul disco (spazi compresi: si riscrive un nodo
 *  solo, e il resto del file deve restare identico a com'era). */
function utenti_dom(string $uid): ?DOMDocument {
    $f = utenti_base() . "/users/$uid/index.xml";
    if (!is_file($f)) return null;
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = true;   // NON toccare la formattazione del resto
    libxml_use_internal_errors(true);
    $ok = $dom->load($f, LIBXML_NONET);   // niente xinclude: qui serve il file, non l'albero risolto
    libxml_clear_errors();
    return $ok ? $dom : null;
}

function utenti_ruolo(DOMDocument $dom): string {
    $n = $dom->getElementsByTagName('role')->item(0);
    return $n ? trim($n->textContent) : '';
}

/** L'entità dentro un file di contenuto, o null. */
function utenti_entita(string $file): ?array {
    $j = json_decode((string)@file_get_contents($file), true);
    if (!is_array($j)) return null;
    $e = $j['mainEntity'] ?? $j;
    return is_array($e) ? $e : null;
}

/** Tutti i gruppi: organizzazioni e luoghi. Un teatro organizza quanto
 *  un'associazione, e chi lo cura deve poterne gestire la scheda. */
function utenti_tutti_i_gruppi(bool $rileggi = false): array {
    static $c = null;
    if ($c !== null && !$rileggi) return $c;
    $c = [];
    foreach (ws_gruppi_glob(utenti_base()) as $f) {
        $e = utenti_entita($f);
        $id = $e ? (string)($e['@id'] ?? '') : '';
        if ($id === '') continue;
        // Le liste con regola (il lungomare, le categorie di zona) stanno sotto
        // places/ ma non sono qualcuno che organizza: non si gestiscono.
        $tipi = (array)($e['@type'] ?? []);
        if (in_array('ItemList', $tipi, true) || ($e['@type'] ?? '') === 'ItemList') continue;
        $c[$id] = [
            'id' => $id,
            'name' => (string)($e['name'] ?? $id),
            'tipo' => 'gruppo',
            'file' => $f,
            'gestori' => ws_ref_ids($e['meetoo:manager'] ?? null),
        ];
    }
    return $c;
}

/** Tutti gli eventi, con TUTTI i loro organizzatori — non solo il primo, che è
 *  quello che porta l'indice: «Ostia LIBRO» ne ha tre, e la regola per cui chi
 *  gestisce un gruppo gestisce i suoi eventi vale per ognuno dei tre. */
function utenti_tutti_gli_eventi(bool $rileggi = false): array {
    static $c = null;
    if ($c !== null && !$rileggi) return $c;
    $c = [];
    foreach (glob(utenti_base() . '/events/*/index.json') ?: [] as $f) {
        $e = utenti_entita($f);
        $id = $e ? (string)($e['@id'] ?? '') : '';
        if ($id === '') continue;
        $tipi = (array)($e['@type'] ?? []);
        $c[$id] = [
            'id' => $id,
            'name' => (string)($e['name'] ?? $id),
            'tipo' => 'evento',
            'serie' => in_array('EventSeries', $tipi, true),
            'file' => $f,
            'organizza' => ws_ref_ids($e['organizer'] ?? null),
            'aiutanti' => ws_ref_ids($e['contributor'] ?? null),
        ];
    }
    return $c;
}

/**
 * Che cosa gestisce questa persona.
 *
 * DUE STRADE, e una sola è da scrivere. Chi gestisce un GRUPPO gestisce già tutti
 * gli eventi che quel gruppo organizza — non serve dirlo evento per evento, e
 * dirlo sarebbe peggio che inutile: un elenco che si allunga da solo a ogni evento
 * nuovo, e che nessuno ricorda di accorciare quando il gruppo cambia. L'altra
 * strada è il singolo evento (`contributor`): serve quando il permesso è proprio
 * quello — il Teatro del Lido che affida una serata sola a qualcuno di fuori.
 */
function utenti_gestisce(string $uid): array {
    $me = "users/$uid";
    $out = [];
    $mieiGruppi = [];
    foreach (utenti_tutti_i_gruppi() as $g) {
        if (in_array($me, $g['gestori'], true)) {
            $mieiGruppi[] = $g['id'];
            $out[] = ['id' => $g['id'], 'name' => $g['name'], 'tipo' => 'gruppo'];
        }
    }
    foreach (utenti_tutti_gli_eventi() as $ev) {
        $coperto = (bool)array_intersect($mieiGruppi, $ev['organizza']);
        if (!in_array($me, $ev['aiutanti'], true)) continue;
        $out[] = [
            'id' => $ev['id'], 'name' => $ev['name'], 'tipo' => 'evento',
            'serie' => $ev['serie'],
            // Segnato, non nascosto: se c'è ed è già coperto dal gruppo, è una
            // riga che si può togliere — ma toglierla è una decisione, non un
            // riordino che facciamo noi alle sue spalle.
            'coperto' => $coperto,
        ];
    }
    return $out;
}

/** I gruppi di una persona, dalle liste di questa pagina. NON si usa qui
 *  `ws_gruppi_gestiti()`: quella memorizza per tutta la richiesta — giusto quando
 *  serve a decidere un permesso, sbagliato subito dopo aver scritto, perché
 *  risponderebbe con la fotografia di prima. */
function utenti_miei_gruppi(string $uid): array {
    $me = "users/$uid";
    $out = [];
    foreach (utenti_tutti_i_gruppi() as $g) {
        if (in_array($me, $g['gestori'], true)) $out[] = $g['id'];
    }
    return $out;
}

/** Gli eventi che questa persona gestisce PERCHÉ gestisce il gruppo. Non si
 *  scrivono da nessuna parte: si calcolano, ed è il punto. */
function utenti_eventi_coperti(array $mieiGruppi): array {
    $out = [];
    if (!$mieiGruppi) return $out;
    foreach (utenti_tutti_gli_eventi() as $ev) {
        if (array_intersect($mieiGruppi, $ev['organizza'])) {
            $out[] = ['id' => $ev['id'], 'name' => $ev['name'], 'serie' => $ev['serie']];
        }
    }
    return $out;
}

/**
 * Che cosa si può ANCORA affidare a questa persona.
 *
 * Fuori restano tre cose: i gruppi che già gestisce, gli eventi di cui è già
 * aiutante, e — la regola che conta — tutti gli eventi organizzati da un gruppo
 * che gestisce. Quelli non si offrono perché li ha già: proporli vorrebbe dire
 * far scrivere a mano una cosa che il sistema sa da sé, e poi ritrovarsela lì il
 * giorno in cui il gruppo cambia.
 */
function utenti_affidabili(string $uid, string $q = ''): array {
    $me = "users/$uid";
    $q = mb_strtolower(trim($q));
    $copertiId = array_column(utenti_eventi_coperti(utenti_miei_gruppi($uid)), 'id');
    $out = [];
    foreach (utenti_tutti_i_gruppi() as $g) {
        if (in_array($me, $g['gestori'], true)) continue;
        $out[] = ['id' => $g['id'], 'name' => $g['name'], 'tipo' => 'gruppo'];
    }
    foreach (utenti_tutti_gli_eventi() as $ev) {
        if (in_array($me, $ev['aiutanti'], true)) continue;
        if (in_array($ev['id'], $copertiId, true)) continue;
        $out[] = ['id' => $ev['id'], 'name' => $ev['name'], 'tipo' => 'evento', 'serie' => $ev['serie']];
    }
    if ($q !== '') {
        $out = array_values(array_filter($out, function ($v) use ($q) {
            return mb_strpos(mb_strtolower($v['name']), $q) !== false
                || mb_strpos(mb_strtolower($v['id']), $q) !== false;
        }));
    }
    usort($out, fn($a, $b) => [$a['tipo'] !== 'gruppo', mb_strtolower($a['name'])] <=> [$b['tipo'] !== 'gruppo', mb_strtolower($b['name'])]);
    return $out;
}

/** Aggiunge o toglie `users/<uid>` da un campo di un contenuto. Scrive il file
 *  intero, ma cambiando una chiave sola: tutto il resto resta com'era. */
function utenti_scrivi_riferimento(string $file, string $campo, string $uid, bool $aggiungi): bool {
    $j = json_decode((string)@file_get_contents($file), true);
    if (!is_array($j)) return false;
    $dentro = isset($j['mainEntity']) && is_array($j['mainEntity']);
    $e = $dentro ? $j['mainEntity'] : $j;
    $me = "users/$uid";
    $attuali = array_values(array_filter(ws_ref_ids($e[$campo] ?? null), fn($x) => $x !== $me));
    if ($aggiungi) $attuali[] = $me;
    if ($attuali) {
        $e[$campo] = array_map(fn($id) => ['@type' => 'Person', '@id' => $id], $attuali);
    } else {
        unset($e[$campo]);
    }
    if ($dentro) $j['mainEntity'] = $e; else $j = $e;
    return @file_put_contents($file, json_encode($j, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) !== false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $user = ws_authenticate($_POST['credential'] ?? '');
    if ($user === null) {
        http_response_code(401);
        echo json_encode(['error' => 'Autenticazione Google fallita o token scaduto. Accedi di nuovo.']);
        exit;
    }
    $action = $_POST['action'] ?? '';
    $sonoAdmin = in_array($user['role'], ['admin', 'super-admin'], true);

    if ($action === 'auth') {
        echo json_encode(['uid' => $user['uid'], 'email' => $user['email'], 'role' => $user['role'], 'canAdmin' => $sonoAdmin]);
        exit;
    }

    if (!$sonoAdmin) {
        http_response_code(403);
        echo json_encode(['error' => "Solo amministratori (ruolo attuale: {$user['role']})."]);
        exit;
    }

    if ($action === 'list') {
        $out = [];
        foreach (scandir(utenti_base() . '/users') ?: [] as $voce) {
            if (!preg_match('/^\d{6,}$/', $voce)) continue;
            $dom = utenti_dom($voce);
            if (!$dom) continue;
            $p = utenti_persona($voce);
            $ultimo = $dom->getElementsByTagName('last_login')->item(0);
            $out[] = [
                'uid' => $voce,
                'role' => utenti_ruolo($dom),
                'name' => $p['name'],
                'alternateName' => $p['alternateName'],
                'lastLogin' => $ultimo ? trim($ultimo->textContent) : '',
                'gestisce' => utenti_gestisce($voce),
                'coperti' => utenti_eventi_coperti(utenti_miei_gruppi($voce)),
                'io' => $voce === $user['uid'],
            ];
        }
        usort($out, function ($a, $b) {
            $peso = function ($r) { $i = array_search($r, RUOLI, true); return $i === false ? -1 : $i; };
            return $peso($b['role']) <=> $peso($a['role']) ?: strcasecmp($a['name'] ?: $a['uid'], $b['name'] ?: $b['uid']);
        });
        echo json_encode(['users' => $out, 'ruoli' => RUOLI]);
        exit;
    }

    if ($action === 'role') {
        /* Il ruolo lo cambia solo un super-admin. Un admin vede l'elenco ma non
         * si promuove da sé: il gradino più alto lo dà chi ce l'ha già. */
        if ($user['role'] !== 'super-admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Solo un super-admin può cambiare i ruoli.']);
            exit;
        }
        $uid = preg_replace('/[^0-9]/', '', (string)($_POST['uid'] ?? ''));
        $ruolo = (string)($_POST['role'] ?? '');
        if ($uid === '' || !in_array($ruolo, RUOLI, true)) {
            http_response_code(400);
            echo json_encode(['error' => 'Utente o ruolo non valido.']);
            exit;
        }
        /* Su se stessi no. Non è paternalismo: è che togliersi il proprio ruolo è
         * l'unico errore di questa pagina che non si può correggere DA questa
         * pagina — dopo, per rientrare, servirebbe un editor di testo sul server. */
        if ($uid === $user['uid']) {
            http_response_code(400);
            echo json_encode(['error' => 'Il tuo ruolo non lo puoi cambiare da qui: chiedilo a un altro super-admin.']);
            exit;
        }
        $dom = utenti_dom($uid);
        if (!$dom) { http_response_code(404); echo json_encode(['error' => "Nessun utente $uid."]); exit; }
        $n = $dom->getElementsByTagName('role')->item(0);
        if (!$n) { http_response_code(500); echo json_encode(['error' => 'Il file di questo utente non ha un ruolo.']); exit; }
        $prima = trim($n->textContent);
        $n->nodeValue = $ruolo;
        $f = utenti_base() . "/users/$uid/index.xml";
        if (@file_put_contents($f, $dom->saveXML()) === false) {
            http_response_code(500);
            echo json_encode(['error' => 'Scrittura fallita (permessi?).']);
            exit;
        }
        echo json_encode(['success' => true, 'uid' => $uid, 'prima' => $prima, 'adesso' => $ruolo]);
        exit;
    }

    /* Che cosa si può ancora affidare a questa persona.
     *
     * Fuori dall'elenco restano: i gruppi che già gestisce, gli eventi di cui è
     * già aiutante, e — la regola che conta — TUTTI gli eventi organizzati da un
     * gruppo che gestisce. Quelli non si aggiungono perché li ha già: proporli
     * vorrebbe dire far scrivere a mano una cosa che il sistema sa da sé, e poi
     * ritrovarsela lì il giorno in cui il gruppo cambia. */
    if ($action === 'cerca') {
        $uid = preg_replace('/[^0-9]/', '', (string)($_POST['uid'] ?? ''));
        echo json_encode(['voci' => array_slice(utenti_affidabili($uid, (string)($_POST['q'] ?? '')), 0, 12)]);
        exit;
    }

    if ($action === 'gestisce') {
        $uid = preg_replace('/[^0-9]/', '', (string)($_POST['uid'] ?? ''));
        $id = trim((string)($_POST['id'] ?? ''));
        $aggiungi = ((string)($_POST['on'] ?? '1')) === '1';
        if ($uid === '' || $id === '') {
            http_response_code(400); echo json_encode(['error' => 'Utente o contenuto non valido.']); exit;
        }
        $gruppi = utenti_tutti_i_gruppi();
        $eventi = utenti_tutti_gli_eventi();
        if (isset($gruppi[$id])) {
            // Un gruppo si GESTISCE: `meetoo:manager`, ed è la strada che si porta
            // dietro tutti i suoi eventi.
            $ok = utenti_scrivi_riferimento($gruppi[$id]['file'], 'meetoo:manager', $uid, $aggiungi);
        } elseif (isset($eventi[$id])) {
            if ($aggiungi) {
                $miei = utenti_miei_gruppi($uid);
                if (array_intersect($miei, $eventi[$id]['organizza'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Questo evento è già suo: lo organizza un gruppo che gestisce.']);
                    exit;
                }
            }
            // Un singolo evento si affida: `contributor`.
            $ok = utenti_scrivi_riferimento($eventi[$id]['file'], 'contributor', $uid, $aggiungi);
        } else {
            http_response_code(404); echo json_encode(['error' => "Non trovo '$id'."]); exit;
        }
        if (!$ok) { http_response_code(500); echo json_encode(['error' => 'Scrittura fallita (permessi?).']); exit; }
        // Riletti dal disco: il file è appena cambiato, e le liste in memoria no.
        utenti_tutti_i_gruppi(true);
        utenti_tutti_gli_eventi(true);
        $miei = utenti_miei_gruppi($uid);
        echo json_encode([
            'success' => true,
            'gestisce' => utenti_gestisce($uid),
            'coperti' => utenti_eventi_coperti($miei),
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Azione non valida.']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Utenti e ruoli — Meetoo</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Roboto+Slab:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap">
  <link rel="stylesheet" href="../../ws-custom/themes/meetoo/meetoo.css">
  <style>
    /* Solo ciò che è proprio di questa pagina: il resto è in meetoo.css. */
    .u-tab { width: 100%; border-collapse: collapse; }
    .u-tab th {
      text-align: left; padding: 8px 12px; font-size: .72rem; letter-spacing: .08em;
      text-transform: uppercase; color: var(--color-hint); border-bottom: 1px solid var(--color-line);
      white-space: nowrap;
    }
    .u-tab td { padding: 12px; border-bottom: 1px solid var(--color-line); vertical-align: top; }
    .u-tab tr:last-child td { border-bottom: none; }
    .u-nome { font-weight: 600; }
    .u-uid { font-family: 'Source Code Pro', ui-monospace, monospace; font-size: .78rem; color: var(--color-hint); }
    .u-io { font-size: .68rem; text-transform: uppercase; letter-spacing: .06em; color: var(--color-link); margin-left: 6px; }
    .u-gestisce { display: flex; flex-direction: column; gap: 8px; min-width: 22rem; }
    .u-cose, .u-auto-riga { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
    .u-cosa {
      display: inline-flex; align-items: center; gap: 5px;
      font-size: .82rem; padding: 3px 6px 3px 10px; border-radius: 999px;
      border: 1px solid var(--color-line);
    }
    .u-cosa .material-symbols-outlined { font-size: 16px; color: var(--color-hint); }
    .u-cosa a { color: var(--color-text); text-decoration: none; }
    .u-cosa a:hover { color: var(--color-link); }
    /* Doppia: c'è scritta e sarebbe già sua per via del gruppo. Non la togliamo
       noi — si segnala, e decide chi guarda. */
    .u-doppia { border-color: var(--color-warning, #b26a00); }
    .u-doppia::after { content: 'già dal gruppo'; font-size: .68rem; color: var(--color-hint); }
    /* Automatica: nessuna crocetta, perché non c'è niente di scritto da togliere. */
    .u-auto { border-style: dashed; color: var(--color-hint); padding-right: 10px; }
    .u-auto .u-togli { display: none; }
    .u-auto-nota { font-size: .72rem; color: var(--color-hint); }
    .u-togli {
      display: inline-flex; align-items: center; justify-content: center;
      width: 18px; height: 18px; padding: 0; border: none; border-radius: 50%;
      background: transparent; color: var(--color-hint); cursor: pointer;
    }
    .u-togli:hover { background: var(--color-line); color: var(--color-danger, #d93025); }
    .u-togli .material-symbols-outlined { font-size: 14px; }
    .u-aggiungi { position: relative; }
    .u-cerca {
      width: 100%; background: var(--color-background-section1); color: var(--color-text);
      border: 1px solid var(--color-line); border-radius: 999px; padding: 5px 12px; font: inherit; font-size: .82rem;
    }
    .u-cerca:focus { outline: none; border-color: var(--color-link); }
    .u-risultati {
      position: absolute; z-index: 20; left: 0; right: 0; top: calc(100% + 4px);
      margin: 0; padding: 4px; list-style: none; max-height: 15rem; overflow: auto;
      background: var(--color-background-section1); border: 1px solid var(--color-line);
      border-radius: 12px; box-shadow: 0 12px 32px rgba(0,0,0,.35);
    }
    .u-risultati li {
      display: flex; align-items: center; gap: 8px; padding: 6px 10px;
      border-radius: 8px; cursor: pointer; font-size: .85rem;
    }
    .u-risultati li:hover { background: var(--color-background-section2); }
    .u-risultati .material-symbols-outlined { font-size: 16px; color: var(--color-hint); }
    .u-risultati .u-id { margin-left: auto; font-size: .72rem; color: var(--color-hint); }
    .u-risultati .u-vuoto { color: var(--color-hint); cursor: default; }
    .u-nessuno { color: var(--color-hint); font-size: .85rem; }
    .u-tutto { display: inline-flex; align-items: center; gap: 6px; color: var(--color-link); font-size: .85rem; }
    .u-tutto .material-symbols-outlined { font-size: 18px; }
    .u-tab select {
      background: var(--color-background-section1); color: var(--color-text);
      border: 1px solid var(--color-line); border-radius: 999px; padding: 6px 12px; font: inherit;
    }
    .u-tab select:disabled { opacity: .5; }
    .u-nota { color: var(--color-hint); max-width: 46rem; margin: 0 0 20px; }
    .u-esito { margin-left: 10px; font-size: .85rem; }
    .u-esito.ok { color: var(--color-link); }
    .u-esito.ko { color: var(--color-danger, #d93025); }
    #gate { display: none; text-align: center; padding: 60px 20px; color: var(--color-hint); }
    #gate .material-symbols-outlined { font-size: 40px; }
    #app { display: none; }
    #app.on { display: block; }
  </style>
</head>
<body>
  <div class="wrap">
    <div id="gate">
      <span class="material-symbols-outlined">lock</span>
      <p id="gate-msg">Accedi con Google (in alto a destra) per gestire gli utenti.</p>
    </div>

    <div id="app">
      <h1>Utenti e ruoli</h1>
      <p class="u-nota">Il <strong>ruolo</strong> dice che cosa una persona può fare in generale.
        <strong>Gestisce</strong> dice per conto di chi: un gruppo — e allora sono suoi anche tutti
        gli eventi che quel gruppo organizza, senza aggiungerli — oppure un singolo evento, quando
        il permesso è proprio quello.</p>

      <section>
        <h2 class="sec-head"><span class="material-symbols-outlined">group</span>Chi c'è <span class="count" id="u-count"></span></h2>
        <table class="u-tab">
          <thead>
            <tr><th>Persona</th><th>Ruolo</th><th>Gestisce</th><th>Ultimo accesso</th></tr>
          </thead>
          <tbody id="u-corpo"><tr><td colspan="4" class="u-nessuno">Carico…</td></tr></tbody>
        </table>
      </section>
    </div>
  </div>

  <script src="../../ws-custom/themes/meetoo/header.js"></script>
  <script>
  (function () {
    const SITE_ROOT = location.pathname.replace(/\/ws-admin\/.*/, '/');
    const SCHEDA = SITE_ROOT + 'ws-admin/places/edit/';
    const EDIT_EVENTO = SITE_ROOT + 'ws-admin/events/edit/';
    const esc = (s) => String(s == null ? '' : s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]);

    function api(action, extra) {
      const body = new URLSearchParams(Object.assign({ action }, extra || {}));
      const t = window.meetooSession && meetooSession.getToken();
      if (t) body.set('credential', t);
      return fetch(location.pathname, {
        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString(),
      }).then((r) => r.json().then((j) => ({ status: r.status, body: j }), () => ({ status: r.status, body: {} })));
    }

    let ruoli = [];
    let ioSonoSuperAdmin = false;

    /* Una cosa affidata: il nome, dove sta, e la crocetta per toglierla. Gli
       eventi che arrivano dal gruppo NON hanno crocetta — non c'è niente da
       togliere, perché non c'è niente di scritto: sono una conseguenza. */
    function pastiglia(v) {
      const dove = v.tipo === 'evento'
        ? EDIT_EVENTO + '?id=' + encodeURIComponent(v.id)
        : SCHEDA + '?id=' + encodeURIComponent(v.id);
      const icona = v.tipo === 'gruppo' ? 'groups' : (v.serie ? 'collections_bookmark' : 'event');
      return '<span class="u-cosa' + (v.coperto ? ' u-doppia' : '') + '"'
        + (v.coperto ? ' title="Già suo attraverso il gruppo: questa riga non serve"' : '') + '>'
        + '<span class="material-symbols-outlined">' + icona + '</span>'
        + '<a href="' + esc(dove) + '">' + esc(v.name || v.id) + '</a>'
        + '<button type="button" class="u-togli" data-id="' + esc(v.id) + '" title="Togli">'
        + '<span class="material-symbols-outlined">close</span></button></span>';
    }

    function automatica(v) {
      return '<span class="u-cosa u-auto" title="Perché gestisce il gruppo che lo organizza">'
        + '<span class="material-symbols-outlined">' + (v.serie ? 'collections_bookmark' : 'event') + '</span>'
        + esc(v.name || v.id) + '</span>';
    }

    function riga(u) {
      const nome = u.name || u.alternateName || '(senza nome)';
      /* Un super-admin gestisce tutto: `ws_can_edit()` gli dice di sì prima di
         guardare qualunque altra cosa. Affidargli un gruppo non aggiungerebbe
         niente, e l'elenco vuoto accanto al suo nome direbbe il falso. */
      if (u.role === 'super-admin') {
        return '<tr data-uid="' + esc(u.uid) + '">'
          + '<td><div class="u-nome">' + esc(nome) + (u.io ? '<span class="u-io">sei tu</span>' : '') + '</div>'
          + '<div class="u-uid">' + esc(u.uid) + '</div></td>'
          + '<td><select class="u-ruolo" disabled title="Il ruolo di un super-admin lo cambia un altro super-admin, dal file"><option selected>super-admin</option></select><span class="u-esito"></span></td>'
          + '<td><span class="u-tutto"><span class="material-symbols-outlined">all_inclusive</span>tutto, perché è super-admin</span></td>'
          + '<td class="u-nessuno">' + esc(u.lastLogin || '—') + '</td></tr>';
      }
      const cose = (u.gestisce || []).map(pastiglia).join('');
      const auto = (u.coperti || []).length
        ? '<div class="u-auto-riga"><span class="u-auto-nota">e quindi, in automatico:</span>'
          + u.coperti.map(automatica).join('') + '</div>'
        : '';
      const gruppi = '<div class="u-gestisce">'
        + (cose ? '<div class="u-cose">' + cose + '</div>' : '<span class="u-nessuno">niente</span>')
        + auto
        + '<div class="u-aggiungi">'
        + '<input type="text" class="u-cerca" placeholder="Aggiungi un gruppo o un evento…" autocomplete="off">'
        + '<ul class="u-risultati" hidden></ul>'
        + '</div></div>';
      // Il proprio ruolo non si tocca da qui, e un admin non promuove nessuno:
      // il menu c'è ma è spento, così si vede la regola invece di indovinarla.
      const spento = !ioSonoSuperAdmin || u.io;
      const opzioni = ruoli.map((r) =>
        '<option value="' + esc(r) + '"' + (r === u.role ? ' selected' : '') + '>' + esc(r) + '</option>').join('');
      return '<tr data-uid="' + esc(u.uid) + '">'
        + '<td><div class="u-nome">' + esc(nome) + (u.io ? '<span class="u-io">sei tu</span>' : '') + '</div>'
        + '<div class="u-uid">' + esc(u.uid) + '</div></td>'
        + '<td><select class="u-ruolo"' + (spento ? ' disabled title="Solo un super-admin può cambiare i ruoli, e non il proprio"' : '') + '>'
        + opzioni + '</select><span class="u-esito"></span></td>'
        + '<td>' + gruppi + '</td>'
        + '<td class="u-nessuno">' + esc(u.lastLogin || '—') + '</td>'
        + '</tr>';
    }

    function disegna(lista) {
      const corpo = document.getElementById('u-corpo');
      corpo.innerHTML = lista.length ? lista.map(riga).join('')
        : '<tr><td colspan="4" class="u-nessuno">Nessun utente.</td></tr>';
      document.getElementById('u-count').textContent = lista.length;
      /* La ricerca dentro la riga: si scrive, si sceglie, si affida. L'elenco lo
         filtra il server, perché è lui a sapere che cosa è già coperto dal gruppo
         — e quello che è già coperto non deve nemmeno comparire. */
      corpo.querySelectorAll('tr').forEach((tr) => {
        const uid = tr.dataset.uid;
        const inp = tr.querySelector('.u-cerca');
        const ris = tr.querySelector('.u-risultati');
        if (!inp) return;
        let attesa = 0;

        const chiudi = () => { ris.hidden = true; ris.innerHTML = ''; };
        const affida = (id, on) => {
          chiudi();
          inp.value = '';
          api('gestisce', { uid, id, on: on ? '1' : '0' }).then((r) => {
            if (r.status === 200 && r.body.success) aggiornaRiga(tr, r.body);
            else segnala(tr, r.body.error || 'non riuscito');
          });
        };

        inp.addEventListener('input', () => {
          clearTimeout(attesa);
          attesa = setTimeout(() => {
            api('cerca', { uid, q: inp.value }).then((r) => {
              const voci = (r.body && r.body.voci) || [];
              if (!voci.length) { ris.innerHTML = '<li class="u-vuoto">Niente da affidare.</li>'; ris.hidden = false; return; }
              ris.innerHTML = voci.map((v) =>
                '<li data-id="' + esc(v.id) + '"><span class="material-symbols-outlined">'
                + (v.tipo === 'gruppo' ? 'groups' : (v.serie ? 'collections_bookmark' : 'event'))
                + '</span>' + esc(v.name) + '<span class="u-id">' + esc(v.id) + '</span></li>').join('');
              ris.hidden = false;
            });
          }, 250);
        });
        inp.addEventListener('focus', () => inp.dispatchEvent(new Event('input')));
        inp.addEventListener('keydown', (e) => { if (e.key === 'Escape') chiudi(); });
        ris.addEventListener('mousedown', (e) => {
          const li = e.target.closest('li[data-id]');
          if (li) { e.preventDefault(); affida(li.dataset.id, true); }
        });
        tr.addEventListener('click', (e) => {
          const b = e.target.closest('.u-togli');
          if (b) affida(b.dataset.id, false);
        });
        document.addEventListener('mousedown', (e) => { if (!tr.contains(e.target)) chiudi(); });
      });

      corpo.querySelectorAll('.u-ruolo').forEach((sel) => {
        sel.addEventListener('change', function () {
          const tr = this.closest('tr');
          const esito = tr.querySelector('.u-esito');
          const prima = this.dataset.prima || '';
          esito.textContent = '…';
          esito.className = 'u-esito';
          api('role', { uid: tr.dataset.uid, role: this.value }).then((r) => {
            if (r.status === 200 && r.body.success) {
              esito.textContent = 'da ' + r.body.prima + ' a ' + r.body.adesso;
              esito.className = 'u-esito ok';
              this.dataset.prima = r.body.adesso;
            } else {
              esito.textContent = r.body.error || 'non riuscito';
              esito.className = 'u-esito ko';
              if (prima) this.value = prima;
            }
          });
        });
        sel.dataset.prima = sel.value;
      });
    }

    function segnala(tr, testo) {
      const e = tr.querySelector('.u-esito');
      if (!e) return;
      e.textContent = testo;
      e.className = 'u-esito ko';
    }

    /** Ridisegna la sola cella «Gestisce»: il resto della riga non è cambiato, e
     *  ricaricare tutto l'elenco farebbe perdere il posto a chi sta leggendo. */
    function aggiornaRiga(tr, dati) {
      const cella = tr.children[2];
      const cose = (dati.gestisce || []).map(pastiglia).join('');
      const auto = (dati.coperti || []).length
        ? '<div class="u-auto-riga"><span class="u-auto-nota">e quindi, in automatico:</span>'
          + dati.coperti.map(automatica).join('') + '</div>'
        : '';
      cella.querySelector('.u-cose')?.remove();
      cella.querySelector('.u-auto-riga')?.remove();
      const box = cella.querySelector('.u-gestisce');
      const vuoto = box.querySelector('.u-nessuno');
      if (vuoto) vuoto.remove();
      box.insertAdjacentHTML('afterbegin',
        (cose ? '<div class="u-cose">' + cose + '</div>' : '<span class="u-nessuno">niente</span>') + auto);
    }

    function avvia() {
      document.getElementById('gate').style.display = 'none';
      document.getElementById('app').classList.add('on');
      if (window.Meetoo) {
        Meetoo.setBreadcrumb([{ label: 'Gestione', href: SITE_ROOT + 'ws-admin/' }, { label: 'Utenti e ruoli', current: true }]);
      }
      api('list').then((r) => {
        if (r.status !== 200) { document.getElementById('u-corpo').innerHTML =
          '<tr><td colspan="4" class="u-nessuno">' + esc(r.body.error || 'Elenco non disponibile.') + '</td></tr>'; return; }
        ruoli = r.body.ruoli || [];
        disegna(r.body.users || []);
      });
    }

    (function auth() {
      if (!window.meetooSession) { setTimeout(auth, 100); return; }
      document.getElementById('gate').style.display = 'block';
      meetooSession.subscribe((user) => {
        const msg = document.getElementById('gate-msg');
        if (!user) { msg.textContent = 'Accedi con Google (in alto a destra) per gestire gli utenti.'; return; }
        api('auth').then((r) => {
          if (r.status === 200 && r.body.canAdmin) { ioSonoSuperAdmin = r.body.role === 'super-admin'; avvia(); }
          else msg.textContent = 'Il tuo account (' + (r.body.email || '') + ', ruolo ' + (r.body.role || '?') + ') non amministra gli utenti.';
        });
      });
    })();
  })();
  </script>
</body>
</html>
