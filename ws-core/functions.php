<?php
// Main WS API.
// @package WS

// Merge user defined arguments into defaults array.
// This function is used to allow for both string or array to be merged into another array.
// @since 0.0.1
// @param string|array|object $args     Value to merge with $defaults.
// @param array               $defaults Optional. Array that serves as the defaults. Default empty.
// @return array Merged user defined values with defaults.
function ws_parse_args( $args, $default_args = '' ) {
	if ( is_object( $args ) )
		$r = get_object_vars( $args );
	elseif ( is_array( $args ) )
		$r =& $args;
	else
		ws_parse_str( $args, $r );

	if ( is_array( $default_args ) )
		return array_merge( $default_args, $r );
	return $r;
}
function is_email( $email ) {
	// Test for the minimum length the email can be
	if ( strlen( $email ) < 6 ) {
		// Filters whether an email address is valid.
		// This filter is evaluated under several different contexts,
		// such as 'email_too_short', 'email_no_at', 'local_invalid_chars',
		// 'domain_period_sequence', 'domain_period_limits', 'domain_no_periods',
		// 'sub_hyphen_limits', 'sub_invalid_chars', or no specific context.
		// @since 0.0.1
		// @param bool   $is_email Whether the email address has passed the is_email() checks. Default false.
		// @param string $email    The email address being checked.
		// @param string $context  Context under which the email was tested.
		return apply_filters( 'is_email', false, $email, 'email_too_short' );
	}
	// Test for an @ character after the first position
	if ( strpos( $email, '@', 1 ) === false ) {
		// This filter is documented in ws-core/formatting.php
		return apply_filters( 'is_email', false, $email, 'email_no_at' );
	}
	// Split out the local and domain parts
	list( $local, $domain ) = explode( '@', $email, 2 );
	// LOCAL PART
	// Test for invalid characters
	if ( !preg_match( '/^[a-zA-Z0-9!#$%&\'*+\/=?^_`{|}~\.-]+$/', $local ) ) {
		return apply_filters( 'is_email', false, $email, 'local_invalid_chars' );
	}

	// DOMAIN PART
	// Test for sequences of periods
	if ( preg_match( '/\.{2,}/', $domain ) ) {
		return apply_filters( 'is_email', false, $email, 'domain_period_sequence' );
	}

	// Test for leading and trailing periods and whitespace
	if ( trim( $domain, " \t\n\r\0\x0B." ) !== $domain ) {
		return apply_filters( 'is_email', false, $email, 'domain_period_limits' );
	}

	// Split the domain into subs
	$subs = explode( '.', $domain );

	// Assume the domain will have at least two subs
	if ( 2 > count( $subs ) ) {
		return apply_filters( 'is_email', false, $email, 'domain_no_periods' );
	}

	// Loop through each sub
	foreach ( $subs as $sub ) {
		// Test for leading and trailing hyphens and whitespace
		if ( trim( $sub, " \t\n\r\0\x0B-" ) !== $sub ) {
			return apply_filters( 'is_email', false, $email, 'sub_hyphen_limits' );
		}

		// Test for invalid characters
		if ( !preg_match('/^[a-z0-9-]+$/i', $sub ) ) {
			return apply_filters( 'is_email', false, $email, 'sub_invalid_chars' );
		}
	}

	// Congratulations your email made it!
	return apply_filters( 'is_email', $email, $email, null );
}

// Strings
// Based on https://github.com/delight-im/PHP-Str
function is_url($string){
	return true;
}
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        return $needle !== '' && mb_strpos($haystack, $needle) !== false;
    }
}
function get_string_between($string, $start, $end){
    $string = ' ' . $string;
    $ini = strpos($string, $start);
    if ($ini == 0) return '';
    $ini += strlen($start);
    $len = strpos($string, $end, $ini) - $ini;
    return substr($string, $ini, $len);
}
// Returns if a string starts with a specified string
if (!function_exists('str_starts_with')) {
	function str_starts_with($string, $start){
		return substr_compare($string, $start, 0, strlen($start)) === 0;
	}
}
// Returns if a string ends with a specified string
if (!function_exists('str_ends_with')) {
  function str_ends_with($str, $end) {
    return (@substr_compare($str, $end, -strlen($end))==0);
  }
}
function remove_start($string, $start){
	if (substr($string, 0, strlen($start)) == $start) {
    return substr($string, strlen($start));
	}
}
function explode_and_remove_last($separator, $string){
	$parts  = explode($separator, $string);
	array_pop($parts);
	return implode($separator, $parts);
}
function remove_end($string, $end){
	return rtrim($string, $end);
}
// Formatting
function remove_whitespaces($string, $target = 'all'){
	if($target == 'all'){
		$return = preg_replace('/\s/', '', $string);
	} else if($target = 'multiple'){
		// Replaces multiple whitespaces with a single whitespace
		$return = preg_replace('/\s+/', ' ', $string);
		$return = trim(preg_replace('/\s+/', ' ', $sring));
	}
	return $return;
}
// FILES
function get_files_abspaths($args = array()){
	$defaults = array(
		'dir_abspath' => false,
		'extension' => false,
		'filename' => false,
	);
	$args = array_intersect_key( $args, $defaults );
	$args = ws_parse_args( $args, $defaults );
	$files_abspaths = array();
	foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($args["dir_abspath"])) as $result){
		$file_pathinfo = pathinfo($result);
		if ($args["filename"] and $args["extension"]){
			if ($file_pathinfo["filename"] == $args["filename"] and $file_pathinfo["extension"] == $args["extension"]){
				$files_abspaths[] = $result->getPathname();
			}
		} else if ($args["filename"]) {
			if ($file_pathinfo["filename"] == $args["filename"]){
				$files_abspaths[] = $result->getPathname();
			}
		} else if ($args["extension"]) {
			if ($file_pathinfo["extension"] == $args["extension"]) {
	  		$files_abspaths[] = $result->getPathname();
			}
		}
	}
	return $files_abspaths;
}

// WS Dies
// Kill WS execution and display HTML message with error message.
// This function complements the `die()` PHP function.
// The difference is that HTML will be displayed to the user.
// It is recommended to use this function only when the execution should not continue any further.
// It is not recommended to call this function very often, and try to handle as many errors as possible silently or more gracefully.
// As a shorthand, the desired HTTP response code may be passed as an integer to the `$title` parameter (the default title would apply) or the `$args` parameter.
// @since 0.1
// @param string|WS_Error		$message	Optional. Error message.
// 																		If this is a WS_Error object, and not an Ajax or XML-RPC request, the error's messages are used.
// 																		Default empty.
// @param string|int				$title		Optional. Error title.
// 																		If `$message` is a `WS_Error` object, error data with the key 'title' may be used to specify the title.
// 																		If `$title` is an integer, then it is treated as the response code.
// 																		Default empty.
// @param string|array|int $args {		Optional. Arguments to control behavior.
// 																		If `$args` is an integer, then it is treated as the response code.
// 																		Default empty array.
// 	@type int			$response						The HTTP response code.
// 																		Default 200 for Ajax requests, 500 otherwise.
// 	@type bool			$back_link					Whether to include a link to go back. Default false.
// 	@type string		$text_direction			The text direction. This is only useful internally, when WS is still loading and the site's locale is not set up yet.
// 																		Accepts 'rtl'. Default is the value of is_rtl().
// }
function ws_die( $message = '', $title = '', $args = array() ) {
	if ( is_int( $args ) ) {
		$args = array( 'response' => $args );
	} elseif ( is_int( $title ) ) {
		$args  = array( 'response' => $title );
		$title = '';
	}
	if ( ws_doing_ajax() ) {
		// Filters the callback for killing WS execution for Ajax requests.
		// @since 0.1
		// @param callable $function Callback function name.
		$function = apply_filters( 'ws_die_ajax_handler', '_ajax_ws_die_handler' );
	} elseif ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
		// Filters the callback for killing WS execution for XML-RPC requests.
		// @since 0.1
		// @param callable $function Callback function name.
		$function = apply_filters( 'ws_die_xmlrpc_handler', '_xmlrpc_ws_die_handler' );
	} else {
		// Filters the callback for killing WordPress execution for all non-Ajax, non-XML-RPC requests.
		// @since 0.1
		// @param callable $function Callback function name.
		$function = apply_filters( 'ws_die_handler', '_default_ws_die_handler' );
	}
	call_user_func( $function, $message, $title, $args );
}

// Kills WS execution and display HTML message with error message.
// This is the default handler for ws_die if you want a custom one for your site then you can overload using the {@see 'ws_die_handler'} filter in ws_die().
// @since 0.1
// @access private
// @param string|WS_Error $message Error message or WS_Error object.
// @param string          $title   Optional. Error title. Default empty.
// @param string|array    $args    Optional. Arguments to control behavior. Default empty array.
function _default_ws_die_handler( $message, $title = '', $args = array() ) {
	$defaults = array( 'response' => 500 );
	$r = ws_parse_args($args, $defaults);
	$have_gettext = function_exists('__');
	if ( function_exists( 'is_ws_error' ) && is_ws_error( $message ) ) {
		if ( empty( $title ) ) {
			$error_data = $message->get_error_data();
			if ( is_array( $error_data ) && isset( $error_data['title'] ) )
				$title = $error_data['title'];
		}
		$errors = $message->get_error_messages();
		switch ( count( $errors ) ) {
		case 0 :
			$message = '';
			break;
		case 1 :
			$message = "<p>{$errors[0]}</p>";
			break;
		default :
			$message = "<ul>\n\t\t<li>" . join( "</li>\n\t\t<li>", $errors ) . "</li>\n\t</ul>";
			break;
		}
	} elseif ( is_string( $message ) ) {
		$message = "<p>$message</p>";
	}
	if ( isset( $r['back_link'] ) && $r['back_link'] ) {
		$back_text = $have_gettext? __('&laquo; Back') : '&laquo; Back';
		$message .= "\n<p><a href='javascript:history.back()'>$back_text</a></p>";
	}
	if ( ! did_action( 'admin_head' ) ) :
		if ( !headers_sent() ) {
			status_header( $r['response'] );
			nocache_headers();
			header( 'Content-Type: text/html; charset=utf-8' );
		}
		if ( empty($title) )
			$title = $have_gettext ? __('WS &rsaquo; Error') : 'WS &rsaquo; Error';
		$text_direction = 'ltr';
		if ( isset($r['text_direction']) && 'rtl' == $r['text_direction'] )
			$text_direction = 'rtl';
		elseif ( function_exists( 'is_rtl' ) && is_rtl() )
			$text_direction = 'rtl';
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" <?php if ( function_exists( 'language_attributes' ) && function_exists( 'is_rtl' ) ) language_attributes(); else echo "dir='$text_direction'"; ?>>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="viewport" content="width=device-width">
	<?php
	if ( function_exists( 'ws_no_robots' ) ) {
		ws_no_robots();
	}
	?>
	<title><?php echo $title ?></title>
	<style type="text/css">
		html {
			background: #f1f1f1;
		}
		body {
			background: #fff;
			color: #444;
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
			margin: 2em auto;
			padding: 1em 2em;
			max-width: 700px;
			-webkit-box-shadow: 0 1px 3px rgba(0,0,0,0.13);
			box-shadow: 0 1px 3px rgba(0,0,0,0.13);
		}
		h1 {
			border-bottom: 1px solid #dadada;
			clear: both;
			color: #666;
			font-size: 24px;
			margin: 30px 0 0 0;
			padding: 0;
			padding-bottom: 7px;
		}
		#error-page {
			margin-top: 50px;
		}
		#error-page p {
			font-size: 14px;
			line-height: 1.5;
			margin: 25px 0 20px;
		}
		#error-page code {
			font-family: Consolas, Monaco, monospace;
		}
		ul li {
			margin-bottom: 10px;
			font-size: 14px ;
		}
		a {
			color: #0073aa;
		}
		a:hover,
		a:active {
			color: #00a0d2;
		}
		a:focus {
			color: #124964;
		    -webkit-box-shadow:
		    	0 0 0 1px #5b9dd9,
				0 0 2px 1px rgba(30, 140, 190, .8);
		    box-shadow:
		    	0 0 0 1px #5b9dd9,
				0 0 2px 1px rgba(30, 140, 190, .8);
			outline: none;
		}
		.button {
			background: #f7f7f7;
			border: 1px solid #ccc;
			color: #555;
			display: inline-block;
			text-decoration: none;
			font-size: 13px;
			line-height: 26px;
			height: 28px;
			margin: 0;
			padding: 0 10px 1px;
			cursor: pointer;
			-webkit-border-radius: 3px;
			-webkit-appearance: none;
			border-radius: 3px;
			white-space: nowrap;
			-webkit-box-sizing: border-box;
			-moz-box-sizing:    border-box;
			box-sizing:         border-box;

			-webkit-box-shadow: 0 1px 0 #ccc;
			box-shadow: 0 1px 0 #ccc;
		 	vertical-align: top;
		}

		.button.button-large {
			height: 30px;
			line-height: 28px;
			padding: 0 12px 2px;
		}

		.button:hover,
		.button:focus {
			background: #fafafa;
			border-color: #999;
			color: #23282d;
		}

		.button:focus  {
			border-color: #5b9dd9;
			-webkit-box-shadow: 0 0 3px rgba( 0, 115, 170, .8 );
			box-shadow: 0 0 3px rgba( 0, 115, 170, .8 );
			outline: none;
		}

		.button:active {
			background: #eee;
			border-color: #999;
		 	-webkit-box-shadow: inset 0 2px 5px -3px rgba( 0, 0, 0, 0.5 );
		 	box-shadow: inset 0 2px 5px -3px rgba( 0, 0, 0, 0.5 );
		 	-webkit-transform: translateY(1px);
		 	-ms-transform: translateY(1px);
		 	transform: translateY(1px);
		}

		<?php
		if ( 'rtl' == $text_direction ) {
			echo 'body { font-family: Tahoma, Arial; }';
		}
		?>
	</style>
</head>
<body id="error-page">
<?php endif; // ! did_action( 'admin_head' ) ?>
	<?php echo $message; ?>
</body>
</html>
<?php
	die();
}

// Kill WS execution and display XML message with error message.
// This is the handler for ws_die when processing XMLRPC requests.
// @since 0.1
// @access private
// @global ws_xmlrpc_server $ws_xmlrpc_server
// @param string       $message Error message.
// @param string       $title   Optional. Error title. Default empty.
// @param string|array $args    Optional. Arguments to control behavior. Default empty array.
function _xmlrpc_ws_die_handler( $message, $title = '', $args = array() ) {
	global $ws_xmlrpc_server;
	$defaults = array( 'response' => 500 );
	$r = ws_parse_args($args, $defaults);
	if ( $ws_xmlrpc_server ) {
		$error = new IXR_Error( $r['response'] , $message);
		$ws_xmlrpc_server->output( $error->getXml() );
	}
	die();
}
// Kill WS ajax execution.
// This is the handler for ws_die when processing Ajax requests.
// @since 0.1
// @access private
// @param string       $message Error message.
// @param string       $title   Optional. Error title (unused). Default empty.
// @param string|array $args    Optional. Arguments to control behavior. Default empty array.
function _ajax_ws_die_handler( $message, $title = '', $args = array() ) {
	$defaults = array(
		'response' => 200,
	);
	$r = ws_parse_args( $args, $defaults );
	if ( ! headers_sent() && null !== $r['response'] ) {
		status_header( $r['response'] );
	}
	if ( is_scalar( $message ) )
		die( (string) $message );
	die( '0' );
}

// Kill WS execution.
// This is the handler for ws_die when processing APP requests.
// @since 0.1
// @access private
// @param string $message Optional. Response to print. Default empty.
function _scalar_ws_die_handler( $message = '' ) {
	if ( is_scalar( $message ) )
		die( (string) $message );
	die();
}
