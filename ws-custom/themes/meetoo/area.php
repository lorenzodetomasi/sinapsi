<?php
/**
 * Un livello dell'albero che non è (ancora) una zona: Roma, Municipio X.
 *
 * Esiste per due ragioni concrete. Chi accorcia un indirizzo a mano —
 * `…/roma/municipio10/lido-di-ostia` → `…/roma/municipio10` — deve trovarci
 * qualcosa, non un 404: è il modo in cui si risale, ed è una cosa che le persone
 * fanno. E perché lì dentro cresceranno gli altri quartieri: quando ce ne sarà un
 * secondo, questa pagina lo mostrerà senza che nessuno tocchi una riga.
 *
 * Il contenuto è l'albero delle zone (`index/index.json`); quale nodo mostrare lo
 * dice la mappa, nel parametro `zona` della query.
 */
global $ws_content, $ws_query;

include_template('template-parts/carte');

$e = !empty($ws_content->mainEntity) ? $ws_content->mainEntity : $ws_content;
$slug = (string)($ws_query['zona'] ?? '');

/**
 * Cerca un nodo dell'albero per identificativo, e se ne ricorda il percorso.
 * Ricorsiva perché l'albero è ricorsivo: città, municipio, quartiere.
 */
function meetoo_nodo($nodi, $slug, $prefisso = ''){
	foreach($nodi as $nodo){
		$n = !empty($nodo->item) ? $nodo->item : $nodo;
		$id = trim((string)($n->identifier ?? ''));
		if($id === ''){
			continue;
		}
		$percorso = ($prefisso === '' ? '' : $prefisso.'/').$id;
		if($id === $slug){
			return array('nodo' => $n, 'percorso' => $percorso);
		}
		if(!empty($n->containsPlace)){
			$dentro = meetoo_nodo($n->containsPlace, $slug, $percorso);
			if($dentro){
				return $dentro;
			}
		}
	}
	return null;
}

$radice = !empty($e->hasPart->itemListElement) ? $e->hasPart->itemListElement : array();
$trovato = meetoo_nodo($radice, $slug);
$nodo = $trovato ? $trovato['nodo'] : null;
$percorso = $trovato ? $trovato['percorso'] : '';
$nome = $nodo ? (string)$nodo->name : $slug;

include_template('template-parts/header');
?>
			<article<?php echo ws_html_attributes('main-content', array('class' => array('mt-pagina', 'mt-area-pagina'))); ?>>
				<h1 class="mt-h1"><?php echo mt_esc($nome); ?></h1>
<?php if($nodo and !empty($nodo->description)){ ?>
				<p class="mt-sommario"><?php echo mt_esc((string)$nodo->description); ?></p>
<?php } ?>

<?php
$figli = ($nodo and !empty($nodo->containsPlace)) ? $nodo->containsPlace : array();
if(count($figli)){
?>
				<section class="mt-sezione">
					<h2 class="sec-head"><?php echo mt_icona('place'); ?><?php _e('Zone'); ?></h2>
					<div class="grid">
<?php
foreach($figli as $figlio){
	$id = trim((string)($figlio->identifier ?? ''));
	if($id === ''){
		continue;
	}
	$suo = $percorso.'/'.$id;
	// Attiva = la mappa le ha dato un indirizzo, cioè il suo contenuto esiste.
	$href = meetoo_indirizzo('places/'.$id);
	$nomeF = (string)($figlio->name ?? $id);
	$nota = trim((string)($figlio->description ?? ''));
	if($href !== ''){
		echo mt_card_tile(array('href' => $href, 'icon' => 'place', 'title' => $nomeF, 'meta' => $nota));
		continue;
	}
	// Un livello intermedio senza contenuto proprio ha comunque la sua pagina:
	// dentro ci sono i suoi figli, ed è lì che si continua a scendere.
	$sotto = !empty($figlio->containsPlace) ? count($figlio->containsPlace) : 0;
	if($sotto){
		echo mt_card_tile(array('href' => ws_href($suo), 'icon' => 'account_tree', 'title' => $nomeF, 'meta' => $nota));
		continue;
	}
?>
						<div class="card mt-in-arrivo">
							<div class="card-icon"><?php echo mt_icona('more_horiz'); ?></div>
							<div class="card-body">
								<h3 class="card-title"><?php echo mt_esc($nomeF); ?> <span class="mt-etichetta"><?php _e('In preparazione'); ?></span></h3>
<?php if($nota !== ''){ ?>
								<div class="card-meta"><span><?php echo mt_esc($nota); ?></span></div>
<?php } ?>
							</div>
						</div>
<?php
}
?>
					</div>
				</section>
<?php } ?>
			</article>
<?php
include_template('template-parts/footer');
