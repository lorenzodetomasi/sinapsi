<?php
// The Location list template file
// @package WS
// @subpackage Localbiz_Theme
// @since Localbiz_Theme 1.0
global $locations;
$ws_content_root_url = ws_content_root_url();
if($locations->count() == 1){
	$GLOBALS['location'] = $locations->location[0];
	global $location;
?>
<?php
if($location->map_static_image->image){
?>
<div class="map">
<?php
	if($location->google_directions_url){
?>
	<a href="<?php echo $location->google_directions_url; ?>" target="_blank">
<?php
	}
?>
		<?php echo get_media($location->map_static_image->image); ?>
<?php
	if($location->google_directions_url){
?>
	</a>
<?php
	}
?>
</div>
<?php
}
$postalAddress = PostalAddress($location->address, array('output' => 'microdata','format' => 'singleline'));
?>
<div class="content-container vgrid-padding-d2">
	<h1 class="margin-top vgrid-text-align-center"><?php echo $location->name; ?></h1>
	<div class="flex vgrid-cols1 hgrid-cols2 margin-bottom-2x">
		<div>
			<section class="address flex vgrid-align-center">
				<div>
					<h2 class="vgrid-text-align-center"><?php _e('Address'); ?></h2>
					<p><?php echo $postalAddress; ?></p>
					<p class="margin-top">
						<a class="button" href="https://www.google.com/maps/search/?api=1&amp;query=<?php echo urlencode($location->google_place_name.', '.$postalAddress); ?>" target="_blank"><i class="material-icons">directions</i> <span class="text"><?php _e('Directions'); ?></span></a>
					</p>
				</div>
			</section>
			<section class="contacts">
				<h2 class="vgrid-text-align-center"><?php _e('Contacts'); ?></h2>
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
		</div>
		<div>
<?php
$openingHours = $location->openingHoursSpecification;
$GLOBALS['openingHours'] = $openingHours;
if(!empty($openingHours)){
?>
			<section class="opening-hours hgrid-padding-right padding-bottom">
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
			<section class="special-hours hgrid-padding-right padding-bottom">
<?php
	include_template('locations/_special_hours');
?>
			</section>
<?php
}
?>
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
	</div>
<?php
} else if($locations->count() > 1){
	foreach($locations as $location){
		$GLOBALS['location'] = $locations->location[0];
?>
			<section class="section">
				<div class="map">
					<a href="<?php echo $location->google_directions_url; ?>" target="_blank">
						<?php //echo get_media($location->map_static_image->image); ?>
						<?php echo get_media($location->map_static_image->image); ?>
					</a>
				</div>
				<h1 class="content-container margin-top"><?php echo $location->name; ?></h1>
<?php include_template('locations/_location'); ?>
			</section>
<?php
	}
?>
<?php
}
?>
