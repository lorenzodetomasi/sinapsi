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
function meetoo_zona($luogo, $profondita = 0){
	$nome = (string)($luogo->name ?? '');
	if($nome === ''){
		return;
	}
	$url = trim((string)($luogo->url ?? ''));
	$attiva = ($url !== '');
	$figli = !empty($luogo->containsPlace) ? $luogo->containsPlace : array();
	$tag = $attiva ? 'a' : 'span';
?>
	<li class="mt-zona<?php echo $attiva ? ' attiva' : ' in-arrivo'; ?>" style="--profondita: <?php echo (int)$profondita; ?>">
		<<?php echo $tag; ?> class="mt-zona-nome"<?php if($attiva){ echo ' href="'.ws_href(ltrim($url, '/')).'"'; } ?>>
			<span class="material-symbols-outlined" aria-hidden="true"><?php echo $attiva ? 'place' : 'more_horiz'; ?></span>
			<span class="mt-zona-testo">
				<b><?php echo htmlspecialchars($nome, ENT_QUOTES, 'UTF-8'); ?></b>
<?php if(!empty($luogo->description)){ ?>
				<span class="mt-zona-nota"><?php echo htmlspecialchars((string)$luogo->description, ENT_QUOTES, 'UTF-8'); ?></span>
<?php } else if(!$attiva){ ?>
				<span class="mt-zona-nota"><?php _e('In preparazione'); ?></span>
<?php } ?>
			</span>
<?php if($attiva){ ?>
			<span class="material-symbols-outlined mt-zona-freccia" aria-hidden="true">arrow_forward</span>
<?php } ?>
		</<?php echo $tag; ?>>
<?php if(count($figli)){ ?>
		<ul class="mt-zone">
<?php foreach($figli as $figlio){ meetoo_zona($figlio, $profondita + 1); } ?>
		</ul>
<?php } ?>
	</li>
<?php
}

include_template('template-parts/header');
?>
			<article<?php echo ws_html_attributes('main-content', array('class' => array('mt-pagina', 'mt-home'))); ?>>
				<h1 class="mt-h1"><?php echo htmlspecialchars((string)$e->name, ENT_QUOTES, 'UTF-8'); ?></h1>
<?php if(!empty($e->description)){ ?>
				<div class="mt-corpo"><?php ws_echo($e->description->innerHTML()); ?></div>
<?php } ?>

<?php if($zone){ ?>
				<section class="mt-scelta-zona" aria-labelledby="zone">
					<h2 id="zone" class="mt-h2"><?php echo htmlspecialchars((string)($zone->name ?: __('Zone')), ENT_QUOTES, 'UTF-8'); ?></h2>
<?php if(!empty($zone->description)){ ?>
					<div class="mt-corpo mt-nota"><?php ws_echo($zone->description->innerHTML()); ?></div>
<?php } ?>
					<ul class="mt-zone">
<?php
foreach($zone->itemListElement as $voce){
	// Un ListItem porta il luogo dentro `item`; si accetta anche il luogo nudo,
	// perché una lista scritta a mano può saltare l'involucro.
	meetoo_zona(!empty($voce->item) ? $voce->item : $voce);
}
?>
					</ul>
				</section>
<?php } ?>
			</article>
<?php
include_template('template-parts/footer');
