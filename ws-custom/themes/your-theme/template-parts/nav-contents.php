<?php
// The default contents nav Php template
// @package WS
// @subpackage Localbiz
// @since WS 1.0
global $ws_query, $rewrite_rule;
if(!empty($_GET['prev'])){
  $prev_page = $_GET['prev'];
} else {
  $prev_page = $rewrite_rule->prev->wspath;
}
$next_page = $rewrite_rule->next->wspath;
/* The parent link is NOT here any more.
 *
 * There are now three ways up on the same screen: the breadcrumb row in the
 * header, the arrow this file used to print, and the one page.php prints above
 * the title. The breadcrumb says where you are AND how to go up, and it says it
 * for every level, not just one — so this arrow was the newcomer with the least
 * to add, and it appeared right under an arrow that was already there.
 *
 * Previous and next stay: they are siblings, which no breadcrumb shows. Today
 * no page in the sitemap declares them, so this row prints nothing at all —
 * which is exactly right. The day somebody declares them, it comes back. */
if(!empty($prev_page) or !empty($next_page)){
?>
<nav class="flex nav"><div class="content-container">
  <ul class="flex nav-contents align-right">
<?php
if(!empty($prev_page)){
?>
    <li><a href="<?php echo ws_href($prev_page); ?>" title="<?php _e('Go to previous page', 'isotype'); ?>"><i class="material-symbols-outlined">arrow_back</i></a></li>
<?php
}
?>
<?php
if(!empty($next_page)){
?>
    <li><a href="<?php echo ws_href($next_page); ?>" title="<?php _e('Go to next page', 'isotype'); ?>"><i class="material-symbols-outlined">arrow_forward</i></a></li>
<?php
}
?>
  </ul>
</div></nav>
<?php
}
?>
