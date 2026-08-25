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

// Le porte d'ingresso del sito. Le pagine vere arrivano dalla mappa; queste sono
// le poche che devono esserci sempre.
$menu = array(
	array('eventi', __('Eventi'), 'event'),
	array('lungomare', __('Lungomare'), 'beach_access'),
	array('bookcrossing', __('BookCrossing'), 'menu_book'),
	array('luoghi', __('Luoghi'), 'place'),
	array('organizzatori', __('Chi organizza'), 'groups'),
);
?>
<!DOCTYPE html>
<html<?php echo ws_html_attributes('html'); ?>>
	<head>
		<title><?php echo htmlspecialchars((string)$rewrite_rule->title, ENT_QUOTES, 'UTF-8'); ?></title>
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

			<div class="mt-drawer-ov" id="mt-drawer-ov" hidden="hidden"></div>
			<nav class="mt-drawer" id="mt-drawer" aria-label="<?php _e('Menu'); ?>" hidden="hidden">
				<div class="mt-drawer-head">
					<span><?php echo htmlspecialchars($nome_sito, ENT_QUOTES, 'UTF-8'); ?></span>
					<button class="mt-icon-btn" id="mt-drawer-close" title="<?php _e('Chiudi'); ?>" aria-label="<?php _e('Chiudi'); ?>">
						<span class="material-symbols-outlined" aria-hidden="true">close</span>
					</button>
				</div>
				<ul>
<?php foreach($menu as $voce){ ?>
					<li><a href="<?php echo ws_href($voce[0]); ?>"><span class="material-symbols-outlined" aria-hidden="true"><?php echo $voce[2]; ?></span> <?php echo $voce[1]; ?></a></li>
<?php } ?>
				</ul>
			</nav>

			<div<?php echo ws_html_attributes('main-container'); ?>>
				<main<?php echo ws_html_attributes('main'); ?>>
