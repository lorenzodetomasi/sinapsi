<?php
/**
 * Un livello che contiene altri livelli: una città, un municipio.
 *
 * Non è un posto dove si va — è quello che contiene i posti — e la sua pagina serve
 * a due cose concrete. Chi accorcia un indirizzo a mano
 * (`…/roma/municipio10/lido-di-ostia` → `…/roma/municipio10`) deve trovarci
 * qualcosa, non un 404: è il modo in cui si risale. E lì dentro cresceranno gli
 * altri quartieri: quando ce ne sarà un secondo, questa pagina lo mostrerà senza
 * che nessuno tocchi una riga.
 *
 * COME SI CHIAMANO LE SUE PARTI lo dice il contenuto, non il codice: Roma ha
 * «Municipi», il Municipio X ha «Quartieri», e una città di un altro paese avrà
 * un'altra parola ancora. Sta in `meetoo:childrenHeading`, sul nodo che le contiene.
 */
global $ws_content, $ws_query;

include_template('template-parts/carte');

$doc = !empty($ws_content->mainEntity) ? $ws_content->mainEntity : $ws_content;
$slug = trim((string)($ws_query['zona'] ?? ''));

/**
 * Il nodo da mostrare, e il percorso per arrivarci.
 *
 * Senza `zona` è la radice del documento (la città); con `zona` è quel nodo dentro
 * il suo albero — un municipio non ha un file suo, vive dentro quello della città.
 */
function meetoo_nodo($nodo, $slug, $prefisso = ''){
	$id = trim(meetoo_riferimento_nodo($nodo));
	$mio = $id !== '' ? basename($id) : '';
	$percorso = ($prefisso === '' ? '' : $prefisso.'/').$mio;
	if($mio !== '' and ($slug === '' or $mio === $slug)){
		return array('nodo' => $nodo, 'percorso' => $percorso);
	}
	foreach((!empty($nodo->containsPlace) ? $nodo->containsPlace : array()) as $figlio){
		$dentro = meetoo_nodo($figlio, $slug, $percorso);
		if($dentro){
			return $dentro;
		}
	}
	return null;
}

$trovato = meetoo_nodo($doc, $slug);
$nodo = $trovato ? $trovato['nodo'] : $doc;
$percorso = $trovato ? $trovato['percorso'] : trim((string)$ws_query['wspath'], '/');
$nome = (string)($nodo->name ?? '');
$figli = !empty($nodo->containsPlace) ? $nodo->containsPlace : array();

/** L'intestazione delle parti: la dichiara il nodo, se no si dice quello che sono. */
$intestazione = trim((string)(meetoo_campo_meetoo($nodo, 'childrenHeading') ?: ''));
if($intestazione === ''){
	$intestazione = __('Zone');
}

include_template('template-parts/header');
?>
			<article<?php echo ws_html_attributes('main-content', array('class' => array('mt-pagina', 'mt-area-pagina'))); ?>>
				<h1 class="mt-h1"><?php echo mt_esc($nome); ?></h1>
<?php
$testo = meetoo_testo_visibile($nodo);
if($testo !== ''){
?>
				<div class="mt-corpo"><?php ws_echo($testo); ?></div>
<?php } else if(!empty($nodo->description)){ ?>
				<p class="mt-sommario"><?php echo mt_esc((string)$nodo->description); ?></p>
<?php } ?>

<?php if(count($figli)){ ?>
				<section class="mt-sezione">
					<h2 class="sec-head"><?php echo mt_icona('place'); ?><?php echo mt_esc($intestazione); ?></h2>
					<div class="grid">
<?php
foreach($figli as $figlio){
	$id = meetoo_riferimento_nodo($figlio);
	if($id === ''){
		continue;
	}
	$mio = basename($id);
	$nomeF = (string)($figlio->name ?? $mio);
	$nota = trim((string)($figlio->description ?? ''));
	$ico = meetoo_icona_nodo($figlio) ?: meetoo_icona_di($id);
	$sotto = !empty($figlio->containsPlace) ? count($figlio->containsPlace) : 0;
	// Attivo = la mappa gli ha dato un indirizzo (ha un contenuto suo), oppure ha
	// dentro qualcosa: in tutti e due i casi c'è dove andare.
	$href = meetoo_indirizzo($id);
	if($href === '' and ($sotto or meetoo_titolo($percorso.'/'.$mio) !== '')){
		$href = ws_href($percorso.'/'.$mio);
	}
	if($href !== ''){
		echo mt_card_tile(array(
			'href' => $href,
			'icon' => $ico ? $ico['name'] : ($sotto ? 'account_tree' : 'place'),
			'iconClass' => $ico ? $ico['class'] : '',
			'title' => $nomeF,
			'meta' => $nota,
		));
		continue;
	}
?>
						<div class="card mt-in-arrivo">
							<div class="card-icon"><?php echo mt_icona($ico ? $ico['name'] : 'more_horiz', $ico ? $ico['class'] : ''); ?></div>
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
