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
