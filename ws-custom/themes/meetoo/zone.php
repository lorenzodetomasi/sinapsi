<?php
/**
 * La pagina di una zona: che cosa succede qui, adesso.
 *
 * È la porta d'ingresso per chi il quartiere lo abita, e prende il posto della
 * vecchia `index.html` che riempiva le stesse liste in JavaScript. Le sezioni sono
 * le sue — esplora, percorsi, eventi, collezioni, gruppi, luoghi — con le stesse
 * card, perché il vestito era già scritto in `meetoo.css`. Quello che cambia è chi
 * scrive il markup: il server, quindi un motore di ricerca le vede e chi ha la
 * rete lenta le legge subito.
 *
 * LE LISTE LUNGHE RESTANO PIGRE. Della lista dei luoghi (una sessantina) arriva
 * subito solo la prima manciata; le altre le chiede il browser, a questa stessa
 * pagina, quando la fine dell'elenco si avvicina — `?parte=luoghi&da=8` risponde
 * con le card successive e basta. Così il modello della card resta uno solo (in
 * `template-parts/carte.php`) e senza JavaScript non si perde niente: il pulsante
 * «mostra altri» è un collegamento vero a `?tutti=luoghi`, che le stampa tutte.
 *
 * Le liste si leggono dagli INDICI (`_index/`), non scandendo le cartelle: gli
 * indici esistono apposta, li rigenera la manutenzione, e leggerli costa una
 * lettura invece di settantasei. Se un indice manca, la sezione dice che manca e la
 * pagina resta in piedi: meglio una sezione vuota che una pagina bianca.
 */
global $ws_content, $ws_query, $rewrite_rule;

include_template('template-parts/carte');

$e = !empty($ws_content->mainEntity) ? $ws_content->mainEntity : $ws_content;
$nome = (string)($e->name ?? '');

/** Quante card in più arrivano a ogni caricamento pigro. */
if(!defined('MEETOO_PASSO')){
	define('MEETOO_PASSO', 12);
}

/**
 * Le sezioni a elenco: quelle che possono allungarsi, e quindi si caricano a
 * pezzi. `primi` è quanto ne arriva già scritto nella pagina — abbastanza da
 * riempire lo schermo e da dare a un motore di ricerca qualcosa da leggere.
 */
$SEZIONI = array(
	'eventi' => array(
		'titolo' => __('Prossimi eventi'), 'icona' => 'event', 'lista' => 'cards', 'primi' => 6,
		'vuoto' => __('Nessun evento in programma al momento.'),
	),
	'collezioni' => array(
		'titolo' => __('Collezioni di eventi'), 'icona' => 'collections_bookmark', 'lista' => 'grid', 'primi' => 6,
		'vuoto' => __('Nessuna collezione: le collezioni raccolgono gli appuntamenti che si ripetono.'),
	),
	'gruppi' => array(
		'titolo' => __('Chi anima il territorio'), 'icona' => 'groups', 'lista' => 'grid', 'primi' => 6,
		'vuoto' => __('Nessun gruppo nell’indice.'),
	),
	'luoghi' => array(
		'titolo' => __('Luoghi'), 'icona' => 'place', 'lista' => 'cards', 'primi' => 8,
		'vuoto' => __('Nessun luogo nell’indice.'),
	),
);

/**
 * Legge un file dell'indice. Ritorna sempre un array: gli indici sono derivati, e
 * un derivato che manca è una cosa da rigenerare, non da far esplodere.
 *
 * Gli indici non stanno tutti nello stesso posto: quelli delle entità in
 * `_index/`, quelli degli eventi in `events/_index/` — perché l'indice degli
 * eventi è diviso fra prossimi e archivio, e vive accanto a ciò che indicizza.
 * Si guarda in tutti e due invece di dare per scontato l'uno o l'altro.
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
 * Le card di una sezione, già scritte, in ordine. Una funzione sola perché la
 * pagina intera e il pezzo chiesto dal browser devono dire la stessa cosa: se
 * divergessero, l'elenco cambierebbe faccia a metà scorrimento.
 */
function meetoo_voci($quale){
	static $fatte = array();
	if(isset($fatte[$quale])){
		return $fatte[$quale];
	}
	$out = array();

	if($quale === 'eventi' or $quale === 'collezioni'){
		$tutti = meetoo_indice('events.json');
		$ora = time();
		$scelti = array();
		foreach($tutti as $ev){
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
				$out[] = mt_card_evento($ev);
			}
		} else {
			usort($scelti, function($a, $b){
				return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
			});
			foreach($scelti as $c){
				$path = (string)($c['path'] ?? '');
				$chi = trim((string)($c['organizer'] ?? ''));
				$out[] = mt_card_tile(array(
					'href' => ws_href('eventi/'.basename($path)),
					'icon' => 'collections_bookmark',
					'accent' => true,
					'title' => !empty($c['name']) ? (string)$c['name'] : basename($path),
					'meta' => $chi !== '' ? $chi : __('Collezione di eventi'),
					'metaIcon' => $chi !== '' ? mt_org_icona($c['organizerType'] ?? '', $chi) : '',
				));
			}
		}
		return $fatte[$quale] = $out;
	}

	if($quale === 'gruppi'){
		// L'indice dei gruppi è curato: ci sono le organizzazioni e quelle attività
		// che sul territorio fanno cose collettive — non tutte le attività.
		foreach(meetoo_indice('gruppi.json') as $g){
			$id = (string)($g['@id'] ?? '');
			$org = (($g['kind'] ?? '') === 'org');
			$chiave = trim((string)($g['key'] ?? ''));
			$slug = ($org and $chiave !== '') ? $chiave : basename($id);
			$out[] = mt_card_tile(array(
				'href' => ws_href(($org ? 'organizzatori/' : 'luoghi/').$slug),
				'icon' => mt_org_icona($g['@type'] ?? '', $g['name'] ?? ''),
				'title' => (string)($g['name'] ?? $slug),
				'meta' => $org ? __('Eventi e collezioni') : __('Progetti ed eventi collettivi'),
			));
		}
		return $fatte[$quale] = $out;
	}

	if($quale === 'luoghi'){
		foreach(meetoo_indice('entities.json') as $l){
			if(($l['kind'] ?? '') !== 'business'){
				continue;
			}
			// Le liste curate (il Lungomare, il BookCrossing) sono percorsi, non
			// luoghi: hanno la loro sezione più in alto.
			if(in_array('ItemList', (array)($l['@type'] ?? array()), true)){
				continue;
			}
			$id = (string)($l['@id'] ?? '');
			$href = ws_href('luoghi/'.basename($id));
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
 * È un collegamento vero, non un pulsante finto: senza JavaScript porta alla
 * pagina con quella sezione stampata per intero. Con JavaScript non ci si arriva
 * quasi mai, perché le card successive arrivano prima, mentre si scorre.
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

/* ---------------------------------------------------------------------------
 * Il pezzo chiesto dal browser: le card che vengono dopo, e nient'altro.
 * Si risponde prima di scrivere la testa della pagina — qui non serve una pagina,
 * serve un pezzo di elenco. La sezione si accetta solo se è una di quelle
 * dichiarate: `$_GET` è di chi passa, non di chi scrive.
 * ------------------------------------------------------------------------- */
$parte = (string)($_GET['parte'] ?? '');
if($parte !== '' and isset($SEZIONI[$parte])){
	$voci = meetoo_voci($parte);
	$da = max(0, (int)($_GET['da'] ?? 0));
	$blocco = array_slice($voci, $da, MEETOO_PASSO);
	echo implode("\n", $blocco);
	echo meetoo_altri($parte, $da + count($blocco), count($voci));
	exit;
}

// Una sezione stampata per intero: è il ripiego di chi non ha JavaScript, ed è
// anche l'indirizzo da dare a chi vuole vedere tutto in un colpo solo.
$tutto = (string)($_GET['tutti'] ?? '');

/** Una sezione a elenco, con il suo conteggio e la sua coda pigra. */
function meetoo_sezione($quale, $cfg, $tutto){
	$voci = meetoo_voci($quale);
	$totale = count($voci);
	$quante = ($tutto === $quale) ? $totale : min($totale, (int)$cfg['primi']);
?>
					<section id="<?php echo mt_esc($quale); ?>" class="mt-sezione">
						<h2 class="sec-head"><?php echo mt_icona($cfg['icona']); ?><?php echo mt_esc($cfg['titolo']); ?><span class="count"><?php echo $totale ? (int)$totale : ''; ?></span></h2>
<?php if(!$totale){ ?>
						<div class="empty"><?php echo mt_esc($cfg['vuoto']); ?></div>
<?php } else { ?>
						<div class="<?php echo mt_esc($cfg['lista']); ?>" data-lista="<?php echo mt_esc($quale); ?>">
<?php
	echo implode("\n", array_slice($voci, 0, $quante));
	echo meetoo_altri($quale, $quante, $totale);
?>
						</div>
<?php } ?>
					</section>
<?php
}

/** Il testo di un campo del contenuto, '' se quel campo non c'è. */
function meetoo_testo($nodo, $campo){
	return isset($nodo->$campo) ? trim((string)$nodo->$campo) : '';
}

/**
 * L'@id a cui punta un nodo del contenuto.
 *
 * Nel passaggio da JSON a XML l'`@id` della radice diventa l'attributo `id`,
 * quello di un nodo interno un `xlink:href` — perché lì è un RIFERIMENTO ad
 * altro, non il nome di questo. Si guardano tutti e due: chi legge un contenuto
 * non deve sapere in che punto dell'albero si trova.
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

/* I percorsi della zona (il Lungomare, il BookCrossing): non vengono da un indice,
 * li dichiara il contenuto — sono pochi, scelti a mano, e non si allungano da soli.
 * Nome, sommario e icona stanno lì accanto al riferimento: sono le etichette con
 * cui la zona presenta i suoi percorsi, e non per forza il nome per esteso del
 * documento in fondo al collegamento. `icon` è un termine nostro: chi legge
 * schema.org lo ignora, e va bene così. */
$percorsi = array();
foreach((!empty($e->hasPart) ? $e->hasPart : array()) as $lista){
	$id = meetoo_riferimento($lista);
	if($id === ''){
		continue;
	}
	$slug = basename($id);
	$titolo = meetoo_testo($lista, 'name');
	$percorsi[] = mt_card_tile(array(
		'href' => ws_href($slug),
		'icon' => meetoo_testo($lista, 'icon') ?: 'route',
		'accent' => true,
		'title' => $titolo !== '' ? $titolo : ucfirst(str_replace('-', ' ', $slug)),
		'meta' => meetoo_testo($lista, 'description'),
	));
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
foreach(array(
	array('#eventi', 'event', __('Eventi'), __('Cosa fare qui')),
	array('#collezioni', 'collections_bookmark', __('Collezioni'), __('Gli appuntamenti che si ripetono')),
	array('#gruppi', 'groups', __('Gruppi'), __('Chi anima il territorio')),
	array('#luoghi', 'place', __('Luoghi'), __('Dove succedono le cose')),
) as $voce){
	echo mt_card_tile(array('href' => $voce[0], 'icon' => $voce[1], 'title' => $voce[2], 'meta' => $voce[3]));
}
?>
					</div>
				</section>

<?php if(count($percorsi)){ ?>
				<section id="percorsi" class="mt-sezione">
					<h2 class="sec-head"><?php echo mt_icona('route'); ?><?php _e('Percorsi'); ?></h2>
					<div class="grid"><?php echo implode("\n", $percorsi); ?></div>
				</section>
<?php } ?>

<?php
foreach($SEZIONI as $quale => $cfg){
	meetoo_sezione($quale, $cfg, $tutto);
}
?>
			</article>
<?php
include_template('template-parts/footer');
