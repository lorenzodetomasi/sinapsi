<?php
// The Today Opening Hours Template
// @package WS
// @subpackage Localbiz_Theme
// @since Localbiz_Theme 1.0
global $openingHours, $specialHours;
if($openingHours){
$today = new DateTime();
//print_r($openingHours);
//print_r($specialHours);
$current_weekday = 'http://schema.org/'.date("l");
$current_weekday_2chars = substr(date("D"), 0, -1);// Returns Mo | Tu | We | Th | Fr | Sa | Su
$opening_hours = $google_place_obj->result->opening_hours;
if($opening_hours->open_now == 1){
	$open_now = __('Now we are <strong>open</strong>.');
} else {
	$open_now = __('Now we are <strong>closed</strong>.');
}
?>
	<p><?php /*Oggi aperto dalle 12:00 alle 15:00 e dalle 19:00 alle 23:00*/ echo $open_now; ?> <a href="#opening-hours" class="link"><?php _e('Opening Hours'); ?></a></p>
<?php
}
?>
