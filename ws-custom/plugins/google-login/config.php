<?php
global $ws_logs;
$ws_logs[] = __('<strong>Google Login</strong> Plugin initialized <code>'.__FILE__.'</code>.');
/*
session_start();
session_regenerate_id(true);
// Load WS Users
CREATE TABLE `users` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `google_id` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
 `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
 `email` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
 `profile_image` text COLLATE utf8mb4_unicode_ci NOT NULL,
 PRIMARY KEY (`id`),
 UNIQUE KEY `google_id` (`google_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
// change the information according to your database
$db_connection = mysqli_connect("localhost","root","","google_login");
// CHECK DATABASE CONNECTION
if(mysqli_connect_errno()){
    echo "Connection Failed".mysqli_connect_error();
    exit;
}
*/
?>
