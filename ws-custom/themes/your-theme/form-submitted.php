<?php
// The Form submitted template
// @package WS
// @subpackage Localbiz
// @since WS 1.0
error_reporting(E_ALL);

global $ws_locales, $ws_headings, $ws_content, $ws_contentmap, $ws_content_root;
include_template('template-parts/header');
require_once(ws_plugins_abspath().'/google-recaptcha/ws-index.php');

if($_POST){
	// RECAPTCHA
	$g_recaptcha_response_keys = google_recaptcha_response_keys($_POST['g-recaptcha-response']);
	if ($g_recaptcha_response_keys["success"] && $g_recaptcha_response_keys["action"] == 'submit_contact_form' && $g_recaptcha_response_keys["score"] >= GOOGLE_RECAPTCHA_ALLOWED_SCORE) {
	    //Send email
		// Generates a boundary
		$mail_boundary = "=_NextPart_" . md5(uniqid(time()));

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

		$sender = $admin_email;

		// EMAIL HEADERS
		$headers = "From: $sender\n";
		$headers .= "MIME-Version: 1.0\n";
		$headers .= "Content-Type: multipart/alternative;\n\tboundary=\"$mail_boundary\"\n";
		$headers .= "X-Mailer: PHP " . phpversion();

		// EMAIL BODIES
		if(isset($_SERVER['HTTP_REFERER'])) {
			$url = $_SERVER['HTTP_REFERER'];
		}
		$currentDateTimeObj = new DateTime();
		$privacyPageLink = ws_pageLink('PrivacyPage');
		$privacyPageUrl  = ws_href(ws_pageDOMElements('PrivacyPage')[0]->wspath);
		$TXT_user_data = "[".sprintf(__('Language: %s'), $ws_locales['content'])."; ";
		$TXT_user_data .= sprintf(__('User IP address: %1$s; @%2$s'), real_client_ip_address(), $currentDateTimeObj->format(DateTime::ISO8601))."]\r\n\r\n";
		$TXT_form_fields = '';
		$HTML_form_fields = '';
		foreach($_POST as $key=>$value){
			if($key == 'acceptance' and $value == 'on'){
				$TXT_legal_to_admin = sprintf(__('The user has stated that he has read and accepted your Privacy Policy %s.'), '<'.$privacyPageUrl.'>')."\r\n";
				$HTML_legal_to_admin = "<p>".sprintf(__('The user has stated that he has read and accepted your Privacy Policy %s.'), '<'.$privacyPageUrl.'>')."</p>";
			} else if($key == 'g-recaptcha-response' and !empty($value)){
				$g_recaptcha_response_TXT = implode("; ", $g_recaptcha_response_keys);
				$TXT_legal_to_admin .= sprintf(__('Google Recaptcha: %s'), $g_recaptcha_response_TXT);
				$HTML_legal_to_admin .= "<p>".sprintf(__('Google Recaptcha: %s'), $g_recaptcha_response_TXT)."</p>";
			} else if($key == 'ws_create_user' and $value == 'on'){
				$TXT_legal_to_admin .= __('The user has authorized the storage of his contact information.')."\r\n";
				$HTML_legal_to_admin .= "<p>".__('The user has authorized the storage of his contact information.')."</p>";
			} else if($key == 'newsletter_subscription' and $value == 'on'){
				$TXT_legal_to_admin .= __('The user has authorized the sending of our best tips and offers by email.')."\r\n";
				$HTML_legal_to_admin .= "<p>".__('The user has authorized the sending of our best tips and offers by email.')."</p>";
			} else if($key == 'email'){
				$TXT_form_fields .= "\r\n".sprintf(__('%1$s: %2$s'), __('Email'), $value)."\r\n";
				$HTML_form_fields .= sprintf(__('%1$s: %2$s'), "<strong>".__('Email')."</strong>", $value)." <br />";
			} else if($key == 'name'){
				$TXT_form_fields .= sprintf(__('%1$s: %2$s'), __('Name'), $value)."\r\n";
				$HTML_form_fields .= sprintf(__('%1$s: %2$s'), "<strong>".__('Name')."</strong>", $value)." <br />";
			} else if($key == 'telephone'){
				$TXT_form_fields .= sprintf(__('%1$s: %2$s'), __('Phone'), $value)."\r\n";
				$HTML_form_fields .= sprintf(__('%1$s: %2$s'), "<strong>".__('Phone')."</strong>", $value)." <br />";
			} else if($key == 'postal-address'){
				$TXT_form_fields .= "\r\n".sprintf(__('%1$s: %2$s'), __('Postal address'), $value)."\r\n";
				$HTML_form_fields .= sprintf(__('%1$s: %2$s'), "<strong>".__('Postal address')."</strong>", $value)." <br />";
			} else if($key == 'intercom-name'){
				$TXT_form_fields .= "\r\n".sprintf(__('%1$s: %2$s'), __('Name on the intercom'), $value)."\r\n";
				$HTML_form_fields .= sprintf(__('%1$s: %2$s'), "<strong>".__('Name on the intercom')."</strong>", $value)." <br />";
			} else if($key == 'message'){
				$TXT_form_fields .= "\r\n".sprintf(__('%1$s: %2$s'), __('Message'), $value)."\r\n";
				$HTML_form_fields .= sprintf(__('%1$s: %2$s'), "<strong>".__('Message')."</strong>", $value)." <br />";
			} else if(is_array($value)){
				$value = implode(", ", $value);
				$TXT_form_fields .= $key.": ".$value."; \r\n\r\n";
				$HTML_form_fields .= "<strong>".$key."</strong>: ".$value." <br />";
			} else {
				$TXT_form_fields .= $key.": ".$value."; \r\n\r\n";
				$HTML_form_fields .= "<strong>".$key."</strong>: ".$value." <br />";
			}
		}

		$TXT_thanks = sprintf(__('Thank you for your request to %s'), $admin_name).".\r\n";
		$TXT_thanks .= __('We will get back to you as soon as possible. If you do not receive answers, please report it to us by replying to this email.')."\r\n";
		$TXT_footer = "\r\n".sprintf(__('This email was sent to you by a form on <%s>.'), $url)."\r\n";
		$TXT_footer .= sprintf(__('If you have not authorized the sending of this email, contact us at the email address %s.'), $admin_email)."\r\n";
		$HTML_footer = "<p>".sprintf(__('This email was sent to you by a form on %s.'), $url)." <br />";
		$HTML_footer .= sprintf(__('If you have not authorized the sending of this email, contact us at the email address %s.'), $admin_email)." </p>";

		$TXT_message_to_admin = $TXT_user_data . $TXT_legal_to_admin . $TXT_form_fields . $TXT_footer;
		$HTML_message_to_admin = "<p>".$TXT_user_data."</p>".$HTML_legal_to_admin."<p>".$HTML_form_fields."</p>"."<p>".$HTML_footer."</p>";

		// TXT format
		$message_to_admin = "This is a multi-part message in MIME format.\n\n";
		$message_to_admin .= "--$mail_boundary\n";
		$message_to_admin .= "Content-Type: text/plain; charset=\"iso-8859-1\"\n";
		$message_to_admin .= "Content-Transfer-Encoding: 8bit\n\n";

		// Add message in TXT format
		$message_to_admin .= $TXT_message_to_admin;

		$message_to_admin .= "\n--$mail_boundary\n";
		$message_to_admin .= "Content-Type: text/html; charset=\"iso-8859-1\"\n";
		$message_to_admin .= "Content-Transfer-Encoding: 8bit\n\n";

		// Add message in HTML format
		$message_to_admin .= $HTML_message_to_admin;

		// Boundary di terminazione multipart/alternative
		$message_to_admin .= "\n--$mail_boundary--\n";
?>
<div<?php echo ws_html_attributes('main-content'); ?>>
	<div id="submitted">
<?php
		if($ws_content->name){
?>
		<h1 itemprop="name"><?php echo $ws_content->name->innerHTML(); ?></h1>
<?php
		}
		// Imposta il Return-Path (funziona solo su hosting Windows)
		ini_set("sendmail_from", $sender);

		// PHP mail ( string $to , string $subject , string $message [, mixed $additional_headers [, string $additional_parameters ]] ) : bool
		// The parameter "-f$sender" sets Return-Path on hosting Linux
		// Send message to admin
		if (mail($admin_email, $admin_subject, $message_to_admin, $headers, "-f$sender")) {
			echo '<p>'.sprintf(__('Your message has been sent to %1$s %2$s.'), $admin_name, '&lt;'.$admin_email.'&gt;').'</p>';
			echo '<p>'.__('We will get back to you as soon as possible. If you do not receive answers, please report it to us.').'</p>';
		} else {
			echo '<p>'.sprintf(__('We have encountered problems sending your message to %1$s %2$s.'), $admin_name, '&lt;'.$admin_email.'&gt;').'</p>';
		}
	} else {
		echo '<p>Sorry, but your request has not been submitted.</p>';
		echo '<p>Error with RECAPTCHA Validation.</p>';
		if ($g_recaptcha_response_keys["score"] && $g_recaptcha_response_keys["score"] < GOOGLE_RECAPTCHA_ALLOWED_SCORE) {
    		//failed spam test. Offer the visitor the option to try again or use an alternative method of contact.
    		echo '<p>Low Google Recaptcha Score.</p>';
    	} elseif($g_recaptcha_response_keys["error-codes"]) { //optional
			print_r($g_recaptcha_response_keys["error-codes"]);
    	}
	}
	// END RECAPTCHA
} else {
?>
		<p><?php _e('We have encountered problems sending your request.'); ?></p>
<?php
}
?>
	</div>
</div>
<?php
include_template('template-parts/footer');
?>