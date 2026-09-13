<?php
// The Homepage template
// @package WS
// @subpackage Localbiz
// @since WS 1.0
global $ws_content, $ws_headings;
$GLOBALS['ws_html_attributes']['html']['class'][] = 'home';
include_template('template-parts/header');
?>
<div<?php echo ws_html_attributes('main-content'); ?>>
<?php
if($ws_content->primaryImageOfPage){
	echo get_media($ws_content->primaryImageOfPage->figure->image, array('imgAttributes' => array('itemprop' => "primaryImageOfPage")));
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
if($ws_content->description){
?>
	<div itemprop="description">
		<p><?php echo $ws_content->description; ?></p>
	</div>
<?php
}
?>
	<div class="content">
<?php if($ws_headings->wip == "true"){ ?><p><?php _e("Website under construction."); ?></p><?php } ?>
<?php
if($ws_content->mainContentOfPage){
	echo $ws_content->mainContentOfPage->innerHTML();
}
?>
	</div>
<?php
global $section;
foreach ($ws_content->section as $section) {
    if ($section->xpath('self::*[contains(concat(" ", normalize-space(@class), " "), " grid ")]')) {
		/* NOT require_once, which is include_template's default: this runs once
		   per grid, and the second grid would find the file already included
		   and silently get nothing. That is how the home showed the clients
		   and not the awards. */
		include_template('template-parts/grid-1_1', array('require_once' => false));
    } else {
        ws_echo($section->innerHTML());     
    }
}
?>
</div>
<?php
include_template('template-parts/footer');
?>
