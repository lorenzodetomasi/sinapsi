<?php
/*
 * A UNIT: one site. The library that knows what a site is made of, and how to
 * bring one into existence. No page of its own — `sites.php` is the page, and
 * anything else that needs to create a site (a CLI, a test) requires this.
 *
 * A site is not a directory. For a site to answer, four things must agree, and
 * they live in four different places:
 *
 *   1. the content root      ws-custom/contents/<site>/<locale>/
 *   2. the mount             WS_MOUNTS: the address prefix -> the site's folder
 *   3. the theme             a theme-settings.php declaring its parent
 *   4. the languages         <site>/ws_languages.wsx, one entry per locale
 *
 * Made by hand, they drift: `contents/isotype/ws_languages.wsx` still declares
 * `relpath: your-website/it_IT/` today, because it was copied and not read. The
 * whole point of this file is that the four are written from ONE description of
 * the site, so they cannot disagree.
 *
 * Every operation here takes an $apply flag and returns a report. With $apply
 * false it writes nothing and the report says what it would have written. That
 * is the hub's rule (README, principle 5), and it matters most here: creating a
 * site touches a tree that does not exist yet, so the preview is the only way
 * to see the shape before it is on disk.
 */

require_once __DIR__ . '/lib/ws-auth.php';

if (!defined('WS_SITE_GENERATOR')) {
    define('WS_SITE_GENERATOR', 'site-scaffold 2026.09.23');
}

/* ---------------------------------------------------------------------------
 * Where things are
 * ------------------------------------------------------------------------- */

function site_contents_abspath(): string {
    return dirname(__DIR__) . '/ws-custom/contents';
}

function site_themes_abspath(): string {
    return dirname(__DIR__) . '/ws-custom/themes';
}

/* The mount is declared in `ws-custom/ws-config.php`, never in `ws-core/mounts.php`:
 * the latter is versioned code, and the former is the place mounts.php itself
 * points at ("Si ridefinisce in ws-custom/ws-config.php, che viene caricato prima"). */
function site_config_abspath(): string {
    return dirname(__DIR__) . '/ws-custom/ws-config.php';
}

/* A site id and a locale both end up in a filesystem path, so neither is ever
 * taken from a request as it comes. A site id is a directory name and nothing
 * else; a locale is exactly `xx_XX`, which is what ws_admin_sites() recognises
 * and what ws_locale() expects. */
function site_id_valid(string $id): bool {
    return (bool)preg_match('/^[a-z0-9][a-z0-9-]{1,48}$/', $id);
}

function site_locale_valid(string $locale): bool {
    return (bool)preg_match('/^[a-z]{2}_[A-Z]{2}$/', $locale);
}

/* BCP 47 for `inLanguage` and `hreflang`: it_IT -> it-IT. */
function site_language_tag(string $locale): string {
    return str_replace('_', '-', $locale);
}

/* ---------------------------------------------------------------------------
 * What a site is made of
 * ------------------------------------------------------------------------- */

/*
 * The base pages.
 *
 * `required` pages are written whatever the editor asks, because the theme
 * looks them up by role and a site without them is broken, not minimal:
 * `ws_pageLink('PrivacyPage')` is called by the contact form's consent
 * checkbox (your-theme/template-parts/user-checkboxes.php) and by both
 * form-submitted templates. A site with a contact form and no privacy page
 * renders "Accept our " followed by nothing.
 *
 * The others are offered as ticks. `types` are schema.org types and nothing
 * else; a role schema.org has no word for goes in `role`, which is written as
 * `additionalType` - schema.org's own way of naming a type from another
 * vocabulary. So every KEY in a page is a schema.org key, and the only word
 * that is ours is the value.
 *
 * `slug` and `title` are given for the locales actually in use. For any other
 * locale the English ones are used and the report says so: a machine that
 * invents a Portuguese page title is a machine that writes a wrong one
 * silently. The editor changes it once, in the JSON.
 */
function site_page_catalog(): array {
    return [
        'index' => [
            'required' => true,
            /* schema.org has no word for "the page a site opens at", so the
             * role goes in `additionalType` - which is schema.org's OWN answer
             * for a type from another vocabulary, and keeps every key in this
             * file a schema.org key. The map reads it first and writes
             * <type>Index</type>, the word your-website's nav1 has always
             * looked up. */
            'types'    => ['WebPage', 'CollectionPage'],
            'role'     => 'Index',
            'template' => 'index',
            'slug'     => ['*' => '/'],
            'title'    => ['it_IT' => 'Home', 'en_US' => 'Home'],
            'priority' => '1',
            'changefreq' => 'weekly',
            'output'   => ['html'],
        ],
        'contacts' => [
            'required' => true,
            'types'    => ['WebPage', 'ContactPage'],
            'template' => 'contacts',
            'slug'     => ['it_IT' => '/contatti', 'en_US' => '/contacts'],
            'title'    => ['it_IT' => 'Contatti', 'en_US' => 'Contacts'],
            'priority' => '0.8',
            'changefreq' => 'yearly',
        ],
        'privacy' => [
            'required' => true,
            'types'    => ['WebPage'],
            'role'     => 'PrivacyPage',
            'template' => 'page',
            'slug'     => ['it_IT' => '/privacy', 'en_US' => '/privacy'],
            'title'    => ['it_IT' => 'Informativa sulla privacy', 'en_US' => 'Privacy policy'],
            'priority' => '0.3',
            'changefreq' => 'yearly',
        ],
        'cookies' => [
            'required' => true,
            'types'    => ['WebPage'],
            'role'     => 'CookiesPage',
            'template' => 'page',
            'slug'     => ['it_IT' => '/cookie', 'en_US' => '/cookies'],
            'title'    => ['it_IT' => 'Informativa sui cookie', 'en_US' => 'Cookie policy'],
            'priority' => '0.3',
            'changefreq' => 'yearly',
        ],
        'disclaimer' => [
            'required' => true,
            'types'    => ['WebPage'],
            'role'     => 'DisclaimerPage',
            'template' => 'page',
            'slug'     => ['it_IT' => '/note-legali', 'en_US' => '/disclaimer'],
            'title'    => ['it_IT' => 'Note legali', 'en_US' => 'Disclaimer'],
            'priority' => '0.3',
            'changefreq' => 'yearly',
        ],

        /* --- optional, one tick each --- */

        'about' => [
            'types'    => ['WebPage', 'AboutPage'],
            'template' => 'page',
            'slug'     => ['it_IT' => '/chi-siamo', 'en_US' => '/about'],
            'title'    => ['it_IT' => 'Chi siamo', 'en_US' => 'About us'],
            'priority' => '0.8',
            'changefreq' => 'monthly',
            /* An AboutPage is about the organisation that runs the site: the
             * same node the home names, by @id. Written only when the site has
             * a main entity; until then the page has no mainEntity at all,
             * which is truthful, rather than an empty one to be filled in. */
            'main_entity' => 'site',
        ],
        'offer' => [
            'types'    => ['WebPage', 'CollectionPage'],
            'template' => 'section',
            'slug'     => ['it_IT' => '/offerta', 'en_US' => '/offer'],
            'title'    => ['it_IT' => 'Offerta', 'en_US' => 'Offer'],
            'priority' => '0.9',
            'changefreq' => 'monthly',
            /* A list of what is offered. Whether the items are Services or
             * Products is the editor's choice at creation; schema.org has no
             * "OfferPage", so the page is what it is — a CollectionPage — and
             * what it collects is said in the mainEntity. */
            'main_entity' => 'itemlist',
            'item_type_choice' => ['Service', 'Product'],
            /* The role depends on what was chosen: ServicesList or
             * ProductsList - the words your-website's nav1 has always looked
             * up (`url[type = 'ServicesList']`). */
            'role_by_item' => ['Service' => 'ServicesList', 'Product' => 'ProductsList'],
        ],
        'events' => [
            'types'    => ['WebPage', 'CollectionPage'],
            'template' => 'event-list',
            'slug'     => ['it_IT' => '/eventi', 'en_US' => '/events'],
            'title'    => ['it_IT' => 'Eventi', 'en_US' => 'Events'],
            'priority' => '0.9',
            'changefreq' => 'weekly',
            'main_entity' => 'itemlist',
            'item_type' => 'Event',
            'role' => 'EventsList',
        ],
        'faq' => [
            'types'    => ['WebPage', 'FAQPage'],
            'template' => 'page',
            'slug'     => ['it_IT' => '/domande-frequenti', 'en_US' => '/faq'],
            'title'    => ['it_IT' => 'Domande frequenti', 'en_US' => 'Frequently asked questions'],
            'priority' => '0.6',
            'changefreq' => 'monthly',
        ],
        'quote' => [
            /* "Ask for a quote" is a ContactPage in fact — a page to get in
             * touch. The role the menu has to be able to look up has no
             * schema.org word, so it goes beside it in the CMS's vocabulary. */
            'types'    => ['WebPage', 'ContactPage'],
            'role'     => 'QuotePage',
            'template' => 'contacts',
            'slug'     => ['it_IT' => '/preventivo', 'en_US' => '/quote'],
            'title'    => ['it_IT' => 'Richiedi un preventivo', 'en_US' => 'Ask for a quote'],
            'priority' => '0.7',
            'changefreq' => 'yearly',
        ],
    ];
}

/* The archives a site has from the start, empty. Having the folder there is
 * what makes the first entity a one-click affair instead of a decision: the
 * place of the business, the people, the organisations it works with, the
 * events it runs. `places/` is Meetoo's schema, kept on purpose — every site
 * has places (a seat, a registered office, the venues of its events), and one
 * archive per kind of thing is the same arrangement as media and images. */
function site_archives(): array {
    return ['places', 'organizations', 'persons', 'events', 'brand/media', 'brand/media-sources'];
}

function site_page_slug(array $page, string $locale): string {
    if (isset($page['slug']['*'])) return $page['slug']['*'];
    return $page['slug'][$locale] ?? $page['slug']['en_US'];
}

function site_page_title(array $page, string $locale): string {
    return $page['title'][$locale] ?? $page['title']['en_US'];
}

/* True when the catalog has no words of its own for this locale and falls back
 * to English. The report says so, once per locale, rather than leaving the
 * editor to find English page titles in an Italian site. */
function site_locale_is_translated(string $locale): bool {
    foreach (site_page_catalog() as $page) {
        if (isset($page['slug']['*'])) continue;
        return isset($page['slug'][$locale]);
    }
    return false;
}

/* ---------------------------------------------------------------------------
 * What exists
 * ------------------------------------------------------------------------- */

/*
 * The sites, with what the panel has to show about each: the locales, the
 * theme each locale's home asks for, the mount, and whether the home already
 * says what the site is about.
 *
 * The hub's own ws_admin_sites() answers a different question — "which content
 * roots can an operation run on" — and returns one entry per LOCALE. This one
 * returns one entry per SITE, because a site is what gets created, mounted and
 * themed. They are not the same list and neither is a worse version of the
 * other, so neither wraps the other.
 */
function site_list(): array {
    $contents = site_contents_abspath();
    $mounts = site_mounts();
    $out = [];

    foreach (glob($contents . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
        $id = basename($dir);
        /* `-` is off (README, principle 7); `_` and `.` were never sites.
         * An archive- prefix is a keepsake, not a site. */
        if ($id[0] === '-' || $id[0] === '_' || $id[0] === '.') continue;

        $locales = [];
        foreach (glob("$dir/*", GLOB_ONLYDIR) ?: [] as $sub) {
            if (site_locale_valid(basename($sub))) $locales[] = basename($sub);
        }
        sort($locales);

        $out[$id] = [
            'id'         => $id,
            'path'       => $dir,
            'locales'    => $locales,
            'mount'      => array_search($id, $mounts, true) ?: '',
            'theme'      => site_theme_of($dir, $locales),
            'mainEntity' => site_main_entity_of($dir, $locales),
        ];
    }
    ksort($out);
    return $out;
}

/* The theme is not declared by the site: it sits in the `query` of every page
 * (`/?theme=isotype&template=index&content=…`). So the site's theme is the one
 * its home asks for — read it there rather than guessing from the site's name,
 * which is only usually the same thing. */
function site_theme_of(string $dir, array $locales): string {
    foreach ($locales ?: [''] as $locale) {
        $home = rtrim("$dir/$locale", '/') . '/index/index.json';
        $data = is_file($home) ? json_decode((string)@file_get_contents($home), true) : null;
        if (is_array($data) && preg_match('/[?&]theme=([a-z0-9_-]+)/i', (string)($data['query'] ?? ''), $m)) {
            return $m[1];
        }
    }
    return '';
}

/* What the site is about: the @id of the home's mainEntity, or '' when the
 * home has none yet. That empty string is the panel's whole reason to offer
 * the Google search. */
function site_main_entity_of(string $dir, array $locales): string {
    foreach ($locales ?: [''] as $locale) {
        $home = rtrim("$dir/$locale", '/') . '/index/index.json';
        $data = is_file($home) ? json_decode((string)@file_get_contents($home), true) : null;
        if (is_array($data) && !empty($data['mainEntity']['@id'])) {
            return (string)$data['mainEntity']['@id'];
        }
    }
    return '';
}

/* The mounts as they are in force, from the constant the CMS and the admin
 * both read. Reading the constant (rather than parsing ws-config.php) is the
 * point of mounts.php living in its own file: one definition, two readers. */
function site_mounts(): array {
    if (!defined('WS_MOUNTS')) {
        $config = site_config_abspath();
        /* ws-config.php is the site owner's file and may define anything; it is
         * included for its constants only, and only when the constant it would
         * set is not already set. */
        if (is_file($config)) { @include_once $config; }
        if (!defined('WS_MOUNTS')) {
            @include_once dirname(__DIR__) . '/ws-core/mounts.php';
        }
    }
    return defined('WS_MOUNTS') ? (array)WS_MOUNTS : [];
}

/* ---------------------------------------------------------------------------
 * Writing
 * ------------------------------------------------------------------------- */

/*
 * The report every operation returns. `changes` is what would be (or was)
 * written; `notes` is what the editor has to know and the machine cannot
 * decide; `errors` stops an apply before the first write.
 */
function site_report(): array {
    return ['changes' => [], 'notes' => [], 'errors' => [], 'applied' => false];
}

function site_change(array &$rep, string $what, string $path, string $detail = ''): void {
    $rep['changes'][] = ['what' => $what, 'path' => $path, 'detail' => $detail];
}

/* Writes a file, making its directory. Returns false and records the error
 * rather than throwing: a scaffold that fails halfway has to say which half. */
function site_write(array &$rep, string $abspath, string $content, string $what): bool {
    $dir = dirname($abspath);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        $rep['errors'][] = "Cannot create directory: $dir";
        return false;
    }
    if (@file_put_contents($abspath, $content) === false) {
        $rep['errors'][] = "Cannot write: $abspath";
        return false;
    }
    site_change($rep, $what, $abspath);
    return true;
}

function site_json(array $data): string {
    return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
}

/* ---------------------------------------------------------------------------
 * The pages
 * ------------------------------------------------------------------------- */

/*
 * One page's index.json.
 *
 * The home is the page about the site, so it carries two nodes that no other
 * page carries: `isPartOf`, the WebSite every page names, and `mainEntity`,
 * what the site is about. They are three entities with three identities — the
 * page, the site, the business — and each names the others by @id rather than
 * repeating them. That is the whole reason the business lives in `places/` and
 * not inline: it is one thing, and it is written once.
 */
function site_page_json(string $site, string $locale, string $key, array $page, array $spec): array {
    $slug  = site_page_slug($page, $locale);
    $title = site_page_title($page, $locale);
    $theme = $spec['theme'];
    $name  = $spec['name'];
    $now   = date('c');

    $json = [
        '@context' => 'https://schema.org',
        '@type'    => $page['types'],
        '@id'      => $key,
        'wspath'   => $slug,
        'query'    => "/?theme=$theme&template={$page['template']}&content=$site/$locale/$key",
        'inLanguage' => site_language_tag($locale),
    ];

    /*
     * The role this page plays, when schema.org has no type for it. It sits
     * right after @type because that is what it extends.
     *
     * A list page needs one as much as the legal pages do, and for a reason
     * the map makes plain: its mainEntity is `["ItemList", "CreativeWork"]`,
     * which is a UNION of two types and not a scale from general to specific,
     * so "the most specific type" picks the last one and every list page in
     * the site comes out as CreativeWork. Two pages, one word, and a menu that
     * sends both to whichever came first.
     */
    $role = $page['role'] ?? '';
    if ($role === '' && !empty($page['role_by_item'])) {
        $role = $page['role_by_item'][$spec['offer_type'] ?? ''] ?? '';
    }
    if ($role !== '') {
        $json['additionalType'] = $role;
    }

    if ($key !== 'index') {
        $json['parent'] = ['wspath' => '/'];
    }

    $json['title']       = $key === 'index' ? $name : "$title – $name";
    $json['description'] = '';
    $json['changefreq']  = $page['changefreq'];
    $json['priority']    = $page['priority'];
    $json['robots']      = 'index, follow';
    $json['dateCreated'] = $now;
    $json['datePublished'] = $now;
    $json['dateModified']  = $now;
    $json['name']        = $title;

    if (!empty($page['output'])) {
        $json['output'] = $page['output'];
    }

    if ($key === 'index') {
        $json['isPartOf'] = [
            '@type' => 'WebSite',
            '@id'   => '#website',
            'name'  => $name,
            'url'   => $spec['url'] ?? '',
        ];
        if (!empty($spec['main_entity_id'])) {
            $json['isPartOf']['publisher'] = ['@id' => $spec['main_entity_id']];
            $json['mainEntity'] = site_main_entity_node($spec);
        }
    } elseif (($page['main_entity'] ?? '') === 'site' && !empty($spec['main_entity_id'])) {
        $json['mainEntity'] = ['@id' => $spec['main_entity_id']];
    } elseif (($page['main_entity'] ?? '') === 'itemlist') {
        /*
         * A list page says what it lists with `about`, and `about` is a
         * property of CreativeWork, not of ItemList - which is why the list is
         * both, exactly as schema.org's own example declares
         * ["ItemList", "CreativeWork"] so that it may carry `about` and
         * `author`.
         *
         * `about` holds a PATTERN of the things listed: an Event whose
         * organizer is this business. That is the scope, said in schema.org's
         * words - no key of ours, and a reader who knows only schema.org still
         * understands what the page collects.
         */
        $json['mainEntity'] = [
            '@type' => ['ItemList', 'CreativeWork'],
            'name'  => $title,
        ];
        $about = site_list_about($page, $spec);
        if ($about) $json['mainEntity']['about'] = $about;
        /* Empty on purpose: the list is what the archive holds, and the
         * archive is empty on the first day. An invented example item would be
         * a page that lies until someone deletes it. */
        $json['mainEntity']['numberOfItems'] = 0;
        $json['mainEntity']['itemListElement'] = [];
    }

    /* A contact page without a form is a page with a heading. The form is a
     * template part, included the way isotype's contacts page includes it. */
    if ($page['template'] === 'contacts') {
        $json['section'] = [[
            '@xml:id' => 'contact-form',
            '@class'  => 'form',
            '#text'   => '[include_template template_relpath="template-parts/contact-form"]',
        ]];
    }

    return $json;
}

/*
 * What a list page is about: the shape of the things it collects.
 *
 * For the events page that is "an Event organised by this business"; for the
 * offer page, "a Service (or Product) provided by it". The pattern is a
 * partial description, so the same field that filters is the field the item
 * itself carries - an event's `organizer`, a service's `provider`. Nothing has
 * to be translated between the list and its items.
 *
 * This is also what makes the scope general enough for Meetoo. There a list is
 * the events of a ZONE, which is not equality but containment, and schema.org
 * says that too: `location` -> a Place whose `containedInPlace` is the zone.
 * Same mechanism, a deeper pattern.
 */
function site_list_about(array $page, array $spec): array {
    $itemType = $page['item_type'] ?? ($spec['offer_type'] ?? '');
    if ($itemType === '') return [];

    $about = ['@type' => $itemType];

    /* The pattern is written even before the site knows whose it is: "this
     * page lists Services" is already true and already worth saying, and it is
     * where the editor's choice between Services and Products is KEPT. The
     * business is added when there is one - a site gets its pages first and
     * its Google listing after, always. */
    $entity = $spec['main_entity_id'] ?? '';
    if ($entity !== '') {
        /* Which property ties the item to the business: an Event is ORGANISED
         * by it, a Service or a Product is PROVIDED by it. */
        $link = $itemType === 'Event' ? 'organizer' : 'provider';
        $about[$link] = ['@id' => $entity];
    }
    return $about;
}

/* The home's mainEntity: the business, named by @id, with its types. The data
 * stays in places/<area>/<slug>/index.json — this is the reference, and the
 * types are repeated here only because a reader of the home should not have to
 * fetch another file to learn what kind of thing the site is about. */
function site_main_entity_node(array $spec): array {
    $types = $spec['main_entity_types'] ?? ['LocalBusiness'];
    return [
        '@type' => count($types) === 1 ? $types[0] : array_values($types),
        '@id'   => $spec['main_entity_id'],
    ];
}

/* ---------------------------------------------------------------------------
 * The site's own files
 * ------------------------------------------------------------------------- */

/*
 * ws_languages.wsx — the one file that made the case for this whole module.
 * Every entry says which locale, how it is written in a menu, where it answers
 * and where its content is. The `relpath` is derived from the site id and the
 * locale, which is precisely what a copied file gets wrong.
 *
 * The first locale answers at the site's root; the others hang off their
 * language's short name (`/en`), which is the arrangement your-website and
 * isotype both use.
 */
function site_languages_wsx(string $site, array $locales, string $primary): string {
    $names = ['it_IT' => ['it', 'Italiano'], 'en_US' => ['en', 'English'],
              'fr_FR' => ['fr', 'Français'], 'de_DE' => ['de', 'Deutsch'],
              'es_ES' => ['es', 'Español'],  'pt_PT' => ['pt', 'Português']];

    $items = '';
    foreach ($locales as $locale) {
        $short = $names[$locale][0] ?? strtolower(substr($locale, 0, 2));
        $label = $names[$locale][1] ?? $locale;
        $wspath = $locale === $primary ? '/' : "/$short";
        $items .= "\t<item>\n"
                . "\t\t<locale>$locale</locale>\n"
                . "\t\t<name>$short</name>\n"
                . "\t\t<title>$label</title>\n"
                . "\t\t<wspath>$wspath</wspath>\n"
                . "\t\t<relpath>$site/$locale/</relpath>\n"
                . "\t</item>\n";
    }

    return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
         . "<nav id=\"languages\" xmlns:xi=\"http://www.w3.org/2001/XInclude\">\n"
         . $items
         . "</nav>\n";
}

/*
 * ws_headings — the site's identity as the templates read it: which theme,
 * which content, the address, the title, and the organisation behind it.
 * ws-settings.php looks for it in the locale's folder first, then the site's,
 * and accepts JSON — so JSON it is, the source, with the XML twin derived.
 *
 * your-theme's header reads `$ws_headings->url[0]` without checking, so a site
 * without this file does not render at all: it is not decoration, it is the
 * first thing the page needs.
 */
function site_headings_json(string $site, string $locale, array $spec): string {
    $json = [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'inLanguage' => $locale,
        'ws_path' => '/',
        'ws_theme_id' => $spec['theme'],
        'ws_content_id' => $site,
        'url' => $spec['url'] ?? '',
        'title' => $spec['name'],
        /* `name` and `headline` are both here because the header prints both
         * without checking either (`mainEntity->headline->innerHTML()`): a
         * missing element is a null, and a null is a fatal error, not a blank
         * line. The headline is empty until someone writes one — empty is a
         * value, absent is not. */
        'mainEntity' => site_headings_entity(['@type' => 'Organization'], $spec),
    ];
    /* When the site is about a business, the headings name it by @id too: the
     * header shows its address and telephone, and they belong to the one node
     * that holds them, not to a second copy written here. */
    if (!empty($spec['main_entity_id'])) {
        $types = $spec['main_entity_types'] ?? ['LocalBusiness'];
        $json['mainEntity'] = site_headings_entity([
            '@type' => count($types) === 1 ? $types[0] : array_values($types),
            '@id' => $spec['main_entity_id'],
        ], $spec);
    }
    return site_json($json);
}

/*
 * The fields the header and the footer read off the headings' main entity,
 * every one of them, declared even when empty.
 *
 * They print without checking: `mainEntity->headline->innerHTML()` on a
 * missing element is a fatal error, and `PostalAddress(null)` renders the
 * literal ", 0, ," that a brand-new site showed in its footer. An empty value
 * is a value — it prints as nothing. An absent one is a bug that only appears
 * on the sites nobody has filled in yet, which is every site on its first day.
 */
function site_headings_entity(array $base, array $spec): array {
    return $base + [
        'name' => $spec['name'],
        'headline' => '',
        'legalName' => '',
        'vatID' => '',
        'taxID' => '',
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => '', 'postalCode' => '',
            'addressLocality' => '', 'addressRegion' => '', 'addressCountry' => '',
        ],
    ];
}

/*
 * The locations: where the site's organisation actually is.
 *
 * It holds no addresses. Each entry is an `@id` into `places/`, because a seat
 * is a place and the places archive is where a place is written - once. The
 * alternative, an address copied here and another in places/, is two answers to
 * "where are you" that drift the first time one of them is corrected.
 *
 * Empty until the site has a main entity: with one, its place is the first
 * location, and that is the whole file.
 */
function site_locations_json(array $spec): string {
    $items = [];
    if (!empty($spec['main_entity_id'])) {
        /* A ListItem wrapping the reference, which is how schema.org's own
         * ItemList example is written: the position is part of the list, the
         * item is the thing. */
        $items[] = [
            '@type' => 'ListItem',
            'position' => 1,
            'item' => ['@id' => $spec['main_entity_id']],
        ];
    }
    return site_json([
        '@context' => 'https://schema.org',
        '@type' => 'ItemList',
        '@id' => 'locations',
        'name' => 'Locations',
        'numberOfItems' => count($items),
        'itemListElement' => $items,
    ]);
}

/* The site's sitemap composes its locales' maps. Each locale's own map is
 * DERIVED (refresh-sitemaps.php builds it from the pages), so it is not
 * written here — only the frame that includes them. */
function site_sitemap_wsx(array $locales): string {
    $includes = '';
    foreach ($locales as $locale) {
        $includes .= "\t<xi:include href=\"$locale/ws_sitemap.wsx\" xpointer=\"xpointer(/*[1]/*)\"/>\n";
    }
    return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
         . "<urlset xmlns:xi=\"http://www.w3.org/2001/XInclude\">\n"
         . $includes
         . "</urlset>\n";
}

/*
 * The main menu.
 *
 * A menu entry says WHAT, not WHERE: it looks the page up in the site map and
 * takes the address from there. Meetoo learned this the hard way — addresses
 * written by hand survived a change in the tree and became seven links to
 * seven 404s.
 *
 * What it looks the page up BY is the point. your-website's nav1 uses the
 * map's `type`, and for its pages that works. For these it does not: the map
 * derives `type` from the mainEntity when there is one (_refresh-sitemap.php),
 * so the home's type is whatever kind of business the site is about — a
 * different word for every site — and the Offer and Events pages are both
 * `ItemList`. Two entries looking up `ItemList` both find whichever url comes
 * first: one menu, two names, one destination.
 *
 * So the key is the page's content path, which is the page's identity (its
 * folder) and is unique by construction. It is matched inside `query` rather
 * than against the whole string so that changing a page's template — the one
 * other thing `query` carries — does not silently empty the menu. The catalog's
 * keys are a closed set in which none is a prefix of another, so the match
 * cannot reach a second page.
 */
function site_nav1_wsx(string $site, array $pages, string $locale): string {
    $icons = [
        'index' => 'home', 'about' => 'group', 'offer' => 'design_services',
        'events' => 'event', 'faq' => 'help', 'contacts' => 'support_agent',
        'quote' => 'request_quote',
    ];
    $catalog = site_page_catalog();
    $order = ['index', 'about', 'offer', 'events', 'faq', 'contacts', 'quote'];

    $items = '';
    foreach ($order as $key) {
        if (!in_array($key, $pages, true)) continue;
        $items .= site_nav_item($site, $key, $locale, $icons[$key] ?? 'chevron_right');
    }

    return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
         . "<nav id=\"nav1\" xmlns:xi=\"http://www.w3.org/2001/XInclude\">\n"
         . $items
         . "</nav>\n";
}

/* The legal menu: the pages the footer links and the consent checkbox names. */
function site_nav_legal_wsx(string $site, string $locale): string {
    $items = '';
    foreach (['privacy', 'cookies', 'disclaimer', 'contacts'] as $key) {
        $items .= site_nav_item($site, $key, $locale, '');
    }
    return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
         . "<nav id=\"nav-legal\" xmlns:xi=\"http://www.w3.org/2001/XInclude\">\n"
         . $items
         . "</nav>\n";
}

/* One menu entry. The name is written here because it is the menu's word for
 * the page, which is not always the page's own title ("Offerta" in the menu,
 * "La nostra offerta" as a heading); the address and the title come from the
 * map, so they are never stale. */
function site_nav_item(string $site, string $key, string $locale, string $icon): string {
    $catalog = site_page_catalog();
    $name = site_page_title($catalog[$key], $locale);
    $match = "contains(query, 'content=$site/$locale/$key')";
    $out = "\t<item>\n\t\t<name>$name</name>\n";
    if ($icon !== '') {
        $out .= "\t\t<icon><span class=\"icon material-symbols-outlined\">$icon</span> </icon>\n";
    }
    $out .= "\t\t<xi:include href=\"ws_sitemap.wsx\" xpointer=\"xpointer(/*[1]/url[$match]/wspath)\"/>\n"
          . "\t\t<xi:include href=\"ws_sitemap.wsx\" xpointer=\"xpointer(/*[1]/url[$match]/title)\"/>\n"
          . "\t</item>\n";
    return $out;
}

/* The theme: a shell that inherits everything from your-theme. isotype's is
 * fifteen lines and nothing else, and that is the point — a new site gets its
 * own identity to put styles in, without a copy of any template. */
function site_theme_settings(string $theme, string $name): string {
    $esc = fn($s) => str_replace("'", "\\'", $s);
    return "<?php\n"
         . "global \$ws_query, \$ws_themes, \$ws_logs;\n"
         . "\$ws_themes[] = [\n"
         . "  'name' => '" . $esc($name) . "',\n"
         . "  'id' => '" . $esc($theme) . "',\n"
         . "  'text_domain' => '" . $esc($theme) . "',\n"
         . "  'version' => 0.1,\n"
         . "  'description' => __('Theme of " . $esc($name) . "', '" . $esc($theme) . "'),\n"
         . "  'parent_theme' => 'your-theme',\n"
         . "  'license_uri' => 'http://www.gnu.org/licenses/gpl-2.0.html',\n"
         . "  'author_name' => '" . $esc($name) . "',\n"
         . "  'tags' => 'json-ld, schema.org',\n"
         . "];\n";
}

/* The site's own config: the file the CMS loads after the global one, for the
 * few constants a single site overrides. Empty but present, as your-website's
 * and isotype's are — its absence is a missing file, not a default. */
function site_config_stub(): string {
    return "<?php\n"
         . "// Configuration of this site alone. Loaded after ws-custom/ws-config.php:\n"
         . "// define here only what this site overrides.\n";
}

/* ---------------------------------------------------------------------------
 * The mount
 * ------------------------------------------------------------------------- */

/*
 * The mount lives in `ws-custom/ws-config.php`, in a block this module owns,
 * between two markers. A block, and not a line appended each time, because
 * `define` is once-only: a second `define('WS_MOUNTS', …)` raises a notice and
 * is IGNORED, so a site added that way would look mounted in the file and not
 * be mounted at all. Owning the block means the whole list is rewritten from
 * the sites, every time, and there is only ever one define.
 *
 * If the file already defines WS_MOUNTS outside the block — someone wrote it
 * by hand — nothing is touched and the report says so. Two definitions of
 * where the sites answer is exactly the failure this avoids.
 */
const SITE_MOUNTS_OPEN  = '// --- WS_MOUNTS: written by ws-admin/sites.php. Change mounts from the panel.';
const SITE_MOUNTS_CLOSE = '// --- end WS_MOUNTS';

function site_mounts_block(array $mounts): string {
    $lines = [SITE_MOUNTS_OPEN, "define('WS_MOUNTS', array("];
    foreach ($mounts as $prefix => $site) {
        $lines[] = "    '" . str_replace("'", "\\'", $prefix) . "' => '" . str_replace("'", "\\'", $site) . "',";
    }
    $lines[] = '));';
    $lines[] = SITE_MOUNTS_CLOSE;
    return implode("\n", $lines) . "\n";
}

/* Adds a mount and rewrites the block. Returns the report; writes only on apply. */
function site_mount_write(array &$rep, string $prefix, string $site, bool $apply): void {
    $config = site_config_abspath();
    if (!is_file($config)) { $rep['errors'][] = "ws-config.php not found: $config"; return; }

    $body = (string)@file_get_contents($config);
    $open = strpos($body, SITE_MOUNTS_OPEN);
    $close = strpos($body, SITE_MOUNTS_CLOSE);

    /* A WS_MOUNTS written by hand is left where it is: rewriting someone's own
     * line is how a panel loses the trust of the person who has to fix it at
     * three in the morning. */
    if ($open === false && preg_match('/^\s*define\s*\(\s*[\'"]WS_MOUNTS[\'"]/m', $body)) {
        $rep['notes'][] = "ws-config.php already defines WS_MOUNTS by hand: not touched. "
                        . "Add '$prefix' => '$site' to it yourself, or remove the definition "
                        . "and let the panel keep the list.";
        return;
    }

    $current = [];
    if ($open !== false && $close !== false && $close > $open) {
        /* Re-read the list from the block itself, not from the constant: by the
         * time this runs the constant may already be defined from an older
         * request, and the file is what the next request will read. */
        $block = substr($body, $open, $close - $open);
        if (preg_match_all('/[\'"]([^\'"]*)[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/', $block, $m, PREG_SET_ORDER)) {
            foreach ($m as $pair) $current[$pair[1]] = $pair[2];
        }
    } else {
        $current = site_mounts();
    }

    if (($current[$prefix] ?? null) === $site) {
        $rep['notes'][] = "Mount '$prefix' -> '$site' was already there.";
        return;
    }
    if (isset($current[$prefix])) {
        $rep['errors'][] = "The address '$prefix' is already the mount of '{$current[$prefix]}'.";
        return;
    }

    $current[$prefix] = $site;
    $new = site_mounts_block($current);

    if ($open !== false && $close !== false && $close > $open) {
        $end = $close + strlen(SITE_MOUNTS_CLOSE);
        $body = substr($body, 0, $open) . rtrim($new) . substr($body, $end);
    } else {
        $body = rtrim($body, " \t\n\r") . "\n\n" . $new;
    }

    site_change($rep, 'mount', $config, "$prefix -> $site");
    if ($apply && @file_put_contents($config, $body) === false) {
        $rep['errors'][] = "Cannot write: $config";
    }
}

/* ---------------------------------------------------------------------------
 * The root map
 * ------------------------------------------------------------------------- */

/*
 * `contents/ws_sitemap.wsx` composes the sites' maps, and it is what the CMS
 * routes on (ws-core/query.php reads it before anything else). A site can have
 * its folder, its pages, its theme and its mount and still answer nothing,
 * because the router never sees it: this file is the difference between a site
 * that exists on disk and a site that is on the air.
 *
 * It is hand-written and stays hand-written — the order of the includes is a
 * decision (the admin's map sits among them), and rewriting the whole file
 * would throw that away. Only one line is added, before the closing tag.
 */
function site_root_map_write(array &$rep, string $siteId, bool $apply): void {
    $path = site_contents_abspath() . '/ws_sitemap.wsx';
    if (!is_file($path)) { $rep['errors'][] = "Root site map not found: $path"; return; }

    $body = (string)@file_get_contents($path);
    $include = "  <xi:include href=\"$siteId/ws_sitemap.wsx\" xpointer=\"xpointer(/*[1]/*)\"/>";

    if (strpos($body, "href=\"$siteId/ws_sitemap.wsx\"") !== false) {
        $rep['notes'][] = "The root map already includes '$siteId'.";
        return;
    }
    $close = strrpos($body, '</urlset>');
    if ($close === false) { $rep['errors'][] = "Root site map has no </urlset>: $path"; return; }

    $body = substr($body, 0, $close) . $include . "\n" . substr($body, $close);

    site_change($rep, 'root map', $path, "includes $siteId");
    if ($apply && @file_put_contents($path, $body) === false) {
        $rep['errors'][] = "Cannot write: $path";
    }
}

/* ---------------------------------------------------------------------------
 * Creating a site
 * ------------------------------------------------------------------------- */

/*
 * $spec:
 *   id            the site's folder in contents/ and the first half of its @id
 *   name          what it is called (the WebSite's name, the theme's name)
 *   locales       one or more, the first being the primary
 *   theme         the new theme's folder; defaults to the site id
 *   mount         the address prefix, '' for a site that answers at the root
 *   url           the site's address, for the WebSite node
 *   pages         the optional pages ticked, by catalog key
 *   offer_type    'Service' or 'Product', when 'offer' is among them
 *
 * Everything is checked BEFORE the first write. A half-made site is worse than
 * no site: it is on the sites list, it answers nothing, and the next attempt
 * finds the folder already there.
 */
function site_create(array $spec, bool $apply): array {
    $rep = site_report();
    $spec = site_spec_normalize($spec, $rep);
    if ($rep['errors']) return $rep;

    $contents = site_contents_abspath();
    $root = $contents . '/' . $spec['id'];
    $themeDir = site_themes_abspath() . '/' . $spec['theme'];

    if (is_dir($root))     $rep['errors'][] = "A site '{$spec['id']}' already exists: $root";
    if (is_dir($themeDir)) $rep['errors'][] = "A theme '{$spec['theme']}' already exists: $themeDir";
    if ($rep['errors']) return $rep;

    /* The theme first: it is what every page's `query` names, so a site whose
     * theme is missing is a site of pages that cannot render. */
    if ($apply) {
        site_write($rep, "$themeDir/theme-settings.php", site_theme_settings($spec['theme'], $spec['name']), 'theme');
    } else {
        site_change($rep, 'theme', "$themeDir/theme-settings.php", "parent: your-theme");
    }

    /* The site's files. */
    site_plan_write($rep, "$root/ws-config.php", site_config_stub(), 'config', $apply);
    site_plan_write($rep, "$root/ws_languages.wsx", site_languages_wsx($spec['id'], $spec['locales'], $spec['locales'][0]), 'languages', $apply);
    site_plan_write($rep, "$root/ws_sitemap.wsx", site_sitemap_wsx($spec['locales']), 'sitemap frame', $apply);

    foreach ($spec['locales'] as $locale) {
        site_locale_write($rep, $root, $spec, $locale, $apply);
    }

    if ($spec['mount'] !== '') {
        site_mount_write($rep, $spec['mount'], $spec['id'], $apply);
    } else {
        $rep['notes'][] = "No mount: this site answers at the root of its domain.";
    }

    /* Without this the site is invisible to the router, whatever else is in
     * place. It is the last thing written, so that a site only appears on the
     * map once everything it needs is there. */
    site_root_map_write($rep, $spec['id'], $apply);

    /* The site map is derived, so it is not written — it is BUILT, from the
     * pages that now exist. Doing it here is what makes the site answer at the
     * end of the operation instead of after a second, manual step that whoever
     * creates a site would have to know about. */
    if ($apply && !$rep['errors']) {
        require_once __DIR__ . '/refresh-sitemaps.php';
        foreach ($spec['locales'] as $locale) {
            $r = ws_refresh_sitemaps("$root/$locale", true);
            site_change($rep, 'site map', "$root/$locale/ws_sitemap.wsx", (string)($r['map']['status'] ?? ''));
        }
        $rep['applied'] = true;
    }

    return $rep;
}

/* One locale: its pages, its menus, its archives. Used both when a site is
 * created and when a language is added to a site that already exists — which
 * is why it is a function and not a stretch of site_create(). */
function site_locale_write(array &$rep, string $root, array $spec, string $locale, bool $apply): void {
    $catalog = site_page_catalog();
    $pages = site_pages_of($spec);

    foreach ($pages as $key) {
        $json = site_page_json($spec['id'], $locale, $key, $catalog[$key], $spec);
        /* The offer page says what it lists, and the editor chose. */
        site_plan_write($rep, "$root/$locale/$key/index.json", site_json($json), "page $key", $apply);
    }

    site_plan_write($rep, "$root/$locale/ws_headings.json", site_headings_json($spec['id'], $locale, $spec), 'headings', $apply);
    site_plan_write($rep, "$root/$locale/locations/index.json", site_locations_json($spec), 'locations', $apply);
    site_plan_write($rep, "$root/$locale/nav1.wsx", site_nav1_wsx($spec['id'], $pages, $locale), 'main menu', $apply);
    site_plan_write($rep, "$root/$locale/nav-legal.wsx", site_nav_legal_wsx($spec['id'], $locale), 'legal menu', $apply);

    /* The twins of the two menus, built now rather than on first request.
     * They are derived files and the CMS makes them lazily — but your-theme's
     * header asks `file_exists(nav1.xml)` BEFORE loading, and takes another
     * branch when it is missing. On a brand-new site that branch reaches for a
     * file that does not exist either, and the page dies on `->count()`. The
     * laziness is right; it just cannot be the FIRST thing that happens on a
     * site whose twins have never been built. */
    if ($apply) {
        require_once __DIR__ . '/_refresh-content.php';
        foreach (['nav1', 'nav-legal'] as $nav) {
            $twin = ws_content_ensure_xml("$root/$locale/$nav.wsx");
            if ($twin === '') $rep['notes'][] = "$locale: could not build $nav.xml; it will be built on the first request.";
        }
    }

    /* The archives: empty folders, kept by a file that makes them survive a git
     * checkout or an FTP upload — an empty directory does not, and a folder
     * that vanishes is a folder the next tool recreates differently.
     *
     * An archive that a page already occupies gets no keeper: a collection page
     * IS its archive, with its items as the pages under it (`places/roma` and
     * what is inside it, in Meetoo). The keeper would sit beside that page's
     * index.json saying the folder is empty, which it is not. */
    foreach (site_archives() as $archive) {
        if (in_array($archive, $pages, true)) continue;
        site_plan_write($rep, "$root/$locale/$archive/.gitkeep", "", "archive $archive", $apply);
    }

    if (!site_locale_is_translated($locale)) {
        $rep['notes'][] = "$locale: the page titles and addresses are the English ones — "
                        . "the catalog has no words for this language. Change them in each index.json.";
    }
}

/* Writes on apply, records on preview. The two paths say the same thing to the
 * report, so the preview cannot describe something the apply does not do. */
function site_plan_write(array &$rep, string $abspath, string $content, string $what, bool $apply): void {
    if ($apply) { site_write($rep, $abspath, $content, $what); return; }
    site_change($rep, $what, $abspath);
}

function site_pages_of(array $spec): array {
    $catalog = site_page_catalog();
    $pages = [];
    foreach ($catalog as $key => $page) {
        if (!empty($page['required']) || in_array($key, $spec['pages'], true)) $pages[] = $key;
    }
    return $pages;
}

/* Checks and fills in the description of the site. Everything that could stop
 * an apply is decided here, in one place, so that site_create() can write. */
function site_spec_normalize(array $spec, array &$rep): array {
    $spec += ['id' => '', 'name' => '', 'locales' => [], 'theme' => '', 'mount' => '',
              'url' => '', 'pages' => [], 'offer_type' => '', 'main_entity_id' => '',
              'main_entity_types' => []];

    $spec['id'] = strtolower(trim((string)$spec['id']));
    if (!site_id_valid($spec['id'])) {
        $rep['errors'][] = "Invalid site id: '{$spec['id']}'. Lowercase letters, digits and hyphens, 2 to 49 characters.";
    }

    $spec['name'] = trim((string)$spec['name']);
    if ($spec['name'] === '') $rep['errors'][] = "The site has no name.";

    $spec['theme'] = strtolower(trim((string)$spec['theme'])) ?: $spec['id'];
    if (!site_id_valid($spec['theme'])) $rep['errors'][] = "Invalid theme id: '{$spec['theme']}'.";

    $locales = [];
    foreach ((array)$spec['locales'] as $l) {
        $l = trim((string)$l);
        if (!site_locale_valid($l)) { $rep['errors'][] = "Invalid language: '$l'. Expected the form it_IT."; continue; }
        if (!in_array($l, $locales, true)) $locales[] = $l;
    }
    if (!$locales) $rep['errors'][] = "The site has no language: choose at least the primary one.";
    $spec['locales'] = $locales;

    /* A mount is an address prefix: one leading slash, no trailing one. */
    $spec['mount'] = trim((string)$spec['mount']);
    if ($spec['mount'] !== '') {
        $spec['mount'] = '/' . trim($spec['mount'], '/');
        if (!preg_match('#^/[a-z0-9][a-z0-9/-]*$#', $spec['mount'])) {
            $rep['errors'][] = "Invalid mount: '{$spec['mount']}'.";
        }
    }

    $catalog = site_page_catalog();
    $pages = [];
    foreach ((array)$spec['pages'] as $key) {
        $key = (string)$key;
        if (!isset($catalog[$key])) { $rep['errors'][] = "Unknown page: '$key'."; continue; }
        if (!empty($catalog[$key]['required'])) continue;  // already in, not a choice
        $pages[] = $key;
    }
    $spec['pages'] = $pages;

    if (in_array('offer', $pages, true)) {
        $allowed = $catalog['offer']['item_type_choice'];
        if (!in_array($spec['offer_type'], $allowed, true)) {
            $rep['errors'][] = "The offer page lists Services or Products: say which.";
        }
    }

    return $spec;
}

/* ---------------------------------------------------------------------------
 * Adding a language to a site that exists
 * ------------------------------------------------------------------------- */

/*
 * The same machinery, on a root that is already there: the locale's pages and
 * menus, and `ws_languages.wsx` and `ws_sitemap.wsx` rewritten to include it.
 * They are rewritten whole, from the locales found on disk, so the file can
 * never disagree with the folders — the failure that `contents/isotype` shows
 * to this day.
 */
function site_add_locale(string $siteId, string $locale, bool $apply): array {
    $rep = site_report();

    $sites = site_list();
    if (!isset($sites[$siteId])) { $rep['errors'][] = "Unknown site: '$siteId'."; return $rep; }
    if (!site_locale_valid($locale)) { $rep['errors'][] = "Invalid language: '$locale'."; return $rep; }

    $site = $sites[$siteId];
    $root = $site['path'];
    if (in_array($locale, $site['locales'], true)) {
        $rep['errors'][] = "'$siteId' already has $locale.";
        return $rep;
    }
    if ($site['theme'] === '') {
        $rep['errors'][] = "'$siteId' has no readable home: cannot tell which theme its pages ask for.";
        return $rep;
    }

    /* What the new locale's pages have to say is taken from the site as it is,
     * not asked again: the name and the main entity are the site's, whatever
     * language it is read in. */
    $spec = [
        'id' => $siteId,
        'name' => site_name_of($root, $site['locales']),
        'theme' => $site['theme'],
        'locales' => array_merge($site['locales'], [$locale]),
        'pages' => site_optional_pages_of($root, $site['locales']),
        'main_entity_id' => $site['mainEntity'],
        'main_entity_types' => site_main_entity_types_of($root, $site['locales']),
        'offer_type' => '',
        'url' => site_url_of($root, $site['locales']),
    ];

    site_locale_write($rep, $root, $spec, $locale, $apply);
    site_plan_write($rep, "$root/ws_languages.wsx", site_languages_wsx($siteId, $spec['locales'], $site['locales'][0]), 'languages', $apply);
    site_plan_write($rep, "$root/ws_sitemap.wsx", site_sitemap_wsx($spec['locales']), 'sitemap frame', $apply);

    if ($apply && !$rep['errors']) {
        require_once __DIR__ . '/refresh-sitemaps.php';
        $r = ws_refresh_sitemaps("$root/$locale", true);
        site_change($rep, 'site map', "$root/$locale/ws_sitemap.wsx", (string)($r['map']['status'] ?? ''));
        $rep['applied'] = true;
    }
    return $rep;
}

/* The pages an existing locale actually has, so a new language gets the same
 * ones. Reading them off the disk beats asking the editor to remember. */
function site_optional_pages_of(string $root, array $locales): array {
    $catalog = site_page_catalog();
    $found = [];
    foreach ($locales as $locale) {
        foreach ($catalog as $key => $page) {
            if (!empty($page['required'])) continue;
            if (is_file("$root/$locale/$key/index.json") && !in_array($key, $found, true)) $found[] = $key;
        }
    }
    return $found;
}

function site_home_json(string $root, array $locales): array {
    foreach ($locales as $locale) {
        $home = "$root/$locale/index/index.json";
        $data = is_file($home) ? json_decode((string)@file_get_contents($home), true) : null;
        if (is_array($data)) return $data;
    }
    return [];
}

function site_name_of(string $root, array $locales): string {
    $home = site_home_json($root, $locales);
    return (string)($home['isPartOf']['name'] ?? $home['name'] ?? basename($root));
}

function site_url_of(string $root, array $locales): string {
    $home = site_home_json($root, $locales);
    return (string)($home['isPartOf']['url'] ?? '');
}

function site_main_entity_types_of(string $root, array $locales): array {
    $home = site_home_json($root, $locales);
    $t = $home['mainEntity']['@type'] ?? [];
    return is_array($t) ? $t : [$t];
}

/* ---------------------------------------------------------------------------
 * What the site is about
 * ------------------------------------------------------------------------- */

/*
 * Attaches a place — a LocalBusiness, an Organization, a Place — as what the
 * home is about.
 *
 * Five files learn it, and every one of them by reference:
 *   places/<area>/<slug>/index.json   the place itself: the ONLY copy of the data
 *   index/index.json                  mainEntity -> its @id; isPartOf.publisher too
 *   about/index.json                  mainEntity -> its @id, when the page exists
 *   locations/index.json              the first location -> its @id
 *   ws_headings.json                  what the header and the footer print
 *
 * Only the first holds anything. That is the point: an address appears once in
 * a site, and the four others say where to look. The day it changes, it
 * changes in one file.
 *
 * $g is what Google returned, already fetched — this function does no network,
 * so it can be run against a saved answer, and re-run without paying again.
 */
function site_attach_main_entity(string $siteId, array $g, array $types, bool $apply): array {
    $rep = site_report();

    $sites = site_list();
    if (!isset($sites[$siteId])) { $rep['errors'][] = "Unknown site: '$siteId'."; return $rep; }
    $site = $sites[$siteId];
    $root = $site['path'];
    if (!$site['locales']) { $rep['errors'][] = "'$siteId' has no language folder."; return $rep; }

    require_once __DIR__ . '/_place-google.php';
    $id = google_place_id_for($g);
    $place = google_place_to_json($g, $id, $types);

    $spec = [
        'id' => $siteId,
        'name' => site_name_of($root, $site['locales']),
        'theme' => $site['theme'],
        'url' => site_url_of($root, $site['locales']),
        'main_entity_id' => $id,
        'main_entity_types' => array_values(array_unique(array_merge(
            [google_place_primary_type($g)], $types
        ))),
    ];

    foreach ($site['locales'] as $locale) {
        /* The place, in every language folder. Its text fields are in the
         * language it was fetched in; the report says so rather than leaving
         * an Italian description sitting in the English site unremarked. */
        site_plan_write($rep, "$root/$locale/$id/index.json", site_json($place), "place $id", $apply);
        site_plan_write($rep, "$root/$locale/locations/index.json", site_locations_json($spec), 'locations', $apply);
        site_plan_write($rep, "$root/$locale/ws_headings.json", site_headings_json($siteId, $locale, $spec), 'headings', $apply);

        site_page_set_main_entity($rep, "$root/$locale/index/index.json", $spec, true, $apply);
        if (is_file("$root/$locale/about/index.json")) {
            site_page_set_main_entity($rep, "$root/$locale/about/index.json", $spec, false, $apply);
        }

        /* The list pages learn WHOSE list they are. Without this, a site that
         * gets its business after its pages keeps an Events page that collects
         * events organised by nobody - which is every site, because the
         * business is chosen after the site exists. */
        foreach (site_page_catalog() as $key => $page) {
            if (($page['main_entity'] ?? '') !== 'itemlist') continue;
            $file = "$root/$locale/$key/index.json";
            if (is_file($file)) site_page_set_list_about($rep, $file, $page, $spec, $apply);
        }
    }

    if (count($site['locales']) > 1) {
        $rep['notes'][] = "Il luogo è stato scritto in tutte le lingue con i testi come Google li ha dati: "
                        . "nome e descrizione vanno tradotti nelle lingue diverse da " . $site['locales'][0] . ".";
    }

    if ($apply && !$rep['errors']) {
        require_once __DIR__ . '/refresh-sitemaps.php';
        foreach ($site['locales'] as $locale) {
            ws_refresh_sitemaps("$root/$locale", true);
        }
        $rep['applied'] = true;
    }
    return $rep;
}

/*
 * Points one page's mainEntity at the entity, leaving everything else in the
 * file alone.
 *
 * The page is read, changed and written back rather than regenerated: by the
 * time a site gets its main entity someone has usually written its home, and a
 * scaffold that overwrites what a person typed is a scaffold nobody uses
 * twice.
 */
function site_page_set_main_entity(array &$rep, string $abspath, array $spec, bool $isHome, bool $apply): void {
    if (!is_file($abspath)) { $rep['errors'][] = "Page not found: $abspath"; return; }
    $data = json_decode((string)@file_get_contents($abspath), true);
    if (!is_array($data)) { $rep['errors'][] = "Page is not readable JSON: $abspath"; return; }

    if ($isHome) {
        $data['mainEntity'] = site_main_entity_node($spec);
        /* The WebSite is published BY the business: that is the edge between
         * the two, and the reason they are two nodes and not one. */
        if (isset($data['isPartOf']) && is_array($data['isPartOf'])) {
            $data['isPartOf']['publisher'] = ['@id' => $spec['main_entity_id']];
        }
    } else {
        $data['mainEntity'] = ['@id' => $spec['main_entity_id']];
    }
    $data['dateModified'] = date('c');

    site_plan_write($rep, $abspath, site_json($data), 'mainEntity of ' . basename(dirname($abspath)), $apply);
}

/*
 * Points a list page's `about` at the entity, leaving the rest of the page
 * alone — the same care as the home: by now someone may have written it.
 *
 * The item type is not taken from the catalog alone: the offer page was told
 * at creation whether it lists Services or Products, and that choice lives in
 * the page's own `about` if it has one. Reading it back means the attach does
 * not quietly turn a product catalogue into a list of services.
 */
function site_page_set_list_about(array &$rep, string $abspath, array $page, array $spec, bool $apply): void {
    $data = json_decode((string)@file_get_contents($abspath), true);
    if (!is_array($data)) { $rep['errors'][] = "Page is not readable JSON: $abspath"; return; }

    $known = $data['mainEntity']['about']['@type'] ?? '';
    $spec['offer_type'] = is_string($known) && $known !== '' ? $known : ($spec['offer_type'] ?? '');

    $about = site_list_about($page, $spec);
    if (!$about) {
        /* Only reachable for an offer page whose kind was never chosen — an
         * older site. Left as it is, and said so, rather than guessed. */
        $rep['notes'][] = basename(dirname($abspath)) . ": non so che cosa elenca (Service o Product): "
                        . "scrivilo in mainEntity.about e riesegui l'abbinamento.";
        return;
    }

    $data['mainEntity']['about'] = $about;
    $data['dateModified'] = date('c');
    site_plan_write($rep, $abspath, site_json($data), 'about of ' . basename(dirname($abspath)), $apply);
}
