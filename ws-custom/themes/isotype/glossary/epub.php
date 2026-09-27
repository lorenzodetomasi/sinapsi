<?php
/**
 * A glossary as an EPUB 3 book.
 *
 * One book per reading: the whole glossary, and one for each selection (the
 * "essential" 95 entries of the Sahaja Yoga glossary) - on a page one filters,
 * a book is read from the first page to the last, so the choice between a
 * short and a long reading is a choice between two books.
 *
 *   cover.xhtml         when the glossary has an image
 *   title.xhtml         series, title, the reading, subtitle, note, authors
 *   front-N.xhtml       front matter (Premessa, Come usare…)
 *   paths.xhtml         reading paths, linked to the entries
 *   tables.xhtml        tables whose rows are entries
 *   letter-X.xhtml      the entries, one file per letter, as an EPUB glossary
 *                       (dl / dt epub:type="glossterm" / dd epub:type="glossdef")
 *   back-N.xhtml        back matter
 *   colophon.xhtml      rights, credits, version, licence
 *   nav.xhtml, toc.ncx  the table of contents (EPUB 3, and EPUB 2 readers)
 *
 * The same JSON gives the same file: the identifier is derived from the
 * glossary and the reading, the dates from the glossary, not from the clock.
 */

require_once __DIR__ . '/lib.php';

/**
 * The file name of a reading: "sahaja-yoga.epub", "sahaja-yoga-essenziale.epub".
 * From the glossary's folder, not its title: a title changes (v1 was the
 * "essenziale"), and a book renamed with it would leave the old one behind.
 */
function glossary_epub_name(string $dir, string $selection = ''): string
{
    return glossary_slug(basename($dir)) . ($selection !== '' ? '-' . glossary_anchor($selection) : '') . '.epub';
}

/**
 * Build the book of a reading into $out. $dir is the glossary's folder, where
 * relative files (the cover) are found. Returns what went in, for a report.
 */
function glossary_epub(array $g, string $dir, string $out, string $selection = ''): array
{
    $m = glossary_model($g);
    glossary_catalogue(glossary_locale($m['lang']));
    if ($selection !== '') $m = glossary_model_select($m, $selection);
    $lang = glossary_x($m['lang']);
    $modified = glossary_epub_date($m['dateModified'] ?: $m['datePublished'] ?: '2000-01-01');
    $reading = isset($m['selection']) ? $m['selection']['name'] : $m['alternateName'];

    $letters = glossary_letters($m);
    $fileOf = [];
    foreach ($letters as $L => $ts) foreach ($ts as $id => $t) $fileOf[$id] = 'letter-' . glossary_slug($L) . '.xhtml';
    $href = static fn(string $id) => isset($fileOf[$id], $m['terms'][$id]) ? $fileOf[$id] . '#' . $m['terms'][$id]['anchor'] : '';

    $files = [];      // name => [content, media-type, properties]
    $spine = [];      // file names, in reading order
    $toc = [];        // [label, href, children[]]
    $page = static function (string $title, string $body, string $type = '') use ($lang) {
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n<!DOCTYPE html>\n"
            . '<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops" lang="' . $lang . '" xml:lang="' . $lang . "\">\n"
            . '<head><meta charset="UTF-8"/><title>' . glossary_x($title) . '</title><link rel="stylesheet" type="text/css" href="style.css"/></head>' . "\n"
            . '<body' . ($type ? ' epub:type="' . $type . '"' : '') . ">\n" . $body . "\n</body>\n</html>\n";
    };
    $add = static function (string $name, string $content, string $type = 'application/xhtml+xml', string $props = '', bool $inSpine = true) use (&$files, &$spine) {
        $files[$name] = [$content, $type, $props];
        if ($inSpine) $spine[] = $name;
    };

    // Cover
    $cover = '';
    if ($m['image'] !== '' && !preg_match('#^[a-z]+:|\.\.#i', $m['image']) && is_file($dir . '/' . $m['image'])) {
        $ext = strtolower(pathinfo($m['image'], PATHINFO_EXTENSION));
        $mime = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'gif' => 'image/gif'][$ext] ?? '';
        if ($mime) {
            $cover = 'cover.' . ($ext === 'jpeg' ? 'jpg' : $ext);
            $add($cover, (string)file_get_contents($dir . '/' . $m['image']), $mime, 'cover-image', false);
            $add('cover.xhtml', $page(glossary_t('Cover'), '<section class="cover" epub:type="cover"><img src="' . $cover . '" alt="' . glossary_x($m['name']) . '"/></section>', 'frontmatter'));
        }
    }

    // Title page
    $x = '<section class="titlepage" epub:type="titlepage">';
    if ($m['series']) $x .= '<p class="series">' . glossary_x(implode(' · ', $m['series'])) . '</p>';
    $x .= '<h1 class="title">' . glossary_x($m['name']) . '</h1>';
    if ($reading !== '') $x .= '<p class="reading">' . glossary_x($reading) . '</p>';
    if ($m['description'] !== '') $x .= '<p class="subtitle">' . glossary_sanitize($m['description']) . '</p>';
    if ($m['note'] !== '') $x .= '<p class="note">' . glossary_sanitize($m['note']) . '</p>';
    if ($m['authors']) $x .= '<p class="author">' . glossary_x(implode(', ', $m['authors'])) . '</p>';
    $x .= '</section>';
    $add('title.xhtml', $page($m['name'], $x, 'frontmatter'));
    $toc[] = [glossary_t('Title page'), 'title.xhtml', []];

    // Front matter
    foreach ($m['front'] as $i => $p) {
        $name = 'front-' . ($i + 1) . '.xhtml';
        $add($name, $page($p['name'], '<section id="' . glossary_x($p['anchor']) . '"><h1>' . glossary_x($p['name']) . '</h1>' . glossary_sanitize($p['text'], true) . '</section>', 'frontmatter'));
        $toc[] = [$p['name'], $name, []];
    }

    // Reading paths
    if ($m['paths']) {
        $x = '<h1>' . glossary_x(glossary_t('Reading paths')) . '</h1>';
        foreach ($m['paths'] as $p) {
            $x .= '<section class="path" id="' . glossary_x($p['anchor']) . '"><h2>' . glossary_x($p['name']) . '</h2><ol>';
            foreach ($p['items'] as $id) $x .= '<li><a href="' . glossary_x($href($id)) . '">' . glossary_x($m['terms'][$id]['name']) . '</a></li>';
            $x .= '</ol></section>';
        }
        $add('paths.xhtml', $page(glossary_t('Reading paths'), $x));
        $toc[] = [glossary_t('Reading paths'), 'paths.xhtml', array_map(static fn($p) => [$p['name'], 'paths.xhtml#' . $p['anchor'], []], $m['paths'])];
    }

    // Tables
    if ($m['tables']) {
        $x = '';
        foreach ($m['tables'] as $tb) {
            $x .= '<section class="table" id="' . glossary_x($tb['anchor']) . '"><h1>' . glossary_x($tb['name']) . '</h1><table><thead><tr>';
            foreach ($tb['columns'] as $c) $x .= '<th scope="col">' . glossary_x($c) . '</th>';
            $x .= '</tr></thead><tbody>';
            foreach ($tb['rows'] as $id) {
                $t = $m['terms'][$id];
                $x .= '<tr><th scope="row"><a href="' . glossary_x($href($id)) . '">' . glossary_x($t['name']) . '</a></th>';
                foreach (array_slice($tb['columns'], 1) as $c) {
                    $v = '';
                    foreach ($t['properties'] as $p) if ($p['name'] === $c) $v = $p['value'];
                    $x .= '<td>' . glossary_sanitize($v) . '</td>';
                }
                $x .= '</tr>';
            }
            $x .= '</tbody></table></section>';
        }
        $add('tables.xhtml', $page($m['tables'][0]['name'], $x));
        foreach ($m['tables'] as $tb) $toc[] = [$tb['name'], 'tables.xhtml#' . $tb['anchor'], []];
    }

    // The entries, a file per letter
    $letterToc = [];
    $firstLetter = '';
    foreach ($letters as $L => $ts) {
        $name = 'letter-' . glossary_slug($L) . '.xhtml';
        $firstLetter = $firstLetter ?: $name;
        $x = '<section class="letter" epub:type="glossary"><h1>' . glossary_x($L) . "</h1>\n<dl>\n";
        foreach ($ts as $t) $x .= glossary_entry_xhtml($m, $t, $href, true);
        $x .= '</dl></section>';
        $add($name, $page($m['name'] . ' · ' . $L, $x, 'bodymatter'));
        $letterToc[] = [$L, $name, []];
    }
    $toc[] = [$reading !== '' ? $reading : $m['name'], $firstLetter, $letterToc];

    // Back matter
    foreach ($m['back'] as $i => $p) {
        $name = 'back-' . ($i + 1) . '.xhtml';
        $add($name, $page($p['name'], '<section id="' . glossary_x($p['anchor']) . '"><h1>' . glossary_x($p['name']) . '</h1>' . glossary_sanitize($p['text'], true) . '</section>', 'backmatter'));
        $toc[] = [$p['name'], $name, []];
    }

    // Colophon
    $x = '<section class="colophon" epub:type="colophon"><h1>' . glossary_x(glossary_t('Colophon')) . '</h1>';
    $holder = $m['copyrightHolder'] ?: implode(', ', $m['authors']);
    if ($holder !== '') $x .= '<p>© ' . glossary_x(trim($m['copyrightYear'] . ' ' . $holder)) . '</p>';
    if ($m['creditText'] !== '') $x .= '<p>' . glossary_x($m['creditText']) . '</p>';
    if ($m['version'] !== '' && $m['dateModified'] !== '') $x .= '<p>' . glossary_x(glossary_t('Version %1$s, updated on %2$s', $m['version'], glossary_epub_human_date($m['dateModified'], $m['lang']))) . '</p>';
    $x .= glossary_epub_license($m) . '</section>';
    $add('colophon.xhtml', $page(glossary_t('Colophon'), $x, 'backmatter'));
    $toc[] = [glossary_t('Colophon'), 'colophon.xhtml', []];

    // Navigation
    $ol = static function (array $items) use (&$ol) {
        $s = '<ol>';
        foreach ($items as [$label, $to, $children]) $s .= '<li><a href="' . glossary_x($to) . '">' . glossary_x($label) . '</a>' . ($children ? $ol($children) : '') . '</li>';
        return $s . '</ol>';
    };
    $nav = '<nav epub:type="toc" id="toc"><h1>' . glossary_x(glossary_t('Contents')) . '</h1>' . $ol($toc) . '</nav>'
        . '<nav epub:type="landmarks" id="landmarks" hidden=""><ol>'
        . ($cover ? '<li><a epub:type="cover" href="cover.xhtml">' . glossary_x(glossary_t('Cover')) . '</a></li>' : '')
        . '<li><a epub:type="toc" href="nav.xhtml#toc">' . glossary_x(glossary_t('Contents')) . '</a></li>'
        . '<li><a epub:type="bodymatter" href="' . $firstLetter . '">' . glossary_x($reading !== '' ? $reading : $m['name']) . '</a></li></ol></nav>';
    $files['nav.xhtml'] = [$page(glossary_t('Contents'), $nav), 'application/xhtml+xml', 'nav'];
    // After the title page, so that a reader opening the book finds the contents early.
    array_splice($spine, array_search('title.xhtml', $spine, true) + 1, 0, ['nav.xhtml']);
    $files['style.css'] = [(string)file_get_contents(__DIR__ . '/epub.css'), 'text/css', ''];

    $title = $m['name'] . ($reading !== '' ? ' · ' . $reading : '');
    $uuid = glossary_epub_uuid(($m['id'] ?: $m['name']) . '|' . $selection);
    $files['toc.ncx'] = [glossary_epub_ncx($uuid, $title, $toc), 'application/x-dtbncx+xml', ''];
    $opf = glossary_epub_opf($m, $uuid, $reading, $modified, $files, $spine, $cover);

    // The package: mimetype first and stored, as the format asks.
    @unlink($out);
    $zip = new ZipArchive();
    if ($zip->open($out, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException(glossary_t('Cannot write %s.', basename($out)));
    $stamp = strtotime($modified) ?: 0;
    $put = static function (string $name, string $content, bool $store = false) use ($zip, $stamp) {
        $zip->addFromString($name, $content);
        $zip->setCompressionName($name, $store ? ZipArchive::CM_STORE : ZipArchive::CM_DEFLATE);
        $zip->setMtimeName($name, $stamp);
    };
    $put('mimetype', 'application/epub+zip', true);
    $put('META-INF/container.xml', '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles>'
        . '<rootfile full-path="OEBPS/content.opf" media-type="application/oebps-package+xml"/></rootfiles></container>' . "\n");
    $put('OEBPS/content.opf', $opf);
    foreach ($files as $name => [$content]) $put('OEBPS/' . $name, $content);
    $zip->close();
    return ['file' => basename($out), 'entries' => count($m['terms']), 'letters' => count($letters), 'reading' => $reading, 'cover' => (bool)$cover];
}

/** Every reading of a glossary, built next to it. */
function glossary_epub_all(array $g, string $dir): array
{
    $done = [glossary_epub($g, $dir, $dir . '/' . glossary_epub_name($dir), '')];
    foreach ((array)($g['hasPart'] ?? []) as $p) {
        if (($p['ws:role'] ?? '') === 'selection' && !empty($p['@id'])) {
            $done[] = glossary_epub($g, $dir, $dir . '/' . glossary_epub_name($dir, $p['@id']), $p['@id']);
        }
    }
    return $done;
}

function glossary_epub_date(string $d): string
{
    $t = strtotime(strlen($d) === 10 ? $d . 'T00:00:00Z' : $d);
    return gmdate('Y-m-d\TH:i:s\Z', $t ?: 0);
}

function glossary_epub_human_date(string $d, string $lang): string
{
    $t = strtotime(strlen($d) === 10 ? $d . 'T12:00:00' : $d);
    if (!$t) return $d;
    if (class_exists('IntlDateFormatter')) {
        $f = new IntlDateFormatter($lang, IntlDateFormatter::LONG, IntlDateFormatter::NONE, 'UTC');
        return (string)$f->format($t);
    }
    return date('Y-m-d', $t);
}

/** A name-based UUID (version 5 layout over SHA-1): the same glossary and reading, the same book. */
function glossary_epub_uuid(string $name): string
{
    $h = sha1('ws-glossary:' . $name);
    return sprintf('%s-%s-5%s-%x%s-%s', substr($h, 0, 8), substr($h, 8, 4), substr($h, 13, 3),
        (hexdec(substr($h, 16, 2)) & 0x3f) | 0x80, substr($h, 18, 2), substr($h, 20, 12));
}

/** A Creative Commons licence URL, named in the glossary's language; null for any other licence. Twin of licenseBox() in glossary.js. */
function glossary_license_cc(string $url, string $lang): ?array
{
    if (!preg_match('#creativecommons\.org/licenses/((?:by)(?:-(?:nc|sa|nd))*)/(\d\.\d)#', $url, $cc)) return null;
    $parts = [
        'by' => ['Attribution', 'You must give appropriate credit, provide a link to the license, and indicate if changes were made.'],
        'nc' => ['NonCommercial', 'You may not use the material for commercial purposes.'],
        'sa' => ['ShareAlike', 'If you remix, transform, or build upon the material, you must distribute your contributions under the same license as the original.'],
        'nd' => ['NoDerivatives', 'If you remix, transform, or build upon the material, you may not distribute the modified material.'],
    ];
    $codes = explode('-', $cc[1]);
    return [
        'code' => 'CC ' . strtoupper($cc[1]) . ' ' . $cc[2],
        'name' => glossary_t('Creative Commons %1$s %2$s International', implode('-', array_map(static fn($c) => glossary_tx('license name', $parts[$c][0]), $codes)), $cc[2]),
        'deed' => 'https://creativecommons.org/licenses/' . $cc[1] . '/' . $cc[2] . '/deed.' . substr($lang, 0, 2),
        'terms' => array_map(static fn($c) => [glossary_tx('license term', $parts[$c][0]), glossary_tx('license term', $parts[$c][1])], $codes),
    ];
}

function glossary_epub_license(array $m): string
{
    $url = $m['license'];
    if (!preg_match('#^https?://#', $url)) return '';
    $cc = glossary_license_cc($url, $m['lang']);
    if (!$cc) return '<p>' . sprintf(glossary_x(glossary_t('This work is licensed under %1$s.')), '<a href="' . glossary_x($url) . '">' . glossary_x($url) . '</a>') . '</p>';
    $x = '<p class="license-code">' . glossary_x($cc['code']) . '</p>'
        . '<p>' . sprintf(glossary_x(glossary_t('This work is licensed under a %1$s license.')), '<a href="' . glossary_x($cc['deed']) . '">' . glossary_x($cc['name']) . '</a>') . '</p><ul class="license-terms">';
    foreach ($cc['terms'] as [$label, $text]) $x .= '<li><b>' . glossary_x($label) . '</b> — ' . glossary_x($text) . '</li>';
    return $x . '</ul>';
}

function glossary_epub_opf(array $m, string $uuid, string $reading, string $modified, array $files, array $spine, string $cover): string
{
    $x = static fn($s) => glossary_x($s);
    $o = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid" xml:lang="' . $x($m['lang']) . '" prefix="cc: http://creativecommons.org/ns#">' . "\n"
        . '<metadata xmlns:dc="http://purl.org/dc/elements/1.1/">' . "\n"
        . '<dc:identifier id="bookid">urn:uuid:' . $uuid . "</dc:identifier>\n"
        . '<dc:title id="title">' . $x($m['name']) . '</dc:title><meta refines="#title" property="title-type">main</meta>' . "\n";
    if ($reading !== '') $o .= '<dc:title id="subtitle">' . $x($reading) . '</dc:title><meta refines="#subtitle" property="title-type">subtitle</meta>' . "\n";
    $o .= '<dc:language>' . $x($m['lang']) . "</dc:language>\n";
    foreach ($m['authors'] as $i => $a) {
        $o .= '<dc:creator id="creator' . $i . '">' . $x($a) . '</dc:creator><meta refines="#creator' . $i . '" property="role" scheme="marc:relators">aut</meta>' . "\n";
    }
    if ($m['series']) {
        $o .= '<meta property="belongs-to-collection" id="series">' . $x(end($m['series'])) . '</meta><meta refines="#series" property="collection-type">series</meta>' . "\n";
    }
    $description = $m['abstract'] ?: $m['description'];
    if ($description !== '') $o .= '<dc:description>' . $x(trim(strip_tags($description))) . "</dc:description>\n";
    foreach ($m['keywords'] as $k) $o .= '<dc:subject>' . $x($k) . "</dc:subject>\n";
    if ($m['datePublished'] !== '') $o .= '<dc:date>' . $x($m['datePublished']) . "</dc:date>\n";
    if ($m['license'] !== '') {
        $cc = glossary_license_cc($m['license'], $m['lang']);
        $rights = $cc ? rtrim(glossary_t('This work is licensed under a %1$s license.', $cc['name']), '.') . ' (' . $cc['code'] . ').' : $m['license'];
        $o .= '<dc:rights>' . $x($rights) . "</dc:rights>\n";
        $o .= '<link rel="cc:license" href="' . $x($m['license']) . "\"/>\n";
    }
    $o .= '<meta property="dcterms:modified">' . $modified . "</meta>\n";
    if ($cover) $o .= '<meta name="cover" content="cover-image"/>' . "\n";
    $o .= "</metadata>\n<manifest>\n";
    $idOf = static fn($name) => $name === $cover ? 'cover-image' : ($name === 'toc.ncx' ? 'ncx' : preg_replace('/[^A-Za-z0-9_-]/', '-', 'f-' . $name));
    foreach ($files as $name => [, $type, $props]) {
        $o .= '<item id="' . $idOf($name) . '" href="' . $x($name) . '" media-type="' . $type . '"' . ($props ? ' properties="' . $props . '"' : '') . "/>\n";
    }
    $o .= "</manifest>\n<spine toc=\"ncx\">\n";
    foreach ($spine as $name) $o .= '<itemref idref="' . $idOf($name) . '"/>' . "\n";
    return $o . "</spine>\n</package>\n";
}

function glossary_epub_ncx(string $uuid, string $title, array $toc): string
{
    $n = 0;
    $points = static function (array $items) use (&$points, &$n) {
        $s = '';
        foreach ($items as [$label, $to, $children]) {
            $n++;
            $s .= '<navPoint id="p' . $n . '" playOrder="' . $n . '"><navLabel><text>' . glossary_x($label) . '</text></navLabel><content src="' . glossary_x($to) . '"/>' . ($children ? $points($children) : '') . '</navPoint>';
        }
        return $s;
    };
    return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1"><head><meta name="dtb:uid" content="urn:uuid:' . $uuid . '"/></head>'
        . '<docTitle><text>' . glossary_x($title) . '</text></docTitle><navMap>' . $points($toc) . "</navMap></ncx>\n";
}
