<?php
// The Business Order to take away template file
// @package WS
// @subpackage Localbiz
// @since WS 1.0
global $ws_headings, $ws_query, $ws_content;
$GLOBALS['ws_html_attributes']['html']['class'][] = 'form';
if(!empty($_GET["to"])){
	$GLOBALS['to_business'] = ws_content('localbiz/localbizs/'.$_GET["to"]);
	global $to_business;
	if(!empty($to_business->name->innerHTML())){
		$name = sprintf(__('%1$s from %2$s'), '<strong>'.__('Order to take away').'</strong>', '<strong>'.$to_business->name->innerHTML().'</strong>');
	}
}
if(empty($name) and $ws_content->name->innerHTML()){
	$name = $ws_content->name->innerHTML();
}
include_template('template-parts/header');
?>
<div class="content-container">
<?php
if(!empty($name)){
?>
	<h1 itemprop="name"><?php echo $name; ?></h1>
<?php
}
?>
<?php
if($to_business->home_delivery->description){
?>
	<div itemprop="description" class="padding background-color1-lighter margin-bottom">
		<?php echo $to_business->home_delivery->description->innerHTML(); ?>
	</div>
<?php
}
?>
<?php
if($to_business->hasMenu->description){
?>
	<details class="background-color2-lighter background-darkcolor margin-bottom">
		<summary class="padding margin-bottom"><?php echo $to_business->hasMenu->name; ?></summary>
		<div class="flex hgrid-cols3 padding-h">
			<?php echo $to_business->hasMenu->description->innerHTML(); ?>
		</div>
	</details>
<?php
}
?>
	<form action="<?php echo ws_href('impresa-contattata').'?to='.urlencode($to_business['id']).'&amp;parent='.urlencode($ws_query['wspath']); ?>" method="POST" name="contact-form">
<?php
include_template('template-parts/contact-form-header');
include_template('template-parts/order-takeaway-form');
include_template('template-parts/business-checkboxes');
include_template('template-parts/user-checkboxes');
?>
		<p class="submit">
			<button type="submit" class="button">
				<span class="text"><?php _e('Submit message'); ?></span>
				<i class="material-icons right">send</i>
			</button>
		</p>
	</form>
<?php
if(!empty($ws_content->ItemList)){
	foreach ($ws_content->ItemList->itemListElement as $itemListElement) {
?>
	<article class="padding-v" id="<?php echo $itemListElement['id']; ?>">
		<h2><?php echo $itemListElement->name; ?></h2>
		<p>
<?php
		foreach ($itemListElement->hasOfferCatalog as $key => $offerCatalog) {
			echo '<span class="offerCatalogName">'.$offerCatalog->name.'</span> ';
		}
?>
		</p>
		<p><span class="material-icons">place</span> <?php echo PostalAddress($itemListElement->address, array('output' => 'microdata','format' => 'singleline')); ?></p>
<?php
		if(!empty($itemListElement->email)){
			if(!empty($itemListElement->home_delivery)){
?>
		<a href="<?php echo '?'.$itemListElement['id']; ?>" class="button"><span class="material-icons">local_shipping</span> <span class="text"><?php _e('Order at home'); ?></span></a>
<?php
			}
			if(!empty($itemListElement->pickup)){
?>
		<a href="<?php echo '?'.$itemListElement['id']; ?>" class="button"><span class="material-icons">storefront</span> <span class="text"><?php _e('Order to take away'); ?></span></a>
<?php
			}
?>
		<a href="<?php echo '?'.$itemListElement['id']; ?>" class="button"><span class="material-icons">email</span> <span class="text"><?php _e('Request information'); ?></span></a>
<?php
		}
?>
	</article>
<?php
	}
}
?>
	<div class="content">
<?php
if($ws_content->mainContentOfPage){
	echo $ws_content->mainContentOfPage->innerHTML();
}
?>
	</div>
</div>
<?php
include_template('template-parts/footer');
?>
