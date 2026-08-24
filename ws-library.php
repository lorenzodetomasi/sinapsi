<?php
// Bootstrap file for defining main paths,
// loading basic functions and informations about requirements, server and translations
// and loading the config.php file.
// The config.php file will then load the settings.php file, which will then set up the Site environment.
// If theconfig.php file is not found then an error will be displayed asking the visitor to set up the config.php file.
//  Will also search for config.php in Site' parent directory to allow the Site directory to remain untouched.

// Define paths, directories and urls
// _ABSPATH: the full path, always ends with /; i.e.
// _RELPATH: the directory name, always ends with /; i.e. ws-content/languages/
// _URL: the full url (http:// or https://), always ends with /; i.e. https://localbiz.it/ws-content/languages/
if ( ! isset($ws_site_nid) )
	$ws_site_nid = 1;
if ( ! defined( 'WS_ROOT_ABSPATH' ) ) {
	define( 'WS_ROOT_ABSPATH', dirname( __FILE__ ) );// Define WS_ROOT_ABSPATH as this file's directory
// Directories
	if ( !defined('WS_CORE_RELPATH') )
		define( 'WS_CORE_RELPATH', 'ws-core' );
	if ( !defined('WS_ADMIN_RELPATH') )
		define( 'WS_ADMIN_RELPATH', 'ws-admin' );
	if ( !defined('WS_CUSTOM_RELPATH') )
		define( 'WS_CUSTOM_RELPATH', 'ws-custom' );
	if ( !defined('WS_CONTENTS_RELPATH') )
		define( 'WS_CONTENTS_RELPATH', WS_CUSTOM_RELPATH . '/contents' );
	if ( !defined('WS_LANGUAGES_RELPATH') )
		define( 'WS_LANGUAGES_RELPATH', WS_CUSTOM_RELPATH . '/languages' );
	if ( !defined('WS_PLUGINS_RELPATH') )
		define( 'WS_PLUGINS_RELPATH', WS_CUSTOM_RELPATH . '/plugins' );
}
error_reporting( E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_ERROR | E_WARNING | E_PARSE | E_USER_ERROR | E_USER_WARNING | E_RECOVERABLE_ERROR );

// Get the absolute filesystem path to the root of the WS installation
// @since 0.0.1
// @return string Full filesystem path to the root of the WordPress installation
function ws_root_abspath() {
	return WS_ROOT_ABSPATH;
}
// Paths
function ws_core_abspath() {
	return ws_root_abspath() . '/' . WS_CORE_RELPATH;
}
function ws_admin_abspath() {
	return ws_root_abspath() . '/' .  WS_ADMIN_RELPATH;
}
function ws_custom_abspath() {
	return ws_root_abspath() . '/' .  WS_CUSTOM_RELPATH;
}
function ws_contents_abspath(){
	return ws_root_abspath() . '/' .  WS_CONTENTS_RELPATH;
}
function ws_languages_abspaths() {
	// Set the location of the language directory.
	// To set directory manually, define the `WS_LANGUAGES_RELPATHS` constant in ws-config.php.
	$ws_languages_relpaths[] = WS_LANGUAGES_RELPATH . '/';
	foreach ( $ws_languages_relpaths as $ws_languages_relpath ){
		$ws_languages_abspaths[] = ws_root_abspath() . '/' .  $ws_languages_relpath;
	}
	if(function_exists('ws_theme_abspath')){
		$ws_theme_languages_abspath = ws_theme_abspath().'/languages/';
		if(file_exists($ws_theme_languages_abspath)){
			$ws_languages_abspaths[] = $ws_theme_languages_abspath;
		}
	}
	return $ws_languages_abspaths;
}
function ws_plugins_abspath(){
	return ws_root_abspath() . '/' .  WS_PLUGINS_RELPATH;
}
function ws_plugins_abspaths(){
	// Set the location of the plugins directory.
	// To set directory manually, define the `WS_PLUGINS_RELPATHS` constant in ws-config.php.
	$ws_plugins_relpaths[] = WS_PLUGINS_RELPATH;
	foreach ( $ws_plugins_relpaths as $ws_plugins_relpath ){
		$ws_plugins_abspaths[] = ws_root_abspath() . '/' .  $ws_plugins_relpath;
	}
	if(function_exists('ws_theme_abspath')){
		$ws_theme_plugins_abspath = ws_theme_abspath().'/plugins/';
		if(file_exists($ws_theme_plugins_abspath)){
			$ws_plugins_abspaths[] = $ws_theme_plugins_abspath;
		}
	}
	return $ws_plugins_abspaths;
}
// These can't be directly globalized in version.php. When updating,
// we're including version.php from another installation and don't want
// these values to be overridden if already set.
global $ws_version, $ws_mysql_version, $tinymce_version, $required_php_version, $required_mysql_version, $ws_local_package;
require_once( ws_core_abspath() . '/version.php' );
require_once( ws_core_abspath() . '/server.php' );

// Urls
function ws_root_url() {
	if(defined( 'WS_ROOT_URL' )){
		return WS_ROOT_URL;
	} else {
		return ws_protocol().$_SERVER['HTTP_HOST'];
	}
}
function ws_admin_url() {
	return ws_root_url() . '/' . WS_ADMIN_RELPATH . '/';
}
function ws_custom_url() {
	return ws_root_url() . '/' . WS_CUSTOM_RELPATH;
}
function ws_contents_url(){
	return ws_root_url() . '/' . WS_CONTENTS_RELPATH . '/';
}
// Check for the required PHP version and for the MySQL extension or a database drop-in.
ws_check_php_mysql_versions();

// If ws-config.php exists in /ws-custom
// or if it exists in /ws-custom and /ws-settings.php doesn't,
// load ws-config.php.
// The secondary check for ws-settings.php has the added benefit
// of avoiding cases where the current directory is a nested installation
// e.g. / is site(a)
// and /blog/ is site(b).
// If neither set of conditions is true, initiate loading the setup process.

if ( file_exists( ws_custom_abspath() . '/ws-config.php') ) {
	require_once( ws_custom_abspath() . '/ws-config.php' );
	require_once( ws_root_abspath() . '/ws-settings.php' );// Sets up WS vars and included files
} elseif ( @file_exists( dirname( ws_root_abspath() ) . '/ws-config.php' ) && ! @file_exists( dirname( ws_root_abspath() ) . '/' .  WS_CORE_RELPATH . '/ws-settings.php' ) ) {
	// The config file resides one level above WS_ROOT_ABSPATH but is not part of another installation
	require_once( dirname( ws_root_abspath() ) . '/ws-config.php' );
	require_once( dirname( ws_root_abspath() ) . '/ws-settings.php' );// Sets up WS vars and included files
} else {
	// A config file doesn't exist
	// Standardize $_SERVER variables across setups.
	ws_fix_server_vars();
	require_once( ws_core_abspath() . '/functions.php' );
	// Redirect to ws-config-setup.php
	$ws_config_setup_url = ws_admin_url() . 'ws-config-setup.php';
	if ( false === strpos( $_SERVER['REQUEST_URI'], 'ws-config-setup' ) ) {
		header( 'Location: ' . $ws_config_setup_url );
		exit;
	}
	if((isset($_GET['debug']) and $_GET['debug'] == 'true') or (defined('WS_DEBUG') and WS_DEBUG == true)){
		// Initialize $ws_logs for error reporting
		$ws_logs = array();
		$ws_logs[] = 'WS_DEBUG == true.';
		$ws_logs[] = '$GLOBALS[\'ws_logs\'] initialized.';
	}
	ws_check_php_mysql_versions();
	// Die with an error message
	$die  = sprintf(
		// translators: %s: ws-config.php
		__( "There doesn't seem to be a %s file. WS needs this." ),
		'<code>ws-config.php</code>'
	) . '</p>';
	$die .= '<p>' . sprintf(
		// translators: %s: ws-config.php
		__( "You can manually create a %s file and upload it in WS root folder." ),
		'<code>ws-config.php</code>'
	) . '</p>';
	$die .= '<p>' . sprintf(
		'<a href="%1$s">%2$s</a>',
		__('https://localbiz.it/ws/ws-config-example.txt'),
		__( "Download a file example." )
	) . '</p>';
	$die .= '<p>' . sprintf(
		// translators: %s: Codex URL
		__("Need more help? <a href='%s'>We got it</a>."),
		__('https://localbiz.it/ws/editing-ws-config.php')
	) . '</p>';
	die( $die );
//	ws_die( $die, __( 'WS &rsaquo; Error' ) );
}
//if(!empty($ws_logs)){
if((isset($_GET['debug']) and $_GET['debug'] == 'true') or WS_DEBUG == true){
	// Load Debug Template.
	require_once( ws_core_abspath() . '/templates/debug.php' );
}
if((isset($_GET['refresh']) and $_GET['refresh'] == 'true')){
	require_once( ws_admin_abspath() . '/_refresh-content.php' );
}
