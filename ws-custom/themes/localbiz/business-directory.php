<?php
// The Business Directory template file
// @package WS
// @subpackage Localbiz
// @since WS 1.0
global $ws_query, $ws_content;
$GLOBALS['ws_html_attributes']['html']['class'][] = 'archive';
include_template('template-parts/header');
?>
<div class="content-container">
	<header>
<?php
if($ws_content->name){
?>
		<h1 itemprop="name"><?php echo $ws_content->name->innerHTML(); ?></h1>
<?php
}
?>
	</header>
	<div class="content">
<?php
if($ws_content->mainContentOfPage){
	echo $ws_content->mainContentOfPage->innerHTML();
}
?>
<p><a class="link uppercase" href="/suggerisci-impresa?parent=<?php echo urlencode($ws_query['wspath']); ?>"><span class="material-icons">add_location</span><span class="text"><?php _e('Add a local business'); ?></span></a></p>
	</div>
</div>
<?php
if(!empty($ws_content->ItemList)){
	foreach ($ws_content->ItemList->itemListElement as $itemListElement) {
?>
	<article itemprop="itemListElement" class="padding-v content-container" id="<?php echo $itemListElement['id']; ?>">
		<h2><?php echo $itemListElement->name; ?></h2>
<?php
	if(!empty($itemListElement->headline)){
?>
		<h3><?php echo $itemListElement->headline; ?></h3>
<?php
}
?>
<?php
	if(!empty($itemListElement->description)){
?>
		<p><?php echo $itemListElement->description; ?></p>
<?php
}
?>
		<p>
<?php
if(!empty($itemListElement->is_direct_producer)){
	echo '<span class="offerCatalogName producer"><span class="material-icons">business</span> '.__('Direct producer').' ';
}
if(!empty($itemListElement->has_certified_organic_products)){
	echo '<span class="offerCatalogName bio"><span class="material-icons">eco</span> '.__('Certified organic products').'</span> ';
}
?>
<?php
		foreach ($itemListElement->hasOfferCatalog as $key => $offerCatalog) {
			echo '<span class="tag offerCatalogName"><span class="material-icons">local_offer</span> <span class="text">'.$offerCatalog->name.'</span></span> ';
		}
		$postalAddress = PostalAddress($itemListElement->address, array('output' => 'microdata','format' => 'singleline'));
?>
		</p>
		<p>
			<a class="link" href="https://www.google.com/maps/search/?api=1&amp;query=<?php echo urlencode($itemListElement->name.', '.$postalAddress); ?>" target="_blank">
				<span class="material-icons">place</span>
				<span class="text"><?php echo $postalAddress ?></span>
			</a>
		</p>
		<p>
<?php
		if(!empty($itemListElement->email) and $itemListElement->localbiz_pro == 'true'){
			if(!empty($itemListElement->home_delivery)){
?>
		<a href="<?php echo '/ordina-domicilio?to='.urlencode($itemListElement['id']).'&amp;parent='.urlencode($ws_query['wspath']); ?>" class="button"><span class="material-icons">local_shipping</span> <span class="text"><?php _e('Order at home'); ?></span></a>
<?php
			}
			if(!empty($itemListElement->pickup)){
?>
		<a href="<?php echo '/ordina-takeaway?to='.urlencode($itemListElement['id']).'&amp;parent='.urlencode($ws_query['wspath']); ?>" class="button"><span class="material-icons">storefront</span> <span class="text"><?php _e('Order to take away'); ?></span></a>
<?php
			}
?>
		<a href="<?php echo '/contatta?to='.urlencode($itemListElement['id']).'&amp;parent='.urlencode($ws_query['wspath']); ?>" class="button"><span class="material-icons">email</span> <span class="text"><?php _e('Request information'); ?></span></a>
<?php
} else {
	if(!empty($itemListElement->home_delivery)){
?>
<span class="tag"><span class="material-icons">local_shipping</span> <span class="text"><?php _e('Home delivery'); ?></span></span>
<?php
	}
	if(!empty($itemListElement->pickup)){
?>
<span class="tag"><span class="material-icons">storefront</span> <span class="text"><?php _e('Pickup at the store'); ?></span></span>
<?php
	}
}
?>
<?php
if($itemListElement->localbiz_pro == 'free' or $itemListElement->localbiz_pro == 'true'){
	if(!empty($itemListElement->telephone)){
		echo telephone($itemListElement->telephone);
	}
	if(!empty($itemListElement->url)){
		echo url($itemListElement->url);
	}
}
?>
		</p>
	</article>
<?php
	}
}
?>
<?php
include_template('template-parts/footer');
?>
