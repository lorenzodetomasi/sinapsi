<?php
global $section;
/* The section keeps the id the content gave it (clienti, premi…), so that a
 * link to /#premi lands on the awards instead of nowhere. Without an id in
 * the content there is no attribute at all - not an empty one. */
/* Either spelling: `id` from the hand-written XML, `xml:id` from the XML the
 * JSON-LD converter derives (the CMS's own convention for anchors). */
$section_id = trim((string)($section['id'] ?? ''));
if($section_id === ''){
	$xml_attributes = $section->attributes('http://www.w3.org/XML/1998/namespace');
	$section_id = trim((string)($xml_attributes['id'] ?? ''));
}
?>
<section<?php if($section_id !== ''){ echo ' id="' . htmlspecialchars($section_id) . '"'; } ?>>
	<h2 class="h1"><?php echo $section->name; ?></h2>
	<ul class="grid-container">
<?php
$itemListElements = $section->xpath($section->xpath);
foreach ($itemListElements as $itemListElement) {
	$url = $itemListElement->item->url[0];
	$image = $itemListElement->xpath("item/figure[@type='logo']/image");
	if(!empty($image)){
		if(!empty($url)){
?>
		<li class="grid-cell"><a href="<?php echo $itemListElement->item->url[0]; ?>" alt="<?php echo $itemListElement->item->name; ?>">
			<?php
				echo get_media($image);
			?>
		</a></li>
<?php
		} else {
?>
		<li class="grid-cell">
			<?php
				echo get_media($image);
			?>
		</li>
<?php
		}

	}
}
?>
	</ul>
</section>