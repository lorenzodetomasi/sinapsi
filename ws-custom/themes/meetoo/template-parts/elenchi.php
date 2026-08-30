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
 * L'invito che chiude un elenco vuoto.
 *
 * Una raccolta senza niente dentro non è un vicolo cieco: è il punto in cui chi
 * legge sa qualcosa che noi non sappiamo — quello che succede nel quartiere lo
 * vede lui prima di noi. Sta in una funzione sola perché la stessa frase compare
 * in fondo a ogni parte di ogni raccolta, e cinque copie divergono al primo
 * ripensamento.
 */
function meetoo_invito(){
	return __('Vuoi contribuire a questa raccolta? Segnalaci quello che ritieni utile e interessante.');
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
			'titolo' => __('Eventi ricorrenti'), 'icona' => 'collections_bookmark', 'lista' => 'grid',
			'primi' => $ampio ? 12 : 6,
			'vuoto' => __('Nessun appuntamento che si ripete.'),
			'sommario' => __('Gli appuntamenti che si ripetono'),
		),
		/* L'archivio non si apre da solo: `primi => 0`. Chi arriva su una pagina
		 * vuole sapere che cosa succede, non che cosa è successo — e su un gruppo
		 * con dieci anni di attività l'archivio sarebbe la parte più pesante della
		 * pagina, scaricata da tutti e letta da pochi. Si carica su richiesta, e da
		 * lì in poi a pezzi come gli altri elenchi. */
		/* Le voci di una COLLEZIONE: il lungomare, il bookcrossing, una categoria.
		 * Non vengono da un indice ma dal contenuto della pagina stessa, e sono
		 * tante (il lungomare ne ha 61): stesso caricamento pigro degli altri. */
		'raccolta' => array(
			'titolo' => __('In questa raccolta'), 'icona' => 'list', 'lista' => 'cards',
			'primi' => 12,
			'vuoto' => __('Questa raccolta è ancora vuota.').' '.meetoo_invito(),
			'sommario' => __('Che cosa c’è dentro'),
		),
		'archivio' => array(
			'titolo' => __('Archivio eventi passati'), 'icona' => 'history', 'lista' => 'cards',
			'primi' => 0, 'manuale' => true,
			'vuoto' => __('Nessun evento passato.'),
			'sommario' => __('Quello che è già successo'),
			'apri' => __('Carica eventi passati'),
		),
	);
}

/**
 * L'AMBITO di una pagina: di chi sono gli eventi che si stanno guardando.
 *
 * La stessa sezione «Prossimi eventi» compare in tre posti — la zona, la pagina di
 * un gruppo, la pagina di un luogo — e ogni volta parla di un insieme diverso. Chi
 * apre la pagina lo dichiara qui una volta, e da quel momento tutte le sezioni
 * pescano dall'elenco giusto, compreso il pezzo chiesto dal browser mentre si
 * scorre: se l'ambito non viaggiasse fin lì, il caricamento pigro continuerebbe
 * con gli eventi di tutti.
 */
function meetoo_ambito($tipo = null, $chiave = null){
	static $ambito = array('tipo' => '', 'chiave' => '');
	if($tipo !== null){
		$ambito = array('tipo' => (string)$tipo, 'chiave' => (string)$chiave);
	}
	return $ambito;
}

/**
 * L'indice degli eventi dell'ambito corrente — i prossimi o l'archivio.
 *
 * Per un ORGANIZZATORE c'è già un indice suo (`by-organizer/<chiave>.json`), fatto
 * apposta perché non si debba leggere tutto per mostrare cinque righe. Per un
 * LUOGO no: si filtra l'indice generale sul suo @id, che con questi numeri costa
 * una lettura e un giro di array.
 */
function meetoo_indice_eventi($archivio = false){
	$ambito = meetoo_ambito();
	$coda = $archivio ? '.archive.json' : '.json';
	/* L'indice suo, anche quando è VUOTO. Se un gruppo non ha eventi la risposta è
	 * «nessuno», non «quelli di tutti»: ripiegare sull'indice generale metteva sulla
	 * pagina di Serenlibrità gli eventi del Club del libro, con il suo nome sopra. */
	if($ambito['tipo'] === 'organizer' and $ambito['chiave'] !== ''){
		return meetoo_indice('by-organizer/'.$ambito['chiave'].$coda);
	}
	if($ambito['tipo'] === 'collection' and $ambito['chiave'] !== ''){
		return meetoo_indice('by-collection/'.$ambito['chiave'].$coda);
	}
	$tutti = meetoo_indice($archivio ? 'events.archive.json' : 'events.json');
	if($ambito['tipo'] === 'place' and $ambito['chiave'] !== ''){
		$chiave = $ambito['chiave'];
		return array_values(array_filter($tutti, function($ev) use ($chiave){
			return (string)($ev['place']['id'] ?? '') === $chiave;
		}));
	}
	return $tutti;
}

/**
 * Questa cosa sta nella zona che si sta guardando?
 *
 * Lo si chiede al suo INDIRIZZO, non a un campo: la mappa mette ogni contenuto
 * sotto la zona che gli compete — `/roma/municipio10/lido-di-ostia/eventi/…` — e
 * quindi l'indirizzo dice già dove sta. È l'unica risposta che non può divergere
 * da quella che dà la mappa, perché è la stessa.
 *
 * Fuori da una pagina di zona non filtra niente: un gruppo, un luogo, una
 * raccolta mostrano le loro cose, che è un'altra domanda.
 */
function meetoo_di_qui($href){
	$ambito = meetoo_ambito();
	if($ambito['tipo'] !== 'zone' or $ambito['chiave'] === '' or $href === ''){
		return true;
	}
	$base = rtrim($ambito['chiave'], '/');
	return ($href === $base or strpos($href, $base.'/') === 0);
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

/**
 * Una voce dell'indice eventi, dal suo @id — prossimi e archivio insieme.
 *
 * Serve alle raccolte che elencano eventi: la collezione dice solo `{"@id": …}`, e
 * il quando e il dove stanno nell'indice. Una tabella sola, costruita alla prima
 * domanda: una raccolta con dentro cinquanta eventi non deve rileggere l'indice
 * cinquanta volte.
 */
function meetoo_evento_indicizzato($id){
	static $per_id = null;
	if($per_id === null){
		$per_id = array();
		foreach(array(meetoo_indice('events.json'), meetoo_indice('events.archive.json')) as $elenco){
			foreach($elenco as $ev){
				$p = trim((string)($ev['path'] ?? ''), '/');
				if($p !== '' and !isset($per_id[$p])){
					$per_id[$p] = $ev;
				}
			}
		}
	}
	return $per_id[trim((string)$id, '/')] ?? null;
}

/**
 * La PARTE N di una raccolta: «Adatto ai bambini» è la parte 0 di «Bambini e
 * famiglie». Una raccolta divisa in parti è una pagina sola con più liste dentro,
 * e il numero è il modo in cui una lista si nomina — anche nel pezzo chiesto dal
 * browser mentre si scorre.
 */
function meetoo_parte($ent, $n){
	$parti = !empty($ent->hasPart) ? $ent->hasPart : array();
	$i = 0;
	foreach($parti as $x){
		if($i === $n){
			return $x;
		}
		$i++;
	}
	return null;
}

/** Le voci di una lista, nude: il ListItem è un involucro, e può non esserci. */
function meetoo_righe($ent){
	$out = array();
	foreach((!empty($ent->itemListElement) ? $ent->itemListElement : array()) as $riga){
		$out[] = !empty($riga->item) ? $riga->item : $riga;
	}
	return $out;
}

/**
 * Questa lista è fatta di EVENTI?
 *
 * Se sì non si mostra come un elenco piatto di titoli: si mostra come si mostrano
 * gli eventi dappertutto — i prossimi, quelli che si ripetono, e il passato solo
 * su richiesta. La differenza la fanno i dati, non un campo apposta: una lista di
 * `events/…` è una lista di appuntamenti, e chi la guarda vuole sapere quando.
 */
function meetoo_lista_di_eventi($ent){
	$righe = meetoo_righe($ent);
	if(!count($righe)){
		return false;   // vuota: una sezione sola che spiega, non tre che tacciono
	}
	foreach($righe as $m){
		if(strpos(meetoo_riferimento_nodo($m), 'events/') !== 0){
			return false;
		}
	}
	return true;
}

/**
 * Gli eventi di una lista, divisi come nel resto del sito.
 *
 * `eventi` i prossimi singoli, `collezioni` quelli che si ripetono, `archivio` il
 * passato. Le date non stanno nella lista — la lista dice solo `{"@id": …}` — ma
 * nell'indice, che è già in memoria.
 */
function meetoo_eventi_lista($ent, $quale){
	$ora = time();
	$scelti = array();
	foreach(meetoo_righe($ent) as $m){
		$id = meetoo_riferimento_nodo($m);
		if(strpos($id, 'events/') !== 0){
			continue;
		}
		$ev = meetoo_evento_indicizzato($id);
		if(!$ev){
			continue;
		}
		$serie = (($ev['kind'] ?? '') === 'series');
		// Un evento è «prossimo» finché non è finito: quello di stasera resta in
		// elenco anche se è cominciato un'ora fa.
		$fine = strtotime((string)(!empty($ev['endDate']) ? $ev['endDate'] : ($ev['startDate'] ?? '')));
		$passato = ($fine and $fine < $ora);
		$suo = $serie ? 'collezioni' : ($passato ? 'archivio' : 'eventi');
		if($suo === $quale){
			$scelti[] = $ev;
		}
	}
	if($quale === 'archivio'){
		usort($scelti, function($a, $b){
			return strcmp((string)($b['startDate'] ?? ''), (string)($a['startDate'] ?? ''));
		});
	} else if($quale === 'collezioni'){
		usort($scelti, function($a, $b){
			return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
		});
	} else {
		usort($scelti, function($a, $b){
			return strcmp((string)($a['startDate'] ?? ''), (string)($b['startDate'] ?? ''));
		});
	}
	$out = array();
	foreach($scelti as $ev){
		$href = meetoo_indirizzo($ev['path'] ?? '');
		if($href === ''){
			continue;
		}
		/* Una collezione non ha una data: non si disegna con il blocchetto del
		 * giorno — sarebbe vuoto — ma come le altre collezioni, dovunque compaiano. */
		if($quale === 'collezioni'){
			$chi = trim((string)($ev['organizer'] ?? ''));
			$sua = meetoo_icona_di($ev['path'] ?? '');
			$out[] = mt_card_tile(array(
				'href' => $href,
				'icon' => $sua ? $sua['name'] : 'collections_bookmark',
				'iconClass' => $sua ? $sua['class'] : '',
				'accent' => true,
				'title' => !empty($ev['name']) ? (string)$ev['name'] : basename((string)($ev['path'] ?? '')),
				'meta' => $chi !== '' ? $chi : __('Collezione di eventi'),
				'metaIcon' => $chi !== '' ? mt_org_icona($ev['organizerType'] ?? '', $chi) : '',
			));
			continue;
		}
		$out[] = mt_card_evento($ev, array('href' => $href));
	}
	return $out;
}

/* ---------- Categorie e Percorsi ----------
 *
 * Le dichiara il contenuto di un LUOGO CONTENITORE — un quartiere, ma anche una
 * città: il Lungotevere attraversa mezza Roma e più di un comune, e non è di un
 * quartiere solo. Stanno in un elenco unico perché sono la stessa cosa vista da
 * due lati: un percorso è una collezione ordinata (il lungomare, da sud a nord),
 * una categoria una collezione senza ordine (i libri), e chi le cerca le cerca
 * insieme.
 *
 * Il codice sta qui e non in `zone.php` perché ora lo usano in due, e due copie
 * della stessa regola divergono al primo ritocco.
 */
function meetoo_percorsi($ent, $dove){
	$out = array();
	foreach((!empty($ent->hasPart) ? $ent->hasPart : array()) as $voce){
		$id = meetoo_riferimento_nodo($voce);
		if($id === ''){
			continue;
		}
		$slug = basename($id);
		/* Una CATEGORIA è generale — «Musica» è musica a Ostia come altrove — e sta
		 * nel catalogo (`categories/…`), dichiarata una volta sola. Qui il luogo dice
		 * soltanto quali categorie ha; nome, sommario e icona si chiedono al catalogo.
		 *
		 * La sua ISTANZA, se esiste, è `places/<dove>/<slug>`: è lì che stanno i suoi
		 * membri, ed è quella la pagina. Se non c'è, la categoria è dichiarata ma non
		 * ancora aperta: si vede spenta.
		 *
		 * Un PERCORSO invece è di questo posto e basta — il lungomare di Ostia non è
		 * il lungomare di nessun altro — e sta dove sta. */
		$categoria = (strpos($id, 'categories/') === 0);
		$istanza = $categoria ? 'places/'.$dove.'/'.$slug : $id;
		$href = meetoo_indirizzo($istanza);
		/* «In preparazione» non vuol dire «la pagina non c'è»: vuol dire «qui non
		 * c'è ancora niente». Una categoria può essere aperta — la sua istanza
		 * esiste, con le sue regole — e non aver ancora raccolto nulla in questo
		 * posto: aprirla costa un clic e restituisce un elenco vuoto, che è il
		 * modo peggiore di dire «non ancora». Lo si dice prima, sul riquadro. */
		if($href !== '' and meetoo_conta_raccolta(meetoo_contenuto($istanza)) === 0){
			$href = '';
		}
		/* Nome e sommario si chiedono al documento: per una categoria al catalogo,
		 * per un percorso a se stesso. Il riferimento nel contenitore può dire solo
		 * `{"@id": …}` — ed è giusto così, perché il nome sta in fondo al
		 * riferimento e tenerne una seconda copia qui vuol dire vederle divergere. */
		$def = meetoo_contenuto($categoria ? $id : $istanza);
		$sua = meetoo_icona_di($istanza) ?: ($categoria ? meetoo_icona_di($id) : null);
		$titolo = (isset($voce->name) ? trim((string)$voce->name) : '') ?: (string)($def['name'] ?? '');
		$nota = (isset($voce->description) ? trim((string)$voce->description) : '') ?: (string)($def['description'] ?? '');
		/* Una categoria d'ETÀ lo dichiara nel catalogo: `"meetoo:ageRange": "0-13"`.
		 * È un dato e non un elenco di slug nel template perché serve a due cose in
		 * una — dice quali categorie stanno insieme nella loro sezione, e in che
		 * ordine (per età, non per alfabeto). Aggiungerne una domani è scrivere una
		 * riga nel catalogo, non ritoccare il tema. */
		$fascia = is_array($def) ? meetoo_fascia((string)($def['meetoo:ageRange'] ?? '')) : null;
		$out[] = array(
			'href' => $href,
			'icona' => $sua ? $sua['name'] : 'route',
			'classe' => $sua ? $sua['class'] : '',
			'titolo' => $titolo !== '' ? $titolo : ucfirst(str_replace('-', ' ', $slug)),
			'nota' => $nota,
			'eta' => $fascia !== null ? $fascia[0] : null,
		);
	}
	/* PRIMA QUELLO CHE SI PUÒ APRIRE. Le categorie dichiarate ma non ancora aperte
	 * restano — dicono che cosa sta arrivando — ma in fondo: chi guarda cerca dove
	 * andare, e trovarsi davanti tre riquadri spenti prima del primo che funziona
	 * fa sembrare vuoto un posto che vuoto non è. L'ordine dichiarato si conserva
	 * dentro i due gruppi: `usort` in PHP è stabile. */
	usort($out, function($a, $b){
		return (int)($a['href'] === '') <=> (int)($b['href'] === '');
	});
	return $out;
}

/**
 * Quante voci ha una raccolta: le sue, e quelle delle sue parti.
 *
 * Una raccolta divisa in parti — «Bambini e famiglie» con dentro le quattro fasce
 * della scuola — non ha voci proprie: le hanno le parti. Contarle solo in cima
 * direbbe «vuota» di una pagina piena.
 */
function meetoo_conta_raccolta($doc){
	if(!is_array($doc)){
		return 0;
	}
	$n = count((array)($doc['itemListElement'] ?? array()));
	foreach((array)($doc['hasPart'] ?? array()) as $parte){
		if(is_array($parte)){
			$n += count((array)($parte['itemListElement'] ?? array()));
		}
	}
	return $n;
}

/**
 * Le categorie e i percorsi di un posto, disegnati.
 *
 * DUE SEZIONI, non una. L'età non è un tema: «Musica» e «Terza età» rispondono a
 * due domande diverse — che cosa mi interessa, e per chi è — e mescolarle obbliga
 * chi cerca «qualcosa per mio figlio» a leggere tutta la griglia. Le fasce vanno
 * in fondo, per età crescente, che è l'unico ordine che hanno.
 */
function meetoo_sezione_percorsi($percorsi){
	$temi = array();
	$fasce = array();
	foreach($percorsi as $p){
		if(($p['eta'] ?? null) === null){
			$temi[] = $p;
		} else {
			$fasce[] = $p;
		}
	}
	// Per età crescente, aperte o no: qui l'ordine è l'anagrafe, non la disponibilità.
	usort($fasce, function($a, $b){
		return $a['eta'] <=> $b['eta'];
	});
	meetoo_griglia_percorsi('categorie', 'explore', __('Categorie e Percorsi'), $temi);
	meetoo_griglia_percorsi('fasce-eta', 'diversity_1', __('Fasce d’età'), $fasce);
}

/** Una griglia di riquadri. Niente da mostrare, niente sezione. */
function meetoo_griglia_percorsi($id, $icona, $titolo, $percorsi){
	if(!count($percorsi)){
		return;
	}
?>
				<section id="<?php echo mt_esc($id); ?>" class="mt-sezione">
					<h2 class="sec-head"><?php echo mt_icona($icona); ?><?php echo mt_esc($titolo); ?></h2>
					<div class="grid">
<?php foreach($percorsi as $p){
	if($p['href'] !== ''){
		echo mt_card_tile(array(
			'href' => $p['href'],
			'icon' => $p['icona'],
			'iconClass' => $p['classe'],
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
							<div class="card-icon"><?php echo mt_icona($p['icona'], $p['classe']); ?></div>
							<div class="card-body">
								<h3 class="card-title"><?php echo mt_esc($p['titolo']); ?> <span class="mt-etichetta"><?php _e('In preparazione'); ?></span></h3>
								<div class="card-meta"><span><?php echo mt_esc($p['nota']); ?></span></div>
							</div>
						</div>
<?php } ?>
					</div>
				</section>
<?php
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

	if($quale === 'raccolta' or strpos($quale, 'raccolta:') === 0){
		global $ws_content;
		$ent = !empty($ws_content->mainEntity) ? $ws_content->mainEntity : $ws_content;
		/* `raccolta:2` = la terza SEZIONE di questa raccolta. Una categoria può essere
		 * divisa in parti — «Adatto ai bambini», «Progettato per i bambini» — e ogni
		 * parte è una lista con la sua regola: qui si sceglie quale disegnare. Il
		 * numero viaggia anche nel pezzo chiesto mentre si scorre, se no il
		 * caricamento pigro continuerebbe con le voci di un'altra sezione. */
		$sotto = '';
		if(strpos($quale, 'raccolta:') === 0){
			/* `raccolta:1:archivio` = l'archivio della seconda parte. Il pezzo dopo il
			 * numero c'è solo quando la parte è fatta di eventi, e allora la parte non
			 * è una lista ma tre. */
			$pezzi = explode(':', substr($quale, strlen('raccolta:')));
			$parte = meetoo_parte($ent, (int)$pezzi[0]);
			if($parte === null){
				return $fatte[$quale] = $out;
			}
			$ent = $parte;
			$sotto = (string)($pezzi[1] ?? '');
		}
		if($sotto !== ''){
			return $fatte[$quale] = meetoo_eventi_lista($ent, $sotto);
		}
		$voci = !empty($ent->itemListElement) ? $ent->itemListElement : array();
		foreach($voci as $riga){
			// Un ListItem porta la cosa dentro `item`; si accetta anche la cosa nuda,
			// perché una lista scritta a mano può saltare l'involucro.
			$m = !empty($riga->item) ? $riga->item : $riga;
			$id = meetoo_riferimento_nodo($m);
			/* Il nome può non esserci: una collezione di riferimenti — «Libri e
			 * letture» — dice solo a che cosa punta, e il nome sta nel documento in
			 * fondo al riferimento. La mappa lo sa già, e chiederglielo evita di
			 * tenerne una seconda copia qui che prima o poi diverge. */
			$nome = trim((string)($m->name ?? ''));
			if($nome === '' and $id !== ''){
				$nome = meetoo_titolo_contenuto($id);
			}
			if($nome === ''){
				continue;
			}
			$note = array();
			$tipo = trim((string)($m->additionalType ?? ''));
			if($tipo !== ''){
				$note[] = $tipo;
			}
			$via = trim((string)($m->address->streetAddress ?? ''));
			if($via !== ''){
				$note[] = $via;
			}
			/* Se la voce è un EVENTO, si disegna come un evento: il blocchetto della
			 * data, l'ora, chi organizza, dove. Una categoria di eventi con dentro
			 * righe che dicono solo il nome è un elenco di titoli, e a chi guarda
			 * serve sapere QUANDO. I dati stanno nell'indice, che è già in memoria. */
			if(strpos($id, 'events/') === 0){
				$riga = meetoo_evento_indicizzato($id);
				if($riga){
					$out[] = mt_card_evento($riga, array('href' => meetoo_indirizzo($id)));
					continue;
				}
			}
			/* Prima si chiede al contenuto: se ha dichiarato la sua icona, quella è.
			 * Solo se non l'ha fatto si indovina dal tipo — un evento, un gruppo, un
			 * posto — che è un ripiego onesto ma resta un ripiego. */
			$sua = $id !== '' ? meetoo_icona_di($id) : null;
			if($sua){
				$icona = $sua['name'];
			} else if(strpos($id, 'events/') === 0){
				$icona = 'collections_bookmark';
			} else if(strpos($id, 'organizations/') === 0){
				$icona = mt_org_icona((string)($m->{'@type'} ?? ''), $nome);
			} else {
				$icona = meetoo_icona_luogo(array((string)($m->{'@type'} ?? ''), $tipo, $nome));
			}
			$out[] = mt_card_tile(array(
				'href' => $id !== '' ? meetoo_indirizzo($id) : '',
				'icon' => $icona,
				'iconClass' => $sua ? $sua['class'] : '',
				'title' => $nome,
				'meta' => implode(' · ', $note),
			));
		}
		return $fatte[$quale] = $out;
	}

	if($quale === 'archivio'){
		$passati = meetoo_indice_eventi(true);
		usort($passati, function($a, $b){
			return strcmp((string)($b['startDate'] ?? ''), (string)($a['startDate'] ?? ''));
		});
		foreach($passati as $ev){
			$href = meetoo_indirizzo($ev['path'] ?? '');
			if($href === '' or !meetoo_di_qui($href)){ continue; }
			$out[] = mt_card_evento($ev, array('href' => $href));
		}
		return $fatte[$quale] = $out;
	}

	if($quale === 'eventi' or $quale === 'collezioni'){
		$ora = time();
		$scelti = array();
		foreach(meetoo_indice_eventi() as $ev){
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
				if($href === '' or !meetoo_di_qui($href)){ continue; }
				$out[] = mt_card_evento($ev, array('href' => $href));
			}
		} else {
			usort($scelti, function($a, $b){
				return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
			});
			foreach($scelti as $c){
				$href = meetoo_indirizzo($c['path'] ?? '');
				if($href === '' or !meetoo_di_qui($href)){ continue; }
				$chi = trim((string)($c['organizer'] ?? ''));
					// L'icona la dichiara la collezione; `collections_bookmark` è il ripiego
				// per quelle che ancora non l'hanno detta.
				$sua = meetoo_icona_di($c['path'] ?? '');
				$out[] = mt_card_tile(array(
					'href' => $href,
					'icon' => $sua ? $sua['name'] : 'collections_bookmark',
					'iconClass' => $sua ? $sua['class'] : '',
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
			if($href === '' or !meetoo_di_qui($href)){ continue; }
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
			if($href === '' or !meetoo_di_qui($href)){ continue; }
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
function meetoo_altri($quale, $da, $totale, $manuale = false, $etichetta = ''){
	if($da >= $totale){
		return '';
	}
	$restanti = $totale - $da;
	$testo = $etichetta !== '' ? $etichetta : sprintf(__('Mostra altri %d'), $restanti);
	return '<div class="mt-altri" data-parte="'.mt_esc($quale).'" data-da="'.(int)$da.'" data-totale="'.(int)$totale.'"'
		.($manuale ? ' data-manuale="1"' : '').'>'
		.'<a class="card-act'.($manuale ? ' primary' : '').'" rel="nofollow" href="?tutti='.urlencode($quale).'#'.mt_esc($quale).'">'
		.mt_icona($manuale ? 'history' : 'expand_more').'<span>'.mt_esc($testo).'</span>'
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
	// `raccolta:N` non sta nell'elenco delle sezioni — ce n'è una per ogni parte di
	// una raccolta — ma la forma è chiusa: `raccolta:` più un numero, e niente altro.
	$sezione = $parte;
	if(preg_match('/^raccolta:\d+(?::(eventi|collezioni|archivio))?$/', $parte, $m)){
		// Una sottosezione tiene il nome della sezione di cui ha la forma: l'archivio
		// di una parte si carica su richiesta come l'archivio di un gruppo.
		$sezione = ($m[1] ?? '') !== '' ? $m[1] : 'raccolta';
	}
	if($sezione === '' or !isset($sezioni[$sezione])){
		return;
	}
	$voci = meetoo_voci($parte);
	$da = max(0, (int)($_GET['da'] ?? 0));
	$blocco = array_slice($voci, $da, MEETOO_PASSO);
	echo implode("\n", $blocco);
	echo meetoo_altri($parte, $da + count($blocco), count($voci));
	exit;
}

/**
 * Una sezione a elenco, con il suo conteggio e la sua coda pigra.
 *
 * `$titoloLink` rende cliccabile il TITOLO; `$vediTutti` mette invece un
 * collegamento sotto l'elenco. Sono due cose diverse: un titolo di sezione dice
 * che cosa viene dopo, e se porta altrove chi legge non sa più se sta guardando
 * l'elenco o l'annuncio di un altro elenco.
 */
function meetoo_sezione($quale, $cfg, $tutto, $titoloLink = '', $vediTutti = ''){
	/* Una sezione che non esiste non stampa quattro avvisi PHP in mezzo alla pagina.
	 * Succede quando i file del tema arrivano sul server in momenti diversi — un
	 * template nuovo che chiede una sezione che il file delle sezioni, più vecchio,
	 * non conosce ancora. Il rimedio vero è caricarli insieme; questo serve perché
	 * nel frattempo la pagina resti leggibile invece di riempirsi di avvisi. */
	if(!is_array($cfg)){
		$tutte = meetoo_sezioni();
		if(!isset($tutte[$quale])){
			return;
		}
		$cfg = $tutte[$quale];
	}
	$cfg += array('titolo' => $quale, 'icona' => 'list', 'lista' => 'cards', 'primi' => 12, 'vuoto' => __('Niente da mostrare.'));
	/* Il livello del titolo. Una sezione è normalmente un `h2`; dentro una parte di
	 * raccolta è un `h3`, perché lì il titolo di rango due è il nome della parte —
	 * e una gerarchia saltata la sente chi legge con lo schermo spento. */
	$tag = 'h'.max(2, min(4, (int)($cfg['livello'] ?? 2)));
	$manuale = !empty($cfg['manuale']);
	$voci = meetoo_voci($quale);
	$totale = count($voci);
	$quante = ($tutto === $quale) ? $totale : min($totale, (int)$cfg['primi']);
?>
				<section id="<?php echo mt_esc($quale); ?>" class="mt-sezione">
					<<?php echo $tag; ?> class="sec-head"><?php echo mt_icona($cfg['icona']); ?><?php
						if($titoloLink !== ''){
							echo '<a href="'.mt_esc($titoloLink).'">'.mt_esc($cfg['titolo']).'</a>';
						} else {
							echo mt_esc($cfg['titolo']);
						}
					?><span class="count"><?php echo $totale ? (int)$totale : ''; ?></span></<?php echo $tag; ?>>
<?php if(!$totale){ ?>
					<div class="empty"><?php echo mt_esc($cfg['vuoto']); ?></div>
<?php } else { ?>
					<div class="<?php echo mt_esc($cfg['lista']); ?>" data-lista="<?php echo mt_esc($quale); ?>">
<?php
	echo implode("\n", array_slice($voci, 0, $quante));
	echo meetoo_altri($quale, $quante, $totale, $manuale, $manuale ? (string)($cfg['apri'] ?? '') : '');
?>
					</div>
<?php $altrove = $vediTutti !== '' ? $vediTutti : $titoloLink; if($quante < $totale and $altrove !== ''){ ?>
					<p class="mt-nota"><a href="<?php echo mt_esc($altrove); ?>"><?php printf(__('Vedi tutti (%d)'), $totale); ?></a></p>
<?php } ?>
<?php } ?>
				</section>
<?php
}

}// function_exists
?>
