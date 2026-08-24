<?php
// The Homepage template
// @package WS
// @subpackage Localbiz
// @since WS 1.0
global $ws_content;
$GLOBALS['ws_html_attributes']['html']['class'][] = 'home';
include_template('template-parts/header');
?>
<div<?php echo ws_html_attributes('main-content'); ?>>
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
		<?php echo $ws_content->description; ?>
	</div>
	<p itemprop="datePublished"><?php echo $ws_content->datePublished; ?></p>
<?php
}
//include_template('locations/_clients');
//include_template('locations/_awards');
//include_template('locations/_locations');
?>
	<div class="content">
<?php
if($ws_content->mainContentOfPage){
	echo $ws_content->mainContentOfPage->innerHTML();
}
?>
	</div>
</div>
<?php
include_template('template-parts/footer');
?>
