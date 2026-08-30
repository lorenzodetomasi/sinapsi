<?php
/**
 * La pagina di un gruppo: chi è, e che cosa fa.
 *
 * «Che cosa fa» è il motivo per cui uno ci arriva, ed è la parte che mancava: gli
 * appuntamenti che si ripetono, i prossimi eventi, e — solo se lo si chiede —
 * quello che ha già fatto. Le tre sezioni sono le stesse della zona, con lo stesso
 * codice: cambia soltanto l'AMBITO, che qui è questo gruppo.
 *
 * L'archivio non si apre da solo. Un gruppo attivo da anni si porta dietro decine
 * di eventi passati: scaricarli a chiunque apra la pagina significherebbe farne la
 * parte più pesante, per la meno guardata.
 */
global $ws_content, $ws_query, $rewrite_rule;

include_template('template-parts/carte');
include_template('template-parts/elenchi');

$e = !empty($ws_content->mainEntity) ? $ws_content->mainEntity : $ws_content;
$titolo = !empty($e->name) ? (string)$e->name : (string)($rewrite_rule->title ?? '');

/* L'ambito PRIMA del frammento: il pezzo di elenco chiesto mentre si scorre passa
 * di qui, e senza ambito continuerebbe con gli eventi di tutti. */
$chiave = basename(trim((string)($ws_query['content'] ?? ''), '/'));
meetoo_ambito('organizer', $chiave);
meetoo_frammento();

$tutto = (string)($_GET['tutti'] ?? '');
$SEZIONI = meetoo_sezioni(true);

include_template('template-parts/header');
?>
			<article<?php echo ws_html_attributes('main-content', array('class' => array('mt-pagina', 'mt-entita-pagina'))); ?>>
<?php
/* La copertina: il percorso nel documento è relativo alla SUA cartella
 * (`media-sources/cover.jpg`), e questa pagina la serve da un altro indirizzo —
 * `meetoo_media()` fa i conti. Senza, era un 404 su ogni scheda. */
/* Per un gruppo l'immagine viene prima del logo: una foto racconta che cosa fa,
 * un marchio dice solo come si chiama — e il nome è già scritto sopra. */
$cover = meetoo_media(meetoo_rel_corrente(), (string)($e->image ?? '') ?: (string)($e->logo ?? ''));
if($cover !== ''){ ?>
				<figure class="mt-copertina<?php echo empty($e->image) ? ' mt-logo-entita' : ''; ?>">
					<img src="<?php echo mt_esc($cover); ?>" alt="" loading="lazy" decoding="async" />
				</figure>
<?php } ?>
<?php
/* Il badge: dice che dietro questo gruppo c'è qualcuno che risponde — l'abbiamo
 * verificato noi. Sta accanto al nome perché è del nome che parla, e si legge dal
 * JSON perché i campi `meetoo:` nell'albero XML stanno in un altro spazio dei nomi
 * e da qui non si raggiungono. */
$mt_doc = meetoo_contenuto(meetoo_rel_corrente());
$mt_verificato = is_array($mt_doc) && !empty($mt_doc['meetoo:verified']);
?>
				<h1 class="mt-h1"><?php echo mt_esc($titolo); ?><?php if($mt_verificato){ ?><span class="mt-verificato" title="<?php _e('Gruppo verificato'); ?>"><span class="material-symbols-outlined" aria-hidden="true">verified</span><span class="mt-solo-voce"><?php _e('Gruppo verificato'); ?></span></span><?php } ?></h1>
<?php $testo = meetoo_testo_visibile($e); if($testo !== ''){ ?>
				<div class="mt-corpo"><?php ws_echo($testo); ?></div>
<?php } ?>
<?php if(!empty($e->url)){ ?>
				<p class="mt-link"><a href="<?php echo mt_esc((string)$e->url); ?>" rel="noopener"><?php _e('Sito web'); ?></a></p>
<?php } ?>

<?php
meetoo_sezione('collezioni', $SEZIONI['collezioni'] ?? null, $tutto);
meetoo_sezione('eventi', $SEZIONI['eventi'] ?? null, $tutto);
meetoo_sezione('archivio', $SEZIONI['archivio'] ?? null, $tutto);
?>
			</article>
<?php
include_template('template-parts/footer');
