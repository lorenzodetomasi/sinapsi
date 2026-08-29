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


            'normalizza' => [
                'title' => 'Normalizza i contenuti',
                'meta'  => 'Guscio, keywords, date e fusi, @id, relazioni: una lettura e una scrittura per file',
                'icon'  => 'auto_fix_high', 'scope' => 'events', 'preview' => true, 'since' => '2026.08',
                'confirm' => 'Normalizzare i contenuti? I file interessati verranno riscritti, e prima messi da parte in _trash/normalizza/.',
                'options' => [
                    ['key' => 'rinomina', 'label' => 'rinomina le cartelle malformate'],
                    ['key' => 'serie', 'label' => 'sposta nel cestino le serie fuori posto'],
                ],
                'run' => function (string $base, bool $apply, array $o): array {
                    require_once __DIR__ . '/ws-normalizza.php';
                    $r = ws_normalizza($base, $apply, $o);
                    $f = $r['fasi'];
                    $pezzi = [];
                    if ($f['nomi'])      $pezzi[] = $f['nomi'] . ' nomi';
                    if ($f['documento']) $pezzi[] = $f['documento'] . ' documenti';
                    if ($f['relazioni']) $pezzi[] = $f['relazioni'] . ' relazioni';
                    if ($f['serie'])     $pezzi[] = $f['serie'] . ' serie fuori posto';
                    $sommario = $r['changes']
                        ? implode(' · ', $pezzi) . ($apply ? ' — fatto' : ' da sistemare')
                        : 'I contenuti sono gia\' normalizzati.';
                    if ($apply && $r['punto']) $sommario .= ' · ripristinabile (' . $r['punto'] . ')';
                    return [
                        'changes' => $r['changes'],
                        'summary' => $sommario . (count($r['segnalati']) ? ' · ⚠ ' . count($r['segnalati']) . ' da guardare a mano' : ''),
                        'lines' => array_merge($r['righe'], array_map(fn($x) => "⚠ $x", $r['segnalati'])),
                    ];
                },
            ],

            'migrazione' => [
                'title' => 'Contenuti da migrare',
                'meta'  => 'Chi ha ancora il testo formattato dentro la descrizione, e in che ordine prenderlo',
                'icon'  => 'fact_check', 'scope' => 'events', 'preview' => true, 'readonly' => true, 'since' => '2026.08',
                'run' => function (string $base, bool $apply, array $o): array {
                    require_once __DIR__ . '/ws-migrazione.php';
                    $r = ws_migrazione_stato($base);
                    return [
                        'changes' => $r['changes'],
                        'summary' => $r['changes']
                            ? $r['changes'] . ' da migrare · ' . $r['ok'] . ' gia\' a posto (il Sommario si sposta a mano dall\'editor)'
                            : 'Nessun contenuto da migrare: ' . $r['ok'] . ' hanno la descrizione al posto giusto.',
                        'lines' => array_map(
                            fn($v) => $v['rel'] . ' — ' . $v['motivo'] . ' (' . $v['caratteri'] . ' caratteri)'
                                . ($v['sommario'] ? ' · il Sommario c\'e\' gia\'' : ''),
                            $r['voci']
                        ),
                    ];
                },
            ],

            'ripristina' => [
                'title' => 'Ripristina una normalizzazione',
                'meta'  => 'Rimette i file com\'erano prima dell\'ultima passata di normalizzazione',
                'icon'  => 'settings_backup_restore', 'scope' => 'events', 'preview' => true, 'since' => '2026.08',
                'confirm' => 'Ripristinare i file dell\'ultima normalizzazione? Le modifiche fatte dopo su quei file andranno perse.',
                'run' => function (string $base, bool $apply, array $o): array {
                    require_once __DIR__ . '/ws-normalizza.php';
                    $punti = ws_norm_punti($base);
                    if (!$punti) {
                        return ['changes' => 0, 'summary' => 'Nessun punto di ripristino: la normalizzazione non e\' ancora stata eseguita.', 'lines' => []];
                    }
                    $ultimo = $punti[0]['quando'];
                    $r = ws_norm_ripristina($base, $ultimo, $apply);
                    return [
                        'changes' => $r['changes'],
                        'summary' => ($apply ? 'Ripristinate ' : 'Da ripristinare: ') . $r['changes'] . ' cose dal punto ' . $ultimo
                            . ($r['problemi'] ? ' · ⚠ ' . $r['problemi'] . ' non ripristinabili' : '')
                            . (count($punti) > 1 ? ' · ' . (count($punti) - 1) . ' punti piu\' vecchi restano nel cestino' : ''),
                        'lines' => $r['righe'],
                    ];
                },
            ],

            /* Le medie sono un DERIVATO: si ricalcolano, non si scrivono a mano.
             * Un gruppo raccoglie i voti di tutti gli eventi che ha organizzato,
             * un luogo quelli di tutti quelli che ha ospitato — per questo si
             * legge l'archivio intero e non il singolo evento, e per questo si fa
             * quando lo si chiede invece che a ogni stella toccata. */
            'valutazioni' => [
                'title' => 'Aggiorna le valutazioni medie',
                'meta'  => 'Ricalcola l\'aggregateRating di eventi, gruppi e luoghi dai voti di chi c\'era',
                'icon'  => 'star', 'scope' => 'events', 'preview' => true, 'since' => '2026.08',
                'confirm' => 'Ricalcolare le valutazioni medie? I valori scritti a mano verranno sostituiti dalla media dei voti.',
                'run' => function (string $base, bool $apply, array $o): array {
                    require_once __DIR__ . '/ws-rating.php';
                    $r = ws_rating_aggiorna_tutti($base, $apply);
                    $righe = [];
                    foreach ($r['righe'] as $x) {
                        if ($x['motivo'] === 'nessun cambiamento' || $x['motivo'] === 'gia\' aggiornato') continue;
                        $righe[] = $x['id'] . ': '
                            . ($x['da'] === null ? '(niente)' : $x['da']) . ' → '
                            . ($x['a'] === null ? '(niente)' : $x['a'] . ' su ' . $x['count'] . ' voti')
                            . ($x['motivo'] !== '' ? ' — ' . $x['motivo'] : '');
                    }
                    return [
                        'changes' => $r['cambiati'],
                        'summary' => $r['cambiati'] . ' su ' . $r['bersagli'] . ' da aggiornare',
                        'lines' => $righe,
                    ];
                },
            ],

            'mappa-sito' => [
                'title' => 'Rigenera la mappa del sito',
                'meta'  => 'Un indirizzo per ogni contenuto: e\' cosi\' che le pagine diventano visibili al CMS e ai motori',
                'icon'  => 'map', 'scope' => 'events', 'preview' => true, 'since' => '2026.08',
                'confirm' => 'Rigenerare la mappa del sito? ws_sitemap.wsx verra\' riscritto.',
                'run' => function (string $base, bool $apply, array $o): array {
                    require_once __DIR__ . '/ws-mappa.php';
                    // $base è la cartella del LOCALE (…/meetoo/it_IT): la mappa vive
                    // un livello sopra, accanto ai locali, perché il sito è uno solo.
                    $locale = basename(rtrim($base, '/'));
                    $radiceSito = dirname(rtrim($base, '/'));
                    $sito = basename($radiceSito);
                    $r = ws_mappa_costruisci($radiceSito, $sito, $locale, $apply);
                    $inn = ws_mappa_innesta(dirname($radiceSito), $sito, $apply);
                    // Le due metà dello stesso lavoro: la mappa serve al CMS per
                    // instradare, il sitemap.xml ai motori per trovare. Farne una
                    // sola significa avere pagine che rispondono e che nessuno cerca.
                    $mounts = is_array(WS_MOUNTS) ? WS_MOUNTS : array();  // ws-mappa.php la definisce
                    $pub = ws_mappa_sitemap_pubblico(dirname($radiceSito), $mounts, $apply);
                    $per = [];
                    foreach ($r['voci'] as $v) $per[$v['template']][] = $v['wspath'];
                    $righe = [];
                    foreach ($per as $t => $w) {
                        $righe[] = "$t: " . count($w) . ' pagine · ' . implode(', ', array_slice($w, 0, 3))
                            . (count($w) > 3 ? ' …' : '');
                    }
                    $righe[] = 'mappa generale: ' . $inn['why'];
                    $righe[] = 'sitemap.xml per i motori: ' . $pub['why'];
                    return [
                        'changes' => $r['changes'],
                        'summary' => $r['changes']
                            ? $r['changes'] . ' pagine' . ($apply ? ' scritte in ' . basename($r['file']) : ' da mappare')
                              . (count($r['problemi']) ? ' · ⚠ ' . count($r['problemi']) : '')
                            : 'Nessun contenuto da mappare.',
                        'lines' => array_merge($righe, array_map(fn($x) => "⚠ $x", $r['problemi'])),
                    ];
                },
            ],




            'lists' => [
                'title' => 'Rigenera le liste con regola',
                'meta'  => 'Chi soddisfa la regola entra; ciò che è stato curato a mano resta',
                'icon'  => 'rule', 'scope' => 'places', 'preview' => true, 'since' => '2026.08',
                'confirm' => 'Rigenerare le liste? Le voci nuove verranno aggiunte; niente viene tolto.',
                'run' => function (string $base, bool $apply, array $o): array {
                    require_once __DIR__ . '/ws-listrule.php';
                    $r = ws_listrule_sync($base, $apply);
                    $righe = [];
                    foreach ($r['liste'] as $l) {
                        foreach ($l['aggiunte'] as $id)   $righe[] = "{$l['id']}: + $id";
                        foreach ($l['orfane'] as $id)     $righe[] = "⚠ {$l['id']}: $id non soddisfa più la regola (resta nella lista)";
                        foreach ($l['incomplete'] as $id) $righe[] = "⚠ {$l['id']}: a $id manca il dato su cui si ordina";
                    }
                    return [
                        'changes' => $r['cambiate'],
                        'summary' => count($r['liste']) . ' liste con regola'
                            . ($r['cambiate'] ? " · {$r['cambiate']} da aggiornare" : ' · già in pari'),
                        'lines' => $righe,
                    ];
                },
            ],


            'xml-rebuild' => [
                'title' => 'Riallinea gli XML al JSON',
                'meta'  => 'L\'XML è derivato: si rigenera dopo ogni migrazione',
                'icon'  => 'sync_alt', 'scope' => 'events', 'preview' => true, 'since' => '2026.08',
                'option' => ['key' => 'root', 'label' => 'adotta la nuova radice'],
                'confirm' => 'Riallineare gli XML? Verranno riscritti solo quelli generati dal JSON.',
                'run' => function (string $base, bool $apply, array $o): array {
                    require_once __DIR__ . '/ws-xml.php';
                    $r = ws_xml_rebuild($base, $apply, !empty($o['create']), !empty($o['root']));
                    $n = count($r['riscritti']) + count($r['creati']);
                    return [
                        'changes' => $n,
                        'summary' => $n
                            ? (count($r['riscritti']) . ' da riallineare, ' . count($r['creati']) . ' da creare')
                            : 'Tutti gli XML sono in pari.'
                            . ($r['mancanti'] ? " · {$r['mancanti']} entità senza gemello" : ''),
                        'lines' => array_merge(
                            array_map(fn($x) => "riallineato: $x", $r['riscritti']),
                            array_map(fn($x) => "creato: $x", $r['creati']),
                            array_map(fn($x) => "⚠ {$x['path']}: l'XML ha radice <{$x['root']}>, il JSON produrrebbe <{$x['atteso']}> — non lo tocco", $r['nonGemelli']),
                            array_map(fn($x) => "⚠ {$x['path']}: {$x['why']}", $r['falliti'])
                        ),
                    ];
                },
            ],

            'covers' => [
                'title' => 'Genera le copertine 1920×1080',
                'meta'  => 'Dalle immagini già caricate; l\'originale resta in media-sources',
                'icon'  => 'crop_16_9', 'scope' => 'media', 'preview' => true, 'since' => '2026.08',
                'option' => ['key' => 'adopt', 'label' => 'adotta orfane'],
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
                'icon'  => 'travel_explore', 'scope' => 'places', 'preview' => true, 'since' => '2026.07',
                'run' => function (string $base, bool $apply, array $o): array {
                    require_once __DIR__ . '/../places/index-lib.php';

                    // Queste funzioni scrivono SEMPRE sotto WS_MEETOO_ROOT, non sotto
                    // il $base ricevuto: eseguirle puntando altrove scriverebbe di
                    // nascosto nei contenuti veri. Meglio fermarsi e dirlo.
                    if (realpath($base) !== realpath(WS_MEETOO_ROOT)) {
                        return ['changes' => 0, 'lines' => [],
                            'summary' => 'Questa operazione lavora solo sui contenuti reali (' . WS_MEETOO_ROOT . '), non su una copia.'];
                    }

                    // I tre indici si ricostruiscono in memoria: `*_rebuild()` non
                    // scrive. Così l'anteprima può confrontarli con quelli su disco
                    // e dire che cosa cambierebbe, senza toccare niente.
                    list($idx, $conf) = ws_index_rebuild();
                    $g = ws_gruppi_rebuild();
                    $e = ws_entities_rebuild();

                    $diff = function (array $nuovo, string $file, string $chiave): array {
                        $vecchio = is_file($file) ? json_decode((string)@file_get_contents($file), true) : [];
                        if (!is_array($vecchio)) $vecchio = [];
                        $id = function ($x) use ($chiave) { return is_array($x) ? (string)($x[$chiave] ?? '') : (string)$x; };
                        $a = []; foreach ($vecchio as $k => $v) $a[$chiave === '' ? (string)$k : $id($v)] = $v;
                        $b = []; foreach ($nuovo as $k => $v) $b[$chiave === '' ? (string)$k : $id($v)] = $v;
                        return [
                            'entrano' => array_values(array_diff(array_keys($b), array_keys($a))),
                            'escono'  => array_values(array_diff(array_keys($a), array_keys($b))),
                            'diversi' => count(array_filter(array_intersect_key($b, $a),
                                fn($v, $k) => json_encode($v) !== json_encode($a[$k]), ARRAY_FILTER_USE_BOTH)),
                        ];
                    };
                    $dLuoghi = $diff($idx, ws_index_path(), '');          // mappa place_id → voce
                    $dGruppi = $diff($g, ws_gruppi_path(), '@id');
                    $dEnt    = $diff($e, ws_entities_path(), '@id');

                    $righe = [];
                    foreach ([['luoghi', $dLuoghi], ['gruppi', $dGruppi], ['organizzatori', $dEnt]] as [$nome, $d]) {
                        foreach ($d['entrano'] as $x) $righe[] = "+ $nome: $x";
                        foreach ($d['escono']  as $x) $righe[] = "− $nome: $x";
                        if ($d['diversi']) $righe[] = "~ $nome: {$d['diversi']} voci aggiornate";
                    }
                    foreach ($conf as $ids) $righe[] = '⚠ stesso place_id: ' . implode(', ', array_unique($ids));

                    $cambi = count($dLuoghi['entrano']) + count($dLuoghi['escono']) + $dLuoghi['diversi']
                           + count($dGruppi['entrano']) + count($dGruppi['escono']) + $dGruppi['diversi']
                           + count($dEnt['entrano']) + count($dEnt['escono']) + $dEnt['diversi'];

                    if ($apply) { ws_index_save($idx); ws_gruppi_save($g); ws_entities_save($e); }

                    return [
                        'changes' => $cambi,
                        'summary' => count($idx) . ' luoghi, ' . count($g) . ' gruppi, ' . count($e) . ' organizzatori possibili'
                            . ($cambi ? " · $cambi voci da aggiornare" : ' · indici già in pari')
                            . (count($conf) ? ' · ⚠ ' . count($conf) . ' possibili duplicati' : ''),
                        'lines' => $righe,
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
            /* Una spunta o piu' d'una: chi legge l'elenco ne trova sempre una lista.
             * Prima il registro conosceva solo `option` al singolare, e
             * «Normalizza i contenuti» ne ha due — le fasi che possono far male. */
            if (isset($op['option'])) { $op['options'] = [$op['option']]; unset($op['option']); }
            if (!isset($op['options'])) $op['options'] = [];
            $out[] = $op + ['id' => $id, 'last' => $st[$id] ?? null];
        }
        return $out;
    }
}
