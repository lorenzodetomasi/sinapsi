<?php
define( 'ABSPATH', WS_ROOT_ABSPATH );
define( 'WP_LANG_DIR', WS_LANGUAGES_RELPATH );
$blog_id = $ws_site_nid;
function get_home_path() {
	return ws_root_abspath();
}
function get_stylesheet() {
	return get_theme_id();
}
function get_stylesheet_directory() {
	return ws_theme_abspath();
}
function get_stylesheet_directory_uri() {

}
function get_template_part( $generic_name, $specialized_name = null ) {
	include_template( $generic_name, $specialized_name = null );
}
function the_header(){
	include_template( 'header' );
}
function the_footer(){
	include_template( 'footer' );
}
function the_content( string $more_link_text = null, bool $strip_teaser = false ){
	
}
?>
