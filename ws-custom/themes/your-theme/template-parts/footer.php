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
if(!empty($ws_content->datePublished)){
	$dateModified = DateTime::createFromFormat(DATE_ATOM, $ws_content->dateModified);
	$datePublished = DateTime::createFromFormat(DATE_ATOM, $ws_content->datePublished);
?>
			<p class="content-container small meta padding-v">
				<span class="date-published"><?php printf(__('Page published on %s'), $longDateTime->format($datePublished)); ?></span><?php
if($dateModified and $dateModified != $datePublished){
?> <span class="date-modified"><?php printf(__('and modified on %s'), $longDateTime->format($dateModified)); ?></span><?php
}
?>
			</p>
<?php
}
?>
		</aside>
<?php
if(!empty($ws_content->inLanguage) and ws_lang(ws_locale()) != $ws_content->inLanguage){
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
/* Le finestre della pagina.
 *
 * `modal-follow` e `modal-search` erano inclusi e NON ESISTONO: due file mai
 * scritti, chiesti a ogni pagina. `modal-languages` esisteva e adesso e' spento
 * (`-modal-languages.php`): la scelta della lingua sta nelle Preferenze, insieme
 * al chiaro/scuro, e tenerne due copie vuol dire che un giorno diranno cose
 * diverse. Il comando che lo apriva non c'e' piu' da quando il mappamondo e'
 * uscito dall'header, quindi non lo apriva piu' nessuno. */
include_template('template-parts/modal-share');
include_template('template-parts/modal-preferences');
?>
<?php echo ws_scripts('bodyend'); ?>
	</body>
</html>
