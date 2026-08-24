<?php
// The ContactPage top menu Php template for restaurants
// @package WS
// @subpackage Localbiz
// @since WS 1.0
global $ws_contentmap, $ws_content_root, $ws_content_root_abspath;
if(file_exists($ws_content_root_abspath.'/'.langArray()[0].'/nav-contact.xml')){
  $nav_contact = ws_content($ws_content_root.'/'.langArray()[0].'/nav-contact');
} else if($ws_content_root_abspath.'/nav-contact.xml'){
  $nav_contact = ws_content($ws_content_root.'/nav-contact');
}
if($nav_contact->count() == 0){
  $nav_contact = $ws_contentmap;
}
?>
<ul><?php ws_nav_items($nav_contact); ?></ul>
