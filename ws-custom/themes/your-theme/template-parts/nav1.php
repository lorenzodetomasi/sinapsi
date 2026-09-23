<?php
// The default contents nav Php template
// @package WS
// @subpackage Localbiz
// @since WS 1.0
global $ws_query, $ws_contentmap, $ws_content_root, $ws_content_root_abspath;
/* `langArray()` used to stand here, and it is not a function — it never was.
 * `$ws_query['langArray']` is the array; the locale folder is `ws_locale()`,
 * which is what header.php has always asked for. Every call of this template
 * died on an undefined function, and nobody saw it because header.php loads
 * the menu itself and never includes this file.
 *
 * The second branch was a bug of its own: `if($path.'/nav1.xml')` tests a
 * non-empty STRING, which is always true, so the site-wide menu was loaded
 * whether or not it existed. */
$nav1 = null;
foreach(array($ws_content_root.'/'.ws_locale().'/nav1', $ws_content_root.'/nav1') as $nav1_path){
  if(file_exists(ws_root_abspath().'/'.WS_CONTENTS_RELPATH.'/'.$nav1_path.'.xml')
    or file_exists(ws_root_abspath().'/'.WS_CONTENTS_RELPATH.'/'.$nav1_path.'.wsx')
    or file_exists(ws_root_abspath().'/'.WS_CONTENTS_RELPATH.'/'.$nav1_path.'.json')){
    $nav1 = ws_content($nav1_path);
    break;
  }
}
if(!$nav1 or $nav1->count() == 0){
  $nav1 = $ws_contentmap;
}
ws_nav_items($nav1);
?>
