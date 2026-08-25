<?php
/**
 * La mappa del sito Meetoo, generata dai contenuti.
 *
 * Nel CMS ogni pagina è un `<url>` del sitemap, e da lì escono tre cose: l'indirizzo
 * a cui risponde (`wspath`), che cosa caricare per rispondere (`query`: tema,
 * template, contenuto) e i metadati che finiscono nella testa dell'HTML (`title`,
 * `description`, `robots`). Nei siti scritti a mano quei valori stanno dentro i
 * `.wsx` e il sitemap li pesca con XInclude; i contenuti Meetoo però nascono in
 * JSON dall'editor e di routing non sanno niente — giustamente, perché un evento è
 * un evento, non una pagina.
 *
 * Quindi il ponte si costruisce qui: una regola sola, in un posto solo, che dice
 * quale indirizzo ha ogni tipo di entità. Cambiare gli indirizzi del sito vuol dire
 * cambiare la tabella qui sotto e rigenerare.
 *
 * Gli indirizzi si scrivono nella forma DEFINITIVA, senza il prefisso `/meetoo`:
 * il prefisso è un fatto dell'ospite (vedi WS_MOUNTS e ws_href), non del contenuto,
 * e il giorno del dominio proprio non ci sarà niente da riscrivere.
 */

if (!function_exists('ws_mappa_wspath')) {

    /** Nome della cartella-foglia di un @id: `events/20261017T1000-…` → `20261017T1000-…`. */
    function ws_mappa_slug(string $rel): string {
        $p = explode('/', trim($rel, '/'));
        return end($p) ?: '';
    }

    /**
     * L'indirizzo e il template di un'entità, dal suo percorso e dal suo tipo.
     * Ritorna [wspath, template, tipo] oppure null per ciò che pagina non è.
     */
    function ws_mappa_wspath(string $rel, array $tipi): ?array {
        $slug = ws_mappa_slug($rel);
        $lista = in_array('ItemList', $tipi, true);
        $serie = in_array('EventSeries', $tipi, true);

        if (strpos($rel, 'events/') === 0) {
            // Una collezione di eventi e un evento singolo vivono nello stesso posto:
            // per chi legge sono la stessa cosa, un appuntamento che si ripete o no.
            return ['/eventi/' . $slug, $serie ? 'collection' : 'event', $serie ? 'EventSeries' : 'Event'];
        }
        if (strpos($rel, 'places/') === 0) {
            // Le liste curate (Lungomare, BookCrossing) sono percorsi, non luoghi:
            // stanno alla radice perché è così che le si nomina e le si condivide.
            if ($lista) return ['/' . $slug, 'collection', 'ItemList'];
            return ['/luoghi/' . $slug, 'place', 'Place'];
        }
        if (strpos($rel, 'organizations/') === 0) {
            return ['/organizzatori/' . $slug, 'organizer', 'Organization'];
        }
        return null; // users, persons, brand, _index, _trash: non sono pagine
    }

    /** Prima frase utile di una descrizione XHTML, per il meta description. */
    function ws_mappa_descrizione($testo, int $max = 200): string {
        $t = trim(html_entity_decode(strip_tags((string)$testo), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $t = preg_replace('/\s+/u', ' ', $t);
        if (mb_strlen($t) <= $max) return $t;
        $tagliato = mb_substr($t, 0, $max);
        $spazio = mb_strrpos($tagliato, ' ');
        return rtrim($spazio ? mb_substr($tagliato, 0, $spazio) : $tagliato, " ,;:.") . '…';
    }

    /** Tutte le entità con un index.json, come percorsi relativi al locale. */
    function ws_mappa_entita(string $localeDir): array {
        $out = [];
        foreach (['events/*', 'places/*/*', 'organizations/*'] as $g) {
            foreach (glob("$localeDir/$g/index.json") as $f) {
                $out[] = trim(str_replace($localeDir, '', dirname($f)), '/');
            }
        }
        sort($out);
        return $out;
    }

    /**
     * Costruisce la mappa. In anteprima non scrive: ritorna che cosa scriverebbe.
     * Ritorna ['changes'=>int, 'voci'=>[[wspath, rel, template]], 'problemi'=>[]].
     */
    function ws_mappa_costruisci(string $contentRoot, string $sito, string $locale = 'it_IT', bool $apply = false): array {
        $localeDir = rtrim($contentRoot, '/') . '/' . $locale;
        $voci = [];
        $problemi = [];
        $presi = [];

        foreach (ws_mappa_entita($localeDir) as $rel) {
            $raw = (string)@file_get_contents("$localeDir/$rel/index.json");
            $doc = json_decode($raw, true);
            if (!is_array($doc)) { $problemi[] = "$rel: index.json illeggibile"; continue; }
            $e = (isset($doc['mainEntity']) && is_array($doc['mainEntity'])) ? $doc['mainEntity'] : $doc;
            $tipi = array_map('strval', (array)($e['@type'] ?? []));

            $regola = ws_mappa_wspath($rel, $tipi);
            if ($regola === null) continue;
            list($wspath, $template, $tipo) = $regola;

            // Due entità che chiedono lo stesso indirizzo: la seconda si qualifica
            // con il segmento che le distingue (per i luoghi, il CAP). Meglio un
            // indirizzo più lungo che due pagine che si coprono a vicenda.
            if (isset($presi[$wspath])) {
                $pezzi = explode('/', $rel);
                $qualifica = count($pezzi) > 2 ? $pezzi[1] : substr(md5($rel), 0, 6);
                $wspath = preg_replace('#/([^/]+)$#', '/' . strtolower($qualifica) . '/$1', $wspath);
                if (isset($presi[$wspath])) { $problemi[] = "$rel: indirizzo già occupato ($wspath)"; continue; }
            }
            $presi[$wspath] = $rel;

            $voci[] = [
                'wspath' => $wspath,
                'rel' => $rel,
                'template' => $template,
                'tipo' => $tipo,
                'title' => trim((string)($e['name'] ?? ws_mappa_slug($rel))),
                'description' => ws_mappa_descrizione($e['description'] ?? ''),
                'dateModified' => (string)($doc['dateModified'] ?? $e['dateModified'] ?? date('c')),
            ];
        }

        $xml = ws_mappa_xml($voci, $sito, $locale);
        $file = rtrim($contentRoot, '/') . '/ws_sitemap.wsx';
        $scritto = false;
        if ($apply) {
            $scritto = @file_put_contents($file, $xml) !== false;
            if (!$scritto) $problemi[] = 'ws_sitemap.wsx: scrittura fallita';
        }
        return ['changes' => count($voci), 'voci' => $voci, 'problemi' => $problemi, 'file' => $file, 'scritto' => $scritto];
    }

    /** Le voci, nella forma che il CMS si aspetta. */
    function ws_mappa_xml(array $voci, string $sito, string $locale): string {
        $lang = str_replace('_', '-', $locale);
        $out = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $out .= "<!-- Generata da ws-admin (Rigenera la mappa del sito). Non si scrive a mano: si rigenera. -->\n";
        $out .= "<urlset xml:lang=\"$lang\" xmlns:xi=\"http://www.w3.org/2001/XInclude\">\n";
        foreach ($voci as $v) {
            $q = "/?theme=$sito&amp;template={$v['template']}&amp;content=$sito/$locale/{$v['rel']}";
            $out .= "\t<url>\n";
            $out .= "\t\t<wspath>{$v['wspath']}</wspath>\n";
            $out .= "\t\t<query>$q</query>\n";
            $out .= "\t\t<inLanguage>$lang</inLanguage>\n";
            $out .= "\t\t<type>{$v['tipo']}</type>\n";
            $out .= "\t\t<title>" . ws_mappa_esc($v['title']) . "</title>\n";
            $out .= "\t\t<description>" . ws_mappa_esc($v['description']) . "</description>\n";
            $out .= "\t\t<changefreq>weekly</changefreq>\n";
            $out .= "\t\t<priority>0.6</priority>\n";
            $out .= "\t\t<robots>index, follow</robots>\n";
            $out .= "\t\t<dateModified>" . ws_mappa_esc($v['dateModified']) . "</dateModified>\n";
            $out .= "\t</url>\n";
        }
        $out .= "</urlset>\n";
        return $out;
    }

    function ws_mappa_esc(string $s): string {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Il sitemap del sito dev'essere incluso in quello generale, altrimenti esiste
     * ma non lo cerca nessuno. Ritorna true se c'era già o se è stato aggiunto.
     */
    function ws_mappa_innesta(string $contentsDir, string $sito, bool $apply): array {
        $file = rtrim($contentsDir, '/') . '/ws_sitemap.wsx';
        if (!is_file($file)) return ['ok' => false, 'why' => 'ws_sitemap.wsx generale non trovato'];
        $raw = (string)file_get_contents($file);
        $riga = "  <xi:include href=\"$sito/ws_sitemap.wsx\" xpointer=\"xpointer(/*[1]/*)\"/>";
        if (strpos($raw, "$sito/ws_sitemap.wsx") !== false) return ['ok' => true, 'why' => 'già incluso'];
        if (!$apply) return ['ok' => true, 'why' => 'da includere'];
        $nuovo = preg_replace('#(\n\s*</urlset>)#', "\n$riga$1", $raw, 1);
        return @file_put_contents($file, $nuovo) !== false
            ? ['ok' => true, 'why' => 'incluso']
            : ['ok' => false, 'why' => 'scrittura fallita'];
    }
}
