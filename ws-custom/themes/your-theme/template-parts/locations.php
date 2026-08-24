<?php
// Displays Locations
global $ws_headings, $ws_query;
if(isset($locations)) {
	$areaServed = $ws_headings->mainEntity->areaServed;
	if(!empty($areaServed->html->innerHTML())){
		$areaServedHtml = $areaServed->html->innerHTML();
	} else {
		$areaServedHtml = __('Our locations');
	}
?>
<section<?php echo ws_html_attributes('locations'); ?>>
	<h1><?php echo $areaServedHtml; ?></h1>
</section>
<?php
}
?>
