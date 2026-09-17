<?php
/**
 * Every content of a root: JSON → XML twin.
 *
 * The module over _refresh-content.php. Walks a content root
 * (`contents/<site>/<locale>`), finds every source - a JSON that is a
 * content (a JSON-LD document: @context and @type), or a .wsx not yet
 * migrated - and makes, or in preview counts, the twins that are stale or
 * missing. Reads the manifest once, saves it once.
 *
 * Skipped on the way: directories switched off with a leading `-`, `_index`
 * and `_trash`, `users/` (the CMS's user record is authored in XML there),
 * media folders. See ws_derived_skip_dir().
 *
 * Registered in the hub's registry (lib/ws-maintenance.php) as `contents`,
 * with preview and apply. A library, not a page.
 *
 * @package WS
 * @subpackage Admin
 */

require_once __DIR__ . '/_refresh-content.php';

if (!function_exists('ws_refresh_contents')) {

    /**
     * Every source under a root, as absolute paths, sorted: every *.json
     * that may be a content, and every *.wsx not yet migrated (see
     * ws_content_wsx_is_source()).
     */
    function ws_content_sources(string $root): array {
        $out = [];
        $walk = function (string $dir) use (&$walk, &$out) {
            $entries = @scandir($dir);
            if (!$entries) return;
            foreach ($entries as $e) {
                if ($e === '.' || $e === '..') continue;
                $p = "$dir/$e";
                if (is_dir($p)) {
                    if (!ws_derived_skip_dir($e)) $walk($p);
                } elseif (substr($e, -5) === '.json' && $e[0] !== '-' && $e[0] !== '.') {
                    $out[] = $p;
                } elseif (substr($e, -4) === '.wsx' && ws_content_wsx_is_source($p)) {
                    $out[] = $p;
                }
            }
        };
        $walk(rtrim($root, '/'));
        sort($out);
        return $out;
    }

    /**
     * @param string $root     the content root
     * @param bool   $apply    false = preview
     * @param array  $options  'adopt_root', 'force' - passed to every unit
     * @return array  rebuilt[], created[], stale[] (preview only), skipped[]
     *                ({path, why}), failed[] ({path, why}), fresh (count),
     *                changes (how many twins would be / were written)
     */
    function ws_refresh_contents(string $root, bool $apply, array $options = []): array {
        $rep = ['rebuilt' => [], 'created' => [], 'stale' => [], 'skipped' => [], 'failed' => [], 'fresh' => 0, 'changes' => 0];
        $root = rtrim($root, '/');
        if (!is_dir($root)) { $rep['failed'][] = ['path' => $root, 'why' => 'no such root']; return $rep; }

        $lock = $apply ? ws_derived_lock($root) : null;
        $manifest = ws_derived_manifest($root);
        $written = false;
        foreach (ws_content_sources($root) as $json) {
            $r = ws_refresh_content($json, $apply, $options, $manifest);
            switch ($r['status']) {
                case 'fresh':   $rep['fresh']++; break;
                case 'stale':   $rep['stale'][] = $r['target']; break;
                case 'rebuilt': $rep['rebuilt'][] = $r['target']; $written = true; break;
                case 'created': $rep['created'][] = $r['target']; $written = true; break;
                case 'skipped':
                    // A JSON that is not a content is silence, not news: only twins that are not twins are worth a line.
                    if (strpos($r['why'], 'no @context') !== 0) $rep['skipped'][] = ['path' => $r['source'], 'why' => $r['why']];
                    break;
                default:        $rep['failed'][] = ['path' => $r['source'], 'why' => $r['why']];
            }
        }
        if ($written && !ws_derived_manifest_save($root, $manifest)) {
            $rep['failed'][] = ['path' => ws_derived_rel($root, ws_derived_manifest_path($root)), 'why' => 'manifest not saved'];
        }
        if ($lock) ws_derived_unlock($lock);
        $rep['changes'] = count($rep['rebuilt']) + count($rep['created']) + count($rep['stale']);
        return $rep;
    }
}
?>
