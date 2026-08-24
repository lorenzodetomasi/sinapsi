<?php
// The Contact Php template
// @package WS
// @subpackage Localbiz
// @since WS 1.0
global $ws_headings, $ws_content;
include_template('template-parts/header');
?>
<div class="content-container padding-top-2x">
<?php
if($ws_content->name){
?>
	<h1 itemprop="name"><?php echo $ws_content->name; ?></h1>
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
?>
<?php
if($ws_content->has_contact_form != 'false'){
?>
	<div class="flex">
		<div class="vgrid-width-full hgrid-w2of3 hgrid-padding-right margin-bottom">
<?php
	$section = $ws_content->xpath('id("before_form")')[0];
	if($section){
		echo $section;
	}
	include_template('template-parts/contact-form');
?>
	</div>
	<div class="vgrid-width-full hgrid-w1of3">
		<nav class="margin-bottom padding background-color2">
			<h1><?php _e('Other'); ?></h1>
	<?php
	include_template('template-parts/nav-contact-ul');
	?>
		</nav>
		<nav>
		<?php include_template('template-parts/contact-cta'); ?>
		</nav>
	</div>
</div>
<?php
} else {
?>
<div class="flex cols2">
	<nav>
		<h1><?php _e('Book'); ?></h1>
<?php
include_template('template-parts/nav-contact-ul');
?>
	</nav>
<?php
	include_template('template-parts/contact-cta');
?>
</div>
<?php
}
?>
</div>
<?php
include_template('locations/_locations');
include_template('template-parts/footer');
?>
