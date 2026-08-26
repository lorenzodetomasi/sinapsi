<?php
/**
 * Gli elenchi di una zona: eventi, gruppi, luoghi.
 *
 * Stesse card e stesse regole, in due posti diversi: la pagina della zona ne mostra
 * un assaggio, la pagina dell'elenco li mostra tutti. Il codice è qui in mezzo
 * perché di regole ce ne sia una sola — se «Prossimi eventi» sulla zona e la pagina
 * `/eventi` filtrassero in due modi, sarebbero due elenchi diversi con lo stesso
 * nome, e nessuno saprebbe quale credere.
 *
 * GLI ELENCHI LUNGHI RESTANO PIGRI. Arriva subito la prima manciata; le altre le
 * chiede il browser alla stessa pagina — `?parte=luoghi&da=8` risponde con le card
 * successive e basta — quando la fine si avvicina. Senza JavaScript non si perde
 * niente: «mostra altri» è un collegamento vero a `?tutti=luoghi`, che le stampa
 * tutte.
 *
 * Le liste si leggono dagli INDICI (`_index/`), non scandendo le cartelle: gli
 * indici esistono apposta e leggerli costa una lettura invece di settantasei.
 */

if(!function_exists('meetoo_indice')){

/** Quante card in più arrivano a ogni caricamento pigro. */
if(!defined('MEETOO_PASSO')){
	define('MEETOO_PASSO', 12);
}

/**
 * Le tre sezioni, con il loro nome e quanto ne arriva già scritto nella pagina.
 * `primi` è l'assaggio sulla pagina della zona; sulla pagina dell'elenco si parte
 * più larghi, perché è lì che uno è andato apposta.
 */
function meetoo_sezioni($ampio = false){
	return array(
		'eventi' => array(
			'titolo' => __('Prossimi eventi'), 'icona' => 'event', 'lista' => 'cards',
			'primi' => $ampio ? 12 : 6,
			'vuoto' => __('Nessun evento in programma al momento.'),
			'sommario' => __('Le cose da fare nei prossimi giorni'),
		),
		'gruppi' => array(
			'titolo' => __('Gruppi'), 'icona' => 'groups', 'lista' => 'grid',
			'primi' => $ampio ? 12 : 6,
			'vuoto' => __('Nessun gruppo nell’indice.'),
			'sommario' => __('Chi anima il territorio'),
		),
		'luoghi' => array(
			'titolo' => __('Luoghi'), 'icona' => 'place', 'lista' => 'cards',
			'primi' => $ampio ? 12 : 8,
			'vuoto' => __('Nessun luogo nell’indice.'),
			'sommario' => __('Dove succedono le cose'),
		),
		'collezioni' => array(
			'titolo' => __('Collezioni di eventi'), 'icona' => 'collections_bookmark', 'lista' => 'grid',
			'primi' => $ampio ? 12 : 6,
			'vuoto' => __('Nessuna collezione.'),
			'sommario' => __('Gli appuntamenti che si ripetono'),
		),
	);
}

/**
 * Legge un file dell'indice. Ritorna sempre un array: gli indici sono derivati, e
 * un derivato che manca è una cosa da rigenerare, non da far esplodere.
 *
 * Non stanno tutti nello stesso posto: le entità in `_index/`, gli eventi in
 * `events/_index/` — perché l'indice degli eventi è diviso fra prossimi e archivio,
 * e vive accanto a ciò che indicizza.
 */
function meetoo_indice($nome){
	global $ws_query;
	static $letti = array();
	if(isset($letti[$nome])){
		return $letti[$nome];
	}
	$pezzi = explode('/', (string)$ws_query['content']);
	$sito = $pezzi[0];
	$locale = $pezzi[1] ?? ws_locale();
	$radice = ws_root_abspath().'/'.WS_CONTENTS_RELPATH."/$sito/$locale";
	$abspath = '';
	foreach(array("$radice/_index/$nome", "$radice/events/_index/$nome") as $forse){
		if(file_exists($forse)){
			$abspath = $forse;
			break;
		}
	}
	$dati = $abspath === '' ? null : json_decode((string)file_get_contents($abspath), true);
	if(!is_array($dati)){
		return $letti[$nome] = array();
	}
	return $letti[$nome] = (isset($dati['events']) && is_array($dati['events']) ? $dati['events'] : $dati);
}

/** L'icona di un luogo, dal suo tipo: un parco non è un negozio. */
function meetoo_icona_luogo($tipi){
	$t = strtolower(implode(' ', (array)$tipi));
	if(preg_match('/park|playground|beach/', $t)){ return 'park'; }
	if(preg_match('/library|book/', $t)){ return 'local_library'; }
	if(preg_match('/localbusiness|store|restaurant|cafe|bar/', $t)){ return 'storefront'; }
	return 'place';
}

/**
 * Le card di una sezione, già scritte, in ordine.
 *
 * Gli indirizzi si chiedono alla mappa (`meetoo_indirizzo`): da quando dicono in
 * che zona sei non si possono più incollare a mano, e chi non ha una pagina non
 * diventa un collegamento rotto — sparisce dall'elenco.
 */
function meetoo_voci($quale){
	static $fatte = array();
	if(isset($fatte[$quale])){
		return $fatte[$quale];
	}
	$out = array();

	if($quale === 'eventi' or $quale === 'collezioni'){
		$ora = time();
		$scelti = array();
		foreach(meetoo_indice('events.json') as $ev){
			$serie = (($ev['kind'] ?? '') === 'series');
			if($quale === 'collezioni'){
				if($serie){ $scelti[] = $ev; }
				continue;
			}
			if($serie){
				continue;
			}
			// Un evento è «prossimo» finché non è finito: quello di stasera resta in
			// elenco anche se è cominciato un'ora fa.
			$fine = strtotime((string)(!empty($ev['endDate']) ? $ev['endDate'] : ($ev['startDate'] ?? '')));
			if($fine and $fine < $ora){
				continue;
			}
			$scelti[] = $ev;
		}
		if($quale === 'eventi'){
			usort($scelti, function($a, $b){
				return strcmp((string)($a['startDate'] ?? ''), (string)($b['startDate'] ?? ''));
			});
			foreach($scelti as $ev){
				$href = meetoo_indirizzo($ev['path'] ?? '');
				if($href === ''){ continue; }
				$out[] = mt_card_evento($ev, array('href' => $href));
			}
		} else {
			usort($scelti, function($a, $b){
				return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
			});
			foreach($scelti as $c){
				$href = meetoo_indirizzo($c['path'] ?? '');
				if($href === ''){ continue; }
				$chi = trim((string)($c['organizer'] ?? ''));
				$out[] = mt_card_tile(array(
					'href' => $href,
					'icon' => 'collections_bookmark',
					'accent' => true,
					'title' => !empty($c['name']) ? (string)$c['name'] : basename((string)($c['path'] ?? '')),
					'meta' => $chi !== '' ? $chi : __('Collezione di eventi'),
					'metaIcon' => $chi !== '' ? mt_org_icona($c['organizerType'] ?? '', $chi) : '',
				));
			}
		}
		return $fatte[$quale] = $out;
	}

	if($quale === 'gruppi'){
		// L'indice dei gruppi è curato: le organizzazioni e quelle attività che sul
		// territorio fanno cose collettive — non tutte le attività.
		foreach(meetoo_indice('gruppi.json') as $g){
			$id = (string)($g['@id'] ?? '');
			$href = meetoo_indirizzo($id);
			if($href === ''){ continue; }
			$out[] = mt_card_tile(array(
				'href' => $href,
				'icon' => mt_org_icona($g['@type'] ?? '', $g['name'] ?? ''),
				'title' => (string)($g['name'] ?? basename($id)),
				'meta' => (($g['kind'] ?? '') === 'org') ? __('Eventi e collezioni') : __('Progetti ed eventi collettivi'),
			));
		}
		return $fatte[$quale] = $out;
	}

	if($quale === 'luoghi'){
		foreach(meetoo_indice('entities.json') as $l){
			if(($l['kind'] ?? '') !== 'business'){
				continue;
			}
			// Le collezioni curate (il Lungomare, il BookCrossing) sono percorsi, non
			// luoghi: stanno in «Categorie e Percorsi».
			if(in_array('ItemList', (array)($l['@type'] ?? array()), true)){
				continue;
			}
			$id = (string)($l['@id'] ?? '');
			$href = meetoo_indirizzo($id);
			if($href === ''){ continue; }
			$out[] = mt_card_tile(array(
				'href' => $href,
				'icon' => meetoo_icona_luogo($l['@type'] ?? ''),
				'title' => (string)($l['name'] ?? basename($id)),
				'meta' => (string)($l['locality'] ?? ''),
				'social' => array('kind' => 'place', 'id' => $id, 'url' => $href),
			));
		}
		return $fatte[$quale] = $out;
	}

	return $fatte[$quale] = $out;
}

/**
 * Il segnaposto in fondo a un elenco incompleto.
 *
 * È un collegamento vero, non un pulsante finto: senza JavaScript porta alla pagina
 * con quella sezione stampata per intero. Con JavaScript non ci si arriva quasi
 * mai, perché le card successive arrivano prima, mentre si scorre.
 */
function meetoo_altri($quale, $da, $totale){
	if($da >= $totale){
		return '';
	}
	$restanti = $totale - $da;
	return '<div class="mt-altri" data-parte="'.mt_esc($quale).'" data-da="'.(int)$da.'" data-totale="'.(int)$totale.'">'
		.'<a class="card-act" rel="nofollow" href="?tutti='.urlencode($quale).'#'.mt_esc($quale).'">'
		.mt_icona('expand_more').'<span>'.mt_esc(sprintf(__('Mostra altri %d'), $restanti)).'</span>'
		.'</a></div>';
}

/**
 * Il pezzo di elenco chiesto dal browser: le card che vengono dopo, e nient'altro.
 *
 * Si risponde prima di scrivere la testa della pagina — qui non serve una pagina,
 * serve un pezzo di elenco. La sezione si accetta solo se è una di quelle
 * dichiarate: `$_GET` è di chi passa, non di chi scrive.
 */
function meetoo_frammento(){
	$parte = (string)($_GET['parte'] ?? '');
	$sezioni = meetoo_sezioni();
	if($parte === '' or !isset($sezioni[$parte])){
		return;
	}
	$voci = meetoo_voci($parte);
	$da = max(0, (int)($_GET['da'] ?? 0));
	$blocco = array_slice($voci, $da, MEETOO_PASSO);
	echo implode("\n", $blocco);
	echo meetoo_altri($parte, $da + count($blocco), count($voci));
	exit;
}

/** Una sezione a elenco, con il suo conteggio e la sua coda pigra. */
function meetoo_sezione($quale, $cfg, $tutto, $titoloLink = ''){
	$voci = meetoo_voci($quale);
	$totale = count($voci);
	$quante = ($tutto === $quale) ? $totale : min($totale, (int)$cfg['primi']);
?>
				<section id="<?php echo mt_esc($quale); ?>" class="mt-sezione">
					<h2 class="sec-head"><?php echo mt_icona($cfg['icona']); ?><?php
						if($titoloLink !== ''){
							echo '<a href="'.mt_esc($titoloLink).'">'.mt_esc($cfg['titolo']).'</a>';
						} else {
							echo mt_esc($cfg['titolo']);
						}
					?><span class="count"><?php echo $totale ? (int)$totale : ''; ?></span></h2>
<?php if(!$totale){ ?>
					<div class="empty"><?php echo mt_esc($cfg['vuoto']); ?></div>
<?php } else { ?>
					<div class="<?php echo mt_esc($cfg['lista']); ?>" data-lista="<?php echo mt_esc($quale); ?>">
<?php
	echo implode("\n", array_slice($voci, 0, $quante));
	echo meetoo_altri($quale, $quante, $totale);
?>
					</div>
<?php if($quante < $totale and $titoloLink !== ''){ ?>
					<p class="mt-nota"><a href="<?php echo mt_esc($titoloLink); ?>"><?php printf(__('Vedi tutti (%d)'), $totale); ?></a></p>
<?php } ?>
<?php } ?>
				</section>
<?php
}

}// function_exists
?>
