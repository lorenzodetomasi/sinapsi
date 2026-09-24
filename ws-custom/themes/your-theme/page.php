<?php
/**
 * The Page template.
 *
 * A page in the middle of the site: it may have pages above it (the arrow to
 * its parent) and pages under it (the grid at the end). Its content is drawn
 * in the order the content declares it: the main text, the offer catalogue of
 * what the page is about, the sections, and last a call to action.
 *
 * What the page IS - a WebPage or a CollectionPage, about a Service - is
 * declared once, as JSON-LD in the head (functions.php). The markup here says
 * nothing about it: no itemprops, no double names.
 *
 * @package WS
 * @subpackage Your Theme
 * @since WS 1.0
 */
global $ws_content, $ws_headings;
$GLOBALS['ws_html_attributes']['html']['class'][] = 'page';
include_template('template-parts/header');
?>
			<div<?php echo ws_html_attributes('main-content'); ?>>
<?php
if($ws_content->primaryImageOfPage){
	echo ws_figure_media($ws_content->primaryImageOfPage->figure, array('imgAttributes' => array('class' => 'primary-image')));
}
?>
<?php
if($ws_content->parent->wspath){
// 	<span class="material-symbols-outlined">home</span>
?>
<a class="link" href="<?php echo $ws_content->parent->wspath; ?>"><span class="material-symbols-outlined">arrow_upward</span></a>
<?php
}
?>
<?php
if($ws_content->name){
?>
				<h1><?php echo $ws_content->name->innerHTML(); ?></h1>
<?php
}
?>
<?php
if($ws_content->headline){
?>
				<h2>
					<?php echo $ws_content->headline->innerHTML(); ?>
				</h2>
<?php
}
?>
				<div class="content">
<?php if($ws_headings->wip == "true"){ ?><p><?php _e("Website under construction."); ?></p><?php } ?>
<?php
if($ws_content->mainContentOfPage){
	ws_echo($ws_content->mainContentOfPage->innerHTML());
}
include_template('template-parts/offer-catalog');
global $section;
foreach ($ws_content->section as $section) {
    if ($section->xpath('self::*[contains(concat(" ", normalize-space(@class), " "), " grid ")]')) {
		include_template('template-parts/grid-1_1-image_gallery', array('require_once' => false));
    } else {
        ws_echo($section->innerHTML());
    }
}
include_template('template-parts/call-to-action');
?>
				</div>
<?php
include_template('template-parts/section-children');
include_template('template-parts/section-siblings');
include_template('template-parts/locations');
?>
			</div>
<?php
include_template('template-parts/footer');
?>
