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
<?php
/* THE LOGOS as the shared cards (css/cards.css, .cards-logos): square cells on
 * white, two across and four from 960px, the grey of isotype on hover. A logo
 * with a link is a link over the whole cell, named by the item - there is no
 * text beside the image to name it. */
?>
	<ul class="cards cards-logos">
<?php
$itemListElements = $section->xpath($section->xpath);
foreach ($itemListElements as $itemListElement) {
	$image = $itemListElement->xpath("item/figure[@type='logo']/image");
	if(empty($image)){
		continue;
	}
	$url = trim((string)($itemListElement->item->url[0] ?? ''));
	$name = trim((string)($itemListElement->item->name ?? ''));
	$media = get_media($image);
	if($url !== ''){
?>
		<li class="card"><a class="card-link card-media" href="<?php echo htmlspecialchars($url); ?>" title="<?php echo htmlspecialchars($name); ?>" aria-label="<?php echo htmlspecialchars($name); ?>"><?php echo $media; ?></a></li>
<?php
	} else {
?>
		<li class="card"><div class="card-media"><?php echo $media; ?></div></li>
<?php
	}
}
?>
	</ul>
</section>
