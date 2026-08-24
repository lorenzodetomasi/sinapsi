<?php
// The Contact Form Php template
// @package WS
// @subpackage Localbiz
// @since WS 1.0
global $ws_headings, $locations;
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
<div class="flex vgrid-width-full hgrid-cols2">
	<p class="flex question">
		<label class="flex field">
			<span class="label align-top"><?php _e('Reservation in the name of'); ?>&nbsp;</span>
			<span class="input person-name flex1">
				<input name="person-name" type="text" placeholder="<?php _e('i.e. John Smith'); ?>" />
				<small class="help"><?php _e('Enter the name and surname of the person to whom the table will be reserved'); ?></small>
			</span>
		</label>
	</p>
	<p class="flex question">
		<label class="flex field">
			<span class="label align-top"><?php _e('Offer code'); ?>&nbsp;</span>
			<span class="input offer-id flex1">
				<input name="offer-id" type="text" <?php echo $offer_id; ?> />
			</span>
		</label>
	</p>
</div>
<div class="flex vgrid-width-full hgrid-cols2">
	<p class="question vgrid-width-full">
		<label class="flex field">
			<strong class="label"><?php _e('Email'); ?>&nbsp;</strong><input type="email" name="email"<?php echo $emailValueAttr; ?> class="flex1" required="required" placeholder="<?php _e('your@email.com'); ?>" />
		</label>
	</p>
	<p class="question vgrid-width-full">
		<label class="flex field">
			<span class="label"><?php _e('Phone'); ?>&nbsp;</span>
			<input type="text" name="telephone" class="flex1" placeholder="<?php _e('06 12 34 567'); ?>"<?php echo $telephoneValueAttr; ?> />
		</label>
	</p>
</div>
<div class="flex">
<?php
$workLocations = $locations->workLocation;
if($workLocations->count() > 1){
?>
<p class="question">
	<label class="flex field">
		<span class="label"><?php _e('Location'); ?>&nbsp;</span>
		<select name="sede">
<?php
foreach($workLocations as $location){
?>
		<option value="<?php echo $location->name; ?>"><?php echo $location->name; ?></option>
<?php
}
?>
			</select>
		</label>
	</p>
<?php
}
?>
</div>
<div class="flex vgrid-width-full hgrid-cols2">
	<p class="question">
		<label class="flex field">
			<strong class="label"><?php _e('Date'); ?>&nbsp;</strong><input type="date" name="date" required="required" class="flex1" />
		</label>
	</p>
	<p class="question">
		<label class="flex field">
			<strong class="label"><?php _e('Time'); ?>&nbsp;</strong>
			<input type="time" name="time" required="required" class="flex1" />
		</label>
	</p>
</div>
<div class="flex vgrid-width-full vgrid-width-full hgrid-cols2">
	<p class="question">
		<label class="flex field">
			<span class="label"><?php _e('Adults and children on chairs'); ?>&nbsp;</span><input type="number" name="seats" class="flex1" />
		</label>
	</p>
	<p class="question">
		<label class="flex field">
			<span class="label"><?php _e('Babies on high chairs'); ?>&nbsp;</span><input type="number" name="high-chairs" class="flex1" />
		</label>
	</p>
</div>
<fieldset class="fieldset">
	<legend><?php _e('People with allergies or intolerances to substances or drugs'); ?></legend>
	<p class="question">
		<label class="flex field">
			<span class="label"><?php _e('Name'); ?></span>
			<input type="text" name="allergic_person-001-name" class="flex1" />
		</label>
	</p>
	<p class="question">
		<label class="field">
			<span class="label"><?php _e('Informations'); ?><br /><small>Specificare i farmaci e gli allergeni a cui è sensibile</small></span>
			<textarea class="width-full" name="allergic_person-001-infos"></textarea>
		</label>
	</p>
</fieldset>
<p class="question">
	<label class="field">
		<span class="label"><?php _e('Notes'); ?><br /><small>Specificare eventuali richieste</small></span>
		<textarea class="width-full" name="allergic_person-001-infos"></textarea>
	</label>
</p>
