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
						<div>
<?php
$logoImage = $ws_headings->xpath("id('logo')");
echo get_media($logoImage, array('pictureAttributes' => array( 'class' => 'site-logo')));
?>
							<hgroup><a href="<?php echo $index_url; ?>" title="<?php _e('Go to homepage'); ?>">
								<h1 class="site-name"><?php echo $ws_headings->mainEntity->name->innerHTML(); ?></h1>
								<h2<?php echo ws_html_attributes('header1-headline'); ?>><?php echo $ws_headings->mainEntity->headline->innerHTML(); ?></h2>
							</a></hgroup>
							<a href="#nav1" onclick="toggle('header1-nav1', this);" class="vgrid" title="<?php _e('Show the Main menu'); ?>"><span class="icon material-symbols-outlined">menu</span> <span class="text-label all-no"><?php _e('Main menu'); ?></span></a>
						</div>
						<nav <?php echo ws_html_attributes('header1-nav1', array('id' => 'header1-nav1', 'class' => array('header1-nav1', 'nav', 'vgrid-fullwidth', 'padding-h-d2', 'hgrid-horizontal'))); ?>>
							<h3 class="vgrid all-no"><?php _e('Main menu'); ?></h3>
							<div>
								<ul><?php ws_nav_items($nav1); ?></ul>
							</div>
						</nav>
<?php
/* L'accesso, in fondo alla riga del nome: dopo l'ultima voce del menu.
 * Prima stava nella barra dei contatti, che si chiude appena si comincia a
 * leggere — e con lei spariva il modo di entrare. */
include_template('template-parts/nav-google-login');
?>
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
			<div<?php echo ws_html_attributes('main-container'); ?>>
				<main<?php echo ws_html_attributes('main'); ?>>
<?php
include_template('template-parts/nav-contents');
?>
