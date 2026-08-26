<?php
/**
 * Una collezione: il lungomare, il BookCrossing, una categoria, una serie di eventi.
 *
 * Sono tutte la stessa cosa — un elenco curato di altre cose — e per chi legge
 * cambia solo che cosa c'è dentro. Quindi una pagina sola, che mostra il suo
 * contenuto e poi l'elenco, con lo stesso caricamento pigro degli altri: il
 * lungomare ha sessantuno fermate, e non ha senso spedirle tutte a chi apre la
 * pagina per leggere di che si tratta.
 *
 * Una COLLEZIONE DI EVENTI (EventSeries) è un caso a parte: le sue voci sono le
 * occorrenze, e quelle hanno una data — si mostrano come eventi, con le stesse
 * sezioni delle altre pagine (prossimi, e archivio su richiesta).
 */
global $ws_content, $ws_query, $rewrite_rule;

include_template('template-parts/carte');
include_template('template-parts/elenchi');

$e = !empty($ws_content->mainEntity) ? $ws_content->mainEntity : $ws_content;
$titolo = !empty($e->name) ? (string)$e->name : (string)($rewrite_rule->title ?? '');
$tipi = strtolower((string)($rewrite_rule->type ?? ''));
$serie = (strpos($tipi, 'eventseries') !== false);

if($serie){
	// Le occorrenze di questa serie: l'indice per collezione esiste apposta.
	$pezzi = explode('/', trim((string)($ws_query['content'] ?? ''), '/'));
	meetoo_ambito('collection', end($pezzi));
}
meetoo_frammento();

$tutto = (string)($_GET['tutti'] ?? '');
$SEZIONI = meetoo_sezioni(true);

include_template('template-parts/header');
?>
			<article<?php echo ws_html_attributes('main-content', array('class' => array('mt-pagina', 'mt-raccolta-pagina'))); ?>>
<?php if(!empty($e->image)){ ?>
				<figure class="mt-copertina">
					<img src="<?php echo mt_esc((string)$e->image); ?>" alt="" loading="lazy" decoding="async" />
				</figure>
<?php } ?>
				<h1 class="mt-h1"><?php echo mt_esc($titolo); ?></h1>
<?php $testo = meetoo_testo_visibile($e); if($testo !== ''){ ?>
				<div class="mt-corpo"><?php ws_echo($testo); ?></div>
<?php } ?>

<?php
if($serie){
	meetoo_sezione('eventi', $SEZIONI['eventi'], $tutto);
	meetoo_sezione('archivio', $SEZIONI['archivio'], $tutto);
} else {
	meetoo_sezione('raccolta', $SEZIONI['raccolta'], $tutto);
}
?>
			</article>
<?php
include_template('template-parts/footer');
