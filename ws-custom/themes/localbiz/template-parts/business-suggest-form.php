<?php
// The Business Suggestion Form Php template
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
<p class="question">
	<label class="field postal-address flex">
		<span class="label">
			<strong class="label-text"><?php _e('Business name and address'); ?></strong><br />
			<small><?php _e('Enter business name, street address and city…'); ?></small>
		</span>
		<input id="autocomplete" name="business-postal-address" type="text" required="required" class="flex1" placeholder="<?php printf(__('E.g. %s'), 'ISOTYPE.ORG, Via Angelo Bertolotto, Lido di Ostia, RM, Italia'); ?>" />
	</label>
</p>
<div class="flex hgrid-cols2">
	<p class="question vgrid-width-full">
		<label class="flex field width-full">
			<span class="label"><?php _e('Email'); ?></span><input type="email" name="business-email"<?php echo $emailValueAttr; ?> class="flex1" placeholder="<?php printf(__('E.g. %s'), __('email@business.com')); ?>" />
		</label>
	</p>
	<p class="question vgrid-width-full">
		<label class="flex field width-full">
			<span class="label"><?php _e('Website'); ?></span>
			<input type="text" name="business-url" class="flex1" placeholder="<?php printf(__('E.g. %s'), __('www.localbiz.it')); ?>" />
		</label>
	</p>
	<p class="question vgrid-width-full">
		<label class="flex field width-full">
			<span class="label"><?php _e('Phone'); ?></span>
			<input type="text" name="business-telephone" class="flex1" placeholder="<?php printf(__('E.g. %s'), __('06 12 34 567')); ?>" />
		</label>
	</p>
</div>
<div class="question width-full">
	<label class="field textarea width-full">
		<span class="label"><?php _e('If you want, describe the business'); ?></span><br />
		<textarea class="width-full" name="business-description" placeholder="<?php printf(__('E.g. %s'), __('offered services and/or products')); ?>"></textarea>
	</label>
</div>
<?php
if(empty($user_ID)){
?>
<fieldset class="fieldset">
	<legend><?php _e('Your contacts'); ?></legend>
	<div class="flex hgrid-cols3">
		<p class="question vgrid-width-full">
			<label class="flex field width-full">
				<strong class="label"><?php _e('Email'); ?></strong><input type="email" name="email"<?php echo $emailValueAttr; ?> class="flex1" required="required" placeholder="<?php _e('your@email.com'); ?>" />
			</label>
		</p>
		<p class="question vgrid-width-full">
			<label class="flex field width-full">
				<span class="label"><?php _e('Phone'); ?></span>
				<input type="text" name="telephone" class="flex1" placeholder="<?php _e('06 12 34 567'); ?>" />
			</label>
		</p>
		<p class="question">
			<label class="field checkbox">
				<input type="checkbox" name="from_business_owner" />
				<span class="label">
					<span class="label-text"><?php _e('You are the business owner'); ?></span>
				</span>
			</label>
		</p>
	</div>
<?php
}
include_template('template-parts/user-checkboxes');
?>
</fieldset>
