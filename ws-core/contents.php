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
	/* The JSON is the source; the XML is its derived twin, and what the CMS
	 * reads. When a content has a JSON, its twin is made fresh here - built
	 * or rebuilt if the JSON changed since the twin was recorded in the
	 * derived-files manifest (ws-admin/_refresh-content.php) - and then read.
	 * That is the lazy refresh: the page that is asked for is the page that
	 * gets its files done, and nothing else moves.
	 *
	 * It used to pick the NEWER of the two by date and, when that was the
	 * JSON, convert it in memory on every request. Dates lie after an FTP
	 * upload, and converting each time paid for the same work forever; the
	 * manifest remembers. When the twin cannot be had (the tree is not
	 * writable, the JSON is not a content) the JSON is read as before. */
	foreach(array('', '/index') as $suffisso){
		$xml = $content_abspath.$suffisso.'.xml';
		$json = $content_abspath.$suffisso.'.json';
		$wsx = $content_abspath.$suffisso.'.wsx';
		if(file_exists($json)){
			require_once( ws_admin_abspath() . '/_refresh-content.php' );
			$twin = ws_content_ensure_xml($json);
			return $content_relpath.$suffisso.($twin !== '' ? '.xml' : '.json');
		}
		/* A content not yet migrated is a hand-written .wsx: the same lazy
		 * twin, resolved this time, so that an edit to the headings or a
		 * location reaches the page without an admin refresh. */
		if(file_exists($wsx)){
			require_once( ws_admin_abspath() . '/_refresh-content.php' );
			$twin = ws_content_ensure_xml($wsx);
			return $content_relpath.$suffisso.($twin !== '' ? '.xml' : '.wsx');
		}
		if(file_exists($xml)){
			return $content_relpath.$suffisso.'.xml';
		}
		if($suffisso === '' and !is_dir($content_abspath)){
			break;
		}
	}
	return $content_relpath.'/';
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
	/* The slash in the middle. `ws_root_url()` ends without one and the relative
	 * path starts without one, so this used to return
	 * `https://www.isotype.orgws-custom/contents/...` - every link built on it
	 * was broken, in five themes. Nobody had noticed because its one real use,
	 * the favicon links, was being wiped by a later `ws_globals_set` before it
	 * ever reached the page. `ws_admin_url()`, two functions down in
	 * ws-library.php, has always spelled the slash out; this one had forgotten.
	 * Trimmed on both sides so a WS_ROOT_URL written with a trailing slash does
	 * not produce two. */
	return rtrim(ws_root_url(), '/').'/'.ltrim(ws_content_root_relpath($content_path), '/');
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
	// Una pagina di servizio che non esiste è la norma, non un errore: chiederla
	// non deve stampare un avviso davanti al contenuto.
	$pageDOMElement = $pageDOMElements[0] ?? null;
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
