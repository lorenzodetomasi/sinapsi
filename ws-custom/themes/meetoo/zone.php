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

/** Il testo di un campo del contenuto, '' se quel campo non c'è. */
function meetoo_testo($nodo, $campo){
	return isset($nodo->$campo) ? trim((string)$nodo->$campo) : '';
}

/* Categorie e Percorsi: le dichiara il contenuto della zona.
 *
 * Sono la stessa cosa vista da due lati — un percorso è una collezione ordinata (il
 * lungomare, da sud a nord), una categoria una collezione senza ordine (i libri) —
 * quindi stanno in un elenco solo, che è come le si cerca.
 *
 * Una voce è ATTIVA quando il suo contenuto esiste, cioè quando la mappa le dà un
 * indirizzo. Le altre si vedono lo stesso, spente: dicono di che cosa questa zona
 * si occuperà, ed è un'informazione, non un buco. */
$percorsi = array();
foreach((!empty($e->hasPart) ? $e->hasPart : array()) as $voce){
	$id = meetoo_riferimento_nodo($voce);
	if($id === ''){
		continue;
	}
	$slug = basename($id);
	/* Una CATEGORIA è generale — «Musica» è musica a Ostia come altrove — e sta nel
	 * catalogo (`categories/…`), dichiarata una volta sola. Qui la zona dice
	 * soltanto quali categorie ha; nome, sommario e icona si chiedono al catalogo.
	 *
	 * La sua ISTANZA di zona, se esiste, è `places/<zona>/<slug>`: è lì che stanno i
	 * suoi membri, ed è quella la pagina. Se non c'è, la categoria è dichiarata ma
	 * non ancora aperta: si vede spenta.
	 *
	 * Un PERCORSO invece è di questa zona e basta — il lungomare di Ostia non è il
	 * lungomare di nessun altro — e sta dove sta. */
	$categoria = (strpos($id, 'categories/') === 0);
	$istanza = $categoria ? 'places/'.$zonaSlug.'/'.$slug : $id;
	$href = meetoo_indirizzo($istanza);
	$def = $categoria ? meetoo_contenuto($id) : null;
	$sua = meetoo_icona_di($istanza) ?: ($categoria ? meetoo_icona_di($id) : null);
	$titolo = meetoo_testo($voce, 'name') ?: (string)($def['name'] ?? '');
	$nota = meetoo_testo($voce, 'description') ?: (string)($def['description'] ?? '');
	$percorsi[] = array(
		'href' => $href,
		'icona' => $sua ? $sua['name'] : 'route',
		'classe' => $sua ? $sua['class'] : '',
		'titolo' => $titolo !== '' ? $titolo : ucfirst(str_replace('-', ' ', $slug)),
		'nota' => $nota,
	);
}

/* PRIMA QUELLO CHE SI PUÒ APRIRE. Le categorie dichiarate ma non ancora aperte
 * restano — dicono che cosa sta arrivando — ma in fondo: chi guarda cerca dove
 * andare, e trovarsi davanti tre riquadri spenti prima del primo che funziona fa
 * sembrare vuota una zona che vuota non è. L'ordine dichiarato si conserva dentro
 * i due gruppi: `usort` in PHP è stabile. */
usort($percorsi, function($a, $b){
	return (int)($a['href'] === '') <=> (int)($b['href'] === '');
});

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

<?php if(count($percorsi)){ ?>
				<section id="categorie" class="mt-sezione">
					<h2 class="sec-head"><?php echo mt_icona('explore'); ?><?php _e('Categorie e Percorsi'); ?></h2>
					<div class="grid">
<?php foreach($percorsi as $p){
	if($p['href'] !== ''){
		echo mt_card_tile(array(
			'href' => $p['href'],
			'icon' => $p['icona'],
			'iconClass' => $p['classe'],
			'accent' => true,
			'title' => $p['titolo'],
			'meta' => $p['nota'],
		));
		continue;
	}
	/* In preparazione: NON è un collegamento, e si vede che non lo è. Un riquadro
	 * che sembra cliccabile e non fa niente è peggio di uno spento. */
?>
						<div class="card mt-in-arrivo">
							<div class="card-icon"><?php echo mt_icona($p['icona'], $p['classe']); ?></div>
							<div class="card-body">
								<h3 class="card-title"><?php echo mt_esc($p['titolo']); ?> <span class="mt-etichetta"><?php _e('In preparazione'); ?></span></h3>
								<div class="card-meta"><span><?php echo mt_esc($p['nota']); ?></span></div>
							</div>
						</div>
<?php } ?>
					</div>
				</section>
<?php } ?>
			</article>
<?php
include_template('template-parts/footer');
