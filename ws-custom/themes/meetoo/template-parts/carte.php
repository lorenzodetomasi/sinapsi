<?php
/**
 * Le card di Meetoo, scritte dal server.
 *
 * È il gemello di `cards.js`: stesse classi, stesso ordine dei pezzi, stesso
 * markup — perché il vestito è già scritto in `meetoo.css` e non ha senso
 * inventarne un secondo. Il gemello in JavaScript resta dov'è: serve alle pagine
 * costruite nel browser, e ai gesti (condividi, «mi interessa») che restano suoi
 * anche qui, perché il click lo intercetta lui una volta sola sul documento.
 *
 * Le liste lunghe non le costruisce il browser: quando servono altre card è
 * questo stesso file a scriverle, e il browser le incolla e basta (vedi
 * `zone.php`, la parte `?parte=`). Così il modello della card resta uno, e la
 * pagina che arriva da un motore di ricerca è già completa.
 *
 * Struttura comune, identica a cards.js:
 *   a.card > .card-date|.card-icon + .card-body(.card-title + .card-meta) + .card-arrow
 */

if(!function_exists('mt_card')){

/** Testo dell'utente dentro l'HTML: sempre di qui, mai a mano. */
function mt_esc($s){
	return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** Un simbolo Material. Decorativo: chi legge con la voce non lo sente. */
function mt_icona($nome){
	return '<span class="material-symbols-outlined" aria-hidden="true">'.mt_esc($nome).'</span>';
}

/** Una voce dei meta: icona facoltativa + testo. */
function mt_meta($icona, $testo){
	$testo = trim((string)$testo);
	if($testo === ''){
		return '';
	}
	return '<span>'.($icona ? mt_icona($icona) : '').mt_esc($testo).'</span>';
}

/**
 * Condividi + «mi interessa».
 *
 * I pulsanti non possono stare DENTRO il link della card (un elemento cliccabile
 * dentro un altro non si fa), quindi la card resta un link e la coppia gli sta
 * accanto, dentro un contenitore che li sovrappone in coda. Chi risponde al click
 * è `cards.js`; qui si scrive solo che cosa si condivide e che cosa si segna.
 *
 * Il cuore parte spento: quali luoghi siano già segnati lo sa il browser di chi
 * guarda (i preferiti dei luoghi stanno in `localStorage`), e lo accende
 * `js/lista.js` appena la pagina è in piedi.
 */
function mt_social($o){
	$tipo = isset($o['kind']) ? $o['kind'] : 'place';
	$id = isset($o['id']) ? $o['id'] : '';
	$url = isset($o['url']) ? $o['url'] : '';
	return '<div class="card-social" data-social-kind="'.mt_esc($tipo).'" data-social-id="'.mt_esc($id).'"'
		.($url ? ' data-social-url="'.mt_esc($url).'"' : '').'>'
		.'<button type="button" class="share" title="'.mt_esc(__('Condividi')).'">'.mt_icona('share').'</button>'
		.'<button type="button" class="fav" title="'.mt_esc(__('Mi interessa')).'">'.mt_icona('favorite').'</button>'
		.'</div>';
}

/**
 * Lo scheletro: cambia solo il «cappello» (una data o un'icona) e la coda.
 *
 * `$titolo` arriva già HTML — perché può portarsi dietro un badge — e chi lo
 * passa lo scappa. Tutto il resto lo scappa questa funzione.
 */
function mt_card($href, $testa, $titolo, $meta = array(), $o = array()){
	$classe = 'card'.(!empty($o['className']) ? ' '.$o['className'] : '');
	$fuori = !empty($o['external']);
	$attr = $fuori ? ' target="_blank" rel="noopener"' : '';
	$freccia = $fuori ? 'open_in_new' : 'arrow_forward';
	$meta = array_filter($meta);
	$corpo = '<div class="card-body"><h3 class="card-title">'.$titolo.'</h3>'
		.($meta ? '<div class="card-meta">'.implode('', $meta).'</div>' : '')
		.'</div>';
	$soc = !empty($o['social']) ? mt_social($o['social']) : '';
	$link = '<a class="'.$classe.'" href="'.mt_esc($href).'"'.$attr.'>'
		.$testa.$corpo
		.($soc ? '' : '<div class="card-arrow">'.mt_icona($freccia).'</div>')
		.'</a>';
	return $soc ? '<div class="card-holder">'.$link.$soc.'</div>' : $link;
}

/**
 * L'icona di chi organizza o anima: la regola è quella di `cards.js`.
 * Il tipo comanda; il nome interviene solo quando il tipo è generico (una
 * biblioteca resta un LocalBusiness, ma non è un negozio).
 */
function mt_org_icona($tipo, $nome = ''){
	$t = strtolower(implode(' ', (array)$tipo));
	$n = strtolower((string)$nome);
	if(preg_match('/localbusiness|store|shop|restaurant|cafe|bar\b/', $t)){
		if(preg_match('/library|biblioteca/', $t.' '.$n)){ return 'local_library'; }
		if(preg_match('/bookstore|libreria/', $t.' '.$n)){ return 'menu_book'; }
		return 'storefront';
	}
	if(preg_match('/ngo|nonprofit|charit/', $t)){ return 'volunteer_activism'; }
	if(preg_match('/organization|group|club|association/', $t)){ return 'groups'; }
	if(preg_match('/biblioteca|library/', $n)){ return 'local_library'; }
	if(preg_match('/onlus|\baps\b|associazion|comitato|volontar/', $n)){ return 'volunteer_activism'; }
	return 'groups';
}

/** Lo stato schema.org di un evento, come etichetta accanto al titolo. */
function mt_badge_stato($stato){
	$s = (string)$stato;
	if(stripos($s, 'Cancelled') !== false){
		return '<span class="badge cancelled">'.mt_icona('cancel').mt_esc(__('Annullato')).'</span>';
	}
	if(stripos($s, 'Rescheduled') !== false){
		return '<span class="badge rescheduled">'.mt_icona('update').mt_esc(__('Riprogrammato')).'</span>';
	}
	if(stripos($s, 'Postponed') !== false){
		return '<span class="badge postponed">'.mt_icona('update').mt_esc(__('Rinviato')).'</span>';
	}
	return '';
}

/** «Nome del luogo, Località» da un riferimento come lo scrive l'indice. */
function mt_luogo_testo($p){
	if(!is_array($p)){
		return '';
	}
	$nome = isset($p['name']) ? trim((string)$p['name']) : '';
	$loc = '';
	if(isset($p['address']['addressLocality'])){
		$loc = trim((string)$p['address']['addressLocality']);
	} else if(isset($p['locality'])){
		$loc = trim((string)$p['locality']);
	}
	if($nome === ''){
		return $loc;
	}
	// Un nome che già dice la località non la ripete («Lido di Ostia, Roma»).
	if($loc === '' or stripos($nome, $loc) !== false){
		return $nome;
	}
	return $nome.', '.$loc;
}

/** I mesi come li abbrevia il blocchetto della data. */
function mt_mese($t){
	$mesi = array('gen','feb','mar','apr','mag','giu','lug','ago','set','ott','nov','dic');
	return $mesi[(int)date('n', $t) - 1];
}

/**
 * La card di un evento, da una voce dell'indice.
 *
 * `$o['organizer'] = false` toglie l'organizzatore (nelle pagine che sono già
 * sue); `$o['social'] = false` toglie condividi e «mi interessa».
 */
function mt_card_evento($ev, $o = array()){
	$path = (string)(isset($ev['path']) ? $ev['path'] : (isset($ev['@id']) ? $ev['@id'] : ''));
	$inizio = trim((string)(isset($ev['startDate']) ? $ev['startDate'] : ''));
	$t = $inizio !== '' ? strtotime($inizio) : false;
	$testa = '<div class="card-date">'
		.'<span class="d">'.($t ? date('j', $t) : '·').'</span>'
		.'<span class="m">'.($t ? mt_mese($t) : '').'</span>'
		.'<span class="y">'.($t ? date('Y', $t) : '').'</span>'
		.'</div>';

	$meta = array();
	// L'ora si mostra solo se c'è: dire «alle 00:00» a un evento che dura tutto il
	// giorno è dirgli addosso una cosa falsa.
	if($t and preg_match('/T\d/', $inizio)){
		$meta[] = mt_meta('schedule', date('H:i', $t));
	}
	if((!isset($o['organizer']) or $o['organizer'] !== false) and !empty($ev['organizer'])){
		$meta[] = mt_meta(mt_org_icona(isset($ev['organizerType']) ? $ev['organizerType'] : '', $ev['organizer']), $ev['organizer']);
	}
	$luogo = mt_luogo_testo(isset($ev['place']) ? $ev['place'] : null);
	if($luogo !== ''){
		$meta[] = mt_meta('location_on', $luogo);
	}

	$href = isset($o['href']) ? $o['href'] : ws_href('eventi/'.basename($path));
	$titolo = mt_esc(!empty($ev['name']) ? $ev['name'] : __('(senza titolo)'))
		.mt_badge_stato(isset($ev['status']) ? $ev['status'] : '');
	if(!isset($o['social']) or $o['social'] !== false){
		$o['social'] = array('kind' => 'event', 'id' => $path, 'url' => $href);
	}
	return mt_card($href, $testa, $titolo, $meta, $o);
}

/** La card con l'icona: collezioni, gruppi, percorsi, voci di sezione. */
function mt_card_tile($o){
	$testa = '<div class="card-icon'.(!empty($o['accent']) ? ' accent' : '').'">'
		.mt_icona(!empty($o['icon']) ? $o['icon'] : 'chevron_right').'</div>';
	$meta = !empty($o['meta']) ? array(mt_meta(isset($o['metaIcon']) ? $o['metaIcon'] : '', $o['meta'])) : array();
	$titolo = mt_esc(isset($o['title']) ? $o['title'] : '').(isset($o['badge']) ? $o['badge'] : '');
	return mt_card(isset($o['href']) ? $o['href'] : '#', $testa, $titolo, $meta, $o);
}

}// function_exists
?>
