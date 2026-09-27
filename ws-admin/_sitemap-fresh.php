<?php
/**
 * The site maps, kept fresh by the requests themselves.
 *
 * A page's XML twin is remade by the request that asks for the page
 * (ws_content_relpath()). The site map could not be: every request needs it
 * before it knows which page it is for - the map is what tells it - and so a
 * page uploaded by FTP was a 404 until someone ran "Rigenera le mappe", and a
 * page whose title or status changed kept the old one in every list.
 *
 * So query.php asks here first, before it loads the map. For each root with a
 * map of its own (`<site>/<locale>/ws_sitemap.wsx`), the question is cheap:
 * is any page file (index.json, index.wsx) or any folder - a page added or
 * removed changes its folder - newer than the map? Only when something is,
 * the map's builder is loaded, and it decides by hashes (refresh-sitemaps.php):
 * a folder that changed for another reason (a twin written, an image added)
 * costs one comparison and no rebuild. The map is then touched, so the same
 * change is not looked at again.
 *
 * Meetoo's map is the site's own (`meetoo/ws_sitemap.wsx`, no locale map) and
 * is kept fresh by its theme (meetoo_derivati_freschi()): it is not a root here.
 *
 * Two guards. A non-blocking lock of its own: of two requests arriving
 * together, one rebuilds and the other goes on with the map as it is. And no
 * file is newer than now: one uploaded with a date in the future would
 * otherwise make every request rebuild.
 *
 * @package WS
 * @subpackage Admin
 */

if (!function_exists('ws_sitemaps_ensure')) {

    /** Rebuild, now, the maps of the roots where a page changed since. */
    function ws_sitemaps_ensure(string $contents): void {
        foreach (glob(rtrim($contents, '/') . '/*/*/ws_sitemap.wsx') ?: [] as $map) {
            $root = dirname($map);
            $site = basename(dirname($root));
            if (!preg_match('/^[a-z]{2}_[A-Z]{2}$/', basename($root)) || strpbrk($site[0], '-_.') !== false) continue;
            if (ws_sitemap_newest($root) <= (int)@filemtime($map)) continue;
            ws_sitemap_rebuild_now($root, $map);
        }
    }

    /** The newest date among what the map is made from, and the code that makes it. */
    function ws_sitemap_newest(string $root): int {
        $newest = 0;
        foreach (['_refresh-sitemap.php', 'refresh-sitemaps.php'] as $code) {
            $newest = max($newest, (int)@filemtime(__DIR__ . '/' . $code));
        }
        // The folders the map's builder skips (ws_derived_skip_dir()), skipped here too.
        $skip = ['_index', '_trash', 'users', 'media', 'media-sources', 'node_modules'];
        $walk = function (string $dir) use (&$walk, &$newest, $skip) {
            $newest = max($newest, (int)@filemtime($dir));
            foreach (@scandir($dir) ?: [] as $e) {
                if ($e === '' || $e[0] === '.' || $e[0] === '-') continue;
                $p = "$dir/$e";
                if ($e === 'index.json' || $e === 'index.wsx') $newest = max($newest, (int)@filemtime($p));
                elseif (!in_array($e, $skip, true) && is_dir($p)) $walk($p);
            }
        };
        $walk(rtrim($root, '/'));
        return min($newest, time());
    }

    function ws_sitemap_rebuild_now(string $root, string $map): void {
        @mkdir($root . '/_index', 0775, true);
        $key = @fopen($root . '/_index/sitemap-fresh.lock', 'c');
        if (!$key || !flock($key, LOCK_EX | LOCK_NB)) return;
        try {
            require_once __DIR__ . '/refresh-sitemaps.php';
            $r = ws_refresh_sitemaps($root, true);
            // Touched only when the map is right: a failed rebuild is tried again by the next request.
            if (in_array($r['map']['status'] ?? '', ['fresh', 'rebuilt', 'created', 'skipped'], true)) {
                @touch($map);
                clearstatcache();
            }
        } catch (Throwable $e) {
            // The map as it is still routes; the next request tries again.
        } finally {
            flock($key, LOCK_UN);
            fclose($key);
        }
    }
}
