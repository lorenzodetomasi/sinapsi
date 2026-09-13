<?php
// The Page template
// @package WS
// @subpackage Localbiz
// @since WS 1.0
global $ws_content, $ws_headings;
$GLOBALS['ws_html_attributes']['html']['class'][] = 'page';
include_template('template-parts/header');
?>
			<div<?php echo ws_html_attributes('main-content'); ?>>
<?php
if($ws_content->primaryImage){
?>
<?php echo $ws_content->name->primaryImage; ?>
<?php
}
?>
<?php
if($ws_content->name){
?>
				<h1 itemprop="name"><?php echo $ws_content->name->innerHTML(); ?></h1>
<?php
}
?>
<?php
if($ws_content->headline){
?>
				<h2 itemprop="headline">
					<?php echo $ws_content->headline->innerHTML(); ?>
				</h2>
<?php
}
if($ws_headings->wip == "true"){ ?>
	<p><?php _e("Website under construction."); ?></p>
<?php 
}
if($ws_content->mainContentOfPage){
	ws_echo($ws_content->mainContentOfPage->innerHTML());
}
?>
				<section>
					<!-- A second h1 on the page was a second title: this is a heading inside
					     the page, and h2 is what it is. The look stays the h1 one. -->
					<h2 class="h1"><?php _e('We have designed many logos'); ?></h2>
<?php
global $itemListElements;
$itemListElements = $ws_content->xpath("grid[@id='clients']/itemList/itemListElement[contains(concat(' ', normalize-space(@class), ' '), ' logo-design ')]");
include_template('template-parts/grid-1_1');
?>
				</section>
				<div class="content">
<?php
foreach ($ws_content->section as $section) {
	ws_echo($section->innerHTML());
}
?>
				</div>
<?php
/* The sisters of this page, under the same parent: names only, the current one
 * marked. What the section shows as cards, the page inside it shows as a list. */
include_template('template-parts/section-siblings');
include_template('template-parts/locations');
?>
			</div>
<?php
include_template('template-parts/footer');
?>