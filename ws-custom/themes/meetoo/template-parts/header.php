<?php
/**
 * La testa della pagina: `<head>`, logo, menu, briciole.
 *
 * È la replica in PHP dell'header che oggi costruisce `header.js`, e ne usa lo
 * STESSO markup e le stesse classi (`mt-header`, `mt-row`, `mt-left`, `mt-brand`,
 * `mt-crumbs`, `mt-drawer`): l'aspetto non cambia di un pixel, cambia chi lo
 * scrive. La differenza è tutta lì — logo, titolo, briciole e collegamenti sono
 * già nell'HTML che parte dal server, quindi un motore di ricerca li vede senza
 * eseguire una riga di JavaScript. È il motivo di questo passaggio.
 *
 * Le due righe sono quelle di sempre: la 1 con logo e azioni, la 2 con le briciole
 * e le azioni di pagina. La riga 2 non si stampa dove non ha niente da dire.
 */
global $ws_query, $rewrite_rule, $ws_headings, $ws_content;

$home = ws_href('');
$nome_sito = !empty($ws_headings->mainEntity->name) ? (string)$ws_headings->mainEntity->name : 'Meetoo';
// Il logo è un contenuto, non un pezzo del tema: sta con il marchio, dove lo
// cerca anche l'header in JavaScript.
$logo = ws_contents_url().'meetoo/'.ws_locale().'/brand/media/logo-h.svg';

/**
 * Le voci del menu principale, dal CONTENUTO.
 *
 * Stesso posto del menu di isotype — `nav1` nella radice dei contenuti — perché un
 * menu è una decisione editoriale, non una riga di programma.
 *
 * Ma una voce dice CHE COSA, non DOVE: `<content>places/lido-di-ostia</content>`,
 * non `/roma/municipio10/lido-di-ostia`. L'indirizzo lo chiede alla mappa. Prima
 * erano scritti a mano, e il giorno in cui gli indirizzi sono diventati un albero
 * il menu ha continuato a offrire i vecchi: sette voci, sette 404. Con il
 * riferimento al contenuto quel guasto non è più possibile — e se un contenuto non
 * ha (o non ha più) una pagina, la voce non si stampa affatto: meglio una voce in
 * meno di una che porta a una pagina che non c'è.
 *
 * `<elenco>eventi</elenco>` accanto a un contenuto punta a uno dei tre elenchi di
 * quella zona: anche quello è derivato, non scritto.
 *
 * Resta accettato un `<wspath>` per le voci che una pagina di contenuto non ce
 * l'hanno, ma solo se la mappa la conosce: si controlla prima di stamparla.
 *
 * (Candidata a diventare `ws_nav_voci()` in your-theme, condivisa con
 * `ws_nav_items()`: la fonte è la stessa, cambia solo come si veste.)
 */
function meetoo_voci_nav(){
	global $ws_query, $ws_content_root;
	$nav = null;
	foreach(array($ws_content_root.'/'.ws_locale().'/nav1', $ws_content_root.'/nav1') as $forse){
		if(file_exists(ws_root_abspath().'/'.WS_CONTENTS_RELPATH.'/'.$forse.'.xml')){
			$nav = ws_content($forse);
			break;
		}
	}
	if(empty($nav) or !count($nav->item)){
		return array();
	}
	$qui = trim((string)($ws_query['wspath'] ?? ''), '/');
	$voci = array();
	foreach($nav->item as $item){
		if(empty($item->name)){
			continue;
		}
		$contenuto = trim((string)$item->content);
		$elenco = trim((string)$item->elenco);
		$destinazione = '';

		if($contenuto !== ''){
			$indirizzo = meetoo_indirizzo($contenuto);
			if($indirizzo === ''){
				continue;   // quel contenuto una pagina non ce l'ha: niente voce
			}
			$destinazione = $elenco !== '' ? rtrim($indirizzo, '/').'/'.$elenco : $indirizzo;
		} else {
			$wspath = trim((string)$item->wspath);
			if($wspath === '' or meetoo_titolo($wspath) === ''){
				continue;   // indirizzo scritto a mano che la mappa non conosce
			}
			$destinazione = ws_href(ltrim($wspath, '/'));
		}

		// «Sei qui»: si confronta l'indirizzo, non il contenuto — due voci possono
		// nascere dallo stesso contenuto (la zona e i suoi elenchi).
		$suo = trim(parse_url($destinazione, PHP_URL_PATH) ?: '', '/');
		$mio = trim(ws_mount().'/'.$qui, '/');
		$voci[] = array(
			'nome' => (string)$item->name,
			'icona' => trim((string)$item->icon) ?: 'chevron_right',
			'href' => $destinazione,
			'corrente' => ($suo !== '' and $suo === $mio),
		);
	}
	return $voci;
}
$menu = meetoo_voci_nav();
?>
<!DOCTYPE html>
<html<?php echo ws_html_attributes('html'); ?>>
	<head>
<?php
/* IL TITOLO DELLA SCHEDA: «Bambini e famiglie | Meetoo».
 *
 * Il nome della pagina prima, quello del sito dopo. È l'ordine in cui serve: in
 * una fila di venti schede aperte si legge il primo pezzo, e venti schede che
 * cominciano tutte con «Meetoo» non si distinguono. Vale anche per un risultato
 * di ricerca e per un titolo condiviso in chat.
 *
 * Il nome del sito NON si aggiunge quando la pagina è già il sito — la home si
 * chiama «Meetoo», non «Meetoo | Meetoo». Il nome della pagina viene dalla mappa
 * (`ws_sitemap.wsx`), dove resta nudo: quello stesso testo fa da etichetta ai
 * collegamenti, e lì il suffisso sarebbe rumore. */
$mt_titolo = trim((string)$rewrite_rule->title);
if($mt_titolo === '' or strcasecmp($mt_titolo, $nome_sito) === 0){
	$mt_titolo = $nome_sito;
} else {
	$mt_titolo .= ' | '.$nome_sito;
}
?>
		<title><?php echo htmlspecialchars($mt_titolo, ENT_QUOTES, 'UTF-8'); ?></title>
<?php
echo ws_metas();
echo ws_scripts('head');
echo ws_styles('head');
echo ws_links();
?>
	</head>
	<body<?php echo ws_html_attributes('body'); ?>>
		<div<?php echo ws_html_attributes('page'); ?>>
			<header<?php echo ws_html_attributes('header', array('class' => array('mt-header'))); ?>>
				<div class="mt-row mt-row-1">
					<div class="mt-left">
						<button class="mt-icon-btn" id="mt-menu" title="<?php _e('Menu'); ?>" aria-label="<?php _e('Menu'); ?>" aria-expanded="false" aria-controls="mt-drawer">
							<span class="material-symbols-outlined" aria-hidden="true">menu</span>
						</button>
						<a class="mt-brand" href="<?php echo $home; ?>" title="<?php _e('Home Meetoo'); ?>">
							<img class="mt-logo" src="<?php echo htmlspecialchars($logo, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($nome_sito, ENT_QUOTES, 'UTF-8'); ?>" width="120" height="24" />
						</a>
					</div>
					<div class="mt-actions">
<?php include_template('template-parts/top-header'); ?>
					</div>
				</div>
<?php include_template('template-parts/bottom-header'); ?>
			</header>

<?php
/* Il cassetto del menu: STESSO markup che costruisce `header.js` quando la pagina
 * non gliel'ha già dato — `.mt-drawer-head` con il marchio, `#mt-nav` con i
 * collegamenti diretti. Non è un vezzo: `meetoo.css` veste `.mt-nav a`, e una
 * lista `<ul><li>` lì dentro non la tocca nessuna regola.
 *
 * Niente `hidden`: chiuso e aperto li decide il CSS (il cassetto sta fuori
 * schermo, il velo è trasparente), e `hidden` bloccherebbe la transizione. */
?>
			<div class="mt-drawer-ov" id="mt-drawer-ov"></div>
			<nav class="mt-drawer" id="mt-drawer" aria-label="<?php _e('Menu'); ?>">
				<div class="mt-drawer-head">
					<a class="mt-brand" href="<?php echo $home; ?>">
						<img src="<?php echo htmlspecialchars($logo, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($nome_sito, ENT_QUOTES, 'UTF-8'); ?>" />
					</a>
					<button class="mt-icon-btn" id="mt-drawer-close" title="<?php _e('Chiudi'); ?>" aria-label="<?php _e('Chiudi'); ?>">
						<span class="material-symbols-outlined" aria-hidden="true">close</span>
					</button>
				</div>
				<div class="mt-nav" id="mt-nav">
<?php foreach($menu as $voce){ ?>
					<a href="<?php echo htmlspecialchars($voce['href'], ENT_QUOTES, 'UTF-8'); ?>"<?php echo $voce['corrente'] ? ' aria-current="page"' : ''; ?>><span class="material-symbols-outlined" aria-hidden="true"><?php echo htmlspecialchars($voce['icona'], ENT_QUOTES, 'UTF-8'); ?></span><?php echo htmlspecialchars($voce['nome'], ENT_QUOTES, 'UTF-8'); ?></a>
<?php } ?>
				</div>
			</nav>

			<div<?php echo ws_html_attributes('main-container'); ?>>
				<main<?php echo ws_html_attributes('main'); ?>>
