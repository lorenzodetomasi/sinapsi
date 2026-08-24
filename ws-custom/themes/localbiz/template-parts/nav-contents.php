<?php
// The default contents nav Php template
// @package WS
// @subpackage Localbiz
// @since WS 1.0
global $ws_query, $rewrite_rule;
if(!empty($_GET['parent'])){
  $parent_page = $_GET['parent'];
} else {
  $parent_page = $rewrite_rule->parent->wspath;
}
if(!empty($_GET['prev'])){
  $prev_page = $_GET['prev'];
} else {
  $prev_page = $rewrite_rule->prev->wspath;
}
$next_page = $rewrite_rule->next->wspath;
if(!empty($parent_page) or !empty($prev_page) or !empty($next_page)){
?>
<nav class="flex nav">
  <ul class="flex nav-contents align-right">
<?php
if(!empty($parent_page)){
?>
    <li><a href="<?php echo ws_href($parent_page); ?>" title="<?php _e('Go to parent page'); ?>"><i class="material-icons">arrow_upward</i></a></li>
<?php
}
?>
<?php
if(!empty($prev_page)){
?>
    <li><a href="<?php echo ws_href($prev_page); ?>" title="<?php _e('Go to previous page', 'isotype'); ?>"><i class="material-icons">arrow_back</i></a></li>
<?php
}
?>
<?php
if(!empty($next_page)){
?>
    <li><a href="<?php echo ws_href($next_page); ?>" title="<?php _e('Go to next page', 'isotype'); ?>"><i class="material-icons">arrow_forward</i></a></li>
<?php
}
?>
  </ul>
</nav>
<?php
}
?>
