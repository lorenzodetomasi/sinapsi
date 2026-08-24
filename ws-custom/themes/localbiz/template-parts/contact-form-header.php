<?php
// The Contact Form Php template
// @package WS
// @subpackage Localbiz
// @since WS 1.0
global $ws_headings, $ws_content, $to_business;
?>
<?php
	$before_contact_form = $ws_content->xpath('id("before-contact-form")')[0];
	if(!empty($before_contact_form->innerHTML())){
		echo $before_contact_form->innerHTML();
	}
?>
	<p><?php	_e('Bold fields are required.'); ?></p>
<?php
if($to_business->monitoring_authorized == 'true'){
	$admin_name = $ws_headings->mainEntity->name;
?>
<p><?php printf(__('We will send a copy of your message to %s, to improve the quality of this service.'), $admin_name); ?></p>
<?php
}
?>
