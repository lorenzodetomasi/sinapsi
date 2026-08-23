<?php
/*
 * L'XML è DERIVATO dal JSON: si rigenera, non si scrive a mano.
 *
 * Ogni entità ha (o avrà) due file: `index.json`, che è la fonte — lo scrive
 * l'editor, lo leggono le pagine e i motori come JSON-LD — e `index.xml`, che è
 * la stessa cosa nella forma che consuma il CMS (i template PHP leggono un albero
 * XML) e da cui si producono gli altri formati: PDF via XSL-FO, IDML per InDesign.
 *
 * Il salvataggio li scrive entrambi, ma le MIGRAZIONI no: quella degli `@id` ha
 * riscritto il JSON e lasciato l'XML col vecchio identificativo. Un formato
 * derivato che si disallinea è peggio che non averlo, perciò questa è la rete di
 * sicurezza da passare dopo ogni migrazione.
 *
 * Rigenera ciò che ESISTE. I gemelli mancanti si creano solo se richiesto: oggi
 * 56 luoghi su 58 non hanno un XML, e crearli tutti è una decisione sui contenuti,
 * non una riparazione.
 *
 * ⚠ NON tutti gli index.xml sono gemelli. In `users/<uid>/index.xml` vive il record
 * utente del CMS — ruolo, permessi, percorsi di accesso, notifiche — che NON deriva
 * dal JSON: rigenerarlo cancellerebbe il ruolo. Perciò due protezioni:
 *   1. si toccano solo gli ambiti dichiaratamente derivati (eventi, luoghi,
 *      organizzazioni): `users/` resta fuori;
 *   2. prima di riscrivere si confronta l'elemento RADICE dell'XML esistente con
 *      quello che il convertitore produrrebbe. Se non combaciano, quel file non è
 *      un gemello: non si tocca e si segnala.
 */

if (!function_exists('ws_xml_rebuild')) {

    /** Le cartelle-entità dei contenuti, come percorsi assoluti. */
    function ws_xml_entities(string $base): array {
        $out = [];
        // `users/` è escluso apposta: là l'XML è la fonte, non il derivato.
        foreach (['events/*', 'places/*/*', 'places/*', 'organizations/*'] as $g) {
            foreach (glob(rtrim($base, '/') . "/$g/index.json") as $f) $out[dirname($f)] = true;
        }
        return array_keys($out);
    }

    /**
     * Confronta (e all'occorrenza riscrive) l'XML di ogni entità.
     * $creaMancanti: genera anche i gemelli che non esistono.
     * Ritorna ['riscritti'=>[], 'creati'=>[], 'falliti'=>[], 'controllati'=>int,
     *          'mancanti'=>int, 'nonGemelli'=>[]].
     */
    function ws_xml_rebuild(string $base, bool $apply, bool $creaMancanti = false): array {
        $conv = __DIR__ . '/../json-xml/functions.php';
        if (!is_file($conv)) {
            return ['riscritti' => [], 'creati' => [], 'controllati' => 0, 'mancanti' => 0,
                    'falliti' => [['path' => 'json-xml/functions.php', 'why' => 'convertitore non disponibile']]];
        }
        require_once $conv;

        $riscritti = []; $creati = []; $falliti = []; $controllati = 0; $mancanti = 0; $nonGemelli = [];

        foreach (ws_xml_entities($base) as $dir) {
            $rel = trim(str_replace(rtrim($base, '/'), '', $dir), '/');
            $json = "$dir/index.json";
            $xml  = "$dir/index.xml";
            $esiste = is_file($xml);
            if (!$esiste) { $mancanti++; if (!$creaMancanti) continue; }

            $raw = (string)@file_get_contents($json);
            $doc = json_decode($raw, true);
            if ($doc === null) { $falliti[] = ['path' => $rel, 'why' => 'index.json illeggibile']; continue; }

            // Un @id che non è una stringa diventerebbe «Array» dentro un attributo
            // xlink:href, in silenzio. Meglio fermarsi e dirlo.
            $rotto = ws_xml_bad_id($doc);
            if ($rotto !== null) { $falliti[] = ['path' => $rel, 'why' => "@id non è una stringa in $rotto"]; continue; }

            try {
                $atteso = jsonToWsx($raw);
            } catch (Throwable $e) {
                $falliti[] = ['path' => $rel, 'why' => 'conversione fallita: ' . $e->getMessage()];
                continue;
            }
            if ($atteso === '' || $atteso === null) { $falliti[] = ['path' => $rel, 'why' => 'conversione vuota']; continue; }

            $controllati++;
            $vecchio = $esiste ? trim((string)@file_get_contents($xml)) : '';
            // Confronto sul CONTENUTO, non sulla data del file: un XML può essere
            // più recente e comunque sbagliato (è successo con gli @id).
            if ($esiste && $vecchio === trim($atteso)) continue;

            // Radici diverse = non è un gemello di questo JSON (è il caso del record
            // utente del CMS). Non si riscrive niente che non si sia generato.
            if ($esiste && ws_xml_root($vecchio) !== ws_xml_root($atteso)) {
                $nonGemelli[] = ['path' => $rel, 'root' => ws_xml_root($vecchio), 'atteso' => ws_xml_root($atteso)];
                continue;
            }

            if (!$apply) { if ($esiste) $riscritti[] = $rel; else $creati[] = $rel; continue; }
            if (@file_put_contents($xml, $atteso) === false) {
                $falliti[] = ['path' => $rel, 'why' => 'scrittura fallita'];
            } elseif ($esiste) {
                $riscritti[] = $rel;
            } else {
                $creati[] = $rel;
            }
        }

        sort($riscritti); sort($creati);
        return ['riscritti' => $riscritti, 'creati' => $creati, 'falliti' => $falliti,
                'controllati' => $controllati, 'mancanti' => $mancanti, 'nonGemelli' => $nonGemelli];
    }

    /** Nome dell'elemento radice di un XML (vuoto se illeggibile). */
    function ws_xml_root(string $xml): string {
        return preg_match('/<\s*([A-Za-z_][\w.:-]*)/', preg_replace('/<\?xml.*?\?>/s', '', $xml), $m) ? $m[1] : '';
    }

    /** Primo percorso in cui un @id non è una stringa, oppure null. */
    function ws_xml_bad_id($x, string $path = '') {
        if (!is_array($x)) return null;
        if (array_key_exists('@id', $x) && !is_string($x['@id'])) return $path ?: '(radice)';
        foreach ($x as $k => $v) {
            $r = ws_xml_bad_id($v, $path === '' ? (string)$k : "$path/$k");
            if ($r !== null) return $r;
        }
        return null;
    }
}
