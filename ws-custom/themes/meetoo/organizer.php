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
<?php if(!empty($e->logo) or !empty($e->image)){ ?>
				<figure class="mt-copertina mt-logo-entita">
					<img src="<?php echo mt_esc((string)(!empty($e->logo) ? $e->logo : $e->image)); ?>" alt="" loading="lazy" decoding="async" />
				</figure>
<?php } ?>
				<h1 class="mt-h1"><?php echo mt_esc($titolo); ?></h1>
<?php $testo = meetoo_testo_visibile($e); if($testo !== ''){ ?>
				<div class="mt-corpo"><?php ws_echo($testo); ?></div>
<?php } ?>
<?php if(!empty($e->url)){ ?>
				<p class="mt-link"><a href="<?php echo mt_esc((string)$e->url); ?>" rel="noopener"><?php _e('Sito web'); ?></a></p>
<?php } ?>

<?php
meetoo_sezione('collezioni', $SEZIONI['collezioni'], $tutto);
meetoo_sezione('eventi', $SEZIONI['eventi'], $tutto);
meetoo_sezione('archivio', $SEZIONI['archivio'], $tutto);
?>
			</article>
<?php
include_template('template-parts/footer');
