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
?>
				<div class="content">
<?php
if($ws_content->mainContentOfPage){
	ws_echo($ws_content->mainContentOfPage->innerHTML());
}
foreach ($ws_content->section as $section) {
	ws_echo($section->innerHTML());
}
?>
				</div>
<?php
include_template('template-parts/locations');
?>
			</div>
<?php
include_template('template-parts/footer');
?>