<?php
function ws_content_id(){
	global $ws_query;
	if($ws_query['content']){
		return apply_filters( 'ws_content_id', $ws_query['content']);
	} else if(defined('WS_CONTENT_ID') and WS_CONTENT_ID){
		return apply_filters( 'ws_content_id', WS_CONTENT_ID );
	} else if(function_exists('get_option') and get_option( 'ws_content_id' )){
		return apply_filters( 'ws_content_id', get_option( 'ws_content_id' ) );
	} else {
		return false;
		$ws_log = __('WS Content ID not set.');
	}
}
// ws-custom/contents/[content_id]
function ws_content_relpath($content_path = null){
	if(empty($content_path)){
		$content_path = ws_content_id();
	}
	$content_relpath = WS_CONTENTS_RELPATH . "/$content_path";
	$content_abspath = ws_root_abspath() . "/$content_relpath";
	if(file_exists($content_abspath.'.xml')) {
		$content_relpath = $content_relpath.'.xml';
	} else if(is_dir($content_abspath) and file_exists($content_abspath)){
		// If is an existing dir
		if(file_exists($content_abspath.'/index.xml')){
			$content_relpath = $content_relpath.'/index.xml';
		}
	} else {
		$content_relpath = $content_relpath.'/';
	}
	return $content_relpath;
}
// [ws_root_abspath]ws-custom/contents/[content_id]
function ws_content_abspath($content_path = null){
	if(empty($content_path)){
		$content_path = ws_content_id();
	}
	$content_relpath = ws_content_relpath($content_path);
	return ws_root_abspath()."/$content_relpath";
}
// Return active content url.
// @since 0.0.1
// @return string
function ws_content_root($content_path = null){
	if(empty($content_path)){
		$content_path = ws_content_id();
	}
	$ws_content_path_parts = explode('/', $content_path);
	$ws_content_root_path = $ws_content_path_parts[0];
	$ws_content_root = $ws_content_root_path;
//	$ws_content_root = dirname($ws_content_root_path);
/*
	if(count($ws_content_path_parts) > 2){
	} else {
		$ws_content_root = dirname(ws_content_abspath());
	}
	*/
	return $ws_content_root;
}

function ws_content_root_relpath($content_path = null){
	if(empty($content_path)){
		$content_path = ws_content_id();
	}
	return WS_CONTENTS_RELPATH . '/' . ws_content_root($content_path);
}

function ws_content_root_abspath($content_path = null){
	return ws_root_abspath() . '/' . ws_content_root_relpath($content_path);
}

function ws_content_root_url($content_path = null) {
	if(empty($content_path)){
		$content_path = ws_content_id();
	}
	return ws_root_url().ws_content_root_relpath($content_path);
}

function ws_content( $content_path, $args = array() ) {
	if(empty($content_path)){
		$content_path = ws_content_id();
	}
	$content_abspath = ws_content_abspath($content_path);
	$content_dirname = dirname($content_abspath);
	$content = ws_load_file($content_abspath, $args);
	return $content;
}
// Default Pages
// $type: PrivacyPage |
function ws_pageDOMElements($type){
	global $ws_contentmap;
	$xpath = 'url[type="'.$type.'"]';
	return $ws_contentmap->xpath($xpath);
}
function ws_pageLink($type, $text = null){
	$pageDOMElements = ws_pageDOMElements($type);
	$pageDOMElement = $pageDOMElements[0];
	if($pageDOMElement){
		if(!$text and !empty($pageDOMElement->title)){
			$text = $pageDOMElement->title;
		}
		return '<a href="'.ws_href($pageDOMElement->wspath).'">'.$text.'</a>';
	} else {
		return false;
	}
}
// Echoes html content with shortcodes
function ws_echo($xml){
	global $shortcode_tags;
	$xml = apply_filters( 'ws_echo', $xml );
	$xml = str_replace( ']]>', ']]&gt;', $xml );
	echo $xml;
}
?>
