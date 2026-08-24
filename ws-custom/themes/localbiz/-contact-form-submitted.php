<?php
// The Form submitted template
// @package WS
// @subpackage Localbiz
// @since WS 1.0
error_reporting(E_ALL);
global $ws_locales, $ws_headings, $ws_content, $ws_contentmap, $ws_content_root;
include_template('template-parts/header');
// Generates a boundary
$mail_boundary = "=_NextPart_" . md5(uniqid(time()));

$privacyPageLink = ws_pageLink('PrivacyPage');
$privacyPageUrl  = ws_href(ws_pageDOMElements('PrivacyPage')[0]->wspath);

if($_POST){
	if(isset($_SERVER['HTTP_REFERER'])) {
		$url = $_SERVER['HTTP_REFERER'];
	}
	if($ws_headings->mainEntity->email){
		$admin_email = $ws_headings->mainEntity->email;
	} else {
		$admin_email = WS_ADMIN_EMAIL;
	}
	$admin_name = $ws_headings->mainEntity->name;
	$user_email = $_POST["email"];
	$user_name = isset($_POST["user_name"]) ? $_POST["user_name"] : $user_email;
	if(isset($_POST["subject"])){
    $subject = sprintf($_POST["subject"], $user_email);
	} else {
		$user_subject = sprintf(__('[%1$s] Your request'), $admin_name);
		$admin_subject = sprintf(__('[%1$s] Request from %2$s'), $admin_name, $user_email);
	}
	if(isset($_POST["action"])){
	    $action = $_POST["action"];
	} else {
		$action = false;
	}

	$headers = 'From: '.$admin_name.' <'.$admin_email.'>\n';
	$headers .= "MIME-Version: 1.0\n";
	$headers .= "Content-Type: multipart/alternative;\n\tboundary=\"$mail_boundary\"\n";
	$headers .= "X-Mailer: PHP " . phpversion();
	//$headers[] = 'Cc: John Q Codex <jqc@wordpress.org>';
	//$headers[] = 'Cc: iluvwp@wordpress.org'; // note you can just use a simple email address

/*
	$user_id = email_exists($user_email);
	if ( !$user_id and $user_name ) {
		$random_password = wp_generate_password( $length=12, $include_standard_special_chars=false );
		$user_id = wp_create_user( $user_name, $random_password, $user_email );
	} else {
		$random_password = __('L’utente risulta già registrato. La password è stata ereditata.', 'isotype')." \r\n".sprintf(__('Per modificare o cancellare i tuoi dati personali, accedi all’indirizzo <%s>.', 'isotype'), admin_url())."\r\n";

	}
	if(!$user_id or (isset($_POST["save-postal-address"]) and $_POST["save-postal-address"] == 'on')){
		$postalAddressValue = array(
			array(
				"postalAddress"	=> $_POST["postal-address"]
			)
		);
		update_field('places', $postalAddressValue, 'user_'.$user_id);
	}
	if(!$user_id or isset($_POST["telephone"])){
		update_field('telephone', $_POST["telephone"], 'user_'.$user_id);
	}
	if(!$user_id or isset($_POST["acceptance"])){
		update_field('acceptance', $_POST["acceptance"], 'user_'.$user_id);
	}
	if(!$user_id or isset($_POST["newsletter_subscription"])){
		update_field('newsletter_subscription', $_POST["newsletter_subscription"], 'user_'.$user_id);
	}
*/
	// Email Message Template
	//$message = serialize($_POST);
	$currentDateTimeObj = new DateTime();

	// Message to WS User
	$message_to_user = __('We will get back to you as soon as possible. If you do not receive answers, please report it to us by replying to this email.')."\r\n";

	// Message to WS Admin
	$message_to_admin = sprintf(__('A %1$s user has sent a message.'), $admin_name)."\r\n";
	$user_data = "[".sprintf(__('Language: %s'), $ws_locales['content'])."; ";
	$user_data .= sprintf(__('User IP address: %1$s; @%2$s'), real_client_ip_address(), $currentDateTimeObj->format(DateTime::ISO8601))."]\r\n\r\n";
	$message_to_admin .= $user_data;
	$form_fields = '';
	foreach($_POST as $key=>$value){
		if($key == 'acceptance' and $value == 'on'){
			$message_to_admin .= sprintf(__('The user has stated that he has read and accepted your Privacy Policy %s.'), '<'.$privacyPageUrl.'>')."\r\n";
			$message_to_user .= sprintf(__('You stated that you have read and accepted our Privacy Policy %s.'), '<'.$privacyPageUrl.'>')."\r\n";
		} else if($key == 'ws_create_user' and $value == 'on'){
			$message_to_admin .= __('The user has authorized the storage of his contact information.')."\r\n";
			$message_to_user .= __('You have authorized the storage of your contact information.')."\r\n";
		} else if($key == 'newsletter_subscription' and $value == 'on'){
			$message_to_admin .= __('The user has authorized the sending of our best tips and offers by email.')."\r\n";
			$message_to_user .= __('You have authorized the sending of our best tips and offers by email.')."\r\n";
		} else if($key == 'email'){
			$form_fields .= "\r\n".sprintf(__('%1$s: %2$s'), __('Email'), $value)."\r\n";
		} else if($key == 'name'){
			$form_fields .= sprintf(__('%1$s: %2$s'), __('Name'), $value)."\r\n";
		} else if($key == 'telephone'){
			$form_fields .= sprintf(__('%1$s: %2$s'), __('Phone'), $value)."\r\n";
		} else if($key == 'postal-address'){
			$form_fields .= "\r\n".sprintf(__('%1$s: %2$s'), __('Postal address'), $value)."\r\n";
		} else if($key == 'intercom-name'){
			$form_fields .= "\r\n".sprintf(__('%1$s: %2$s'), __('Name on the intercom'), $value)."\r\n";
		} else if($key == 'message'){
			$form_fields .= "\r\n".sprintf(__('%1$s: %2$s'), __('Message'), $value)."\r\n";
		} else if(is_array($value)){
			$value = implode(", ", $value);
			$form_fields .= $key.": ".$value."; \r\n\r\n";
		} else {
			$form_fields .= $key.": ".$value."; \r\n\r\n";
		}
	}
	$message_to_user .= $form_fields;
	$message_to_admin .= $form_fields;
	$message_to_admin .= sprintf(__('%1$s: %2$s'), __('Reply now to your customer'), $user_email)."\r\n";
	$footer = "\r\n".sprintf(__('This email was sent to you by a form on <%s>.'), $url)."\r\n";
	$footer .= sprintf(__('If you have not authorized the sending of this email, contact us at the email address %s.'), $admin_email)."\r\n";
	$message_to_user .= $footer;
	$message_to_admin .= $footer;
	//PHP mail ( string $to , string $subject , string $message_to_admin [, mixed $additional_headers [, string $additional_parameters ]] ) : bool
?>
<div<?php echo ws_html_attributes('main-content'); ?>>
<?php
// Html Page Template
?>
	<div id="submitted">
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
<?php
	if($admin_email){
		// Imposta il Return-Path (funziona solo su hosting Windows)
		ini_set("sendmail_from", $admin_email);
		if(mail( $admin_email, $admin_subject, $message_to_admin, $headers, "-f$admin_email" )){
			echo '<p>'.sprintf(__('Your message has been sent to %1$s %2$s.'), $admin_name, '&lt;'.$admin_email.'&gt;').'<br />';
			echo '<p>'.__('We will get back to you as soon as possible. If you do not receive answers, please report it to us.').'</p>';
		} else {
			echo '<p>'.sprintf(__('We have encountered problems sending your message to %1$s %2$s.'), $admin_name, '&lt;'.$admin_email.'&gt;').'</p>';
		}
	}
	if(mail( $user_email, $user_subject, $message_to_user, $headers, "-f$admin_email" )){
		echo '<p>'.sprintf(__('A copy of your message has been sent to your email %s.'), '&lt;'.$user_email.'&gt;').'</p>';
	} else {
		echo '<p>'.sprintf(__('We have encountered problems sending a copy of your message to your email %s.'), '&lt;'.$user_email.'&gt;').'</p>';
	}
}
?>
</div>
<?php
include_template('template-parts/footer');
?>
