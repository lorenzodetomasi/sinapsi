<?php
// Load templates and template parts
function include_template( $name, $args = array() ) {
	// Fires before the specified template part file is loaded.
	// @since 0.0.1
	// @param string | array      $name The name for the generic template. i.e. 'template-parts/header' 
	//														If is array: array([$generic_name], [$specialized_name])
	// 														$generic_name The slug name for the generic template.
	// 														$specialized_name The name of the specialized template.
	if(is_array($name)){
		$generic_name = $name[0];
		$specialized_name = $name[1];
	} else {
		$generic_name = $name;
		$specialized_name = false;
	}
	$default_args = array(
		'load' => true,
		'require_once' => true,
		'cascading' => false,
	);
	$args = array_intersect_key( $args, $default_args );
	$args = array_merge( $default_args, $args );

//	do_action( "get_template_part_{$generic_name}", $generic_name, $specialized_name );
	$templates = get_ws_templates_array( $generic_name, $specialized_name );
	locate_file($templates, $args);
}
function get_ws_templates_array($generic_name, $specialized_name = null){
	$templates = array();
	$specialized_name = (string) $specialized_name;
	if ( !empty($specialized_name) )
		$templates[] = "{$generic_name}-{$specialized_name}.php";
	$templates[] = "{$generic_name}.php";
	return $templates;
}
function ws_template_abspath( $generic_name, $specialized_name = null ) {
	$templates = get_ws_templates_array( $generic_name, $specialized_name );
	locate_file($templates);
}
function locate_file($basenames, $args = array() ) {
	global $ws_query;
	$default_args = array(
		'load' => false,
		'require_once' => true,
		'cascading' => false,
	);
	$args = array_intersect_key( $args, $default_args );
	$args = array_merge( $default_args, $args );
	$located = '';
	if($args['cascading'] == false) {
		foreach ( (array) $basenames as $basename ) {
			if ( !$basename )
				continue;
			if(file_exists(ws_admin_abspath()."/$basename")){
				//if template file exists in admin folder
				$located = ws_admin_abspath()."/$basename";
				break;
			}
			foreach ($ws_query['themes'] as $ws_theme_id) {
				if ( file_exists(ws_theme_abspath($ws_theme_id).$basename)) {
					//if template file exists, starting from last descendant theme
					$located = ws_theme_abspath($ws_theme_id).$basename;
					break;
				}
			}
		}
		if ( $args['load'] && !empty($located) ){
			load_template( $located, $args );
		}
		return $located;
	} else {
		//if ($args['cascading'] == true)
		foreach ( (array) $basenames as $basename ) {
			if ( !$basename )
				continue;
			if(file_exists(ws_admin_abspath()."/$basename")){
				//if template file exists in admin folder
				$located = ws_admin_abspath()."/$basename";
				if ( $args['load'] && !empty($located) ){
					load_template( $located, $args );
				}
			}
			foreach (array_reverse($ws_query['themes']) as $ws_theme_id) {
				if ( file_exists(ws_theme_abspath($ws_theme_id).$basename)) {
					//if template file exists in child-theme
					$located = ws_theme_abspath($ws_theme_id).$basename;
					if ( $args['load'] && !empty($located) ){
						load_template( $located, $args );
					}
				}
			}
		}
	}
}
// Require the template file.
// The globals are set up for the template file to ensure that the WS environment is available from within the function.
// The query variables are also available.
// @since 0.0.1
// @global array      $posts
// @global WP_Post    $post
// @global bool       $wp_did_header
// @global WP_Query   $wp_query
// @global WP_Rewrite $wp_rewrite
// @global ws_mysql   $ws_mysql
// @global string     $wp_version
// @global WP         $wp
// @global int        $id
// @global WP_Comment $comment
// @global int        $user_ID

// @param string $basename template filename with extension.
// @param bool   $require_once   Whether to require_once or require. Default true.

function load_template( $template_abspath, $args = array() ) {
	$default_args = array(
		'require_once' => true,
	);
	$args = array_intersect_key( $args, $default_args );
	$args = array_merge( $default_args, $args );
	/*
	global $posts, $post, $wp_did_header, $wp_query, $wp_rewrite, $ws_mysql, $wp_version, $wp, $id, $comment, $user_ID;

	if ( is_array( $wp_query->query_vars ) ) {
		extract( $wp_query->query_vars, EXTR_SKIP );
	}

	if ( isset( $s ) ) {
		$s = esc_attr( $s );
	}
	*/
	if ( $args['require_once'] ) {
		require_once( $template_abspath );
	} else {
		require( $template_abspath );
	}
	global $ws_logs;
	$ws_logs[] = sprintf(__('File (%1$s) included.'), '<code>'.$template_abspath.'</code>');
}

//
function ws_array_merge(&$array, $path, $value, $args = array()){
	$default_args = array(
		'recursive' => true,
	);
	$args = array_merge( $default_args, $args );
  $key = array_shift($path);
  if (empty($path)) {
    $array[$key] = $value;
  } else {
    if (!isset($array[$key]) || !is_array($array[$key])) {
      $array[$key] = array();
    }
		if($args['recursive'] === true){
    	ws_array_merge($array[$key], $path, $value);
		}
  }
}
function ws_html_attributes($selector, $attrs = array(), $args = array()){
	//	@param array $selector
	//	@param array $attrs
	$default_args = array(
		'recursive' => true,
	);
	$args = array_merge( $default_args, $args );
	if(isset($GLOBALS['ws_html_attributes'][$selector])){
		$GLOBAL_ws_html_attributes = $GLOBALS['ws_html_attributes'][$selector];
		if(!$GLOBAL_ws_html_attributes){
			$GLOBAL_ws_html_attributes = array();
		}
		if($args['recursive'] === true){
			$ws_html_attributes = array_merge_recursive( $GLOBAL_ws_html_attributes, $attrs );
		} else {
			$ws_html_attributes = array_merge( $GLOBAL_ws_html_attributes, $attrs );
		}
	} else {
		$ws_html_attributes = $attrs;
	}
	return array2html_attributes($ws_html_attributes);
}
function array2html_attributes($array){
	$html = '';
	foreach ($array as $attrKey => $attrValue) {
		if(is_array($attrValue)){
			$attrValue = implode(' ', $attrValue);
		}
		if(empty($attrValue)){
			$html .= ' '.$attrKey;
		} else if(!empty($attrValue)){
			$html .= ' '.$attrKey.'="'.$attrValue.'"';
		}
	}
	return $html;
}
function ws_metas($selector = null, $metas = array(), $args = array()){
	$default_args = array();
	$args = array_merge( $default_args, $args );
	$GLOBAL_ws_metas = $GLOBALS['ws_metas'];
	$ws_metas = array_merge_recursive( $GLOBAL_ws_metas, $metas );
	$html = '';
	foreach ($ws_metas as $metaKey => $metaValue) {
		$html .= $metaValue;
	}
	return $html;
}
function ws_links($selector = null, $links = array(), $args = array()){
	$default_args = array();
	$args = array_merge( $default_args, $args );
	if($GLOBALS['ws_links']){
		$GLOBAL_ws_links = $GLOBALS['ws_links'];
		$ws_links = array_merge_recursive( $GLOBAL_ws_links, $links );
	} else {
		$ws_links = $links;
	}
	$html = '';
	foreach ($ws_links as $linkKey => $linkValue) {
		$html .= $linkValue;
	}
	return $html;
}
function ws_scripts($selector, $scripts = array(), $args = array()){
	$default_args = array();
	$args = array_merge( $default_args, $args );
	$GLOBAL_ws_scripts = $GLOBALS['ws_scripts'][$selector];
	if(!$GLOBAL_ws_scripts){
		$GLOBAL_ws_scripts = array();
	}
	$ws_scripts = array_merge_recursive( $GLOBAL_ws_scripts, $scripts );
	$html = '';
	foreach ($ws_scripts as $scriptKey => $scriptValue) {
		$html .= $scriptValue;
	}
	return $html;
}
function ws_styles($selector, $styles = array(), $args = array()){
	$default_args = array();
	$args = array_merge( $default_args, $args );
	$GLOBAL_ws_styles = $GLOBALS['ws_styles'][$selector];
	if(!$GLOBAL_ws_styles){
		$GLOBAL_ws_styles = array();
	}
	$ws_styles = array_merge_recursive( $GLOBAL_ws_styles, $styles );
	$html = '';
	foreach ($ws_styles as $styleKey => $styleValue) {
		$html .= $styleValue;
	}
	return $html;
}
function ws_href($wspath, $args = array()){
	$default_args = array(
		'output' => 'absolute',// absolute | relative
	);
	$args = array_intersect_key( $args, $default_args );
	$args = array_merge( $default_args, $args );
	$wspath = ws_normalize_relpath($wspath);
	if($args['output'] == 'absolute'){
		return ws_root_url()."/$wspath";
	} else if($args['output'] == 'relative'){
		return './'.$wspath;
	}
}
?>
