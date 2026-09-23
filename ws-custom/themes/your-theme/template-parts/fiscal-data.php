<?php
/*
 * The Footer Fiscal Data Php template
 *
 * The legal name, the registered address and the tax numbers of whoever runs
 * the site. They are facts about the ORGANISATION, so they are read from the
 * organisation — which the headings may write out in full or, as a site built
 * by the panel does, name by `@id`. `ws_resolved()` answers the same way for
 * both, so this template does not have to know which.
 *
 * Nothing is printed when there is nothing to print. A footer that says "Vat
 * ID:" followed by a blank is worse than a footer without the line: it looks
 * like a bug to a visitor and like a filled-in field to whoever should fill it
 * in.
 *
 * @package WS
 * @subpackage Localbiz
 * @since WS 1.0
 */
global $ws_headings;

$ws_fiscal = ws_resolved($ws_headings->mainEntity);

$ws_fiscal_name = trim((string)$ws_fiscal->legalName);
$ws_fiscal_vat  = trim((string)$ws_fiscal->vatID);
$ws_fiscal_tax  = trim((string)$ws_fiscal->taxID);
$ws_fiscal_addr = !empty($ws_fiscal->address)
	? trim(PostalAddress($ws_fiscal->address, array('output' => 'microdata', 'format' => 'singleline')))
	: '';

if($ws_fiscal_name === '' and $ws_fiscal_vat === '' and $ws_fiscal_tax === '' and $ws_fiscal_addr === ''){
	return;
}
?>
<section <?php echo ws_html_attributes('fiscal-data', array('id'=>'fiscal-data', 'class' => array('fiscal-data'))); ?>>
	<h3><?php _e('Fiscal data'); ?></h3>
<?php if($ws_fiscal_name !== '' or $ws_fiscal_addr !== ''): ?>
	<p>
<?php if($ws_fiscal_name !== ''): ?>
		<span class="legal-name"><?php echo $ws_fiscal_name; ?></span><?php echo $ws_fiscal_addr !== '' ? '<br />' : ''; ?>
<?php endif; ?>
<?php if($ws_fiscal_addr !== ''): ?>
		<?php echo $ws_fiscal_addr; ?>
<?php endif; ?>
	</p>
<?php endif; ?>
<?php if($ws_fiscal_vat !== '' or $ws_fiscal_tax !== ''): ?>
	<p>
<?php if($ws_fiscal_vat !== ''): ?>
		<?php printf(__('Vat ID: %s'), '<span class="vat-id">'.$ws_fiscal_vat.'</span>'); ?><?php echo $ws_fiscal_tax !== '' ? '<br />' : ''; ?>
<?php endif; ?>
<?php if($ws_fiscal_tax !== ''): ?>
		<?php printf(__('Tax ID: %s'), '<span class="tax-id">'.$ws_fiscal_tax.'</span>'); ?>
<?php endif; ?>
	</p>
<?php endif; ?>
</section>
