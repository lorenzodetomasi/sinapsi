<?php
// The Daily Menu Php template
// @package WS
// @subpackage Localbiz
// @since WS 1.0
global $ws_query, $ws_content, $longDate;
$GLOBALS['ws_html_attributes']['html']['class'][] = 'page';
include_template('template-parts/header');
?>
<div class="content-container">
<?php
if($ws_content->name){
?>
<h1 itemprop="name"><?php echo $ws_content->name->innerHTML(); ?></h1>
<h2><?php
if($ws_content->offers->availabilityStarts == $ws_content->offers->availabilityEnds){
	$availabilityStarts = new DateTime($ws_content->offers->availabilityStarts);
//	$IntlDateFormatter->setPattern(DEFAULT_HUMAN_DATE_FORMAT);
	echo $longDate->format($availabilityStarts);
}
?></h2>
<?php
}
?>
<?php
if($ws_content->description){
?>
			<div itemprop="description">
				<?php echo $ws_content->description->innerHTML(); ?>
			</div>
<?php
}
?>
			<div class="content">
<?php
if($ws_content->mainContentOfPage){
	echo $ws_content->mainContentOfPage->innerHTML();
}
?>
			</div>
<?php
if($ws_content->hasMenuItem){
	foreach ($ws_content->hasMenuItem as $menuItem) {
		$menuItemName = $menuItem->name;
		$menuItemImage = $menuItem->image;
		$menuItemDescription = $menuItem->description;
		$menuItemPrice = '<span itemprop="price">'.$menuItem->offers->price.'</span>';
		$menuItemCurrency = '<span itemprop="priceCurrency">'.$menuItem->offers->priceCurrency.'</span>';
?>
				<li class="flex align-baseline">
<?php
		if($menuItemImage){
?>
					<img src="<?php echo $menuItemImage; ?>" />
<?php
		}
?>
					<div class="flex1">
						<h2 itemprop="name"><?php echo $menuItemName; ?></h2>
						<p itemprop="description"><?php echo $menuItemDescription; ?></p>
					</div>
					<div itemprop="offers">
						<p><?php printf(__('%1$s %2$s'), $menuItemPrice, $menuItemCurrency); ?></p>
					</div>
				</li>
<?php
	}
}
?>
</div>
<?php
include_template('template-parts/footer');
?>
