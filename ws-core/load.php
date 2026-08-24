<?php
// These functions are needed to load WS.
// @package WS

// Turn register globals off.
// @since 1.0
// @access private
function ws_unregister_GLOBALS() {
	if ( !ini_get( 'register_globals' ) )
		return;

	if ( isset( $_REQUEST['GLOBALS'] ) )
		die( 'GLOBALS overwrite attempt detected' );

	// Variables that shouldn't be unset
	$no_unset = array( 'GLOBALS', '_GET', '_POST', '_COOKIE', '_REQUEST', '_SERVER', '_ENV', '_FILES', 'table_prefix' );

	$input = array_merge( $_GET, $_POST, $_COOKIE, $_SERVER, $_ENV, $_FILES, isset( $_SESSION ) && is_array( $_SESSION ) ? $_SESSION : array() );
	foreach ( $input as $k => $v )
		if ( !in_array( $k, $no_unset ) && isset( $GLOBALS[$k] ) ) {
			unset( $GLOBALS[$k] );
		}
}

// Don't load all of WS when handling a favicon.ico request.
// Instead, send the headers for a zero-length favicon and bail.
// @since 1.0
function ws_favicon_request() {
	if ( '/favicon.ico' == $_SERVER['REQUEST_URI'] ) {
		header('Content-Type: image/vnd.microsoft.icon');
		exit;
	}
}

// Die with a maintenance message when conditions are met.
// Checks for a file in the WS root directory named ".maintenance".
// This file will contain the variable $upgrading, set to the time the file was created.
// If the file was created less than 10 minutes ago, WS enters maintenance mode and displays a message.
// The default message can be replaced by using a drop-in (maintenance.php in the ws-custom/contents directory).
// @since 1.0
// @access private
// @global int $upgrading the unix timestamp marking when upgrading WS began.
function ws_maintenance() {
	if ( ! file_exists( ws_root_abspath() . '/.maintenance' ) || ws_installing() )
		return;
	global $upgrading;
	include( ws_root_abspath() . '/.maintenance' );
	// If the $upgrading timestamp is older than 10 minutes, don't die.
	if ( ( time() - $upgrading ) >= 600 )
		return;
	// Filters whether to enable maintenance mode.
	// This filter runs before it can be used by plugins.
	// It is designed for non-web runtimes.
	// If this filter returns true, maintenance mode will be active and the request will end.
	// If false, the request will be allowed to continue processing even if maintenance mode should be active.
	// @since 1.0
	// @param bool $enable_checks Whether to enable maintenance mode. Default true.
	// @param int  $upgrading     The timestamp set in the .maintenance file.
	if ( ! apply_filters( 'enable_maintenance_mode', true, $upgrading ) ) {
		return;
	}
	echo ws_contents_abspath();
	if ( file_exists( ws_contents_abspath() . '/maintenance.php' ) ) {
		require_once( ws_contents_abspath() . '/maintenance.php' );
		die();
	} else {
		require_once(ws_core_abspath() . '/templates/maintenance.php');
		die();
	}
}

// Start the WS micro-timer.
// @since 1.0
// @access private
// @global float $timestart Unix timestamp set at the beginning of the page load.
// @see timer_stop()
// @return bool Always returns true.
function timer_start() {
	global $timestart;
	$timestart = microtime( true );
	return true;
}

// Retrieve or display the time from the page start to when function is called.
// @since 1.0
// @global float   $timestart Seconds from when timer_start() is called.
// @global float   $timeend   Seconds from when function is called.
// @param int|bool $display   Whether to echo or return the results. Accepts 0|false for return,
// @param int      $precision The number of digits from the right of the decimal to display.
// @return string The "second.microsecond" finished time calculation. The number is formatted for human consumption, both localized and rounded.
function timer_stop( $display = 0, $precision = 3 ) {
	global $timestart, $timeend;
	$timeend = microtime( true );
	$timetotal = $timeend - $timestart;
	$r = ( function_exists( 'number_format_i18n' ) ) ? number_format_i18n( $timetotal, $precision ) : number_format( $timetotal, $precision );
	if ( $display )
		echo $r;
	return $r;
}

// Set PHP error reporting based on WS debug settings.
// Uses three constants: `WS_DEBUG`, `WS_DEBUG_DISPLAY`, and `WS_DEBUG_LOG`.
// All three can be defined in ws-config.php.
// By default, `WS_DEBUG` and `WS_DEBUG_LOG` are set to false, and `WS_DEBUG_DISPLAY` is set to true.
// When `WS_DEBUG` is true, all PHP notices are reported.
// WS will also display internal notices: when a deprecated WS function, function argument, or file is used.
// Deprecated code may be removed from a later version.
// It is strongly recommended that plugin and theme developers use `WS_DEBUG` in their development environments.
// `WS_DEBUG_DISPLAY` and `WS_DEBUG_LOG` perform no function unless `WS_DEBUG` is true.
// When `WS_DEBUG_DISPLAY` is true, WS will force errors to be displayed.
// `WS_DEBUG_DISPLAY` defaults to true.
// Defining it as null prevents WS from changing the global configuration setting.
// Defining `WS_DEBUG_DISPLAY` as false will force errors to be hidden.
// When `WS_DEBUG_LOG` is true, errors will be logged to debug.log in the content directory.
// Errors are never displayed for XML-RPC, REST, and Ajax requests.
// @since 1.0
// @access private
function ws_debug_mode() {
	// Filters whether to allow the debug mode check to occur.
	// This filter runs before it can be used by plugins.
	// It is designed for non-web run-times.
	// Returning false causes the `WS_DEBUG` and related constants to not be checked
	// and the default php values for errors will be used unless you take care to update them yourself.
	// @since 1.0
	// @param bool $enable_debug_mode Whether to enable debug mode checks to occur. Default true.
	if ( ! apply_filters( 'enable_ws_debug_mode_checks', true ) ){
		return;
	}

	if ( WS_DEBUG ) {
		error_reporting( E_ALL );

		if ( WS_DEBUG_DISPLAY )
			ini_set( 'display_errors', 1 );
		elseif ( null !== WS_DEBUG_DISPLAY )
			ini_set( 'display_errors', 0 );

		if ( WS_DEBUG_LOG ) {
			ini_set( 'log_errors', 1 );
			ini_set( 'error_log', ws_contents_abspath() . '/debug.log' );
		}
	} else {
		error_reporting( E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_ERROR | E_WARNING | E_PARSE | E_USER_ERROR | E_USER_WARNING | E_RECOVERABLE_ERROR );
	}

	if ( defined( 'XMLRPC_REQUEST' ) || defined( 'REST_REQUEST' ) || ( defined( 'WS_INSTALLING' ) && WS_INSTALLING ) || ws_doing_ajax() ) {
		@ini_set( 'display_errors', 0 );
	}
}

// Toggle `$_ws_using_ext_object_cache` on and off without directly touching global.
// @since 1.0
// @global bool $_ws_using_ext_object_cache
// @param bool $using Whether external object cache is being used.
// @return bool The current 'using' setting.
function ws_using_ext_object_cache( $using = null ) {
	global $_ws_using_ext_object_cache;
	$current_using = $_ws_using_ext_object_cache;
	if ( null !== $using )
		$_ws_using_ext_object_cache = $using;
	return $current_using;
}

// Start the WS object cache.
// If an object-cache.php file exists in the ws-custom/contents directory,
// it uses that drop-in as an external object cache.
// @since 1.0
// @access private
// @global array $ws_filter Stores all of the filters.
function ws_start_object_cache() {
	global $ws_filter;
	$first_init = false;
 	if ( ! function_exists( 'ws_cache_init' ) ) {
		if ( file_exists( ws_contents_abspath() . '/object-cache.php' ) ) {
			require_once ( ws_contents_abspath() . '/object-cache.php' );
			if ( function_exists( 'ws_cache_init' ) ) {
				ws_using_ext_object_cache( true );
			}
			// Re-initialize any hooks added manually by object-cache.php
			if ( $ws_filter ) {
				$ws_filter = WS_Hook::build_preinitialized_hooks( $ws_filter );
			}
		}
		$first_init = true;
	} elseif ( ! ws_using_ext_object_cache() && file_exists( ws_contents_abspath() . '/object-cache.php' ) ) {
		// Sometimes advanced-cache.php can load object-cache.php before it is loaded here.
		// This breaks the function_exists check above and can result in `$_ws_using_ext_object_cache` being set incorrectly.
		// Double check if an external cache exists.
		ws_using_ext_object_cache( true );
	}
	if ( ! ws_using_ext_object_cache() ) {
		require_once ( ws_core_abspath() . '/cache.php' );
	}
	// If cache supports reset, reset instead of init if already initialized.
	// Reset signals to the cache that global IDs have changed and it may need to update keys and cleanup caches.
	if ( ! $first_init && function_exists( 'ws_cache_switch_to_blog' ) ) {
		ws_cache_switch_to_blog( get_current_blog_id() );
	} elseif ( function_exists( 'ws_cache_init' ) ) {
		ws_cache_init();
	}
	if ( function_exists( 'ws_cache_add_global_groups' ) ) {
		ws_cache_add_global_groups( array( 'users', 'userlogins', 'usermeta', 'user_meta', 'useremail', 'userslugs', 'site-transient', 'site-options', 'blog-lookup', 'blog-details', 'site-details', 'rss', 'global-posts', 'blog-id-cache', 'networks', 'sites' ) );
		ws_cache_add_non_persistent_groups( array( 'counts', 'plugins' ) );
	}
}
// Add magic quotes to `$_GET`, `$_POST`, `$_COOKIE`, and `$_SERVER`.
// Also forces `$_REQUEST` to be `$_GET + $_POST`. If `$_SERVER`,
// `$_COOKIE`, or `$_ENV` are needed, use those superglobals directly.
// @since 1.0
// @access private
function ws_magic_quotes() {
	// If already slashed, strip.
	if ( get_magic_quotes_gpc() ) {
		$_GET    = stripslashes_deep( $_GET    );
		$_POST   = stripslashes_deep( $_POST   );
		$_COOKIE = stripslashes_deep( $_COOKIE );
	}

	// Escape with ws_mysql.
	$_GET    = add_magic_quotes( $_GET    );
	$_POST   = add_magic_quotes( $_POST   );
	$_COOKIE = add_magic_quotes( $_COOKIE );
	$_SERVER = add_magic_quotes( $_SERVER );

	// Force REQUEST to be GET + POST.
	$_REQUEST = array_merge( $_GET, $_POST );
}

// Runs just before PHP shuts down execution.
// @since 1.0
// @access private
function shutdown_action_hook() {
	do_action( 'shutdown' );
	ws_cache_close();
}

// Whether the current request is for an administrative interface page.
// Does not check if the user is an administrator; current_user_can() for checking roles and capabilities.
// @since 1.0
// @global WS_Screen $current_screen
// @return bool True if inside WordPress administration interface, false otherwise.
function is_admin() {
	if ( isset( $GLOBALS['current_screen'] ) )
		return $GLOBALS['current_screen']->in_admin();
	elseif ( defined( 'WS_ADMIN' ) )
		return WS_ADMIN;
	return false;
}

/**
 * If Multisite is enabled.
 *
 * @since 3.0.0
 *
 * @return bool True if Multisite is enabled, false otherwise.
 */
function is_multisite() {
	if ( defined( 'MULTISITE' ) )
		return MULTISITE;

	if ( defined( 'SUBDOMAIN_INSTALL' ) || defined( 'VHOST' ) || defined( 'SUNRISE' ) )
		return true;

	return false;
}

/**
 * Retrieve the current site ID.
 *
 * @since 3.1.0
 *
 * @global int $ws_site_nid
 *
 * @return int Site ID.
 */
function get_current_blog_id() {
	global $ws_site_nid;
	return absint($ws_site_nid);
}

/**
 * Retrieves the current network ID.
 *
 * @since 4.6.0
 *
 * @return int The ID of the current network.
 */
function get_current_network_id() {
	if ( ! is_multisite() ) {
		return 1;
	}

	$current_network = get_network();

	if ( ! isset( $current_network->id ) ) {
		return get_main_network_id();
	}

	return absint( $current_network->id );
}


/**
 * Check or set whether WordPress is in "installation" mode.
 *
 * If the `WS_INSTALLING` constant is defined during the bootstrap, `ws_installing()` will default to `true`.
 *
 * @since 4.4.0
 *
 * @staticvar bool $installing
 *
 * @param bool $is_installing Optional. True to set WS into Installing mode, false to turn Installing mode off.
 *                            Omit this parameter if you only want to fetch the current status.
 * @return bool True if WS is installing, otherwise false. When a `$is_installing` is passed, the function will
 *              report whether WS was in installing mode prior to the change to `$is_installing`.
 */
function ws_installing( $is_installing = null ) {
	static $installing = null;

	// Support for the `WS_INSTALLING` constant, defined before WS is loaded.
	if ( is_null( $installing ) ) {
		$installing = defined( 'WS_INSTALLING' ) && WS_INSTALLING;
	}

	if ( ! is_null( $is_installing ) ) {
		$old_installing = $installing;
		$installing = $is_installing;
		return (bool) $old_installing;
	}

	return (bool) $installing;
}

/**
 * Determines whether the current request is a WordPress Ajax request.
 *
 * @since 4.7.0
 *
 * @return bool True if it's a WordPress Ajax request, false otherwise.
 */
function ws_doing_ajax() {
	/**
	 * Filters whether the current request is a WordPress Ajax request.
	 *
	 * @since 4.7.0
	 *
	 * @param bool $ws_doing_ajax Whether the current request is a WordPress Ajax request.
	 */
	return apply_filters( 'ws_doing_ajax', defined( 'DOING_AJAX' ) && DOING_AJAX );
}

/**
 * Determines whether the current request is a WordPress cron request.
 *
 * @since 4.8.0
 *
 * @return bool True if it's a WordPress cron request, false otherwise.
 */
function ws_doing_cron() {
	/**
	 * Filters whether the current request is a WordPress cron request.
	 *
	 * @since 4.8.0
	 *
	 * @param bool $ws_doing_cron Whether the current request is a WordPress cron request.
	 */
	return apply_filters( 'ws_doing_cron', defined( 'DOING_CRON' ) && DOING_CRON );
}

/**
 * Check whether variable is a WordPress Error.
 *
 * Returns true if $thing is an object of the WS_Error class.
 *
 * @since 2.1.0
 *
 * @param mixed $thing Check if unknown variable is a WS_Error object.
 * @return bool True, if WS_Error. False, if not WS_Error.
 */
function is_ws_error( $thing ) {
	return ( $thing instanceof WS_Error );
}

/**
 * Determines whether file modifications are allowed.
 *
 * @since 4.8.0
 *
 * @param string $context The usage context.
 * @return bool True if file modification is allowed, false otherwise.
 */
function ws_is_file_mod_allowed( $context ) {
	/**
	 * Filters whether file modifications are allowed.
	 *
	 * @since 4.8.0
	 *
	 * @param bool   $file_mod_allowed Whether file modifications are allowed.
	 * @param string $context          The usage context.
	 */
	return apply_filters( 'file_mod_allowed', ! defined( 'DISALLOW_FILE_MODS' ) || ! DISALLOW_FILE_MODS, $context );
}

/**
 * Start scraping edited file errors.
 *
 * @since 4.9.0
 */
function ws_start_scraping_edited_file_errors() {
	if ( ! isset( $_REQUEST['ws_scrape_key'] ) || ! isset( $_REQUEST['ws_scrape_nonce'] ) ) {
		return;
	}
	$key = substr( sanitize_key( ws_unslash( $_REQUEST['ws_scrape_key'] ) ), 0, 32 );
	$nonce = ws_unslash( $_REQUEST['ws_scrape_nonce'] );

	if ( get_transient( 'scrape_key_' . $key ) !== $nonce ) {
		echo "###### ws_scraping_result_start:$key ######";
		echo ws_json_encode( array(
			'code' => 'scrape_nonce_failure',
			'message' => __( 'Scrape nonce check failed. Please try again.' ),
		) );
		echo "###### ws_scraping_result_end:$key ######";
		die();
	}
	register_shutdown_function( 'ws_finalize_scraping_edited_file_errors', $key );
}

/**
 * Finalize scraping for edited file errors.
 *
 * @since 4.9.0
 *
 * @param string $scrape_key Scrape key.
 */
function ws_finalize_scraping_edited_file_errors( $scrape_key ) {
	$error = error_get_last();
	echo "\n###### ws_scraping_result_start:$scrape_key ######\n";
	if ( ! empty( $error ) && in_array( $error['type'], array( E_CORE_ERROR, E_COMPILE_ERROR, E_ERROR, E_PARSE, E_USER_ERROR, E_RECOVERABLE_ERROR ), true ) ) {
		$error = str_replace( ABSPATH, '', $error );
		echo ws_json_encode( $error );
	} else {
		echo ws_json_encode( true );
	}
	echo "\n###### ws_scraping_result_end:$scrape_key ######\n";
}
