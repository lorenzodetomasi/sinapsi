<?php
class ws_mysql {

	/**
	 * Whether to show SQL/DB errors.
	 *
	 * Default behavior is to show errors if both WP_DEBUG and WP_DEBUG_DISPLAY
	 * evaluated to true.
	 *
	 * @since 0.71
	 * @var bool
	 */
	var $show_errors = false;

	/**
	 * Database table columns charset
	 *
	 * @since 2.2.0
	 * @var string
	 */
	public $charset;

	/**
	 * Database table columns collate
	 *
	 * @since 2.2.0
	 * @var string
	 */
	public $collate;

	/**
	 * Database Username
	 *
	 * @since 2.9.0
	 * @var string
	 */
	protected $dbuser;

	/**
	 * Database Password
	 *
	 * @since 3.1.0
	 * @var string
	 */
	protected $dbpassword;

	/**
	 * Database Name
	 *
	 * @since 3.1.0
	 * @var string
	 */
	protected $dbname;

	/**
	 * Database Host
	 *
	 * @since 3.1.0
	 * @var string
	 */
	protected $dbhost;

	/**
	 * Database Handle
	 *
	 * @since 0.71
	 * @var string
	 */
	protected $dbh;
	/**
	 * Connects to the database server and selects a database
	 *
	 * PHP5 style constructor for compatibility with PHP5. Does
	 * the actual setting up of the class properties and connection
	 * to the database.
	 *
	 * @link https://core.trac.wordpress.org/ticket/3354
	 * @since 2.0.8
	 *
	 * @global string $wp_version
	 *
	 * @param string $dbuser     MySQL database user
	 * @param string $dbpassword MySQL database password
	 * @param string $dbname     MySQL database name
	 * @param string $dbhost     MySQL database host
	 */
	 public function __construct( $dbuser, $dbpassword, $dbname, $dbhost ) {
		register_shutdown_function( array( $this, '__destruct' ) );

		if ( WS_DEBUG && WS_DEBUG_DISPLAY )
			$this->show_errors();

		// Use ext/mysqli if it exists unless WP_USE_EXT_MYSQL is defined as true
		if ( function_exists( 'mysqli_connect' ) ) {
			$this->use_mysqli = true;

			if ( defined( 'WS_USE_EXT_MYSQL' ) ) {
				$this->use_mysqli = ! WS_USE_EXT_MYSQL;
			}
		}

		$this->dbuser = $dbuser;
		$this->dbpassword = $dbpassword;
		$this->dbname = $dbname;
		$this->dbhost = $dbhost;

		// wp-config.php creation will manually connect when ready.
		if ( defined( 'WS_SETUP_CONFIG' ) ) {
			return;
		}

		$this->db_connect();
	}
}
?>
