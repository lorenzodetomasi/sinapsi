<?php
// Indice di deduplica google_place_id → @id per tutti i JSON di Meetoo che hanno
// un google_place_id (places + organizations). Un solo indice GLOBALE: il place_id
// è unico a prescindere dal tipo, e la ricerca è per place_id (che non contiene il
// CAP) → dividere per CAP costringerebbe a scandire tutti i file, quindi è inutile.
// L'indice è O(1) in lookup ed è interamente RICOSTRUIBILE (rebuild-index.php),
// perciò vive sotto ws-custom (gitignored) e non è una fonte di verità primaria.

const WS_MEETOO_ROOT = __DIR__ . '/../../ws-custom/contents/meetoo/it_IT';
// Cartelle che possono contenere entità con google_place_id.
const WS_INDEX_SCAN_DIRS = ['places', 'organizations'];

function ws_index_path() {
    return WS_MEETOO_ROOT . '/_index/google-places.json';
}

// Estrae il google_place_id da una mainEntity (accetta entrambe le grafie storiche).
function ws_index_place_id($entity) {
    if (!is_array($entity)) return '';
    $gid = $entity['meetoo:google_place_id'] ?? $entity['meetoo:googlePlaceId'] ?? '';
    return is_string($gid) ? trim($gid) : '';
}

// Carica l'indice come array associativo place_id → voce. [] se assente/illeggibile.
function ws_index_load() {
    $f = ws_index_path();
    if (!is_file($f)) return [];
    $j = json_decode((string)file_get_contents($f), true);
    return is_array($j) ? $j : [];
}

// Cerca un place_id. Ritorna la voce {@id,name,@type} o null.
function ws_index_lookup($placeId) {
    if ($placeId === '') return null;
    $idx = ws_index_load();
    return $idx[$placeId] ?? null;
}

// Inserisce/aggiorna una voce con lock esclusivo. Ritorna true in caso di scrittura.
function ws_index_upsert($placeId, $id, $name, $type) {
    if ($placeId === '' || $id === '') return false;
    $dir = dirname(ws_index_path());
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $fp = @fopen(ws_index_path(), 'c+');
    if (!$fp) return false;
    $ok = false;
    if (flock($fp, LOCK_EX)) {
        $raw = stream_get_contents($fp);
        $idx = json_decode((string)$raw, true);
        if (!is_array($idx)) $idx = [];
        $idx[$placeId] = ['@id' => $id, 'name' => $name, '@type' => $type];
        ksort($idx);
        rewind($fp);
        ftruncate($fp, 0);
        fwrite($fp, json_encode($idx, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        fflush($fp);
        flock($fp, LOCK_UN);
        $ok = true;
    }
    fclose($fp);
    return $ok;
}

// Scrive l'indice completo su disco (crea la cartella se serve). Ritorna bool.
function ws_index_save($idx) {
    $dir = dirname(ws_index_path());
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    return @file_put_contents(
        ws_index_path(),
        json_encode($idx, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    ) !== false;
}

// ── Indice "Gruppi" ────────────────────────────────────────────────────────
// Elenco delle entità-gruppo per la home: TUTTE le organizations + i LocalBusiness
// (places) che si sono marcati `meetoo:isGroup` perché offrono esperienze
// collettive/gratuite (es. La Farfalla, Sognalibri, Feltrinelli). Derivato e
// interamente ricostruibile, come google-places.json.
function ws_gruppi_path() {
    return WS_MEETOO_ROOT . '/_index/gruppi.json';
}

// Estrae un URL "mappa" da hasMap/geo, altrimenti '' (link cliente-side lo integra).
function ws_gruppi_map_url($e) {
    $hm = $e['hasMap'] ?? null;
    if (is_string($hm) && $hm !== '') return $hm;
    if (is_array($hm) && !empty($hm['url'])) return $hm['url'];
    $geo = $e['geo'] ?? null;
    if (is_array($geo) && isset($geo['latitude'], $geo['longitude'])) {
        return 'https://www.google.com/maps/search/?api=1&query=' . $geo['latitude'] . ',' . $geo['longitude'];
    }
    return '';
}

// Ricostruisce l'elenco gruppi. Ritorna un array di voci ordinate per nome.
function ws_gruppi_rebuild() {
    $out = [];
    foreach (WS_INDEX_SCAN_DIRS as $sub) {
        $base = WS_MEETOO_ROOT . '/' . $sub;
        if (!is_dir($base)) continue;
        $isOrgDir = ($sub === 'organizations');
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->getFilename() !== 'index.json') continue;
            $j = json_decode((string)file_get_contents($file->getPathname()), true);
            if (!is_array($j)) continue;
            $e = $j['mainEntity'] ?? $j;
            // Regola d'inclusione: tutte le organizations, i places solo se marcati.
            if (!$isOrgDir && empty($e['meetoo:isGroup'])) continue;
            $id = $e['@id'] ?? '';
            if ($id === '') continue;
            $type = $e['@type'] ?? ($isOrgDir ? 'Organization' : 'LocalBusiness');
            $out[] = [
                '@id'   => $id,
                'name'  => $e['name'] ?? basename($id),
                '@type' => is_array($type) ? ($type[0] ?? 'Organization') : $type,
                'kind'  => $isOrgDir ? 'org' : 'business',
                'key'   => basename($id),
                'url'   => is_string($e['url'] ?? null) ? $e['url'] : '',
                'map'   => ws_gruppi_map_url($e),
                'description' => is_string($e['description'] ?? null) ? $e['description'] : '',
            ];
        }
    }
    usort($out, fn($a, $b) => strcasecmp($a['name'], $b['name']));
    return $out;
}

// Scrive gruppi.json (crea la cartella se serve). Ritorna bool.
function ws_gruppi_save($list) {
    $dir = dirname(ws_gruppi_path());
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    return @file_put_contents(
        ws_gruppi_path(),
        json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    ) !== false;
}

// ── Indice "Entità" ────────────────────────────────────────────────────────
// Elenco di TUTTO ciò che può organizzare un evento o ospitarlo: organizations +
// places/localBusiness, senza il filtro `isGroup` di gruppi.json (un'attività può
// organizzare senza offrire esperienze collettive). Serve all'editor eventi per
// scegliere l'organizzatore dall'elenco invece di digitarne l'@id a memoria, e per
// risalire al nome partendo dall'@id. Derivato e ricostruibile come gli altri.
function ws_entities_path() {
    return WS_MEETOO_ROOT . '/_index/entities.json';
}

// addressRegion nei dati è a volte una stringa, a volte ["Lazio","RM"]: qui si
// tiene la prima forma leggibile, senza inventare.
function ws_entities_region($address) {
    $r = is_array($address) ? ($address['addressRegion'] ?? '') : '';
    if (is_array($r)) $r = $r[0] ?? '';
    return is_string($r) ? $r : '';
}

function ws_entities_rebuild() {
    $out = [];
    foreach (WS_INDEX_SCAN_DIRS as $sub) {
        $base = WS_MEETOO_ROOT . '/' . $sub;
        if (!is_dir($base)) continue;
        $isOrgDir = ($sub === 'organizations');
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->getFilename() !== 'index.json') continue;
            $j = json_decode((string)file_get_contents($file->getPathname()), true);
            if (!is_array($j)) continue;
            $e = $j['mainEntity'] ?? $j;
            $id = $e['@id'] ?? '';
            if ($id === '') continue;
            $type = $e['@type'] ?? ($isOrgDir ? 'Organization' : 'Place');
            $addr = $e['address'] ?? [];
            $out[] = [
                '@id'      => $id,
                'name'     => $e['name'] ?? basename($id),
                '@type'    => is_array($type) ? ($type[0] ?? 'Organization') : $type,
                'kind'     => $isOrgDir ? 'org' : 'business',
                'isGroup'  => !empty($e['meetoo:isGroup']),
                'locality' => is_array($addr) && is_string($addr['addressLocality'] ?? null) ? $addr['addressLocality'] : '',
                'region'   => ws_entities_region($addr),
            ];
        }
    }
    usort($out, fn($a, $b) => strcasecmp($a['name'], $b['name']));
    return $out;
}

function ws_entities_save($list) {
    $dir = dirname(ws_entities_path());
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    return @file_put_contents(
        ws_entities_path(),
        json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    ) !== false;
}

// Ricostruisce l'indice da zero scandendo i JSON. Ritorna [indice, conflitti].
// Un conflitto = stesso place_id su @id diversi (segnala possibili duplicati).
function ws_index_rebuild() {
    $idx = [];
    $conflicts = [];
    foreach (WS_INDEX_SCAN_DIRS as $sub) {
        $base = WS_MEETOO_ROOT . '/' . $sub;
        if (!is_dir($base)) continue;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->getFilename() !== 'index.json') continue;
            $j = json_decode((string)file_get_contents($file->getPathname()), true);
            if (!is_array($j)) continue;
            $e = $j['mainEntity'] ?? $j;
            $gid = ws_index_place_id($e);
            if ($gid === '') continue;
            $id = $e['@id'] ?? '';
            if ($id === '') continue;
            $entry = ['@id' => $id, 'name' => $e['name'] ?? '', '@type' => $e['@type'] ?? ''];
            if (isset($idx[$gid]) && $idx[$gid]['@id'] !== $id) {
                $conflicts[$gid][] = $idx[$gid]['@id'];
                $conflicts[$gid][] = $id;
            }
            $idx[$gid] = $entry;
        }
    }
    ksort($idx);
    return [$idx, $conflicts];
}
