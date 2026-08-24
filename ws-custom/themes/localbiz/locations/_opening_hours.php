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
<tr>
	<th><?php echo $weekdayName; ?></th>
	<td>
<?php
if(empty($timePeriods[0]->opens) and empty($timePeriods[0]->closes)){
echo __('Closed');
} else if(count($timePeriods) > 0){
	foreach ($timePeriods as $timePeriodIndex => $timePeriod) {
		printf(__('%1$s - %2$s'), '<span class="open-time">'.$timePeriod->opens.'</span>', '<span class="close-time">'.$timePeriod->closes.'</span>');
		if(count($timePeriods) == 1){

		} else if($timePeriodIndex == 0){
			echo ', ';
		} else {
			echo ' ';
		}
	}
}
?>
	</td>
</tr>
<?php
}
?>
	<h2><?php _e('Opening Hours'); ?></h2>
	<table class="opening_hours">
<?php
if($openingHours->name){
?>
				<caption><?php echo $openingHours->name; ?></caption>
<?php
}
?>
		<thead>
			<tr>
				<th><?php _e('Weekday'); ?></th>
				<th><?php _e('Opening Hours'); ?></th>
			</tr>
		</thead>
		<tbody>
<?php
openingHoursTableRow($monday, __('Monday'));
openingHoursTableRow($tuesday, __('Tuesday'));
openingHoursTableRow($wednesday, __('Wednesday'));
openingHoursTableRow($thursday, __('Thursday'));
openingHoursTableRow($friday, __('Friday'));
openingHoursTableRow($saturday, __('Saturday'));
openingHoursTableRow($sunday, __('Sunday'));
?>
		</tbody>
	</table>
