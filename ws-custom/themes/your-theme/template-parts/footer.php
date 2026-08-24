<?php
// The main footer Php template
// @package WS
// @subpackage Localbiz
// @since WS 1.0
global $ws_query, $ws_headings, $ws_content, $longDateTime;
?>
				</main>
				<footer<?php echo ws_html_attributes('footer'); ?>>
					<div<?php echo ws_html_attributes('footer-content', array('id'=>'footer-content')); ?>>
<?php
include_template('template-parts/nav-contents');
include_template('template-parts/fiscal-data');
include_template('template-parts/nav-legal');
include_template('template-parts/credits');
?>
					</div>
<?php echo ws_scripts('footerend'); ?>
				</footer>
			</div><!-- /#main-container-->
		</div><!-- /#page-->
		<aside class="meta">
<?php
if($ws_content->datePublished){
	$dateModified = DateTime::createFromFormat(DATE_ATOM, $ws_content->dateModified);
	$datePublished = DateTime::createFromFormat(DATE_ATOM, $ws_content->datePublished);
?>
			<p class="content-container small meta padding-v">
				<span itemprop="datePublished"><?php printf(__('Page published on %s'), $longDateTime->format($datePublished)); ?></span><?php
if($dateModified and $dateModified != $datePublished){
?> <span itemprop="dateModified"><?php printf(__('and modified on %s'), $longDateTime->format($dateModified)); ?></span><?php
}
?>
			</p>
<?php
}
?>
		</aside>
<?php
if(ws_lang(ws_locale()) != $ws_content->inLanguage){
?>
				<p class="content-container alert">
<?php
	if(!empty($ws_content->inLanguage)){
		printf(__('Content in %1$s. '), $ws_content->inLanguage);
	} else {
		printf(__('Content language not specified. '), $ws_content->inLanguage);
	}
	printf(__('Page not available in %1$s. '), ws_locale());
?>
				</p>
<?php
}
?>
<?php
include_template('template-parts/modal-follow');
include_template('template-parts/modal-share');
include_template('template-parts/modal-search');
include_template('template-parts/modal-languages');
?>
<?php echo ws_scripts('bodyend'); ?>
	</body>
</html>
