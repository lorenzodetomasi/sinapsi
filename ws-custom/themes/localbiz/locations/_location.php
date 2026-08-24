<?php
// The Single Location template part file
// @package WS
// @subpackage Localbiz_Theme
// @since Localbiz_Theme 1.0
global $location;
$postalAddress = PostalAddress($location->address, array('output' => 'microdata','format' => 'singleline'));
?>
<div class="flex hgrid-cols2 margin-bottom-2x">
	<section class="address hgrid-padding-right">
		<h2 class="vgrid-text-align-center"><?php _e('Address'); ?></h2>
		<p><?php echo $postalAddress; ?></p>
		<p class="margin-top">
			<a class="button" href="https://www.google.com/maps/search/?api=1&amp;query=<?php echo urlencode($location->name.', '.$postalAddress); ?>" target="_blank"><i class="material-icons">directions</i> <span class="text"><?php _e('Directions'); ?></span></a>
		</p>
	</section>
	<section class="contacts hgrid-padding-left">
		<h2><?php _e('Contacts'); ?></h2>
		<nav class="nav">
			<ul>
<?php
foreach($location->telephone as $telephone){
?>
				<li><?php echo telephone($telephone); ?></li>
<?php
}
?>
<?php
foreach($location->email as $email){
?>
				<li><?php echo email($email); ?></li>
<?php
}
?>
			</ul>
		</nav>
	</section>
<?php
$paymentAccepted = $location->xpath('paymentAccepted[@lang="'.langArray()[0].'"]')[0];
if($paymentAccepted){
?>
	<section>
		<h2><?php _e('Accepted payment methods'); ?></h2>
		<?php echo $paymentAccepted; ?>
	</section>
<?php
}
?>
</div>
<div class="flex hgrid-cols2">
<?php
$openingHours = $location->openingHoursSpecification;
$GLOBALS['openingHours'] = $openingHours;
if(!empty($openingHours)){
?>
	<section class="opening-hours hgrid-padding-right">
<?php
	include_template('locations/_opening_hours');
?>
	</section>
<?php
}
?>
<?php
$specialHours = $location->specialHoursSpecification;
$GLOBALS['specialHours'] = $specialHours;
if(!empty($specialHours)){
?>
	<section class="special-hours hgrid-padding-right">
<?php
	include_template('locations/_special_hours');
?>
	</section>
<?php
}
?>
	<section class="contact-form hgrid-padding-left">
		<h2><?php _e('Contact form'); ?></h2>
<?php
	include_template('template-parts/contact-form');
?>
	</section>
</div>
