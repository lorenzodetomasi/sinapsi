<?php
// The Contact Form Php template
// @package WS
// @subpackage Localbiz
// @since WS 1.0
global $ws_headings, $ws_contentmap, $ws_content;
$index_url = $ws_headings->ws_path[0];
?>
<form action="<?php echo ws_href('/grazie'); ?>" method="POST" name="contact-form">
<?php
include_template('template-parts/contacts-basic');
include_template('template-parts/user-checkboxes');
?>
	<p class="submit">
		<button type="submit" class="button">
			<span class="text"><?php _e('Submit message'); ?></span>
			<i class="material-icons right">send</i>
		</button>
	</p>
</form>
