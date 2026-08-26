<?php
/**
 * La pagina generica di Meetoo.
 *
 * Ispirata a `page.php` di your-theme, ma su contenuti schema.org: lì il corpo è
 * `mainContentOfPage`, qui è `mainEntity` — un evento, un luogo, un'organizzazione.
 * Quello che i due hanno in comune è l'ossatura, ed è quella che si tiene: titolo,
 * immagine, corpo, e il guscio dell'header e del footer intorno.
 *
 * Gli altri template (event, place, organizer, collection) partono da qui e
 * aggiungono ciò che il loro tipo ha di suo. Chi non ha un template proprio
 * finisce comunque su una pagina leggibile, invece che su un errore.
 */
global $ws_content, $ws_query, $rewrite_rule;

$e = !empty($ws_content->mainEntity) ? $ws_content->mainEntity : $ws_content;
$titolo = !empty($e->name) ? (string)$e->name : (string)($rewrite_rule->title ?? '');

include_template('template-parts/header');
?>
			<article<?php echo ws_html_attributes('main-content', array('class' => array('mt-pagina'))); ?>>
<?php if(!empty($e->image)){ ?>
				<figure class="mt-copertina">
					<img src="<?php echo htmlspecialchars((string)$e->image, ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="lazy" />
				</figure>
<?php } ?>
				<h1 class="mt-h1"><?php echo htmlspecialchars($titolo, ENT_QUOTES, 'UTF-8'); ?></h1>
<?php
/* Il testo, una volta sola.
 *
 * Qui ne comparivano due: il sommario preso dalla mappa — che è la frase per i
 * motori di ricerca — e subito sotto il corpo, che in quei contenuti conteneva la
 * stessa cosa. Adesso i due testi sono campi distinti e questo stampa quello
 * fatto per essere letto: `abstract` se c'è, la descrizione altrimenti. La frase
 * SEO resta dov'è il suo posto, nella testa del documento.
 *
 * È XHTML già validato dall'editor: si stampa com'è, non si riscappa — è testo
 * formattato, non l'input di un estraneo.
 */
$testo = meetoo_testo_visibile($e);
if($testo !== ''){
?>
				<div class="mt-corpo"><?php ws_echo($testo); ?></div>
<?php } ?>
<?php
if(!empty($e->url)){
?>
				<p class="mt-link"><a href="<?php echo htmlspecialchars((string)$e->url, ENT_QUOTES, 'UTF-8'); ?>" rel="noopener"><?php _e('Sito web'); ?></a></p>
<?php } ?>
			</article>
<?php
include_template('template-parts/footer');
