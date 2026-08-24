<?php
// The Form submitted template
// @package WS
// @subpackage Localbiz
// @since WS 1.0
global $ws_locales, $ws_headings, $ws_content, $ws_contentmap, $ws_content_root;
$privacyPageLink = ws_pageLink('PrivacyPage');
$privacyPageUrl  = ws_href(ws_pageDOMElements('PrivacyPage')[0]->wspath);
$files = $_FILES;
$attachments = array();
include_template('template-parts/header');
/*
function upload_user_file( $file = array() ) {
	require_once( ABSPATH . 'wp-admin/includes/admin.php' );
      $file_return = wp_handle_upload( $file, array('test_form' => false ) );
      if( isset( $file_return['error'] ) || isset( $file_return['upload_error_handler'] ) ) {
          return false;
      } else {
          $filename = $file_return['file'];
          $attachment = array(
              'post_mime_type' => $file_return['type'],
              'post_title' => preg_replace( '/\.[^.]+$/', '', basename( $filename ) ),
              'post_content' => '',
              'post_status' => 'inherit',
              'guid' => $file_return['url']
          );
          $attachment_id = wp_insert_attachment( $attachment, $file_return['url'] );
          require_once(ABSPATH . 'wp-admin/includes/image.php');
          $attachment_data = wp_generate_attachment_metadata( $attachment_id, $filename );
          wp_update_attachment_metadata( $attachment_id, $attachment_data );
          if( 0 < intval( $attachment_id ) ) {
          	return $attachment_id;
          }
      }
      return false;
}
if( ! empty( $files ) ) {
	foreach( $files as $file ) {
		if( $file["size"] > 0 ) {
			$attachment_id = upload_user_file( $file );
			$attachment_url = wp_get_attachment_image_src($attachment_id, 'full')[0];
			$attachments[] = $attachment_url;
		}
	}
}
*/
if($_POST){
	$admin_email = WS_ADMIN_EMAIL;
	$admin_name = $ws_headings->mainEntity->name;
	if(!empty($_POST["business-email"])) {
		
	}
	$user_email = $_POST["email"];
	$user_name = isset($_POST["user_name"]) ? $_POST["user_name"] : $user_email;
	if(isset($_SERVER['HTTP_REFERER'])) {
		$url = $_SERVER['HTTP_REFERER'];
	}
	if(isset($_POST["subject"])){
	    $subject = sprintf($_POST["subject"], $user_email);
	} else {
		if($to_business_name){
			$user_subject = sprintf(__('[%1$s] Your request to %2$s'), $admin_name, $to_business_name);
		} else {
			$user_subject = sprintf(__('[%1$s] Your request'), $admin_name);
		}
		$to_business_subject = sprintf(__('[%1$s] Request from %2$s'), $admin_name, $user_email);
		if($to_business_name){
			$admin_subject = sprintf(__('[%1$s] Request for %2$s'), $admin_name, $to_business_name);
		} else {
			$admin_subject = $to_business_subject;
		}
	}
	if(isset($_POST["action"])){
	    $action = $_POST["action"];
	} else {
		$action = false;
	}
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

	// Message too WS User
	$message_to_user = sprintf(__('Thanks for using %1$s. We sent your message to %2$s %3$s.'), $admin_name, $to_business_name, '<'.$to_business_email.'>')."\r\n";
	if($to_business_name){
		$message_to_user = sprintf(__('%1$s will get back to you as soon as possible. If you do not receive answers, please report it to us by replying to this email.'), $to_business_name)."\r\n";
	} else {
		$message_to_user = __('We will get back to you as soon as possible. If you do not receive answers, please report it to us by replying to this email.')."\r\n";
	}
	// Message to selected Business
	$message_to_business = sprintf(__('A %1$s user has sent a message to %2$s %3$s.'), $admin_name, $to_business_name, '<'.$to_business_email.'>')."\r\n";
	$message_to_admin = sprintf(__('A %1$s user has sent a message.'), $admin_name)."\r\n";

	// Message to WS Admin
	$user_data = "[".sprintf(__('Language: %s'), $ws_locales['content'])."; ";
	$user_data .= sprintf(__('User IP address: %1$s; @%2$s'), real_client_ip_address(), $currentDateTimeObj->format(DateTime::ISO8601))."]\r\n\r\n";
	$message_to_business .= $user_data;
	$message_to_admin .= $user_data;
	foreach($_POST as $key=>$value){
		if($key == 'acceptance' and $value == 'on'){
			$acceptance = sprintf(__('The user has stated that he has read and accepted your Privacy Policy %s.'), '<'.$privacyPageUrl.'>')."\r\n";
			$message_to_business .= $acceptance;
			$message_to_admin .= $acceptance;
			$message_to_user .= sprintf(__('You stated that you have read and accepted our Privacy Policy %s.'), '<'.$privacyPageUrl.'>')."\r\n";
		} else if($key == 'ws_create_user' and $value == 'on'){
			$ws_create_user = __('The user has authorized the storage of his contact information.')."\r\n";
			$message_to_business .= $ws_create_user;
			$message_to_admin .= $ws_create_user;
			$message_to_user .= __('You have authorized the storage of your contact information.')."\r\n";
		} else if($key == 'newsletter_subscription' and $value == 'on'){
			$newsletter_subscription = __('The user has authorized the sending of our best tips and offers by email.')."\r\n";
			$message_to_business .= $newsletter_subscription;
			$message_to_admin .= $newsletter_subscription;
			$message_to_user .= __('You have authorized the sending of our best tips and offers by email.')."\r\n";
		} else if($key == 'email'){
			$form_fields .= "\r\n".sprintf(__('%1$s: %2$s'), __('Email'), $value)."\r\n";
		} else if($key == 'name'){
			$form_fields .= sprintf(__('%1$s: %2$s'), __('Name'), $value)."\r\n";
		} else if($key == 'telephone'){
			$form_fields .= sprintf(__('%1$s: %2$s'), __('Phone'), $value)."\r\n";
		} else if($key == 'business-postal-address'){
			$form_fields .= "\r\n".sprintf(__('%1$s: %2$s'), __('Business postal address'), $value)."\r\n";
		} else if($key == 'business-email'){
			$form_fields .= "\r\n".sprintf(__('%1$s: %2$s'), __('Email'), $value)."\r\n";
		} else if($key == 'business-url'){
			$form_fields .= "\r\n".sprintf(__('%1$s: %2$s'), __('Website'), $value)."\r\n";
		} else if($key == 'business-telephone'){
			$form_fields .= "\r\n".sprintf(__('%1$s: %2$s'), __('Phone'), $value)."\r\n";
		} else if($key == 'business-description'){
			$form_fields .= "\r\n".sprintf(__('%1$s: %2$s'), __('Description'), $value)."\r\n";
		} else if(is_array($value)){
			$value = implode(", ", $value);
			$form_fields .= $key.": ".$value."; \r\n\r\n";
		} else {
			$form_fields .= $key.": ".$value."; \r\n\r\n";
		}
	}
	$message_to_user .= $form_fields;
	$message_to_business .= $form_fields;
	$message_to_admin .= $form_fields;
/*
	$attachments_num = count($attachments);
	if($attachments_num > 0){
		$message_to_admin .= sprintf( _n( 'Allegato', '%s allegati', $attachments_num, 'isotype' ), number_format_i18n($attachments_num) );
		$message_to_admin .=  ': '.$attachments[0]."\r\n\r\n";
	}
	if($user_name and $random_password){
		$message_to_admin .= sprintf(__('Nome utente: %s', 'isotype'), $user_name)."\r\n";
		$message_to_admin .= sprintf(__('Password: %s', 'isotype'), $random_password)."\r\n\r\n";
	}
*/
	$footer .= "\r\n".sprintf(__('Questa email ti è stata inviata da un modulo su <%s>.', 'isotype'), $url)."\r\n";
	$footer .= sprintf(__('Se non hai autorizzato l’invio di questa email, contattaci all’indirizzo email %s.', 'isotype'), $admin_email)."\r\n";
	$message_to_user .= $footer;
	$message_to_business .= $footer;
	$message_to_admin .= $footer;
	$headers .= 'From: '.$admin_name.' <'.$admin_email.'>\r\n';
	$headers .= "X-Mailer: PHP/" . phpversion();
	//$headers[] = 'Cc: John Q Codex <jqc@wordpress.org>';
	//$headers[] = 'Cc: iluvwp@wordpress.org'; // note you can just use a simple email address
	$parameters = '-f '.$admin_email;
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
	if($to_business_email){
		if(mail( $to_business_email, $to_business_subject, $message_to_business, $headers )){
			echo '<p>'.sprintf(__('Your message has been sent to %1$s %2$s.'), $to_business_name, '&lt;'.$to_business_email.'&gt;').'<br />';
			printf(__('%1$s will get back to you as soon as possible. If you do not receive answers, please report it to us by replying to this email.'), $to_business_name).'</p>';
		} else {
			echo '<p>'.sprintf(__('We have encountered problems sending your message to %1$s %2$s.'), $to_business_name, '&lt;'.$to_business_email.'&gt;').'</p>';
		}
	}
	if(mail( $user_email, $user_subject, $message_to_user, $headers )){
		echo '<p>'.sprintf(__('A copy of your message has been sent to your email %s.'), '&lt;'.$user_email.'&gt;').'</p>';
	} else {
		echo '<p>'.sprintf(__('We have encountered problems sending a copy of your message to your email %s.'), '&lt;'.$user_email.'&gt;').'</p>';
	}
	if(mail( $admin_email, $admin_subject, $message_to_admin, $headers )){
		echo '<p>'.sprintf(__('Your message has also been sent to %s in order to improve the quality of this service.'), '&lt;'.$admin_email.'&gt;').'</p>';
	} else {
		echo '<p>'.sprintf(__('We have encountered problems sending your message to %s, in order to ensure the quality of this service.'), '&lt;'.$admin_email.'&gt;').'</p>';
	}
}
?>
</div>
<?php
include_template('template-parts/footer');
?>
