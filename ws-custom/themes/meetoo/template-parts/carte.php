<?php
/**
 * Meetoo's cards, written by the server.
 *
 * The skeleton is the shared one (your-theme: template-parts/cards.php, drawn
 * by css/cards.css, with js/cards.js as its twin in the browser): here only
 * what is Meetoo's - the lead block (a date, an icon), the facts of an event,
 * who may edit what. Meetoo's own cards.js builds the same cards in the browser.
 *
 * Long lists are not built by the browser: when more cards are needed this
 * same file writes them and the browser pastes them in (see `zone.php`, the
 * `?parte=` part). So there is one model of the card, and the page a search
 * engine gets is already complete.
 *
 *   .card > .card-date|.card-icon + .card-body(.card-title > a.card-link,
 *           .card-meta) + .card-tools | .card-arrow
 */
include_template('template-parts/cards');

if(!function_exists('mt_card')){

/** Testo dell'utente dentro l'HTML: sempre di qui, mai a mano. */
function mt_esc($s){
	return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/**
 * Un simbolo. Decorativo: chi legge con la voce non lo sente.
 *
 * La CLASSE si può passare perché a dichiarare l'icona è il contenuto, e il
 * contenuto dice tutte e due le cose: `{"class": "material-symbols-outlined",
 * "name": "water"}`. Oggi la famiglia è una sola, ma il giorno che ne arriva
 * un'altra non c'è niente da riscrivere qui.
 */
function mt_icona($nome, $classe = 'material-symbols-outlined'){
	$classe = trim((string)$classe) ?: 'material-symbols-outlined';
	return '<span class="'.mt_esc($classe).'" aria-hidden="true">'.mt_esc($nome).'</span>';
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
 * Share + interest, as the tools of a card (ws_card_tools()).
 *
 * Who answers the click is js/cards.js, once for the whole document; interest
 * is then Meetoo's to decide (cards.js of this theme): recorded on the server
 * for an event, kept in the browser for a place. The bookmark starts off:
 * which places are already kept only the browser knows, and `js/lista.js`
 * lights them as soon as the page is up.
 */
function mt_social($o){
	return ws_card_tools(mt_strumenti($o));
}

/** The tools of a card from Meetoo's options: 'social' and, if allowed, 'edit'. */
function mt_strumenti($o){
	$t = array();
	if(!empty($o['kind']) or !empty($o['id'])){          // called with the social options themselves
		$o = array('social' => $o);
	}
	if(!empty($o['social'])){
		$s = $o['social'];
		$t['share'] = !empty($s['url']) ? $s['url'] : true;
		$t['interest'] = array('kind' => $s['kind'] ?? 'place', 'id' => $s['id'] ?? '');
	}
	if(!empty($o['edit'])){
		$t['edit'] = $o['edit'];
	}
	return $t ?: null;
}

/**
 * The skeleton: only the lead block (a date or an icon) and the tail change.
 *
 * `$titolo` comes as HTML - it may carry a badge - and whoever passes it
 * escapes it. Everything else is escaped here.
 */
function mt_card($href, $testa, $titolo, $meta = array(), $o = array()){
	return ws_card(array(
		'href' => $href,
		'external' => !empty($o['external']),
		'className' => $o['className'] ?? '',
		'head' => $testa,
		'title' => $titolo,
		'meta' => $meta,
		'tools' => mt_strumenti($o),
	));
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
	return mt_mese_num((int)date('n', $t));
}
/** Lo stesso, dal numero del mese: si usa quando la data è già letta nel suo fuso. */
function mt_mese_num($n){
	$mesi = array('gen','feb','mar','apr','mag','giu','lug','ago','set','ott','nov','dic');
	return $mesi[max(1, min(12, $n)) - 1];
}

/**
 * The next date of an event, or null when all of them are past.
 *
 * The index writes down every date (replicas, a weekly rule, a period: see
 * ws-admin/lib/event-dates.php); which one is "next" depends on today, so it
 * is chosen here, when the list is drawn. An entry written before the index
 * had `dates` has one: its startDate. A date is next until it is OVER - the
 * show of tonight stays in the list after it has begun.
 */
function meetoo_prossima_data($ev, $ora = null){
	$ora = $ora === null ? time() : $ora;
	$date = !empty($ev['dates']) ? $ev['dates']
		: array(array('start' => (string)($ev['startDate'] ?? ''), 'end' => (string)($ev['endDate'] ?? '')));
	foreach($date as $d){
		$fine = (string)(($d['end'] ?? '') !== '' ? $d['end'] : ($d['start'] ?? ''));
		if($fine === ''){
			continue;
		}
		if(strlen($fine) === 10){
			$fine .= 'T23:59:59';   // a whole day lasts until its end
		}
		$t = strtotime($fine);
		if($t !== false and $t >= $ora){
			return $d;
		}
	}
	return null;
}

/**
 * What an event with more than one date says under its title, in a few words:
 * "every Monday · until 28 Jun", "until 15 Nov", "and 30 Oct", "and 2 more
 * dates". '' for a single date, which the date block already says.
 */
function meetoo_quando_breve($ev, $prossima){
	$giorno = function($iso){
		$d = meetoo_istante((string)$iso);
		return $d ? $d->format('j').' '.mt_mese_num((int)$d->format('n')) : '';
	};
	$schema = (string)($ev['pattern'] ?? '');
	$fino = !empty($ev['until']) ? sprintf(__('until %s'), $giorno($ev['until'])) : '';
	if($schema === 'rule' and !empty($ev['rule'])){
		$r = $ev['rule'];
		$lunghi = array(1 => __('Monday'), __('Tuesday'), __('Wednesday'), __('Thursday'), __('Friday'), __('Saturday'), __('Sunday'));
		$corti = array(1 => __('Mon'), __('Tue'), __('Wed'), __('Thu'), __('Fri'), __('Sat'), __('Sun'));
		$giorni = array_values(array_filter(array_map('intval', (array)($r['days'] ?? array()))));
		$ogni = '';
		if(($r['freq'] ?? '') === 'W' and $giorni){
			// Lowercase: the day sits in the middle of a phrase ("ogni lunedì").
			$nomi = count($giorni) === 1 ? mb_strtolower((string)($lunghi[$giorni[0]] ?? '')) : implode(', ', array_map(function($n) use ($corti){ return $corti[$n] ?? ''; }, $giorni));
			$ogni = (int)($r['interval'] ?? 1) > 1 ? sprintf(__('every other %s'), $nomi) : sprintf(__('every %s'), $nomi);
		} else if(($r['freq'] ?? '') === 'D'){
			$ogni = __('every day');
		} else if(($r['freq'] ?? '') === 'M'){
			$ogni = __('every month');
		}
		return implode(' · ', array_filter(array($ogni, $fino)));
	}
	if($schema === 'period'){
		return $fino;
	}
	if($schema === 'dates' and $prossima){
		$dopo = array();
		$visto = false;
		foreach((array)($ev['dates'] ?? array()) as $d){
			if($visto){
				$dopo[] = $d;
			}
			if(($d['start'] ?? '') === ($prossima['start'] ?? null)){
				$visto = true;
			}
		}
		if(count($dopo) === 1){
			$uno = (string)$dopo[0]['start'];
			// The same day at another hour: "and 19:30"; another day: "and 30 Oct".
			if(substr($uno, 0, 10) === substr((string)$prossima['start'], 0, 10) and preg_match('/T(\d{2}:\d{2})/', $uno, $m)){
				return sprintf(__('and %s'), $m[1]);
			}
			return sprintf(__('and %s'), $giorno($uno));
		}
		if(count($dopo) > 1){
			return sprintf(__('and %d more dates'), count($dopo));
		}
	}
	return '';
}

/**
 * La card di un evento, da una voce dell'indice.
 *
 * `$o['organizer'] = false` toglie l'organizzatore (nelle pagine che sono già
 * sue); `$o['social'] = false` toglie condividi e «mi interessa».
 */
function mt_card_evento($ev, $o = array()){
	$path = (string)(isset($ev['path']) ? $ev['path'] : (isset($ev['@id']) ? $ev['@id'] : ''));
	/* The date block shows the NEXT date: for replicas, a rule or a period the
	 * first one may be long gone. In the archive, where none is next, the first. */
	$prossima = meetoo_prossima_data($ev);
	$inizio = trim((string)($prossima ? $prossima['start'] : (isset($ev['startDate']) ? $ev['startDate'] : '')));
	/* Il giorno e l'ora si leggono NEL FUSO SCRITTO NELLA DATA, non in quello del
	 * server: le sei di sera a Ostia sono le quattro a Greenwich, e il server sta
	 * a Greenwich. Vale anche per il giorno — un evento delle 00:30 cambia data. */
	$d = function_exists('meetoo_istante') ? meetoo_istante($inizio) : null;
	$t = $d ? $d->getTimestamp() : ($inizio !== '' ? strtotime($inizio) : false);
	$testa = '<div class="card-date">'
		.'<span class="d">'.($d ? $d->format('j') : '·').'</span>'
		.'<span class="m">'.($d ? mt_mese_num((int)$d->format('n')) : '').'</span>'
		.'<span class="y">'.($d ? $d->format('Y') : '').'</span>'
		.'</div>';

	$meta = array();
	// L'ora si mostra solo se c'è: dire «alle 00:00» a un evento che dura tutto il
	// giorno è dirgli addosso una cosa falsa.
	if($d and preg_match('/T\d/', $inizio)){
		$meta[] = mt_meta('schedule', $d->format('H:i'));
	}
	$altre = $prossima ? meetoo_quando_breve($ev, $prossima) : '';
	if($altre !== ''){
		$meta[] = mt_meta(($ev['pattern'] ?? '') === 'rule' ? 'event_repeat' : 'date_range', $altre);
	}
	if((!isset($o['organizer']) or $o['organizer'] !== false) and !empty($ev['organizer'])){
		$meta[] = mt_meta(mt_org_icona(isset($ev['organizerType']) ? $ev['organizerType'] : '', $ev['organizer']), $ev['organizer']);
	}
	$luogo = mt_luogo_testo(isset($ev['place']) ? $ev['place'] : null);
	if($luogo !== ''){
		$meta[] = mt_meta('location_on', $luogo);
	}
	/* La fascia d'età, quando l'evento la dichiara: dice PER CHI è, ed è la prima
	 * cosa che legge chi cerca qualcosa da fare con i figli. In una pastiglia si
	 * scrive corta — «0+», «14+», «6-10» — che è come si legge su una locandina;
	 * per esteso si dice sulla scheda, dove c'è spazio per una frase. */
	/* What it costs, in the fewest words: "Gratuito", "10 €", "da 6 €" - and
	 * whether one has to sign up. The detail is on the event's page. */
	if(array_key_exists('free', $ev)){
		$costo = '';
		if(!empty($ev['free'])){
			$costo = __('Gratuito');
		} else if(isset($ev['priceMin']) and $ev['priceMin'] !== null){
			require_once ws_admin_abspath().'/lib/event-offers.php';
			$piu = (($ev['priceMax'] ?? null) !== null and $ev['priceMax'] > $ev['priceMin']);
			$costo = $piu
				? sprintf(__('da %s'), event_price_text((float)$ev['priceMin']))
				: event_price_text((float)$ev['priceMin']);
		}
		if(!empty($ev['registration'])){
			$costo = trim($costo.($costo !== '' ? ' · ' : '').__('su iscrizione'));
		}
		if($costo !== ''){
			$meta[] = mt_meta('confirmation_number', $costo);
		}
	}
	$eta = trim((string)($ev['ageRange'] ?? ''));
	if($eta !== ''){
		$meta[] = mt_meta('escalator_warning', meetoo_fascia_breve($eta));
	} else if(!empty($ev['isChildrens'])){
		$meta[] = mt_meta('escalator_warning', __('Adatto ai bambini'));
	}

	$href = isset($o['href']) ? $o['href'] : ws_href('eventi/'.basename($path));
	$titolo = mt_esc(!empty($ev['name']) ? $ev['name'] : __('(senza titolo)'))
		.mt_badge_stato(isset($ev['status']) ? $ev['status'] : '');
	if(!isset($o['social']) or $o['social'] !== false){
		$o['social'] = array('kind' => 'event', 'id' => $path, 'url' => $href);
		// The pen, only for whoever may edit this event (the same rule as saving).
		if(function_exists('meetoo_puo_modificare_di') and meetoo_puo_modificare_di($path)){
			$o['edit'] = meetoo_url_modifica_di($path);
		}
	}
	return mt_card($href, $testa, $titolo, $meta, $o);
}

/** La card con l'icona: collezioni, gruppi, percorsi, voci di sezione. */
function mt_card_tile($o){
	$testa = '<div class="card-icon'.(!empty($o['accent']) ? ' accent' : '').'">'
		.mt_icona(!empty($o['icon']) ? $o['icon'] : 'chevron_right', $o['iconClass'] ?? '').'</div>';
	$meta = !empty($o['meta']) ? array(mt_meta(isset($o['metaIcon']) ? $o['metaIcon'] : '', $o['meta'])) : array();
	$titolo = mt_esc(isset($o['title']) ? $o['title'] : '').(isset($o['badge']) ? $o['badge'] : '');
	return mt_card(isset($o['href']) ? $o['href'] : '#', $testa, $titolo, $meta, $o);
}

}// function_exists
?>
