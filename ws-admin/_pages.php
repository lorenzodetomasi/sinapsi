<?php
/*
 * A UNIT: the pages of a content root.
 *
 * What a PAGE is here: a content whose `@type` names `WebPage`. Not an event,
 * not a place, not an organisation — those are entities, they have their own
 * editors, and a page about one of them says so with its `mainEntity` rather
 * than by being one.
 *
 * The list is not only the pages that exist as JSON. Most of them do not yet:
 * isotype has nine migrated and twenty still written as `index.wsx`, and
 * your-website is entirely unmigrated. A list that showed only the JSON would
 * be a list of a fifth of the site, and the editor built on it would look
 * broken to whoever opened it expecting to find a page they can see published.
 *
 * So a `.wsx` not yet migrated is listed too, marked for what it is. It can be
 * migrated and then opened - `_migrate-page.php` already knows how - which
 * makes the editor the place where the migration finishes, instead of a chore
 * to be done first somewhere else.
 */

require_once __DIR__ . '/lib/ws-sites.php';

if (!defined('WS_PAGES_GENERATOR')) {
    define('WS_PAGES_GENERATOR', 'pages 2026.09.23');
}

/* ---------------------------------------------------------------------------
 * Finding
 * ------------------------------------------------------------------------- */

/*
 * Every page of a root, in the order the site map has them: a page before the
 * pages under it, siblings by address. Returns a flat list; each entry says
 * who its parent is, so a caller can draw the tree without walking the disk
 * again.
 *
 * Each entry:
 *   id        the content path inside the root (`about`, `services/branding`)
 *   state     'json' | 'wsx' | 'both'
 *   wspath    the public address, '' when the page does not declare one
 *   title     what the tab says; `name` is what the page calls itself
 *   role      the CMS's word for what this page IS (Index, PrivacyPage…)
 *   types     the schema.org types
 *   about     the @id of the mainEntity, when the page is about something
 *   parent    the wspath of the page above, '' at the root
 *   modified  ISO date, for sorting by what was touched last
 *   problem   why the file could not be read, when it could not
 */
function ws_pages_list(string $root): array {
    $root = rtrim($root, '/');
    if (!is_dir($root)) return [];

    $found = [];
    foreach (ws_pages_scan($root) as $dir) {
        $id = trim(str_replace($root, '', $dir), '/');
        if ($id === '') continue;

        $json = "$dir/index.json";
        $wsx  = "$dir/index.wsx";
        $hasJson = is_file($json);
        /* `-index.wsx` is the source a migration set aside; it is not a page
         * waiting to be migrated, it is one already done. */
        $hasWsx = is_file($wsx);

        if ($hasJson) {
            $entry = ws_pages_from_json($json, $id);
            if ($entry === null) continue;          // a content, but not a page
            $entry['state'] = $hasWsx ? 'both' : 'json';
        } elseif ($hasWsx) {
            $entry = ws_pages_from_wsx($wsx, $id);
            if ($entry === null) continue;
            $entry['state'] = 'wsx';
        } else {
            continue;
        }

        $found[$id] = $entry;
    }

    /* Tree order: by address, so a page sorts next to the pages under it. A
     * page without an address goes last - it is not published, and putting it
     * among the published ones would suggest it is. */
    uasort($found, function ($a, $b) {
        if (($a['wspath'] === '') !== ($b['wspath'] === '')) return $a['wspath'] === '' ? 1 : -1;
        return strcmp($a['wspath'], $b['wspath']) ?: strcmp($a['id'], $b['id']);
    });

    return array_values($found);
}

/*
 * The directories that may hold a page.
 *
 * `users/`, `_index/`, `_trash/` and `-`-prefixed directories are never
 * scanned - the rule the whole administration follows, and the reason a draft
 * is switched off by renaming its folder. The entity archives are skipped as
 * well: `events/`, `places/`, `organizations/`, `persons/` hold entities, and
 * an entity's page is edited where the entity is.
 */
function ws_pages_scan(string $root): array {
    $skipTop = ['users', 'events', 'places', 'organizations', 'persons', 'brand'];

    $out = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            function ($current, $key, $iterator) use ($root, $skipTop) {
                if (!$current->isDir()) return false;
                $name = $current->getFilename();
                if ($name[0] === '-' || $name[0] === '_' || $name[0] === '.') return false;
                /* The archives, only at the top: a page may legitimately be
                 * called `events` deeper down without being the archive. */
                $rel = trim(str_replace($root, '', $current->getPathname()), '/');
                if (strpos($rel, '/') === false && in_array($name, $skipTop, true)) return false;
                return true;
            }
        ),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $dir) $out[] = $dir->getPathname();
    sort($out);
    return $out;
}

/* ---------------------------------------------------------------------------
 * Reading a page
 * ------------------------------------------------------------------------- */

/* A JSON page. Returns null when the content is not a page - an event, a
 * place, a datalist: they are contents, but not this module's business. */
function ws_pages_from_json(string $file, string $id): ?array {
    $d = json_decode((string)@file_get_contents($file), true);
    if (!is_array($d)) {
        return ['problem' => 'index.json illeggibile', 'title' => $id] + ws_pages_entry($id);
    }

    $types = (array)($d['@type'] ?? []);
    if (!in_array('WebPage', $types, true)) return null;

    $about = $d['mainEntity']['@id'] ?? '';

    return [
        'wspath'   => (string)($d['wspath'] ?? ''),
        'title'    => (string)($d['title'] ?? $d['name'] ?? $id),
        'name'     => (string)($d['name'] ?? ''),
        'role'     => ws_pages_role($d),
        'types'    => array_values($types),
        'about'    => is_string($about) ? $about : '',
        'parent'   => (string)($d['parent']['wspath'] ?? ''),
        'robots'   => (string)($d['robots'] ?? ''),
        'modified' => (string)($d['dateModified'] ?? ''),
        'template' => ws_pages_template((string)($d['query'] ?? '')),
    ] + ws_pages_entry($id);
}

/*
 * A page still written by hand.
 *
 * Only what the list has to show is read, and it is read with the CMS's own
 * loader so an unresolved include does not stop the listing: a `.wsx` names
 * its address, its title and its old `type` as plain elements, and those are
 * enough to say what the page is and offer to migrate it.
 */
function ws_pages_from_wsx(string $file, string $id): ?array {
    $raw = (string)@file_get_contents($file);
    if ($raw === '') return null;

    $field = function (string $name) use ($raw): string {
        /* Deliberately textual: a `.wsx` is full of XInclude that only the CMS
         * can resolve, and parsing it here would fail on half the files for a
         * title this list shows in grey. */
        return preg_match("#<$name>([^<]*)</$name>#", $raw, $m) ? trim($m[1]) : '';
    };

    $wspath = $field('wspath');
    $type = $field('type');
    /* Without an address it is not a page of the site: a fragment, a list, a
     * file included by somebody else. */
    if ($wspath === '') return null;

    return [
        'wspath'   => $wspath,
        'title'    => $field('title') ?: ($field('name') ?: $id),
        'name'     => $field('name'),
        'role'     => $type,
        'types'    => $type !== '' ? ['WebPage', $type] : ['WebPage'],
        'about'    => '',
        'parent'   => '',
        'robots'   => $field('robots'),
        'modified' => $field('dateModified'),
        'template' => ws_pages_template($field('query')),
    ] + ws_pages_entry($id);
}

/* The defaults, to be unioned on the RIGHT of the real values: `a + b` keeps
 * what is in A, so defaults on the left would quietly throw away everything
 * that was read. */
function ws_pages_entry(string $id): array {
    return [
        'id' => $id, 'state' => '', 'wspath' => '', 'title' => '', 'name' => '',
        'role' => '', 'types' => [], 'about' => '', 'parent' => '',
        'robots' => '', 'modified' => '', 'template' => '', 'problem' => '',
    ];
}

/*
 * The CMS's word for what a page is — asked of the site map's own function,
 * not worked out again here.
 *
 * The rule has three steps (`additionalType`, then what the page is ABOUT,
 * then what it IS), and the second one is easy to forget: a page whose types
 * are only `WebPage` but whose mainEntity is a Service is a `Service` on the
 * map. Written a second time, this list said `—` for five of isotype's pages
 * that the map calls Service, and a list that disagrees with the map about
 * what a page is would be worse than no list.
 */
function ws_pages_role(array $d): string {
    require_once __DIR__ . '/_refresh-sitemap.php';
    $type = ws_sitemap_type($d);
    return $type === 'WebPage' ? '' : $type;
}

/* Which template renders the page, from its query. Shown because it is the one
 * field of a page that decides how it LOOKS, and the only way to see it today
 * is to open the JSON. */
function ws_pages_template(string $query): string {
    return preg_match('/[?&]template=([a-z0-9_-]+)/i', $query, $m) ? $m[1] : '';
}

/* ---------------------------------------------------------------------------
 * Counting, for the panel's heading
 * ------------------------------------------------------------------------- */

/* How many pages, how many still to migrate, how many unreadable. The numbers
 * the list puts above itself, so the panel does not compute them from the rows
 * and get a different answer. */
function ws_pages_summary(array $pages): array {
    $out = ['total' => count($pages), 'json' => 0, 'wsx' => 0, 'both' => 0, 'problems' => 0];
    foreach ($pages as $p) {
        if ($p['problem'] !== '') $out['problems']++;
        if (isset($out[$p['state']])) $out[$p['state']]++;
    }
    return $out;
}

/* ---------------------------------------------------------------------------
 * Il tema e il mount
 * ------------------------------------------------------------------------- */

/*
 * Il tema che le pagine di questa radice chiedono.
 *
 * Non è dichiarato dal sito da nessuna parte: sta nella `query` di ogni pagina
 * (`/?theme=isotype&template=…`), e la home è quella da cui leggerlo. Un sito
 * appena migrato può non avere ancora la home in JSON, e allora va bene
 * qualunque pagina: la query porta lo stesso tema in tutte.
 */
function ws_pages_theme(string $root): string {
    $home = rtrim($root, '/') . '/index/index.json';
    $tema = ws_pages_theme_of($home);
    if ($tema !== '') return $tema;

    foreach (ws_pages_list($root) as $p) {
        if ($p['state'] === 'wsx') continue;
        $tema = ws_pages_theme_of(rtrim($root, '/') . '/' . $p['id'] . '/index.json');
        if ($tema !== '') return $tema;
    }
    return '';
}

function ws_pages_theme_of(string $file): string {
    if (!is_file($file)) return '';
    $d = json_decode((string)@file_get_contents($file), true);
    if (!is_array($d)) return '';
    return preg_match('/[?&]theme=([a-z0-9_-]+)/i', (string)($d['query'] ?? ''), $m) ? $m[1] : '';
}

/*
 * Dove risponde un sito: il prefisso che il CMS gli mette davanti.
 *
 * L'indirizzo pubblico di una pagina è il mount più il suo `wspath`, e i
 * contenuti il mount non lo sanno — è il CMS che lo mette e lo toglie, così i
 * loro indirizzi restano quelli del giorno in cui avranno un dominio proprio.
 */
function ws_pages_mount(string $siteId): string {
    require_once __DIR__ . '/_site.php';
    $site = explode('/', $siteId)[0];
    return (string)(array_search($site, site_mounts(), true) ?: '');
}
