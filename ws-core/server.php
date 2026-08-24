<?php
// Return the HTTP protocol sent by the server.
// @return string The HTTP protocol. Default: HTTP/1.0.
function ws_get_server_protocol() {
	$protocol = $_SERVER['SERVER_PROTOCOL'];
	if ( ! in_array( $protocol, array( 'HTTP/1.1', 'HTTP/2', 'HTTP/2.0' ) ) ) {
		$protocol = 'HTTP/1.0';
	}
	return $protocol;
}

// Fix `$_SERVER` variables for various setups.
// @since 0.0.1
// @access private
// @global string $PHP_SELF The filename of the currently executing script, relative to the document root.
function ws_fix_server_vars() {
	global $PHP_SELF;

	$default_server_values = array(
		'SERVER_SOFTWARE' => '',
		'REQUEST_URI' => '',
	);

	$_SERVER = array_merge( $default_server_values, $_SERVER );

	// Fix for IIS when running with PHP ISAPI
	if ( empty( $_SERVER['REQUEST_URI'] ) || ( PHP_SAPI != 'cgi-fcgi' && preg_match( '/^Microsoft-IIS\//', $_SERVER['SERVER_SOFTWARE'] ) ) ) {

		// IIS Mod-Rewrite
		if ( isset( $_SERVER['HTTP_X_ORIGINAL_URL'] ) ) {
			$_SERVER['REQUEST_URI'] = $_SERVER['HTTP_X_ORIGINAL_URL'];
		}
		// IIS Isapi_Rewrite
		elseif ( isset( $_SERVER['HTTP_X_REWRITE_URL'] ) ) {
			$_SERVER['REQUEST_URI'] = $_SERVER['HTTP_X_REWRITE_URL'];
		} else {
			// Use ORIG_PATH_INFO if there is no PATH_INFO
			if ( !isset( $_SERVER['PATH_INFO'] ) && isset( $_SERVER['ORIG_PATH_INFO'] ) )
				$_SERVER['PATH_INFO'] = $_SERVER['ORIG_PATH_INFO'];

			// Some IIS + PHP configurations puts the script-name in the path-info (No need to append it twice)
			if ( isset( $_SERVER['PATH_INFO'] ) ) {
				if ( $_SERVER['PATH_INFO'] == $_SERVER['SCRIPT_NAME'] )
					$_SERVER['REQUEST_URI'] = $_SERVER['PATH_INFO'];
				else
					$_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'] . $_SERVER['PATH_INFO'];
			}

			// Append the query string if it exists and isn't null
			if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
				$_SERVER['REQUEST_URI'] .= '?' . $_SERVER['QUERY_STRING'];
			}
		}
	}

	// Fix for PHP as CGI hosts that set SCRIPT_FILENAME to something ending in php.cgi for all requests
	if ( isset( $_SERVER['SCRIPT_FILENAME'] ) && ( strpos( $_SERVER['SCRIPT_FILENAME'], 'php.cgi' ) == strlen( $_SERVER['SCRIPT_FILENAME'] ) - 7 ) )
		$_SERVER['SCRIPT_FILENAME'] = $_SERVER['PATH_TRANSLATED'];

	// Fix for Dreamhost and other PHP as CGI hosts
	if ( strpos( $_SERVER['SCRIPT_NAME'], 'php.cgi' ) !== false )
		unset( $_SERVER['PATH_INFO'] );

	// Fix empty PHP_SELF
	$PHP_SELF = $_SERVER['PHP_SELF'];
	if ( empty( $PHP_SELF ) )
		$_SERVER['PHP_SELF'] = $PHP_SELF = preg_replace( '/(\?.*)?$/', '', $_SERVER["REQUEST_URI"] );
}

// Check for the required PHP version, and the MySQL extension or
// a database drop-in.
// Dies if requirements are not met.
// @since 0.0.1
// @access private
// @global string $required_php_version The required PHP version string.
// @global string $ws_version           The WS version string.
function ws_check_php_mysql_versions() {
	global $required_php_version, $ws_version;
	$php_version = phpversion();

	if ( version_compare( $required_php_version, $php_version, '>' ) ) {
		$protocol = ws_get_server_protocol();
		header( sprintf( '%s 500 Internal Server Error', $protocol ), true, 500 );
		header( 'Content-Type: text/html; charset=utf-8' );
		// translators: 1: Current PHP version number, 2: WS version number, 3: Minimum required PHP version number
		die( sprintf( __( 'Your server is running PHP version %1$s but WS %2$s requires at least %3$s.' ), $php_version, $ws_version, $required_php_version ) );
	}

	if ( ! extension_loaded( 'mysql' ) && ! extension_loaded( 'mysqli' ) && ! extension_loaded( 'mysqlnd' ) && ! file_exists( WS_CUSTOM_DIR . '/db.php' ) ) {
		$protocol = ws_get_server_protocol();
		header( sprintf( '%s 500 Internal Server Error', $protocol ), true, 500 );
		header( 'Content-Type: text/html; charset=utf-8' );
		die( __( 'Your PHP installation appears to be missing the MySQL extension which is required by WS.' ) );
	}
}

// Determines if SSL is used.
// @since 0.0.1
// @return bool True if SSL, otherwise false.
function is_ssl($output = 'bool') {
	// string $output bool | http
	if ( isset( $_SERVER['HTTPS'] ) ) {
		if ( 'on' == strtolower( $_SERVER['HTTPS'] ) ) {
			$bool = true;
		}

		if ( '1' == $_SERVER['HTTPS'] ) {
			$bool = true;
		}
	} elseif ( isset($_SERVER['SERVER_PORT'] ) && ( '443' == $_SERVER['SERVER_PORT'] ) ) {
		$bool = true;
	} else {
		$bool = false;
	}
	if($output == 'bool'){
		return $bool;
	} else if($output == 'http'){
		if($bool === true){
			return 'https:';
		} else if($bool === false){
			return 'http:';
		}
	}
}
// Test if a given path is a stream URL
// @since 0.0.1
// @param string $path The resource path or URL.
// @return bool True if the path is a stream URL.
function ws_is_stream( $path_or_url ) {
	if ( false === strpos( $path_or_url, '://' ) ) {
		// $path_or_url isn't a stream
		return false;
	}

	$wrappers    = stream_get_wrappers();
	$wrappers    = array_map( 'preg_quote', $wrappers );
	$wrappers_re = '(' . join( '|', $wrappers ) . ')';

	return preg_match( "!^$wrappers_re://!", $path ) === 1;
}
// File Paths
// Normalize a relative path.
function ws_normalize_relpath( $relpath ) {
	// Removes first "/" from $relpath
	$relpath = ltrim($relpath, '/');
	// Removes last "/" from $relpath
	$relpath = rtrim($relpath, '/');
	return $relpath;
}
// Normalize a filesystem path.
// On windows systems, replaces backslashes with forward slashes and forces upper-case drive letters.
// Allows for two leading slashes for Windows network shares,
// but ensures that all other duplicate slashes are reduced to a single.
// @since 0.0.1
// @param string $path Path to normalize.
// @return string Normalized path.
function ws_normalize_abspath( $abspath ) {
	$wrapper = '';
	if ( ws_is_stream( $abspath ) ) {
		list( $wrapper, $abspath ) = explode( '://', $abspath, 2 );
		$wrapper .= '://';
	}

	// Standardise all paths to use /
	$abspath = str_replace( '\\', '/', $abspath );

	// Replace multiple slashes down to a singular, allowing for network shares having two slashes.
	$abspath = preg_replace( '|(?<=.)/+|', '/', $abspath );

	// Windows paths should uppercase the drive letter
	if ( ':' === substr( $abspath, 1, 1 ) ) {
		$abspath = ucfirst( $abspath );
	}

	return $wrapper . $abspath;
}

function ws_protocol(){
	if(is_ssl()){
		return 'https://';
	} else {
		return 'http://';
	}
}

// Converts an abspath in an url
function abspath2url($abspath) {
  return ws_root_url().str_replace(ws_root_abspath(), '', $abspath);
}
//
// @since 0.0.1
// @return string Ip address.
function real_client_ip_address() {
	if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
		$ip=$_SERVER['HTTP_CLIENT_IP'];
	} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
		$ip=$_SERVER['HTTP_X_FORWARDED_FOR'];
	} else {
		$ip=$_SERVER['REMOTE_ADDR'];
	}
	return $ip;
}
?>
