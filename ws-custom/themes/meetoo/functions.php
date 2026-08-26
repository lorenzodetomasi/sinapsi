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
global $ws_query, $ws_content, $ws_content_root_url, $rewrite_rule;

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
	// I caratteri di Meetoo, gli stessi che i token chiedono: Roboto Slab per i
	// titoli, Roboto per il testo. Li chiediamo con un foglio di stile, come faceva
	// la vecchia home — vedi qui sotto perché non con WebFont.js.
	'<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&amp;family=Roboto+Slab:wght@400;500;600;700&amp;display=swap" />',
) as $collegamento){
	$GLOBALS['ws_links'][] = $collegamento;
}

/* Due cose che il tema genitore mette nella testa di tutti i suoi siti, e che qui
 * non solo non servono ma fanno danno. Si tolgono da qui perché `functions.php` si
 * carica a cascata e il figlio parla per ultimo: il genitore resta com'è per
 * isotype, che quelle due cose le usa davvero.
 *
 * - `js_header` misura un `#header1` che nei temi del genitore c'è e in Meetoo no:
 *   su ogni pagina lasciava un errore JavaScript vero («firstElementChild of
 *   null»), e con lui moriva tutto ciò che veniva dopo nello stesso blocco.
 * - `js_webfont` carica WebFont.js da ajax.googleapis.com per prendere Titillium e
 *   Raleway, che Meetoo non usa: un file in più e due famiglie scaricate per
 *   niente. I caratteri che servono li chiede il foglio di stile qui sopra. */
unset($GLOBALS['ws_scripts']['head']['js_header'], $GLOBALS['ws_scripts']['head']['js_webfont']);

/* E il CSS «above the fold» del genitore, che qui pesava 25 KB su ogni pagina.
 *
 * Il genitore lo stampa dentro la testa perché la prima schermata si veda senza
 * aspettare un foglio esterno: giusto, per i suoi siti. Ma è la griglia a tre
 * colonne di isotype, con il suo `#header1` e la sua spalla, e Meetoo non ne usa
 * una riga — anzi, ne annullava dei pezzi in `meetoo-cms.css`. Toglierlo dimezza
 * la pagina (57 → 32 KB) e la lascia identica: i fogli di Meetoo bastano a sé,
 * come bastavano alle pagine che il CMS non serviva ancora.
 *
 * Se un giorno servisse rimetterlo, è questa riga sola: si cancella. */
unset(
	$GLOBALS['ws_styles']['head']['all'], $GLOBALS['ws_styles']['head']['screen'],
	$GLOBALS['ws_styles']['head']['vgrid'], $GLOBALS['ws_styles']['head']['hgrid'],
	$GLOBALS['ws_styles']['head']['maxgrid']
);

/* L'indirizzo CANONICO della pagina.
 *
 * Il CMS scrive `og:url` da REQUEST_URI, cioè dall'indirizzo com'è arrivato: se
 * qualcuno apre `?tutti=luoghi` — il ripiego senza JavaScript per vedere un elenco
 * intero — quell'indirizzo diventa una seconda copia della stessa pagina agli
 * occhi di un motore. Il canonico invece viene dalla MAPPA: è l'indirizzo che
 * quella pagina ha, e uno solo. */
if(!empty($rewrite_rule->wspath)){
	$wspath = (string)$rewrite_rule->wspath;
	$canonico = ws_href(ltrim($wspath, '/'));
	if(substr($wspath, -1) === '/' and substr($canonico, -1) !== '/'){
		$canonico .= '/';   // la home tiene la sua barra finale
	}
	$GLOBALS['ws_links'][] = '<link rel="canonical" href="'.htmlspecialchars($canonico, ENT_QUOTES, 'UTF-8').'" />';
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

/* ---------------------------------------------------------------------------
 * Chi sei, detto dal server.
 *
 * Il comportamento dell'header — cassetto, impostazioni, tema, «mi interessa» —
 * sta tutto in `header.js`, che è il lavoro finito e non si riscrive. Quello che
 * cambia qui è da dove viene la SESSIONE: non più un token di Google tenuto nel
 * browser e verificato a ogni caricamento (dura un'ora: era quello a far
 * ricomparire di continuo la richiesta di accesso), ma la sessione PHP che apre
 * il plugin `google-login`. La pagina parte già sapendo chi sei — niente attesa,
 * niente avatar che lampeggia — e le richieste si autenticano con il cookie più
 * un gettone, che il cookie da solo non basta.
 * ------------------------------------------------------------------------- */
require_once ws_root_abspath().'/ws-admin/lib/ws-auth.php';
require_once ws_root_abspath().'/ws-admin/lib/ws-users.php';

$meetoo_utente = ws_autentica_sessione();
if($meetoo_utente){
	// Le preferenze stanno sul profilo, non nel browser: valgono anche da un altro
	// computer. Se il profilo non c'è ancora, restano quelle di partenza.
	$profilo = ws_user_get(ws_root_abspath().'/'.WS_CONTENTS_RELPATH.'/meetoo/'.ws_locale(), $meetoo_utente['uid']);
	$meetoo_utente = array(
		'uid' => $meetoo_utente['uid'],
		'name' => $meetoo_utente['name'] ?: $meetoo_utente['email'],
		'email' => $meetoo_utente['email'],
		'picture' => $meetoo_utente['picture'],
		'role' => $meetoo_utente['role'],
		'prefs' => (is_array($profilo) and isset($profilo['meetoo:preferences'])) ? $profilo['meetoo:preferences'] : new stdClass(),
	);
}
$meetoo_cfg = array(
	'sessione' => 'php',
	'utente' => $meetoo_utente ?: null,
	'csrf' => $meetoo_utente ? ws_gettone_sessione() : '',
	// L'uscita la esegue il plugin, su qualunque indirizzo: il cookie può
	// cancellarlo solo il server.
	'logoutUrl' => ws_href(ltrim((string)($rewrite_rule->wspath ?? ''), '/')).'?logout=1',
);
$GLOBALS['ws_scripts']['head']['meetoo_sessione'] = '<script>window.MEETOO_HEADER = '
	.json_encode($meetoo_cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
	.';</script>';

/* Dove sta il sito e dove stanno i contenuti: DICHIARATI, non indovinati.
 * `header.js` li cerca proprio con questi due nomi — li aveva previsti per il
 * giorno in cui il CMS avrebbe servito le pagine, che è oggi. Senza, dedurrebbe
 * la radice tagliando l'indirizzo su `/ws-custom/`, che negli indirizzi puliti
 * non compare. */
$GLOBALS['ws_metas']['meetoo_site_root'] = '<meta name="meetoo:site-root" content="'.ws_root_url().'/" />';
$GLOBALS['ws_metas']['meetoo_content_base'] = '<meta name="meetoo:content-base" content="'.ws_contents_url().'meetoo/'.ws_locale().'/" />';

// Il comportamento dell'header: lo stesso file delle pagine dell'archivio e
// dell'editor. Qui trova il markup già scritto e lo adotta invece di crearlo.
$GLOBALS['ws_scripts']['bodyend']['meetoo_header'] = '<script defer="defer" src="'.$ws_theme_url.'header.js"></script>';
// Le azioni della riga contestuale (condividi), che dell'header non fanno parte.
$GLOBALS['ws_scripts']['bodyend']['meetoo_azioni'] = '<script defer="defer" src="'.$ws_theme_url.'js/azioni.js"></script>';
// I gesti delle card — condividi, «mi interessa» — sono già scritti una volta
// sola in `cards.js`, che li intercetta sul documento: valgono anche per le card
// che arrivano dal server, senza doverle costruire in JavaScript.
$GLOBALS['ws_scripts']['bodyend']['meetoo_carte'] = '<script defer="defer" src="'.$ws_theme_url.'cards.js"></script>';
// Le liste lunghe che si allungano mentre si scorre.
$GLOBALS['ws_scripts']['bodyend']['meetoo_liste'] = '<script defer="defer" src="'.$ws_theme_url.'js/lista.js"></script>';

// L'header che si restringe al primo scorrimento vive nel tema genitore, perché
// serve a tutti i siti: qui si accende soltanto.
$GLOBALS['ws_html_attributes']['header']['class'][] = 'header-compatto';
$GLOBALS['ws_html_attributes']['html']['class'][] = 'meetoo';
?>
