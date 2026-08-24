<?php
// Core User API
// @package WS
// @subpackage Users
// Core class used to implement the WS_User object.
// @since 0.0.1
// @property int 		$user_nid
// @property string 	$user_id
// @property string 	$user_password
// @property string 	$user_email
// @property string 	$user_locale
// @property string 	$user_status
// @property int    	$user_level
// @property datetime   $user_created
// @property datetime   $user_modified
// @property datetime   $user_deleted
// @property string 	$user_profile i.e. ["person", 1], ["organization", 1]
// @property string 	$oauth_provider
// @property string 	$oauth_uid
function WS_init_users() {
	global $ws_mysql, $table_prefix;

	// Attempt MySQL server connection
	$mysqli_connection = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
	// Check MySQL server connection
	if ($mysqli_connection->connect_error) {
		$log = sprintf( __( 'Failed to connect to MySQL: %s' ), mysqli_connect_error() );
		die($mysqli_connection->connect_error);
	}
	$table_name = $table_prefix."users";
	$sql = array();
	$sql = "CREATE TABLE IF NOT EXISTS ".$table_name." (
		user_nid int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
		user_id varchar(255) NOT NULL,
		user_password varchar(255) NOT NULL,
		user_email varchar(255) NOT NULL,
		user_locale varchar(10) NOT NULL,
		user_status varchar(10) NOT NULL,
		user_level varchar(10) NOT NULL,
		user_created timestamp default '0000-00-00 00:00:00',,
		user_modified timestamp default now() on update now(),
		user_deleted datetime NOT NULL,
		user_profile varchar(10) NOT NULL,
		oauth_provider varchar(255) NOT NULL,
		oauth_uid varchar(255) NOT NULL
	) CHARSET=".DB_CHARSET." COLLATE=".DB_COLLATE.";";

	if( $mysqli_connection->query($sql) === true ){
		$log = sprintf( __( 'Table “%s” created.' ), $table_name );
	} else{
		$log =  sprintf( __( 'Not able to execute: %1$s. %2$s' ), $sql, mysqli_error($mysqli_connection));
	}

	// Close connection
	mysqli_close($mysqli_connection);
}
// https://developers.google.com/+/web/signin/
// https://developers.google.com/identity/sign-in/web/sign-in
class User {
	function __construct(){
		if(!isset($this->db)){
			// Connect to the database
			$conn = new mysqli($this->dbHost, $this->dbUsername, $this->dbPassword, $this->dbName);
			if($conn->connect_error){
				die("Failed to connect with MySQL: " . $conn->connect_error);
			} else {
				$this->db = $conn;
			}
	    }
	}
}
