<?php
/**
 * The maps: a root's site map from its pages, then the public sitemap.xml
 * for the search engines from the maps of every site.
 *
 * The module over _refresh-sitemap.php. Registered in the hub's registry as
 * `sitemaps`, with preview and apply. A library, not a page.
 *
 * The public sitemap is one for the whole installation (contents/sitemap.xml)
 * and comes from the general map, so it is redone whenever a site's map is:
 * the builder is Meetoo's ws_mappa_sitemap_pubblico(), which already knows
 * the mounts (a site nested under a prefix gets it there, not in its pages).
 *
 * @package WS
 * @subpackage Admin
 */

require_once __DIR__ . '/_refresh-sitemap.php';

if (!function_exists('ws_refresh_sitemaps')) {

    /**
     * @param string $root     the content root
     * @param bool   $apply    false = preview
     * @param array  $options  'force' => rebuild even when fresh
     * @return array  map (the unit's report), public (['ok','why','urls']),
     *                changes (0 or 1: the map is one thing)
     */
    function ws_refresh_sitemaps(string $root, bool $apply, array $options = []): array {
        $root = rtrim($root, '/');
        $rep = ['map' => null, 'frame' => null, 'public' => null, 'changes' => 0];

        $lock = $apply ? ws_derived_lock($root) : null;
        $rep['map'] = ws_refresh_sitemap($root, $apply, $options);
        if ($lock) ws_derived_unlock($lock);
        if (in_array($rep['map']['status'], ['stale', 'rebuilt', 'created'], true)) $rep['changes'] = 1;

        /*
         * Poi il TELAIO del sito, che compone le mappe delle sue lingue.
         *
         * Sta qui, in mezzo, perché è l'anello che mancava: la mappa della
         * lingua non serve a niente se il sito non la include, ed è esattamente
         * quello che è successo a isotype — mappe di lingua perfette, telaio
         * schiacciato, e ogni pagina tranne la home caduta su un altro sito.
         * Rifarlo a ogni passata costa la lettura di una cartella e toglie di
         * mezzo un file che nessuno possedeva.
         */
        if (preg_match('/\/[a-z]{2}_[A-Z]{2}$/', $root)) {
            $rep['frame'] = ws_refresh_site_frame(dirname($root), $apply);
            if (in_array($rep['frame']['status'], ['stale', 'rebuilt', 'created'], true)) $rep['changes']++;
        }

        // The public sitemap follows the general map, which includes this one.
        if ($rep['map']['status'] !== 'skipped' && $rep['map']['status'] !== 'failed') {
            $contents = dirname(preg_match('/\/[a-z]{2}_[A-Z]{2}$/', $root) ? dirname($root) : $root);
            $lib = __DIR__ . '/lib/ws-mappa.php';
            if (is_file($lib)) {
                require_once $lib;
                $mounts = defined('WS_MOUNTS') && is_array(WS_MOUNTS) ? WS_MOUNTS : [];
                $rep['public'] = ws_mappa_sitemap_pubblico($contents, $mounts, $apply && $rep['changes'] > 0);
            } else {
                $rep['public'] = ['ok' => false, 'why' => 'lib/ws-mappa.php not found', 'urls' => 0];
            }
        }
        return $rep;
    }
}
?>
