<?php
/**
 * Un elenco di una zona: gli eventi, i gruppi, i luoghi.
 *
 * Tre pagine con lo stesso template: quale delle tre lo dice la mappa, nel
 * parametro `elenco` della query. Il contenuto è quello della zona — nome,
 * descrizione, briciole vengono da lì — perché un elenco non è un'entità a sé: è un
 * modo di guardare la zona.
 *
 * Esistono come PAGINE, e non come ancore dentro la zona, per la ragione più
 * concreta che c'è: «gli eventi di Lido di Ostia» è un indirizzo che uno manda a un
 * altro. Un'ancora no.
 */
global $ws_content, $ws_query, $rewrite_rule;

include_template('template-parts/carte');
include_template('template-parts/elenchi');

meetoo_frammento();

$e = !empty($ws_content->mainEntity) ? $ws_content->mainEntity : $ws_content;
$zona = (string)($e->name ?? '');
$SEZIONI = meetoo_sezioni(true);
$quale = (string)($ws_query['elenco'] ?? 'eventi');
if(!isset($SEZIONI[$quale])){
	$quale = 'eventi';
}
$cfg = $SEZIONI[$quale];
// Qui l'elenco è il motivo della pagina: si stampa tutto quello che c'è, e il
// caricamento pigro serve a non farlo arrivare tutto insieme.
$tutto = (string)($_GET['tutti'] ?? '');

include_template('template-parts/header');
?>
			<article<?php echo ws_html_attributes('main-content', array('class' => array('mt-pagina', 'mt-elenco-pagina'))); ?>>
				<h1 class="mt-h1"><?php echo mt_esc($cfg['titolo']); ?></h1>
				<p class="mt-sommario"><?php echo mt_esc($cfg['sommario']); ?><?php echo $zona !== '' ? ' — '.mt_esc($zona) : ''; ?></p>

<?php
meetoo_sezione($quale, $cfg, $tutto);
/* Sulla pagina degli eventi ce ne sono altri due tipi, e sono altre due domande:
 * che cosa si ripete, e che cosa è già successo. Le collezioni si vedono subito
 * perché sono poche e sono il modo in cui un quartiere ha un ritmo; l'archivio no,
 * si apre a chi lo chiede. */
if($quale === 'eventi'){
	meetoo_sezione('collezioni', $SEZIONI['collezioni'], $tutto);
	meetoo_sezione('archivio', $SEZIONI['archivio'], $tutto);
}
?>
			</article>
<?php
include_template('template-parts/footer');
