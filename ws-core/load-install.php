<?php
// Redirect to the installer if WS is not installed.
// Dies with an error message when Multisite is enabled.
// @since 1.0
// @access private
function ws_not_installed() {
	if ( is_multisite() ) {
		if ( ! is_blog_installed() && ! ws_installing() ) {
			nocache_headers();

			ws_die( __( 'The site you have requested is not installed properly. Please contact the system administrator.' ) );
		}
	} elseif ( ! is_blog_installed() && ! ws_installing() ) {
		nocache_headers();

		require( ws_core_abspath() . '/kses.php' );
		require( ws_core_abspath() . '/pluggable.php' );
		require( ws_core_abspath() . '/formatting.php' );

		$link = ws_guess_url() . '/ws-admin/install.php';

		ws_redirect( $link );
		die();
	}
}
?>
