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
 *   ../users/*        a person using the site: at SITE level (ws_users_dir),
 *                     only when asked - it is private
 *
 * It was written by hand in a dozen places, each with its own copy; a new
 * folder meant finding them all, and the one forgotten went on ignoring it.
 * Code that deliberately looks at SOME folders (ratings, groups) keeps its own
 * list; code that means "every content" asks here.
 */

if (!function_exists('ws_content_globs')) {
    function ws_content_globs(bool $users = false): array {
        $g = ['events/*', 'places/*', 'places/*/*', 'organizations/*', 'lists/*', 'lists/*/*'];
        // users/* only where a site still keeps them per language (not migrated):
        // the files themselves are found by ws_content_files(), wherever they are.
        return $users ? array_merge($g, ['users/*']) : $g;
    }

    /** Every index.json under $base (a locale folder), as absolute paths; with
     *  $users, the site's users and persons too, wherever they are. */
    function ws_content_files(string $base, bool $users = false): array {
        $out = [];
        foreach (ws_content_globs(false) as $g) {
            foreach (glob(rtrim($base, '/') . "/$g/index.json") ?: [] as $f) $out[] = $f;
        }
        if ($users) {
            foreach ([ws_users_dir($base), ws_persons_dir($base)] as $d) {
                foreach (glob("$d/*/index.json") ?: [] as $f) $out[] = $f;
            }
        }
        return $out;
    }

    /**
     * WHERE A SITE'S USERS ARE (decided on 28 Sep 2026): at the level of the
     * SITE, not of a language - a user is a person, not a translation; the
     * language they prefer is in their record. contents/meetoo/users,
     * contents/isotype/users. $base is a locale folder (…/meetoo/it_IT); a
     * server not yet migrated still has them in it, and is still read.
     * `persons/` - their public profiles - follow the same rule.
     */
    function ws_users_dir(string $base): string {
        return ws_site_people_dir($base, 'users');
    }
    function ws_persons_dir(string $base): string {
        return ws_site_people_dir($base, 'persons');
    }
    function ws_site_people_dir(string $base, string $which): string {
        $base = rtrim($base, '/');
        $sito = dirname($base) . "/$which";
        $lingua = "$base/$which";
        if (is_dir($sito) || !is_dir($lingua)) return $sito;
        return $lingua;
    }
}
