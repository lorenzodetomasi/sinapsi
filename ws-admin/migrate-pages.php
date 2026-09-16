<?php
/**
 * Every hand-written page of a root: index.wsx → index.json, with its twin.
 *
 * The module over _migrate-page.php. Finds every index.wsx under a content
 * root that is still the page's source (no index.json beside it, not set
 * aside with a leading `-`), works out which pages have pages under them
 * (they become CollectionPages), and migrates them - or, in preview, shows
 * what each would become and what a reader should check.
 *
 * Registered in the hub's registry as `migrate`, with preview and apply.
 * The preview is also the way to read the JSON before it is written: the
 * report carries it, page by page.
 *
 * @package WS
 * @subpackage Admin
 */

require_once __DIR__ . '/_migrate-page.php';
require_once __DIR__ . '/refresh-contents.php';

if (!function_exists('ws_migrate_pages')) {

    /** The pages still written by hand: every index.wsx with a wspath and no index.json. */
    function ws_migrate_sources(string $root): array {
        $out = [];
        $walk = function (string $dir) use (&$walk, &$out) {
            $entries = @scandir($dir);
            if (!$entries) return;
            foreach ($entries as $e) {
                if ($e === '.' || $e === '..') continue;
                $p = "$dir/$e";
                if (is_dir($p)) { if (!ws_derived_skip_dir($e)) $walk($p); }
                elseif ($e === 'index.wsx' && !is_file("$dir/index.json")) $out[] = $p;
            }
        };
        $walk(rtrim($root, '/'));
        sort($out);
        return $out;
    }

    /** wspath and parent of a page, read cheaply from its source. */
    function ws_migrate_paths(string $wsx_abspath): array {
        $prev = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $ok = $dom->load($wsx_abspath);
        libxml_clear_errors(); libxml_use_internal_errors($prev);
        if (!$ok) return ['', ''];
        $x = new DOMXPath($dom);
        $norm = fn($p) => '/' . trim((string)$p, "/ \t\n\r");
        return [$norm($x->evaluate('string(/*/wspath)')), $norm($x->evaluate('string(/*/parent/wspath)'))];
    }

    /**
     * @param string $root     the content root
     * @param bool   $apply    false = preview
     * @param array  $options  'only' => [relative dirs] to restrict the run;
     *                         'brand' => provider for Service pages
     * @return array  migrated[], would[] ({path, type, notes, json}), skipped[]
     *                ({path, why}), failed[] ({path, why}), changes
     */
    function ws_migrate_pages(string $root, bool $apply, array $options = []): array {
        $rep = ['migrated' => [], 'would' => [], 'skipped' => [], 'failed' => [], 'changes' => 0];
        $root = rtrim($root, '/');
        if (!is_dir($root)) { $rep['failed'][] = ['path' => $root, 'why' => 'no such root']; return $rep; }

        $sources = ws_migrate_sources($root);
        // Who has children: every parent named by any page, migrated or not.
        $parents = [];
        foreach ($sources as $wsx) { [, $parent] = ws_migrate_paths($wsx); $parents[$parent] = true; }
        foreach (ws_content_sources($root) as $j) {
            $d = json_decode((string)@file_get_contents($j), true);
            if (is_array($d) && !empty($d['parent']['wspath'])) $parents['/' . trim((string)$d['parent']['wspath'], '/')] = true;
        }

        $only = isset($options['only']) ? array_map(fn($p) => trim($p, '/'), (array)$options['only']) : null;
        $lock = $apply ? ws_derived_lock($root) : null;
        foreach ($sources as $wsx) {
            $rel = ws_derived_rel($root, dirname($wsx));
            if ($only !== null && !in_array($rel, $only, true)) continue;
            [$wspath] = ws_migrate_paths($wsx);
            $r = ws_migrate_page($wsx, $apply, [
                'collection' => isset($parents[$wspath]),
                'brand' => $options['brand'] ?? ['name' => 'ISOTYPE.ORG', 'url' => 'https://www.isotype.org/'],
            ]);
            switch ($r['status']) {
                case 'migrated': $rep['migrated'][] = ['path' => $rel, 'type' => implode(' › ', (array)$r['json']['@type']), 'notes' => $r['notes']]; break;
                case 'would':    $rep['would'][]    = ['path' => $rel, 'type' => implode(' › ', (array)$r['json']['@type']), 'notes' => $r['notes'], 'json' => $r['json']]; break;
                case 'skipped':  $rep['skipped'][]  = ['path' => $rel, 'why' => $r['why']]; break;
                default:         $rep['failed'][]   = ['path' => $rel, 'why' => $r['why']];
            }
        }
        if ($lock) ws_derived_unlock($lock);
        $rep['changes'] = count($rep['migrated']) + count($rep['would']);
        return $rep;
    }
}
?>
