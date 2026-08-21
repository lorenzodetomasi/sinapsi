<?php
// Manutenzione dell'indice eventi, aggiornato a ogni salvataggio web.
// Split PROSSIMI/ARCHIVIO per non far scaricare tutto l'archivio a chi mostra i prossimi:
//   • Globale:       events/_index/events.json            (serie + singoli PROSSIMI)
//                    events/_index/events.archive.json    (singoli PASSATI)
//   • Per-organizer: events/_index/by-organizer/<key>.json         (serie + singoli prossimi dell'org)
//                    events/_index/by-organizer/<key>.archive.json (singoli passati dell'org)
//   • Per-collection: events/_index/by-collection/<key>.json         (occorrenze prossime della serie)
//                     events/_index/by-collection/<key>.archive.json (occorrenze passate della serie)
// Regola bucket: una SERIE sta sempre nel file principale; un SINGOLO va in .archive.json se
// endDate (o startDate) è < adesso. Il taglio è al momento del rebuild/salvataggio: le pagine
// ri-splittano comunque per data ciò che caricano, quindi il drift al confine è solo cosmetico.
// Voce compatta per evento; ordinamento per startDate. Best-effort: gli errori NON bloccano il salvataggio.

// Luogo dell'evento per l'indice: {id, name, address:{addressLocality}}.
// Nell'evento la location è solo un RIFERIMENTO ({@id, name}), quindi la località
// va letta nel file del luogo; da lì prendiamo anche il nome CANONICO, così un
// luogo si chiama allo stesso modo ovunque e rinominarlo si propaga al rebuild.
// Se il riferimento è rotto (o manca $base) si ripiega su ciò che ha l'evento.
if (!function_exists('event_index_place_ref')) {
    function event_index_place_ref($location, string $base = ''): ?array {
        if (is_string($location)) $location = ['@id' => $location];
        if (!is_array($location)) return null;
        // Riferimento singolo o lista: prendiamo il primo utile.
        if (!isset($location['@id']) && !isset($location['name']) && isset($location[0])) $location = $location[0];
        if (!is_array($location)) return null;

        $id = (string)($location['@id'] ?? '');
        $out = ['id' => $id, 'name' => (string)($location['name'] ?? '')];
        $locality = (string)($location['address']['addressLocality'] ?? '');

        // Risoluzione del luogo (una lettura per file, memorizzata: gli eventi
        // condividono gli stessi luoghi e il rebuild ne indicizza molti).
        static $cache = [];
        if ($base !== '' && $id !== '' && preg_match('#^[A-Za-z0-9._/-]+$#', $id) && strpos($id, '..') === false) {
            if (!array_key_exists($id, $cache)) {
                $f = rtrim($base, '/') . '/' . $id . '/index.json';
                $j = is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
                $cache[$id] = is_array($j) ? ($j['mainEntity'] ?? $j) : null;
            }
            $p = $cache[$id];
            if (is_array($p)) {
                if (($p['name'] ?? '') !== '') $out['name'] = (string)$p['name'];   // nome canonico
                if ($locality === '') $locality = (string)($p['address']['addressLocality'] ?? '');
            }
        }
        if ($locality !== '') $out['address'] = ['addressLocality' => $locality];
        return ($out['name'] === '' && $out['id'] === '') ? null : $out;
    }
}

// Voce compatta dell'indice a partire dal documento evento e dal percorso (schema c).
// $base (.../contents/meetoo/it_IT) serve a risolvere il luogo: se omesso, la voce
// place riporta solo ciò che è scritto nell'evento.
if (!function_exists('event_index_item')) {
    function event_index_item(array $doc, string $relPath, string $base = ''): array {
        // cap dal nome cartella: <AAAAMMGGThhmm>-<cap>[-slug]
        $cap = '';
        if (preg_match('/^\d{8}T\d{4}-([A-Za-z0-9]+)/', basename($relPath), $m)) $cap = $m[1];

        // Natura: 'series' = collection (EventSeries), altrimenti 'single'.
        $typeArr = isset($doc['@type']) ? (is_array($doc['@type']) ? $doc['@type'] : [$doc['@type']]) : [];
        $kind = in_array('EventSeries', $typeArr, true) ? 'series' : 'single';
        // Collection di appartenenza: riferimento @id/path della serie contenitrice (superEvent).
        $collection = function_exists('ws_ref_id') ? ws_ref_id($doc['superEvent'] ?? null) : '';

        // organizer: name del primo se presente (inline), altrimenti l'@id di riferimento
        $orgName = '';
        $orgId = '';
        $o = $doc['organizer'] ?? null;
        if (is_array($o)) {
            $first = array_key_exists(0, $o) ? $o[0] : $o;
            if (is_array($first)) { $orgName = (string)($first['name'] ?? ''); $orgId = (string)($first['@id'] ?? ''); }
        } elseif (is_string($o)) {
            $orgId = $o;
        }

        // immagine di anteprima: prima url utile tra image/logo (stringa o oggetto/array)
        $img = '';
        foreach (['image', 'logo'] as $k) {
            $v = $doc[$k] ?? null;
            if (is_string($v) && $v !== '') { $img = $v; break; }
            if (is_array($v)) {
                $img = (string)($v['url'] ?? $v['@id'] ?? (isset($v[0]) && is_array($v[0]) ? ($v[0]['url'] ?? '') : (is_string($v[0] ?? null) ? $v[0] : '')));
                if ($img !== '') break;
            }
        }

        return [
            'path'         => $relPath,
            'kind'         => $kind,
            'collection'   => $collection,
            'name'         => (string)($doc['name'] ?? ''),
            'startDate'    => (string)($doc['startDate'] ?? ''),
            'endDate'      => (string)($doc['endDate'] ?? ''),
            'organizer'    => $orgName !== '' ? $orgName : $orgId,
            'organizerKey' => $orgId !== '' ? event_index_key($orgId) : '',
            // Luogo: {id, name, address:{addressLocality}} — le card mostrano
            // "{place.name}, {place.address.addressLocality}".
            'place'        => event_index_place_ref($doc['location'] ?? null, $base),
            'cap'          => $cap,
            'status'       => (string)($doc['eventStatus'] ?? ''),
            'image'        => $img,
            'dateModified' => (string)($doc['dateModified'] ?? ''),
        ];
    }
}

// Chiave file-safe per un organizer (ultimo segmento del path/URL, sanitizzato).
if (!function_exists('event_index_key')) {
    function event_index_key(string $refId): string {
        $s = trim($refId);
        if ($s === '') return 'senza-organizer';
        $p = strrpos($s, '/');
        if ($p !== false) $s = substr($s, $p + 1);
        $s = trim((string)preg_replace('/[^A-Za-z0-9._-]+/', '-', $s), '-');
        return $s !== '' ? $s : 'senza-organizer';
    }
}

// Upsert atomico-ish di una voce in un file-indice (elenco JSON): rimuove la voce con
// lo stesso path, aggiunge quella nuova, riordina per startDate e riscrive.
if (!function_exists('event_index_upsert_file')) {
    function event_index_upsert_file(string $file, array $item): bool {
        $dir = dirname($file);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) return false;
        $list = [];
        if (is_file($file)) {
            $j = json_decode((string)@file_get_contents($file), true);
            if (is_array($j)) $list = (isset($j['events']) && is_array($j['events'])) ? $j['events'] : $j;
        }
        $list = array_values(array_filter(
            is_array($list) ? $list : [],
            fn($e) => is_array($e) && ($e['path'] ?? null) !== $item['path']
        ));
        $list[] = $item;
        usort($list, fn($a, $b) => strcmp((string)($a['startDate'] ?? ''), (string)($b['startDate'] ?? '')));
        $json = json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return $json !== false && @file_put_contents($file, $json) !== false;
    }
}

// La voce è "archiviata"? Le serie MAI (stanno sempre nel file principale); un singolo
// è passato se la sua fine (o inizio) è precedente ad adesso.
if (!function_exists('event_is_archived')) {
    function event_is_archived(array $item): bool {
        if (($item['kind'] ?? '') === 'series') return false;
        $d = ($item['endDate'] ?? '') !== '' ? $item['endDate'] : ($item['startDate'] ?? '');
        $t = $d !== '' ? strtotime($d) : false;
        return $t !== false && $t < time();
    }
}

// Rimuove la voce con quel path da un file-indice; se resta vuoto elimina il file.
if (!function_exists('event_index_remove_from_file')) {
    function event_index_remove_from_file(string $file, string $path): void {
        if (!is_file($file)) return;
        $j = json_decode((string)@file_get_contents($file), true);
        $list = is_array($j) ? ((isset($j['events']) && is_array($j['events'])) ? $j['events'] : $j) : [];
        $list = array_values(array_filter(is_array($list) ? $list : [], fn($e) => is_array($e) && ($e['path'] ?? null) !== $path));
        if ($list) @file_put_contents($file, json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        else @unlink($file);
    }
}

// Colloca la voce nel bucket giusto: prossimi = <base>.json, passati = <base>.archive.json.
// Rimuove sempre dall'altro bucket (per gestire ri-salvataggi e cambi di data).
if (!function_exists('event_index_place')) {
    function event_index_place(string $baseFile, array $item): bool {
        $archiveFile = preg_replace('/\.json$/', '.archive.json', $baseFile);
        if (event_is_archived($item)) {
            event_index_remove_from_file($baseFile, $item['path']);
            return event_index_upsert_file($archiveFile, $item);
        }
        event_index_remove_from_file($archiveFile, $item['path']);
        return event_index_upsert_file($baseFile, $item);
    }
}

// Ricostruzione COMPLETA dell'indice da tutti gli eventi su disco (serie + occorrenze
// annidate). Azzera l'indice esistente e re-indicizza. $base = .../contents/meetoo/it_IT.
// Ritorna ['indexed'=>int, 'skipped'=>int, 'organizers'=>int, 'series'=>int].
if (!function_exists('event_index_rebuild')) {
    function event_index_rebuild(string $base): array {
        $eventsDir = rtrim($base, '/') . '/events';
        $idxDir = "$eventsDir/_index";
        if (!is_dir($eventsDir)) return ['indexed' => 0, 'skipped' => 0, 'organizers' => 0, 'series' => 0, 'error' => 'events dir mancante'];

        // Azzera per una ricostruzione pulita (rimuove voci obsolete). glob *.json prende
        // sia i file "prossimi" sia gli ".archive.json".
        foreach (glob("$idxDir/by-organizer/*.json") ?: [] as $f) @unlink($f);
        foreach (glob("$idxDir/by-collection/*.json") ?: [] as $f) @unlink($f);
        @unlink("$idxDir/events.json");
        @unlink("$idxDir/events.archive.json");

        $files = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($eventsDir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->getFilename() === 'index.json' && strpos($f->getPathname(), '/_index/') === false) $files[] = $f->getPathname();
        }
        sort($files);

        $isSeriesDoc = function (array $doc): bool {
            $t = isset($doc['@type']) ? (is_array($doc['@type']) ? $doc['@type'] : [$doc['@type']]) : [];
            return in_array('EventSeries', $t, true);
        };

        // Passata 1: carica i doc validi e mappa gli organizer delle SERIE (per l'ereditarietà).
        // La chiave normalizza i riferimenti (slug nudo o events/{slug}) all'ultimo segmento.
        $docs = []; $seriesOrgs = []; $skipped = 0;
        foreach ($files as $file) {
            $doc = json_decode((string)@file_get_contents($file), true);
            if (!is_array($doc)) { $skipped++; continue; }
            $rel = trim(str_replace(rtrim($base, '/'), '', dirname($file)), '/');
            $docs[] = [$rel, $doc];
            if ($isSeriesDoc($doc)) {
                $ids = function_exists('ws_ref_ids') ? ws_ref_ids($doc['organizer'] ?? null) : [];
                $seriesOrgs[event_index_key((string)($doc['@id'] ?? basename($rel)))] = $ids;
            }
        }

        // Passata 2: indicizza. Un'occorrenza (superEvent) senza organizer proprio valido
        // eredita gli organizer della sua serie → resta attribuita anche se il suo organizer
        // è vuoto/rotto (es. XInclude non risolto). Coerente con "default di serie con override".
        $indexed = 0; $series = 0; $orgs = [];
        foreach ($docs as $pair) {
            [$rel, $doc] = $pair;
            $override = null;
            if (!$isSeriesDoc($doc)) {
                $superRef = function_exists('ws_ref_id') ? ws_ref_id($doc['superEvent'] ?? null) : '';
                if ($superRef !== '') {
                    $ownIds = function_exists('ws_ref_ids') ? ws_ref_ids($doc['organizer'] ?? null) : [];
                    if (!$ownIds) $override = $seriesOrgs[event_index_key($superRef)] ?? [];
                }
            } else {
                $series++;
            }
            $res = event_index_update($base, $rel, $doc, $override);
            foreach (array_keys($res['organizers']) as $k) $orgs[$k] = true;
            $indexed++;
        }
        return ['indexed' => $indexed, 'skipped' => $skipped, 'organizers' => count($orgs), 'series' => $series];
    }
}

// Aggiorna l'indice globale e quelli per-organizer. $base = .../contents/meetoo/it_IT.
// Ritorna ['global'=>bool, 'organizers'=>[key=>bool]] per la diagnostica.
if (!function_exists('event_index_update')) {
    // $orgIdsOverride: se non-null, usa questi @id di organizzatore invece di estrarli dal
    // doc (serve all'ereditarietà: un'occorrenza senza organizer proprio eredita quelli della serie).
    function event_index_update(string $base, string $relPath, array $doc, ?array $orgIdsOverride = null): array {
        $item = event_index_item($doc, $relPath, $base);
        $idxDir = rtrim($base, '/') . '/events/_index';
        // Ogni indice è splittato prossimi/archivio da event_index_place().
        $res = ['global' => event_index_place("$idxDir/events.json", $item), 'organizers' => [], 'collection' => null];

        $orgIds = $orgIdsOverride !== null
            ? $orgIdsOverride
            : (function_exists('ws_ref_ids') ? ws_ref_ids($doc['organizer'] ?? null) : []);
        if (!$orgIds && $item['organizer'] !== '') $orgIds = [$item['organizer']]; // organizer inline senza @id
        foreach (array_unique($orgIds) as $oid) {
            $key = event_index_key($oid);
            $res['organizers'][$key] = event_index_place("$idxDir/by-organizer/$key.json", $item);
        }

        // Indice per-collection: solo le occorrenze (singoli con superEvent).
        if ($item['kind'] !== 'series' && $item['collection'] !== '') {
            $ck = event_index_key($item['collection']);
            $res['collection'] = event_index_place("$idxDir/by-collection/$ck.json", $item);
        }
        return $res;
    }
}
