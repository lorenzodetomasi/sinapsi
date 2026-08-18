<?php
// Normalizzazione dei riferimenti eventi verso le convenzioni (CONTENT-STRUCTURE.md):
//   • @id = slug della cartella (self-@id "nudo")
//   • superEvent = events/{slug}
//   • subEvent[].@id (riferimenti) = events/{slug}
// Idempotente: scrive SOLO i file che cambiano (quindi "solo quando necessario").
// NON rinomina cartelle, non tocca organizer/location.

if (!function_exists('event_ref_to_events')) {
    // Riferimento a evento → events/{slug}. Se già contiene "/", si assume qualificato.
    function event_ref_to_events($ref) {
        if (!is_string($ref) || $ref === '') return $ref;
        return strpos($ref, '/') !== false ? $ref : 'events/' . $ref;
    }
}

if (!function_exists('event_migrate_refs')) {
    // $apply=false → dry-run (calcola ma non scrive). Ritorna:
    //   ['changedFiles'=>int, 'changes'=>int, 'details'=>[rel=>[...]], 'warns'=>[...]].
    function event_migrate_refs(string $base, bool $apply): array {
        $eventsDir = rtrim($base, '/') . '/events';
        if (!is_dir($eventsDir)) return ['changedFiles' => 0, 'changes' => 0, 'details' => [], 'warns' => []];

        $files = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($eventsDir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->getFilename() === 'index.json' && strpos($f->getPathname(), '/_index/') === false) $files[] = $f->getPathname();
        }
        sort($files);

        $existing = [];
        foreach ($files as $f) $existing['events/' . trim(str_replace($eventsDir, '', dirname($f)), '/')] = true;

        $changedFiles = 0; $changes = 0; $details = []; $warns = [];
        foreach ($files as $file) {
            $doc = json_decode((string)@file_get_contents($file), true);
            if (!is_array($doc)) continue;
            $rel  = trim(str_replace($eventsDir, '', dirname($file)), '/');
            $slug = basename($rel);
            $local = [];

            // 1) @id = slug cartella
            if (($doc['@id'] ?? null) !== $slug) { $local[] = "@id: '" . ($doc['@id'] ?? '') . "' → '$slug'"; $doc['@id'] = $slug; }

            // 2) superEvent → events/{slug}
            if (isset($doc['superEvent'])) {
                $se = $doc['superEvent']; $ref = null;
                if (is_string($se)) { $n = event_ref_to_events($se); if ($n !== $se) { $local[] = "superEvent: '$se' → '$n'"; $doc['superEvent'] = $n; } $ref = $n; }
                elseif (is_array($se) && isset($se['@id'])) { $n = event_ref_to_events($se['@id']); if ($n !== $se['@id']) { $local[] = "superEvent.@id → '$n'"; $doc['superEvent']['@id'] = $n; } $ref = $n; }
                if ($ref && !isset($existing[$ref])) $warns[] = "$rel: superEvent → $ref (cartella mancante)";
            }

            // 3) subEvent[].@id (solo riferimenti a occorrenze; il programma inline non ha @id)
            if (isset($doc['subEvent']) && is_array($doc['subEvent'])) {
                foreach ($doc['subEvent'] as $i => $sub) {
                    if (is_array($sub) && isset($sub['@id'])) {
                        $n = event_ref_to_events($sub['@id']);
                        if ($n !== $sub['@id']) { $local[] = "subEvent[$i].@id → '$n'"; $doc['subEvent'][$i]['@id'] = $n; }
                        if (!isset($existing[$n])) $warns[] = "$rel: subEvent → $n (cartella mancante)";
                    }
                }
            }

            if ($local) {
                $changedFiles++; $changes += count($local); $details[$rel] = $local;
                if ($apply) file_put_contents($file, json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            }
        }
        return ['changedFiles' => $changedFiles, 'changes' => $changes, 'details' => $details, 'warns' => array_values(array_unique($warns))];
    }
}
