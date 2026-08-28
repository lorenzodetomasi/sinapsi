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

/**
 * Questa raccolta è un PERCORSO?
 *
 * Lo dicono i dati, non un campo apposta: se le sue fermate dichiarano quanto
 * distano dall'inizio, allora hanno un ordine nello spazio — e una cosa che ha un
 * ordine nello spazio si guarda come una linea, non come un elenco. Vale per il
 * lungomare di Ostia e varrà per qualunque altro percorso, senza aggiungere niente
 * al contenuto.
 */
function meetoo_e_percorso($ent){
	foreach((!empty($ent->itemListElement) ? $ent->itemListElement : array()) as $riga){
		$m = !empty($riga->item) ? $riga->item : $riga;
		/* `children('meetoo', true)`: quel campo è in un NAMESPACE, e con
		 * `$m->{'meetoo:…'}` SimpleXML non lo trova — cerca un elemento che si chiama
		 * proprio così, con i due punti dentro, e non esiste. Il `true` dice di
		 * risolvere il prefisso, che è l'unica cosa stabile: l'indirizzo del
		 * namespace cambia a seconda di come il contenuto dichiara il contesto. */
		$suoi = $m->children('meetoo', true);
		if($suoi !== null and isset($suoi->m_from_border_south)){
			return true;
		}
	}
	return false;
}
$percorso = (!$serie and meetoo_e_percorso($e));
if($percorso){
	/* Il vestito e il comportamento della linea si caricano SOLO qui: sono
	 * cinquecento righe di stile e mille di programma, e su una raccolta qualunque
	 * sarebbero peso scaricato per niente. */
	$ws_theme_url = ws_theme_url();
	$GLOBALS['ws_links'][] = '<link rel="stylesheet" type="text/css" media="all" href="'.$ws_theme_url.'lungomare.css" />';
	$GLOBALS['ws_scripts']['bodyend']['meetoo_lungomare'] = '<script defer="defer" src="'.$ws_theme_url.'js/lungomare.js"></script>';
	/* Quale raccolta disegnare: l'@id, che è anche la cartella dei suoi dati.
	 * Va nei GLOBALS, non in una variabile qui: i template si includono dentro una
	 * funzione del CMS, e da lì le variabili di questo file non si vedono. */
	$GLOBALS['raccolta'] = preg_replace('#^[^/]+/[^/]+/#', '', trim((string)($ws_query['content'] ?? ''), '/'));
}

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
	meetoo_sezione('eventi', $SEZIONI['eventi'] ?? null, $tutto);
	meetoo_sezione('archivio', $SEZIONI['archivio'] ?? null, $tutto);
} else {
	/* L'elenco delle fermate c'è SEMPRE: è quello che legge un motore di ricerca, e
	 * quello che resta a chi il JavaScript non ce l'ha. Quando la linea si accende,
	 * si toglie di mezzo da solo (`body.mt-percorso`).
	 * `?? null`: se il file delle sezioni sul server fosse più vecchio di questo
	 * template, la sezione si arrangia invece di far stampare avvisi. */
	if($percorso){ echo '<div class="mt-percorso-elenco">'; }
	/* Una raccolta può essere DIVISA IN PARTI — «Adatto ai bambini» e «Progettato
	 * per i bambini» — e ogni parte ha il suo nome, il suo sommario e la sua regola.
	 * Sono sezioni della stessa pagina, non pagine diverse: dividere una categoria
	 * in due non deve costare due indirizzi. */
	$parti = !empty($e->hasPart) ? $e->hasPart : array();
	if(count($parti)){
		$n = 0;
		foreach($parti as $parte){
			$cfg = ($SEZIONI['raccolta'] ?? array());
			$nome = trim((string)($parte->name ?? ''));
			$nota = trim((string)($parte->description ?? ''));
			$cfg['titolo'] = $nome !== '' ? $nome : sprintf(__('Parte %d'), $n + 1);
			$cfg['icona'] = 'list';
			$cfg['vuoto'] = $nota !== ''
				? sprintf(__('Ancora niente qui. %s'), $nota)
				: __('Ancora niente in questa parte.');
			meetoo_sezione('raccolta:'.$n, $cfg, $tutto);
			$n++;
		}
	} else {
		meetoo_sezione('raccolta', $SEZIONI['raccolta'] ?? null, $tutto);
	}
	if($percorso){ echo '</div>'; }
}
?>
			</article>
<?php if($percorso){ include_template('template-parts/percorso'); } ?>
<?php
include_template('template-parts/footer');
