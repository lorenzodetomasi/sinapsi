<?php
// The Daily Menu Php template
// @package WS
// @subpackage Localbiz
// @since WS 1.0
global $ws_headings, $ws_query, $ws_content, $longDate;
$ws_contents_url = ws_contents_url();
$index_url = $ws_headings->ws_path[0];
$GLOBALS['ws_html_attributes']['html']['class'][] = 'page';
include_template('template-parts/header');
$menu = $ws_content->mainEntity;
$cover = $ws_content->xpath('id("cover")/*');
if($cover){
?>
<div id="cover" class="flex cover">
<?php
	foreach($cover as $index => $slide){
		if($index == 0){ $isSlideActive = ' active'; } else { $isSlideActive = ' display-none'; }
		if($slide->style){
			echo '<style type="text/css">'.$slide->style.'</style>';
		}
?>
	<div class="flex slide width-full background-darkcolor align-middle align-center<?php echo $isSlideActive; ?>" style="background-image: url(<?php echo $ws_contents_url.$slide->image[0]->destination[1]->relpath; ?>); background-size: cover; background-position: center center;">
		<div class="hgrid-w1of3 background-color1-transparent padding">
			<?php echo $slide->content->innerHTML(); ?>
		</div>
	</div>
<?php
	}
?>
</div>
<?php
}
?>
<div class="content-container padding-top-2x">
<?php
if($ws_content->name){
?>
<h1 itemprop="name"><?php echo $ws_content->name->innerHTML(); ?></h1>
<?php
}
if(!empty($menu->offers->availabilityStarts) or !empty($menu->offers->availabilityEnds)){
	$availabilityStarts = new DateTime($menu->offers->availabilityStarts);
	$availabilityEnds = new DateTime($menu->offers->availabilityEnds);
?>
<h2><?php
	if($availabilityStarts == $availabilityEnds){
	//	$IntlDateFormatter->setPattern(DEFAULT_HUMAN_DATE_FORMAT);
		printf(__('Valid from %s'), $longDate->format($availabilityStarts));
	} else {
		printf(__('Valid from %1$s to %2$s'), $longDate->format($availabilityStarts), $longDate->format($availabilityEnds));
	}
?></h2>
<?php
}
?>
<?php
if($ws_content->description){
?>
<div itemprop="description" class="margin-bottom">
	<?php echo $menu->description->innerHTML(); ?>
</div>
<?php
}
if(!empty($ws_content->mainEntity->form)){
?>
<aside class="border-top-d2 padding-top">
	<h2><?php _e('Order now'); ?></h2>
	<div class="flex">
		<div class="vgrid-width-full hgrid-w2of3 hgrid-padding-right margin-bottom">
			<form action="<?php echo ws_href('/grazie'); ?>" method="POST" name="order-home-form" class="print-no">
<?php
include_template($ws_content->mainEntity->form);
include_template('template-parts/user-checkboxes');
?>
				<p class="submit">
					<button type="submit" class="button">
						<span class="text"><?php _e('Submit order'); ?></span>
						<i class="material-icons right">send</i>
					</button>
				</p>
			</form>
		</div>
		<nav class="vgrid-width-full hgrid-w1of3">
			<?php include_template('template-parts/contact-cta'); ?>
		</nav>
	</div>
</aside>
<?php
}
?>
<section class="border-top-d2 border-bottom-d2 hgrid-padding-v">
	<h1><?php _e('Allergens'); ?></h1>
	<div class="flex hgrid-cols2">
<?php
$AllergensList = $ws_content->xpath('id("AllergensList")')[0];
?>
		<div>
<?php
echo $AllergensList->description->innerHTML();
?>
		</div>
		<div class="flex cols2">
<?php
foreach ($AllergensList->itemListElement as $itemListElement) {
?>
			<div class="flex" style="border-top: 1px solid black;">
				<div class="padding-h-d2" style="width: 2em;"><strong><?php echo $itemListElement['id']; ?></strong></div>
				<div class="flex1"><?php echo $itemListElement->item->name; ?></div>
			</div>
<?php
}
?>
		</div>
	</div>
</section>
<?php
if($menu->hasMenuSection){
	foreach ($menu->hasMenuSection as $hasMenuSection) {
?>
		<section itemprop="hasMenuSection" class="section-padding-top" id="<?php echo $hasMenuSection['id']; ?>">
			<hgroup>
<?php
		if(!empty($hasMenuSection->name)){
?>
				<h2 itemprop="name" class="border-top-d2 padding-top"><?php echo $hasMenuSection->name->innerHTML(); ?></h2>
<?php
		}
?>
<?php
		if(!empty($hasMenuSection->description)){
?>
				<p itemprop="description"><?php echo $hasMenuSection->description->innerHTML(); ?></p>
<?php
		}
?>
			</hgroup>
<?php
			hasMenuItem($hasMenuSection);
?>
		</section>
<?php
		}
	} else {
		hasMenuItem($menu);
	}
?>
</div>
<?php
include_template('template-parts/menues-nav');
include_template('template-parts/footer');

function hasMenuItem($menu){
	global $ws_content;
	$PriceSpecificationType = $ws_content->mainEntity->PriceSpecificationType;
	if($menu->hasMenuItem){
?>
	<ul>
<?php
		foreach ($menu->hasMenuItem as $menuItem) {
			$menuItemName = $menuItem->name->innerHTML();
			$menuItemImage = $menuItem->image;
			$menuItemDescription = $menuItem->description->innerHTML();
			$menuItemAllergens = $menuItem->allergens->innerHTML();
			if($menuItem->offers->price){
				if($PriceSpecificationType){
					$price = $menuItem->xpath('offers/price[@type="'.$PriceSpecificationType.'"]')[0];
				}
				if(!$PriceSpecificationType or !$price){
					$price = $menuItem->xpath('offers/price')[0];
				}
				$menuItemPrice = '<span itemprop="price">'.$price.'</span>';
				$menuItemCurrency = '<span itemprop="priceCurrency">'.$menuItem->offers->priceCurrency.'</span>';
				$offersHtml = sprintf(__('<p itemprop="offers">%1$s %2$s</p>'), $menuItemPrice, $menuItemCurrency);
			} else if($menuItem->offers->priceSpecification->priceComponent){
				$offersHtml = '<p itemprop="offers">';
				foreach($menuItem->offers->priceSpecification->priceComponent as $priceComponent){
					if($PriceSpecificationType){
						$price = $priceComponent->xpath('price[@type="'.$PriceSpecificationType.'"]')[0];
					}
					if(!$PriceSpecificationType or !$price){
						$price = $priceComponent->xpath('price')[0];
					}
					$menuItemPrice = '<span itemprop="priceSpecification"><span itemprop="price">'.$price.'</span></span>';
					$menuItemCurrency = '<span itemprop="priceCurrency">'.$priceComponent->priceCurrency.'</span>';
					$menuItemReferenceQuantity = sprintf(__('<strong itemprop="referenceQuantity">%2$s</strong>'), '<span itemprop="value">'.$priceComponent->referenceQuantity->value.'</span>', '<span itemprop="unitText">'.$priceComponent->referenceQuantity->unitText.'</span>');
					$offersHtml .= sprintf(__('<span itemprop="priceComponent">%1$s %2$s %3$s</span><br />'), $menuItemPrice, $menuItemCurrency, '<strong>'.$menuItemReferenceQuantity.'</strong>');
				}
				$offersHtml .= '</p>';
			} elseif($menuItem->offers->priceSpecification->price){
				if($PriceSpecificationType){
					$price = $menuItem->xpath('offers[0]/priceSpecification[0]/price[@type="'.$PriceSpecificationType.'"]')[0];
				}
				if(!$PriceSpecificationType or !$price){
					$price = $menuItem->xpath('offers[0]/priceSpecification[0]/price')[0];
				}
				$menuItemPrice = '<span itemprop="priceSpecification"><span itemprop="price">'.$price.'</span></span>';
				$menuItemCurrency = '<span itemprop="priceCurrency">'.$menuItem->offers->priceSpecification->priceCurrency.'</span>';
				if($menuItem->offers->priceSpecification->referenceQuantity->name){
					$menuItemReferenceQuantity = sprintf('<strong itemprop="referenceQuantity">%1$s</strong>', '<span itemprop="name">'.$menuItem->offers->priceSpecification->referenceQuantity->name.'</span>');
				} else {
					$menuItemReferenceQuantity = sprintf('<strong itemprop="referenceQuantity">per %1$s %2$s</strong>', '<span itemprop="value">'.$menuItem->offers->priceSpecification->referenceQuantity->value.'</span>', '<span itemprop="unitText">'.$menuItem->offers->priceSpecification->referenceQuantity->unitText.'</span>');
				}
				$offersHtml = sprintf(__('<p itemprop="offers">%1$s %2$s %3$s</p>'), $menuItemPrice, $menuItemCurrency, $menuItemReferenceQuantity);
			}
?>
				<li itemprop="hasMenuItem" class="flex align-baseline">
<?php
			if($menuItemImage){
?>
					<img itemprop="image" src="<?php echo $menuItemImage; ?>" />
<?php
			}
?>
					<div class="flex1">
						<h2 itemprop="name"><?php echo $menuItemName; ?></h2>
						<p itemprop="description">
							<?php echo $menuItemDescription.' '; if(!empty($menuItemAllergens)){ echo $menuItemAllergens; } ?>
						</p>
					</div>
					<?php echo $offersHtml; ?>
				</li>
<?php
		}
?>
	</ul>
<?php
	}
}
function priceComponent($priceComponent){
	if($menu->priceComponent){

	}
}
?>
