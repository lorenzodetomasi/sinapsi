<?php
// Locale: i.e. en_US
// Language: IETF BCP 47 standard, i.e. en-US

// Retrieves the current locale.
// If the locale is set, then it will filter the locale in the {@see 'locale'} filter hook and return the value.
// If the locale is not set already, then the WPLANG constant is used if it is defined. Then it is filtered through the {@see 'locale'} filter hook and the value for the locale global set and the locale is returned.
// The process to get the locale should only be done once, but the locale will always be filtered using the {@see 'locale'} hook.
// @since 0.0.1
// @global string $locale
// @global string $wp_local_package
// @return string The locale of the blog or from the {@see 'locale'} hook.
function ws_locales() {
	global $ws_query, $rewrite_rule, $ws_user;//$ws_local_package,
	$locales = array();
	// In inverse priority order:
	$locales['fallback'] = 'en_US';
	if ( defined( 'WS_DEFAULT_LOCALE' ) ) {
		$locales['default'] = WS_DEFAULT_LOCALE;
	}
	// Detect user locale
	// L'intestazione può non arrivare — i motori di ricerca e i programmi a riga di
	// comando spesso non la mandano — e chiederla senza guardare se c'è stampava un
	// avviso PHP in cima a ogni pagina, prima ancora del DOCTYPE.
	$accettate = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? (string)$_SERVER['HTTP_ACCEPT_LANGUAGE'] : '';
	if($accettate !== '' and !empty(locale_accept_from_http($accettate))){
		$locales['user_http'] = locale_accept_from_http($accettate);
	}
	if(!empty($ws_user['selected_locale'])){
		$locales['user_selected'] = $ws_user['selected_locale'];
	}
	if(!empty($ws_query['locale'])){
		$locales['ws_query'] = $ws_query['locale'];
	}
	if(!empty($rewrite_rule->inLanguage)){
		$locales['content'] = str_replace('-', '_', $rewrite_rule->inLanguage->__toString());
	}
	/*
		// If multisite, check options.
		if ( is_multisite() ) {
			// Don't check blog option when installing.
			if ( wp_installing() || ( false === $ms_locale = get_option( 'WPLANG' ) ) ) {
				$ms_locale = get_site_option( 'WPLANG' );
			}

			if ( $ms_locale !== false ) {
				$locale = $ms_locale;
			}
		} else {
			$db_locale = get_option( 'WPLANG' );
			if ( $db_locale !== false ) {
				$locale = $db_locale;
			}
		}
	*/
	return $locales;
}
function ws_locale() {
	global $ws_locales;
	$locale = end($ws_locales);
	// This filter is documented in ws-core/l10n.php
	return apply_filters( 'locale', $locale );
}
function ws_lang($locale = null, $args = array()){
	// Convert locale to IETF BCP 47 standard <https://tools.ietf.org/html/bcp47>
	// i.e. from en_US to en-US
	$default_args = array(
		'output' => 'full', // full | primary-language
	);
	$args = array_intersect_key( $args, $default_args );
	$args = array_merge( $default_args, $args );
	if(empty($locale)){
		$locale = ws_locale();
	}
	if($args['output'] == 'primary-language'){
		$lang = strtok($locale, '_');
	} else if($args['output'] == 'full'){
		$lang = str_replace('_', '-', $locale);
	}
	return $lang;
}

// Retrieves the locale of a user.
// If the user has a locale set to a non-empty string then it will be returned. Otherwise it returns the locale of ws_locale().
// @since 1.0
// @param int|WP_User $user_id User's ID or a WS_User object. Defaults to current user.
// @return string The locale of the user.
function get_user_locale( $user_id = 0 ) {
	$user = false;
	if ( 0 === $user_id && function_exists( 'wp_get_current_user' ) ) {
		$user = wp_get_current_user();
	} elseif ( $user_id instanceof WP_User ) {
		$user = $user_id;
	} elseif ( $user_id && is_numeric( $user_id ) ) {
		$user = get_user_by( 'id', $user_id );
	}

	if ( ! $user ) {
		return ws_locale();
	}

	$locale = $user->locale;
	return $locale ? $locale : ws_locale();
}
/* Was in ws-settings.php
// Load the default text localization domain.
load_default_textdomain();
$locale = ws_locale();
$locale_file = WS_LANGUAGES_RELPATH . "/$locale.php";
if ( ( 0 === validate_file( $locale ) ) && is_readable( $locale_file ) )
	require( $locale_file );
unset( $locale_file );
// WS Locale object for loading locale domain date and various strings.
// @global WS_Locale $ws_locale
// @since 0.0.1
 */
/*
//  WordPress Locale Switcher object for switching locales.
// @since 4.7.0
// @global WP_Locale_Switcher $wp_locale_switcher WordPress locale switcher object.
$GLOBALS['wp_locale_switcher'] = new WP_Locale_Switcher();
$GLOBALS['wp_locale_switcher']->init();
*/

// Set internal encoding.
// In most cases the default internal encoding is latin1, which is of no use, since we want to use the `mb_` functions for `utf-8` strings.
// @since 0.0.1
// @access private
function ws_set_internal_encoding() {
	if ( function_exists( 'mb_internal_encoding' ) ) {
		if(get_option( 'blog_charset' )){
			$charset = get_option( 'blog_charset' );
		} else {
			$charset = WS_CHARSET;
		}
		if ( ! $charset || ! @mb_internal_encoding( $charset ) )
			mb_internal_encoding( 'UTF-8' );
	}
}

// Load of translations.
// @since 0.0.1
// @access private
// @global $ws_languages: Array of arrays: [domain], [ws_root]/ws-custom/languages/[name]-[locale].
// @staticvar bool $loaded
function ws_load_translations() {
	global $ws_languages, $ws_locales;

	static $loaded = false;
	if ( $loaded )
		return;
	$loaded = true;

	if ( function_exists( 'did_action' ) && did_action( 'init' ) )
		return;

	// Translation and localization
	require_once ws_core_abspath() . '/pomo/mo.php';
	// Load the L10n library.
	require_once ws_core_abspath() . '/l10n.php';
	require_once ws_core_abspath() . '/class-ws-locale.php';
	require_once ws_core_abspath() . '/class-ws-locale-switcher.php';

	while ( true ) {
		if ( ! $ws_locales )
			break;

		$ws_languages_abspaths = ws_languages_abspaths();
		if ( ! $ws_languages_abspaths )
			break;
		$ws_languages_abspaths = array_unique( $ws_languages_abspaths );
		$ws_locale = ws_locale();
		foreach ( $ws_languages_abspaths as $ws_languages_abspath ) {
			if ( file_exists( $ws_languages_abspath . '/translation-constants-'.$ws_locale . '.php' )){
				require_once $ws_languages_abspath . '/translation-constants-'.$ws_locale . '.php';
				ws_human_translation_constants();
			}
			foreach ($ws_languages as $ws_language) {
				if ( file_exists( $ws_languages_abspath .'/'. $ws_language['name'] . '-' . $ws_locale . '.mo' ) ) {
					load_textdomain( $ws_language['domain'], $ws_languages_abspath .'/'. $ws_language['name'] . '-' . $ws_locale . '.mo' );
				}
			}
		}
		break;
	}
	$ws_locale = new WS_Locale();
}
?>
