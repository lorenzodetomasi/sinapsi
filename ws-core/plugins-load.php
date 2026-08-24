<?php
// Retrieve an array of must-use plugin files.
// The default directory is ws-custom/plugins/.
// To change the default directory manually, define `WSMU_PLUGIN_DIR` and `WSMU_PLUGIN_URL` in wp-config.php.
// @since 1.0
// @access private
// @return array Files to include.
function ws_get_mu_plugins() {
	$mu_plugins = array();
	if ( !is_dir( WSMU_PLUGIN_DIR ) )
		return $mu_plugins;
	if ( ! $dh = opendir( WSMU_PLUGIN_DIR ) )
		return $mu_plugins;
	while ( ( $plugin = readdir( $dh ) ) !== false ) {
		if ( substr( $plugin, -4 ) == '.php' )
			$mu_plugins[] = WSMU_PLUGIN_DIR . '/' . $plugin;
	}
	closedir( $dh );
	sort( $mu_plugins );

	return $mu_plugins;
}
// Retrieve an array of active and valid plugin files.
// While upgrading or installing WS, no plugins are returned.
// The default directory is ws-custom/plugins.
// To change the default directory manually, define `WS_PLUGIN_DIR` and `WS_PLUGIN_URL` in wp-config.php.
// @since 1.0
// @access private
// @return array Files.
function ws_get_active_and_valid_plugins() {
	$plugins = array();
	$active_plugins = (array) get_option( 'active_plugins', array() );

	// Check for hacks file if the option is enabled
	if ( get_option( 'hack_file' ) && file_exists( ABSPATH . 'my-hacks.php' ) ) {
		_deprecated_file( 'my-hacks.php', '1.5.0' );
		array_unshift( $plugins, ABSPATH . 'my-hacks.php' );
	}

	if ( empty( $active_plugins ) || ws_installing() )
		return $plugins;

	$network_plugins = is_multisite() ? ws_get_active_network_plugins() : false;

	foreach ( $active_plugins as $plugin ) {
		if ( ! validate_file( $plugin ) // $plugin must validate as file
			&& '.php' == substr( $plugin, -4 ) // $plugin must end with '.php'
			&& file_exists( WS_PLUGIN_DIR . '/' . $plugin ) // $plugin must exist
			// not already included as a network plugin
			&& ( ! $network_plugins || ! in_array( WS_PLUGIN_DIR . '/' . $plugin, $network_plugins ) )
			)
		$plugins[] = WS_PLUGIN_DIR . '/' . $plugin;
	}
	return $plugins;
}
?>
