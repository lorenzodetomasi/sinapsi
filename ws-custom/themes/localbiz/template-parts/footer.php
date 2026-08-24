<?php
// The main footer Php template
// @package WS
// @subpackage Localbiz
// @since WS 1.0
global $ws_query, $ws_headings, $ws_content, $longDateTime;
?>
			</main>
			<footer<?php echo ws_html_attributes('footer'); ?>>
				<div<?php echo ws_html_attributes('footer-content'); ?>>
<?php
include_template('template-parts/nav-contents');
include_template('template-parts/fiscal-data');
include_template('template-parts/nav-legal');
include_template('template-parts/credits');
?>
				</div>
			</footer>
		</div><!-- /#page-->
		<aside class="meta">
<?php
if($ws_content->datePublished){
	$dateModified = DateTime::createFromFormat(DATE_ATOM, $ws_content->dateModified);
	$datePublished = DateTime::createFromFormat(DATE_ATOM, $ws_content->datePublished);
?>
			<p class="content-container small meta padding-v">
				<span itemprop="datePublished"><?php printf(__('Page published on %s'), $longDateTime->format($datePublished)); ?></span> <span itemprop="dateModified"><?php printf(__('and modified on %s'), $longDateTime->format($dateModified)); ?></span>
			</p>
<?php
}
?>
		</aside>
<?php
include_template('template-parts/follow-modal');
include_template('template-parts/share-modal');
include_template('template-parts/search-modal');
include_template('template-parts/languages-modal');
?>
<?php echo ws_scripts('bodyend'); ?>
	</body>
</html>
