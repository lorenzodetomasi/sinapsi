<?php
// Used to set up and fix common variables and include
// the WS procedural and class library.
// Allows for some configuration in ws-config.php (see default-constants.php)
$ws_admin_abspath = ws_admin_abspath();

// Set initial default constants including WP_MEMORY_LIMIT, WP_MAX_MEMORY_LIMIT, WP_DEBUG, SCRIPT_DEBUG, WP_CONTENT_DIR and WP_CACHE.
require_once( ws_core_abspath() . '/conversion-constants.php' );
ws_conversion_constants();
require_once( ws_core_abspath() . '/default-constants.php' );
ws_initial_constants();
ws_input_constants();

// Include files required for initialization.
require_once( ws_core_abspath() . '/load.php' );
require_once( ws_core_abspath() . '/ws_dom.php' );
require_once( ws_core_abspath() . '/ws_simplexml.php' );
require_once( ws_core_abspath() . '/xml-json.php' );

require_once( ws_core_abspath() . '/filters.php' );
require_once( ws_core_abspath() . '/translations.php' );

// Set up early WS locales
$GLOBALS['ws_locales'] = ws_locales();
global $ws_locales;
setlocale(LC_TIME, ws_locale());
// Set up and load early translations
$GLOBALS['ws_languages'] = array(
  array(
    'domain' => 'default',
    'name' => ''
  ),
  array(
    'domain' => 'default',
    'name' => 'ui'
  ),
  array(
    'domain' => 'default',
    'name' => 'core'
  ),
);

require_once( ws_core_abspath() . '/plugins-load.php' );
require_once( ws_core_abspath() . '/plugins.php' );

if(!empty(GOOGLE_API_KEY)){
  require_once( ws_core_abspath() . '/google-api.php' );
}

// If not already configured, `$ws_site_nid` will default to 1 in a single site
// configuration. In multisite, it will be overridden by default in ms-settings.php.
// @global int $ws_site_nid
global $ws_site_nid;
/*
// Disable magic quotes at runtime. Magic quotes are added using ws_mysql later in wp-settings.php.
@ini_set( 'magic_quotes_runtime', 0 );
@ini_set( 'magic_quotes_sybase',  0 );
*/
// WS calculates offsets from UTC.
date_default_timezone_set( 'UTC' );
// Turn register_globals off.
ws_unregister_GLOBALS();
/*
// Standardize $_SERVER variables across setups.
wp_fix_server_vars();
*/
// Check if we have received a request due to missing favicon.ico
ws_favicon_request();
// Check if we're in maintenance mode.
ws_maintenance();
// Start loading timer.
timer_start();
// Check if we're in WP_DEBUG mode.
ws_debug_mode();
/*
// Filters whether to enable loading of the advanced-cache.php drop-in.
// This filter runs before it can be used by plugins. It is designed for non-web
// run-times. If false is returned, advanced-cache.php will never be loaded.
// @since 0.0.1
// @param bool $enable_advanced_cache Whether to enable loading advanced-cache.php (if present).
//                                    Default true.
if ( WP_CACHE && apply_filters( 'enable_loading_advanced_cache_dropin', true ) ) {
	// For an advanced caching plugin to use. Uses a static drop-in because you would only want one.
	WP_DEBUG ? include( WP_CONTENT_DIR . '/advanced-cache.php' ) : @include( WP_CONTENT_DIR . '/advanced-cache.php' );

	// Re-initialize any hooks added manually by advanced-cache.php
	if ( $wp_filter ) {
		$wp_filter = WP_Hook::build_preinitialized_hooks( $wp_filter );
	}
}

// Load early WordPress files.
require( ws_core_abspath() . '/compat.php' );
require( ws_core_abspath() . '/class-wp-list-util.php' );
*/
require( ws_core_abspath() . '/functions.php' );
/*
require( ws_core_abspath() . '/class-wp-matchesmapregex.php' );
require( ws_core_abspath() . '/class-wp.php' );
require( ws_core_abspath() . '/class-wp-error.php' );

// Include the ws_mysql class and, if present, a db.php database drop-in.
global $ws_mysql;
require_wp_db();

// Set the database table prefix and the format specifiers for database table columns.
$GLOBALS['table_prefix'] = $table_prefix;
wp_set_ws_mysql_vars();

// Start the WordPress object cache, or an external object cache if the drop-in is present.
wp_start_object_cache();
*/
// Attach the default filters.
require( ws_core_abspath() . '/filters-default.php' );
/*
// Initialize multisite if enabled.
if ( is_multisite() ) {
	require( ws_core_abspath() . '/class-wp-site-query.php' );
	require( ws_core_abspath() . '/class-wp-network-query.php' );
	require( ws_core_abspath() . '/ms-blogs.php' );
	require( ws_core_abspath() . '/ms-settings.php' );
} elseif ( ! defined( 'MULTISITE' ) ) {
	define( 'MULTISITE', false );
}

register_shutdown_function( 'shutdown_action_hook' );

// Stop most of WordPress from being loaded if we just want the basics.
if ( SHORTINIT )
	return false;

// Run the installer if WordPress is not installed.
wp_not_installed();

// Load most of WordPress.
require( ws_core_abspath() . '/class-wp-walker.php' );
require( ws_core_abspath() . '/class-wp-ajax-response.php' );
*/
require( ws_core_abspath() . '/formatting.php' );
/*
require( ws_core_abspath() . '/capabilities.php' );
require( ws_core_abspath() . '/class-wp-roles.php' );
require( ws_core_abspath() . '/class-wp-role.php' );
require( ws_core_abspath() . '/class-wp-user.php' );
require( ws_core_abspath() . '/class-wp-query.php' );
require( ws_core_abspath() . '/query.php' );
require( ws_core_abspath() . '/date.php' );
*/
if(file_exists(ws_root_abspath() . '/ws-wp.php')){
  // Required for WS-WP compatibility: converts WS functions in WP functions
  // Delete if WS-WP compatibility is not required
  require( ws_root_abspath() . '/ws-wp.php' );
}
require( ws_core_abspath() . '/themes.php' );
require( ws_core_abspath() . '/templates.php' );
require( ws_core_abspath() . '/contents.php' );
/*
require( ws_core_abspath() . '/class-wp-theme.php' );
require( ws_core_abspath() . '/user.php' );
require( ws_core_abspath() . '/class-wp-user-query.php' );
require( ws_core_abspath() . '/class-wp-session-tokens.php' );
require( ws_core_abspath() . '/class-wp-user-meta-session-tokens.php' );
require( ws_core_abspath() . '/meta.php' );
require( ws_core_abspath() . '/class-wp-meta-query.php' );
require( ws_core_abspath() . '/class-wp-metadata-lazyloader.php' );
require( ws_core_abspath() . '/general-template.php' );
require( ws_core_abspath() . '/link-template.php' );
require( ws_core_abspath() . '/author-template.php' );
require( ws_core_abspath() . '/post.php' );
require( ws_core_abspath() . '/class-walker-page.php' );
require( ws_core_abspath() . '/class-walker-page-dropdown.php' );
require( ws_core_abspath() . '/class-wp-post-type.php' );
require( ws_core_abspath() . '/class-wp-post.php' );
require( ws_core_abspath() . '/post-template.php' );
require( ws_core_abspath() . '/revision.php' );
require( ws_core_abspath() . '/post-formats.php' );
require( ws_core_abspath() . '/post-thumbnail-template.php' );
require( ws_core_abspath() . '/category.php' );
require( ws_core_abspath() . '/class-walker-category.php' );
require( ws_core_abspath() . '/class-walker-category-dropdown.php' );
require( ws_core_abspath() . '/category-template.php' );
require( ws_core_abspath() . '/comment.php' );
require( ws_core_abspath() . '/class-wp-comment.php' );
require( ws_core_abspath() . '/class-wp-comment-query.php' );
require( ws_core_abspath() . '/class-walker-comment.php' );
require( ws_core_abspath() . '/comment-template.php' );
*/
require( ws_core_abspath() . '/url_rewrite.php' );
/*
require( ws_core_abspath() . '/class-wp-rewrite.php' );
require( ws_core_abspath() . '/feed.php' );
require( ws_core_abspath() . '/bookmark.php' );
require( ws_core_abspath() . '/bookmark-template.php' );
require( ws_core_abspath() . '/kses.php' );
require( ws_core_abspath() . '/cron.php' );
require( ws_core_abspath() . '/deprecated.php' );
require( ws_core_abspath() . '/script-loader.php' );
require( ws_core_abspath() . '/taxonomy.php' );
require( ws_core_abspath() . '/class-wp-taxonomy.php' );
require( ws_core_abspath() . '/class-wp-term.php' );
require( ws_core_abspath() . '/class-wp-term-query.php' );
require( ws_core_abspath() . '/class-wp-tax-query.php' );
require( ws_core_abspath() . '/update.php' );
require( ws_core_abspath() . '/canonical.php' );
*/
require( ws_core_abspath() . '/shortcodes.php' );
require( ws_core_abspath() . '/shortcodes-default.php' );
/*
require( ws_core_abspath() . '/embed.php' );
require( ws_core_abspath() . '/class-wp-embed.php' );
require( ws_core_abspath() . '/class-oembed.php' );
require( ws_core_abspath() . '/class-wp-oembed-controller.php' );
*/
require( ws_core_abspath() . '/media.php' );
require( ws_core_abspath() . '/inputs.php' );
/*
require( ws_core_abspath() . '/http.php' );
require( ws_core_abspath() . '/class-http.php' );
require( ws_core_abspath() . '/class-wp-http-streams.php' );
require( ws_core_abspath() . '/class-wp-http-curl.php' );
require( ws_core_abspath() . '/class-wp-http-proxy.php' );
require( ws_core_abspath() . '/class-wp-http-cookie.php' );
require( ws_core_abspath() . '/class-wp-http-encoding.php' );
require( ws_core_abspath() . '/class-wp-http-response.php' );
require( ws_core_abspath() . '/class-wp-http-requests-response.php' );
require( ws_core_abspath() . '/class-wp-http-requests-hooks.php' );
require( ws_core_abspath() . '/widgets.php' );
require( ws_core_abspath() . '/class-wp-widget.php' );
require( ws_core_abspath() . '/class-wp-widget-factory.php' );
require( ws_core_abspath() . '/nav-menu.php' );
require( ws_core_abspath() . '/nav-menu-template.php' );
require( ws_core_abspath() . '/admin-bar.php' );
require( ws_core_abspath() . '/rest-api.php' );
require( ws_core_abspath() . '/rest-api/class-wp-rest-server.php' );
require( ws_core_abspath() . '/rest-api/class-wp-rest-response.php' );
require( ws_core_abspath() . '/rest-api/class-wp-rest-request.php' );
require( ws_core_abspath() . '/rest-api/endpoints/class-wp-rest-controller.php' );
require( ws_core_abspath() . '/rest-api/endpoints/class-wp-rest-posts-controller.php' );
require( ws_core_abspath() . '/rest-api/endpoints/class-wp-rest-attachments-controller.php' );
require( ws_core_abspath() . '/rest-api/endpoints/class-wp-rest-post-types-controller.php' );
require( ws_core_abspath() . '/rest-api/endpoints/class-wp-rest-post-statuses-controller.php' );
require( ws_core_abspath() . '/rest-api/endpoints/class-wp-rest-revisions-controller.php' );
require( ws_core_abspath() . '/rest-api/endpoints/class-wp-rest-taxonomies-controller.php' );
require( ws_core_abspath() . '/rest-api/endpoints/class-wp-rest-terms-controller.php' );
require( ws_core_abspath() . '/rest-api/endpoints/class-wp-rest-users-controller.php' );
require( ws_core_abspath() . '/rest-api/endpoints/class-wp-rest-comments-controller.php' );
require( ws_core_abspath() . '/rest-api/endpoints/class-wp-rest-settings-controller.php' );
require( ws_core_abspath() . '/rest-api/fields/class-wp-rest-meta-fields.php' );
require( ws_core_abspath() . '/rest-api/fields/class-wp-rest-comment-meta-fields.php' );
require( ws_core_abspath() . '/rest-api/fields/class-wp-rest-post-meta-fields.php' );
require( ws_core_abspath() . '/rest-api/fields/class-wp-rest-term-meta-fields.php' );
require( ws_core_abspath() . '/rest-api/fields/class-wp-rest-user-meta-fields.php' );
$GLOBALS['wp_embed'] = new WP_Embed();

// Load multisite-specific files.
if ( is_multisite() ) {
	require( ws_core_abspath() . '/ms-functions.php' );
	require( ws_core_abspath() . '/ms-default-filters.php' );
	require( ws_core_abspath() . '/ms-deprecated.php' );
}

// Define constants that rely on the API to obtain the default value.
// Define must-use plugin directory constants, which may be overridden in the sunrise.php drop-in.
wp_plugin_directory_constants();

$GLOBALS['wp_plugin_paths'] = array();

// Load must-use plugins.
foreach ( wp_get_mu_plugins() as $mu_plugin ) {
	include_once( $mu_plugin );
}
unset( $mu_plugin );

// Load network activated plugins.
if ( is_multisite() ) {
	foreach ( wp_get_active_network_plugins() as $network_plugin ) {
		wp_register_plugin_realpath( $network_plugin );
		include_once( $network_plugin );
	}
	unset( $network_plugin );
}

// Fires once all must-use and network-activated plugins have loaded.
// @since 2.8.0
do_action( 'muplugins_loaded' );

if ( is_multisite() )
	ms_cookie_constants(  );

// Define constants after multisite is loaded.
ws_cookie_constants();

// Define and enforce our SSL constants
wp_ssl_constants();

// Create common globals.
require( ws_core_abspath() . '/vars.php' );

// Make taxonomies and posts available to plugins and themes.
// @plugin authors: warning: these get registered again on the init hook.
create_initial_taxonomies();
create_initial_post_types();

wp_start_scraping_edited_file_errors();

// Register the default theme directory root
register_theme_directory( get_theme_root() );

// Load active plugins.
foreach ( wp_get_active_and_valid_plugins() as $plugin ) {
	wp_register_plugin_realpath( $plugin );
	include_once( $plugin );
}
unset( $plugin );

// Load pluggable functions.
require( ws_core_abspath() . '/pluggable.php' );
require( ws_core_abspath() . '/pluggable-deprecated.php' );

// Set internal encoding.
wp_set_internal_encoding();

// Run wp_cache_postload() if object cache is enabled and the function exists.
if ( WP_CACHE && function_exists( 'wp_cache_postload' ) )
	wp_cache_postload();

// Fires once activated plugins have loaded.
// Pluggable functions are also available at this point in the loading order.
// @since 1.5.0
do_action( 'plugins_loaded' );

// Define constants which affect functionality if not already defined.
wp_functionality_constants();

// Add magic quotes and set up $_REQUEST ( $_GET + $_POST )
wp_magic_quotes();

// Fires when comment cookies are sanitized.
// @since 2.0.11
do_action( 'sanitize_comment_cookies' );
*/
// WS Query object
// @since 1.0
$GLOBALS['ws_query'] = array();
require_once( ws_core_abspath() . '/query.php' );
$GLOBALS['ws_uri'] = ws_href($ws_query['wspath']);
global $ws_query;
$GLOBALS['ws_themes'] = array();
$GLOBALS['ws_plugins'] = array();

// Load ws-config
$ws_content_root = ws_content_root();
$ws_content_root_abspath = ws_content_root_abspath($ws_query['content']);
$ws_content_config_abspath = ws_content_root_abspath().'/ws-config.php';
if(file_exists($ws_content_config_abspath)){
  include($ws_content_config_abspath);
}

// Setup Content Languages
$GLOBALS['ws_content_languages'] = ws_content($ws_content_root.'/ws_languages');

// Update WS locales
$ws_locales = ws_locales();
setlocale(LC_TIME, ws_locale());
// Set up and load translations
ws_load_translations();
$longDate = new IntlDateFormatter(
  ws_locale(),
  IntlDateFormatter::LONG,
  IntlDateFormatter::NONE
);
$longDateTime = new IntlDateFormatter(
  ws_locale(),
  IntlDateFormatter::LONG,
  IntlDateFormatter::LONG
);
$longTime = new IntlDateFormatter(
  ws_locale(),
  IntlDateFormatter::NONE,
  IntlDateFormatter::LONG
);

// WS Headings Object
// Load headings (header and footer data)
// @since 1.0
global $ws_logs;
if(file_exists($ws_content_root_abspath.'/'.ws_locale().'/ws_headings.xml')){
	$GLOBALS['ws_headings'] = ws_load_file($ws_content_root_abspath.'/'.ws_locale().'/ws_headings.xml');
} else if(file_exists($ws_content_root_abspath.'/'.ws_locale().'/ws_headings.wsx')){
	$GLOBALS['ws_headings'] = ws_load_file($ws_content_root_abspath.'/'.ws_locale().'/ws_headings.wsx');
} else if(file_exists($ws_content_root_abspath.'/ws_headings.xml')){
	$GLOBALS['ws_headings'] = ws_load_file($ws_content_root_abspath.'/ws_headings.xml');
} else if(file_exists($ws_content_root_abspath.'/ws_headings.wsx')){
	$GLOBALS['ws_headings'] = ws_load_file($ws_content_root_abspath.'/ws_headings.wsx');
} else {
  $ws_logs[] = sprintf(__('File %s doesn’t exists.'), '<code>'.$ws_content_root_abspath.'/'.ws_locale().'/ws_headings.xml</code> or <code>.wsx</code>');
}
if(file_exists($ws_content_root_abspath.'/ws_sitemap.xml')){
  $GLOBALS['ws_contentmap'] = ws_load_file($ws_content_root_abspath.'/ws_sitemap.xml');
} else if(file_exists($ws_content_root_abspath.'/ws_sitemap.wsx')){
  $GLOBALS['ws_contentmap'] = ws_load_file($ws_content_root_abspath.'/ws_sitemap.wsx');
} else if(!file_exists($ws_content_root_abspath.'/ws_sitemap.wsx')){
  $GLOBALS['ws_contentmap'] = ws_load_file($ws_admin_abspath.'/ws_sitemap.wsx');
} else {
  $ws_logs[] = sprintf(__('File %s doesn’t exists.'), '<code>ws_sitemap.xml</code> or <code>.wsx</code>'); echo '<br />';
}
// WS Content Object
// @since 1.0
if(!empty($ws_query['content'])){
	$GLOBALS['ws_content'] = ws_content($ws_query['content']);
}
/*
// WordPress Widget Factory Object
// @global WP_Widget_Factory $wp_widget_factory
// @since 2.8.0
$GLOBALS['wp_widget_factory'] = new WP_Widget_Factory();

// WordPress User Roles
// @global WP_Roles $wp_roles
// @since 2.0.0
$GLOBALS['wp_roles'] = new WP_Roles();

// Fires before the theme is loaded.
// @since 2.6.0
do_action( 'setup_theme' );

// Define the template related constants.
wp_templating_constants(  );

// Load the functions for the active theme, for both parent and child theme if applicable.
if ( ! wp_installing() || 'wp-activate.php' === $pagenow ) {
	if ( TEMPLATEPATH !== STYLESHEETPATH && file_exists( STYLESHEETPATH . '/functions.php' ) )
		include( STYLESHEETPATH . '/functions.php' );
	if ( file_exists( TEMPLATEPATH . '/functions.php' ) )
		include( TEMPLATEPATH . '/functions.php' );
}

// Fires after the theme is loaded.
// @since 3.0.0
do_action( 'after_setup_theme' );

// Set up current user.
$GLOBALS['wp']->init();

// Fires after WordPress has finished loading but before any headers are sent.
// Most of WP is loaded at this stage, and the user is authenticated. WP continues
// themselves on it for all sorts of reasons (e.g. they need a user, a taxonomy, etc.).
// If you wish to plug an action once WP is loaded, use the {@see 'wp_loaded'} hook below.
// @since 1.5.0
do_action( 'init' );

// Check site status
if ( is_multisite() ) {
	if ( true !== ( $file = ms_site_check() ) ) {
		require( $file );
		die();
	}
	unset($file);
}
*/
// This hook is fired once WS, all plugins, and the theme are fully loaded and instantiated.
// Ajax requests should use wp-admin/admin-ajax.php. admin-ajax.php can handle requests for
// users not logged in.
//
// @link https://codex.wordpress.org/AJAX_in_Plugins
//
// @since 3.0.0
do_action( 'wp_loaded' );
// Set up $ws_query and load the theme.
require( ws_core_abspath() . '/template-load.php' );
