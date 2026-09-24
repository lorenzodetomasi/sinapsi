<?php
// The header top menu Php template
// @package WS
// @subpackage Localbiz
// @since WS 1.0
global $ws_sitemap, $ws_query, $ws_headings, $ws_content, $ws_content_languages, $ws_locales;
$contactPageDOMElement = $ws_sitemap->xpath('url[type="ContactPage"]');
$contact_page_url = ws_href($contactPageDOMElement[0]->wspath);
$areaServed = $ws_headings->mainEntity->areaServed;
$email = $ws_headings->mainEntity->email[0];
$telephone = $ws_headings->mainEntity->telephone;
?>
<nav <?php echo ws_html_attributes('header-top', array('id' => 'header-top')); ?>><div class="content-container">
<?php
if($contact_page_url and $areaServed){
?>
	<ul class="location">
		<li>
			<a href="<?php echo $contact_page_url; ?>" title="<?php _e('Where we are and how to contact us'); ?>">
				<i class="material-symbols-outlined">place</i><span class="text"><?php echo $areaServed->html->innerHTML(); ?></span>
			</a>
		</li>
	</ul>
<?php
}
if($email or $telephone){
?>
	<ul class="contacts">
<?php
	if($email){
?>
		<li><?php echo email($email); ?></li>
<?php
	}
	if($telephone){
?>
		<li><?php echo telephone($telephone); ?></li>
<?php
	}
?>
	</ul>
<?php
}
if($ws_headings->share == 'true'){
?>
	<ul class="social print-no">
		<li>
			<a href="#share" title="<?php _e('Share on social media or email'); ?>" aria-expanded="false" aria-controls="share">
				<i class="material-symbols-outlined">share</i><span class="text vgrid-no"><?php _e('Share'); ?></span>
			</a>
		</li>
	</ul>
<?php
}
/* «SEGUICI» e «CERCA» non ci sono piu'.
 *
 * Erano due comandi che non aprivano niente: `modal-follow` e `modal-search`
 * non sono mai stati scritti, e il footer li includeva lo stesso. Non era solo
 * un file mancante - non c'e' niente da metterci. «Seguici» vuole i profili
 * social e la newsletter, e nessun contenuto di questo CMS li dichiara;
 * «Cerca» vuole una ricerca, e non esiste.
 *
 * Tolti e non nascosti: un sito che scrive `<follow>true</follow>` nelle sue
 * testate si aspetta un pulsante che funziona, non uno che c'e' e basta. Il
 * giorno che una marca dichiarera' i suoi profili, «Seguici» torna e nasce gia'
 * pieno; lo stesso per la ricerca.
 *
 * «Condividi» invece e' rimasto, perche' e' l'unico dei tre che si accontenta
 * di quello che il sito ha gia': l'indirizzo e il titolo della pagina. E il suo
 * comando puntava a `#share-container`, un id che non esiste da nessuna parte. */

/* NÉ LE PREFERENZE NÉ LA LINGUA stanno più qui.
 *
 * La lingua era un mappamondo con la sigla del paese, in fondo a questa riga;
 * le preferenze un ingranaggio, poco prima. Erano due comandi nella riga che si
 * CHIUDE appena si comincia a leggere — e quella riga è anche la prima cosa che
 * sparisce su uno schermo stretto. Adesso sono una cosa sola, dentro
 * «Preferenze», che si apre dalle azioni dell'header: come si vede la pagina e
 * in che lingua la si legge sono la stessa domanda, e si rispondono nello
 * stesso posto.
 *
 * L'ACCESSO nemmeno.
 *
 * Stava in fondo alla barra dei contatti, che è la riga che si chiude quando si
 * comincia a leggere: chi voleva entrare, mentre leggeva, non aveva più il
 * pulsante. Adesso sta nella riga del nome, subito dopo le voci del menu — dove
 * si guarda per andare da qualche parte, ed «entrare» è andare da qualche
 * parte. Lo include `template-parts/header.php`. */
?>
</div></nav>
