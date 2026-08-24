<?php
/**
 * Whether the current request is for an administrative interface page.
 *
 * Does not check if the user is an administrator; current_user_can()
 * for checking roles and capabilities.
 *
 * @since 1.5.1
 *
 * @global WS_Screen $current_screen
 *
 * @return bool True if inside WordPress administration interface, false otherwise.
 */
function is_admin() {
	if ( isset( $GLOBALS['current_screen'] ) )
		return $GLOBALS['current_screen']->in_admin();
	elseif ( defined( 'WS_ADMIN' ) )
		return WS_ADMIN;

	return false;
}

/**
 * Whether the current request is for a site's admininstrative interface.
 *
 * e.g. `/wp-admin/`
 *
 * Does not check if the user is an administrator; current_user_can()
 * for checking roles and capabilities.
 *
 * @since 3.1.0
 *
 * @global WS_Screen $current_screen
 *
 * @return bool True if inside WordPress blog administration pages.
 */
function is_blog_admin() {
	if ( isset( $GLOBALS['current_screen'] ) )
		return $GLOBALS['current_screen']->in_admin( 'site' );
	elseif ( defined( 'WS_BLOG_ADMIN' ) )
		return WS_BLOG_ADMIN;

	return false;
}

/**
 * Whether the current request is for the network administrative interface.
 *
 * e.g. `/wp-admin/network/`
 *
 * Does not check if the user is an administrator; current_user_can()
 * for checking roles and capabilities.
 *
 * @since 3.1.0
 *
 * @global WS_Screen $current_screen
 *
 * @return bool True if inside WordPress network administration pages.
 */
function is_network_admin() {
	if ( isset( $GLOBALS['current_screen'] ) )
		return $GLOBALS['current_screen']->in_admin( 'network' );
	elseif ( defined( 'WS_NETWORK_ADMIN' ) )
		return WS_NETWORK_ADMIN;

	return false;
}

/**
 * Whether the current request is for a user admin screen.
 *
 * e.g. `/wp-admin/user/`
 *
 * Does not inform on whether the user is an admin! Use capability
 * checks to tell if the user should be accessing a section or not
 * current_user_can().
 *
 * @since 3.1.0
 *
 * @global WS_Screen $current_screen
 *
 * @return bool True if inside WordPress user administration pages.
 */
function is_user_admin() {
	if ( isset( $GLOBALS['current_screen'] ) )
		return $GLOBALS['current_screen']->in_admin( 'user' );
	elseif ( defined( 'WS_USER_ADMIN' ) )
		return WS_USER_ADMIN;

	return false;
}
?>
