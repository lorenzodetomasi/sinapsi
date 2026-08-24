<?php
// Initialize the filter globals.
require( ws_core_abspath() . '/class-ws-hook.php' );

/** @var WS_Hook[] $ws_filter */
global $ws_filter, $ws_actions, $ws_current_filter;

if ( $ws_filter ) {
	$ws_filter = WS_Hook::build_preinitialized_hooks( $ws_filter );
} else {
	$ws_filter = array();
}

if ( ! isset( $ws_actions ) )
	$ws_actions = array();

if ( ! isset( $ws_current_filter ) )
	$ws_current_filter = array();

/**
 * Hook a function or method to a specific filter action.
 *
 * WordPress offers filter hooks to allow plugins to modify
 * various types of internal data at runtime.
 *
 * A plugin can modify data by binding a callback to a filter hook. When the filter
 * is later applied, each bound callback is run in order of priority, and given
 * the opportunity to modify a value by returning a new value.
 *
 * The following example shows how a callback function is bound to a filter hook.
 *
 * Note that `$example` is passed to the callback, (maybe) modified, then returned:
 *
 *     function example_callback( $example ) {
 *         // Maybe modify $example in some way.
 *         return $example;
 *     }
 *     add_filter( 'example_filter', 'example_callback' );
 *
 * Bound callbacks can accept from none to the total number of arguments passed as parameters
 * in the corresponding apply_filters() call.
 *
 * In other words, if an apply_filters() call passes four total arguments, callbacks bound to
 * it can accept none (the same as 1) of the arguments or up to four. The important part is that
 * the `$accepted_args` value must reflect the number of arguments the bound callback *actually*
 * opted to accept. If no arguments were accepted by the callback that is considered to be the
 * same as accepting 1 argument. For example:
 *
 *     // Filter call.
 *     $value = apply_filters( 'hook', $value, $arg2, $arg3 );
 *
 *     // Accepting zero/one arguments.
 *     function example_callback() {
 *         ...
 *         return 'some value';
 *     }
 *     add_filter( 'hook', 'example_callback' ); // Where $priority is default 10, $accepted_args is default 1.
 *
 *     // Accepting two arguments (three possible).
 *     function example_callback( $value, $arg2 ) {
 *         ...
 *         return $maybe_modified_value;
 *     }
 *     add_filter( 'hook', 'example_callback', 10, 2 ); // Where $priority is 10, $accepted_args is 2.
 *
 * *Note:* The function will return true whether or not the callback is valid.
 * It is up to you to take care. This is done for optimization purposes, so
 * everything is as quick as possible.
 *
 * @since 0.71
 *
 * @global array $ws_filter      A multidimensional array of all hooks and the callbacks hooked to them.
 *
 * @param string   $tag             The name of the filter to hook the $function_to_add callback to.
 * @param callable $function_to_add The callback to be run when the filter is applied.
 * @param int      $priority        Optional. Used to specify the order in which the functions
 *                                  associated with a particular action are executed. Default 10.
 *                                  Lower numbers correspond with earlier execution,
 *                                  and functions with the same priority are executed
 *                                  in the order in which they were added to the action.
 * @param int      $accepted_args   Optional. The number of arguments the function accepts. Default 1.
 * @return true
 */
function add_filter( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {
	global $ws_filter;
	if ( ! isset( $ws_filter[ $tag ] ) ) {
		$ws_filter[ $tag ] = new WS_Hook();
	}
	$ws_filter[ $tag ]->add_filter( $tag, $function_to_add, $priority, $accepted_args );
	return true;
}

/**
 * Check if any filter has been registered for a hook.
 *
 * @since 2.5.0
 *
 * @global array $ws_filter Stores all of the filters.
 *
 * @param string        $tag               The name of the filter hook.
 * @param callable|bool $function_to_check Optional. The callback to check for. Default false.
 * @return false|int If $function_to_check is omitted, returns boolean for whether the hook has
 *                   anything registered. When checking a specific function, the priority of that
 *                   hook is returned, or false if the function is not attached. When using the
 *                   $function_to_check argument, this function may return a non-boolean value
 *                   that evaluates to false (e.g.) 0, so use the === operator for testing the
 *                   return value.
 */
function has_filter($tag, $function_to_check = false) {
	global $ws_filter;

	if ( ! isset( $ws_filter[ $tag ] ) ) {
		return false;
	}

	return $ws_filter[ $tag ]->has_filter( $tag, $function_to_check );
}

/**
 * Call the functions added to a filter hook.
 *
 * The callback functions attached to filter hook $tag are invoked by calling
 * this function. This function can be used to create a new filter hook by
 * simply calling this function with the name of the new hook specified using
 * the $tag parameter.
 *
 * The function allows for additional arguments to be added and passed to hooks.
 *
 *     // Our filter callback function
 *     function example_callback( $string, $arg1, $arg2 ) {
 *         // (maybe) modify $string
 *         return $string;
 *     }
 *     add_filter( 'example_filter', 'example_callback', 10, 3 );
 *
 *     /*
 *      * Apply the filters by calling the 'example_callback' function we
 *      * "hooked" to 'example_filter' using the add_filter() function above.
 *      * - 'example_filter' is the filter hook $tag
 *      * - 'filter me' is the value being filtered
 *      * - $arg1 and $arg2 are the additional arguments passed to the callback.
 *     $value = apply_filters( 'example_filter', 'filter me', $arg1, $arg2 );
 *
 * @since 0.71
 *
 * @global array $ws_filter         Stores all of the filters.
 * @global array $ws_current_filter Stores the list of current filters with the current one last.
 *
 * @param string $tag     The name of the filter hook.
 * @param mixed  $value   The value on which the filters hooked to `$tag` are applied on.
 * @param mixed  $var,... Additional variables passed to the functions hooked to `$tag`.
 * @return mixed The filtered value after all hooked functions are applied to it.
 */
function apply_filters( $tag, $value ) {
	global $ws_filter, $ws_current_filter;

	$args = array();

	// Do 'all' actions first.
	if ( isset($ws_filter['all']) ) {
		$ws_current_filter[] = $tag;
		$args = func_get_args();
		_ws_call_all_hook($args);
	}

	if ( !isset($ws_filter[$tag]) ) {
		if ( isset($ws_filter['all']) )
			array_pop($ws_current_filter);
		return $value;
	}

	if ( !isset($ws_filter['all']) )
		$ws_current_filter[] = $tag;

	if ( empty($args) )
		$args = func_get_args();

	// don't pass the tag name to WS_Hook
	array_shift( $args );

	$filtered = $ws_filter[ $tag ]->apply_filters( $value, $args );

	array_pop( $ws_current_filter );

	return $filtered;
}

/**
 * Execute functions hooked on a specific filter hook, specifying arguments in an array.
 *
 * @since 3.0.0
 *
 * @see apply_filters() This function is identical, but the arguments passed to the
 * functions hooked to `$tag` are supplied using an array.
 *
 * @global array $ws_filter         Stores all of the filters
 * @global array $ws_current_filter Stores the list of current filters with the current one last
 *
 * @param string $tag  The name of the filter hook.
 * @param array  $args The arguments supplied to the functions hooked to $tag.
 * @return mixed The filtered value after all hooked functions are applied to it.
 */
function apply_filters_ref_array($tag, $args) {
	global $ws_filter, $ws_current_filter;

	// Do 'all' actions first
	if ( isset($ws_filter['all']) ) {
		$ws_current_filter[] = $tag;
		$all_args = func_get_args();
		_ws_call_all_hook($all_args);
	}

	if ( !isset($ws_filter[$tag]) ) {
		if ( isset($ws_filter['all']) )
			array_pop($ws_current_filter);
		return $args[0];
	}

	if ( !isset($ws_filter['all']) )
		$ws_current_filter[] = $tag;

	$filtered = $ws_filter[ $tag ]->apply_filters( $args[0], $args );

	array_pop( $ws_current_filter );

	return $filtered;
}

/**
 * Removes a function from a specified filter hook.
 *
 * This function removes a function attached to a specified filter hook. This
 * method can be used to remove default functions attached to a specific filter
 * hook and possibly replace them with a substitute.
 *
 * To remove a hook, the $function_to_remove and $priority arguments must match
 * when the hook was added. This goes for both filters and actions. No warning
 * will be given on removal failure.
 *
 * @since 1.2.0
 *
 * @global array $ws_filter         Stores all of the filters
 *
 * @param string   $tag                The filter hook to which the function to be removed is hooked.
 * @param callable $function_to_remove The name of the function which should be removed.
 * @param int      $priority           Optional. The priority of the function. Default 10.
 * @return bool    Whether the function existed before it was removed.
 */
function remove_filter( $tag, $function_to_remove, $priority = 10 ) {
	global $ws_filter;

	$r = false;
	if ( isset( $ws_filter[ $tag ] ) ) {
		$r = $ws_filter[ $tag ]->remove_filter( $tag, $function_to_remove, $priority );
		if ( ! $ws_filter[ $tag ]->callbacks ) {
			unset( $ws_filter[ $tag ] );
		}
	}

	return $r;
}

/**
 * Remove all of the hooks from a filter.
 *
 * @since 2.7.0
 *
 * @global array $ws_filter  Stores all of the filters
 *
 * @param string   $tag      The filter to remove hooks from.
 * @param int|bool $priority Optional. The priority number to remove. Default false.
 * @return true True when finished.
 */
function remove_all_filters( $tag, $priority = false ) {
	global $ws_filter;

	if ( isset( $ws_filter[ $tag ]) ) {
		$ws_filter[ $tag ]->remove_all_filters( $priority );
		if ( ! $ws_filter[ $tag ]->has_filters() ) {
			unset( $ws_filter[ $tag ] );
		}
	}

	return true;
}

/**
 * Retrieve the name of the current filter or action.
 *
 * @since 2.5.0
 *
 * @global array $ws_current_filter Stores the list of current filters with the current one last
 *
 * @return string Hook name of the current filter or action.
 */
function current_filter() {
	global $ws_current_filter;
	return end( $ws_current_filter );
}

/**
 * Retrieve the name of the current action.
 *
 * @since 3.9.0
 *
 * @return string Hook name of the current action.
 */
function current_action() {
	return current_filter();
}

/**
 * Retrieve the name of a filter currently being processed.
 *
 * The function current_filter() only returns the most recent filter or action
 * being executed. did_action() returns true once the action is initially
 * processed.
 *
 * This function allows detection for any filter currently being
 * executed (despite not being the most recent filter to fire, in the case of
 * hooks called from hook callbacks) to be verified.
 *
 * @since 3.9.0
 *
 * @see current_filter()
 * @see did_action()
 * @global array $ws_current_filter Current filter.
 *
 * @param null|string $filter Optional. Filter to check. Defaults to null, which
 *                            checks if any filter is currently being run.
 * @return bool Whether the filter is currently in the stack.
 */
function doing_filter( $filter = null ) {
	global $ws_current_filter;

	if ( null === $filter ) {
		return ! empty( $ws_current_filter );
	}

	return in_array( $filter, $ws_current_filter );
}

/**
 * Retrieve the name of an action currently being processed.
 *
 * @since 3.9.0
 *
 * @param string|null $action Optional. Action to check. Defaults to null, which checks
 *                            if any action is currently being run.
 * @return bool Whether the action is currently in the stack.
 */
function doing_action( $action = null ) {
	return doing_filter( $action );
}

/**
 * Hooks a function on to a specific action.
 *
 * Actions are the hooks that the WordPress core launches at specific points
 * during execution, or when specific events occur. Plugins can specify that
 * one or more of its PHP functions are executed at these points, using the
 * Action API.
 *
 * @since 1.2.0
 *
 * @param string   $tag             The name of the action to which the $function_to_add is hooked.
 * @param callable $function_to_add The name of the function you wish to be called.
 * @param int      $priority        Optional. Used to specify the order in which the functions
 *                                  associated with a particular action are executed. Default 10.
 *                                  Lower numbers correspond with earlier execution,
 *                                  and functions with the same priority are executed
 *                                  in the order in which they were added to the action.
 * @param int      $accepted_args   Optional. The number of arguments the function accepts. Default 1.
 * @return true Will always return true.
 */
function add_action($tag, $function_to_add, $priority = 10, $accepted_args = 1) {
	return add_filter($tag, $function_to_add, $priority, $accepted_args);
}
?>
