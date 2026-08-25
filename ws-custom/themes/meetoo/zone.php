<?php
/**
 * La pagina di una zona: che cosa succede qui, adesso.
 *
 * È la porta d'ingresso per chi il quartiere lo abita, e sostituisce la vecchia
 * `index.html` che riempiva le stesse liste in JavaScript. La differenza non è
 * estetica: qui i prossimi eventi, i luoghi e i gruppi sono già nell'HTML che parte
 * dal server — quindi un motore li vede, e chi ha la rete lenta li legge subito.
 *
 * Le liste si leggono dagli INDICI (`_index/`), non scandendo le cartelle: gli
 * indici esistono apposta, li rigenera la manutenzione, e leggerli costa una
 * lettura invece di settantasei. Se un indice manca, la sezione dice che manca e la
 * pagina resta in piedi: meglio una sezione vuota che una pagina bianca.
 */
global $ws_content, $ws_query, $rewrite_rule;

$e = !empty($ws_content->mainEntity) ? $ws_content->mainEntity : $ws_content;
$nome = (string)($e->name ?? '');

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
	if($abspath === ''){
		return array();
	}
	$dati = json_decode((string)file_get_contents($abspath), true);
	if(!is_array($dati)){
		return array();
	}
	return isset($dati['events']) && is_array($dati['events']) ? $dati['events'] : $dati;
}

/** La data di un evento, come la direbbe una persona. */
function meetoo_quando($iso){
	$q = trim((string)$iso);
	if($q === ''){
		return '';
	}
	$t = strtotime($q);
	if(!$t){
		return $q;
	}
	// Le date con l'ora la mostrano, quelle senza no: dire «alle 00:00» a chi legge
	// un evento che dura tutto il giorno è dirgli una cosa falsa.
	$conOra = (strpos($q, 'T') !== false and substr($q, 11, 5) !== '00:00');
	return ucfirst(strftime_ita($t, $conOra));
}

/** Formattazione italiana senza strftime, che PHP ha deprecato. */
function strftime_ita($t, $conOra){
	$giorni = array('domenica','lunedì','martedì','mercoledì','giovedì','venerdì','sabato');
	$mesi = array('', 'gennaio','febbraio','marzo','aprile','maggio','giugno','luglio','agosto','settembre','ottobre','novembre','dicembre');
	$s = $giorni[(int)date('w', $t)].' '.date('j', $t).' '.$mesi[(int)date('n', $t)];
	if(date('Y', $t) !== date('Y')){
		$s .= ' '.date('Y', $t);
	}
	return $conOra ? $s.' · '.date('H:i', $t) : $s;
}

// I prossimi eventi: l'indice li tiene già divisi fra prossimi e archivio.
$eventi = array_slice(meetoo_indice('events.json'), 0, 6);
// Luoghi e gruppi vengono dallo stesso indice delle entità: un gruppo è
// un'organizzazione, un luogo un'attività o un posto.
$entita = meetoo_indice('entities.json');
$luoghi = array_slice(array_values(array_filter($entita, function($x){
	return ($x['kind'] ?? '') === 'business' and ($x['@type'] ?? '') !== 'ItemList';
})), 0, 8);
$gruppi = array_slice(array_values(array_filter($entita, function($x){
	return ($x['kind'] ?? '') === 'org';
})), 0, 8);
// Le liste curate della zona (Lungomare, BookCrossing): le dichiara il contenuto.
$liste = !empty($e->hasPart) ? $e->hasPart : array();

include_template('template-parts/header');
?>
			<article<?php echo ws_html_attributes('main-content', array('class' => array('mt-pagina', 'mt-zona-pagina'))); ?>>
				<h1 class="mt-h1"><?php echo htmlspecialchars($nome, ENT_QUOTES, 'UTF-8'); ?></h1>
<?php if(!empty($e->description)){ ?>
				<div class="mt-corpo"><?php ws_echo($e->description->innerHTML()); ?></div>
<?php } ?>

<?php if(count($liste)){ ?>
				<section class="mt-sezione" aria-labelledby="percorsi">
					<h2 id="percorsi" class="mt-h2"><?php _e('Percorsi'); ?></h2>
					<ul class="mt-elenco">
<?php foreach($liste as $lista){
	$id = (string)($lista->attributes()->id ?? '');
	if($id === ''){ continue; }
	$slug = basename($id);
?>
						<li><a class="mt-riga" href="<?php echo ws_href($slug); ?>">
							<span class="material-symbols-outlined" aria-hidden="true">route</span>
							<span class="mt-riga-testo"><b><?php echo htmlspecialchars(ucfirst($slug), ENT_QUOTES, 'UTF-8'); ?></b></span>
							<span class="material-symbols-outlined mt-riga-freccia" aria-hidden="true">arrow_forward</span>
						</a></li>
<?php } ?>
					</ul>
				</section>
<?php } ?>

				<section class="mt-sezione" aria-labelledby="eventi">
					<h2 id="eventi" class="mt-h2"><span class="material-symbols-outlined" aria-hidden="true">event</span> <?php _e('Prossimi eventi'); ?></h2>
<?php if(!count($eventi)){ ?>
					<p class="mt-nota"><?php _e('Nessun evento in programma. L’indice si rigenera dall’amministrazione.'); ?></p>
<?php } else { ?>
					<ul class="mt-elenco">
<?php foreach($eventi as $ev){
	$path = (string)($ev['path'] ?? $ev['@id'] ?? '');
	if($path === ''){ continue; }
?>
						<li><a class="mt-riga" href="<?php echo ws_href('eventi/'.basename($path)); ?>">
							<span class="material-symbols-outlined" aria-hidden="true">event</span>
							<span class="mt-riga-testo">
								<b><?php echo htmlspecialchars((string)($ev['name'] ?? basename($path)), ENT_QUOTES, 'UTF-8'); ?></b>
								<span class="mt-riga-nota"><?php
									echo htmlspecialchars(trim(meetoo_quando($ev['startDate'] ?? '').(!empty($ev['place']['name']) ? ' · '.$ev['place']['name'] : '')), ENT_QUOTES, 'UTF-8');
								?></span>
							</span>
							<span class="material-symbols-outlined mt-riga-freccia" aria-hidden="true">arrow_forward</span>
						</a></li>
<?php } ?>
					</ul>
<?php } ?>
				</section>

				<section class="mt-sezione" aria-labelledby="gruppi">
					<h2 id="gruppi" class="mt-h2"><span class="material-symbols-outlined" aria-hidden="true">groups</span> <?php _e('Chi anima il territorio'); ?></h2>
<?php if(!count($gruppi)){ ?>
					<p class="mt-nota"><?php _e('Nessun gruppo nell’indice.'); ?></p>
<?php } else { ?>
					<ul class="mt-elenco mt-elenco-fitto">
<?php foreach($gruppi as $g){ ?>
						<li><a class="mt-riga" href="<?php echo ws_href('organizzatori/'.basename((string)$g['@id'])); ?>">
							<span class="material-symbols-outlined" aria-hidden="true">groups</span>
							<span class="mt-riga-testo"><b><?php echo htmlspecialchars((string)$g['name'], ENT_QUOTES, 'UTF-8'); ?></b></span>
						</a></li>
<?php } ?>
					</ul>
<?php } ?>
				</section>

				<section class="mt-sezione" aria-labelledby="luoghi">
					<h2 id="luoghi" class="mt-h2"><span class="material-symbols-outlined" aria-hidden="true">place</span> <?php _e('Luoghi'); ?></h2>
<?php if(!count($luoghi)){ ?>
					<p class="mt-nota"><?php _e('Nessun luogo nell’indice.'); ?></p>
<?php } else { ?>
					<ul class="mt-elenco mt-elenco-fitto">
<?php foreach($luoghi as $l){ ?>
						<li><a class="mt-riga" href="<?php echo ws_href('luoghi/'.basename((string)$l['@id'])); ?>">
							<span class="material-symbols-outlined" aria-hidden="true">place</span>
							<span class="mt-riga-testo">
								<b><?php echo htmlspecialchars((string)$l['name'], ENT_QUOTES, 'UTF-8'); ?></b>
<?php if(!empty($l['locality'])){ ?>
								<span class="mt-riga-nota"><?php echo htmlspecialchars((string)$l['locality'], ENT_QUOTES, 'UTF-8'); ?></span>
<?php } ?>
							</span>
						</a></li>
<?php } ?>
					</ul>
<?php } ?>
				</section>
			</article>
<?php
include_template('template-parts/footer');
