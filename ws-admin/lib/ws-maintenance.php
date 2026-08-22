<?php
/*
 * Registro delle operazioni di manutenzione — UNICA fonte.
 *
 * Migrazioni e conversioni sono elencate qui una volta sola; le pagine le
 * leggono, non le riscrivono. L'hub (ws-admin/index.php) le mostra TUTTE ed è
 * il posto dove si governa un aggiornamento di Meetoo; la Gestione eventi
 * mostra come scorciatoia solo quelle del suo ambito, che servono nel lavoro
 * quotidiano. Aggiungere un'operazione significa aggiungere una voce qui: le
 * due pagine si aggiornano da sole e non possono divergere.
 *
 * Ogni voce dichiara:
 *   scope    ambito (events, media, places, privacy) — decide dove appare
 *   preview  se sa dire cosa farebbe senza scrivere (allora l'hub la interroga
 *            all'apertura e segnala quante cose sono in attesa)
 *   since    versione di Meetoo che l'ha introdotta: serve a capire, dopo un
 *            aggiornamento, che cosa non è ancora stato eseguito qui
 *   run      la funzione che fa il lavoro; ritorna ['lines'=>[], 'summary'=>'',
 *            'changes'=>int] dove changes = quante cose cambierebbero/sono cambiate
 */

const MEETOO_VERSION = '2026.08';

// Dove si annota che cosa è già stato eseguito su QUESTA installazione.
// È un file di stato, non un contenuto: si può cancellare, si riparte da capo.
if (!function_exists('ws_maint_state_path')) {
    function ws_maint_state_path(string $base): string { return rtrim($base, '/') . '/_index/maintenance.json'; }

    function ws_maint_state(string $base): array {
        $f = ws_maint_state_path($base);
        $j = is_file($f) ? json_decode((string)@file_get_contents($f), true) : null;
        return is_array($j) ? $j : [];
    }

    function ws_maint_record(string $base, string $id, array $rep, string $by = ''): void {
        $st = ws_maint_state($base);
        $st[$id] = ['at' => date('c'), 'by' => $by, 'changes' => (int)($rep['changes'] ?? 0), 'version' => MEETOO_VERSION];
        $dir = dirname(ws_maint_state_path($base));
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        @file_put_contents(ws_maint_state_path($base), json_encode($st, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}

if (!function_exists('ws_maint_ops')) {
    function ws_maint_ops(): array {
        return [
            'events-index' => [
                'title' => 'Rigenera l\'indice degli eventi',
                'meta'  => 'Normalizza i riferimenti e ricostruisce gli indici; segnala i problemi',
                'icon'  => 'manage_history', 'scope' => 'events', 'preview' => true, 'since' => '2026.06',
                'run' => function (string $base, bool $apply, array $o): array {
                    require_once __DIR__ . '/events-index.php';
                    require_once __DIR__ . '/events-migrate.php';
                    require_once __DIR__ . '/events-check.php';
                    // In anteprima si guarda soltanto: quanti file avrebbero i riferimenti
                    // da riscrivere (è il segnale che compare dopo un allineamento dei
                    // contenuti) e quali problemi restano. L'indice non si tocca.
                    $mig = event_migrate_refs($base, $apply);
                    $idx = $apply ? event_index_rebuild($base) : null;
                    $bad = event_check_refs($base);
                    $n = (int)($mig['changedFiles'] ?? 0);
                    return [
                        'changes' => $n,
                        'summary' => ($idx
                            ? "{$idx['indexed']} eventi · {$idx['series']} collezioni · {$idx['organizers']} organizzatori"
                            : ($n ? "$n file con riferimenti da normalizzare" : 'Riferimenti a posto'))
                            . ($apply && $n ? " · $n file normalizzati" : '')
                            . (count($bad) ? ' · ⚠ ' . count($bad) . ' problemi' : ''),
                        'lines' => array_merge(
                            $apply ? [] : array_map(fn($rel, $ch) => "da normalizzare: $rel (" . count($ch) . ')',
                                array_keys($mig['details'] ?? []), array_values($mig['details'] ?? [])),
                            array_map(fn($b) => "⚠ {$b['from']} [{$b['field']}] → {$b['ref']}", $bad)
                        ),
                    ];
                },
            ],

            'events-normalize' => [
                'title' => 'Normalizza i contenuti degli eventi',
                'meta'  => 'Ripara serie annidate, occorrenze mancanti, subEvent↔superEvent',
                'icon'  => 'healing', 'scope' => 'events', 'preview' => true, 'since' => '2026.06',
                'confirm' => 'Normalizzare i contenuti? I file degli eventi interessati verranno riscritti.',
                'run' => function (string $base, bool $apply, array $o): array {
                    require_once __DIR__ . '/events-normalize.php';
                    require_once __DIR__ . '/events-index.php';
                    $n = event_normalize($base, $apply);
                    $tot = count($n['removedSeries']) + count($n['completedOccurrences'])
                         + count($n['repairedSuperEvent']) + count($n['seriesSubEventUpdated']);
                    if ($apply) event_index_rebuild($base);
                    return [
                        'changes' => $tot,
                        'summary' => $tot ? "$tot riparazioni" : 'Niente da riparare.',
                        'lines' => array_merge(
                            array_map(fn($x) => 'serie annidata rimossa: ' . json_encode($x), $n['removedSeries']),
                            array_map(fn($x) => 'occorrenza completata: ' . json_encode($x), $n['completedOccurrences']),
                            array_map(fn($x) => 'superEvent riparato: ' . json_encode($x), $n['repairedSuperEvent']),
                            array_map(fn($x) => "serie riallineata: {$x['series']} ({$x['count']} occorrenze)", $n['seriesSubEventUpdated'])
                        ),
                    ];
                },
            ],

            'keywords-array' => [
                'title' => 'Keywords come elenco',
                'meta'  => 'Da stringa separata da virgole ad array, senza doppioni',
                'icon'  => 'sell', 'scope' => 'events', 'preview' => true, 'since' => '2026.08',
                'confirm' => 'Convertire le keywords in elenco? I file interessati (JSON e XML) verranno riscritti.',
                'run' => function (string $base, bool $apply, array $o): array {
                    require_once __DIR__ . '/ws-keywords.php';
                    $r = ws_keywords_migrate($base, $apply);
                    return [
                        'changes' => count($r['done']),
                        'summary' => count($r['done'])
                            ? count($r['done']) . ' file' . ($r['removed'] ? " · {$r['removed']} doppioni tolti" : '')
                            : 'Tutte le keywords sono già un elenco.',
                        'lines' => array_merge(
                            array_map(fn($d) => "{$d['path']}: {$d['before']} → " . count($d['after']) . ' voci'
                                . ($d['xml'] ? '' : ' (XML non rigenerato)'), $r['done']),
                            array_map(fn($x) => "⚠ {$x['path']} → {$x['why']}", $r['failed'])
                        ),
                    ];
                },
            ],

            'covers' => [
                'title' => 'Genera le copertine 1920×1080',
                'meta'  => 'Dalle immagini già caricate; l\'originale resta in media-sources',
                'icon'  => 'crop_16_9', 'scope' => 'media', 'preview' => true, 'adopt' => true, 'since' => '2026.08',
                'confirm' => 'Generare le copertine? Verranno creati file in media/ e aggiornato il campo image degli eventi.',
                'run' => function (string $base, bool $apply, array $o): array {
                    require_once __DIR__ . '/ws-media.php';
                    require_once __DIR__ . '/events-index.php';
                    $r = ws_media_covers($base, $apply, !empty($o['adopt']));
                    if ($apply) event_index_rebuild($base);
                    return [
                        'changes' => count($r['done']),
                        'summary' => count($r['done']) . ' cover, ' . count($r['broken']) . ' da sistemare a mano',
                        'lines' => array_merge(
                            array_map(fn($d) => "{$d['event']} → " . ($d['exact'] ? 'già 1920×1080' : "{$d['from']} → cover"), $r['done']),
                            array_map(fn($x) => "{$x['event']} → {$x['why']}", $r['skipped']),
                            array_map(fn($x) => "{$x['event']} → esterna: {$x['url']}", $r['external']),
                            array_map(fn($b) => "⚠ {$b['event']} → {$b['ref']}" . ($b['found'] ? " (in cartella: {$b['found']})" : ' (nessuna immagine)'), $r['broken'])
                        ),
                    ];
                },
            ],

            'media-index' => [
                'title' => 'Rigenera l\'indice delle immagini',
                'meta'  => 'Impronta → percorso: è ciò che evita i duplicati quando si riusa una cover',
                'icon'  => 'photo_library', 'scope' => 'media', 'preview' => false, 'since' => '2026.08',
                'run' => function (string $base, bool $apply, array $o): array {
                    require_once __DIR__ . '/ws-media.php';
                    $n = count(ws_media_reindex($base));
                    return ['changes' => 0, 'summary' => "$n immagini indicizzate", 'lines' => []];
                },
            ],

            'places-index' => [
                'title' => 'Rigenera indice luoghi e Gruppi',
                'meta'  => 'Deduplica per Google Place ID, Gruppi della home, elenco organizzatori dell\'editor',
                'icon'  => 'travel_explore', 'scope' => 'places', 'preview' => false, 'since' => '2026.07',
                'run' => function (string $base, bool $apply, array $o): array {
                    require_once __DIR__ . '/../places/index-lib.php';
                    list($idx, $conf) = ws_index_rebuild();
                    ws_index_save($idx);
                    $g = ws_gruppi_rebuild();
                    ws_gruppi_save($g);
                    $e = ws_entities_rebuild();
                    ws_entities_save($e);
                    return [
                        'changes' => count($conf),
                        'summary' => count($idx) . ' luoghi, ' . count($g) . ' gruppi, ' . count($e) . ' organizzatori possibili'
                            . (count($conf) ? ' · ⚠ ' . count($conf) . ' possibili duplicati' : ''),
                        'lines' => array_map(fn($ids) => '⚠ stesso place_id: ' . implode(', ', array_unique($ids)), $conf),
                    ];
                },
            ],

            'privacy' => [
                'title' => 'Dati personali fuori dai file pubblici',
                'meta'  => 'Sposta nome ed email dai profili e dalle registrazioni all\'archivio privato',
                'icon'  => 'shield_lock', 'scope' => 'privacy', 'preview' => true, 'since' => '2026.08',
                'confirm' => 'Spostare i dati personali nell\'archivio privato? I file pubblici verranno riscritti senza nome ed email.',
                'run' => function (string $base, bool $apply, array $o): array {
                    require_once __DIR__ . '/ws-private.php';
                    $r = ws_privacy_migrate($base, $apply);
                    $tot = count($r['profiles']) + count($r['rsvp']);
                    return [
                        'changes' => $tot,
                        'summary' => $tot ? (count($r['profiles']) . ' profili, ' . count($r['rsvp']) . ' file di registrazioni')
                                          : 'Nessun dato personale nei file pubblici.',
                        'lines' => array_merge(
                            array_map(fn($p) => "users/{$p['uid']} → " . implode(', ', $p['fields']), $r['profiles']),
                            array_map(fn($x) => "{$x['event']}/rsvp.json → {$x['entries']} registrazioni", $r['rsvp'])
                        ),
                    ];
                },
            ],
        ];
    }

    // Esegue un'operazione del registro. $apply=false → anteprima (non scrive),
    // ammessa solo per chi la dichiara: le altre scrivono e basta.
    function ws_maint_run(string $base, string $id, bool $apply, array $opts = [], string $by = ''): array {
        $ops = ws_maint_ops();
        if (!isset($ops[$id])) return ['error' => "Operazione sconosciuta: '$id'."];
        $op = $ops[$id];
        if (!$apply && empty($op['preview'])) return ['error' => "L'operazione «{$op['title']}» non ha un'anteprima."];
        $rep = ($op['run'])($base, $apply, $opts);
        $rep['applied'] = $apply;
        $rep['title'] = $op['title'];
        if ($apply) ws_maint_record($base, $id, $rep, $by);
        return $rep;
    }

    // Elenco per l'interfaccia: le voci senza la funzione (non serializzabile),
    // con l'ultima esecuzione registrata su questa installazione.
    function ws_maint_list(string $base, ?string $scope = null): array {
        $st = ws_maint_state($base);
        $out = [];
        foreach (ws_maint_ops() as $id => $op) {
            if ($scope !== null && $op['scope'] !== $scope) continue;
            unset($op['run']);
            $out[] = $op + ['id' => $id, 'last' => $st[$id] ?? null];
        }
        return $out;
    }
}
