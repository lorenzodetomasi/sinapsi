<?php
// The Business Checkboxes Php template
// @package WS
// @subpackage Localbiz
// @since WS 1.0
global $ws_headings, $to_business;
$admin_name = $ws_headings->mainEntity->name;
?>
<?php
if($to_business->monitoring_authorized == 'true'){
	$checked = ' checked="checked"';
} else {
	$checked = '';
}
?>
<p class="question">
	<label class="field checkbox">
		<input name="cc_admin" type="checkbox"<?php echo $checked; ?> />
		<span class="label">
			<span class="label-text"><?php printf(__('Improve the quality of this service, sending a copy of your message to %s'), $admin_name); ?></span>
		</span>
	</label>
</p>
