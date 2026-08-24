<?php
// The Homepage template
// @package WS
// @subpackage Localbiz
// @since WS 1.0
global $ws_content, $ws_headings;
$GLOBALS['ws_html_attributes']['html']['class'][] = 'home';
include_template('template-parts/header');
?>
<div<?php echo ws_html_attributes('main-content'); ?>>
<?php
if($ws_content->primaryImageOfPage){
	echo get_media($ws_content->primaryImageOfPage->figure->image, array('imgAttributes' => array('itemprop' => "primaryImageOfPage")));
}
?>
<?php
if($ws_content->name){
?>
	<h1 itemprop="name"><?php echo $ws_content->name->innerHTML(); ?></h1>
<?php
}
?>
<?php
if($ws_content->description){
?>
	<div itemprop="description">
		<p><?php echo $ws_content->description; ?></p>
	</div>
<?php
}
//include_template('locations/_clients');
//include_template('locations/_awards');
//include_template('locations/_locations');
?>
	<div class="content">
<?php if($ws_headings->wip == "true"){ ?><p><?php _e("Website under construction."); ?></p><?php } ?>
<?php
if($ws_content->mainContentOfPage){
	echo $ws_content->mainContentOfPage->innerHTML();
}
?>
	</div>
	<section id="competenze">
		<h1>Le nostre competenze</h1>
		<ul>
			<li>Design della comunicazione. Direzione creativa e del progetto. Strategie di comunicazione</li>
			<li>Brand Design. Immagine coordinata. Naming e progetto grafico del logotipo</li>
			<li>Progettazione e realizzazione di libri cartacei e digitali (ebook in formato epub e Amazon Kindle), progetto grafico e cartografico del libro, copertine e impaginazione, servizi per l’editoria, self-publishing, pubblicazione su Amazon Kindle Direct Publishing (KDP)</li>
			<li>Architettura dell’informazione, Information design, infografiche, tabelle e grafici, ottimizzati per stampa, ebbok o web, a partire da dati o fogli di calcolo</li>
			<li>Progettazione di interfacce multimodali innovative, progetto grafico dell’interfaccia di siti web adaptive/responsive e app</li>
			<li>Illustrazioni originali 2D e 3D</li>
			<li>Progetti specifici per l’infanzia</li>
			<li>Docenze</li>
		</ul>
	</section>
	<section id="clienti">
		<h1>I nostri principali clienti</h1>
<?php
global $itemListElements;
// $itemListElements = $ws_content->xpath("section[@id='clients']/itemList/itemListElement[not(@class='logo-design')]");
$itemListElements = $ws_content->xpath("section[@id='clients']/itemList/itemListElement[contains(concat(' ', normalize-space(@class), ' '), ' main ')]");
include_template('template-parts/grid-1_1', $args = array('require_once' => false));
?>
	</section>
	<section id="premi">
		<h1>I premi che abbiamo ricevuto</h1>
<?php
$itemListElements = $ws_content->xpath("section[@id='awards']/itemList/itemListElement[contains(concat(' ', normalize-space(@class), ' '), ' main ')]");
include_template('template-parts/grid-1_1', $args = array('require_once' => false));
?>
		</ul>
	</section>
</div>
<?php
include_template('template-parts/footer');
?>
