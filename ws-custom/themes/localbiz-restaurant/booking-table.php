<?php
// The Booking Table Php template
// @package WS
// @subpackage Localbiz
// @since WS 1.0
global $ws_sitemap, $ws_content, $longDate, $locations,
	$site_url, $user_ID, $template_directory_url, $privacy_page_id;
	if(!empty($_GET["offer_id"])){
		$offer_id = ' value="'.$_GET["offer_id"].'"';
	}
$GLOBALS['ws_html_attributes']['html']['class'][] = 'page';
include_template('template-parts/header');
?>
<div class="content-container">
<?php
if($ws_content->name){
?>
<h1 itemprop="name"><?php echo $ws_content->name->innerHTML(); ?></h1>
<?php
}
?>
<div class="flex">
	<div class="vgrid-width-full hgrid-w2of3 hgrid-padding-right margin-bottom">
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
if(!empty($ws_content->mainContentOfPage)){
	echo $ws_content->mainContentOfPage->innerHTML();
} else {
?>
			<p><?php _e('Leave your contact information: you will be contacted soon to be informed on the <strong>outcome of the reservation</strong>.'); ?></p>
<?php
}
?>
			<form method="POST" action="<?php echo ws_href('/grazie'); ?>" name="booking-table" enctype="multipart/form-data">
<?php
include_template('template-parts/booking-table-form');
include_template('template-parts/user-checkboxes');
?>
				<p class="submit">
					<button type="submit" class="button h48">
						<span class="button-text"><?php _e('Submit booking request'); ?></span>
						<i class="material-icons right">send</i>
					</button>
				</p>
			</form>
		</div>
	</div>
	<div class="vgrid-width-full hgrid-w1of3">
		<nav>
		<?php include_template('template-parts/contact-cta'); ?>
		</nav>
		<nav class="margin-bottom padding background-color2">
			<h1><?php _e('Other'); ?></h1>
	<?php
	include_template('template-parts/nav-contact-ul');
	?>
		</nav>
	</div>
</div>
<?php
include_template('template-parts/footer');
?>
