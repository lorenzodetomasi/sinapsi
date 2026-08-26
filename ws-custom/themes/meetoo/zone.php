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

// Il pezzo di elenco chiesto dal browser (caricamento pigro): si risponde e basta.
meetoo_frammento();

$e = !empty($ws_content->mainEntity) ? $ws_content->mainEntity : $ws_content;
$nome = (string)($e->name ?? '');
$qui = trim((string)$ws_query['wspath'], '/');
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

/**
 * L'@id a cui punta un nodo del contenuto.
 *
 * Nel passaggio da JSON a XML l'`@id` della radice diventa l'attributo `id`, quello
 * di un nodo interno un `xlink:href` — perché lì è un RIFERIMENTO ad altro, non il
 * nome di questo. Si guardano tutti e due: chi legge un contenuto non deve sapere
 * in che punto dell'albero si trova.
 */
function meetoo_riferimento($nodo){
	$href = $nodo->attributes('http://www.w3.org/1999/xlink');
	$id = ($href !== null and isset($href->href)) ? (string)$href->href : '';
	if($id === ''){
		$suoi = $nodo->attributes();
		$id = ($suoi !== null and isset($suoi->id)) ? (string)$suoi->id : '';
	}
	return trim($id);
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
	$titolo = meetoo_testo($voce, 'name');
	$id = meetoo_riferimento($voce);
	$href = $id !== '' ? meetoo_indirizzo($id) : '';
	$percorsi[] = array(
		'href' => $href,
		'icona' => meetoo_testo($voce, 'icon') ?: 'route',
		'titolo' => $titolo !== '' ? $titolo : ucfirst(str_replace('-', ' ', basename($id))),
		'nota' => meetoo_testo($voce, 'description'),
	);
}

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

<?php meetoo_sezione('eventi', $SEZIONI['eventi'], $tutto, meetoo_elenco_url($qui, 'eventi')); ?>

<?php if(count($percorsi)){ ?>
				<section id="categorie" class="mt-sezione">
					<h2 class="sec-head"><?php echo mt_icona('explore'); ?><?php _e('Categorie e Percorsi'); ?></h2>
					<div class="grid">
<?php foreach($percorsi as $p){
	if($p['href'] !== ''){
		echo mt_card_tile(array(
			'href' => $p['href'],
			'icon' => $p['icona'],
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
							<div class="card-icon"><?php echo mt_icona($p['icona']); ?></div>
							<div class="card-body">
								<h3 class="card-title"><?php echo mt_esc($p['titolo']); ?></h3>
								<div class="card-meta"><span><?php echo mt_esc($p['nota']); ?></span></div>
							</div>
							<div class="card-arrow"><span class="mt-etichetta"><?php _e('In preparazione'); ?></span></div>
						</div>
<?php } ?>
					</div>
				</section>
<?php } ?>
			</article>
<?php
include_template('template-parts/footer');
