<?php
global $ws_logs;
$ws_root_url = ws_root_url();
$ws_custom_url = ws_custom_url();

// https://scssphp.github.io/scssphp/docs/
require_once ws_core_abspath().'/libraries/php/scssphp/scss.inc.php';
use ScssPhp\ScssPhp\Compiler;
?>
<h1><a href="<?php echo $ws_root_url; ?>"><?php echo $ws_root_url; ?></a></h1>
<h2><?php _e('Refreshing themes'); ?></h2>
<?php

?>
