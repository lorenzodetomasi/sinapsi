<?php
/**
 * Meetoo — impostazioni del tema.
 *
 * Si carica in cascata insieme a quello del tema genitore: qui c'è solo ciò che
 * Meetoo ha di diverso, cioè i suoi fogli di stile e il JSON-LD nella testa.
 *
 * Il JSON-LD è il motivo per cui queste pagine esistono lato server: il contenuto
 * È già schema.org, quindi invece di descriverlo una seconda volta si serve il file
 * com'è. Un motore di ricerca legge l'evento — data, luogo, organizzatore, prezzo —
 * senza eseguire una riga di JavaScript.
 */
global $ws_query, $ws_content, $ws_content_root_url;

$ws_theme_url = ws_theme_url();

// I fogli di stile di Meetoo: i token prima (definiscono le variabili), poi il
// resto. `places.css` serve solo dove ci sono schede di luoghi, ma pesa poco e
// tenerlo in un elenco solo evita che una pagina si presenti a metà vestita.
// Si aggiungono in coda una per una: `ws_globals_set` alla foglia sostituisce, e
// due chiamate sullo stesso percorso si cancellerebbero a vicenda.
foreach(array(
	'<link rel="stylesheet" type="text/css" media="all" href="'.$ws_theme_url.'meetoo-tokens.css" />',
	'<link rel="stylesheet" type="text/css" media="all" href="'.$ws_theme_url.'meetoo.css" />',
	'<link rel="stylesheet" type="text/css" media="all" href="'.$ws_theme_url.'places.css" />',
	// Le classi delle pagine costruite dal server, separate da quelle delle pagine
	// costruite in JavaScript: finché convivono, si possono togliere una per volta.
	'<link rel="stylesheet" type="text/css" media="all" href="'.$ws_theme_url.'meetoo-cms.css" />',
	// Le icone: la stessa famiglia dell'editor, così l'amministrazione e il sito
	// parlano con gli stessi simboli.
	'<link rel="preconnect" href="https://fonts.googleapis.com" />',
	'<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="crossorigin" />',
	'<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&amp;display=swap" />',
) as $collegamento){
	$GLOBALS['ws_links'][] = $collegamento;
}

/**
 * Il contenuto della pagina, così com'è sul disco, dentro uno <script> JSON-LD.
 *
 * Si legge il file invece di ricostruirlo dall'albero XML: qualunque conversione
 * andata e ritorno è un'occasione per perdere qualcosa, e qui la fedeltà è il punto.
 */
function meetoo_jsonld(){
	global $ws_query;
	if(empty($ws_query['content'])){
		return '';
	}
	$abspath = ws_root_abspath().'/'.WS_CONTENTS_RELPATH.'/'.$ws_query['content'].'/index.json';
	if(!file_exists($abspath)){
		return '';
	}
	$raw = trim((string)file_get_contents($abspath));
	if($raw === '' or json_decode($raw) === null){
		return '';
	}
	// `</script>` dentro una descrizione chiuderebbe il blocco: si spezza la
	// sequenza senza toccare il significato del JSON.
	$raw = str_replace('</', '<\/', $raw);
	return '<script type="application/ld+json">'."\n".$raw."\n".'</script>';
}
$GLOBALS['ws_scripts']['head']['meetoo_jsonld'] = meetoo_jsonld();

// I gesti dell'header (cassetto, condividi): quel poco che resta al browser ora
// che il markup arriva già scritto.
$GLOBALS['ws_scripts']['bodyend']['meetoo_header'] = '<script defer="defer" src="'.$ws_theme_url.'js/header.js"></script>';

// L'header che si restringe al primo scorrimento vive nel tema genitore, perché
// serve a tutti i siti: qui si accende soltanto.
$GLOBALS['ws_html_attributes']['header']['class'][] = 'header-compatto';
$GLOBALS['ws_html_attributes']['html']['class'][] = 'meetoo';
?>
