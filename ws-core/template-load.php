<?php
global $ws_query, $ws_themes, $ws_content, $ws_logs;
// Theme settings
$theme_index = 0;
add_ws_theme();

// Language
$ws_query['lang'] = ws_lang();
$ws_query['langArray'] = explode("-", $ws_query['lang']);

// $GLOBALS
$GLOBALS['ws_html_attributes'] = array(
	'html' => array(
		'id' => str_replace('/', '-', $ws_query['wspath']),
		'lang' => ws_lang(),
		'class' => array('ws'),
	),
	'header' => array(
		'id' => 'header',
	),
	'body' => array(),
	'page' => array(
		'id' => 'page',
	),
	'main' => array(
		'id' => 'main',
	),
	'footer' => array(
		'id' => 'footer',
	),
);
$current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
$GLOBALS['ws_metas'] = array(
	'charset' => '<meta charset="'.WS_CHARSET.'" />',
	'viewport' => '<meta name="viewport" content="width=device-width,minimum-scale=1,initial-scale=1" />'
);
if(!empty($rewrite_rule->description)){
	$GLOBALS['ws_metas']['description'] = '<meta name="description" content="'.$rewrite_rule->description.'" />';
}
if(!empty($rewrite_rule->robots)){
	$GLOBALS['ws_metas']['robots'] = '<meta name="robots" content="'.$rewrite_rule->robots.'" />';
}
$GLOBALS['ws_metas']['og_type'] = '<meta property="og:type" content="website" />';
if(!empty($rewrite_rule->title)){
	$GLOBALS['ws_metas']['og_title'] = '<meta property="og:title" content="'.$rewrite_rule->title.'" />';
}
// Una descrizione che non c'è non si annuncia: `<meta name="description">` qui
// sopra si guarda dall'essere vuoto, e `og:description` faceva l'opposto — le
// pagine senza sommario (per esempio i luoghi di Meetoo) spedivano un
// `content=""`, che a chi legge la scheda del collegamento dice meno di niente.
if(!empty($rewrite_rule->description)){
	$GLOBALS['ws_metas']['og_description'] = '<meta property="og:description" content="'.$rewrite_rule->description.'" />';
}
if(!empty($ws_content->og->image[0])){
	// `$$ws_content` era un doppio dollaro di troppo: PHP ci leggeva il nome di
	// un'altra variabile, e la pagina moriva. Non è mai successo solo perché
	// nessun contenuto, finora, ha un `og/image`.
	$GLOBALS['ws_metas']['og_image'] = '<meta property="og:image" content="'.get_media($ws_content->og->image[0], array('output' => 'src')).'" />';
	$GLOBALS['ws_metas']['twitter_card'] = '<meta name="twitter:card" content="'.get_media($ws_content->og->image[0], array('output' => 'src')).'" />';
}
if(!empty($current_url)){
	$GLOBALS['ws_metas']['og_url'] = '<meta property="og:url" content="'.$current_url.'" />';
}
/* Qui c'era anche un `<link rel="alternate" hreflang="lang_code" href="url_of_page">`:
 * il segnaposto di una cosa da fare, che però ogni pagina di ogni sito spediva
 * tale e quale ai motori di ricerca — un hreflang che dichiara una lingua di nome
 * «lang_code» in un indirizzo di nome «url_of_page».
 *
 * Non si può riempirlo con quello che il CMS sa oggi: per dire «questa pagina è la
 * traduzione di quella» serve un legame FRA due voci della mappa, e nella mappa
 * quel legame non c'è (ogni `<url>` sa la propria lingua, non chi sono i suoi
 * gemelli). Il giorno che ci sarà, l'hreflang si scrive da lì; finché non c'è,
 * non dichiarare niente è più corretto che dichiarare una cosa falsa. */

$GLOBALS['ws_scripts'] = array(
	'body_end' => '',
);
$GLOBALS['ws_styles'] = array();
include_template( 'functions', array('cascading' => true) );

// Load Theme Plugins
foreach(ws_plugins('theme') as $ws_plugin){
	$ws_plugin_abspath = ws_plugins_abspath().'/'.$ws_plugin.'/ws-index.php';
	$ws_logs[] = sprintf(__('Loading Theme plugin: %1$s.'), $ws_plugin_abspath);
	if(file_exists($ws_plugin_abspath)){
		require_once($ws_plugin_abspath);
	} else {
		$ws_logs[] = sprintf(__('Theme plugin not found: %1$s.'), $ws_plugin_abspath);
	}
}
// Load Content Plugins
foreach(ws_plugins('content') as $ws_plugin){
	$ws_plugin_abspath = ws_plugins_abspath().'/'.$ws_plugin.'/ws-index.php';
	$ws_logs[] = sprintf(__('Loading Content plugin: %1$s.'), $ws_plugin_abspath);
	if(file_exists($ws_plugin_abspath)){
		require_once($ws_plugin_abspath);
	} else {
		$ws_logs[] = sprintf(__('Content plugin not found: %1$s.'), $ws_plugin_abspath);
	}
}

$js_theme_functions_abspath = locate_file('js/functions.js');
if(file_exists($js_theme_functions_abspath)){
	$GLOBALS['ws_scripts']['head']['js_theme_functions'] = '<script defer="defer" src="'.abspath2url($js_theme_functions_abspath).'"></script>';
}

// Load template
// Loads the current theme template.
// @since 0.0.1

include_template($ws_query['template']);
$ws_logs[] = sprintf(__('Theme template (%1$s) loaded.'), '<code>'.$ws_query['template'].'</code>');
?>
