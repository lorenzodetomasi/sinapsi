<?php
/**
 * A page whose point is a list of events.
 *
 * The same shell as section.php — header, name, headline, the page's own text
 * — and then the events, instead of the pages underneath. The difference is
 * where the list comes from: a section reads the site map for its children,
 * this reads the root's event index and keeps what answers to the page's
 * `about`.
 *
 * Nothing here knows whose events they are. A page that collects one
 * business's events and a page that collects a neighbourhood's are the same
 * template with a different `about`, which is the point of putting the scope
 * in the content rather than in the code.
 *
 * @package WS
 * @subpackage Your Theme
 */
global $ws_content, $ws_headings;
$GLOBALS['ws_html_attributes']['html']['class'][] = 'page';
$GLOBALS['ws_html_attributes']['html']['class'][] = 'event-list-page';
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
include_template('template-parts/event-list');
include_template('template-parts/locations');
?>
			</div>
<?php
include_template('template-parts/footer');
?>
