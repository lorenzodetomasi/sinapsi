<?php
global $section;
?>
<section>
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
				echo get_media($itemListElement->xpath("item/figure[@type='logo']/image"));
			?>
		</a></li>
<?php
		} else {
?>
		<li class="grid-cell">
			<?php
				echo get_media($itemListElement->xpath("item/figure[@type='logo']/image"));
			?>
		</li>
<?php
		}

	}
}
?>
	</ul>
</section>