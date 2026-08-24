<?php
// The Booking Table Php template
// @package WS
// @subpackage Localbiz
// @since WS 1.0
global $ws_sitemap, $ws_content, $ws_headings, $longDate, $locations,
	$site_url, $user_ID, $template_directory_url, $privacy_page_id;
$GLOBALS['ws_html_attributes']['html']['class'][] = 'page';
include_template('template-parts/header');
?>
<div class="flex content-container">
	<div class="vgrid-width-full hgrid-w2of3 hgrid-padding-right margin-bottom">
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
			<?php echo $ws_content->description->innerHTML(); ?>
		</div>
<?php
}
?>
		<div class="content">
<?php
if($ws_content->mainContentOfPage){
	echo $ws_content->mainContentOfPage->innerHTML();
}
?>
			<nav>
<?php
	include_template('template-parts/nav-contact-ul');
?>
			</nav>
		</div>
	</div>
	<nav class="vgrid-width-full hgrid-w1of3">
<?php include_template('template-parts/contact-cta'); ?>
	</nav>
</div>
<?php
include_template('template-parts/footer');
?>
