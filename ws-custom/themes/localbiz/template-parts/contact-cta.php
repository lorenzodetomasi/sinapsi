<?php
// The Contact Page Call to action Nav Php template
// @package WS
// @subpackage Localbiz
// @since WS 1.0
//global $ws_query, $rewrite_rule, $ws_headings, $ws_contentmap, $ws_content;
global $ws_headings;
$emergency = $ws_headings->mainEntity->emergency[0];
$email = $ws_headings->mainEntity->email[0];
$telephone = $ws_headings->mainEntity->telephone;
if($emergency){
?>
<p><a class="button h48 width-full emergency" href="tel:<?php echo telephone(array('output' => 'iso')); ?>">
	<i class="material-icons width-full margin-bottom-d2">local_hospital</i>
	<strong class="text width-full margin-bottom-d2"><?php _e('For <strong>emergencies</strong>'); ?></strong>
	<span class="width-full lowercase">
		<?php _e('even on holidays'); ?><br />
		<?php printf(__('Call %s immediately'), $telephone); ?>
	</span>
</a></p>
<?php
}
if(!empty($telephone)){
?>
<p><a class="button h48 width-full" href="tel:<?php echo $telephone_iso; ?>">
	<i class="material-icons width-full margin-bottom-d2">phone</i>
	<span class="width-full"><?php _e('Call now'); ?></span>
	<small class="width-full lowercase"><?php echo $telephone; ?></small>
</a></p>
<?php
}
if(!empty($email)){
?>
<p><a class="button h48 width-full" href="mailto:<?php echo $email; ?>">
	<i class="material-icons width-full margin-bottom-d2">email</i>
	<span class="width-full"><?php _e('Write us by email'); ?></span>
	<small class="width-full lowercase"><?php echo $email; ?></small>
</a></p>
<?php
}
?>
