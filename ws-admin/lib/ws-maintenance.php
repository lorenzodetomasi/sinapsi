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
            /* Il vocabolario: le chiavi con prefisso che stavano sotto il
             * proprietario sbagliato. `meetoo:icon` e' del CMS, non di Ostia.
             * La modifica e' testuale — la riscrittura del JSON rifarebbe la
             * formattazione di ogni file toccato — e viene verificata
             * rileggendo il risultato: se non combacia, il file non si scrive. */
            'vocabulary' => [
                'title' => 'Allinea il vocabolario',
                'meta'  => 'Rinomina le chiavi con prefisso finite sotto il proprietario sbagliato (meetoo:icon → ws:icon)',
                'icon'  => 'label', 'scope' => 'tutti', 'preview' => true, 'since' => '2026.09',
                'confirm' => 'Rinominare le chiavi? I file di contenuto verranno riscritti.',
                'run' => function (string $base, bool $apply, array $o): array {
                    require_once __DIR__ . '/../_migrate-vocabulary.php';
                    $r = ws_vocabulary_migrate($base, $apply);
                    $lines = [];
                    foreach ($r['details'] as $rel => $d) $lines[] = "$rel — " . implode(' · ', $d);
                    foreach ($r['problems'] as $p) $lines[] = "⚠ $p";
                    return [
                        'changes' => $r['files'],
                        'summary' => $r['files']
                            ? "{$r['files']} file, {$r['keys']} chiavi" . ($apply ? ' — rinominate' : ' da rinominare')
                            : 'Vocabolario in pari',
                        'lines' => $lines,
                    ];
                },
            ],

            /* The index of the events of ANY content root, in schema.org's own
             * shape - the one a list page matches its `about` against. Distinct
             * from `events-index` below, which is Meetoo's: that one normalises
             * references and builds Meetoo's own groupings (by-cap,
             * by-organizer) into events/_index/. This one knows nothing about
             * zones or postal areas and works on a site that has never heard of
             * them. When Meetoo becomes a WS site like the others, the two
             * become one. */
            'root-events-index' => [
                'title' => 'Indice degli eventi del sito',
                'meta'  => 'Raccoglie gli eventi di questa radice in _index/events.json, in forma schema.org',
                'icon'  => 'event_list', 'scope' => 'tutti', 'preview' => true, 'since' => '2026.09',
                'run' => function (string $base, bool $apply, array $o): array {
                    require_once __DIR__ . '/../_refresh-events-index.php';
                    $r = ws_events_index_build($base, $apply);
                    return [
                        'changes' => $r['changed'] ? 1 : 0,
                        'summary' => "{$r['indexed']} eventi"
                            . ($r['skipped'] ? " · {$r['skipped']} saltati" : '')
                            . ($r['changed'] ? ($apply ? ' · indice riscritto' : ' · l\'indice e\' da rifare') : ' · indice aggiornato'),
                        'lines' => array_map(fn($p) => "⚠ $p", $r['problems']),
                    ];
                },
            ],

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
                            . (!empty($x['fonti']) ? '  [' . $x['fonti'] . ']' : '')
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

                    /*
                     * UN SITO CON PIU' LINGUE QUI NON SI TOCCA.
                     *
                     * Questa operazione è nata per Meetoo, che ha una lingua sola:
                     * lì `<sito>/ws_sitemap.wsx` E' la mappa, piatta, e riscriverla
                     * è giusto. In un sito con due lingue quello stesso file non è
                     * una mappa: è il TELAIO che include `it_IT/ws_sitemap.wsx` e
                     * `en_US/ws_sitemap.wsx`. Riscriverlo piatto lo distrugge.
                     *
                     * E' successo su isotype, in produzione: il telaio è diventato
                     * una mappa con dentro il solo `/`, e da quel momento ogni
                     * pagina che non fosse la home non si trovava più. Il CMS
                     * scorre le mappe dei siti in ordine e prende la prima che
                     * risponde, così `/servizi`, `/chi-siamo` e `/contatti` —
                     * indirizzi che your-website ha uguali — finivano lì: pagine
                     * di un altro sito, con un'altra marca, a un indirizzo di
                     * isotype. Per giorni, senza un errore da nessuna parte.
                     *
                     * Per quei siti la mappa della lingua la fa `sitemaps`, e il
                     * telaio non lo tocca nessuno perché non è derivato.
                     */
                    $lingue = array_filter(glob($radiceSito . '/*', GLOB_ONLYDIR) ?: [],
                        fn($d) => preg_match('/^[a-z]{2}_[A-Z]{2}$/', basename($d)));
                    if (count($lingue) > 1) {
                        $nomi = implode(', ', array_map('basename', $lingue));
                        return [
                            'changes' => 0,
                            'summary' => "Non eseguita: «{$sito}» ha piu' lingue ($nomi), e li' "
                                . "$sito/ws_sitemap.wsx e' il telaio che le include, non una mappa. "
                                . "Usa «Rigenera le mappe» (sitemaps), che riscrive la mappa della lingua.",
                            'lines' => [],
                        ];
                    }
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


            'contents' => [
                'title' => 'Riallinea gli XML al JSON',
                'meta'  => 'Il JSON è la fonte, l\'XML il suo gemello derivato: qui si rifanno quelli vecchi o mancanti',
                'icon'  => 'sync_alt', 'scope' => 'contents', 'preview' => true, 'since' => '2026.09',
                'option' => ['key' => 'adopt_root', 'label' => 'adotta la nuova radice'],
                'confirm' => 'Riallineare gli XML? Verranno riscritti solo i gemelli generati dal JSON.',
                'run' => function (string $base, bool $apply, array $o): array {
                    // The generic module of the CMS (ws-admin/refresh-contents.php):
                    // every content of the root, whatever the site. Staleness comes
                    // from the derived-files manifest, not from dates or a diff.
                    require_once __DIR__ . '/../refresh-contents.php';
                    $r = ws_refresh_contents($base, $apply, ['adopt_root' => !empty($o['adopt_root'])]);
                    $n = $r['changes'];
                    return [
                        'changes' => $n,
                        'summary' => ($n
                            ? ($apply
                                ? count($r['rebuilt']) . ' riallineati, ' . count($r['created']) . ' creati'
                                : "$n da riallineare o creare")
                            : 'Tutti gli XML sono in pari.')
                            . " · {$r['fresh']} in pari"
                            . ($r['skipped'] ? ' · ⚠ ' . count($r['skipped']) . ' non gemelli' : '')
                            . ($r['failed'] ? ' · ⚠ ' . count($r['failed']) . ' falliti' : ''),
                        'lines' => array_merge(
                            array_map(fn($x) => "da rifare: $x", $r['stale']),
                            array_map(fn($x) => "riallineato: $x", $r['rebuilt']),
                            array_map(fn($x) => "creato: $x", $r['created']),
                            array_map(fn($x) => "⚠ {$x['path']}: {$x['why']}", $r['skipped']),
                            array_map(fn($x) => "⚠ {$x['path']}: {$x['why']}", $r['failed'])
                        ),
                    ];
                },
            ],

            'migrate' => [
                'title' => 'Migra le pagine da .wsx a JSON',
                'meta'  => 'Il JSON diventa la fonte della pagina; il .wsx resta accanto, disattivato con il trattino',
                'icon'  => 'move_up', 'scope' => 'contents', 'preview' => true, 'since' => '2026.09',
                'confirm' => 'Migrare le pagine? Per ognuna si scrive index.json e il suo gemello XML; index.wsx viene rinominato -index.wsx.',
                'run' => function (string $base, bool $apply, array $o): array {
                    require_once __DIR__ . '/../migrate-pages.php';
                    $r = ws_migrate_pages($base, $apply);
                    $lines = [];
                    foreach ($r['would'] as $w) {
                        $lines[] = "{$w['path']} → {$w['type']}";
                        foreach ($w['notes'] as $n) $lines[] = "    · $n";
                    }
                    foreach ($r['migrated'] as $w) {
                        $lines[] = "migrata: {$w['path']} → {$w['type']}";
                        foreach ($w['notes'] as $n) $lines[] = "    · $n";
                    }
                    foreach ($r['skipped'] as $x) $lines[] = "saltata: {$x['path']}: {$x['why']}";
                    foreach ($r['failed'] as $x)  $lines[] = "⚠ {$x['path']}: {$x['why']}";
                    $n = $r['changes'];
                    return [
                        'changes' => $n,
                        'summary' => ($n ? ($apply ? "$n pagine migrate" : "$n pagine da migrare") : 'Nessuna pagina in .wsx: tutte in JSON.')
                            . ($r['skipped'] ? ' · ' . count($r['skipped']) . ' non pagine' : '')
                            . ($r['failed'] ? ' · ⚠ ' . count($r['failed']) . ' fallite' : ''),
                        'lines' => $lines,
                    ];
                },
            ],

            'sitemaps' => [
                'title' => 'Rigenera la mappa del sito dalle pagine',
                'meta'  => 'Una pagina è sulla mappa perché il suo index.json esiste; poi il sitemap.xml per i motori',
                'icon'  => 'map', 'scope' => 'contents', 'preview' => true, 'since' => '2026.09',
                'confirm' => 'Rigenerare la mappa? ws_sitemap.wsx di questo sito e sitemap.xml generale verranno riscritti.',
                'run' => function (string $base, bool $apply, array $o): array {
                    require_once __DIR__ . '/../refresh-sitemaps.php';
                    $r = ws_refresh_sitemaps($base, $apply);
                    $m = $r['map'];
                    $lines = [];
                    if ($m['status'] === 'skipped' || $m['status'] === 'failed') {
                        return ['changes' => 0, 'summary' => ($m['status'] === 'failed' ? '⚠ ' : '') . $m['why'], 'lines' => []];
                    }
                    $lines[] = "{$m['pages']} pagine sulla mappa: {$m['json']} da JSON, {$m['wsx']} ancora da .wsx";
                    foreach ($m['fragments'] as $f)   $lines[] = "inclusa come frammento: $f";
                    foreach ($m['legacy_maps'] as $f) $lines[] = "⚠ mappa scritta a mano, non più letta (le sue pagine sono già qui): $f";
                    foreach ($m['problems'] as $pr)   $lines[] = "⚠ $pr";
                    if ($r['public']) $lines[] = 'sitemap.xml per i motori: ' . $r['public']['why'];
                    return [
                        'changes' => $r['changes'],
                        'summary' => $m['status'] === 'fresh' ? 'La mappa è in pari.'
                            : ($apply ? "Mappa {$m['status']}: {$m['pages']} pagine" : "Mappa da rifare: {$m['pages']} pagine"),
                        'lines' => $lines,
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

            /* Il numero era giusto, il nome no.
             *
             * Google manda `user_ratings_total`: quante persone hanno messo le
             * stelline. In schema.org quello e' `ratingCount`; `reviewCount` sono
             * le recensioni SCRITTE, che sono un'altra cosa e quasi sempre molte
             * di meno. Per anni l'abbiamo salvato sotto `reviewCount`, e chi legge
             * i nostri dati da fuori si sente dire che 426 persone hanno scritto
             * una recensione quando hanno votato e basta. Qui si sposta il valore
             * sotto il nome giusto.
             *
             * Si tocca SOLO cio' che viene da Google (c'e' un `google_place_id`) e
             * solo dove `ratingCount` non c'e' ancora: una media gia' ricalcolata
             * da noi ha tutti e due i campi e non va rimessa in discussione. */
            'voti-google' => [
                'title' => 'I voti di Google sotto il nome giusto',
                'meta'  => 'reviewCount -> ratingCount dove il numero viene da Google',
                'icon'  => 'star_half', 'scope' => 'places', 'preview' => true, 'since' => '2026.09',
                'run' => function (string $base, bool $apply, array $o): array {
                    $righe = []; $n = 0;
                    $files = array_merge(
                        glob("$base/places/*/index.json") ?: [],
                        glob("$base/places/*/*/index.json") ?: [],
                        glob("$base/organizations/*/index.json") ?: [],
                        glob("$base/events/*/index.json") ?: []
                    );
                    foreach ($files as $f) {
                        $j = json_decode((string)@file_get_contents($f), true);
                        if (!is_array($j)) continue;
                        $dentro = isset($j['mainEntity']) && is_array($j['mainEntity']);
                        $e = $dentro ? $j['mainEntity'] : $j;
                        $agg = isset($e['aggregateRating']) && is_array($e['aggregateRating']) ? $e['aggregateRating'] : null;
                        if (!$agg) continue;
                        if (isset($agg['ratingCount'])) continue;
                        if (!isset($agg['reviewCount']) || $agg['reviewCount'] === null) continue;
                        $daGoogle = !empty($e['meetoo:google_place_id']) || !empty($e['meetoo:googlePlaceId']);
                        if (!$daGoogle) continue;
                        $val = (int)$agg['reviewCount'];
                        $id = (string)($e['@id'] ?? basename(dirname($f)));
                        $righe[] = "$id - $val voti: reviewCount -> ratingCount";
                        $n++;
                        if ($apply) {
                            $agg['ratingCount'] = $val;
                            unset($agg['reviewCount']);
                            $e['aggregateRating'] = $agg;
                            if ($dentro) { $j['mainEntity'] = $e; } else { $j = $e; }
                            @file_put_contents($f, json_encode($j, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                        }
                    }
                    return [
                        'changes' => $n,
                        'summary' => $n
                            ? ($apply ? "$n schede sistemate." : "$n schede da sistemare: il conteggio dei voti sta sotto «recensioni».")
                            : 'Niente da spostare: i conteggi sono gia\' al posto giusto.',
                        'lines' => $righe,
                    ];
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
