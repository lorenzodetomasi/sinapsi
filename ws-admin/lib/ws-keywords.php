<?php
/*
 * Keywords: da stringa separata da virgole ad ELENCO (array).
 *
 * Perché: la stringa unica non sa rappresentare una keyword che contiene una
 * virgola, e i nomi dei luoghi ne contengono («Lido di Ostia, Roma»). Salvandoli
 * lì dentro venivano spezzati in due voci e poi ri-aggiunti interi, moltiplicando
 * località e regione a ogni salvataggio. Con l'array la domanda non si pone più.
 *
 * L'editor scrive già l'array e legge entrambe le forme; questa migrazione mette in
 * riga i file scritti prima, JSON e XML insieme (l'XML si rigenera dal JSON, come
 * al salvataggio: <keywords> ripetuto, esattamente come <organizer>).
 */

if (!function_exists('ws_keywords_split')) {
    /** Stringa o array → elenco pulito: spezza sulle virgole, toglie spazi e vuoti,
     *  scarta i doppioni (senza distinguere maiuscole) tenendo la prima grafia. */
    function ws_keywords_split($valore): array {
        $out = [];
        $viste = [];
        foreach ((is_array($valore) ? $valore : [$valore]) as $pezzo) {
            foreach (explode(',', (string)$pezzo) as $parte) {
                $v = trim($parte);
                if ($v === '') continue;
                $k = mb_strtolower($v);
                if (isset($viste[$k])) continue;
                $viste[$k] = true;
                $out[] = $v;
            }
        }
        return $out;
    }

    /**
     * Converte le keywords di tutti gli eventi. $apply=false → dice solo cosa farebbe.
     * Ritorna ['done'=>[{path,before,after,xml}], 'failed'=>[{path,why}], 'removed'=>int].
     */
    function ws_keywords_migrate(string $base, bool $apply): array {
        $dir = rtrim($base, '/') . '/events';
        $done = [];
        $failed = [];
        $removed = 0;
        if (!is_dir($dir)) return ['done' => $done, 'failed' => $failed, 'removed' => $removed];

        // L'XML si rigenera con lo stesso convertitore del salvataggio: se non è
        // raggiungibile si converte comunque il JSON e lo si dichiara nel rapporto,
        // invece di fermare tutto (l'XML si rifà con un salvataggio dall'editor).
        $conv = __DIR__ . '/../json-xml/functions.php';
        $puoXml = is_file($conv);
        if ($puoXml) require_once $conv;

        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->getFilename() !== 'index.json') continue;
            $path = $file->getPathname();
            $rel = trim(str_replace($dir, '', dirname($path)), '/');
            $raw = (string)@file_get_contents($path);
            $doc = json_decode($raw, true);
            if (!is_array($doc)) {
                $failed[] = ['path' => $rel, 'why' => 'index.json illeggibile'];
                continue;
            }
            $dove = isset($doc['mainEntity']) && is_array($doc['mainEntity']) ? 'mainEntity' : null;
            $ent = $dove ? $doc['mainEntity'] : $doc;
            if (!isset($ent['keywords'])) continue;

            $prima = $ent['keywords'];
            if (is_array($prima)) {
                // Già un elenco: si tocca solo se contiene doppioni o virgole dentro.
                $pulito = ws_keywords_split($prima);
                if ($pulito === array_values($prima)) continue;
            }
            $dopo = ws_keywords_split($prima);
            $contava = is_array($prima) ? count($prima) : count(array_filter(array_map('trim', explode(',', (string)$prima)), 'strlen'));
            $removed += max(0, $contava - count($dopo));

            $ent['keywords'] = $dopo;
            if ($dove) $doc[$dove] = $ent; else $doc = $ent;

            $xmlOk = false;
            if ($apply) {
                $json = json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                if (@file_put_contents($path, $json) === false) {
                    $failed[] = ['path' => $rel, 'why' => 'scrittura index.json fallita'];
                    continue;
                }
                $xmlPath = dirname($path) . '/index.xml';
                if ($puoXml && is_file($xmlPath)) {
                    try {
                        $xml = jsonToWsx($json);
                        $xmlOk = @file_put_contents($xmlPath, $xml) !== false;
                    } catch (Throwable $e) {
                        $xmlOk = false;
                    }
                }
            } else {
                $xmlOk = $puoXml && is_file(dirname($path) . '/index.xml');
            }

            $done[] = [
                'path' => $rel,
                'before' => is_array($prima) ? count($prima) . ' voci' : 'stringa',
                'after' => $dopo,
                'xml' => $xmlOk,
            ];
        }

        usort($done, fn($a, $b) => strcmp($a['path'], $b['path']));
        return ['done' => $done, 'failed' => $failed, 'removed' => $removed];
    }
}
