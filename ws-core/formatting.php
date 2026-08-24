<?php
//
// Main WS Formatting API.
// Handles many functions for formatting output.
// @package WS

// Separates HTML elements and comments from the text.
//
// @since 1.0
// @param string $input The text which has to be formatted.
// @return string[] Array of the formatted text.
function ws_html_split( $input ) {
	return preg_split( get_html_split_regex(), $input, -1, PREG_SPLIT_DELIM_CAPTURE );
}

// Retrieves the regular expression for an HTML element.
// @since 1.0
// @return string The regular expression
function get_html_split_regex() {
	static $regex;

	if ( ! isset( $regex ) ) {
		// phpcs:disable Squiz.Strings.ConcatenationSpacing.PaddingFound -- don't remove regex indentation
		$comments =
			'!'             // Start of comment, after the <.
			. '(?:'         // Unroll the loop: Consume everything until --> is found.
			.     '-(?!->)' // Dash not followed by end of comment.
			.     '[^\-]*+' // Consume non-dashes.
			. ')*+'         // Loop possessively.
			. '(?:-->)?';   // End of comment. If not found, match all input.

		$cdata =
			'!\[CDATA\['    // Start of comment, after the <.
			. '[^\]]*+'     // Consume non-].
			. '(?:'         // Unroll the loop: Consume everything until ]]> is found.
			.     '](?!]>)' // One ] not followed by end of comment.
			.     '[^\]]*+' // Consume non-].
			. ')*+'         // Loop possessively.
			. '(?:]]>)?';   // End of comment. If not found, match all input.

		$escaped =
			'(?='             // Is the element escaped?
			.    '!--'
			. '|'
			.    '!\[CDATA\['
			. ')'
			. '(?(?=!-)'      // If yes, which type?
			.     $comments
			. '|'
			.     $cdata
			. ')';

		$regex =
			'/('                // Capture the entire match.
			.     '<'           // Find start of element.
			.     '(?'          // Conditional expression follows.
			.         $escaped  // Find end of escaped element.
			.     '|'           // ...else...
			.         '[^>]*>?' // Find end of normal element.
			.     ')'
			. ')/';
		// phpcs:enable
	}

	return $regex;
}
?>