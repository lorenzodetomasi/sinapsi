<?php
// The Location list template file
// @package WS
// @subpackage Localbiz_Theme
// @since Localbiz_Theme 1.0
global $ws_content;
$GLOBALS['ws_html_attributes']['html']['class'][] = 'locations';
include_template('template-parts/header');
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
<?php echo $ws_content->head->description->innerHTML(); ?>
			</div>
			<p itemprop="datePublished"><?php echo $ws_content->datePublished; ?></p>
<?php
}
?>
			<div class="content">
<?php
include_template('locations/_locations');
?>
			</div>
<?php
include_template('template-parts/footer');
?>
