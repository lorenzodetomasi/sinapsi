<?php
/**
 * A PORTFOLIO: a section of the page whose items are works with an image
 * (29 Sep 2026). The same mechanism as the logos (grid-1_1-image_gallery.php):
 * the page declares a section, the section names its list and chooses the
 * items with an XPath, and its CLASSES say how it looks - the shared cards
 * (css/cards.css):
 *
 *   <section class="portfolio cards-gallery cols-4 ratio-2x3" xml:id="libri">
 *     <name>Libri</name>
 *     <xpath>itemList/itemListElement[contains(@class, 'libro')]</xpath>
 *     <xi:include href="portfolio.wsx"/>
 *   </section>
 *
 *   cards-gallery  the image and, below it, the title and two lines of facts
 *                  (publisher, year) - a portfolio of books;
 *   cards-mosaic   images edge to edge; on hover the second image, if the work
 *                  has one, and the title on a veil - a portfolio of projects;
 *   cols-N         the most a line holds (fewer as it narrows);
 *   ratio-WxH      the shape of the images: 1x1, 4x3, 3x4, 2x3, 3x2, 16x9…
 *
 * An item of the list:
 *
 *   <itemListElement type="ListItem" xml:id="…" class="libro">
 *     <item type="Book">
 *       <name>…</name>
 *       <url>…</url>                         where it leads, if anywhere
 *       <publisher>…</publisher>             a line of facts
 *       <datePublished>2022</datePublished>  another (the year)
 *       <description>…</description>         a third, if needed
 *       <figure type="cover">
 *         <image><source><relpath>…</relpath><alt>…</alt></source></image>
 *         <image><source><relpath>…</relpath></source></image>   on hover
 *       </figure>
 *     </item>
 *   </itemListElement>
 *
 * @package WS
 * @subpackage Your Theme
 */
global $section;
include_template('template-parts/cards');

$section_id = trim((string)($section['id'] ?? ''));
if($section_id === ''){
	$xml_attributes = $section->attributes('http://www.w3.org/XML/1998/namespace');
	$section_id = trim((string)($xml_attributes['id'] ?? ''));
}
// The look: the section's classes, less the one that chose this template.
$classes = preg_split('/\s+/', trim((string)($section['class'] ?? '')));
$classes = array_values(array_diff($classes, array('portfolio', '')));
if(!array_intersect($classes, array('cards-gallery', 'cards-mosaic'))){
	$classes[] = 'cards-gallery';
}
$media_url = function($relpath){
	$relpath = trim((string)$relpath);
	if($relpath === ''){
		return '';
	}
	return preg_match('#^https?://#', $relpath) ? $relpath : rtrim(ws_contents_url(), '/').'/'.ltrim($relpath, '/');
};
$items = !empty($section->xpath) ? $section->xpath((string)$section->xpath) : array();
?>
<section<?php if($section_id !== ''){ echo ' id="'.htmlspecialchars($section_id).'"'; } ?> class="portfolio">
<?php if(!empty($section->name)){ ?>
	<h2 class="h1"><?php echo $section->name->innerHTML(); ?></h2>
<?php } ?>
	<ul class="cards <?php echo htmlspecialchars(implode(' ', $classes)); ?>">
<?php
foreach($items as $element){
	$work = $element->item;
	if(empty($work)){
		continue;
	}
	$images = $work->xpath('figure/image');
	$first = !empty($images[0]) ? $media_url($images[0]->source->relpath ?? '') : '';
	if($first === ''){
		continue;   // a portfolio shows works through their image
	}
	$name = !empty($work->name) ? trim($work->name->innerHTML()) : '';
	$alt = !empty($images[0]->source->alt) ? (string)$images[0]->source->alt : strip_tags($name);
	$meta = array();
	foreach(array('publisher', 'datePublished', 'description') as $field){
		if(!empty($work->$field)){
			$value = trim(strip_tags((string)$work->$field));
			// A date says its year: that is what a portfolio lists.
			if($field === 'datePublished' and preg_match('/^(\d{4})/', $value, $y)){
				$value = $y[1];
			}
			if($value !== ''){
				$meta[] = '<span>'.htmlspecialchars($value).'</span>';
			}
		}
	}
	echo ws_card(array(
		'tag' => 'li',
		'href' => trim((string)($work->url ?? '')),
		'external' => preg_match('#^https?://#', trim((string)($work->url ?? ''))) and strpos(trim((string)$work->url), ws_root_url()) !== 0,
		'media' => array(
			'src' => $first,
			'alt' => $alt,
			'src2' => !empty($images[1]) ? $media_url($images[1]->source->relpath ?? '') : '',
		),
		'title' => $name,
		'meta' => $meta,
		'arrow' => false,
	))."\n";
}
?>
	</ul>
</section>
