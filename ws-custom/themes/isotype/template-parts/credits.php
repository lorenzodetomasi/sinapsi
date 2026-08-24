<?php
// The ContactPage top menu Php template
// @package WS
// @subpackage Localbiz
// @since WS 1.0
global $ws_query, $ws_headings, $ws_content;
$legalName = $ws_headings->mainEntity->legalName;
$copyrightYear = $ws_headings->mainEntity->copyrightYear;
$currentYear = date(__('Y'));
if((int)$currentYear > (int)$copyrightYear){
	$copyrightInfo = sprintf(__('<abbr title="Copyright">©</abbr> %1$s - %2$s %3$s'), $copyrightYear, $currentYear, $legalName);
} else {
	$copyrightInfo = sprintf(__('<abbr title="Copyright">©</abbr> %1$s %2$s'), $copyrightYear, $legalName);
}
if($ws_headings->mainEntity->designer){
	$designer = $ws_headings->mainEntity->designer->innerHTML();
} else {
	$designer = '<a href="https://www.localbiz.it">Localbiz.it</a>';
}
?>
<section <?php echo ws_html_attributes('credits', array('id'=>'credits', 'class' => array('credits'))); ?>>
	<h3><?php _e('Credits'); ?></h3>
	<p>
		<?php echo $copyrightInfo; ?> <br />
		<?php printf(__('Design by %s'), $designer); ?> <br />
	</p>
</section>
