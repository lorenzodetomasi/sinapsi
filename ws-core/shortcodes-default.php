<?php
/* Add Default Shortcodes */
// 1. Shortcode for include_template function
// Source: <https://wordpress.stackexchange.com/questions/49675/include-php-file-in-content-using-shortcode>
function shortcode_include_template($atts) {
$a = shortcode_atts( array(
'template_relpath' => 'NULL',
), $atts );

	if($a['template_relpath'] != 'NULL'){
		ob_start();
		include_template($a['template_relpath']);
		return ob_get_clean();
	}
}
add_shortcode('include_template', 'shortcode_include_template');
function ws_kses_attr_parse( $element ) {
	$valid = preg_match( '%^(<\s*)(/\s*)?([a-zA-Z0-9]+\s*)([^>]*)(>?)$%', $element, $matches );
	if ( 1 !== $valid ) {
		return false;
	}

	$begin  = $matches[1];
	$slash  = $matches[2];
	$elname = $matches[3];
	$attr   = $matches[4];
	$end    = $matches[5];

	if ( '' !== $slash ) {
		// Closing elements do not get parsed.
		return false;
	}

	// Is there a closing XHTML slash at the end of the attributes?
	if ( 1 === preg_match( '%\s*/\s*$%', $attr, $matches ) ) {
		$xhtml_slash = $matches[0];
		$attr        = substr( $attr, 0, -strlen( $xhtml_slash ) );
	} else {
		$xhtml_slash = '';
	}

	// Split it.
	$attrarr = ws_kses_hair_parse( $attr );
	if ( false === $attrarr ) {
		return false;
	}

	// Make sure all input is returned by adding front and back matter.
	array_unshift( $attrarr, $begin . $slash . $elname );
	array_push( $attrarr, $xhtml_slash . $end );

	return $attrarr;
}
function ws_kses_hair_parse( $attr ) {
	if ( '' === $attr ) {
		return array();
	}

	$regex =
		'(?:
				[_a-zA-Z][-_a-zA-Z0-9:.]* # Attribute name.
			|
				\[\[?[^\[\]]+\]\]?        # Shortcode in the name position implies unfiltered_html.
		)
		(?:                               # Attribute value.
			\s*=\s*                       # All values begin with "=".
			(?:
				"[^"]*"                   # Double-quoted.
			|
				\'[^\']*\'                # Single-quoted.
			|
				[^\s"\']+                 # Non-quoted.
				(?:\s|$)                  # Must have a space.
			)
		|
			(?:\s|$)                      # If attribute has no value, space is required.
		)
		\s*                               # Trailing space is optional except as mentioned above.
		';

	/*
	 * Although it is possible to reduce this procedure to a single regexp,
	 * we must run that regexp twice to get exactly the expected result.
	 *
	 * Note: do NOT remove the `x` modifiers as they are essential for the above regex!
	 */

	$validation = "/^($regex)+$/x";
	$extraction = "/$regex/x";

	if ( 1 === preg_match( $validation, $attr ) ) {
		preg_match_all( $extraction, $attr, $attrarr );
		return $attrarr[0];
	} else {
		return false;
	}
}
?>