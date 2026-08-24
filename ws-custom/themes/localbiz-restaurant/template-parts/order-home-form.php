<?php
// The Contact Form Php template
// @package WS
// @subpackage Localbiz
// @since WS 1.0
global $ws_headings;
$current_user = false;
$user_email = ''; $emailValueAttr = ''; $acceptanceCheckedAttr = ''; $newsletter_subscriptionCheckedAttr = ''; $telephoneValueAttr = '';
if(!empty($current_user)){
	$user_ID = $current_user->ID;
	if($user_ID){
		$user_email = $current_user->user_email;
		$emailValueAttr = ' value="'.$user_email.'"';
		if(get_field('telephone', 'user_'.$user_ID)){
			$telephoneValueAttr = ' value="'.get_field('telephone', 'user_'.$user_ID).'"';
		}
		if(get_field('acceptance', 'user_'.$user_ID) == 1){
			$acceptanceCheckedAttr = ' checked="checked"';
		}
		if(get_field('newsletter_subscription', 'user_'.$user_ID) == 1){
			$newsletter_subscriptionCheckedAttr = ' checked="checked"';
		}
	}
}
?>
<div class="flex hgrid-cols2">
	<p class="question vgrid-width-full">
		<label class="flex field width-full">
			<span class="label"><?php _e('Your email'); ?></span><input type="email" name="email"<?php echo $emailValueAttr; ?> class="flex1" required placeholder="<?php _e('your@email.com'); ?>" />
		</label>
	</p>
	<p class="question vgrid-width-full">
		<label class="flex field width-full">
			<span class="label"><?php _e('Phone'); ?></span>
			<input type="text" name="telephone" class="flex1" placeholder="<?php _e('06 12 34 567'); ?>" />
		</label>
	</p>
</div>
<fieldset class="question">
	<label class="field postal-address flex">
		<span class="label">
			<span class="label-text"><?php _e('Delivery address'); ?></span><br />
			<small><?php _e('Enter your street address and city…'); ?></small>
		</span>
		<input id="autocomplete" name="postal-address" type="text" class="flex1" placeholder="<?php printf(__('E.g. %s'), 'piazza del Colosseo, 1, Roma RM, Italia'); ?>" />
	</label>
	<label class="field flex">
		<span class="label">
			<span class="label-text"><?php _e('Name on the intercom'); ?></span>
		</span>
		<input id="autocomplete" name="intercom-name" type="text" class="flex1" placeholder="<?php printf(__('E.g. %s'), __('John Smith')); ?>" />
	</label>
</fieldset>
<div class="question width-full">
	<label class="field textarea width-full">
		<span class="label"><?php _e('Your Order'); ?></span><br />
		<textarea class="width-full" name="message" placeholder="<?php _e('Describe your order in detail'); ?>"></textarea>
	</label>
</div>
