<?php
/*
 * WHERE THE CONTENTS ARE: the folders of a locale that hold an entity each,
 * as glob patterns relative to it (…/meetoo/it_IT).
 *
 *   events/*          an event, a series, a strand
 *   places/*          a zone (lido-di-ostia, roma)
 *   places/*\/*        a place, a site, a route of a zone (lungomare)
 *   organizations/*   a subject without a site, or with several
 *   lists/*\/*         a list of a zone (bookcrossing, libri-e-letture, age bands)
 *   users/*           a person using the site (only when asked: it is private)
 *
 * It was written by hand in a dozen places, each with its own copy; a new
 * folder meant finding them all, and the one forgotten went on ignoring it.
 * Code that deliberately looks at SOME folders (ratings, groups) keeps its own
 * list; code that means "every content" asks here.
 */

if (!function_exists('ws_content_globs')) {
    function ws_content_globs(bool $users = false): array {
        $g = ['events/*', 'places/*', 'places/*/*', 'organizations/*', 'lists/*', 'lists/*/*'];
        return $users ? array_merge($g, ['users/*']) : $g;
    }

    /** Every index.json under $base (a locale folder), as absolute paths. */
    function ws_content_files(string $base, bool $users = false): array {
        $out = [];
        foreach (ws_content_globs($users) as $g) {
            foreach (glob(rtrim($base, '/') . "/$g/index.json") ?: [] as $f) $out[] = $f;
        }
        return $out;
    }
}
