<?php
/**
 * La pagina di un evento: tutto quello che l'evento dice di sé.
 *
 * Prima era un rimando alla pagina generica, e di un evento si leggeva il titolo
 * e poco altro: quando, dove, chi organizza, quanto costa, per chi è — tutto
 * scritto nel suo file — non arrivava a chi la pagina la apriva. Le vecchie
 * pagine in JavaScript quelle cose le mostravano; qui le scrive il SERVER, che è
 * la differenza che conta: un evento è la cosa che la gente si manda per
 * messaggio, e quello che si vede quando arriva dev'esserci già.
 *
 * L'ordine è quello delle domande: che cos'è, quando e dove, che cosa c'è da
 * fare (interessarsi, dire che ci si va), di che si tratta, com'è fatto dentro,
 * e in fondo da dove viene.
 */
global $ws_content, $ws_query, $rewrite_rule;

include_template('template-parts/carte');
include_template('template-parts/elenchi');

$e = !empty($ws_content->mainEntity) ? $ws_content->mainEntity : $ws_content;
$titolo = !empty($e->name) ? (string)$e->name : (string)($rewrite_rule->title ?? '');

/** Il valore di un campo, '' se non c'è: SimpleXML non ha `??` che tenga. */
function mt_ev($n, $campo){
	return isset($n->$campo) ? trim((string)$n->$campo) : '';
}

/**
 * Quando, scritto come lo direbbe una persona.
 *
 * «sabato 31 agosto 2026, 19:00–21:00» se comincia e finisce lo stesso giorno,
 * altrimenti i due giorni per esteso. I nomi in italiano si scrivono qui e non
 * si chiedono a `strftime`: quella funzione dipende dalla lingua installata sul
 * server, e un server che non ce l'ha risponde in inglese senza dirlo.
 */
function mt_quando($dal, $al, $fuso = ''){
	$g = array('domenica','lunedì','martedì','mercoledì','giovedì','venerdì','sabato');
	$m = array('gennaio','febbraio','marzo','aprile','maggio','giugno','luglio','agosto','settembre','ottobre','novembre','dicembre');
	/* Nel fuso scritto nella data, non in quello del server: le sei di sera a
	 * Ostia sono le quattro a Greenwich, e il server sta a Greenwich. */
	$i = meetoo_istante($dal, $fuso);
	if(!$i){
		return '';
	}
	$f = $al !== '' ? meetoo_istante($al, $fuso) : null;
	$giorno = function($d) use ($g, $m){
		return $g[(int)$d->format('w')].' '.(int)$d->format('j').' '.$m[(int)$d->format('n') - 1].' '.$d->format('Y');
	};
	if($f and $f->format('Y-m-d') !== $i->format('Y-m-d')){
		return $giorno($i).', '.$i->format('H:i').' — '.$giorno($f).', '.$f->format('H:i');
	}
	return $giorno($i).', '.$i->format('H:i').($f ? '–'.$f->format('H:i') : '');
}

/** In presenza, online, o tutte e due: lo dice `eventAttendanceMode`. */
function mt_modalita($v){
	$s = strtolower((string)$v);
	if(strpos($s, 'mixed') !== false){ return array('devices', __('In presenza e online')); }
	if(strpos($s, 'online') !== false){ return array('videocam', __('Solo online')); }
	if(strpos($s, 'offline') !== false){ return array('groups', __('Solo in presenza')); }
	return null;
}

/**
 * Il tipo schema.org di un nodo.
 *
 * Nel JSON è `@type`; nell'XML che il CMS legge diventa l'ATTRIBUTO `xsi:type`
 * — `<mainEntity xsi:type="LiteraryEvent">` — perché un nome di elemento non può
 * contenere la chiocciola. Chiederlo come `$nodo->{'@type'}` ritorna sempre
 * vuoto, e il tipo sembra non esserci: è successo, e per questo c'è questa
 * funzione invece di quella riga.
 */
function mt_xsi_tipo($nodo){
	if(!is_object($nodo)){
		return '';
	}
	$a = $nodo->attributes('http://www.w3.org/2001/XMLSchema-instance');
	return ($a !== null and isset($a['type'])) ? trim((string)$a['type']) : '';
}

/** I riferimenti di un campo (organizer, sameAs…) sempre come elenco. */
function mt_lista($n, $campo){
	if(!isset($n->$campo)){
		return array();
	}
	$out = array();
	foreach($n->$campo as $x){
		$out[] = $x;
	}
	return $out;
}

// Il percorso del contenuto: la cartella in cui l'evento tiene le sue cose (le
// immagini) ed è anche la chiave con cui il server tiene «mi interessa», «mi
// piace» e le iscrizioni.
$rel = preg_replace('#^[^/]+/[^/]+/#', '', trim((string)($ws_query['content'] ?? ''), '/'));

$stato = mt_ev($e, 'eventStatus');
$dal = mt_ev($e, 'startDate');
$al = mt_ev($e, 'endDate');
// Il fuso lo dichiara l'evento: serve alle date che lo scarto non ce l'hanno.
$fuso = trim((string)meetoo_campo_meetoo($e, 'timezone'));
$quando = mt_quando($dal, $al, $fuso);

/**
 * L'evento come lo capisce un calendario.
 *
 * Un appuntamento serve a poco se resta su una pagina: il gesto vero è metterlo
 * nel proprio calendario, e da lì in poi ci pensa il telefono a ricordarselo.
 * Google ha un indirizzo che fa da modulo precompilato; tutti gli altri —
 * Apple, Outlook, Thunderbird, il telefono — parlano `.ics`, che è un formato di
 * testo e si scrive qui in dieci righe.
 *
 * Le ore vanno in UTC con la Z finale: è l'unico modo perché l'appuntamento
 * cada all'ora giusta anche per chi lo apre da un altro fuso.
 */
function mt_utc($valore, $fuso){
	$d = meetoo_istante($valore, $fuso);
	return $d ? $d->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z') : '';
}
/** Il testo di un campo, senza marcatura e senza righe vuote: un calendario non le legge. */
function mt_piano($html){
	$t = trim(html_entity_decode(strip_tags(preg_replace('#</(p|div|li|h[1-6])>#i', "$0\n", (string)$html)), ENT_QUOTES, 'UTF-8'));
	return preg_replace('/\n{3,}/', "\n\n", $t);
}

/* Il luogo: il nome sta nell'evento, la località nel documento del luogo — e
 * l'indirizzo della sua pagina lo sa la mappa. Un evento non ripete quello che
 * il luogo dice già di sé. */
$luogoId = isset($e->location) ? meetoo_riferimento_nodo($e->location) : '';
$luogoNome = isset($e->location) ? mt_ev($e->location, 'name') : '';
$luogoDoc = $luogoId !== '' ? meetoo_contenuto($luogoId) : null;
if($luogoDoc){
	$luogoNome = mt_luogo_testo($luogoDoc) ?: $luogoNome;
}
$luogoHref = $luogoId !== '' ? meetoo_indirizzo($luogoId) : '';

$ics_inizio = mt_utc($dal, $fuso);
$ics_fine = mt_utc($al !== '' ? $al : $dal, $fuso);
$ics_dove = trim($luogoNome);
$ics_testo = mt_piano(isset($e->abstract) ? $e->abstract->innerHTML() : mt_ev($e, 'description'));
$ics_url = ws_href(trim((string)$ws_query['wspath'], '/'));

/* IL FILE .ICS, chiesto alla stessa pagina.
 *
 * `?ics` non è un'altra pagina: è la stessa cosa detta in un'altra lingua, e
 * risponde prima che si cominci a scrivere l'HTML — come già fa il pezzo di
 * elenco chiesto mentre si scorre. Un indirizzo in meno da inventare, e nessun
 * posto dove i due possano divergere. */
if(isset($_GET['ics']) and $ics_inizio !== ''){
	/* Nel formato iCalendar la barra rovescia, la virgola e il punto e virgola
	 * vanno protetti, e un a capo si scrive come i due caratteri `\` e `n` — non
	 * come un a capo vero, che spezzerebbe il file in due righe e lo renderebbe
	 * illeggibile a chi lo apre. */
	$esc = function($t){
		return str_replace(
			array('\\', "\r\n", "\n", ',', ';'),
			array('\\\\', '\\n', '\\n', '\\,', '\\;'),
			(string)$t
		);
	};
	$righe = array(
		'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Meetoo//IT', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH',
		'BEGIN:VEVENT',
		'UID:'.$esc($rel).'@meetoo',
		'DTSTAMP:'.gmdate('Ymd\THis\Z'),
		'DTSTART:'.$ics_inizio,
		'DTEND:'.$ics_fine,
		'SUMMARY:'.$esc($titolo),
	);
	if($ics_dove !== ''){
		$righe[] = 'LOCATION:'.$esc($ics_dove);
	}
	if($ics_testo !== ''){
		$righe[] = 'DESCRIPTION:'.$esc($ics_testo);
	}
	$righe[] = 'URL:'.$esc($ics_url);
	$righe[] = 'END:VEVENT';
	$righe[] = 'END:VCALENDAR';
	header('Content-Type: text/calendar; charset=utf-8');
	header('Content-Disposition: attachment; filename="'.basename($rel).'.ics"');
	echo implode("\r\n", $righe)."\r\n";
	exit;
}

/* L'indirizzo del modulo precompilato di Google Calendar. Le due date separate
 * da una barra, in UTC, e il resto come testo. */
$google_cal = $ics_inizio === '' ? '' : 'https://calendar.google.com/calendar/render?'.http_build_query(array(
	'action' => 'TEMPLATE',
	'text' => $titolo,
	'dates' => $ics_inizio.'/'.$ics_fine,
	'details' => trim($ics_testo."\n\n".$ics_url),
	'location' => $ics_dove,
));

// Prezzo: «Ingresso libero» quando è dichiarato gratuito o costa zero.
$gratis = in_array(strtolower(mt_ev($e, 'isAccessibleForFree')), array('1','true'), true);
$prezzo = isset($e->offers) ? mt_ev($e->offers, 'price') : '';
$valuta = isset($e->offers) ? (mt_ev($e->offers, 'priceCurrency') ?: 'EUR') : 'EUR';
$offerta = ($gratis or $prezzo === '0' or $prezzo === '0.00') ? __('Ingresso libero')
	: ($prezzo !== '' ? $prezzo.' '.$valuta : '');

// Le capienze si mostrano solo se l'evento le conta.
$posti = mt_ev($e, 'maximumAttendeeCapacity');
$rimasti = mt_ev($e, 'remainingAttendeeCapacity');

$eta = mt_ev($e, 'typicalAgeRange');
$modalita = mt_modalita(mt_ev($e, 'eventAttendanceMode'));
/* La serie di cui l'evento fa parte. Nel file è scritta come una stringa nuda
 * (`"superEvent": "events/…"`), non come un oggetto con l'@id: il riferimento si
 * chiede prima nel modo consueto e poi si legge il testo, che è quello che c'è. */
$serieId = isset($e->superEvent) ? (meetoo_riferimento_nodo($e->superEvent) ?: trim((string)$e->superEvent)) : '';
$serieNome = $serieId !== '' ? meetoo_titolo_contenuto($serieId) : '';
$serieHref = $serieId !== '' ? meetoo_indirizzo($serieId) : '';

/* Il programma: i sotto-eventi scritti dentro l'evento (quelli senza un @id
 * proprio). Quelli CON un @id sono eventi a sé e hanno una pagina loro. */
$programma = array();
foreach(mt_lista($e, 'subEvent') as $s){
	if(meetoo_riferimento_nodo($s) === '' and (mt_ev($s, 'name') !== '' or mt_ev($s, 'startDate') !== '')){
		$programma[] = $s;
	}
}

$serie = (stripos((string)($rewrite_rule->type ?? ''), 'eventseries') !== false);

/* PASSATO O NO: cambia che cosa si puo' fare. A un appuntamento di ieri non ci
 * si iscrive e non lo si mette in agenda; lo si puo' ancora ricordare, e
 * raccontare com'e' andato. */
$fine_ev = meetoo_istante($al !== '' ? $al : $dal, $fuso);
$passato = $fine_ev ? $fine_ev->getTimestamp() < time() : false;

$organizzatori = mt_lista($e, 'organizer');

/* CHE COSA È, detto in italiano.
 *
 * `@type` porta i tipi di schema.org — `LiteraryEvent`, `MusicEvent` — che sono
 * parole per le macchine: qui si mostrano tradotte. `Event` e `EventSeries` si
 * saltano: dicono soltanto «è un evento», che chi è su questa pagina lo sa già.
 *
 * `additionalType` è la parola che ci mette la redazione («Leggere insieme»), e
 * sta accanto: le due cose non si escludono, una classifica e l'altra racconta.
 */
function mt_tipo_evento($t){
	$nomi = array(
		'LiteraryEvent' => 'Letteratura', 'MusicEvent' => 'Musica', 'TheaterEvent' => 'Teatro',
		'ScreeningEvent' => 'Proiezione', 'ExhibitionEvent' => 'Mostra', 'Festival' => 'Festival',
		'FoodEvent' => 'Cibo', 'SportsEvent' => 'Sport', 'ChildrensEvent' => 'Per bambini',
		'EducationEvent' => 'Formazione', 'SocialEvent' => 'Incontro', 'BusinessEvent' => 'Lavoro',
		'ComedyEvent' => 'Comicità', 'DanceEvent' => 'Danza', 'CourseInstance' => 'Corso',
		'Hackathon' => 'Hackathon', 'SaleEvent' => 'Vendita', 'VisualArtsEvent' => 'Arti visive',
		'PublicationEvent' => 'Pubblicazione', 'DeliveryEvent' => 'Consegna',
	);
	$k = trim((string)$t);
	if($k === '' or $k === 'Event' or $k === 'EventSeries'){
		return '';
	}
	// Un tipo che non conosciamo si mostra com'è, senza la coda «Event»: meglio
	// una parola inglese che nessuna parola.
	return $nomi[$k] ?? preg_replace('/Event$/', '', $k);
}

/**
 * Le parole che un tipo di schema.org DICE GIÀ.
 *
 * «Letteratura» e «Libri e letture» sono la stessa cosa detta due volte, e due
 * etichette identiche accanto non aggiungono niente: si tiene quella della
 * redazione, che è più precisa e più nostra, e si butta il tipo.
 *
 * Il dizionario è corto apposta. Ci sono solo le corrispondenze fra i tipi che
 * usiamo e i nomi delle categorie che esistono nel catalogo: una voce in più,
 * indovinata, nasconderebbe un'informazione vera — che è il danno peggiore di
 * tutti, perché non si vede. Oggi non scarta niente (l'unico tipo in uso è
 * `LiteraryEvent` e gli additionalType sono «Leggere insieme» e «BookCrossing»,
 * che sono altre cose): è una guardia per quando il vocabolario crescerà.
 */
function mt_sinonimi_tipo($tipo){
	$mappa = array(
		'LiteraryEvent' => array('letteratura', 'libri e letture', 'letture', 'lettura'),
		'MusicEvent' => array('musica', 'concerto', 'concerti'),
		'TheaterEvent' => array('teatro', 'spettacolo teatrale'),
		'ScreeningEvent' => array('cinema', 'proiezione', 'proiezioni'),
		'ChildrensEvent' => array('per bambini', 'bambini e famiglie', 'bambini'),
		'BusinessEvent' => array('lavoro', 'lavoro e opportunita', 'business'),
		'Festival' => array('festival', 'sagre, feste, palii', 'sagra', 'festa'),
		'FoodEvent' => array('cibo', 'gastronomia'),
		'SportsEvent' => array('sport'),
		'EducationEvent' => array('formazione', 'corso', 'corsi'),
		'ExhibitionEvent' => array('mostra', 'mostre'),
		'VisualArtsEvent' => array('arti visive', 'arte'),
	);
	return $mappa[trim((string)$tipo)] ?? array();
}

/* QUALI TIPI MOSTRARE. Si tiene la prima etichetta e si scartano i doppioni —
 * confronto senza maiuscole e senza accenti — e in più si butta il tipo quando
 * la parola della redazione dice già la stessa cosa. */
$tipi = array();
$visti = array();
$normale = function($t){
	$x = mb_strtolower(trim((string)$t), 'UTF-8');
	return strtr($x, array('à'=>'a','è'=>'e','é'=>'e','ì'=>'i','ò'=>'o','ù'=>'u'));
};
/* Prima la parola della redazione, poi il tipo: l'ordine conta, perché a
 * scartare è chi arriva dopo, e fra le due deve sopravvivere la più precisa. */
$cat = mt_ev($e, 'additionalType');
if($cat !== ''){
	$visti[$normale($cat)] = true;
	$tipi[] = $cat;
}
$tipoSchema = mt_xsi_tipo($e);
$nome = mt_tipo_evento($tipoSchema);
if($nome !== '' and !isset($visti[$normale($nome)])){
	// Il tipo si tace anche quando una parola già scritta dice la stessa cosa.
	$ridondante = false;
	foreach(mt_sinonimi_tipo($tipoSchema) as $sinonimo){
		if(isset($visti[$normale($sinonimo)])){
			$ridondante = true;
			break;
		}
	}
	if(!$ridondante){
		$visti[$normale($nome)] = true;
		array_unshift($tipi, $nome);
	}
}
$voto = isset($e->aggregateRating) ? mt_ev($e->aggregateRating, 'ratingValue') : '';
$voto_max = $voto !== '' ? (mt_ev($e->aggregateRating, 'bestRating') ?: '5') : '';
$voto_n = $voto !== '' ? mt_ev($e->aggregateRating, 'ratingCount') : '';

/* Il vestito e il comportamento di questa pagina si caricano solo qui: sono la
 * pagina di un evento, e su una zona sarebbero peso scaricato per niente. */
$ws_theme_url = ws_theme_url();
$GLOBALS['ws_scripts']['bodyend']['meetoo_evento'] = '<script defer="defer" src="'.$ws_theme_url.'js/evento.js"></script>';

include_template('template-parts/header');
?>
			<article<?php echo ws_html_attributes('main-content', array('class' => array('mt-pagina', 'mt-evento-pagina'))); ?>>

				<?php /* LA TESTATA: che cosa è, quando e dove, e da dove viene.
				          A sinistra il quando e il dove — le due domande per cui si apre la
				          pagina di un evento, e per questo in evidenza. A destra i rimandi
				          ad altro: la collezione di cui fa parte, chi lo organizza, il voto
				          di chi c'è stato. La copertina viene dopo: è bella, ma non è
				          l'informazione. */ ?>
				<header class="mt-evento-testa">
					<h1 class="mt-h1"><?php echo mt_esc($titolo); ?> <?php echo mt_badge_stato($stato); ?></h1>

					<div class="mt-testa-righe">
						<div class="mt-testa-sx">
<?php if($quando !== ''){ ?>
							<p class="mt-quando"><?php echo mt_icona('event'); ?><span><?php echo mt_esc($quando); ?></span></p>
<?php } ?>
<?php if($luogoNome !== ''){ ?>
							<p class="mt-dove"><?php echo mt_icona('location_on'); ?><span><?php
								echo $luogoHref !== ''
									? '<a href="'.mt_esc($luogoHref).'">'.mt_esc($luogoNome).'</a>'
									: mt_esc($luogoNome);
							?></span></p>
<?php } ?>
<?php if(count($tipi)){ ?>
							<p class="mt-tipi">
<?php foreach($tipi as $t){ ?><span class="mt-chip mt-chip-tipo"><?php echo mt_esc($t); ?></span><?php } ?>
							</p>
<?php } ?>
						</div>

						<div class="mt-testa-dx">
<?php if($serieHref !== ''){ ?>
							<p class="mt-nota"><?php echo mt_icona('collections_bookmark'); ?>
								<?php _e('Fa parte di'); ?> <a href="<?php echo mt_esc($serieHref); ?>"><?php echo mt_esc($serieNome !== '' ? $serieNome : basename($serieId)); ?></a>
							</p>
<?php } ?>
<?php if(count($organizzatori)){ ?>
							<div class="mt-organizza"><span class="mt-organizza-testa"><?php _e('Organizzato da'); ?></span>
<?php foreach($organizzatori as $o){
	$id = meetoo_riferimento_nodo($o);
	$nome = mt_ev($o, 'name') ?: ($id !== '' ? meetoo_titolo_contenuto($id) : '');
	if($nome === ''){ continue; }
	$href = $id !== '' ? meetoo_indirizzo($id) : '';
	$dentro = mt_icona(mt_org_icona(mt_xsi_tipo($o), $nome)).mt_esc($nome);
	echo $href !== ''
		? '<a class="mt-chip" href="'.mt_esc($href).'">'.$dentro.'</a>'
		: '<span class="mt-chip">'.$dentro.'</span>';
} ?>
							</div>
<?php } ?>
<?php if($voto !== ''){ ?>
							<p class="mt-voto"><?php echo mt_icona('star'); ?><span><?php
								echo mt_esc($voto.' / '.$voto_max);
								echo $voto_n !== '' ? ' '.mt_esc(sprintf(__('(%s valutazioni)'), $voto_n)) : '';
							?></span></p>
<?php } ?>
						</div>
					</div>

<?php $cover = meetoo_media($rel, mt_ev($e, 'image') ?: mt_ev($e, 'logo')); if($cover !== ''){ ?>
					<figure class="mt-copertina">
						<img src="<?php echo mt_esc($cover); ?>" alt="" loading="lazy" decoding="async" />
<?php $credito = trim((string)meetoo_campo_meetoo($e, 'imageCredit')); if($credito !== ''){ ?>
						<figcaption class="mt-credito"><?php echo mt_esc($credito); ?></figcaption>
<?php } ?>
					</figure>
<?php } ?>
				</header>

				<?php /* Da qui in giù, due colonne su schermo largo: a sinistra quello che
				          c'è da leggere, a destra quello che c'è da fare. Su schermo stretto
				          diventa una colonna sola, e l'ordine del documento è già quello
				          giusto — l'abstract, i dati, le azioni, poi il programma. */ ?>
				<div class="mt-evento-griglia">
					<div class="mt-principale">

<?php $testo = meetoo_testo_visibile($e); if($testo !== ''){ ?>
					<div class="mt-corpo mt-abstract"><?php ws_echo($testo); ?></div>
<?php } else if(mt_ev($e, 'description') !== ''){ ?>
					<p class="mt-abstract mt-sommario"><?php echo mt_esc(mt_ev($e, 'description')); ?></p>
<?php } ?>

					<?php /* `aside` per quello che è, non per come si vede: qui dentro non c'è
					          una cornice, c'è una colonna. Il riquadro del «salva la data» la
					          cornice ce l'ha perché è un invito ad agire, e si deve vedere. */ ?>

<?php if(count($programma)){ ?>
					<section class="mt-sezione">
						<h2 class="sec-head"><?php echo mt_icona('list_alt'); ?><?php _e('Programma'); ?></h2>
						<ol class="mt-programma">
<?php foreach($programma as $sub){
	$oraS = meetoo_ora(mt_ev($sub, 'startDate'), $fuso); ?>
							<li>
								<span class="mt-prog-ora"><?php echo $oraS !== '' ? mt_esc($oraS) : '·'; ?></span>
								<span class="mt-prog-cosa">
									<strong><?php echo mt_esc(mt_ev($sub, 'name')); ?></strong>
<?php $dsub = mt_ev($sub, 'description'); if($dsub !== ''){ ?>
									<span class="mt-prog-nota"><?php echo mt_esc($dsub); ?></span>
<?php } ?>
								</span>
							</li>
<?php } ?>
						</ol>
					</section>
<?php } ?>

<?php
/* Le occorrenze di una SERIE: quando la pagina è quella di una collezione, le
 * sue date sono la cosa che si sta cercando. */
if($serie){
	meetoo_ambito('collection', basename($rel));
	meetoo_frammento();
	$tutto = (string)($_GET['tutti'] ?? '');
	$SEZIONI = meetoo_sezioni(true);
	meetoo_sezione('eventi', $SEZIONI['eventi'] ?? null, $tutto);
	meetoo_sezione('archivio', $SEZIONI['archivio'] ?? null, $tutto);
}
?>

					<?php /* I partecipanti: il guscio è qui, l'elenco lo chiede il browser — e
					          lo ottiene solo chi ha i permessi, perché a decidere è il server.
					          Sta nel contenuto e non nell'aside perché un elenco di nomi ed
					          email ha bisogno di larghezza. */ ?>
					<section id="mt-partecipanti" class="mt-sezione" hidden></section>
					</div><!-- .mt-principale -->

					<aside class="mt-aside">
						<div class="mt-dati">
<?php if($modalita){ echo mt_meta($modalita[0], $modalita[1]); } ?>
<?php if($eta !== ''){ echo mt_meta('escalator_warning', strcasecmp($eta, 'All Ages') === 0 ? __('Tutte le età') : $eta); } ?>
<?php if($offerta !== ''){ echo mt_meta('sell', $offerta); } ?>
<?php if($posti !== '' and (int)$posti > 0){
	echo mt_meta('event_seat', $rimasti !== ''
		? sprintf(__('%1$s posti su %2$s ancora liberi'), $rimasti, $posti)
		: sprintf(__('%s posti'), $posti));
} ?>
<?php
$link = array();
$sito = mt_ev($e, 'url');
if($sito !== ''){
	$link[] = array($sito, __('Sito dell’evento'));
}
foreach(mt_lista($e, 'sameAs') as $x){
	$v = trim((string)$x);
	if($v !== ''){
		$link[] = array($v, preg_replace('#^www\.#', '', (string)parse_url($v, PHP_URL_HOST)) ?: $v);
	}
}
foreach($link as $l){
	echo '<span>'.mt_icona('link').'<a href="'.mt_esc($l[0]).'" rel="noopener">'.mt_esc($l[1]).'</a></span>';
}
?>
						</div>

						<?php /* Le cose che si possono fare, insieme e con un indirizzo suo —
						          `#review`: un invito a valutare si manda per messaggio, e chi
						          lo riceve deve atterrare sul punto. */ ?>
						<div class="mt-cta" id="review">
<?php
/* SALVA LA DATA, solo per quello che deve ancora succedere. Due strade perché i
 * calendari sono due mondi: Google ha un indirizzo che apre il suo modulo già
 * compilato, tutti gli altri — Apple, Outlook, il telefono — leggono un file
 * `.ics`, che questa stessa pagina sa scrivere. */
if(!$passato and $ics_inizio !== ''){
?>
							<div class="mt-agenda">
								<a class="mt-azione mt-azione-forte" href="<?php echo mt_esc($google_cal); ?>" target="_blank" rel="noopener">
									<?php echo mt_icona('event_available'); ?><span><?php _e('Salva la data su Google Calendar'); ?></span>
								</a>
								<a class="mt-azione" href="?ics=1" download>
									<?php echo mt_icona('download'); ?><span><?php _e('Altri calendari (.ics)'); ?></span>
								</a>
							</div>
							<p class="mt-agenda-nota"><?php _e('Inserisci questo evento nel tuo calendario.'); ?></p>
<?php } ?>
							<div class="mt-azioni" data-evento="<?php echo mt_esc($rel); ?>"<?php echo $serie ? ' data-serie="1"' : ''; ?>>
								<button type="button" class="mt-azione" data-azione="interesse" aria-pressed="false">
									<?php echo mt_icona('bookmark'); ?><span><?php _e('Mi interessa'); ?></span><span class="mt-conto"></span>
								</button>
								<button type="button" class="mt-azione" data-azione="piace" aria-pressed="false">
									<?php echo mt_icona('favorite'); ?><span><?php _e('Mi piace'); ?></span><span class="mt-conto"></span>
								</button>
<?php /* A un appuntamento di ieri non ci si iscrive: il bottone non c'è. */
if(!$serie and !$passato){ ?>
								<button type="button" class="mt-azione mt-azione-forte" data-azione="iscrizione" aria-pressed="false">
									<?php echo mt_icona('how_to_reg'); ?><span><?php _e('Parteciperò'); ?></span><span class="mt-conto"></span>
								</button>
<?php } ?>
								<button type="button" class="mt-azione" data-azione="condividi">
									<?php echo mt_icona('share'); ?><span><?php _e('Condividi'); ?></span>
								</button>
							</div>
							<p class="mt-azioni-nota" hidden></p>

<?php
/* CHE COSA SI PUÒ VALUTARE: l'evento, chi l'ha organizzato, il luogo. Tre
 * bersagli distinti perché sono tre esperienze distinte: un posto scomodo non è
 * colpa di chi organizza. Le medie e le recensioni le legge chiunque — come su
 * una scheda di Google Maps —; votare può solo chi c'era, e lo decide il server. */
$bersagli = array(array('id' => $rel, 'nome' => $titolo, 'tipo' => __('L’evento')));
foreach($organizzatori as $o){
	$oid = meetoo_riferimento_nodo($o);
	$onome = mt_ev($o, 'name') ?: ($oid !== '' ? meetoo_titolo_contenuto($oid) : '');
	if($oid !== '' and $onome !== ''){
		$bersagli[] = array('id' => $oid, 'nome' => $onome, 'tipo' => __('Chi ha organizzato'));
	}
}
if($luogoId !== '' and $luogoNome !== ''){
	$bersagli[] = array('id' => $luogoId, 'nome' => $luogoNome, 'tipo' => __('Il luogo'));
}
if(!$serie){
?>
							<section id="mt-valuta" class="mt-sezione" hidden
								data-bersagli="<?php echo mt_esc(json_encode($bersagli, JSON_UNESCAPED_UNICODE)); ?>"></section>
<?php } ?>
						</div><!-- .mt-cta -->
					</aside>
				</div><!-- .mt-evento-griglia -->

<?php
// I temi: la categoria dichiarata e le parole chiave, su una riga in fondo.
// La categoria sta nella testata, con i tipi: qui restano le parole chiave.
$tag = array();
foreach(mt_lista($e, 'keywords') as $k){
	$v = trim((string)$k);
	if($v !== ''){
		$tag[] = $v;
	}
}
if(count($tag)){
?>
				<p class="mt-tag mt-tag-fondo">
<?php foreach($tag as $t){ ?><span class="mt-chip"><?php echo mt_esc($t); ?></span><?php } ?>
				</p>
<?php } ?>
			</article>
<?php
include_template('template-parts/footer');
