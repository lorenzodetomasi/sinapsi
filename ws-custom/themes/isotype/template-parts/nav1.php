<?php
// The default contents nav Php template
// @package WS
// @subpackage Localbiz
// @since WS 1.0
global $ws_query, $ws_contentmap, $ws_content_root, $ws_content_root_abspath;
if(file_exists($ws_content_root_abspath.'/'.langArray()[0].'/nav1.xml')){
  $nav1 = ws_content($ws_content_root.'/'.langArray()[0].'/nav1');
} else if($ws_content_root_abspath.'/nav1.xml'){
  $nav1 = ws_content($ws_content_root.'/nav1');
}
if($nav1->count() == 0){
  $nav1 = $ws_contentmap;
}
ws_nav_items($nav1);
?>
