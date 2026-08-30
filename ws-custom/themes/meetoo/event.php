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

/* Il vestito e il comportamento di questa pagina si caricano solo qui: sono la
 * pagina di un evento, e su una zona sarebbero peso scaricato per niente. */
$ws_theme_url = ws_theme_url();
$GLOBALS['ws_scripts']['bodyend']['meetoo_evento'] = '<script defer="defer" src="'.$ws_theme_url.'js/evento.js"></script>';

include_template('template-parts/header');
?>
			<article<?php echo ws_html_attributes('main-content', array('class' => array('mt-pagina', 'mt-evento-pagina'))); ?>>
				<h1 class="mt-h1"><?php echo mt_esc($titolo); ?> <?php echo mt_badge_stato($stato); ?></h1>

<?php $cover = meetoo_media($rel, mt_ev($e, 'image') ?: mt_ev($e, 'logo')); if($cover !== ''){ ?>
				<figure class="mt-copertina">
					<img src="<?php echo mt_esc($cover); ?>" alt="" loading="lazy" decoding="async" />
<?php $credito = trim((string)meetoo_campo_meetoo($e, 'imageCredit')); if($credito !== ''){ ?>
					<figcaption class="mt-credito"><?php echo mt_esc($credito); ?></figcaption>
<?php } ?>
				</figure>
<?php } ?>

				<div class="mt-scheda">
<?php if($quando !== ''){ echo mt_meta('event', $quando); } ?>
<?php if($luogoNome !== ''){ ?>
					<span><?php echo mt_icona('location_on'); ?><?php
						echo $luogoHref !== ''
							? '<a href="'.mt_esc($luogoHref).'">'.mt_esc($luogoNome).'</a>'
							: mt_esc($luogoNome);
					?></span>
<?php } ?>
<?php if($modalita){ echo mt_meta($modalita[0], $modalita[1]); } ?>
<?php if($eta !== ''){ echo mt_meta('escalator_warning', strcasecmp($eta, 'All Ages') === 0 ? __('Tutte le età') : $eta); } ?>
<?php if($offerta !== ''){ echo mt_meta('sell', $offerta); } ?>
<?php if($posti !== '' and (int)$posti > 0){
	echo mt_meta('event_seat', $rimasti !== ''
		? sprintf(__('%1$s posti su %2$s ancora liberi'), $rimasti, $posti)
		: sprintf(__('%s posti'), $posti));
} ?>
				</div>

<?php $organizzatori = mt_lista($e, 'organizer'); if(count($organizzatori)){ ?>
				<p class="mt-organizza"><span><?php _e('Organizzato da'); ?></span>
<?php foreach($organizzatori as $o){
	$id = meetoo_riferimento_nodo($o);
	$nome = mt_ev($o, 'name') ?: ($id !== '' ? meetoo_titolo_contenuto($id) : '');
	if($nome === ''){ continue; }
	$href = $id !== '' ? meetoo_indirizzo($id) : '';
	$icona = mt_org_icona((string)($o->{'@type'} ?? ''), $nome);
	$dentro = mt_icona($icona).mt_esc($nome);
	echo $href !== ''
		? '<a class="mt-chip" href="'.mt_esc($href).'">'.$dentro.'</a>'
		: '<span class="mt-chip">'.$dentro.'</span>';
} ?>
				</p>
<?php } ?>

				<?php /* Le tre cose che si possono fare. Ci sono anche senza JavaScript
				          — sono bottoni veri, e chi li preme senza essere collegato riceve
				          l'invito ad accedere, non il silenzio. `data-*` porta l'evento a
				          cui si riferiscono: il programma non deve indovinarlo. */ ?>
				<?php /* IL RIQUADRO: le cose da fare stanno insieme, in evidenza, e hanno un
				          indirizzo — `#review`. Un invito a valutare si manda per messaggio
				          («dicci com'è andata»), e chi lo riceve deve atterrare sul punto,
				          non in cima a una pagina lunga. */ ?>
				<div class="mt-cta" id="review">
<?php
/* SALVA LA DATA. Solo per quello che deve ancora succedere: mettere in agenda un
 * appuntamento di ieri non serve a nessuno, e un pulsante che non serve toglie
 * attenzione a quelli che servono.
 *
 * Due strade perché i calendari sono due mondi: Google ha un indirizzo che apre
 * il suo modulo già compilato, tutti gli altri — Apple, Outlook, il telefono —
 * leggono un file `.ics`, che questa stessa pagina sa scrivere. */
$passato = $i_fine = meetoo_istante($al !== '' ? $al : $dal, $fuso);
$passato = $passato ? $passato->getTimestamp() < time() : false;
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
<?php if(!$serie){ ?>
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
/* CHE COSA SI PUÒ VALUTARE: l'evento, chi l'ha organizzato, il luogo.
 *
 * L'elenco lo compone il server, perché è il server a sapere come si chiamano e
 * che @id hanno; quando e da chi si possa votare lo decide il server pure, e il
 * browser si limita a chiedere. Tre bersagli distinti perché sono tre esperienze
 * distinte: un posto scomodo non è colpa di chi organizza. */
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

				<?php /* I partecipanti: il guscio è qui, l'elenco lo chiede il browser — e lo
				          ottiene solo chi ha i permessi, perché a decidere è il server. I nomi
				          non stanno nel file dell'evento: li ricompone l'archivio privato. */ ?>
				<section id="mt-partecipanti" class="mt-sezione" hidden></section>

<?php $testo = meetoo_testo_visibile($e); if($testo !== ''){ ?>
				<div class="mt-corpo"><?php ws_echo($testo); ?></div>
<?php } else if(mt_ev($e, 'description') !== ''){ ?>
				<p class="mt-sommario"><?php echo mt_esc(mt_ev($e, 'description')); ?></p>
<?php } ?>

<?php if(count($programma)){ ?>
				<section class="mt-sezione">
					<h2 class="sec-head"><?php echo mt_icona('list_alt'); ?><?php _e('Programma'); ?></h2>
					<ol class="mt-programma">
<?php foreach($programma as $s){
	$oraS = meetoo_ora(mt_ev($s, 'startDate'), $fuso); ?>
						<li>
							<span class="mt-prog-ora"><?php echo $oraS !== '' ? mt_esc($oraS) : '·'; ?></span>
							<span class="mt-prog-cosa">
								<strong><?php echo mt_esc(mt_ev($s, 'name')); ?></strong>
<?php $d = mt_ev($s, 'description'); if($d !== ''){ ?>
								<span class="mt-prog-nota"><?php echo mt_esc($d); ?></span>
<?php } ?>
							</span>
						</li>
<?php } ?>
					</ol>
				</section>
<?php } ?>

<?php
/* Le occorrenze di una SERIE: quando la pagina è quella di una collezione, le
 * sue date sono la cosa che si sta cercando. Stesse sezioni delle altre pagine,
 * con l'ambito puntato su questa serie. */
if($serie){
	meetoo_ambito('collection', basename($rel));
	meetoo_frammento();
	$tutto = (string)($_GET['tutti'] ?? '');
	$SEZIONI = meetoo_sezioni(true);
	meetoo_sezione('eventi', $SEZIONI['eventi'] ?? null, $tutto);
	meetoo_sezione('archivio', $SEZIONI['archivio'] ?? null, $tutto);
}
?>

<?php
// Da dove viene e dove continua: la serie, i temi, i collegamenti.
$tag = array();
$cat = mt_ev($e, 'additionalType');
if($cat !== ''){
	$tag[] = $cat;
}
foreach(mt_lista($e, 'keywords') as $k){
	$v = trim((string)$k);
	if($v !== ''){
		$tag[] = $v;
	}
}
$link = array();
$sito = mt_ev($e, 'url');
if($sito !== ''){
	$link[] = array($sito, __('Sito dell’evento'));
}
foreach(mt_lista($e, 'sameAs') as $s){
	$v = trim((string)$s);
	if($v !== ''){
		$link[] = array($v, preg_replace('#^www\.#', '', (string)parse_url($v, PHP_URL_HOST)) ?: $v);
	}
}
$voto = isset($e->aggregateRating) ? mt_ev($e->aggregateRating, 'ratingValue') : '';
if($serieHref !== '' or count($tag) or count($link) or $voto !== ''){
?>
				<section class="mt-sezione mt-scheda-fondo">
<?php if($serieHref !== ''){ ?>
					<p class="mt-nota"><?php echo mt_icona('collections_bookmark'); ?>
						<?php _e('Fa parte di'); ?> <a href="<?php echo mt_esc($serieHref); ?>"><?php echo mt_esc($serieNome !== '' ? $serieNome : basename($serieId)); ?></a>
					</p>
<?php } ?>
<?php if($voto !== ''){ $max = mt_ev($e->aggregateRating, 'bestRating') ?: '5'; ?>
					<p class="mt-nota"><?php echo mt_icona('star'); ?><?php echo mt_esc($voto.' / '.$max); ?></p>
<?php } ?>
<?php if(count($tag)){ ?>
					<p class="mt-tag">
<?php foreach($tag as $t){ ?><span class="mt-chip"><?php echo mt_esc($t); ?></span><?php } ?>
					</p>
<?php } ?>
<?php if(count($link)){ ?>
					<p class="mt-link">
<?php foreach($link as $l){ ?><a href="<?php echo mt_esc($l[0]); ?>" rel="noopener"><?php echo mt_esc($l[1]); ?></a><?php } ?>
					</p>
<?php } ?>
				</section>
<?php } ?>
			</article>
<?php
include_template('template-parts/footer');
