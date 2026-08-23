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

// Cover dell'evento, in forma RISOLTA (percorso dalla radice dei contenuti), così
// ogni pagina la usa senza sapere in quale cartella stia il file.
// Un'occorrenza senza immagine propria eredita quella della SERIE: la locandina
// della rassegna vale per tutte le sue date, finché una non ne ha una sua.
if (!function_exists('event_index_cover')) {
    function event_index_cover(array $doc, string $relPath, string $base = ''): string {
        $pick = function ($v) {
            if (is_string($v)) return trim($v);
            if (is_array($v)) return trim((string)($v['url'] ?? $v['@id'] ?? (is_string($v[0] ?? null) ? $v[0] : (is_array($v[0] ?? null) ? ($v[0]['url'] ?? '') : ''))));
            return '';
        };
        // Percorso relativo alla cartella (media/x.jpg) → dalla radice (events/<slug>/media/x.jpg).
        $abs = function (string $img, string $owner) {
            if ($img === '' || preg_match('#^(https?:)?//#i', $img)) return $img;   // URL esterno: invariato
            if (preg_match('#^(events|places|organizations)/#', $img)) return $img; // già dalla radice
            return trim($owner, '/') . '/' . ltrim($img, '/');
        };

        // Un riferimento che punta a un file inesistente NON è una cover: dichiararlo
        // farebbe apparire un'immagine rotta e impedirebbe il ripiego sulla serie.
        $esiste = function (string $img) use ($base): bool {
            if ($img === '') return false;
            if (preg_match('#^(https?:)?//#i', $img)) return true;    // esterna: non verificabile qui
            return $base !== '' && is_file(rtrim($base, '/') . '/' . $img);
        };

        $own = $abs($pick($doc['image'] ?? null) ?: $pick($doc['logo'] ?? null), $relPath);
        if ($own !== '' && $esiste($own)) return $own;

        // Nessuna immagine propria: si guarda la serie contenitrice.
        $superRef = ws_ref_id($doc['superEvent'] ?? null);
        if ($superRef === '' || $base === '') return '';
        $sr = strpos($superRef, '/') === false ? "events/$superRef" : $superRef;
        static $cache = [];
        if (!array_key_exists($sr, $cache)) {
            $f = rtrim($base, '/') . '/' . $sr . '/index.json';
            $j = is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
            $cache[$sr] = is_array($j) ? ($j['mainEntity'] ?? $j) : null;
        }
        $s = $cache[$sr];
        if (!is_array($s)) return '';
        $della = $abs($pick($s['image'] ?? null) ?: $pick($s['logo'] ?? null), $sr);
        return $esiste($della) ? $della : '';
    }
}

// @type dell'organizzatore, letto dal suo documento: serve alle card per dare
// l'icona giusta (un'associazione non è un negozio). Nell'evento l'organizer è un
// riferimento {@id, name} e il tipo non c'è. Una lettura per organizzatore,
// memorizzata; se il riferimento è rotto si ripiega su ciò che dice l'evento.
if (!function_exists('event_index_org_type')) {
    function event_index_org_type(string $orgId, $inline = null, string $base = ''): string {
        $fallback = '';
        if (is_array($inline)) {
            $t = $inline['@type'] ?? '';
            $fallback = is_array($t) ? (string)($t[0] ?? '') : (string)$t;
        }
        static $cache = [];
        if ($base === '' || $orgId === '' || !preg_match('#^[A-Za-z0-9._/-]+$#', $orgId) || strpos($orgId, '..') !== false) return $fallback;
        if (!array_key_exists($orgId, $cache)) {
            $f = rtrim($base, '/') . '/' . $orgId . '/index.json';
            $j = is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
            $e = is_array($j) ? ($j['mainEntity'] ?? $j) : null;
            $t = is_array($e) ? ($e['@type'] ?? '') : '';
            $cache[$orgId] = is_array($t) ? (string)($t[0] ?? '') : (string)$t;
        }
        return $cache[$orgId] !== '' ? $cache[$orgId] : $fallback;
    }
}

// I riferimenti ({@id} di organizer/superEvent) si leggono con ws_ref_id/ws_ref_ids:
// senza, l'indice si costruisce lo stesso ma DEGRADATO (niente by-collection, chiavi
// organizzatore prese dal nome invece che dallo slug). Meglio dipenderci apertamente
// che affidarsi a chi ci include.
require_once __DIR__ . '/ws-auth.php';

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
        $orgInline = null;
        $o = $doc['organizer'] ?? null;
        if (is_array($o)) {
            $first = array_key_exists(0, $o) ? $o[0] : $o;
            if (is_array($first)) { $orgName = (string)($first['name'] ?? ''); $orgId = (string)($first['@id'] ?? ''); $orgInline = $first; }
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
            'organizerKey'  => $orgId !== '' ? event_index_key($orgId) : '',
            // Tipo dell'organizzatore (Organization, NGO, LocalBusiness…): decide l'icona.
            'organizerType' => event_index_org_type($orgId, $orgInline, $base),
            // Luogo: {id, name, address:{addressLocality}} — le card mostrano
            // "{place.name}, {place.address.addressLocality}".
            'place'        => event_index_place_ref($doc['location'] ?? null, $base),
            'cap'          => $cap,
            'status'       => (string)($doc['eventStatus'] ?? ''),
            'image'        => $img,
            // Cover risolta (dalla radice), con ripiego sulla serie: vedi event_index_cover.
            'cover'        => event_index_cover($doc, $relPath, $base),
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
        foreach (glob("$idxDir/by-cap/*.json") ?: [] as $f) @unlink($f);
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
            // L'entità sta dentro il guscio di pagina, quando c'è: senza scartarlo
            // qui si indicizzerebbe un ItemPage — nessun organizer, nessun subEvent,
            // e l'indice esce vuoto pur senza errori.
            $docs[] = [$rel, $doc['mainEntity'] ?? $doc];
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
                $superRef = ws_ref_id($doc['superEvent'] ?? null);
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

        // Indice per-CAP: il CAP è già nel nome della cartella evento
        // (<AAAAMMGGThhmm>-<CAP>[-slug]), quindi è gratis tenerlo indicizzato. Serve
        // alle viste per zona: un CAP è la più piccola area con un confine certo, e
        // le aree più grandi (quartiere, municipio, comune) sono somme di CAP.
        if (!empty($item['cap'])) {
            $res['cap'] = event_index_place("$idxDir/by-cap/" . event_index_key($item['cap']) . '.json', $item);
        }
        return $res;
    }
}

// Aggiornamento INCREMENTALE dell'indice per un solo evento (usato al salvataggio).
// Fa le stesse scritture del rebuild ma toccando solo i file interessati; in più
// RIPULISCE le voci rimaste negli altri raggruppamenti (se cambi organizzatore,
// collezione o CAP, la voce vecchia va tolta da lì, cosa che l'update da solo non fa).
// Salvare una SERIE tocca anche le sue occorrenze (ne ereditano gli organizzatori):
// in quel caso si reindicizzano solo quelle, non tutto l'archivio.
if (!function_exists('event_index_sync')) {
    function event_index_sync(string $base, string $relPath, array $doc): array {
        $idxDir = rtrim($base, '/') . '/events/_index';
        $rel = trim($relPath, '/');

        $isSeries = function (array $d): bool {
            $t = isset($d['@type']) ? (is_array($d['@type']) ? $d['@type'] : [$d['@type']]) : [];
            return in_array('EventSeries', $t, true);
        };
        // Legge un evento dal disco (per la serie di appartenenza e per le occorrenze).
        $read = function (string $r) use ($base): ?array {
            $f = rtrim($base, '/') . '/' . trim($r, '/') . '/index.json';
            if (!is_file($f)) return null;
            $j = json_decode((string)@file_get_contents($f), true);
            return is_array($j) ? ($j['mainEntity'] ?? $j) : null;
        };

        // Un'occorrenza senza organizer proprio eredita quelli della serie: stessa
        // regola del rebuild, qui risolta leggendo solo il documento della serie.
        $override = null;
        if (!$isSeries($doc)) {
            $superRef = ws_ref_id($doc['superEvent'] ?? null);
            if ($superRef !== '') {
                $own = ws_ref_ids($doc['organizer'] ?? null);
                if (!$own) {
                    $sdoc = $read(strpos($superRef, '/') === false ? "events/$superRef" : $superRef);
                    $override = $sdoc ? ws_ref_ids($sdoc['organizer'] ?? null) : [];
                }
            }
        }

        // Indicizza un evento e lo TOGLIE dai raggruppamenti che non lo riguardano
        // più (organizzatore/collezione/CAP cambiati): è la differenza fra un update
        // e un rebuild. Vale per ogni evento toccato, non solo per quello salvato.
        $applyOne = function (string $r, array $d, ?array $ovr) use ($base, $idxDir, &$pruned) {
            $res = event_index_update($base, $r, $d, $ovr);
            $item = event_index_item($d, $r, $base);
            $keep = ['by-organizer' => [], 'by-collection' => [], 'by-cap' => []];
            $orgIds = $ovr !== null ? $ovr : ws_ref_ids($d['organizer'] ?? null);
            if (!$orgIds && $item['organizer'] !== '') $orgIds = [$item['organizer']];
            foreach (array_unique($orgIds) as $oid) $keep['by-organizer'][] = event_index_key($oid) . '.json';
            if ($item['kind'] !== 'series' && $item['collection'] !== '') $keep['by-collection'][] = event_index_key($item['collection']) . '.json';
            if (!empty($item['cap'])) $keep['by-cap'][] = event_index_key($item['cap']) . '.json';

            foreach ($keep as $sub => $keepFiles) {
                foreach (glob("$idxDir/$sub/*.json") ?: [] as $f) {
                    // Il file "prossimi" e il suo archivio sono la stessa appartenenza.
                    $baseName = preg_replace('/\.archive\.json$/', '.json', basename($f));
                    if (in_array($baseName, $keepFiles, true)) continue;
                    // Confronto sul contenuto: filesize() legge la cache di stat e qui
                    // mentirebbe (il file è appena stato riscritto).
                    $before = (string)@file_get_contents($f);
                    event_index_remove_from_file($f, $r);
                    if ($before !== (string)@file_get_contents($f)) $pruned++;
                }
            }
            return $res;
        };

        $pruned = 0;
        $res = $applyOne($rel, $doc, $override);

        // Salvando una serie cambiano gli organizzatori ereditati dalle occorrenze:
        // si reindicizzano solo quelle (dal suo indice per-collection e dai subEvent).
        $touched = 1;
        if ($isSeries($doc)) {
            $ck = event_index_key((string)($doc['@id'] ?? basename($rel)));
            $members = [];
            foreach (["$idxDir/by-collection/$ck.json", "$idxDir/by-collection/$ck.archive.json"] as $f) {
                $j = is_file($f) ? json_decode((string)@file_get_contents($f), true) : null;
                foreach (is_array($j) ? $j : [] as $e) if (!empty($e['path'])) $members[$e['path']] = true;
            }
            foreach ((array)($doc['subEvent'] ?? []) as $sub) {
                $id = is_array($sub) ? (string)($sub['@id'] ?? '') : (string)$sub;
                if ($id !== '') $members[strpos($id, '/') === false ? "events/$id" : $id] = true;
            }
            $seriesOrgs = ws_ref_ids($doc['organizer'] ?? null);
            foreach (array_keys($members) as $m) {
                $mdoc = $read($m);
                if (!$mdoc) continue;
                $own = ws_ref_ids($mdoc['organizer'] ?? null);
                $applyOne(trim($m, '/'), $mdoc, $own ? null : $seriesOrgs);
                $touched++;
            }
        }

        return ['mode' => 'incremental', 'indexed' => $touched, 'pruned' => $pruned,
                'series' => $isSeries($doc) ? 1 : 0, 'organizers' => count($res['organizers'])];
    }
}
