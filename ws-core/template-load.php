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
$GLOBALS['ws_metas']['og_description'] = '<meta property="og:description" content="'.$rewrite_rule->description.'" />';
if(!empty($ws_content->og->image[0])){
	$GLOBALS['ws_metas']['og_image'] = '<meta property="og:image" content="'.get_media($$ws_content->og->image[0], array('output' => 'src')).'" />';
	$GLOBALS['ws_metas']['twitter_card'] = '<meta name="twitter:card" content="'.get_media($ws_content->og->image[0], array('output' => 'src')).'" />';
}
if(!empty($current_url)){
	$GLOBALS['ws_metas']['og_url'] = '<meta property="og:url" content="'.$current_url.'" />';
}
if(!empty($current_url)){
	$GLOBALS['ws_metas']['og_url'] = '<meta property="og:url" content="'.$current_url.'" />';
}
$GLOBALS['ws_metas'][] = '<link rel="alternate" hreflang="lang_code" href="url_of_page" />';

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
