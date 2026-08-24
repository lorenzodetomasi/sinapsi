<?php
// The Business Contact template file
// @package WS
// @subpackage Localbiz
// @since WS 1.0
global $ws_headings, $ws_query, $ws_content;
$GLOBALS['ws_html_attributes']['html']['class'][] = 'form';
if(!empty($_GET["to"])){
	$GLOBALS['to_business'] = ws_content('localbiz/localbizs/'.$_GET["to"]);
	global $to_business;
	if(!empty($to_business->name->innerHTML())){
		$name = sprintf(__('Contact %s'), '<strong>'.$to_business->name->innerHTML().'</strong>');
	}
}
if(empty($name) and $ws_content->name->innerHTML()){
	$name = $ws_content->name->innerHTML();
}
include_template('template-parts/header');
?>
<div class="content-container">
<?php
if(!empty($name)){
?>
	<h1 itemprop="name"><?php echo $name; ?></h1>
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
	<form action="<?php echo ws_href('impresa-contattata').'?to='.urlencode($to_business['id']).'&amp;parent='.urlencode($ws_query['wspath']); ?>" method="POST" name="contact-form">
<?php
include_template('template-parts/contact-form-header');
include_template('template-parts/contacts-basic');
include_template('template-parts/business-checkboxes');
include_template('template-parts/user-checkboxes');
?>
		<p class="submit">
			<button type="submit" class="button">
				<span class="text"><?php _e('Submit message'); ?></span>
				<i class="material-icons right">send</i>
			</button>
		</p>
	</form>
</div>
<?php
include_template('template-parts/footer');
?>
