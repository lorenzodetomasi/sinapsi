<?php
// The Contact Form Php template
// @package WS
// @subpackage Localbiz
// @since WS 1.0
global $ws_headings;
$admin_name = $ws_headings->mainEntity->name;
$current_user = false;
$user_email = ''; $emailValueAttr = ''; $acceptanceCheckedAttr = ''; $newsletter_subscriptionCheckedAttr = ''; $telephoneValueAttr = '';
if(!empty($current_user)){
	$user_ID = $current_user->ID;
	if($user_ID){
		if(get_field('acceptance', 'user_'.$user_ID) == 1){
			$acceptanceCheckedAttr = ' checked="checked"';
		}
		if(get_field('newsletter_subscription', 'user_'.$user_ID) == 1){
			$newsletter_subscriptionCheckedAttr = ' checked="checked"';
		}
	}
}
?>
<?php
if(empty($user_ID)){
?>
<p class="question">
	<label class="field checkbox">
		<input type="checkbox" name="ws_create_user" />
		<span class="label">
			<span class="label-text"><?php _e('Save your contact details'); ?></span>
		</span>
	</label>
</p>
<?php
}
?>
<p class="question">
	<label class="field checkbox">
		<input type="checkbox" name="newsletter_subscription" />
		<span class="label">
			<span class="label-text"><?php _e('Receive our best tips and offers by email'); ?></span>
		</span>
	</label>
</p>
<?php /*
Per ricevere comunicazioni tramite posta elettronica e/o canali telefonici, materiale pubblicitario, informativo e informazioni commerciali
Per ricevere comunicazioni personalizzate, quindi per attività di profilazione e ricerche statistiche e di mercato
Per effettuare rilevazioni del grado di soddisfazione sulla qualità dei servizi forniti (anche tramite soggetti terzi)
*/ ?>
<p class="question">
	<label class="field checkbox acceptance">
		<input name="acceptance" required type="checkbox"<?php echo $acceptanceCheckedAttr; ?> />
		<span class="label">
			<strong class="label-text"><?php printf(__('Accept our %s'), ws_pageLink('PrivacyPage', __('Privacy Policy'))); ?></strong>
		</span>
	</label>
</p>