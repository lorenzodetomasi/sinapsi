<?php
/*
 * «Normalizza i contenuti»: una passata sola, in fasi ordinate.
 *
 * Prima erano quattro operazioni separate — guscio, keywords, date e fusi,
 * relazioni fra eventi — e funzionavano tutte. Il problema non era che
 * sbagliassero: era che ognuna leggeva e riscriveva gli stessi file per conto
 * suo, che l'ordine giusto lo doveva sapere chi premeva i bottoni, e che ogni
 * anteprima era calcolata sul disco com'era in quel momento, ignorando quello che
 * le altre tre avrebbero cambiato. Quattro rapporti che non descrivevano mai lo
 * stesso stato finale.
 *
 * L'ORDINE, e perché è quello:
 *
 *   1. NOMI (facoltativa) — rinominare una cartella cambia l'indirizzo di un
 *      evento, e quindi il suo @id e tutti i riferimenti che lo citano. Va fatto
 *      per primo, o il passo 2 scriverebbe @id destinati a cambiare subito dopo.
 *   2. DOCUMENTO — guscio, keywords, @id, fuso, date. Qui ogni file si legge una
 *      volta, si trasforma in memoria e si riscrive una volta. È anche il solo
 *      punto in cui il gemello `index.xml` viene rigenerato: due delle quattro
 *      operazioni vecchie non lo facevano, e lasciavano il gemello a raccontare
 *      un'altra storia.
 *   3. RELAZIONI — subEvent/superEvent, occorrenze mancanti. Viene dopo perché
 *      lavora sui riferimenti per @id, e gli @id li ha appena sistemati il passo 2.
 *   4. DERIVATI — l'indice degli eventi, e la mappa del sito se gli indirizzi
 *      sono cambiati. Una volta sola invece di una per operazione.
 *
 * NIENTE SI PERDE. Prima della prima scrittura si apre un PUNTO DI RIPRISTINO in
 * `_trash/normalizza/<quando>/`: ogni file che sta per essere riscritto ci viene
 * copiato com'era, ogni cartella spostata ci finisce dentro, e un manifest dice
 * che cosa è successo. «Ripristina una normalizzazione» rimette tutto a posto.
 *
 * LE FASI CHE POSSONO FAR MALE SONO SPENTE. Rinominare cartelle e spostare serie
 * mal posizionate cambiano indirizzi e tolgono roba di mezzo: si accendono a mano,
 * e finché sono spente il rapporto dice quante cose aspettano — così si possono
 * eseguire dopo, quando si è guardato con calma.
 */

require_once __DIR__ . '/ws-wrap.php';
require_once __DIR__ . '/ws-keywords.php';
require_once __DIR__ . '/ws-quando.php';
require_once __DIR__ . '/events-normalize.php';
require_once __DIR__ . '/events-index.php';

if (!function_exists('ws_norm_punti_dir')) {

/* ===========================================================================
 * PUNTI DI RIPRISTINO
 *
 * Stessa idea del cestino degli eventi, e stessa casa: si SPOSTA o si COPIA,
 * non si distrugge, e un manifest ricorda dove stava ogni cosa. Il punto si apre
 * pigramente, alla prima scrittura: una normalizzazione che non cambia niente non
 * lascia cartelle vuote in giro.
 * ========================================================================= */

function ws_norm_punti_dir(string $base): string {
    return rtrim($base, '/') . '/_trash/normalizza';
}

/** I punti di ripristino esistenti, dal più recente. */
function ws_norm_punti(string $base): array {
    $dir = ws_norm_punti_dir($base);
    if (!is_dir($dir)) return [];
    $out = [];
    foreach (scandir($dir) as $n) {
        if ($n === '.' || $n === '..') continue;
        $m = "$dir/$n/manifest.json";
        if (!is_file($m)) continue;
        $j = json_decode((string)@file_get_contents($m), true);
        if (is_array($j)) $out[] = $j + ['quando' => $n];
    }
    usort($out, fn($a, $b) => strcmp($b['quando'], $a['quando']));
    return $out;
}

/** Apre (in memoria) un punto di ripristino. Sul disco nasce alla prima copia. */
function ws_norm_apri(string $base): array {
    return ['base' => rtrim($base, '/'), 'quando' => date('Ymd-His'), 'voci' => [], 'aperto' => false];
}

/** La cartella del punto, creata al primo bisogno. */
function ws_norm_cartella(array &$p): string {
    $dir = ws_norm_punti_dir($p['base']) . '/' . $p['quando'];
    if (!$p['aperto']) {
        @mkdir($dir, 0775, true);
        $p['aperto'] = is_dir($dir);
    }
    return $dir;
}

/**
 * Mette da parte un file PRIMA che venga riscritto. Una volta sola: se lo stesso
 * file viene toccato da due fasi, l'originale da conservare è quello di partenza.
 */
function ws_norm_copia(array &$p, string $rel): bool {
    foreach ($p['voci'] as $v) if ($v['rel'] === $rel) return true;   // già salvato
    $src = $p['base'] . '/' . $rel;
    if (!is_file($src)) return false;
    $dst = ws_norm_cartella($p) . '/' . $rel;
    if (!is_dir(dirname($dst)) && !@mkdir(dirname($dst), 0775, true)) return false;
    if (!@copy($src, $dst)) return false;
    $p['voci'][] = ['rel' => $rel, 'azione' => 'riscritto'];
    return true;
}

/** Sposta una CARTELLA dentro il punto di ripristino (invece di cancellarla). */
function ws_norm_sposta(array &$p, string $rel): bool {
    $src = $p['base'] . '/' . $rel;
    if (!is_dir($src)) return false;
    $dst = ws_norm_cartella($p) . '/' . $rel;
    if (!is_dir(dirname($dst)) && !@mkdir(dirname($dst), 0775, true)) return false;
    if (!@rename($src, $dst)) return false;
    $p['voci'][] = ['rel' => $rel, 'azione' => 'spostato'];
    return true;
}

/**
 * Annota una rinomina: si disfa rinominando al contrario.
 *
 * E porta con sé tutto quello che era già stato messo da parte da lì dentro. È il
 * punto delicato di tutta la faccenda: un file salvato PRIMA della rinomina sta
 * sotto il vecchio nome, uno salvato dopo sotto il nuovo, e sono lo stesso file.
 * Se il manifest tiene le due versioni come se fossero due cose diverse, il
 * ripristino ricrea la cartella vecchia accanto a quella nuova e ne restano due —
 * l'ho visto succedere provando, prima che questa riga esistesse.
 *
 * Quindi ogni voce si riscrive al nome NUOVO, e la copia si sposta di conseguenza
 * dentro il punto: da lì in poi il manifest parla un indirizzo solo, quello finale.
 */
function ws_norm_rinomina(array &$p, string $da, string $a): void {
    $dir = ws_norm_cartella($p);
    if (is_dir("$dir/$da")) {
        if (!is_dir(dirname("$dir/$a"))) @mkdir(dirname("$dir/$a"), 0775, true);
        @rename("$dir/$da", "$dir/$a");
    }
    foreach ($p['voci'] as $i => $v) {
        if (strpos($v['rel'], $da . '/') === 0) {
            $p['voci'][$i]['rel'] = $a . substr($v['rel'], strlen($da));
        }
    }
    $p['voci'][] = ['rel' => $a, 'azione' => 'rinominato', 'da' => $da];
}

/** Annota una cartella CREATA: si disfa togliendola, se nessuno l'ha toccata dopo. */
function ws_norm_creato(array &$p, string $rel): void {
    $p['voci'][] = ['rel' => $rel, 'azione' => 'creato'];
}

/** Scrive il manifest. Senza manifest il punto non esiste per il ripristino. */
function ws_norm_chiudi(array $p, array $meta): ?string {
    if (!$p['aperto'] && !$p['voci']) return null;
    $dir = ws_norm_cartella($p);
    $man = $meta + [
        'quando' => $p['quando'],
        'versione' => defined('MEETOO_VERSION') ? MEETOO_VERSION : '',
        'voci' => $p['voci'],
    ];
    @file_put_contents("$dir/manifest.json", json_encode($man, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    return $p['quando'];
}

/**
 * Rimette le cose com'erano. In anteprima dice soltanto che cosa rimetterebbe.
 *
 * Le cartelle CREATE si tolgono solo se nessuno le ha toccate dopo: un ripristino
 * serve a disfare una normalizzazione andata storta, non a cancellare il lavoro
 * fatto nel frattempo.
 */
function ws_norm_ripristina(string $base, string $quando, bool $apply): array {
    $base = rtrim($base, '/');
    $dir = ws_norm_punti_dir($base) . '/' . $quando;
    $man = "$dir/manifest.json";
    if (!preg_match('/^[0-9]{8}-[0-9]{6}$/', $quando) || !is_file($man)) {
        return ['changes' => 0, 'righe' => ["Punto di ripristino inesistente: $quando"], 'problemi' => 1];
    }
    $j = json_decode((string)file_get_contents($man), true);
    $voci = is_array($j['voci'] ?? null) ? $j['voci'] : [];
    $righe = [];
    $fatte = 0;
    $problemi = 0;
    $quandoTs = strtotime(substr($quando, 0, 8) . 'T' . substr($quando, 9, 6)) ?: time();

    /* PRIMA i contenuti, POI i nomi.
     *
     * Nel manifest ogni percorso è quello FINALE, cioè come si chiamava la cartella
     * quando la normalizzazione ha finito. Quindi si rimettono i file dove il
     * manifest dice — che è dove stanno adesso — e solo dopo si disfano le
     * rinomine, che riportano indietro le cartelle intere, contenuto compreso.
     * Nell'ordine opposto si ricreerebbe la cartella vecchia mentre quella nuova
     * esiste ancora, e alla fine ce ne sarebbero due. */
    usort($voci, fn($a, $b) => (($a['azione'] ?? '') === 'rinominato' ? 1 : 0) <=> (($b['azione'] ?? '') === 'rinominato' ? 1 : 0));

    foreach ($voci as $v) {
        $rel = (string)($v['rel'] ?? '');
        $azione = (string)($v['azione'] ?? '');
        if ($rel === '' || strpos($rel, '..') !== false) { $problemi++; continue; }
        $dest = "$base/$rel";

        if ($azione === 'riscritto') {
            $righe[] = "torna com'era: $rel";
            if (!$apply) { $fatte++; continue; }
            if (!is_dir(dirname($dest))) @mkdir(dirname($dest), 0775, true);
            if (@copy("$dir/$rel", $dest)) $fatte++; else { $problemi++; $righe[] = "⚠ non ripristinato: $rel"; }
        } elseif ($azione === 'spostato') {
            $righe[] = "torna al suo posto: $rel";
            if (!$apply) { $fatte++; continue; }
            if (is_dir($dest)) { $problemi++; $righe[] = "⚠ $rel esiste di nuovo: non lo sovrascrivo"; continue; }
            if (!is_dir(dirname($dest))) @mkdir(dirname($dest), 0775, true);
            if (@rename("$dir/$rel", $dest)) $fatte++; else { $problemi++; $righe[] = "⚠ non ripristinato: $rel"; }
        } elseif ($azione === 'rinominato') {
            $da = (string)($v['da'] ?? '');
            $righe[] = "torna a chiamarsi: $rel → $da";
            if (!$apply) { $fatte++; continue; }
            if ($da === '' || is_dir("$base/$da")) { $problemi++; continue; }
            if (@rename($dest, "$base/$da")) $fatte++; else $problemi++;
        } elseif ($azione === 'creato') {
            // Toccata dopo la normalizzazione? Allora è lavoro di qualcuno: si lascia.
            $recente = false;
            if (is_dir($dest)) {
                foreach (glob("$dest/*") as $f) if (filemtime($f) > $quandoTs + 5) { $recente = true; break; }
            }
            if ($recente) { $righe[] = "⚠ modificata dopo: la lascio — $rel"; continue; }
            $righe[] = "creata dalla normalizzazione, la tolgo: $rel";
            if (!$apply) { $fatte++; continue; }
            if (ws_norm_rmdir($base, $dest)) $fatte++; else $problemi++;
        }
    }
    if ($apply && $fatte) {
        require_once __DIR__ . '/events-index.php';
        event_index_rebuild($base);
        $righe[] = 'indice degli eventi rifatto';
    }
    return ['changes' => $fatte, 'righe' => $righe, 'problemi' => $problemi];
}

/** Cancellazione ricorsiva confinata SOTTO $base: fuori non si tocca niente. */
function ws_norm_rmdir(string $base, string $dir): bool {
    $radice = realpath($base);
    $vero = realpath($dir);
    if ($radice === false || $vero === false) return false;
    if (strpos($vero, $radice . DIRECTORY_SEPARATOR) !== 0) return false;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($vero, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $f) { $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
    return @rmdir($vero);
}

/* ===========================================================================
 * LA PASSATA
 * ========================================================================= */

/** I gruppi di cartelle su cui lavorare, secondo l'ambito. */
function ws_norm_gruppi(string $ambito): array {
    // Da «Gestione | Eventi» si normalizzano gli eventi e basta; dall'hub, tutto.
    if ($ambito === 'events') return ['events/*'];
    return ['events/*', 'places/*', 'places/*/*', 'organizations/*', 'users/*'];
}

/** Il gemello XML, se esiste già. Non se ne creano di nuovi: chi non ce l'ha non lo vuole. */
function ws_norm_gemello(string $file, string $json): ?bool {
    $xml = preg_replace('/\.json$/', '.xml', $file);
    if (!is_file($xml)) return null;
    static $puo = null;
    if ($puo === null) {
        $conv = __DIR__ . '/../json-xml/functions.php';
        $puo = is_file($conv);
        if ($puo) require_once $conv;
    }
    if (!$puo || !function_exists('jsonToWsx')) return false;
    try { return @file_put_contents($xml, jsonToWsx($json)) !== false; }
    catch (\Throwable $e) { return false; }
}

/**
 * Normalizza i contenuti. In anteprima non scrive niente e dice che cosa farebbe.
 *
 * $o: ambito ('events'|'tutti'), serie (bool), rinomina (bool).
 * Ritorna ['changes'=>int, 'fasi'=>[...], 'righe'=>[], 'segnalati'=>[], 'punto'=>?string].
 */
function ws_normalizza(string $base, bool $apply, array $o = []): array {
    $base = rtrim($base, '/');
    $ambito = ($o['ambito'] ?? 'tutti') === 'events' ? 'events' : 'tutti';
    $conSerie = !empty($o['serie']);
    $conRinomina = !empty($o['rinomina']);

    $punto = ws_norm_apri($base);
    $righe = [];
    $segnalati = [];
    $fasi = ['nomi' => 0, 'documento' => 0, 'relazioni' => 0, 'serie' => 0];
    $indirizziCambiati = false;

    /* --- 1. NOMI (facoltativa) -------------------------------------------
     * Si chiede prima l'anteprima anche quando si applica: serve a sapere QUALI
     * file verranno riscritti, per metterli da parte prima che accada. */
    if ($conRinomina) {
        $piano = ws_quando_rinomina($base, false);
        foreach ($piano['problemi'] as $p) $segnalati[] = $p;
        $fasi['nomi'] = $piano['changes'];
        foreach ($piano['piano'] as $p) {
            $righe[] = "nome: events/{$p['da']} → events/{$p['a']} ({$p['perche']})"
                . (count($p['file']) ? ' · ' . count($p['file']) . ' riferimenti' : '');
        }
        if ($apply && $piano['changes']) {
            foreach ($piano['piano'] as $p) {
                foreach (array_keys($p['file']) as $rel) ws_norm_copia($punto, $rel);
            }
            $fatto = ws_quando_rinomina($base, true);
            foreach ($fatto['problemi'] as $x) $segnalati[] = $x;
            foreach ($fatto['piano'] as $p) ws_norm_rinomina($punto, "events/{$p['da']}", "events/{$p['a']}");
            $fasi['nomi'] = $fatto['changes'];
            $indirizziCambiati = $indirizziCambiati || $fatto['changes'] > 0;
        }
    } else {
        // Spenta: si dice comunque quante ne aspettano, così si può accendere dopo.
        $piano = ws_quando_rinomina($base, false);
        if ($piano['changes']) {
            $righe[] = "· {$piano['changes']} cartelle hanno un nome irregolare: accendi «rinomina le cartelle» per sistemarle";
        }
    }

    /* --- 2. DOCUMENTO: guscio + keywords + date/fusi/@id, una lettura e una
     * scrittura per file, e il gemello XML rigenerato una volta sola. -------- */
    foreach (ws_norm_gruppi($ambito) as $gruppo) {
        foreach (glob("$base/$gruppo/index.json") as $file) {
            $rel = trim(str_replace($base, '', $file), '/');            // es. events/xxx/index.json
            $cartellaRel = dirname($rel);                                // es. events/xxx
            $nome = basename($cartellaRel);
            $eventoTop = (strpos($gruppo, 'events/') === 0);

            $raw = (string)@file_get_contents($file);
            $doc = json_decode($raw, true);
            if (!is_array($doc)) { $segnalati[] = "$cartellaRel: index.json illeggibile"; continue; }

            $cambi = [];

            // a) il guscio
            if (!ws_wrap_has($doc)) {
                if (empty($doc['@type'])) {
                    $segnalati[] = "$cartellaRel: senza @type, non so che entità sia";
                } else {
                    $doc = ws_wrap_one($doc);
                    $cambi[] = 'guscio ItemPage';
                }
            }
            $ent = ws_wrap_entity($doc);

            // b) le keywords come elenco, senza doppioni
            if (isset($ent['keywords'])) {
                $prima = $ent['keywords'];
                $dopo = ws_keywords_split($prima);
                if (!is_array($prima) || array_values($prima) !== $dopo) {
                    $quante = is_array($prima) ? count($prima) : count(array_filter(array_map('trim', explode(',', (string)$prima)), 'strlen'));
                    $cambi[] = 'keywords: ' . (is_array($prima) ? "$quante voci" : 'stringa') . ' → ' . count($dopo) . ' voci';
                    $ent['keywords'] = $dopo;
                }
            }

            // c) @id, fuso e date: solo gli eventi ce li hanno
            if ($eventoTop) {
                $esito = ws_quando_documento($ent, $nome);
                foreach ($esito['segnalati'] as $s) $segnalati[] = $s;
                if ($esito['cambi']) {
                    $ent = $esito['e'];
                    foreach ($esito['cambi'] as $c) {
                        $cambi[] = $c;
                        if (strpos($c, '@id:') === 0) $indirizziCambiati = true;
                    }
                }
            }

            if (!$cambi) continue;
            $fasi['documento']++;
            $righe[] = $cartellaRel . ': ' . implode(' · ', array_slice($cambi, 0, 4))
                . (count($cambi) > 4 ? ' … (+' . (count($cambi) - 4) . ')' : '');
            if (!$apply) continue;

            $doc = ws_wrap_set($doc, $ent);
            $json = json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($json === false) { $segnalati[] = "$cartellaRel: non si riesce a riscriverlo"; $fasi['documento']--; continue; }
            ws_norm_copia($punto, $rel);
            /* Anche il GEMELLO va messo da parte, perché sta per essere riscritto
             * pure lui. Senza, il ripristino rimetteva a posto il JSON e lasciava
             * l'XML normalizzato: i due tornavano a raccontare storie diverse,
             * che è esattamente il difetto che questa passata esiste per chiudere. */
            $relXml = preg_replace('/\.json$/', '.xml', $rel);
            if (is_file("$base/$relXml")) ws_norm_copia($punto, $relXml);
            if (@file_put_contents($file, $json . "\n") === false) {
                $segnalati[] = "$cartellaRel: scrittura fallita";
                $fasi['documento']--;
                continue;
            }
            // Il gemello: se c'era, ora dice la stessa cosa del JSON.
            $gem = ws_norm_gemello($file, $json);
            if ($gem === false) $segnalati[] = "$cartellaRel: index.xml non rigenerato (si rifà salvando dall'editor)";
        }
    }

    /* --- 3. RELAZIONI fra eventi ------------------------------------------
     * Anche qui prima l'anteprima, per sapere che cosa mettere da parte. La parte
     * che toglie di mezzo le serie mal posizionate la facciamo NOI, spostandole
     * nel punto di ripristino invece di cancellarle. */
    $rel3 = event_normalize($base, false);
    $malposte = $rel3['removedSeries'];
    $daRiscrivere = array_merge(
        array_map(fn($x) => (string)$x, $rel3['repairedSuperEvent']),
        array_map(fn($x) => (string)$x['series'], $rel3['seriesSubEventUpdated'])
    );
    $daCreare = array_map(fn($x) => (string)$x['path'], $rel3['completedOccurrences']);
    $fasi['relazioni'] = count($daRiscrivere) + count($daCreare);
    foreach ($rel3['repairedSuperEvent'] as $x) $righe[] = "relazione: superEvent riparato in $x";
    foreach ($rel3['seriesSubEventUpdated'] as $x) $righe[] = "relazione: {$x['series']} riallineata a {$x['count']} occorrenze";
    foreach ($rel3['completedOccurrences'] as $x) $righe[] = "relazione: occorrenza completata {$x['path']} (da {$x['source']})";

    if ($malposte) {
        $fasi['serie'] = count($malposte);
        foreach ($malposte as $m) {
            $righe[] = $conSerie
                ? "serie fuori posto, va nel cestino: $m"
                : "· serie fuori posto: $m — accendi «sposta le serie fuori posto» per toglierla di mezzo";
        }
    }

    if ($apply && ($fasi['relazioni'] || ($conSerie && $malposte))) {
        // Le serie fuori posto: spostate, non cancellate.
        if ($conSerie) {
            foreach ($malposte as $m) {
                if (ws_norm_sposta($punto, trim($m, '/'))) $indirizziCambiati = true;
                else $segnalati[] = "$m: non si è potuta spostare nel cestino";
            }
        }
        foreach ($daRiscrivere as $r) ws_norm_copia($punto, trim($r, '/') . '/index.json');
        // `event_normalize` non le cancella più: quelle fuori posto o non ci sono
        // più (spostate qui sopra), o restano dove sono perché l'opzione è spenta.
        $fatto = event_normalize($base, true, ['rimuoviSerie' => false]);
        foreach ($daCreare as $c) ws_norm_creato($punto, trim($c, '/'));
        foreach ($fatto['warns'] as $w) $segnalati[] = $w;
    }

    /* --- 4. DERIVATI ------------------------------------------------------- */
    $cambi = $fasi['nomi'] + $fasi['documento'] + $fasi['relazioni'] + ($conSerie ? $fasi['serie'] : 0);
    $codice = null;
    if ($apply && $cambi) {
        event_index_rebuild($base);
        $righe[] = 'indice degli eventi rifatto';
        if ($indirizziCambiati) {
            // Gli indirizzi sono cambiati: la mappa che li elenca è vecchia di un
            // minuto. Rigenerarla qui evita di lasciare in giro pagine che
            // rispondono a un indirizzo che non esiste più.
            require_once __DIR__ . '/ws-mappa.php';
            $locale = basename($base);
            $radiceSito = dirname($base);
            $m = ws_mappa_costruisci($radiceSito, basename($radiceSito), $locale, true);
            ws_mappa_innesta(dirname($radiceSito), basename($radiceSito), true);
            $pub = ws_mappa_sitemap_pubblico(dirname($radiceSito), is_array(WS_MOUNTS) ? WS_MOUNTS : [], true);
            $righe[] = 'mappa del sito rigenerata: ' . count($m['voci']) . ' pagine · ' . $pub['why'];
        }
        $codice = ws_norm_chiudi($punto, [
            'fasi' => $fasi,
            'ambito' => $ambito,
            'opzioni' => ['serie' => $conSerie, 'rinomina' => $conRinomina],
        ]);
    }

    return [
        'changes' => $cambi,
        'fasi' => $fasi,
        'righe' => $righe,
        'segnalati' => $segnalati,
        'punto' => $codice,
        'indirizzi' => $indirizziCambiati,
    ];
}

}// function_exists
