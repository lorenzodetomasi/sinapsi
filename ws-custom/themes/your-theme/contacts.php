<?php
// The Page template
// @package WS
// @subpackage Localbiz
// @since WS 1.0
global $ws_content, $ws_headings;
$GLOBALS['ws_html_attributes']['html']['class'][] = 'page';
$GLOBALS['ws_scripts']['head']['js_google_recaptcha'] = '
<!-- Google Recaptcha -->
<script src="https://www.google.com/recaptcha/api.js"></script>
<script>
	function onSubmit(token) {
		var form = document.forms["contact-form"];
        if(form.checkValidity()) {
        	console.log("Valid");
            form.submit();  
        } else {
            grecaptcha.reset();
            form.reportValidity();
        }
	}
 </script>';
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
				<div class="flex content">
					<div class="flex1">
<?php if($ws_headings->wip == "true"){ ?><p><?php _e("Website under construction."); ?></p><?php } ?>
<?php
if($ws_content->mainContentOfPage){
	echo $ws_content->mainContentOfPage->innerHTML();
}
?>
<?php
foreach ($ws_content->section as $section) {
	if($section['class'] == "form"){
		ws_echo($section->innerHTML());
	} else {
		echo $section->innerHTML();
	}
}
?>
					</div>
<?php
include_template('template-parts/nav-contact-ul');
?>
				</div>
<?php
include_template('template-parts/locations');
?>
			</div>
<?php
include_template('template-parts/footer');
?>
