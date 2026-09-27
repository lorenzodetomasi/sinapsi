<?php
/**
 * WS Glossary, the PHP side: what the page template, the EPUB and the admin
 * module share. The browser side is glossary.js. Where the two do the same
 * thing they are twins and say so, and the lists they share must stay equal:
 *   the markup whitelist   GLOSSARY_INLINE/_BLOCK/_DROP  <->  INLINE/BLOCK/DROP
 *   the .po reader         glossary_parse_po()           <->  parsePo()
 *   the model              glossary_model()              <->  model()
 *   slugs and anchors      glossary_slug/_anchor()       <->  slug/anchor()
 *
 * glossary_model() reads the compacted form the glossary tools write (see
 * README.md); the browser's reader also tolerates hand-written variants (a
 * @graph, "schema:" prefixes), this one needs not: what it reads has passed
 * through the admin module.
 */

const GLOSSARY_FILE = 'glossary.jsonld';
const GLOSSARY_MAX_BYTES = 8 * 1024 * 1024;

const GLOSSARY_INLINE = ['em', 'strong', 'i', 'b', 'sup', 'sub', 'q', 'cite', 'abbr', 'dfn',
    'small', 'mark', 's', 'u', 'code', 'kbd', 'bdi', 'br'];
const GLOSSARY_BLOCK = ['p', 'ul', 'ol', 'li', 'blockquote'];
const GLOSSARY_DROP = ['script', 'style', 'template', 'iframe', 'object', 'embed', 'noscript',
    'svg', 'math', 'head', 'title', 'textarea', 'select', 'button', 'form', 'input'];

/* ---- Interface strings -------------------------------------------------------
 * ws-custom/languages/glossary-<locale>.po, read directly: the admin module is
 * not a CMS page and has no __(), and the page template has to hand the whole
 * catalogue to the browser anyway. */

/** 'it' / 'it-IT' / 'it_IT' -> 'it_IT'. */
function glossary_locale(string $lang): string
{
    return preg_match('/^([a-z]{2,3})(?:[-_]([A-Za-z]{2}))?/', $lang, $m) ? $m[1] . '_' . strtoupper($m[2] ?? $m[1]) : 'it_IT';
}

/** The catalogue of a locale; with an argument it also becomes the one glossary_t() uses. */
function glossary_catalogue(?string $locale = null): array
{
    static $cache = [], $current = 'it_IT';
    if ($locale !== null) $current = preg_replace('/[^A-Za-z_]/', '', $locale);
    if (!isset($cache[$current])) {
        $file = __DIR__ . '/../../../languages/glossary-' . $current . '.po';
        $cache[$current] = is_file($file) ? glossary_parse_po((string)file_get_contents($file)) : [];
    }
    return $cache[$current];
}

/** A .po file as msgid => msgstr (or => [forms]); a msgctxt is joined by "\x04". Twin of parsePo() in glossary.js. */
function glossary_parse_po(string $po): array
{
    $out = [];
    $cur = ['msgid' => '', 'forms' => []];
    $field = null;
    $unq = static fn(string $s) => stripcslashes(substr($s, 1, -1));
    $flush = static function () use (&$out, &$cur, &$field) {
        if ($cur['msgid'] !== '') {
            $key = (isset($cur['msgctxt']) ? $cur['msgctxt'] . "\x04" : '') . $cur['msgid'];
            $out[$key] = isset($cur['plural']) ? $cur['forms'] : ($cur['forms'][0] ?? '');
        }
        $cur = ['msgid' => '', 'forms' => []];
        $field = null;
    };
    foreach (preg_split('/\R/', $po) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') { if ($line === '' && $field) $flush(); continue; }
        if (preg_match('/^msgctxt\s+(".*")$/', $line, $m)) { if ($field && $field !== 'msgctxt') $flush(); $cur['msgctxt'] = $unq($m[1]); $field = 'msgctxt'; }
        elseif (preg_match('/^msgid\s+(".*")$/', $line, $m)) { if ($field && $field !== 'msgctxt') $flush(); $cur['msgid'] = $unq($m[1]); $field = 'msgid'; }
        elseif (preg_match('/^msgid_plural\s+(".*")$/', $line, $m)) { $cur['plural'] = $unq($m[1]); $field = 'msgid_plural'; }
        elseif (preg_match('/^msgstr(?:\[(\d+)\])?\s+(".*")$/', $line, $m)) { $k = (int)($m[1] ?: 0); $cur['forms'][$k] = $unq($m[2]); $field = 'msgstr' . $k; }
        elseif (preg_match('/^(".*")$/', $line, $m) && $field) {
            $s = $unq($m[1]);
            if ($field === 'msgctxt') $cur['msgctxt'] .= $s;
            elseif ($field === 'msgid') $cur['msgid'] .= $s;
            elseif ($field === 'msgid_plural') $cur['plural'] .= $s;
            else { $k = (int)substr($field, 6); $cur['forms'][$k] = ($cur['forms'][$k] ?? '') . $s; }
        }
    }
    $flush();
    return $out;
}

function glossary_t(string $msgid, ...$args): string
{
    $s = glossary_catalogue()[$msgid] ?? '';
    $s = is_string($s) && $s !== '' ? $s : $msgid;
    return $args ? vsprintf($s, $args) : $s;
}

/** With a context, as gettext's pgettext. */
function glossary_tx(string $context, string $msgid, ...$args): string
{
    $s = glossary_catalogue()[$context . "\x04" . $msgid] ?? '';
    $s = is_string($s) && $s !== '' ? $s : $msgid;
    return $args ? vsprintf($s, $args) : $s;
}

/** Plural: glossary_tn('%d entry', '%d entries', $n) - $n fills the placeholder unless other arguments follow. */
function glossary_tn(string $single, string $plural, int $n, ...$args): string
{
    $s = glossary_catalogue()[$single] ?? null;
    $i = $n === 1 ? 0 : 1;
    $chosen = is_array($s) && ($s[$i] ?? '') !== '' ? $s[$i] : ($i ? $plural : $single);
    return vsprintf($chosen, $args ?: [$n]);
}

/* ---- Content markup ------------------------------------------------------------ */

/**
 * A description or a piece of matter reduced to the allowed elements, as
 * well-formed XHTML (the EPUB needs it). Twin of sanitize() in glossary.js.
 */
function glossary_sanitize(?string $html, bool $blocks = false): string
{
    if ($html === null || $html === '') return '';
    $allowed = $blocks ? array_merge(GLOSSARY_INLINE, GLOSSARY_BLOCK) : GLOSSARY_INLINE;
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"?><body>' . $html . '</body>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    $body = $doc->getElementsByTagName('body')->item(0);
    return $body ? glossary_sanitize_children($body, $allowed) : '';
}

function glossary_sanitize_children(DOMNode $from, array $allowed): string
{
    $out = '';
    foreach ($from->childNodes as $n) {
        if ($n instanceof DOMText) { $out .= glossary_x($n->data); continue; }
        if (!$n instanceof DOMElement) continue;
        $tag = strtolower($n->tagName);
        if (in_array($tag, GLOSSARY_DROP, true)) continue;
        if (!in_array($tag, $allowed, true)) { $out .= glossary_sanitize_children($n, $allowed); continue; }
        $attrs = '';
        $lang = $n->getAttribute('lang');
        if ($lang !== '' && preg_match('/^[A-Za-z]{2,3}(-[A-Za-z0-9]{1,8})*$/', $lang)) $attrs .= ' lang="' . $lang . '" xml:lang="' . $lang . '"';
        if (($tag === 'abbr' || $tag === 'dfn') && $n->getAttribute('title') !== '') $attrs .= ' title="' . glossary_x($n->getAttribute('title')) . '"';
        $out .= $tag === 'br' ? "<br$attrs/>" : "<$tag$attrs>" . glossary_sanitize_children($n, $allowed) . "</$tag>";
    }
    return $out;
}

/** Text for XHTML: escaped for XML, never an HTML-only entity. */
function glossary_x(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* ---- Names --------------------------------------------------------------------------- */

function glossary_fold(string $s): string
{
    $s = class_exists('Normalizer') ? Normalizer::normalize($s, Normalizer::FORM_KD) : $s;
    return mb_strtolower(preg_replace('/\p{Mn}+/u', '', (string)$s));
}

/** Twin of slug() in glossary.js. */
function glossary_slug(string $s): string
{
    $s = trim(preg_replace('/[^a-z0-9]+/', '-', glossary_fold($s)), '-');
    return $s === '' ? 'x' : $s;
}

/** An @id as an HTML id: "#kundalini" -> "kundalini". Twin of anchor() in glossary.js. */
function glossary_anchor(string $id): string
{
    $h = strrpos($id, '#');
    return glossary_slug($h === false ? $id : substr($id, $h + 1));
}

function glossary_text($v): string
{
    if (is_array($v)) {
        if (array_is_list($v)) return glossary_text($v[0] ?? '');
        return (string)($v['@value'] ?? $v['name'] ?? $v['@id'] ?? '');
    }
    return (string)$v;
}

/** The names of one person or organisation, or of a list of them. */
function glossary_names($v): array
{
    $list = is_array($v) && !array_is_list($v) ? [$v] : (array)$v;
    $out = [];
    foreach ($list as $a) {
        $n = is_array($a) ? glossary_text($a['name'] ?? '') : (string)$a;
        if ($n !== '') $out[] = $n;
    }
    return $out;
}

/* ---- The model ----------------------------------------------------------------------- */

/**
 * The glossary as the EPUB and the page want it. Twin of model() in glossary.js:
 * terms sorted by name in the glossary's language, each with its letter, its
 * references split into entries and themes; the subsets (categories,
 * selections) with their members; paths, tables, front and back matter.
 */
function glossary_model(array $g): array
{
    $lang = glossary_text($g['inLanguage'] ?? '') ?: 'it';
    $terms = [];
    foreach ((array)($g['hasDefinedTerm'] ?? []) as $t) {
        if (!is_array($t) || trim(glossary_text($t['name'] ?? '')) === '') continue;
        $name = glossary_text($t['name']);
        $id = (string)($t['@id'] ?? '#' . glossary_slug($name));
        $first = strtoupper(substr(preg_replace('/^[^a-z0-9]+/', '', glossary_fold($name)), 0, 1));
        $terms[$id] = [
            'id' => $id, 'anchor' => glossary_anchor($id), 'name' => $name,
            'letter' => preg_match('/^[A-Z]$/', $first) ? $first : '#',
            'label' => glossary_text($t['disambiguatingDescription'] ?? ''),
            'description' => glossary_text($t['description'] ?? ''),
            'related' => array_map(static fn($r) => is_array($r) && isset($r['@id'])
                ? ['id' => (string)$r['@id'], 'name' => '']
                : ['id' => '', 'name' => glossary_text($r)], array_values((array)($t['skos:related'] ?? []))),
            'properties' => array_map(static fn($p) => ['name' => glossary_text($p['name'] ?? ''), 'value' => glossary_text($p['value'] ?? '')],
                array_values((array)($t['ws:additionalProperty'] ?? []))),
            'sets' => array_values(array_filter(array_map(static fn($r) => is_array($r) ? (string)($r['@id'] ?? '') : '', (array)($t['inDefinedTermSet'] ?? [])))),
        ];
    }
    $collator = class_exists('Collator') ? new Collator($lang) : null;
    if ($collator) $collator->setStrength(Collator::SECONDARY);
    uasort($terms, static fn($a, $b) => $collator ? $collator->compare($a['name'], $b['name']) : strcmp(glossary_fold($a['name']), glossary_fold($b['name'])));

    $m = [
        'lang' => $lang, 'id' => (string)($g['@id'] ?? ''),
        'name' => glossary_text($g['name'] ?? ''), 'alternateName' => glossary_text($g['alternateName'] ?? ''),
        'description' => glossary_text($g['description'] ?? ''), 'note' => glossary_text($g['disambiguatingDescription'] ?? ''),
        'abstract' => glossary_text($g['abstract'] ?? ''),
        'series' => array_values(array_filter([glossary_text($g['isPartOf']['alternativeHeadline'] ?? ''), glossary_text($g['isPartOf']['name'] ?? '')])),
        'authors' => glossary_names($g['author'] ?? []),
        'copyrightHolder' => glossary_text($g['copyrightHolder'] ?? ''), 'copyrightYear' => glossary_text($g['copyrightYear'] ?? ''),
        'creditText' => glossary_text($g['creditText'] ?? ''), 'license' => glossary_text($g['license'] ?? ''),
        'version' => glossary_text($g['version'] ?? ''), 'datePublished' => glossary_text($g['datePublished'] ?? ''),
        'dateModified' => glossary_text($g['dateModified'] ?? ''), 'image' => glossary_text($g['image'] ?? ''),
        'keywords' => array_values(array_map('glossary_text', (array)($g['keywords'] ?? []))),
        // Every entry's name, also after glossary_model_select(): a reference to
        // an entry left out still reads as its name.
        'names' => array_map(static fn($t) => $t['name'], $terms),
        'terms' => $terms, 'categories' => [], 'selections' => [], 'paths' => [], 'tables' => [], 'front' => [], 'back' => [],
    ];
    foreach ((array)($g['hasPart'] ?? []) as $p) {
        if (!is_array($p)) continue;
        $types = (array)($p['@type'] ?? []);
        $pid = (string)($p['@id'] ?? '');
        $name = glossary_text($p['name'] ?? '');
        if (in_array('DefinedTermSet', $types, true)) {
            $role = (string)($p['ws:role'] ?? '');
            $members = [];
            foreach ((array)($p['hasDefinedTerm'] ?? []) as $r) if (isset($terms[$r['@id'] ?? ''])) $members[$r['@id']] = true;
            foreach ($terms as $tid => $t) if (in_array($pid, $t['sets'], true)) $members[$tid] = true;
            $set = ['id' => $pid, 'anchor' => glossary_anchor($pid), 'name' => $name, 'members' => $members,
                    'color' => preg_match('/^#[0-9A-Fa-f]{3,8}$/', (string)($p['ws:color'] ?? '')) ? $p['ws:color'] : ''];
            if ($role === 'category') $m['categories'][$pid] = $set;
            elseif ($role === 'selection') $m['selections'][$pid] = $set;
        } elseif (in_array('ItemList', $types, true)) {
            $items = [];
            foreach ((array)($p['itemListElement'] ?? []) as $li) {
                $ref = is_array($li) ? ($li['item']['@id'] ?? $li['@id'] ?? '') : '';
                if (isset($terms[$ref]) && !in_array($ref, $items, true)) $items[] = $ref;
            }
            $m['paths'][] = ['id' => $pid, 'anchor' => glossary_anchor($pid), 'name' => $name, 'items' => $items];
        } elseif (in_array('Table', $types, true)) {
            $rows = array_values(array_filter(array_map(static fn($r) => $r['@id'] ?? '', (array)($p['ws:rows'] ?? [])), static fn($r) => isset($terms[$r])));
            $m['tables'][] = ['id' => $pid, 'anchor' => glossary_anchor($pid), 'name' => $name,
                              'columns' => array_values(array_map('glossary_text', (array)($p['ws:columns'] ?? []))), 'rows' => $rows];
        } elseif (isset($p['text'])) {
            $m[($p['ws:placement'] ?? '') === 'back' ? 'back' : 'front'][] = ['id' => $pid, 'anchor' => glossary_anchor($pid), 'name' => $name, 'text' => glossary_text($p['text'])];
        }
    }
    foreach ($m['terms'] as &$t) {
        $t['categories'] = array_values(array_filter(array_keys($m['categories']), static fn($c) => isset($m['categories'][$c]['members'][$t['id']])));
    }
    unset($t);
    return $m;
}

/** Only the entries of one selection: the rest of the glossary stays, pointing at what is left. */
function glossary_model_select(array $m, string $selection): array
{
    if (!isset($m['selections'][$selection])) return $m;
    $keep = $m['selections'][$selection]['members'];
    $m['terms'] = array_intersect_key($m['terms'], $keep);
    foreach ($m['paths'] as &$p) $p['items'] = array_values(array_filter($p['items'], static fn($i) => isset($keep[$i])));
    unset($p);
    $m['paths'] = array_values(array_filter($m['paths'], static fn($p) => $p['items']));
    foreach ($m['tables'] as &$tb) $tb['rows'] = array_values(array_filter($tb['rows'], static fn($i) => isset($keep[$i])));
    unset($tb);
    $m['tables'] = array_values(array_filter($m['tables'], static fn($tb) => $tb['rows']));
    $m['selection'] = $m['selections'][$selection];
    return $m;
}

/** The letters of the glossary, in order, each with its terms. */
function glossary_letters(array $m): array
{
    $out = [];
    foreach ($m['terms'] as $id => $t) $out[$t['letter']][$id] = $t;
    return $out;
}

/* ---- One entry, as XHTML ------------------------------------------------------------
 * The EPUB's markup, and the page's <noscript>. $href maps an entry id to its
 * link, or '' when the entry is not in this document (then the name is text). */
function glossary_entry_xhtml(array $m, array $t, callable $href, bool $epub = false): string
{
    $x = '';
    $x .= '<dt id="' . glossary_x($t['anchor']) . '"' . ($epub ? ' epub:type="glossterm"' : '') . '><dfn>' . glossary_x($t['name']) . '</dfn>';
    if ($t['label'] !== '') $x .= ' <span class="label">' . glossary_x($t['label']) . '</span>';
    $x .= "</dt>\n<dd" . ($epub ? ' epub:type="glossdef"' : '') . '>';
    if ($t['description'] !== '') $x .= '<p class="def">' . glossary_sanitize($t['description']) . '</p>';
    if ($t['properties']) {
        $x .= '<dl class="props">';
        foreach ($t['properties'] as $p) $x .= '<dt>' . glossary_x($p['name']) . '</dt><dd>' . glossary_sanitize($p['value']) . '</dd>';
        $x .= '</dl>';
    }
    if ($t['related']) {
        $links = [];
        foreach ($t['related'] as $r) {
            if ($r['id'] !== '' && ($target = $m['terms'][$r['id']] ?? null) && ($to = $href($r['id'])) !== '') {
                $links[] = '<a href="' . glossary_x($to) . '">' . glossary_x($target['name']) . '</a>';
            } else {
                $label = $r['id'] !== '' ? ($m['names'][$r['id']] ?? $r['name']) : $r['name'];
                if ($label !== '') $links[] = '<span class="theme">' . glossary_x($label) . '</span>';
            }
        }
        if ($links) $x .= '<p class="see"><span class="see-label">' . glossary_x(glossary_t('See also')) . '</span> ' . implode('; ', $links) . '</p>';
    }
    return $x . "</dd>\n";
}
