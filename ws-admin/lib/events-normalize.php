<?php
require_once __DIR__ . '/ws-wrap.php';
// Normalizzazione STRUTTURALE dei contenuti eventi (una-tantum, ripetibile e idempotente):
//   1) rimuove le EventSeries MAL POSIZIONATE (annidate sotto un evento invece che a top-level)
//   2) per ogni occorrenza dichiarata nel subEvent di una serie:
//      - se manca index.json → la COMPLETA (recupero da index.xml se presente, altrimenti
//        placeholder da slug + default della serie);
//      - se esiste ma ha superEvent vuoto → RIPARA superEvent = serie (la serie la dichiara
//        membro). Unione bidirezionale subEvent↔superEvent: nessuna perdita di appartenenza.
//   3) rideriva il subEvent di ogni serie dai membri effettivi (dopo riparazione/completamento).
// $apply=false → dry-run (calcola ma non scrive; i conteggi riflettono comunque l'esito).
// Ritorna ['removedSeries'=>[], 'completedOccurrences'=>[{path,source}], 'repairedSuperEvent'=>[],
//          'seriesSubEventUpdated'=>[{series,count}], 'warns'=>[]].

require_once __DIR__ . '/../json-xml/functions.php'; // WsxToJson, jsonToWsx

if (!function_exists('en_rrmdir')) {
    function en_rrmdir(string $d): void {
        if (!is_dir($d)) return;
        foreach (scandir($d) as $f) { if ($f === '.' || $f === '..') continue; $p = "$d/$f"; is_dir($p) ? en_rrmdir($p) : @unlink($p); }
        @rmdir($d);
    }
}
if (!function_exists('en_dates_from_slug')) {
    // 20260607T1730-… → ['2026-06-07T17:30:00+02:00', +2h30]. Null se lo slug non porta data.
    function en_dates_from_slug(string $slug): array {
        if (!preg_match('/^(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})/', $slug, $m)) return [null, null];
        $start = "$m[1]-$m[2]-$m[3]T$m[4]:$m[5]:00+02:00";
        return [$start, date('Y-m-d\TH:i:s+02:00', strtotime($start) + 9000)];
    }
}
if (!function_exists('en_is_series')) {
    function en_is_series(array $doc): bool {
        $t = $doc['@type'] ?? null; $t = is_array($t) ? $t : [$t];
        return in_array('EventSeries', $t, true);
    }
}
if (!function_exists('en_write_occurrence')) {
    function en_write_occurrence(string $dir, array $doc): void {
        @mkdir($dir, 0775, true);
        @mkdir("$dir/media", 0775, true);
        // Nasce con il guscio di pagina, come ogni altro contenuto.
        $out = ws_wrap_one($doc);
        file_put_contents("$dir/index.json", json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        try { file_put_contents("$dir/index.xml", jsonToWsx(json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))); } catch (\Throwable $e) { /* xml best-effort */ }
    }
}
if (!function_exists('en_placeholder_occurrence')) {
    // Occorrenza minima da slug + default della serie (nome, tipo, luogo, organizer, ecc.).
    function en_placeholder_occurrence(string $slug, string $seriesSlug, array $sdoc): array {
        [$s, $e] = en_dates_from_slug($slug);
        $st = $sdoc['@type'] ?? []; $st = is_array($st) ? $st : [$st];
        $subtypes = array_values(array_filter($st, fn($t) => $t !== 'EventSeries' && $t !== 'Event'));
        $occ = [
            '@context' => $sdoc['@context'] ?? ['https://schema.org', ['meetoo' => 'https://meetoo.eu']],
            '@id'      => $slug,
            '@type'    => array_merge(['Event'], $subtypes),
            'name'     => (string)($sdoc['name'] ?? 'Evento'),
        ];
        if ($s) { $occ['startDate'] = $s; $occ['endDate'] = $e; }
        foreach (['eventAttendanceMode', 'typicalAgeRange', 'isAccessibleForFree'] as $k) if (isset($sdoc[$k])) $occ[$k] = $sdoc[$k];
        if (isset($sdoc['location']))  $occ['location']  = $sdoc['location'];
        if (isset($sdoc['organizer'])) $occ['organizer'] = $sdoc['organizer'];
        $occ['superEvent'] = 'events/' . $seriesSlug;
        $occ['eventStatus'] = $sdoc['eventStatus'] ?? 'https://schema.org/EventScheduled';
        return $occ;
    }
}

if (!function_exists('event_normalize')) {
    /* $o['rimuoviSerie'] = false → le serie fuori posto si SEGNALANO ma non si
     * toccano. Serve a «Normalizza i contenuti», che invece di cancellarle le
     * sposta nel punto di ripristino: cancellare una cartella e' l'unica cosa,
     * qui dentro, che non si puo' disfare. */
    function event_normalize(string $base, bool $apply, array $o = []): array {
        $rimuoviSerie = ($o['rimuoviSerie'] ?? true) !== false;
        $eventsDir = rtrim($base, '/') . '/events';
        $report = ['removedSeries' => [], 'completedOccurrences' => [], 'repairedSuperEvent' => [], 'seriesSubEventUpdated' => [], 'warns' => []];
        if (!is_dir($eventsDir)) { $report['warns'][] = 'cartella events mancante'; return $report; }

        // Raccogli i doc (rel = percorso relativo a events/).
        $docs = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($eventsDir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->getFilename() !== 'index.json' || strpos($f->getPathname(), '/_index/') !== false) continue;
            $doc = json_decode((string)@file_get_contents($f->getPathname()), true);
            // 'doc' è l'ENTITÀ (le regole guardano @type, superEvent, subEvent…),
            // 'raw' il documento intero: serve a riscrivere senza perdere il guscio.
            if (is_array($doc)) $docs[trim(str_replace($eventsDir, '', dirname($f->getPathname())), '/')] =
                ['file' => $f->getPathname(), 'doc' => ws_wrap_entity($doc), 'raw' => $doc];
        }

        // 1) rimuovi le serie mal posizionate (rel con "/" = non top-level)
        foreach ($docs as $rel => $info) {
            if (en_is_series($info['doc']) && strpos($rel, '/') !== false) {
                $report['removedSeries'][] = "events/$rel";
                if ($apply && $rimuoviSerie) {
                    $dir = dirname($info['file']);
                    en_rrmdir($dir);
                    $parent = dirname($dir); // se ".../events" resta vuoto, rimuovilo
                    if (basename($parent) === 'events' && count(array_diff(scandir($parent) ?: [], ['.', '..'])) === 0) @rmdir($parent);
                }
                unset($docs[$rel]);
            }
        }

        // serie top-level
        $series = [];
        foreach ($docs as $rel => $info) if (en_is_series($info['doc']) && strpos($rel, '/') === false) $series[$rel] = $info['doc'];

        // Mappa membership EFFETTIVA: slug top-level → ['seriesRef','name','start'].
        // Parte dal superEvent esistente; viene riparata/estesa nello step 2 (anche in dry-run).
        $member = [];
        foreach ($docs as $rel => $info) {
            if (strpos($rel, '/') !== false || en_is_series($info['doc'])) continue;
            $se = $info['doc']['superEvent'] ?? '';
            $member[$rel] = [
                'seriesRef' => is_array($se) ? ($se['@id'] ?? '') : (string)$se,
                'name'      => (string)($info['doc']['name'] ?? ''),
                'start'     => (string)($info['doc']['startDate'] ?? ''),
            ];
        }

        // 2) per ogni subEvent dichiarato: completa se manca, ripara superEvent se vuoto
        foreach ($series as $srel => $sdoc) {
            $seriesRef = "events/$srel";
            foreach (($sdoc['subEvent'] ?? []) as $sub) {
                if (!is_array($sub) || !isset($sub['@id'])) continue; // solo riferimenti (no programma inline)
                $slug = basename((string)$sub['@id']);
                if ($slug === '') continue;
                $occDir = "$eventsDir/$slug";

                if (is_file("$occDir/index.json")) {
                    // esiste: ripara il superEvent se mancante (la serie la dichiara membro)
                    if (empty($member[$slug]['seriesRef'])) {
                        $report['repairedSuperEvent'][] = "events/$slug";
                        if (!isset($member[$slug])) $member[$slug] = ['name' => '', 'start' => ''];
                        $member[$slug]['seriesRef'] = $seriesRef;
                        if ($apply) {
                            $d = json_decode((string)@file_get_contents("$occDir/index.json"), true);
                            if (is_array($d)) {
                                $de = ws_wrap_entity($d); $de['superEvent'] = $seriesRef;
                                file_put_contents("$occDir/index.json", json_encode(ws_wrap_set($d, $de), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                            }
                        }
                    }
                    continue;
                }

                // manca: completa (recupero da xml o placeholder)
                $doc = null; $source = 'generated';
                if (is_file("$occDir/index.xml")) {
                    $rec = json_decode(WsxToJson((string)file_get_contents("$occDir/index.xml")), true);
                    if (is_array($rec)) { $doc = ws_wrap_entity($rec); $source = 'xml'; }
                }
                if (!is_array($doc)) $doc = en_placeholder_occurrence($slug, $srel, $sdoc);
                $doc['@id'] = $slug;
                $doc['superEvent'] = $seriesRef;
                $report['completedOccurrences'][] = ['path' => "events/$slug", 'source' => $source];
                $member[$slug] = ['seriesRef' => $seriesRef, 'name' => (string)($doc['name'] ?? ''), 'start' => (string)($doc['startDate'] ?? '')];
                if ($apply) en_write_occurrence($occDir, $doc);
            }
        }

        // 3) rideriva il subEvent di ogni serie dai membri effettivi (mappa in memoria)
        foreach ($series as $srel => $sdoc) {
            $mem = [];
            foreach ($member as $slug => $m) {
                if ($m['seriesRef'] !== '' && basename($m['seriesRef']) === basename($srel)) $mem[] = ['@id' => "events/$slug", 'name' => $m['name'], 'start' => $m['start']];
            }
            usort($mem, fn($a, $b) => strcmp($a['start'], $b['start']));
            $newSub = array_map(fn($m) => ['@id' => $m['@id'], '@type' => 'Event', 'name' => $m['name']], $mem);
            if (json_encode($sdoc['subEvent'] ?? []) !== json_encode($newSub)) {
                $report['seriesSubEventUpdated'][] = ['series' => "events/$srel", 'count' => count($newSub)];
                if ($apply) {
                    $sdoc['subEvent'] = $newSub;
                    $sraw = $docs[$srel]['raw'] ?? $sdoc;
                    file_put_contents("$eventsDir/$srel/index.json", json_encode(ws_wrap_set($sraw, $sdoc), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                }
            }
        }

        return $report;
    }
}
