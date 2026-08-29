<?php
/**
 * La seconda riga dell'header: briciole a sinistra, azioni di pagina a destra.
 *
 * Le briciole si ricavano dall'INDIRIZZO, non da un campo scritto a mano: ogni
 * segmento del wspath è un posto dove si può tornare, e i segmenti che il sito
 * conosce hanno un'etichetta leggibile. Così una pagina nuova ha le briciole
 * giuste il giorno che nasce, senza che nessuno le compili. L'ultima briciola
 * prende il nome del contenuto, che dice più dello slug con dentro data e CAP.
 *
 * Le azioni dipendono da che cosa si guarda — un evento si condivide e si salva,
 * un luogo si apre in mappa — e da chi guarda. Stanno nel markup, non in
 * JavaScript: esistono anche prima che uno script sia stato eseguito.
 *
 * Se non c'è niente da dire, la riga non si stampa: una fascia vuota sotto
 * l'header è peggio che non averla.
 */
global $ws_query, $ws_content, $rewrite_rule;

$wspath = trim((string)($ws_query['wspath'] ?? ''), '/');
$entita = !empty($ws_content->mainEntity) ? $ws_content->mainEntity : $ws_content;
$tipo = (string)($rewrite_rule->type ?? '');


$briciole = array();
if($wspath !== ''){
	$briciole[] = array('', __('Home'));
	$corso = '';
	$pezzi = explode('/', $wspath);
	foreach($pezzi as $i => $pezzo){
		$corso = ($corso === '') ? $pezzo : $corso.'/'.$pezzo;
		$ultimo = ($i === count($pezzi) - 1);
		/* L'etichetta la sa la MAPPA: ogni indirizzo ha il suo titolo, e quello è
		 * il nome che la pagina porta. Prima c'era una tabellina qui dentro con
		 * dentro «eventi», «luoghi», «organizzatori»: si scriveva a mano, invecchiava
		 * a ogni cambio di struttura, e con gli indirizzi per zona avrebbe stampato
		 * «roma» e «municipio10» come se fossero nomi di posti. */
		$nome = meetoo_titolo($corso);
		/* Il titolo di una pagina si qualifica — «Eventi — Lido di Ostia» — perché
		 * nella scheda del browser e nei risultati di ricerca deve reggersi da solo.
		 * In una briciola no: lì il contesto è la briciola prima, e ripeterlo la
		 * allunga senza dire niente. */
		if(strpos($nome, ' — ') !== false){
			$nome = trim(substr($nome, 0, strpos($nome, ' — ')));
		}
		if($nome === '' and $ultimo and !empty($entita->name)){
			$nome = (string)$entita->name;
		}
		if($nome === ''){
			$nome = ucfirst(str_replace('-', ' ', $pezzo));
		}
		$briciole[] = array($corso, $nome);
	}
}

// Le azioni della pagina, raccolte prima di decidere se stampare la riga.
$azioni = array();
$azioni[] = '<button type="button" class="mt-icon-btn" data-mt-condividi title="'.__('Condividi').'" aria-label="'.__('Condividi').'"><span class="material-symbols-outlined" aria-hidden="true">share</span></button>';
if($tipo === 'Event' or $tipo === 'EventSeries'){
	$azioni[] = '<button type="button" class="mt-icon-btn" data-mt-preferito title="'.__('Salva tra i preferiti').'" aria-label="'.__('Salva tra i preferiti').'"><span class="material-symbols-outlined" aria-hidden="true">favorite</span></button>';
}
$gid = '';
if(!empty($entita->{'meetoo:googlePlaceId'})){
	$gid = (string)$entita->{'meetoo:googlePlaceId'};
} else if(!empty($entita->location) and !empty($entita->location->{'meetoo:googlePlaceId'})){
	$gid = (string)$entita->location->{'meetoo:googlePlaceId'};
}
if($gid !== ''){
	$mappa = 'https://www.google.com/maps/search/?api=1&amp;query=%20&amp;query_place_id='.rawurlencode($gid);
	$azioni[] = '<a class="mt-icon-btn" href="'.$mappa.'" rel="noopener" target="_blank" title="'.__('Apri in Google Maps').'" aria-label="'.__('Apri in Google Maps').'"><span class="material-symbols-outlined" aria-hidden="true">map</span></a>';
}
/* La penna NON è più qui: sta nella prima riga, accanto alle impostazioni
 * (`top-header.php`). Quella che c'era qui non ha mai funzionato — chiedeva a
 * `ws_utente_puo_scrivere()`, una funzione che in tutto il progetto non esiste,
 * quindi la condizione era falsa per costruzione e il pulsante non compariva a
 * nessuno. Il permesso adesso lo decide `meetoo_puo_modificare()`, che lo chiede
 * alla stessa funzione che risponde al salvataggio. */

if(empty($briciole) and empty($azioni)){
	return;
}
?>
				<div class="mt-row mt-row-2">
					<div class="mt-crumbs" id="mt-crumbs">
<?php foreach($briciole as $i => $b){
	$ultimo = ($i === count($briciole) - 1);
	if($i > 0){ echo '<span class="sep">|</span>'; }
	/* La prima briciola è una casa, e si disegna come tale: la parola «Home» è
	 * l'unica dell'intera riga che non dice dove sei, e su uno schermo stretto è
	 * anche la prima a rubare spazio a quelle che invece lo dicono. Il nome resta
	 * per chi legge con la voce e per chi ci passa sopra. */
	$etichetta = htmlspecialchars((string)$b[1], ENT_QUOTES, 'UTF-8');
	$casa = ($i === 0 and trim((string)$b[0]) === '');
	$dentro = $casa
		? '<span class="material-symbols-outlined" aria-hidden="true">home</span><span class="mt-solo-voce">'.$etichetta.'</span>'
		: $etichetta;
	if($ultimo){
?>
						<span class="c cur" aria-current="page"><?php echo $dentro; ?></span>
<?php } else { ?>
						<a class="c<?php echo $casa ? ' mt-casa' : ''; ?>" href="<?php echo ws_href($b[0]); ?>"<?php echo $casa ? ' title="'.$etichetta.'"' : ''; ?>><?php echo $dentro; ?></a>
<?php }
} ?>
					</div>
					<div class="mt-admin" id="mt-admin"><?php echo implode("\n\t\t\t\t\t\t", $azioni); ?></div>
				</div>
