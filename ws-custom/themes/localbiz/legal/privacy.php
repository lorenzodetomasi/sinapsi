<?php
// The Privacy policy Php template
// @package WS
// @subpackage Localbiz
// @since WS 1.0
global $ws_headings, $ws_content;
include_template('template-parts/header');
?>
<div class="content-container">
<?php
if($ws_content->name){
?>
			<h1 itemprop="name"><?php echo $ws_content->name; ?></h1>
<?php
}
?>
<?php
if($ws_content->description){
?>
			<div itemprop="description">
				<?php ?>
			</div>
			<p itemprop="datePublished"><?php echo $ws_content->datePublished; ?></p>
<?php
}
?>
			<div class="content">
<?php
if($ws_content->mainContentOfPage){
	echo $ws_content->mainContentOfPage->innerHTML();
}
?>
			</div>
<?php
include_template('template-parts/footer');
?>
