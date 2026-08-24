<?php
// The Upload Files Php template
// @package WS
// @subpackage Localbiz
// @since WS 1.0
$files = $_FILES;
$attachments = array();
global $ws_headings, $to_business;
$admin_name = $ws_headings->mainEntity->name;
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
$attachments_num = count($attachments);
if($attachments_num > 0){
	$message_to_admin .= sprintf( _n( 'Allegato', '%s allegati', $attachments_num, 'isotype' ), number_format_i18n($attachments_num) );
	$message_to_admin .=  ': '.$attachments[0]."\r\n\r\n";
}
if($user_name and $random_password){
	$message_to_admin .= sprintf(__('Nome utente: %s', 'isotype'), $user_name)."\r\n";
	$message_to_admin .= sprintf(__('Password: %s', 'isotype'), $random_password)."\r\n\r\n";
}
?>
