<?php
// Load the database class file and instantiate the `$wsdb` global.
// @since 1.0
// @global ws_mysql $ws_mysql The WordPress database class.
function require_ws_mysql() {
	global $ws_mysql;

	require_once( ABSPATH . WS_CORE_DIR . '/wp-db.php' );
	if ( file_exists( WS_CONTENT_DIR . '/db.php' ) )
		require_once( WS_CONTENT_DIR . '/db.php' );

	if ( isset( $ws_mysql ) ) {
		return;
	}

	$ws_mysql = new ws_mysql( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
}

/**
 * Set the database table prefix and the format specifiers for database
 * table columns.
 *
 * Columns not listed here default to `%s`.
 *
 * @since 3.0.0
 * @access private
 *
 * @global ws_mysql   $ws_mysql         The WordPress database class.
 * @global string $table_prefix The database table prefix.
 */
function ws_set_ws_mysql_vars() {
	global $ws_mysql, $table_prefix;
	if ( !empty( $ws_mysql->error ) )
		dead_db();

	$ws_mysql->field_types = array( 'post_author' => '%d', 'post_parent' => '%d', 'menu_order' => '%d', 'term_id' => '%d', 'term_group' => '%d', 'term_taxonomy_id' => '%d',
		'parent' => '%d', 'count' => '%d','object_id' => '%d', 'term_order' => '%d', 'ID' => '%d', 'comment_ID' => '%d', 'comment_post_ID' => '%d', 'comment_parent' => '%d',
		'user_id' => '%d', 'link_id' => '%d', 'link_owner' => '%d', 'link_rating' => '%d', 'option_id' => '%d', 'blog_id' => '%d', 'meta_id' => '%d', 'post_id' => '%d',
		'user_status' => '%d', 'umeta_id' => '%d', 'comment_karma' => '%d', 'comment_count' => '%d',
		// multisite:
		'active' => '%d', 'cat_id' => '%d', 'deleted' => '%d', 'lang_id' => '%d', 'mature' => '%d', 'public' => '%d', 'site_id' => '%d', 'spam' => '%d',
	);

	$prefix = $ws_mysql->set_prefix( $table_prefix );

	if ( is_ws_error( $prefix ) ) {
		ws_die(
			/* translators: 1: $table_prefix 2: wp-config.php */
			sprintf( __( '<strong>ERROR</strong>: %1$s in %2$s can only contain numbers, letters, and underscores.' ),
				'<code>$table_prefix</code>',
				'<code>wp-config.php</code>'
			)
		);
	}
}
?>
