<?php
/**
 * The offer catalogue of the page's main entity, drawn from the content.
 *
 * A Service says what it is made of through `hasOfferCatalog`: an
 * OfferCatalog whose items are either further catalogues (the levels - the
 * strategic, the verbal, the visual…) or Offers, each offering a Service.
 * The content declares that tree in JSON-LD; the converter turns it into
 * XML; this part walks the XML and draws it - headings for the catalogues,
 * a list for the services, a link where a service has a page of its own.
 *
 * It used to be prose in a CDATA block, and prose cannot be declared to
 * anyone: a search engine saw paragraphs, and one of them had lost its
 * table on the way. Data can be drawn AND declared, from the same source.
 *
 * Recursive: a level is a catalogue like the one it sits in, one heading
 * deeper. Nothing here is about brand design; any Service with a catalogue
 * gets the same treatment.
 *
 * @package WS
 * @subpackage Your Theme
 */
global $ws_content;

if(empty($ws_content->mainEntity) or empty($ws_content->mainEntity->hasOfferCatalog)){
	return;
}

if(!function_exists('ws_offer_price')){
/**
 * An Offer's price as the reader expects it - "500,00 €" - or '' when the
 * offer names none. The content writes the number schema.org wants
 * ("500.00", "EUR"); the page writes the number the reader wants.
 */
function ws_offer_price($offer){
	if(empty($offer->price) or !is_numeric((string)$offer->price)) return '';
	$currency = strtoupper(trim((string)$offer->priceCurrency));
	$symbols = array('EUR' => '€', 'USD' => '$', 'GBP' => '£', 'CHF' => 'CHF');
	$symbol = $symbols[$currency] ?? $currency;
	return number_format((float)$offer->price, 2, ',', '.') . ($symbol !== '' ? ' ' . $symbol : '');
}
}

if(!function_exists('ws_draw_offer_catalog')){
/**
 * @param SimpleXMLElement $catalog  an OfferCatalog element
 * @param int              $level    heading level for its name (2 for the top)
 */
function ws_draw_offer_catalog($catalog, $level){
	$h = 'h' . min(6, max(2, $level));
	$name = !empty($catalog->name) ? trim($catalog->name->innerHTML()) : '';
	$alt = !empty($catalog->alternateName) ? trim($catalog->alternateName->innerHTML()) : '';
	$description = !empty($catalog->description) ? trim($catalog->description->innerHTML()) : '';
?>
				<section class="offer-catalog offer-catalog-level-<?php echo $level; ?>">
<?php if($name !== ''){ ?>
					<<?php echo $h; ?>><?php echo $name; ?><?php if($alt !== ''){ ?> <small class="offer-catalog-alt"><?php echo $alt; ?></small><?php } ?></<?php echo $h; ?>>
<?php }
	if($description !== ''){
		// Prose or a fragment of HTML (a table, two paragraphs): both are HTML here.
		echo (strpos($description, '<') === false) ? '<p>' . $description . '</p>' : $description;
	}
	$offers = array();
	$catalogs = array();
	foreach($catalog->itemListElement as $item){
		$type = (string)$item->attributes('http://www.w3.org/2001/XMLSchema-instance')->type;
		if($type === 'OfferCatalog' or !empty($item->itemListElement)){
			$catalogs[] = $item;
		} else {
			$offers[] = $item;
		}
	}
	if($offers){
?>
					<ul class="offers">
<?php
		foreach($offers as $offer){
			$service = !empty($offer->itemOffered) ? $offer->itemOffered : $offer;
			$sname = !empty($service->name) ? trim($service->name->innerHTML()) : '';
			$salt = !empty($service->alternateName) ? trim($service->alternateName->innerHTML()) : '';
			$sdesc = !empty($service->description) ? trim($service->description->innerHTML()) : '';
			$url = !empty($service->url) ? trim((string)$service->url) : '';
			$price = ws_offer_price($offer);
?>
						<li class="offer">
							<strong class="offer-name"><?php if($url !== ''){ ?><a href="<?php echo ws_href($url); ?>"><?php echo $sname; ?></a><?php } else { echo $sname; } ?></strong><?php if($salt !== ''){ ?> <span class="offer-alt"><?php echo $salt; ?></span><?php } ?><?php if($sdesc !== ''){ ?>
							<span class="offer-description"><?php echo $sdesc; ?></span><?php } ?><?php if($price !== ''){ ?>
							<span class="offer-price"><?php echo $price; ?></span><?php } ?>
						</li>
<?php
		}
?>
					</ul>
<?php
	}
	foreach($catalogs as $child){
		ws_draw_offer_catalog($child, $level + 1);
	}
?>
				</section>
<?php
}
}

ws_draw_offer_catalog($ws_content->mainEntity->hasOfferCatalog, 2);
