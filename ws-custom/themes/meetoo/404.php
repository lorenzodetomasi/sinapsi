<?php
/**
 * L'indirizzo non c'è.
 *
 * Serve a due categorie di visitatori, e a nessuna delle due basta la scritta
 * «errore»: chi ha seguito un collegamento vecchio, e chi ha accorciato l'indirizzo
 * a mano — `…/lido-di-ostia/eventi/qualcosa` → `…/lido-di-ostia/eventi/`. Al
 * secondo, la strada su di un livello alla volta è esattamente quello che serve, e
 * gliela dicono le briciole nell'header, che ci sono anche qui.
 *
 * La pagina esiste anche perché il CMS, senza, rispondeva con la home dell'ospite
 * — e con stato 200. Il codice 404 lo mette `ws-core/query.php`; questo è quello
 * che si legge.
 */
global $ws_query;

$home = ws_href('');
include_template('template-parts/header');
?>
			<article<?php echo ws_html_attributes('main-content', array('class' => array('mt-pagina'))); ?>>
				<h1 class="mt-h1"><?php _e('Questa pagina non c’è'); ?></h1>
				<div class="mt-corpo">
					<p><?php _e('L’indirizzo che hai aperto non corrisponde a niente: può essere cambiato, oppure non è mai esistito.'); ?></p>
				</div>

				<div class="cards" style="margin-top:1.5rem">
<?php
include_template('template-parts/carte');
echo mt_card_tile(array(
	'href' => $home,
	'icon' => 'home',
	'title' => __('Torna all’inizio'),
	'meta' => __('Scegli la zona e riparti da lì'),
));
?>
				</div>
			</article>
<?php
include_template('template-parts/footer');
