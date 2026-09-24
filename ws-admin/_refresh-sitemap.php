<?php
/**
 * One root's site map, derived from its pages.
 *
 * The unit behind refresh-sitemaps.php. A library, not a page.
 *
 * `<root>/ws_sitemap.wsx` is what the CMS routes with: one <url> per page,
 * with the fields the routing, the menus and the search engines read
 * (wspath, query, inLanguage, type, title, description, keywords, name,
 * parent, dateModified, changefreq, priority, robots). It used to be written
 * by hand, entry by entry, each field an XInclude into the page's XML; now
 * a page is on the map because its index.json exists and says where it
 * stands, and the map is rebuilt when any page changes.
 *
 * During the migration a page may still be a hand-written index.wsx: it is
 * mapped too, from its fields, so the map is whole at every step. Its
 * source is the .wsx; the map is stale when that changes.
 *
 * A sub-map (`<sub>/ws_sitemap.wsx`) in a folder that holds no page of its
 * own (the admin's, whose entries are not contents) is included as a
 * fragment. A sub-map beside pages is the old way of listing them and is
 * reported as such: the map already lists those pages itself.
 *
 * The map's source, for the manifest, is the whole set of pages: its hash
 * is the hash of every page's hash, in path order.
 *
 * @package WS
 * @subpackage Admin
 */

require_once __DIR__ . '/lib/derived.php';
require_once __DIR__ . '/refresh-contents.php';

if (!defined('WS_SITEMAP_VERSION')) {
    define('WS_SITEMAP_VERSION', 'sitemap 2026.09.16');
}

if (!function_exists('ws_refresh_sitemap')) {

    /** The fields a map entry carries, in the order the old maps had them. */
    function ws_sitemap_fields(): array {
        return ['wspath', 'query', 'inLanguage', 'type', 'title', 'description', 'keywords', 'name', 'parent', 'dateModified', 'changefreq', 'priority', 'robots'];
    }

    /**
     * The pages of a root, as ['abspath' => source file, 'kind' => json|wsx,
     * 'dir' => the page's directory], plus the sub-maps found on the way.
     */
    function ws_sitemap_sources(string $root): array {
        $pages = []; $submaps = [];
        $walk = function (string $dir, bool $top) use (&$walk, &$pages, &$submaps, $root) {
            $entries = @scandir($dir);
            if (!$entries) return;
            $hasPage = false;
            foreach ($entries as $e) {
                if ($e === '.' || $e === '..') continue;
                $p = "$dir/$e";
                if (is_dir($p)) { if (!ws_derived_skip_dir($e)) $walk($p, false); continue; }
                if ($e === 'index.json') {
                    $d = json_decode((string)@file_get_contents($p), true);
                    if (is_array($d) && !empty($d['@context']) && trim((string)($d['wspath'] ?? '')) !== '') {
                        $pages[$dir] = ['abspath' => $p, 'kind' => 'json', 'dir' => $dir]; $hasPage = true;
                    }
                } elseif ($e === 'index.wsx' && !isset($pages[$dir]) && !is_file("$dir/index.json")) {
                    $pages[$dir] = ['abspath' => $p, 'kind' => 'wsx', 'dir' => $dir]; $hasPage = true;
                } elseif ($e === 'ws_sitemap.wsx' && !$top) {
                    $submaps[$dir] = $p;
                }
            }
            if (isset($submaps[$dir])) $submaps[$dir] = ['abspath' => $submaps[$dir], 'beside_pages' => $hasPage];
        };
        $walk(rtrim($root, '/'), true);
        ksort($pages);
        ksort($submaps);
        return ['pages' => array_values($pages), 'submaps' => $submaps];
    }

    /**
     * The map's `type` of a page, from its JSON-LD. Three answers, in order:
     *
     * 1. the page's own `additionalType` - the ROLE it plays in this CMS, for
     *    the roles schema.org has no word for (Index, PrivacyPage, CookiesPage,
     *    DisclaimerPage). It wins because it is the thing the CMS looks up
     *    (`url[type = "PrivacyPage"]`), and because otherwise it would be lost
     *    exactly where it matters: a home page that says what it is about gets
     *    its type from the mainEntity, so the moment a site declared its
     *    business the home stopped being an Index and became a RealEstateAgent.
     * 2. what the page is ABOUT (the mainEntity's most specific type);
     * 3. what the page IS (its own most specific type).
     *
     * Any vocabulary prefix is dropped, so a lookup by role reads as it always
     * did; a full URL is reduced to its fragment, which is the same name.
     */
    function ws_sitemap_type(array $d): string {
        $of = function ($t): string {
            $t = is_array($t) ? (string)end($t) : (string)$t;
            if (strpos($t, '#') !== false) $t = substr($t, strrpos($t, '#') + 1);
            return preg_replace('/^[A-Za-z_][\w.-]*:/', '', $t);
        };
        if (!empty($d['additionalType'])) return $of($d['additionalType']);
        if (!empty($d['mainEntity']['@type'])) return $of($d['mainEntity']['@type']);
        return !empty($d['@type']) ? $of($d['@type']) : 'WebPage';
    }

    /** A map entry from a page's JSON: the fields, as strings; null when the page has no wspath. */
    function ws_sitemap_entry_from_json(array $d): ?array {
        $wspath = trim((string)($d['wspath'] ?? ''));
        if ($wspath === '') return null;
        $e = [];
        foreach (ws_sitemap_fields() as $f) {
            if ($f === 'parent') {
                $p = trim((string)($d['parent']['wspath'] ?? ''));
                if ($p !== '') $e['parent'] = $p;
                continue;
            }
            if ($f === 'type') { $e['type'] = ws_sitemap_type($d); continue; }
            $v = $d[$f] ?? null;
            if (is_array($v)) $v = $v['#text'] ?? '';
            $v = trim(strip_tags((string)$v));
            if ($v !== '') $e[$f] = preg_replace('/\s+/u', ' ', $v);
        }
        return $e;
    }

    /** A map entry from a hand-written page: its fields, includes resolved. */
    function ws_sitemap_entry_from_wsx(string $abspath): ?array {
        $prev = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $ok = $dom->load($abspath);
        if ($ok) $dom->xinclude();
        libxml_clear_errors(); libxml_use_internal_errors($prev);
        if (!$ok) return null;
        $x = new DOMXPath($dom);
        $wspath = trim($x->evaluate('string(/*/wspath)'));
        if ($wspath === '') return null;
        $e = [];
        foreach (ws_sitemap_fields() as $f) {
            $v = trim($f === 'parent' ? $x->evaluate('string(/*/parent/wspath)') : $x->evaluate("string(/*/$f)"));
            if ($v !== '') $e[$f] = preg_replace('/\s+/u', ' ', $v);
        }
        return $e;
    }

    /** Tree order: a page before the pages under it, siblings by path. */
    function ws_sitemap_sort(array &$entries): void {
        usort($entries, function ($a, $b) {
            $pa = trim($a['wspath'], '/'); $pb = trim($b['wspath'], '/');
            if ($pa === $pb) return 0;
            if ($pa === '') return -1;
            if ($pb === '') return 1;
            return strcmp($pa, $pb);
        });
    }

    /** The map as XML text. */
    function ws_sitemap_xml(array $entries, array $fragments, string $lang): string {
        $esc = fn($s) => htmlspecialchars((string)$s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $out = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $out .= "<!-- Derived from the pages' index.json by ws-admin/refresh-sitemaps.php. Do not edit: edit the pages. -->\n";
        $out .= "<urlset xml:lang=\"" . $esc($lang) . "\" xmlns:xi=\"http://www.w3.org/2001/XInclude\">\n";
        foreach ($entries as $e) {
            $out .= "\t<url>\n";
            foreach (ws_sitemap_fields() as $f) {
                if (!isset($e[$f])) continue;
                if ($f === 'parent') {
                    $out .= "\t\t<parent>\n\t\t\t<wspath>" . $esc($e['parent']) . "</wspath>\n\t\t</parent>\n";
                } else {
                    $out .= "\t\t<$f>" . $esc($e[$f]) . "</$f>\n";
                }
            }
            $out .= "\t</url>\n";
        }
        foreach ($fragments as $href) {
            $out .= "\t<xi:include href=\"" . $esc($href) . "\" xpointer=\"xpointer(/*[1]/*)\"/>\n";
        }
        $out .= "</urlset>\n";
        return $out;
    }

    /**
     * Makes (or says it would make) the map of a root.
     *
     * @return array  status: fresh | stale | rebuilt | created | skipped | failed;
     *                pages (count), json (count), wsx (count), legacy_maps[],
     *                fragments[], problems[], target, why
     */
    function ws_refresh_sitemap(string $root, bool $apply = true, array $options = [], ?array &$manifest = null): array {
        $root = rtrim($root, '/');
        $target_abs = "$root/ws_sitemap.wsx";
        $out = ['status' => 'failed', 'target' => 'ws_sitemap.wsx', 'pages' => 0, 'json' => 0, 'wsx' => 0,
                'legacy_maps' => [], 'fragments' => [], 'problems' => [], 'why' => ''];
        if (!is_dir($root)) { $out['why'] = 'no such root'; return $out; }

        $src = ws_sitemap_sources($root);
        if (!$src['pages']) { $out['status'] = 'skipped'; $out['why'] = 'no page with a wspath here: nothing to map'; return $out; }

        // The source hash: every page's hash, in order.
        $hashes = [];
        foreach ($src['pages'] as $p) $hashes[] = ws_derived_rel($root, $p['abspath']) . ':' . ws_derived_hash($p['abspath']);
        foreach ($src['submaps'] as $m) if (!$m['beside_pages']) $hashes[] = ws_derived_rel($root, $m['abspath']) . ':' . ws_derived_hash($m['abspath']);
        $hash = sha1(implode("\n", $hashes));

        $own_manifest = ($manifest === null);
        if ($own_manifest) $manifest = ws_derived_manifest($root);
        if (empty($options['force']) && !ws_derived_is_stale($manifest, 'ws_sitemap.wsx', $target_abs, $hash, WS_SITEMAP_VERSION)) {
            $out['status'] = 'fresh'; return $out;
        }

        $entries = [];
        foreach ($src['pages'] as $p) {
            $rel = ws_derived_rel($root, $p['dir']);
            if ($p['kind'] === 'json') {
                $d = json_decode((string)@file_get_contents($p['abspath']), true);
                $e = is_array($d) ? ws_sitemap_entry_from_json($d) : null;
                $out['json']++;
            } else {
                $e = ws_sitemap_entry_from_wsx($p['abspath']);
                $out['wsx']++;
            }
            if ($e === null) { $out['problems'][] = "$rel: no wspath, left off the map"; continue; }
            if (empty($e['query'])) $out['problems'][] = "$rel: no query - the CMS cannot route it";
            $entries[] = $e;
        }
        ws_sitemap_sort($entries);
        $seen = [];
        foreach ($entries as $e) {
            $k = rtrim($e['wspath'], '/') ?: '/';
            if (isset($seen[$k])) $out['problems'][] = "{$e['wspath']}: declared twice";
            $seen[$k] = true;
        }
        $out['pages'] = count($entries);

        foreach ($src['submaps'] as $dir => $m) {
            $rel = ws_derived_rel($root, $m['abspath']);
            if ($m['beside_pages']) $out['legacy_maps'][] = $rel;
            else $out['fragments'][] = $rel;
        }

        $exists = is_file($target_abs);
        if (!$apply) { $out['status'] = 'stale'; return $out; }

        $locale = basename($root);
        $lang = preg_match('/^[a-z]{2}_[A-Z]{2}$/', $locale) ? str_replace('_', '-', $locale) : 'it-IT';
        $xml = ws_sitemap_xml($entries, $out['fragments'], $lang);
        if (@file_put_contents($target_abs, $xml, LOCK_EX) === false) { $out['why'] = 'cannot write ws_sitemap.wsx'; return $out; }
        $manifest['ws_sitemap.wsx'] = ws_derived_entry('(every page)', $hash, WS_SITEMAP_VERSION);
        if ($own_manifest && !ws_derived_manifest_save($root, $manifest)) {
            $out['why'] = 'map written, manifest not saved';
        }
        $out['status'] = $exists ? 'rebuilt' : 'created';
        return $out;
    }
}

/*
 * IL TELAIO DI UN SITO: `<sito>/ws_sitemap.wsx`, che compone le mappe delle sue
 * lingue.
 *
 * Non era di nessuno. Non lo generava niente e niente lo proteggeva, e quando
 * un'operazione pensata per un sito a lingua sola lo ha riscritto piatto,
 * isotype ha perso ogni pagina tranne la home: gli indirizzi rimasti senza
 * risposta sono caduti su un altro sito che li aveva uguali, e per giorni le
 * pagine di your-website hanno risposto a nome di isotype. Un file che nessuno
 * genera è un file che, perso, resta perso.
 *
 * Adesso è derivato come tutto il resto: si ricostruisce dalle lingue che
 * esistono, e se sparisce torna.
 *
 * QUANDO NON SI TOCCA. Un sito a lingua sola può avere la mappa piatta nel sito
 * stesso — è l'arrangiamento di Meetoo, dove `contents/meetoo/ws_sitemap.wsx`
 * porta le 92 voci e `it_IT/` non ha una mappa sua. Lì un telaio che include
 * mappe che non esistono cancellerebbe le uniche che ci sono. Il segnale non è
 * quante lingue ci sono ma se le lingue hanno una mappa propria: se nessuna ce
 * l'ha, questo file non è un telaio e non lo si riscrive.
 */
if (!function_exists('ws_refresh_site_frame')) {

    function ws_refresh_site_frame(string $site_root, bool $apply): array {
        $site_root = rtrim($site_root, '/');
        $out = ['status' => 'skipped', 'why' => '', 'locales' => []];
        if (!is_dir($site_root)) { $out['why'] = 'no such site'; return $out; }

        $locales = ws_sitemap_frame_locales($site_root);
        if (!$locales) {
            /* Nessuna lingua con una mappa propria: o il sito non ha lingue, o
             * le sue voci stanno qui dentro (Meetoo). In entrambi i casi non è
             * un telaio. */
            $out['why'] = 'no locale has a map of its own';
            return $out;
        }
        $out['locales'] = $locales;

        $target = $site_root . '/ws_sitemap.wsx';
        $wanted = ws_sitemap_frame_xml($locales);
        $current = is_file($target) ? (string)@file_get_contents($target) : '';

        if ($current === $wanted) { $out['status'] = 'fresh'; return $out; }

        /* Che cosa si sta sostituendo, detto nel rapporto: un telaio diverso è
         * un riordino, una mappa piatta è un file che qualcuno aveva
         * schiacciato e che torna al suo posto. */
        $out['why'] = $current === '' ? 'missing'
            : (strpos($current, '<url>') !== false ? 'was a flat map, not a frame' : 'includes changed');

        if (!$apply) { $out['status'] = 'stale'; return $out; }

        if (@file_put_contents($target, $wanted, LOCK_EX) === false) {
            $out['status'] = 'failed'; $out['why'] = 'cannot write ws_sitemap.wsx'; return $out;
        }
        $out['status'] = $current === '' ? 'created' : 'rebuilt';
        return $out;
    }

    /*
     * Le lingue del sito, nell'ordine in cui vanno incluse.
     *
     * L'ordine conta: il CMS prende la PRIMA voce che risponde a un indirizzo,
     * e la lingua primaria è quella che risponde alla radice. Lo dice
     * `ws_languages.wsx`, che è il posto dove il sito dichiara le sue lingue e
     * quale sta a `/`; in mancanza si va in ordine alfabetico, che almeno è
     * stabile.
     *
     * Entrano solo le lingue che una mappa ce l'hanno davvero: includere un
     * file che non c'è fa fallire la risoluzione dell'intero telaio, e con essa
     * tutte le altre lingue.
     */
    function ws_sitemap_frame_locales(string $site_root): array {
        $con_mappa = [];
        foreach (glob($site_root . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $name = basename($dir);
            if (!preg_match('/^[a-z]{2}_[A-Z]{2}$/', $name)) continue;
            if (is_file($dir . '/ws_sitemap.wsx')) $con_mappa[] = $name;
        }
        if (!$con_mappa) return [];
        sort($con_mappa);

        $dichiarate = ws_sitemap_declared_locales($site_root);
        $ordinate = [];
        foreach ($dichiarate as $l) {
            if (in_array($l, $con_mappa, true)) $ordinate[] = $l;
        }
        /* Una lingua che ha una mappa ma che `ws_languages.wsx` non nomina non
         * si perde: va in fondo. Un file dimenticato non deve togliere pagine
         * dal sito. */
        foreach ($con_mappa as $l) {
            if (!in_array($l, $ordinate, true)) $ordinate[] = $l;
        }
        return $ordinate;
    }

    /* Le lingue come le dichiara il sito, la primaria per prima. Si legge senza
     * XML: il file è pieno di XInclude che solo il CMS risolve, e qui servono
     * due elementi per voce. */
    function ws_sitemap_declared_locales(string $site_root): array {
        $file = $site_root . '/ws_languages.wsx';
        if (!is_file($file)) return [];
        $raw = (string)@file_get_contents($file);
        if (!preg_match_all('#<item>(.*?)</item>#s', $raw, $m)) return [];

        $prima = [];
        $dopo = [];
        foreach ($m[1] as $item) {
            if (!preg_match('#<locale>\s*([a-z]{2}_[A-Z]{2})\s*</locale>#', $item, $l)) continue;
            $wspath = preg_match('#<wspath>\s*([^<]*)</wspath>#', $item, $w) ? trim($w[1]) : '';
            if ($wspath === '/') $prima[] = $l[1]; else $dopo[] = $l[1];
        }
        return array_merge($prima, $dopo);
    }

    function ws_sitemap_frame_xml(array $locales): string {
        $righe = '';
        foreach ($locales as $l) {
            $righe .= "\t<xi:include href=\"$l/ws_sitemap.wsx\" xpointer=\"xpointer(/*[1]/*)\"/>\n";
        }
        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
             . "<!-- Derived from the site's language folders by ws-admin/_refresh-sitemap.php."
             . " Do not edit: it comes back. -->\n"
             . "<urlset xmlns:xi=\"http://www.w3.org/2001/XInclude\">\n"
             . $righe
             . "</urlset>\n";
    }
}

?>
