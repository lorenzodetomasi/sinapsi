<?php
// The main header Php template
// @package WS
// @subpackage Your Theme
// @since WS 1.0
global $ws_query, $rewrite_rule, $ws_headings, $ws_contentmap, $ws_content, $ws_content_root, $ws_content_root_abspath;
$index_url = $ws_headings->url[0];

/*
 * Il menu: quello della lingua, se c'è; poi quello del sito; e se non c'è
 * nessuno dei due, la mappa, che le voci ce le ha comunque.
 *
 * Le due righe di prima erano rotte in due modi che si nascondevano a vicenda.
 * La seconda condizione — `if($percorso.'/nav1.xml')` — verifica una STRINGA
 * non vuota, quindi è sempre vera: si caricava il menu del sito esistesse o no.
 * E quando non esisteva, `ws_content()` tornava `false`, su cui `->count()` è
 * un errore fatale.
 *
 * Nessuno se n'era accorto perché su isotype e Meetoo il menu della lingua c'è
 * sempre. Si vede la prima volta che una lingua non ce l'ha: `/en` di isotype
 * moriva così, con la pagina bianca e l'errore nel corpo.
 */
$nav1 = null;
foreach(array($ws_content_root.'/'.ws_locale().'/nav1', $ws_content_root.'/nav1') as $nav1_relpath){
  $nav1_abspath = ws_root_abspath().'/'.WS_CONTENTS_RELPATH.'/'.$nav1_relpath;
  if(file_exists($nav1_abspath.'.xml') or file_exists($nav1_abspath.'.wsx') or file_exists($nav1_abspath.'.json')){
    $nav1 = ws_content($nav1_relpath);
    if($nav1){
      break;
    }
  }
}
if(!$nav1 or $nav1->count() == 0){
  $nav1 = $ws_contentmap;
}
?>
<!DOCTYPE html>
<html<?php echo ws_html_attributes('html'); ?>>
	<head>
		<title><?php echo $rewrite_rule->title; ?></title>
<?php
echo ws_metas();
echo ws_scripts('head');
echo ws_styles('head');
echo ws_links();
?>
	</head>
	<body<?php echo ws_html_attributes('body'); ?>>
		<div<?php echo ws_html_attributes('page'); ?>>
			<header<?php echo ws_html_attributes('header'); ?>>
				<div<?php echo ws_html_attributes('header-content'); ?>>
<?php
if($ws_headings->header_top){
	include_template($ws_headings->header_top);
}
?>
					<div <?php echo ws_html_attributes('header1'); ?>>
<?php
/* IL MARCHIO, e se accanto ci va scritto il nome.
 *
 * Due forme, distinte dall'id che la marca dà al disegno: `logotype` è un
 * segno che il nome ce l'ha già dentro (il marchio di Meetoo), `logo` è un
 * segno che gli sta accanto (il simbolo di isotype). Nel primo caso scrivere
 * anche il nome vorrebbe dire scriverlo due volte. Nel secondo il nome e
 * l'occhiello escono come testo, che è anche ciò che li rende selezionabili,
 * traducibili e leggibili ad alta voce. Qui non c'è nessun `if` che sappia il
 * nome di un sito: la differenza la dichiara il contenuto. */
$marchio = ws_brand_mark();
?>
						<a<?php echo ws_html_attributes('brand', array('id' => 'brand', 'href' => $index_url, 'title' => __('Go to homepage'))); ?>>
<?php
echo $marchio['html'];
if($marchio['name']){
?>
							<hgroup>
								<h1><?php echo $ws_headings->mainEntity->name->innerHTML(); ?></h1>
<?php
	/* L'occhiello si stampa se la marca ce l'ha. Un `<h2>` vuoto sotto al nome
	   è una riga di niente che sposta tutto il resto. */
	if(!empty($ws_headings->mainEntity->headline) and trim((string)$ws_headings->mainEntity->headline) !== ''){
?>
								<h2<?php echo ws_html_attributes('header1-headline'); ?>><?php echo $ws_headings->mainEntity->headline->innerHTML(); ?></h2>
<?php
	}
?>
							</hgroup>
<?php
}
?>
						</a>
						<nav <?php echo ws_html_attributes('header1-nav1', array('id' => 'header1-nav1')); ?>>
							<ul><?php ws_nav_items($nav1); ?></ul>
						</nav>
<?php
/* LE AZIONI, e il loro ordine, che è sempre lo stesso.
 *
 * Le preferenze, poi l'accesso, poi l'hamburger — che sta all'ESTREMA destra,
 * su qualunque schermo. Non è un gusto: è l'unico comando che la pagina ha
 * sempre, e un comando che cambia posto a seconda di cosa c'è accanto si cerca
 * ogni volta. L'accesso gli sta subito a sinistra, e c'è solo se c'è il
 * plugin: il tema non sa (e non deve sapere) come si entra. */
?>
						<div<?php echo ws_html_attributes('header1-actions', array('id' => 'header1-actions')); ?>>
<?php
/* Le azioni che un sito ha IN PIU'. Meetoo ci mette il «+» per creare e la
 * penna per modificare, che sono suoi e di nessun altro. Un tema figlio che
 * porta questo file vince, perche' `locate_file` scorre i temi dal figlio al
 * genitore e si ferma al primo: e' l'unico punto della cascata in cui il figlio
 * arriva davvero prima. Stanno PRIMA delle preferenze perche' riguardano la
 * pagina che si sta guardando, non chi la guarda. */
if(locate_file('template-parts/header-actions.php')){
	include_template('template-parts/header-actions');
}
?>
							<a id="preferences-open" href="#preferences" title="<?php _e('Preferences'); ?>" aria-label="<?php _e('Preferences'); ?>" aria-expanded="false" aria-controls="preferences"><span class="material-symbols-outlined" aria-hidden="true">settings</span></a>
<?php
if(locate_file('template-parts/nav-google-login.php')){
	include_template('template-parts/nav-google-login');
}
?>
							<button type="button" id="menu-open" title="<?php _e('Main menu'); ?>" aria-label="<?php _e('Main menu'); ?>" aria-expanded="false" aria-controls="drawer"><span class="material-symbols-outlined" aria-hidden="true">menu</span></button>
						</div>
					</div>
<?php
/* La terza riga: dove sei. Sta DENTRO l'header perché è parte
 * dell'orientamento, come in Meetoo — e come il resto dell'header si stringe
 * quando si legge, ma non sparisce: è l'unica cosa che dice a chi arriva da
 * una ricerca in che punto del sito è finito. */
include_template('template-parts/header2');
?>
				</div>
			</header>
<?php
/* IL CASSETTO del menu.
 *
 * Fuori dall'`<header>`, che è appiccicato in cima: un pannello alto quanto lo
 * schermo dentro un elemento sticky resta alto quanto l'header.
 *
 * Niente `hidden`: aperto e chiuso li decide il CSS — il cassetto sta fuori
 * schermo e il velo è trasparente — e `hidden` spegnerebbe la transizione.
 * Chiuso però non deve essere raggiungibile col tab, e a quello pensa
 * `inert`, che si toglie all'apertura. */
?>
			<div id="drawer-overlay"></div>
			<nav id="drawer" aria-label="<?php _e('Main menu'); ?>" inert>
				<div id="drawer-head">
					<a href="<?php echo $index_url; ?>" title="<?php _e('Go to homepage'); ?>"><?php echo $marchio['html']; ?></a>
					<button type="button" id="drawer-close" title="<?php _e('Close'); ?>" aria-label="<?php _e('Close'); ?>"><span class="material-symbols-outlined" aria-hidden="true">close</span></button>
				</div>
				<ul id="drawer-nav"><?php ws_nav_items($nav1, array('icons' => true)); ?></ul>
			</nav>
			<div<?php echo ws_html_attributes('main-container'); ?>>
				<main<?php echo ws_html_attributes('main'); ?>>
<?php
include_template('template-parts/nav-contents');
?>
