<?php
// The Footer Fiscal Data Php template
// @package WS
// @subpackage Localbiz
// @since WS 1.0
global $ws_headings;
?>
<section <?php echo ws_html_attributes('fiscal-data', array('id'=>'fiscal-data', 'class' => array('fiscal-data'))); ?>>
	<h3><?php _e('Fiscal data'); ?></h3>
	<p>
		<span itemprop="legalName"><?php echo $ws_headings->mainEntity->legalName; ?></span> <br />
		<?php echo PostalAddress($ws_headings->mainEntity->address, array('output' => 'microdata',
    'format' => 'singleline')); ?>
	</p>
	<p>
		<?php printf(__('Vat ID: %s'), '<span itemprop="vatID">'.$ws_headings->mainEntity->vatID.'</span>'); ?> <br />
		<?php printf(__('Tax ID: %s'), '<span itemprop="taxID">'.$ws_headings->mainEntity->taxID.'</span>'); ?>
	</p>
</section>
