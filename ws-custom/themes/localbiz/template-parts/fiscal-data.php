<?php
// The Footer Fiscal Data Php template
// @package WS
// @subpackage Localbiz
// @since WS 1.0
global $ws_query, $ws_headings, $ws_content;
$legalName = $ws_headings->mainEntity->legalName;
$address = $ws_headings->mainEntity->address;
$vatID = $ws_headings->mainEntity->vatID;
$taxID = $ws_headings->mainEntity->taxID;
?>
<section>
	<h3><?php _e('Fiscal data'); ?></h3>
	<p>
		<span itemprop="legalName"><?php echo $legalName; ?></span> <br />
		<?php echo PostalAddress($address, array('output' => 'microdata',
    'format' => 'singleline')); ?>
	</p>
	<p>
		<?php printf(__('Vat ID: %s'), '<span itemprop="vatID">'.$vatID.'</span>'); ?> <br />
		<?php printf(__('Tax ID: %s'), '<span itemprop="taxID">'.$taxID.'</span>'); ?>
	</p>
</section>
