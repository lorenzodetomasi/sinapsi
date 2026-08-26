<?php
/**
 * Normalizzazione di date, fusi e @id degli eventi.
 *
 * Tre cose che l'editor fa ora a ogni salvataggio, ma che i file scritti prima non
 * hanno, e che a mano sarebbero centinaia di modifiche:
 *
 *  1. le DATE portano lo scarto da UTC (`2026-08-31T19:00:00+02:00`). Un'ora senza
 *     scarto è ambigua: chi legge il JSON da fuori non sa a quale orologio si
 *     riferisce;
 *  2. il FUSO viaggia in `meetoo:timezone`, perché dallo scarto non si ricava
 *     (+02:00 d'estate ce l'ha mezza Europa). Si deduce dal paese del luogo;
 *  3. l'@id coincide con la CARTELLA. La cartella è la verità — è lì che il file
 *     sta, ed è quello che i percorsi risolvono — quindi un @id che dice altro si
 *     riallinea a lei, non viceversa.
 *
 * Le cartelle con il nome malformato (per esempio `20260716T11730-…`, cinque cifre
 * nell'ora) qui vengono solo SEGNALATE: rinominarle significa spostare una cartella
 * e riscrivere ogni riferimento che la cita, e non può essere l'effetto collaterale
 * di una normalizzazione. Per quello c'è `ws_quando_rinomina()`, in fondo a questo
 * file, che è un'operazione a sé e mostra prima quali file toccherebbe.
 */

if (!function_exists('ws_quando_fuso_del_luogo')) {
    /** Il fuso di un luogo, dal paese scritto nel suo @id (`places/IT00122/…` → IT).
     *  Solo per i paesi con UN fuso solo: dove ce n'è più d'uno, indovinare è peggio
     *  che lasciar stare. */
    function ws_quando_fuso_del_luogo(?string $idLuogo): string {
        if (!preg_match('#^places/([A-Z]{2})\d{4,5}/#', (string)$idLuogo, $m)) return '';
        $zone = DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, $m[1]);
        return (is_array($zone) && count($zone) === 1) ? $zone[0] : '';
    }
}

if (!function_exists('ws_quando_con_offset')) {
    /** Aggiunge (o rifà) lo scarto a una data con l'ora. Le date senza ora restano
     *  nude: «2026-06-07» è un giorno, non un istante. Quello che non è una data
     *  resta com'è: un valore rotto va corretto a mano, non limato di nascosto. */
    function ws_quando_con_offset(?string $iso, string $zona): ?string {
        $s = trim((string)$iso);
        if ($s === '' || strpos($s, 'T') === false) return $iso;
        if (!preg_match('/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2})/', $s, $m)) return $iso;
        try {
            $dt = new DateTime($m[1], new DateTimeZone($zona));
        } catch (Exception $e) {
            return $iso;
        }
        return $m[1] . ':00' . $dt->format('P');
    }
}

if (!function_exists('ws_quando_id_atteso')) {
    /** L'@id che una cartella si porta dietro per il solo fatto di chiamarsi così. */
    function ws_quando_id_atteso(string $cartella): string {
        return 'events/' . trim($cartella, '/');
    }
}

if (!function_exists('ws_quando_nome_regolare')) {
    /** Il nome della cartella rispetta la regola? Ritorna '' se sì, il perché se no. */
    function ws_quando_nome_regolare(string $nome, bool $serie): string {
        if ($serie) {
            return preg_match('/^[a-z0-9][a-z0-9_-]*$/', $nome)
                ? '' : 'una collezione si scrive in minuscolo (es. clubdellibro-ostia-reading_party)';
        }
        if (!preg_match('/^\d{8}T\d{4}-/', $nome)) return 'un evento singolo comincia con data e ora (es. 20261017T1000-…)';
        return preg_match('/^\d{8}T\d{4}-([A-Z]{2}\d{4,5}|online)-[a-z0-9][a-z0-9_-]*$/', $nome)
            ? '' : 'la forma è data-CAP-nome, o data-online-nome';
    }
}

if (!function_exists('ws_quando_documento')) {
    /**
     * La normalizzazione di UN evento: @id, fuso, date. Non tocca il disco.
     *
     * Era chiusa dentro la passata su tutte le cartelle. Sta fuori perché la
     * stessa regola serve anche a «Normalizza i contenuti», che legge ogni file
     * una volta sola e gli applica tutte le trasformazioni insieme: due copie
     * della stessa regola, prima o poi, diventano due regole diverse.
     *
     * Ritorna ['e' => entità aggiornata, 'cambi' => [], 'segnalati' => []].
     */
    function ws_quando_documento(array $e, string $cartella, string $predefinito = 'Europe/Rome'): array {
        $cambi = [];
        $segnalati = [];
        $nome = $cartella;

        $tipi = (array)($e['@type'] ?? []);
        $serie = in_array('EventSeries', $tipi, true);

        // 1) L'@id segue la cartella.
        $atteso = ws_quando_id_atteso($nome);
        if (($e['@id'] ?? '') !== $atteso) {
            $cambi[] = '@id: ' . ($e['@id'] ?? '(assente)') . " → $atteso";
            $e['@id'] = $atteso;
        }
        // …e il nome della cartella si guarda, ma non si tocca (lo cambia semmai
        // «Rinomina le cartelle malformate», che sa anche riscrivere i riferimenti).
        $perche = ws_quando_nome_regolare($nome, $serie);
        if ($perche !== '') $segnalati[] = "$nome: $perche";

        // 2) Il fuso: dal luogo, o quello del sito.
        $zona = ws_quando_fuso_del_luogo($e['location']['@id'] ?? '') ?: $predefinito;

        if (($e['meetoo:timezone'] ?? '') !== $zona && (isset($e['startDate']) || isset($e['endDate']))) {
            $cambi[] = 'meetoo:timezone: ' . ($e['meetoo:timezone'] ?? '(assente)') . " → $zona";
            $e['meetoo:timezone'] = $zona;
        }

        // 3) Le date, con lo scarto.
        foreach (['startDate', 'endDate'] as $campo) {
            if (!isset($e[$campo]) || !is_string($e[$campo])) continue;
            $nuovo = ws_quando_con_offset($e[$campo], $zona);
            if ($nuovo !== $e[$campo]) {
                $cambi[] = "$campo: {$e[$campo]} → $nuovo";
                $e[$campo] = $nuovo;
            }
        }
        // Il programma di un evento singolo (le occorrenze di una serie sono
        // riferimenti: le loro date stanno nei loro file, non qui).
        if (!$serie && isset($e['subEvent']) && is_array($e['subEvent'])) {
            foreach ($e['subEvent'] as $i => $s) {
                if (!is_array($s)) continue;
                foreach (['startDate', 'endDate'] as $campo) {
                    if (!isset($s[$campo]) || !is_string($s[$campo])) continue;
                    $nuovo = ws_quando_con_offset($s[$campo], $zona);
                    if ($nuovo !== $s[$campo]) {
                        $cambi[] = "subEvent[$i].$campo: {$s[$campo]} → $nuovo";
                        $e['subEvent'][$i][$campo] = $nuovo;
                    }
                }
            }
        }
        return ['e' => $e, 'cambi' => $cambi, 'segnalati' => $segnalati];
    }
}

if (!function_exists('ws_quando_normalizza')) {
    /**
     * Passa tutti gli eventi. In anteprima non scrive niente.
     * Ritorna ['changes' => n, 'done' => [...], 'segnalati' => [...]].
     */
    function ws_quando_normalizza(string $base, bool $apply, string $predefinito = 'Europe/Rome'): array {
        $dir = rtrim($base, '/') . '/events';
        $done = [];
        $segnalati = [];
        if (!is_dir($dir)) return ['changes' => 0, 'done' => [], 'segnalati' => []];

        foreach (scandir($dir) as $nome) {
            if ($nome === '.' || $nome === '..' || $nome === '_index') continue;
            $file = "$dir/$nome/index.json";
            if (!is_file($file)) continue;
            $raw = (string)file_get_contents($file);
            $doc = json_decode($raw, true);
            if (!is_array($doc)) { $segnalati[] = "$nome: JSON illeggibile"; continue; }

            $guscio = isset($doc['mainEntity']) && is_array($doc['mainEntity']);
            $esito = ws_quando_documento($guscio ? $doc['mainEntity'] : $doc, $nome, $predefinito);
            $e = $esito['e'];
            $cambi = $esito['cambi'];
            foreach ($esito['segnalati'] as $s) $segnalati[] = $s;
            if (!$cambi) continue;
            $done[] = ['path' => $nome, 'cambi' => $cambi];
            if (!$apply) continue;

            if ($guscio) $doc['mainEntity'] = $e; else $doc = $e;
            $scritto = json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($scritto === false || @file_put_contents($file, $scritto . "\n") === false) {
                $segnalati[] = "$nome: scrittura fallita";
                array_pop($done);
            }
        }
        return ['changes' => count($done), 'done' => $done, 'segnalati' => $segnalati];
    }
}

if (!function_exists('ws_quando_slug')) {
    /** Parole minuscole legate da `_`, senza accenti: la coda di un @id. */
    function ws_quando_slug(?string $testo): string {
        $s = @iconv('UTF-8', 'ASCII//TRANSLIT', (string)$testo);
        $s = strtolower($s === false ? (string)$testo : $s);
        $parti = preg_split('/[^a-z0-9]+/', $s, -1, PREG_SPLIT_NO_EMPTY);
        return implode('_', $parti ?: []);
    }
}

if (!function_exists('ws_quando_nome_proposto')) {
    /**
     * Il nome che una cartella dovrebbe avere, ricavato dal CONTENUTO e non dalla
     * stringa: la data la dice `startDate`, il dove la dice il luogo. La coda —
     * la parte redazionale, quella scelta da chi scrive — si conserva com'è: è
     * l'unico pezzo che nessuna regola sa rifare meglio di chi l'ha deciso.
     * Ritorna '' se dal contenuto non si ricava abbastanza.
     */
    function ws_quando_nome_proposto(string $nome, array $e, bool $serie): string {
        if ($serie) {
            // Nelle collezioni il trattino separa organizzatore e titolo: si tiene.
            $pulito = strtolower(preg_replace('/[^A-Za-z0-9_-]+/', '-', $nome));
            $pulito = trim(preg_replace('/-{2,}/', '-', $pulito), '-');
            return $pulito;
        }
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/', (string)($e['startDate'] ?? ''), $d)) return '';
        $quando = "{$d[1]}{$d[2]}{$d[3]}T{$d[4]}{$d[5]}";

        $idLuogo = (string)($e['location']['@id'] ?? '');
        $dove = '';
        if (preg_match('#^places/([A-Z]{2}\d{4,5})/#', $idLuogo, $m)) $dove = $m[1];
        elseif (strpos($idLuogo, 'places/online/') === 0) $dove = 'online';
        elseif (stripos((string)($e['eventAttendanceMode'] ?? ''), 'OnlineEventAttendanceMode') !== false) $dove = 'online';
        if ($dove === '') return '';

        // La coda: quello che resta togliendo data e luogo dal nome di adesso.
        $coda = preg_replace('/^\d{4,8}T?\d{0,6}-/', '', $nome);
        $coda = preg_replace('/^([A-Za-z]{2}\d{4,5}|online)-/i', '', $coda);
        if ($coda === '' || $coda === $nome) $coda = ws_quando_slug($e['name'] ?? '');
        if ($coda === '') return '';
        return "$quando-$dove-" . strtolower($coda);
    }
}

if (!function_exists('ws_quando_riferimenti')) {
    /** I file che citano `events/<id>`: percorso relativo → quante volte.
     *  `_index/` non si conta: è derivato, si rigenera dopo.
     *  `_trash/` nemmeno, ed è più importante: lì dentro ci sono le cose com'erano
     *  — eventi cestinati, copie di riserva della normalizzazione — e riscriverle
     *  vuol dire falsificare un archivio. Peggio: la copia di riserva di una
     *  rinomina finiva riscritta dalla rinomina stessa, e il ripristino rimetteva
     *  a posto file che parlavano già del nome nuovo. */
    function ws_quando_riferimenti(string $base, string $id): array {
        $out = [];
        $radice = rtrim($base, '/');
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($radice, FilesystemIterator::SKIP_DOTS));
        // Il confine a destra evita di scambiare un @id per il prefisso di un altro.
        $re = '#' . preg_quote("events/$id", '#') . '(?![A-Za-z0-9_-])#';
        foreach ($it as $f) {
            $nome = $f->getFilename();
            if ($nome !== 'index.json' && $nome !== 'index.xml') continue;
            $rel = ltrim(str_replace($radice, '', $f->getPathname()), '/');
            if (strpos($rel, '_index/') !== false || strpos($rel, '_trash/') === 0) continue;
            $raw = (string)file_get_contents($f->getPathname());
            $n = preg_match_all($re, $raw);
            if ($n) $out[$rel] = $n;
        }
        return $out;
    }
}

if (!function_exists('ws_quando_rinomina')) {
    /**
     * Rinomina le cartelle degli eventi che non rispettano la regola, e riscrive i
     * riferimenti che le citano. In anteprima non tocca niente e dice esattamente
     * quali file verrebbero riscritti.
     *
     * L'ordine conta: PRIMA si riscrivono i riferimenti (compreso quello che il file
     * fa a se stesso), POI si sposta la cartella. Al contrario, i percorsi raccolti
     * non esisterebbero più.
     */
    function ws_quando_rinomina(string $base, bool $apply): array {
        $dir = rtrim($base, '/') . '/events';
        $piano = [];
        $problemi = [];
        if (!is_dir($dir)) return ['changes' => 0, 'piano' => [], 'problemi' => []];

        $presi = [];
        foreach (scandir($dir) as $nome) {
            if ($nome === '.' || $nome === '..' || $nome === '_index') continue;
            $file = "$dir/$nome/index.json";
            if (!is_file($file)) continue;
            $doc = json_decode((string)file_get_contents($file), true);
            if (!is_array($doc)) { $problemi[] = "$nome: JSON illeggibile"; continue; }
            $e = (isset($doc['mainEntity']) && is_array($doc['mainEntity'])) ? $doc['mainEntity'] : $doc;
            $serie = in_array('EventSeries', (array)($e['@type'] ?? []), true);

            $perche = ws_quando_nome_regolare($nome, $serie);
            if ($perche === '') continue;

            $nuovo = ws_quando_nome_proposto($nome, $e, $serie);
            if ($nuovo === '' || $nuovo === $nome) {
                $problemi[] = "$nome: $perche — dal contenuto non si ricava un nome migliore, va deciso a mano";
                continue;
            }
            if (ws_quando_nome_regolare($nuovo, $serie) !== '') {
                $problemi[] = "$nome: il nome che si ricava ($nuovo) non sarebbe comunque regolare";
                continue;
            }
            if (is_dir("$dir/$nuovo") || isset($presi[$nuovo])) {
                $problemi[] = "$nome → $nuovo: esiste già una cartella con quel nome";
                continue;
            }
            $presi[$nuovo] = true;
            $piano[] = ['da' => $nome, 'a' => $nuovo, 'perche' => $perche, 'file' => ws_quando_riferimenti($base, $nome)];
        }

        if (!$apply) return ['changes' => count($piano), 'piano' => $piano, 'problemi' => $problemi];

        $fatti = [];
        foreach ($piano as $p) {
            $re = '#' . preg_quote('events/' . $p['da'], '#') . '(?![A-Za-z0-9_-])#';
            $scritti = 0;
            foreach (array_keys($p['file']) as $rel) {
                $f = rtrim($base, '/') . '/' . $rel;
                $raw = (string)@file_get_contents($f);
                if ($raw === '') continue;
                $nuovoRaw = preg_replace($re, 'events/' . $p['a'], $raw);
                if ($nuovoRaw !== null && $nuovoRaw !== $raw && @file_put_contents($f, $nuovoRaw) !== false) $scritti++;
            }
            if (!@rename("$dir/{$p['da']}", "$dir/{$p['a']}")) {
                $problemi[] = "{$p['da']}: riferimenti riscritti ma la cartella non si è spostata";
                continue;
            }
            $fatti[] = $p + ['scritti' => $scritti];
        }
        return ['changes' => count($fatti), 'piano' => $fatti, 'problemi' => $problemi];
    }
}
