<?php
// The Contact Form Php template
// @package WS
// @subpackage Localbiz
// @since WS 1.0
// Recaptcha tutorial: https://stackoverflow.com/questions/50405977/how-to-verify-google-recaptcha-v3-response
global $ws_headings, $ws_contentmap, $ws_content;
$index_url = $ws_headings->ws_path[0];
?>
<form action="<?php echo ws_href('/grazie'); ?>" method="POST" name="contact-form" action="contact">
<?php
include_template('template-parts/contacts-basic');
include_template('template-parts/user-checkboxes');
?>
	<p class="small">Compila tutti i campi obbligatori (in <strong>grassetto</strong>).</p>
	<p class="submit">
		<button type="submit" class="button g-recaptcha"
		data-sitekey="<?php echo GOOGLE_RECAPTCHA_SITE_KEY; ?>" 
        data-callback='onSubmit' 
        data-action='submit_contact_form'>
			<span class="text"><?php _e('Submit message'); ?></span>
			<i class="material-symbols-outlined right">send</i>
		</button>
	</p>
</form>