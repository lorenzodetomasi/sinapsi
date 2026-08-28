<?php
/**
 * La home di Meetoo: che cos'è, e da dove si comincia.
 *
 * Due cose sole, perché due sole ne servono a chi arriva la prima volta: capire di
 * che si tratta, e trovare il proprio quartiere. Tutto il resto — eventi, luoghi,
 * gruppi — sta dentro le zone, ed è lì che si va.
 *
 * L'albero delle zone è CONTENUTO, non codice: sta in `index/index.json`, e ci si
 * aggiungono città e municipi senza toccare una riga di PHP. Una zona è ATTIVA
 * quando ha un `url`: è il modo più economico di dire «questa si può visitare»,
 * senza inventare un campo apposta — e una zona senza indirizzo è, letteralmente,
 * una zona dove non si può ancora andare.
 */
global $ws_content, $ws_query, $rewrite_rule;

$e = !empty($ws_content->mainEntity) ? $ws_content->mainEntity : $ws_content;
$zone = !empty($e->hasPart) ? $e->hasPart : null;

/**
 * Disegna un ramo dell'albero delle zone. Ricorsiva perché l'albero è ricorsivo:
 * città, municipio, quartiere — e domani magari un livello in più.
 */
/**
 * Una città nell'elenco della home.
 *
 * La home non disegna più tutto l'albero: dice quali città ci sono, e l'albero lo
 * mostra ognuna a casa propria — `places/roma` sa quali municipi ha, il municipio
 * quali quartieri. Prima l'albero intero stava nell'indice del sito, e ogni
 * quartiere nuovo di qualunque città andava aggiunto lì.
 */
function meetoo_citta($rif){
	$id = meetoo_riferimento_nodo($rif);
	if($id === ''){
		return;
	}
	$doc = meetoo_contenuto($id);
	$href = meetoo_indirizzo($id);
	$nome = (string)($doc['name'] ?? basename($id));
	$nota = (string)($doc['description'] ?? '');
	$ico = meetoo_icona_di($id);
	$dentro = count((array)($doc['containsPlace'] ?? []));
	if($href !== ''){
		echo mt_card_tile(array(
			'href' => $href,
			'icon' => $ico ? $ico['name'] : 'location_city',
			'iconClass' => $ico ? $ico['class'] : '',
			'title' => $nome,
			'meta' => $nota,
		));
		return;
	}
?>
		<div class="card mt-in-arrivo">
			<div class="card-icon"><?php echo mt_icona($ico ? $ico['name'] : 'more_horiz', $ico ? $ico['class'] : ''); ?></div>
			<div class="card-body">
				<h3 class="card-title"><?php echo mt_esc($nome); ?> <span class="mt-etichetta"><?php _e('In preparazione'); ?></span></h3>
<?php if($nota !== ''){ ?>
				<div class="card-meta"><span><?php echo mt_esc($nota); ?></span></div>
<?php } ?>
			</div>
		</div>
<?php
}

include_template('template-parts/carte');
include_template('template-parts/header');
?>
			<article<?php echo ws_html_attributes('main-content', array('class' => array('mt-pagina', 'mt-home'))); ?>>
				<h1 class="mt-h1"><?php echo htmlspecialchars((string)$e->name, ENT_QUOTES, 'UTF-8'); ?></h1>
<?php $testo = meetoo_testo_visibile($e); if($testo !== ''){ ?>
				<div class="mt-corpo"><?php ws_echo($testo); ?></div>
<?php } ?>

<?php if($zone){ ?>
				<section class="mt-sezione" aria-labelledby="zone">
					<h2 id="zone" class="sec-head"><?php echo mt_icona('location_city'); ?><?php echo mt_esc((string)($zone->name ?: __('Zone'))); ?></h2>
<?php $nota = meetoo_testo_visibile($zone); if($nota !== ''){ ?>
					<div class="mt-corpo mt-nota"><?php ws_echo($nota); ?></div>
<?php } ?>
					<div class="grid">
<?php
foreach($zone->itemListElement as $voce){
	// Un ListItem porta il riferimento dentro `item`; si accetta anche il
	// riferimento nudo, perché un elenco scritto a mano può saltare l'involucro.
	meetoo_citta(!empty($voce->item) ? $voce->item : $voce);
}
?>
					</div>
				</section>
<?php } ?>
			</article>
<?php
include_template('template-parts/footer');
