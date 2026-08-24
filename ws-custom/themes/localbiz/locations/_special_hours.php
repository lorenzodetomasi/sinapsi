<?php
// The Single Location template part file
// @package WS
// @subpackage Localbiz_Theme
// @since Localbiz_Theme 1.0
global $openingHours, $specialHours;
$monday = $openingHours->xpath('./OpeningHoursSpecification[dayOfWeek = "http://schema.org/Monday"]');
$tuesday = $openingHours->xpath('./OpeningHoursSpecification[dayOfWeek = "http://schema.org/Tuesday"]');
$wednesday = $openingHours->xpath('./OpeningHoursSpecification[dayOfWeek = "http://schema.org/Wednesday"]');
$thursday = $openingHours->xpath('./OpeningHoursSpecification[dayOfWeek = "http://schema.org/Thursday"]');
$friday = $openingHours->xpath('./OpeningHoursSpecification[dayOfWeek = "http://schema.org/Friday"]');
$saturday = $openingHours->xpath('./OpeningHoursSpecification[dayOfWeek = "http://schema.org/Saturday"]');
$sunday = $openingHours->xpath('./OpeningHoursSpecification[dayOfWeek = "http://schema.org/Sunday"]');
function openingHoursTableRow($timePeriods, $weekdayName){
?>
<?php
if($specialHours){
?>
	<h1><?php _e('Special Hours'); ?></h1>
<?php
}
?>
