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
<p class="question width-full">
	<label class="flex field width-full">
		<strong class="label"><?php _e('Your email'); ?></strong>
		<input type="email" name="email"<?php echo $emailValueAttr; ?> class="input flex1" placeholder="<?php _e('your@email.com'); ?>" required />
	</label>
</p>
<p class="question width-full">
	<label class="flex field width-full">
		<span class="label"><?php _e('Phone'); ?></span>
		<input type="text" name="telephone" class="input flex1" placeholder="<?php _e('06 12 34 567'); ?>" />
	</label>
</p>
<p class="question width-full">
	<label class="field textarea width-full">
		<strong class="label"><?php _e('Message'); ?></strong><br />
		<textarea class="width-full" name="message" placeholder="<?php _e('Why are you contacting us?'); ?>" required></textarea>
	</label>
</p>
