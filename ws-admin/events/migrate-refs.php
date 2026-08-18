<?php
// CLI — normalizza i contenuti eventi verso le convenzioni (CONTENT-STRUCTURE.md):
//   1) @id = slug della cartella (self-@id "nudo")
//   2) superEvent = events/{slug}                 (riferimento all'evento serie)
//   3) subEvent[].@id (riferimenti) = events/{slug}
// NON rinomina cartelle e NON tocca organizer/location (già in forma {collection}/{slug};
// gli xi:include rotti restano — vanno sistemati nella pipeline, non qui).
//
// Dry-run di default (mostra cosa cambierebbe); scrive SOLO con --apply.
//   php ws-admin/events/migrate-refs.php            # anteprima
//   php ws-admin/events/migrate-refs.php --apply    # applica

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Solo da riga di comando.\n"); }
$apply = in_array('--apply', $argv, true);

$base = __DIR__ . '/../../ws-custom/contents/meetoo/it_IT';
$eventsDir = "$base/events";
if (!is_dir($eventsDir)) { fwrite(STDERR, "Cartella eventi non trovata: $eventsDir\n"); exit(1); }

// Riferimento a evento → events/{slug}. Se già contiene "/", si assume qualificato e resta.
function refToEvents($ref) {
    if (!is_string($ref) || $ref === '') return $ref;
    return strpos($ref, '/') !== false ? $ref : 'events/' . $ref;
}

// Tutti gli index.json (ricorsivo), escluso _index.
$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($eventsDir, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if ($f->getFilename() === 'index.json' && strpos($f->getPathname(), '/_index/') === false) $files[] = $f->getPathname();
}
sort($files);

// Insieme dei percorsi-evento esistenti (per segnalare riferimenti a cartelle mancanti).
$existing = [];
foreach ($files as $f) $existing['events/' . trim(str_replace($eventsDir, '', dirname($f)), '/')] = true;

$changedFiles = 0; $changes = 0; $warns = [];
foreach ($files as $file) {
    $doc = json_decode((string)file_get_contents($file), true);
    if (!is_array($doc)) { echo "  ! JSON illeggibile: $file\n"; continue; }
    $rel  = trim(str_replace($eventsDir, '', dirname($file)), '/'); // es. 20260614…-reading-party  (o serie/occ)
    $slug = basename($rel);                                          // self-@id nudo
    $local = [];

    // 1) @id = slug cartella
    if (($doc['@id'] ?? null) !== $slug) { $local[] = "@id: '" . ($doc['@id'] ?? '') . "' → '$slug'"; $doc['@id'] = $slug; }

    // 2) superEvent → events/{slug}
    if (isset($doc['superEvent'])) {
        $se = $doc['superEvent']; $ref = null;
        if (is_string($se)) { $n = refToEvents($se); if ($n !== $se) { $local[] = "superEvent: '$se' → '$n'"; $doc['superEvent'] = $n; } $ref = $n; }
        elseif (is_array($se) && isset($se['@id'])) { $n = refToEvents($se['@id']); if ($n !== $se['@id']) { $local[] = "superEvent.@id: '{$se['@id']}' → '$n'"; $doc['superEvent']['@id'] = $n; } $ref = $n; }
        if ($ref && !isset($existing[$ref])) $warns[] = "$rel: superEvent → $ref (cartella mancante)";
    }

    // 3) subEvent[].@id (solo i riferimenti a occorrenze; il programma inline non ha @id)
    if (isset($doc['subEvent']) && is_array($doc['subEvent'])) {
        foreach ($doc['subEvent'] as $i => $sub) {
            if (is_array($sub) && isset($sub['@id'])) {
                $n = refToEvents($sub['@id']);
                if ($n !== $sub['@id']) { $local[] = "subEvent[$i].@id: '{$sub['@id']}' → '$n'"; $doc['subEvent'][$i]['@id'] = $n; }
                if (!isset($existing[$n])) $warns[] = "$rel: subEvent → $n (cartella mancante)";
            }
        }
    }

    if ($local) {
        $changedFiles++; $changes += count($local);
        echo "• $rel\n";
        foreach ($local as $l) echo "    $l\n";
        if ($apply) file_put_contents($file, json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}

echo "\n" . ($apply ? 'APPLICATO' : 'DRY-RUN') . ": $changedFiles file, $changes modifiche.\n";
if ($warns) { echo "\nAvvisi (riferimenti a cartelle non ancora presenti — occorrenze da completare):\n"; foreach (array_unique($warns) as $w) echo "  - $w\n"; }
if (!$apply && $changedFiles) echo "\nRilancia con --apply per scrivere. Poi: php ws-admin/events/rebuild-index.php\n";
