<?php
/*
 * The site's locations.
 *
 * A site keeps its seats in `locations/index.json`, and that file holds no
 * addresses: each entry is an `@id` into `places/`, because a seat IS a place
 * and a place is written once. This follows the references and prints them.
 *
 * It used to print a heading and nothing else, because `$locations` was never
 * set — the line in functions.php that loaded it sits inside a commented-out
 * block, so `isset($locations)` was false on every page of every site and this
 * template had been silently doing nothing for as long as it existed.
 *
 * With one location the block is the address and the contacts, plainly: a site
 * with one office does not need a list of one. The heading appears when there
 * are two or more, or when the organisation says what area it serves.
 *
 * @package WS
 * @subpackage Localbiz
 * @since WS 1.0
 */
global $ws_headings, $ws_content_root;

$ws_locations = ws_content($ws_content_root.'/'.ws_locale().'/locations');
$ws_location_items = array();

if($ws_locations and !empty($ws_locations->itemListElement)){
	foreach($ws_locations->itemListElement as $ws_location_entry){
		/* A ListItem wraps the reference; a bare reference is accepted too, so
		 * a hand-written file does not have to know about ListItem. */
		$ws_location_node = !empty($ws_location_entry->item) ? $ws_location_entry->item : $ws_location_entry;
		$ws_location_place = ws_resolved($ws_location_node);
		if($ws_location_place and !empty($ws_location_place->address)){
			$ws_location_items[] = $ws_location_place;
		}
	}
}

if(!$ws_location_items){
	return;
}

$ws_area_served = !empty($ws_headings->mainEntity->areaServed->html)
	? $ws_headings->mainEntity->areaServed->html->innerHTML() : '';
?>
<section<?php echo ws_html_attributes('locations'); ?>>
<?php if($ws_area_served !== ''): ?>
	<h3><?php echo $ws_area_served; ?></h3>
<?php elseif(count($ws_location_items) > 1): ?>
	<h3><?php _e('Our locations'); ?></h3>
<?php endif; ?>
<?php foreach($ws_location_items as $ws_location_place): ?>
	<div class="location" itemscope itemtype="https://schema.org/Place">
<?php if(count($ws_location_items) > 1 and !empty($ws_location_place->name)): ?>
		<h4 itemprop="name"><?php echo $ws_location_place->name->innerHTML(); ?></h4>
<?php endif; ?>
		<p class="location-address"><?php echo PostalAddress($ws_location_place->address, array('output' => 'microdata', 'format' => 'singleline')); ?></p>
<?php if(!empty($ws_location_place->telephone)): ?>
		<p class="location-phone"><a href="tel:<?php echo preg_replace('/[^+0-9]/', '', (string)$ws_location_place->telephone); ?>" itemprop="telephone"><?php echo trim((string)$ws_location_place->telephone); ?></a></p>
<?php endif; ?>
<?php if(!empty($ws_location_place->email)): ?>
		<p class="location-email"><a href="mailto:<?php echo trim((string)$ws_location_place->email); ?>" itemprop="email"><?php echo trim((string)$ws_location_place->email); ?></a></p>
<?php endif; ?>
<?php
	/* The opening hours, as the place itself records them: one specification
	 * per stretch, so a day with a break shows both. */
	echo ws_opening_hours($ws_location_place->openingHoursSpecification);
?>
	</div>
<?php endforeach; ?>
</section>
