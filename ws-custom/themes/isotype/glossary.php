<?php
/**
 * The Glossary template.
 *
 * A page whose main entity is a glossary: the content folder holds
 * `glossary.jsonld` (see glossary/README.md) next to the page's index.json.
 * The page carries the glossary as JSON-LD - the data the app draws from, and
 * the structured data the engines read - and glossary/glossary.js draws it
 * inside the site's header and footer.
 *
 * - The look is a skin, `&skin=` in the page's query (isotype by default).
 * - The EPUBs built beside the glossary (ws-admin/glossaries) are offered for
 *   download: they are added to the data here, as `encoding`, not written into
 *   the glossary, since they are made from it.
 * - Without JavaScript the entries are still there, as a plain list.
 *
 * @package WS
 * @subpackage isotype
 */
global $ws_content, $ws_query;
require_once __DIR__ . '/glossary/epub.php';

$glossary_dir = ws_contents_abspath() . '/' . ws_content_id();
$glossary_url = rtrim(ws_contents_url(), '/') . '/' . ws_content_id() . '/';
$glossary = is_file($glossary_dir . '/' . GLOSSARY_FILE) && filesize($glossary_dir . '/' . GLOSSARY_FILE) <= GLOSSARY_MAX_BYTES
    ? json_decode((string)file_get_contents($glossary_dir . '/' . GLOSSARY_FILE), true) : null;
$glossary_model = is_array($glossary) ? glossary_model($glossary) : null;
if ($glossary_model) glossary_catalogue(glossary_locale($glossary_model['lang']));

if ($glossary_model) {
    $readings = ['' => $glossary_model['alternateName'] ?: $glossary_model['name']];
    foreach ($glossary_model['selections'] as $id => $s) $readings[$id] = $s['name'];
    $encoding = [];
    foreach ($readings as $id => $label) {
        $file = glossary_epub_name($glossary_dir, $id);
        if (is_file($glossary_dir . '/' . $file)) {
            $encoding[] = ['@type' => 'MediaObject', 'encodingFormat' => 'application/epub+zip',
                'contentUrl' => $glossary_url . $file, 'name' => glossary_t('Download the EPUB: %s', $label)];
        }
    }
    if ($encoding) $glossary['encoding'] = $encoding;
}

$skin = (string)($ws_query['skin'] ?? '');
if (!preg_match('/^[a-z0-9-]+$/', $skin) || !is_file(__DIR__ . '/glossary/skins/' . $skin . '.css')) $skin = 'isotype';
$assets = ws_theme_url() . 'glossary/';
// The file's date in the address: a changed stylesheet or script reaches the reader at once.
$asset = static fn(string $f) => $assets . $f . '?v=' . @filemtime(__DIR__ . '/glossary/' . $f);
$GLOBALS['ws_styles']['head']['glossary'] = '<link rel="stylesheet" href="' . $asset('glossary.css') . '">'
    . '<link rel="stylesheet" href="' . $asset('skins/' . $skin . '.css') . '">'
    // The site's #main-container scrolls on its own (overflow: auto), which traps anything
    // sticky inside it: the glossary's search bar would never stop under the header. Without
    // it, min-width: 0 keeps the page's grid from growing to the widest row inside.
    . '<style>.page-glossary #main-container { overflow: visible; min-width: 0; }</style>';
$GLOBALS['ws_html_attributes']['html']['class'][] = 'page-glossary';
include_template('template-parts/header');
?>
			<div<?php echo ws_html_attributes('main-content'); ?>>
<?php if ($ws_content->parent->wspath) { ?>
				<a class="link" href="<?php echo $ws_content->parent->wspath; ?>"><span class="material-symbols-outlined">arrow_upward</span></a>
<?php } ?>
<?php if (!$glossary_model) { ?>
				<h1><?php echo $ws_content->name ? $ws_content->name->innerHTML() : ''; ?></h1>
				<p><?php echo glossary_x(glossary_t('This file has no DefinedTermSet.')); ?></p>
<?php } else { ?>
				<div data-glossary data-source="#glossary-data" data-sticky-under="#header" data-base="<?php echo glossary_x($glossary_url); ?>">
					<noscript>
						<h1><?php echo glossary_x($glossary_model['name']); ?></h1>
						<dl class="glossary-fallback">
<?php
    foreach ($glossary_model['terms'] as $t) {
        echo glossary_entry_xhtml($glossary_model, $t, static fn($id) => isset($glossary_model['terms'][$id]) ? '#' . $glossary_model['terms'][$id]['anchor'] : '');
    }
?>
						</dl>
					</noscript>
				</div>
				<script type="application/ld+json" id="glossary-data"><?php echo json_encode($glossary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG); ?></script>
				<script>window.WSGlossaryL10n = <?php echo json_encode(glossary_catalogue() ?: new stdClass(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG); ?>;</script>
				<script src="<?php echo $asset('glossary.js'); ?>" defer></script>
<?php } ?>
			</div>
<?php
include_template('template-parts/footer');
?>
