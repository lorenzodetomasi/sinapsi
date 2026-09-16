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
     * The map's `type` of a page, from its JSON-LD: what the page is ABOUT
     * when it is about something (mainEntity's most specific type), else
     * what it IS (its own most specific type) - without the vocabulary
     * prefix, so that a lookup by role (`url[type = "PrivacyPage"]`) reads
     * as it always did. This is the one place `type` is computed.
     */
    function ws_sitemap_type(array $d): string {
        $of = function ($t): string {
            $t = is_array($t) ? (string)end($t) : (string)$t;
            return preg_replace('/^[A-Za-z_][\w.-]*:/', '', $t);
        };
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
?>
