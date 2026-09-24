<?php
/*
 * The content roots the administration can work on.
 *
 * A ROOT is `contents/<site>/<locale>` for a site that has locale folders, and
 * `contents/<site>` for one that does not. It is the unit every operation runs
 * on: the maintenance registry takes one, the site map is built for one, the
 * events index belongs to one.
 *
 * This lived inside `ws-admin/index.php` — a page. Anything else that needed
 * the same list could not have it without running the hub, so the choice was
 * to copy the function or to do without. Both are worse than moving it here:
 * two lists of the sites drift the first time one learns something, and a
 * module that does without ends up asking the editor to type a path.
 *
 * Not to be confused with `_site.php`'s `site_list()`, which answers a
 * different question — one entry per SITE, with its theme, its mount and what
 * its home is about, because a site is what gets created and mounted. This one
 * answers "what can an operation run on", and returns one entry per locale.
 * Neither is a worse version of the other, so neither wraps the other.
 */

if (!function_exists('ws_admin_sites')) {
    /**
     * Keyed by id (`meetoo/it_IT`, `isotype/it_IT`); a `-` in front of a name
     * switches the site off (a reminder, a work in progress).
     *
     * @return array<string, array{id: string, label: string, path: string}>
     */
    function ws_admin_sites(string $contents): array {
        $out = [];
        foreach (glob(rtrim($contents, '/') . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $site = basename($dir);
            if ($site[0] === '-' || $site[0] === '_' || $site[0] === '.') continue;
            $locales = array_filter(glob("$dir/*", GLOB_ONLYDIR) ?: [], fn($d) => preg_match('/^[a-z]{2}_[A-Z]{2}$/', basename($d)));
            if ($locales) {
                foreach ($locales as $l) {
                    $id = "$site/" . basename($l);
                    $out[$id] = ['id' => $id, 'label' => "$site · " . basename($l), 'path' => $l];
                }
            } else {
                $out[$site] = ['id' => $site, 'label' => $site, 'path' => $dir];
            }
        }
        ksort($out);
        return $out;
    }
}

if (!function_exists('ws_admin_contents_abspath')) {
    /** Where the content roots live. One answer, so no caller spells it again. */
    function ws_admin_contents_abspath(): string {
        return dirname(__DIR__, 2) . '/ws-custom/contents';
    }
}

if (!function_exists('ws_admin_site_path')) {
    /**
     * The path of a root named by a request, or null.
     *
     * A path is never taken from a request as it comes: the name must be one
     * of the roots that exist. Every module that accepts a `site` parameter
     * goes through here, so the check cannot be the one somebody forgot.
     */
    function ws_admin_site_path(string $id): ?string {
        $sites = ws_admin_sites(ws_admin_contents_abspath());
        return $sites[$id]['path'] ?? null;
    }
}

/* The capabilities on offer. A site that declares nothing handles its pages
 * and nothing else, which is what every site did until now. */
function site_features_available(): array {
    return [
        'events' => 'Gestione degli eventi',
    ];
}

/*
 * What a site declares, read from its file.
 *
 * Parsed and not included: see above. The regex is deliberately forgiving
 * about spacing and quotes, because this line is also written by hand.
 */
function site_features(string $siteId): array {
    $file = ws_admin_contents_abspath() . '/' . $siteId . '/ws-config.php';
    if (!is_file($file)) return [];
    $body = (string)@file_get_contents($file);
    if (!preg_match('/define\s*\(\s*[\'"]WS_SITE_FEATURES[\'"]\s*,\s*(?:array\s*\(|\[)(.*?)(?:\)|\])\s*\)\s*;/s', $body, $m)) {
        return [];
    }
    $out = [];
    if (preg_match_all('/[\'"]([a-z0-9_-]+)[\'"]/i', $m[1], $f)) {
        foreach ($f[1] as $name) {
            if (isset(site_features_available()[$name]) && !in_array($name, $out, true)) $out[] = $name;
        }
    }
    return $out;
}

/*
 * Il sito su cui lavora una richiesta.
 *
 * Restituisce ['id', 'path', 'error']. Un endpoint chiama questa e non compone
 * mai un percorso da sé: il nome deve essere una delle radici che esistono, e
 * il controllo sta in un posto solo.
 *
 * SENZA UN SITO NELLA RICHIESTA si prende l'unico che dichiara la capacità
 * chiesta. Non è una scorciatoia: finché una cosa la gestisce un sito solo, la
 * domanda «quale?» non ha altre risposte, e obbligare a scriverla sarebbe
 * chiedere di ripetere ciò che si sa già. Dal giorno che i siti sono due, la
 * risposta non è più ovvia e il parametro diventa obbligatorio — cioè
 * esattamente quando comincia a servire.
 */
function ws_admin_request_site(?string $asked, string $feature = ''): array {
    $sites = ws_admin_sites(ws_admin_contents_abspath());
    $asked = trim((string)$asked);

    if ($asked !== '') {
        if (!isset($sites[$asked])) return ['id' => '', 'path' => '', 'error' => "Radice sconosciuta: $asked"];
        if ($feature !== '' && !in_array($feature, site_features(explode('/', $asked)[0]), true)) {
            return ['id' => '', 'path' => '', 'error' =>
                "«{$asked}» non ha attivato " . (site_features_available()[$feature] ?? $feature)
                . ". Si attiva dal pannello Siti."];
        }
        return ['id' => $asked, 'path' => $sites[$asked]['path'], 'error' => ''];
    }

    if ($feature === '') return ['id' => '', 'path' => '', 'error' => 'Manca il sito.'];

    $con = [];
    foreach ($sites as $id => $s) {
        if (in_array($feature, site_features(explode('/', $id)[0]), true)) $con[] = $id;
    }
    if (!$con) {
        return ['id' => '', 'path' => '', 'error' =>
            'Nessun sito ha attivato ' . (site_features_available()[$feature] ?? $feature) . '.'];
    }
    if (count($con) > 1) {
        return ['id' => '', 'path' => '', 'error' =>
            'Più siti gestiscono ' . (site_features_available()[$feature] ?? $feature)
            . ' (' . implode(', ', $con) . '): dimmi quale.'];
    }
    return ['id' => $con[0], 'path' => $sites[$con[0]]['path'], 'error' => ''];
}
