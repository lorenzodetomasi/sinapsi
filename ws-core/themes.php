<?php
// Themes
function add_ws_theme(){
	global $ws_query, $ws_themes, $theme_index;
	// Load theme-settings.php
	load_template(ws_theme_abspath($ws_query['themes'][$theme_index]).'theme-settings.php');
	// If theme has parent, …
	if(!empty($ws_themes[$theme_index]['parent_theme'])){
		// add ws_theme_id to $ws_query['themes']…
		$ws_query['themes'][] = $ws_themes[$theme_index]['parent_theme'];
		// and load parent theme-settings.php
		$theme_index =  $theme_index + 1;
		add_ws_theme();
	}
}
function ws_theme_id() {
	// Return active theme id. 
	// For parent-theme, use ws_parent_theme_id().
	// @since 0.0.1
	// @return string theme id, equal to the theme's directory name.
	global $ws_query;
	if(!empty($ws_query['themes'][0])){
		return apply_filters( 'themes', $ws_query['themes'][0]);
	} else if(defined('WS_THEME_ID') and WS_THEME_ID){
		return apply_filters( 'ws_theme_id', WS_THEME_ID );
	} else if(function_exists('get_option') and get_option( 'ws_theme_id' )){
		return apply_filters( 'ws_theme_id', get_option( 'ws_theme_id' ) );
	} else {
		$ws_logs[] = 'WS Theme ID not set.';
//		die('WS Theme ID not set.');
	}
}
function ws_themes_abspath(){
	if ( defined('WS_THEMES_RELPATH') and WS_THEMES_RELPATH ){
		return ws_root_abspath() . '/' . WS_THEMES_RELPATH;
	} else {
		return ws_custom_abspath() . '/themes/';
	}
}
function ws_themes_url(){
	if ( defined('WS_THEMES_RELPATH') and WS_THEMES_RELPATH ){
		return ws_root_url() . WS_THEMES_RELPATH;
	} else {
		return ws_custom_url() . '/themes/';
	}
}
// Return active theme absolute path (child-theme). 
// For parent-theme, use ws_parent_theme_abspath()
// @since 0.0.1
// @return string Path to current theme directory.
function ws_theme_abspath($theme_id = null) {
	if(empty($theme_id)){
		$theme_id = ws_theme_id();
	}
	return ws_themes_abspath().$theme_id.'/';
}
// Return active theme url (child-theme). 
// For parent-theme, use ws_parent_theme_url()
// @since 0.0.1
// @return string
function ws_theme_url($theme_id = null) {
	if(empty($theme_id)){
		$theme_id = ws_theme_id();
	}
	return ws_themes_url().$theme_id.'/';
}

//Parent theme
function ws_parent_theme_id($theme_id = null) {
	global $ws_query;
	if(empty($theme_id)){
		$theme_id = ws_theme_id();
	}
	
	$parent_theme_id = false; // Inizializza a false di default
	
	if(!empty($ws_query['themes'])){
		$position = array_search($theme_id, $ws_query['themes'], true);
		
		// Procedi solo se il tema è stato trovato nell'array
		if ($position !== false) {
		    $values = array_values($ws_query['themes']);
		    
		    // Verifica che esista un elemento successivo (il parent theme)
		    if (isset($values[$position + 1])) {
		        $parent_theme_id = $values[$position + 1];
		    }
		}
	}
	return $parent_theme_id;
}
function ws_parent_theme_abspath($theme_id = null) {
	if(empty($theme_id)){
		$theme_id = ws_theme_id();
	}
	$parent_theme_id = ws_parent_theme_id($theme_id);
	return ws_themes_abspath().$parent_theme_id.'/';
}
function ws_parent_theme_url($theme_id = null) {
	if(empty($theme_id)){
		$theme_id = ws_theme_id();
	}
	$parent_theme_id = ws_parent_theme_id($theme_id);
	return ws_themes_url().$parent_theme_id.'/';
}
?>
