<?php
// The default contents nav Php template
// @package WS
// @subpackage Localbiz
// @since WS 1.0
global $ws_query, $ws_contentmap, $ws_content_root, $ws_content_languages, $ws_locales;
$nav_languages_items = $ws_content_languages->item;
$current_language_item = $ws_content_languages->xpath('//item[./locale="'.explode("-", $ws_locales['content'])[0].'"]')[0];
if(count($nav_languages_items) > 1){
?>
<nav id="languages"<?php echo ws_html_attributes('languages'); ?>>
  <div class="flex width-full padding-h-d2 hgrid-display-none maxgrid-display-none">
    <h1 class="flex1 no-margin"><?php _e('Select your <strong>language</strong>'); ?></h1>
    <button onclick="toggle('languages');" title="<?php _e('Close'); ?>"><i class="material-icons">close</i></button>
  </div>
  <ul<?php echo ws_html_attributes('languages-ul'); ?>>
<?php
  $nav_languages_item_index = 0;
  foreach($nav_languages_items as $key => $item){
    if(explode("-", $ws_locales['content'])[0] == $item->locale){
      $GLOBALS['ws_html_attributes']['languages-item-'.$nav_languages_item_index]['class'] = 'current-language';
    }
    if(!empty($item->class)){
      $GLOBALS['ws_html_attributes']['languages-item-'.$nav_languages_item_index]['class'] = $item->class;
    }
    $item_url = ws_href($item->wspath);
?>
    <li<?php echo ws_html_attributes('languages-item-'.$nav_languages_item_index); ?>><a href="<?php echo $item_url; ?>"><?php echo $item->name->innerHTML(); ?></a></li>
<?php
    $nav_languages_item_index++;
  }
?>
  </ul>
</nav>
<?php
}
?>
