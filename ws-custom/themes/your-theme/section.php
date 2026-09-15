<?php
/**
 * A section: a page whose point is the pages under it.
 *
 * Same shell as page.php — header, name, headline, the page's own text — and
 * then the pages beneath, as cards, read from the site map. Nothing in here
 * knows what the section is about: /servizi and /progetti are the same shape,
 * and any page that names this template in its query gets it.
 *
 * In schema.org a page like this is a CollectionPage, and the list of what it
 * collects is its mainEntity (see section-children.php). The body carries the
 * type, so everything inside can hang its properties on it.
 *
 * No "up" arrow here: the breadcrumbs already say where the parent is, and
 * two ways of saying the same thing on one page is one too many.
 *
 * @package WS
 * @subpackage Your Theme
 */
global $ws_content, $ws_headings;
$GLOBALS['ws_html_attributes']['html']['class'][] = 'page';
$GLOBALS['ws_html_attributes']['html']['class'][] = 'section';
include_template('template-parts/header');
?>
			<div<?php echo ws_html_attributes('main-content'); ?>>
<?php
if(!empty($ws_content->primaryImageOfPage)){
	echo get_media($ws_content->primaryImageOfPage->figure->image, array('imgAttributes' => array('class' => 'primary-image')));
}
if(!empty($ws_content->name)){
?>
				<h1><?php echo $ws_content->name->innerHTML(); ?></h1>
<?php
}
if(!empty($ws_content->headline)){
?>
				<h2><?php echo $ws_content->headline->innerHTML(); ?></h2>
<?php
}
?>
				<div class="content">
<?php
if($ws_headings->wip == "true"){ ?><p><?php _e("Website under construction."); ?></p><?php }
if(!empty($ws_content->mainContentOfPage)){
	ws_echo($ws_content->mainContentOfPage->innerHTML());
}
if(!empty($ws_content->section)){
	foreach ($ws_content->section as $section) {
		ws_echo($section->innerHTML());
	}
}
?>
				</div>
<?php
include_template('template-parts/section-children');
include_template('template-parts/locations');
?>
			</div>
<?php
include_template('template-parts/footer');
?>
