<?php
// The Footer Legal Menu Php template
// @package WS
// @subpackage Localbiz
// @since WS 1.0
global $ws_contentmap, $ws_content_root, $ws_content_root_abspath;
if(file_exists($ws_content_root_abspath.'/'.langArray()[0].'/nav-legal.xml')){
  $nav_legal = ws_content($ws_content_root.'/'.langArray()[0].'/nav-legal');
} else if($ws_content_root_abspath.'/nav-legal.xml'){
  $nav_legal = ws_content($ws_content_root.'/nav-legal');
}
if($nav_legal->count() == 0){
  $nav_legal = $ws_contentmap;
}
?>
<nav class="nav vertical nav-legal hgrid-text-align-right">
	<h3><?php _e('Legal informations'); ?></h3>
	<ul><?php ws_nav_items($nav_legal); ?></ul>
</nav>
