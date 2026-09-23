<?php
/**
 * One page: its hand-written index.wsx → index.json (JSON-LD), and from
 * that its XML twin.
 *
 * The unit behind migrate-pages.php. A library, not a page: prints nothing.
 *
 * The old format is XML with a `section` (or `person`, `location`…) root,
 * the CMS's fields as elements, HTML in CDATA, and XInclude in two roles:
 *  - a FRAGMENT of another file (`xpointer=…`: the brand's name inside the
 *    title, the cover image, the description) - resolved here, once, into
 *    literal values, because JSON cannot hold "half a string is elsewhere";
 *  - a WHOLE shared file (the clients, the awards) - kept as a reference,
 *    `"xi:include": {"@href": …}`, resolved by the CMS at request time.
 *
 * What the new page says (see ws-admin/README.md):
 *  - @context and @type, general to specific: every page is a WebPage;
 *    CollectionPage when pages stand under it; then the old `type` - as a
 *    schema.org page type when it is one (AboutPage, ContactPage), as a
 *    role in `additionalType` when it is not (PrivacyPage, CookiesPage: what
 *    the legal menu and the forms look up);
 *  - @id, the content path;
 *  - the CMS's routing keys as they were (wspath, query, parent…) - but no
 *    `type`: it is derived from @type and mainEntity wherever it is read;
 *  - for a page ABOUT something (the old `type` was Service, Person…), a
 *    mainEntity of that type from what the page already says (name,
 *    headline, description, path) - to be enriched by hand, as Brand
 *    Design was;
 *  - `output: ["html"]` in place of `<htmlcache>true</htmlcache>`;
 *  - attributes as `@name`, and `id` as `@xml:id` (in JSON `@id` means a
 *    reference, not an anchor);
 *  - sections as an array; the old `<grid id=…>` becomes a section of class
 *    `grid`, with the name and xpath the template needs, taken from what
 *    the home page says for the same list;
 *
 * Comments are dropped and counted; empty elements are dropped; every
 * decision that a reader should check is in the report's notes.
 *
 * @package WS
 * @subpackage Admin
 */

require_once __DIR__ . '/_refresh-content.php';

if (!function_exists('ws_migrate_page')) {

    /** The names of the shared lists, and what a grid over each of them says. */
    function ws_migrate_grid_defaults(string $href): array {
        $base = basename($href);
        $known = [
            'clients.wsx' => ['name' => 'Abbiamo progettato molti loghi',
                              'xpath' => "itemList/itemListElement[contains(concat(' ', normalize-space(@class), ' '), ' logo-design ')]"],
            'awards.wsx'  => ['name' => 'I premi che abbiamo ricevuto',
                              'xpath' => "itemList/itemListElement[contains(concat(' ', normalize-space(@class), ' '), ' main ')]"],
        ];
        return $known[$base] ?? ['name' => '', 'xpath' => 'itemList/itemListElement'];
    }

    /** XHTML element names: a child that is one of these makes its parent rich text, not data. */
    function ws_migrate_is_xhtml(string $name): bool {
        static $set = null;
        if ($set === null) {
            $set = array_fill_keys(['a','abbr','address','article','aside','b','blockquote','br','cite','code','dd','del','details',
                'dfn','div','dl','dt','em','figcaption','footer','h1','h2','h3','h4','h5','h6','header','hr','i','img','ins',
                'kbd','li','main','mark','nav','ol','p','pre','q','s','samp','small','span','strong','sub','summary','sup',
                'table','tbody','td','tfoot','th','thead','time','tr','u','ul','var','wbr'], true);
        }
        return isset($set[$name]);
    }

    /** The inner XML of an element, as the CMS's innerHTML() would give it. */
    function ws_migrate_inner(DOMElement $el): string {
        $s = '';
        foreach ($el->childNodes as $n) {
            $s .= ($n instanceof DOMText) ? $n->textContent : $n->ownerDocument->saveXML($n);
        }
        return trim($s);
    }

    /** HTML as it was written, without the indentation of the file around it. */
    function ws_migrate_html(string $s): string {
        $lines = preg_split('/\r?\n/', trim($s));
        return implode("\n", array_map(fn($l) => ltrim($l, " \t"), $lines));
    }

    /** Whitespace collapsed, ends trimmed: what a title or a name should be. */
    function ws_migrate_text(string $s): string {
        return trim(preg_replace('/\s+/u', ' ', $s));
    }

    /**
     * An element to its JSON value. Attributes become `@name`; text-only
     * elements a string; elements with element children an object (repeated
     * names → array); an element with XHTML inside, a string of that HTML.
     * Returns null for an element with nothing in it.
     */
    function ws_migrate_node(DOMElement $el, array &$notes) {
        $out = [];
        foreach ($el->attributes as $a) {
            $name = $a->nodeName;
            if ($name === 'id' || $name === 'xml:id') $name = 'xml:id';
            if (strpos($name, 'xmlns') === 0) continue;
            $out['@' . $name] = $a->nodeValue;
        }
        $elements = []; $text = ''; $xhtml = false;
        foreach ($el->childNodes as $n) {
            if ($n instanceof DOMElement) {
                $elements[] = $n;
                if (ws_migrate_is_xhtml($n->localName)) $xhtml = true;
            } elseif ($n instanceof DOMText) {          // DOMCdataSection is a DOMText
                $text .= $n->textContent;
            } elseif ($n instanceof DOMComment) {
                $notes['comments'] = ($notes['comments'] ?? 0) + 1;
            }
        }
        if (!$elements) {
            $t = trim($text);
            if ($t === '' && !$out) return null;
            if ($t === '') return $out ?: null;
            // Prose keeps its lines but loses the indentation of the file it
            // sat in; a name, a title, a path is one line.
            $value = (strpos($t, '<') !== false) ? ws_migrate_html($t) : ws_migrate_text($t);
            if (!$out) return $value;
            $out['#text'] = $value;
            return $out;
        }
        if ($xhtml) {
            // Rich text. An element that also carried attributes keeps them.
            $html = ws_migrate_html(ws_migrate_inner($el));
            if (!$out) return $html;
            $out['#text'] = $html;
            return $out;
        }
        $leftover = trim($text);
        if ($leftover !== '') $out['#text'] = ws_migrate_text($leftover);
        foreach ($elements as $child) {
            // `ws-include` is the placeholder ws_migrate_page() puts where a whole-file include stood.
            $isInclude = ($child->namespaceURI === 'http://www.w3.org/2001/XInclude' || $child->nodeName === 'xi:include' || $child->nodeName === 'ws-include');
            $key = $isInclude ? 'xi:include' : $child->nodeName;
            $value = ws_migrate_node($child, $notes);
            if ($value === null) { $notes['dropped'][] = $child->nodeName; continue; }
            if (isset($out[$key])) {
                if (!is_array($out[$key]) || !array_is_list($out[$key])) $out[$key] = [$out[$key]];
                $out[$key][] = $value;
            } else {
                $out[$key] = $value;
            }
        }
        return $out ?: null;
    }

    /**
     * Migrates (or says how it would migrate) one page.
     *
     * @param string $wsx_abspath  the page's index.wsx
     * @param bool   $apply        false = preview: the JSON is built and
     *                             returned, nothing is written
     * @param array  $options      'collection' => true when pages stand
     *                             under this one (→ CollectionPage);
     *                             'brand' => ['name' => …, 'url' => …] for
     *                             the provider of a Service page
     * @return array  status: migrated | would | skipped | failed; json (the
     *                document, as an array); notes; source/target (relative
     *                to the root); why
     */
    function ws_migrate_page(string $wsx_abspath, bool $apply, array $options = []): array {
        $root = ws_derived_root($wsx_abspath);
        $dir = dirname($wsx_abspath);
        $json_abspath = "$dir/index.json";
        $out = [
            'status' => 'failed', 'json' => null, 'notes' => [], 'why' => '',
            'source' => $root ? ws_derived_rel($root, $wsx_abspath) : $wsx_abspath,
            'target' => $root ? ws_derived_rel($root, $json_abspath) : $json_abspath,
        ];
        if ($root === '') { $out['why'] = 'not under a contents tree'; return $out; }
        if (is_file($json_abspath)) { $out['status'] = 'skipped'; $out['why'] = 'index.json already there'; return $out; }

        $prev = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = true;
        if (!$dom->load($wsx_abspath)) {
            libxml_clear_errors(); libxml_use_internal_errors($prev);
            $out['why'] = 'unreadable XML'; return $out;
        }
        $x = new DOMXPath($dom);
        $x->registerNamespace('xi', 'http://www.w3.org/2001/XInclude');
        if (trim($x->evaluate('string(/*/wspath)')) === '') {
            libxml_use_internal_errors($prev);
            $out['status'] = 'skipped'; $out['why'] = 'no wspath: a list, not a page'; return $out;
        }

        // 1. Whole-file includes stay references: set them aside before
        //    resolving, as elements XInclude does not know.
        $kept = 0;
        foreach (iterator_to_array($x->query('//xi:include[not(@xpointer)]')) as $inc) {
            $ph = $dom->createElement('ws-include');
            $ph->setAttribute('href', $inc->getAttribute('href'));
            $inc->parentNode->replaceChild($ph, $inc);
            $kept++;
        }
        // 2. Fragment includes become literal values, once.
        $frags = $x->query('//xi:include[@xpointer]')->length;
        if ($frags) {
            $dom->xinclude();
            foreach (libxml_get_errors() as $e) $out['notes'][] = 'include not resolved: ' . trim($e->message);
            libxml_clear_errors();
            $out['notes'][] = "$frags fragment include(s) resolved into literal values (title, name, description, cover…)";
            // xinclude() leaves xml:base attributes on what it brought in: not content.
            foreach (iterator_to_array($x->query('//@xml:base')) as $a) $a->ownerElement->removeAttributeNode($a);
        }
        libxml_use_internal_errors($prev);
        if ($kept) $out['notes'][] = "$kept whole-file include(s) kept as references";

        // 3. The tree to JSON.
        $notes = [];
        $data = ws_migrate_node($dom->documentElement, $notes) ?: [];
        if (!empty($notes['comments'])) $out['notes'][] = $notes['comments'] . ' comment(s) dropped';
        if (!empty($notes['dropped'])) $out['notes'][] = 'empty element(s) dropped: ' . implode(', ', array_unique($notes['dropped']));
        unset($data['@xmlns:xi']);

        // 4. The page's own shape: what it IS (@type, general to specific) and
        //    what it is ABOUT (mainEntity). The old `type` said one or the
        //    other without saying which; it is not kept.
        $wspath = (string)($data['wspath'] ?? '');
        $type = trim((string)($data['type'] ?? ''));
        unset($data['type']);
        $schemaPages = ['AboutPage', 'CheckoutPage', 'CollectionPage', 'ContactPage', 'FAQPage', 'ItemPage', 'MedicalWebPage',
                        'ProfilePage', 'QAPage', 'RealEstateListing', 'SearchResultsPage', 'WebPage'];
        $types = ['WebPage'];
        if (!empty($options['collection']) || $type === 'CollectionPage') $types[] = 'CollectionPage';
        $entityType = '';
        $role = '';
        $isHome = ($type === 'Index' || rtrim($wspath, '/') === '');
        if ($type === '' || $type === 'WebPage' || $type === 'CollectionPage' || $type === 'Page' || $isHome) {
            // A page and nothing more. `Index` named the home: schema.org has
            // no such type, and the home is the page at "/" that is ABOUT the
            // site - its mainEntity is the WebSite, as Meetoo's map already
            // says (<type>WebSite</type>).
            if ($isHome) $entityType = 'WebSite';
        } elseif (in_array($type, $schemaPages, true)) {
            $types[] = $type;
        } elseif (substr($type, -4) === 'Page') {
            /* A role the CMS looks up and schema.org has no word for. It goes
             * in `additionalType` - schema.org's OWN way of naming a type from
             * another vocabulary - and not in `@type` with a `ws:` prefix, so
             * that every KEY of a page stays a schema.org key and only the
             * value is ours. The site map reads `additionalType` first, before
             * the mainEntity and before the page's own types.
             *
             * This wrote `ws:$type` inside `@type` until the pages module
             * settled the question the other way. Both still work - the map
             * strips any prefix - but two ways of saying one thing is how a
             * codebase ends up with a rule nobody can state. */
            $role = $type;
            $out['notes'][] = "$type is not a schema.org type: kept as the page's role, additionalType $type";
        } else {
            $entityType = $type;
        }
        $id = ws_derived_rel($root, $dir);
        $doc = [
            '@context' => 'https://schema.org',
            '@type' => count($types) === 1 ? $types[0] : $types,
            '@id' => $id,
        ];
        if ($role !== '') $doc['additionalType'] = $role;

        // The query's htmlcache attribute and the htmlcache element say one
        // thing: which outputs the page wants.
        $output = [];
        if (is_array($data['query'] ?? null)) {
            if (!empty($data['query']['@htmlcache'])) $output[] = 'html';
            $data['query'] = $data['query']['#text'] ?? '';
        }
        if (isset($data['htmlcache'])) {
            if (trim((string)$data['htmlcache']) === 'true') $output[] = 'html';
            unset($data['htmlcache']);
        }

        // Sections: the old <grid> is a section of class grid; every section
        // goes into one array, in document order.
        $sections = [];
        foreach (['section', 'grid'] as $k) {
            if (!isset($data[$k])) continue;
            $list = (is_array($data[$k]) && array_is_list($data[$k])) ? $data[$k] : [$data[$k]];
            foreach ($list as $s) {
                if (!is_array($s)) $s = ['#text' => (string)$s];
                if ($k === 'grid') {
                    $s['@class'] = trim('grid ' . ($s['@class'] ?? ''));
                    $href = is_array($s['xi:include'] ?? null) ? (string)($s['xi:include']['@href'] ?? '') : '';
                    $def = ws_migrate_grid_defaults($href);
                    if (empty($s['name']))  { $s['name']  = $def['name'];  if ($def['name'] === '') $out['notes'][] = "grid over $href: give it a name"; }
                    if (empty($s['xpath'])) { $s['xpath'] = $def['xpath']; }
                    $out['notes'][] = "<grid> over $href became a section of class grid" . ($def['name'] !== '' ? " named «{$def['name']}»" : '');
                }
                // Keys in the order the templates read them: anchor, class, name, xpath, include, text.
                $ordered = [];
                foreach (['@xml:id', '@class', 'name', 'xpath', 'xi:include', '#text'] as $kk) if (isset($s[$kk])) $ordered[$kk] = $s[$kk];
                $sections[] = $ordered + $s;
            }
            unset($data[$k]);
        }

        // A page about something says what, from what it already says.
        $mainEntity = null;
        if ($entityType !== '') {
            $brand = $options['brand'] ?? ['name' => 'ISOTYPE.ORG', 'url' => 'https://www.isotype.org/'];
            $mainEntity = ['@type' => $entityType, '@id' => "$id#" . strtolower($entityType)];
            if (!empty($data['name'])) $mainEntity['name'] = ws_migrate_text(strip_tags((string)$data['name']));
            if ($entityType === 'WebSite') {
                // The site: its name, its address, its language - what the headings say of it.
                $mainEntity['url'] = $brand['url'];
                if (!empty($data['inLanguage'])) $mainEntity['inLanguage'] = (string)$data['inLanguage'];
                if (!empty($data['description']) && is_string($data['description'])) $mainEntity['description'] = ws_migrate_text(strip_tags($data['description']));
                $out['notes'][] = 'the home is about the site: mainEntity WebSite';
            } else {
            if ($entityType === 'Service' && !empty($data['headline']) && is_string($data['headline'])) $mainEntity['slogan'] = ws_migrate_text(strip_tags($data['headline']));
            if (!empty($data['description']) && is_string($data['description'])) $mainEntity['description'] = ws_migrate_text(strip_tags($data['description']));
            $mainEntity['url'] = $wspath;
            if ($entityType === 'Service') {
                if (!empty($mainEntity['name'])) $mainEntity['serviceType'] = $mainEntity['name'];
                $mainEntity['areaServed'] = ['@type' => 'Country', 'name' => 'Italia'];
                $mainEntity['provider'] = ['@type' => 'Organization', 'name' => $brand['name'], 'url' => $brand['url']];
            }
            $out['notes'][] = "mainEntity $entityType written from name, headline and description: enrich it";
            }
        }

        // 5. Assemble, in a readable order: routing, SEO, dates, content, entity, sections.
        $order = ['wspath', 'query', 'parent', 'inLanguage', 'workTranslation', 'title', 'description', 'keywords',
                  'changefreq', 'priority', 'robots', 'dateCreated', 'datePublished', 'dateModified',
                  'name', 'headline', 'alternateName', 'cta', 'primaryImageOfPage', 'mainContentOfPage'];
        foreach ($order as $k) if (isset($data[$k])) { $doc[$k] = $data[$k]; unset($data[$k]); }
        if ($output) $doc['output'] = array_values(array_unique($output));
        if ($mainEntity) $doc['mainEntity'] = $mainEntity;
        // Whatever else the page carried (offers, awards, addresses…) follows as it was.
        foreach ($data as $k => $v) {
            if (strpos($k, '@') === 0) continue;
            $doc[$k] = $v;
            $out['notes'][] = "kept as it was: <$k>";
        }
        if ($sections) $doc['section'] = $sections;
        $out['json'] = $doc;

        if (!$apply) { $out['status'] = 'would'; return $out; }

        // 6. Write the JSON, make the twin (adopting the new root: the old XML
        //    had a <section> root), set the old source aside as `-index.wsx`.
        $json = json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || @file_put_contents($json_abspath, $json . "\n", LOCK_EX) === false) {
            $out['why'] = 'cannot write index.json'; return $out;
        }
        $twin = ws_refresh_content($json_abspath, true, ['adopt_root' => true, 'force' => true]);
        if (!in_array($twin['status'], ['rebuilt', 'created'], true)) {
            $out['why'] = 'JSON written, twin not made: ' . $twin['why']; return $out;
        }
        if (!@rename($wsx_abspath, "$dir/-index.wsx")) {
            $out['notes'][] = 'could not rename index.wsx to -index.wsx: do it by hand';
        } else {
            $out['notes'][] = 'index.wsx set aside as -index.wsx';
        }
        $out['status'] = 'migrated';
        return $out;
    }
}
?>
