<?php
// Determines whether a PHP ini value is changeable at runtime.
// @since 1.0
// @staticvar array $ini_all
// @link https://secure.php.net/manual/en/function.ini-get-all.php
// @param string $setting The name of the ini setting to check.
// @return bool True if the value is changeable at runtime. False otherwise.
function ws_is_ini_value_changeable( $setting ) {
	static $ini_all;

	if ( ! isset( $ini_all ) ) {
		$ini_all = false;
		// Sometimes `ini_get_all()` is disabled via the `disable_functions` option for "security purposes".
		if ( function_exists( 'ini_get_all' ) ) {
			$ini_all = ini_get_all();
		}
 	}

	// Bit operator to workaround https://bugs.php.net/bug.php?id=44936 which changes access level to 63 in PHP 5.2.6 - 5.2.17.
	if ( isset( $ini_all[ $setting ]['access'] ) && ( INI_ALL === ( $ini_all[ $setting ]['access'] & 7 ) || INI_USER === ( $ini_all[ $setting ]['access'] & 7 ) ) ) {
		return true;
	}

	// If we were unable to retrieve the details, fail gracefully to assume it's changeable.
	if ( ! is_array( $ini_all ) ) {
		return true;
	}

	return false;
}

// Defines constants and global variables that can be overridden, generally in ws-config.php.
// Defines initial WS constants
// @see ws_debug_mode()
// @since 0.0.1
// @global int    $ws_site_nid    The current site ID.
// @global string $wp_version The WS version string.
function ws_initial_constants() {
	global $ws_site_nid;
	$current_limit     = @ini_get( 'memory_limit' );
	$current_limit_int = ws_convert_hr_to_bytes( $current_limit );
	// Define memory limits.
	if ( ! defined( 'WS_MEMORY_LIMIT' ) ) {
		if ( false === ws_is_ini_value_changeable( 'memory_limit' ) ) {
			define( 'WS_MEMORY_LIMIT', $current_limit );
//		} elseif ( is_multisite() ) {
//			define( 'WS_MEMORY_LIMIT', '64M' );
		} else {
			define( 'WS_MEMORY_LIMIT', '40M' );
		}
	}
	if ( ! defined( 'WS_MAX_MEMORY_LIMIT' ) ) {
		if ( false === ws_is_ini_value_changeable( 'memory_limit' ) ) {
			define( 'WS_MAX_MEMORY_LIMIT', $current_limit );
		} elseif ( -1 === $current_limit_int || $current_limit_int > 268435456 ) {// = 256M
			define( 'WS_MAX_MEMORY_LIMIT', $current_limit );
		} else {
			define( 'WS_MAX_MEMORY_LIMIT', '256M' );
		}
	}
	// Set memory limits.
	$wp_limit_int = ws_convert_hr_to_bytes( WS_MEMORY_LIMIT );
	if ( -1 !== $current_limit_int && ( -1 === $wp_limit_int || $wp_limit_int > $current_limit_int ) ) {
		@ini_set( 'memory_limit', WS_MEMORY_LIMIT );
	}
	// Add define('WS_DEBUG', true); to wp-config.php to enable display of notices during development.
	if ( !defined('WS_DEBUG') )
		define( 'WS_DEBUG', false );
	// Add define('WS_DEBUG_DISPLAY', null); to wp-config.php use the globally configured setting for
	// display_errors and not force errors to be displayed. Use false to force display_errors off.
	if ( !defined('WS_DEBUG_DISPLAY') )
		define( 'WS_DEBUG_DISPLAY', true );
	// Add define('WS_DEBUG_LOG', true); to enable error logging to wp-content/debug.log.
	if ( !defined('WS_DEBUG_LOG') )
		define('WS_DEBUG_LOG', false);
	if ( !defined('WS_CACHE') )
		define('WS_CACHE', false);
	// Add define('SCRIPT_DEBUG', true); to wp-config.php to enable loading of non-minified,
	// non-concatenated scripts and stylesheets.
	if ( ! defined( 'SCRIPT_DEBUG' ) ) {
		if ( ! empty( $GLOBALS['wp_version'] ) ) {
			$develop_src = false !== strpos( $GLOBALS['wp_version'], '-src' );
		} else {
			$develop_src = false;
		}
		define( 'SCRIPT_DEBUG', $develop_src );
	}
	// Private
	if ( !defined('MEDIA_TRASH') )
		define('MEDIA_TRASH', false);

	if ( !defined('SHORTINIT') )
		define('SHORTINIT', false);
	// Constants for features added to WS that should short-circuit their plugin implementations
	define( 'WS_FEATURE_BETTER_PASSWORDS', true );
}
// Constants for filenames
// Last version: filename.ext
// Archive copy: filename-[WS_DATETIME_FORMAT].ext
define( 'WS_DATETIME_FORMAT', 'Ymd\THis\Z' );

// Constants for Html5 form input fields
function ws_input_constants() {
	// Constants for expressing html5 form input formats.
	// @since 0.0.1
	define( 'INPUT_DATE_FORMAT', 'Y-m-d' );
	define( 'INPUT_DATETIME_FORMAT', 'c' );
}
// Defines plugin directory WS constants
// Defines must-use plugin directory constants, which may be overridden in the sunrise.php drop-in
// @since 3.0.0
function wp_plugin_directory_constants() {
	// Allows for the plugins directory to be moved from the default location.
	// @since 2.6.0
	if ( !defined('WS_PLUGIN_DIR') )
		define( 'WS_PLUGIN_DIR', WS_CONTENT_DIR . '/plugins' ); // full path, no trailing slash

	// Allows for the plugins directory to be moved from the default location.
	// @since 2.6.0
	if ( !defined('WS_PLUGIN_URL') )
		define( 'WS_PLUGIN_URL', WS_CONTENT_URL . '/plugins' ); // full url, no trailing slash

	// Allows for the mu-plugins directory to be moved from the default location.
	// @since 2.8.0
	if ( !defined('WSMU_PLUGIN_DIR') )
		define( 'WSMU_PLUGIN_DIR', WS_CONTENT_DIR . '/mu-plugins' ); // full path, no trailing slash

	// Allows for the mu-plugins directory to be moved from the default location.
	// @since 2.8.0
	if ( !defined('WSMU_PLUGIN_URL') )
		define( 'WSMU_PLUGIN_URL', WS_CONTENT_URL . '/mu-plugins' ); // full url, no trailing slash
}

// Defines cookie related WS constants
// Defines constants after multisite is loaded.
// @since 3.0.0
function ws_cookie_constants() {
	// Used to guarantee unique hash cookies
	// @since 1.5.0
	if ( !defined( 'COOKIEHASH' ) ) {
		$siteurl = get_site_option( 'siteurl' );
		if ( $siteurl )
			define( 'COOKIEHASH', md5( $siteurl ) );
		else
			define( 'COOKIEHASH', '' );
	}
	if ( !defined('USER_COOKIE') )
		define('USER_COOKIE', 'ws_user_' . COOKIEHASH);
	if ( !defined('PASS_COOKIE') )
		define('PASS_COOKIE', 'ws_pass_' . COOKIEHASH);
	if ( !defined('AUTH_COOKIE') )
		define('AUTH_COOKIE', 'ws__' . COOKIEHASH);
	if ( !defined('SECURE_AUTH_COOKIE') )
		define('SECURE_AUTH_COOKIE', 'ws__sec_' . COOKIEHASH);
	if ( !defined('LOGGED_IN_COOKIE') )
		define('LOGGED_IN_COOKIE', 'ws__logged_in_' . COOKIEHASH);
	if ( !defined('TEST_COOKIE') )
		define('TEST_COOKIE', 'ws__test_cookie');
	if ( !defined('COOKIEPATH') )
		define('COOKIEPATH', preg_replace('|https?://[^/]+|i', '', get_option('home') . '/' ) );
	if ( !defined('SITECOOKIEPATH') )
		define('SITECOOKIEPATH', preg_replace('|https?://[^/]+|i', '', get_option('siteurl') . '/' ) );
	if ( !defined('ADMIN_COOKIE_PATH') )
		define( 'ADMIN_COOKIE_PATH', SITECOOKIEPATH . 'wp-admin' );
	if ( !defined('PLUGINS_COOKIE_PATH') )
		define( 'PLUGINS_COOKIE_PATH', preg_replace('|https?://[^/]+|i', '', WS_PLUGIN_URL)  );
	if ( !defined('COOKIE_DOMAIN') )
		define('COOKIE_DOMAIN', false);
}

// Defines cookie related WS constants
// @since 3.0.0
function wp_ssl_constants() {
	if ( !defined( 'FORCE_SSL_ADMIN' ) ) {
		if ( 'https' === parse_url( get_option( 'siteurl' ), PHP_URL_SCHEME ) ) {
			define( 'FORCE_SSL_ADMIN', true );
		} else {
			define( 'FORCE_SSL_ADMIN', false );
		}
	}
	force_ssl_admin( FORCE_SSL_ADMIN );
}

// Defines functionality related WS constants
// @since 3.0.0
function wp_functionality_constants() {
	if ( !defined( 'AUTOSAVE_INTERVAL' ) )
		define( 'AUTOSAVE_INTERVAL', 60 );
	if ( !defined( 'EMPTY_TRASH_DAYS' ) )
		define( 'EMPTY_TRASH_DAYS', 30 );
	if ( !defined('WS_POST_REVISIONS') )
		define('WS_POST_REVISIONS', true);
	if ( !defined( 'WS_CRON_LOCK_TIMEOUT' ) )
		define('WS_CRON_LOCK_TIMEOUT', 60);  // In seconds
}
/*
// Defines templating related WS constants
// @since 3.0.0
function wp_templating_constants() {
	// Filesystem path to the current active template directory
	define('TEMPLATEPATH', get_template_directory());
	// Filesystem path to the current active template stylesheet directory
	define('STYLESHEETPATH', get_stylesheet_directory());

	// Slug of the default theme for this installation.
	// Used as the default theme when installing new sites.
	// It will be used as the fallback if the current theme doesn't exist.
	// @since 0.0.1
	// @see WS_Theme::get_core_default_theme()
	if ( !defined('WS_DEFAULT_THEME') )
		define( 'WS_DEFAULT_THEME', 'twentyseventeen' );
}
*/
