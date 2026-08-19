<?php
// Verifica integrità dei RIFERIMENTI degli eventi: organizer/location/performer/superEvent/
// subEvent che puntano a una cartella inesistente (tipico dei refusi nell'@id, che fanno
// "sparire" silenziosamente collezioni/eventi dagli indici). $base = .../contents/meetoo/it_IT.
// Ritorna [ ['from'=>relPath, 'field'=>..., 'ref'=>...], ... ] (vuoto = tutto ok).

require_once __DIR__ . '/ws-auth.php'; // ws_ref_id / ws_ref_ids

if (!function_exists('event_check_refs')) {
    function event_check_refs(string $base): array {
        $base = rtrim($base, '/');
        $eventsDir = "$base/events";
        if (!is_dir($eventsDir)) return [];

        // Un riferimento {collection}/{slug} è "rotto" se non esiste il suo index.json.
        $missing = fn($ref) => ($ref = trim((string)$ref, '/')) !== '' && !is_file("$base/$ref/index.json");
        $toEvent = fn($ref) => strpos((string)$ref, '/') === false ? 'events/' . $ref : (string)$ref;

        $broken = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($eventsDir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->getFilename() !== 'index.json' || strpos($f->getPathname(), '/_index/') !== false) continue;
            $doc = json_decode((string)@file_get_contents($f->getPathname()), true);
            if (!is_array($doc)) continue;
            $rel = 'events/' . trim(str_replace($eventsDir, '', dirname($f->getPathname())), '/');

            // Riferimenti a entità di altre collezioni (organizations/… places/…): forma {collection}/{slug}.
            foreach (['organizer', 'location', 'performer'] as $field) {
                foreach (ws_ref_ids($doc[$field] ?? null) as $ref) {
                    if (strpos($ref, '/') === false) continue; // slug nudo: non è un riferimento qui
                    if ($missing($ref)) $broken[] = ['from' => $rel, 'field' => $field, 'ref' => $ref];
                }
            }

            // Riferimenti a eventi: superEvent (occorrenza→serie) e subEvent (serie→occorrenze).
            $sup = ws_ref_id($doc['superEvent'] ?? null);
            if ($sup !== '' && $missing($toEvent($sup))) $broken[] = ['from' => $rel, 'field' => 'superEvent', 'ref' => $toEvent($sup)];
            if (isset($doc['subEvent']) && is_array($doc['subEvent'])) {
                foreach ($doc['subEvent'] as $sub) {
                    if (is_array($sub) && !empty($sub['@id'])) {
                        $r = $toEvent($sub['@id']);
                        if ($missing($r)) $broken[] = ['from' => $rel, 'field' => 'subEvent', 'ref' => $r];
                    }
                }
            }
        }
        return $broken;
    }
}
