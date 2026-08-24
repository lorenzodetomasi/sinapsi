<?php
// The Footer Legal Menu Php template
// @package WS
// @subpackage Localbiz
// @since WS 1.0
global $ws_contentmap, $ws_content_root, $ws_content_root_abspath;
if(file_exists($ws_content_root_abspath.'/'.ws_locale().'/nav-legal.xml')){
  $nav_legal = ws_content($ws_content_root.'/'.ws_locale().'/nav-legal');
} else if($ws_content_root_abspath.'/nav-legal.xml'){
  $nav_legal = ws_content($ws_content_root.'/nav-legal');
}
if($nav_legal->count() == 0){
  $nav_legal = $ws_contentmap;
}
if(isset($nav_legal)){
?>
<nav <?php echo ws_html_attributes('nav-legal', array('id'=>'nav-legal', 'class' => array('nav-legal', 'nav', 'vertical'))); ?>>
	<h3><?php _e('Legal informations'); ?></h3>
  <div>
	 <ul><?php ws_nav_items($nav_legal); ?></ul>
  </div>
</nav>
<?php
}
?>