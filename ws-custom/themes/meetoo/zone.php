<?php
/**
 * La pagina di una zona: che cosa succede qui, adesso.
 *
 * È la porta d'ingresso per chi il quartiere lo abita, e sta in cima al suo albero
 * di indirizzi: `/roma/municipio10/lido-di-ostia`. Da qui si va in tre direzioni —
 * eventi, gruppi, luoghi — e ognuna è una PAGINA, non un'ancora più in basso:
 * sono tre domande diverse, e chi ne ha una vuole un indirizzo da condividere.
 *
 * La pagina quindi dice quattro cose e basta: dove sei, dove puoi andare, che cosa
 * succede nei prossimi giorni, e per che cosa questa zona è fatta — le categorie e
 * i percorsi, che sono il modo in cui un quartiere si racconta.
 */
global $ws_content, $ws_query, $rewrite_rule;

include_template('template-parts/carte');
include_template('template-parts/elenchi');

/* DI CHI SONO le cose che si stanno guardando: di questa zona. Va dichiarato prima
 * del frammento, se no il pezzo di elenco chiesto mentre si scorre continuerebbe
 * con le cose di tutti — e su una pagina di zona «tutti» vuol dire un altro
 * quartiere. La chiave è l'indirizzo della zona, che la mappa conosce: è lo stesso
 * con cui costruisce l'indirizzo di ogni cosa che ci sta dentro. */
meetoo_ambito('zone', ws_href(trim((string)$ws_query['wspath'], '/')));

// Il pezzo di elenco chiesto dal browser (caricamento pigro): si risponde e basta.
meetoo_frammento();

$e = !empty($ws_content->mainEntity) ? $ws_content->mainEntity : $ws_content;
$nome = (string)($e->name ?? '');
$qui = trim((string)$ws_query['wspath'], '/');
// Lo slug di questa zona: l'ultimo pezzo del suo indirizzo, che è anche il nome
// della sua cartella — è lì che stanno le istanze delle sue categorie.
$zonaSlug = basename($qui);
$tutto = (string)($_GET['tutti'] ?? '');
$SEZIONI = meetoo_sezioni();

/** L'indirizzo di uno dei tre elenchi di questa zona. */
function meetoo_elenco_url($qui, $quale){
	return ws_href($qui.'/'.$quale);
}

// Categorie e Percorsi: la regola sta in `elenchi.php`, perché ora la usano in
// due — la zona e la città.
$percorsi = meetoo_percorsi($e, $zonaSlug);

include_template('template-parts/header');
?>
			<div class="mt-hero">
				<div class="mt-pagina">
					<h1 class="mt-h1"><?php echo mt_esc($nome); ?></h1>
<?php $testo = meetoo_testo_visibile($e); if($testo !== ''){ ?>
					<div class="mt-corpo"><?php ws_echo($testo); ?></div>
<?php } ?>
				</div>
			</div>

			<article<?php echo ws_html_attributes('main-content', array('class' => array('mt-pagina', 'mt-zona-pagina'))); ?>>
				<section class="mt-esplora" aria-label="<?php echo mt_esc(__('Esplora')); ?>">
					<div class="grid">
<?php
foreach(array('eventi' => 'event', 'gruppi' => 'groups', 'luoghi' => 'place') as $quale => $icona){
	echo mt_card_tile(array(
		'href' => meetoo_elenco_url($qui, $quale),
		'icon' => $icona,
		'title' => $quale === 'eventi' ? __('Eventi') : $SEZIONI[$quale]['titolo'],
		'meta' => $SEZIONI[$quale]['sommario'],
	));
}
?>
					</div>
				</section>

<?php
/* Il titolo della sezione è un TITOLO, come «Categorie e Percorsi»: dice che cosa
 * viene dopo, non porta da un'altra parte. Alla pagina degli eventi si va dalla
 * card qui sopra, e da «Vedi tutti» quando ce ne sono più di quanti ne stiano. */
meetoo_sezione('eventi', $SEZIONI['eventi'], $tutto, '', meetoo_elenco_url($qui, 'eventi'));
?>

<?php meetoo_sezione_percorsi($percorsi); ?>
			</article>
<?php
include_template('template-parts/footer');
