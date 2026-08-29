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
 * Le due tabelle della mappa, costruite in una passata sola.
 *
 * `perContenuto`: da un contenuto (`places/IT00122/lamanusa`) al suo indirizzo.
 * `perIndirizzo`: da un indirizzo al titolo della pagina che ci risponde.
 *
 * Ottanta voci, una lettura. Farne una ricerca XPath per ogni card costerebbe
 * ottanta volte tanto per la stessa risposta.
 */
function meetoo_mappa(){
	global $ws_sitemap;
	static $tab = null;
	if($tab !== null){
		return $tab;
	}
	$tab = array('perContenuto' => array(), 'perIndirizzo' => array());
	foreach($ws_sitemap->url as $voce){
		$q = (string)$voce->query;
		$wspath = trim((string)$voce->wspath, '/');
		$tab['perIndirizzo'][$wspath] = trim((string)$voce->title);
		// Le pagine GENERATE (i tre elenchi, i livelli intermedi) condividono il
		// contenuto della zona: qui si cerca la pagina PROPRIA di un contenuto, e
		// quelle non lo sono.
		if(strpos($q, 'elenco=') !== false or strpos($q, 'zona=') !== false){
			continue;
		}
		if(!preg_match('/[?&]content=([^&]*)/', $q, $m)){
			continue;
		}
		$pezzi = explode('/', trim(urldecode($m[1]), '/'));
		if(count($pezzi) < 3){
			continue;   // sito/locale/… : meno di così non è un contenuto
		}
		array_shift($pezzi);   // sito
		array_shift($pezzi);   // locale
		$tab['perContenuto'][implode('/', $pezzi)] = $wspath;
	}
	return $tab;
}

/**
 * L'indirizzo di un contenuto, chiesto alla MAPPA.
 *
 * Da quando gli indirizzi dicono dove sei — `/roma/municipio10/lido-di-ostia/…` —
 * non si possono più costruire nel template incollando `luoghi/` davanti a uno
 * slug: la zona di un luogo dipende dal suo CAP, quella di un evento dal luogo,
 * quella di un gruppo dagli eventi che organizza. Quelle regole stanno in un posto
 * solo, `ws-mappa.php`, e il loro risultato è la mappa. Qui la si legge.
 *
 * Un contenuto senza pagina ritorna '': chi chiama decide se saltarlo o mostrarlo
 * spento. Meglio che un collegamento che porta a un 404.
 */
function meetoo_indirizzo($rel){
	$rel = trim((string)$rel, '/');
	if($rel === ''){
		return '';
	}
	$tab = meetoo_mappa();
	return isset($tab['perContenuto'][$rel]) ? ws_href($tab['perContenuto'][$rel]) : '';
}

/**
 * L'@id a cui punta un nodo del contenuto.
 *
 * Nel passaggio da JSON a XML l'`@id` della radice diventa l'attributo `id`, quello
 * di un nodo interno un `xlink:href` — perché lì è un RIFERIMENTO ad altro, non il
 * nome di questo. Si guardano tutti e due: chi legge un contenuto non deve sapere
 * in che punto dell'albero si trova.
 */
function meetoo_riferimento_nodo($nodo){
	if(!is_object($nodo)){
		return '';
	}
	$href = $nodo->attributes('http://www.w3.org/1999/xlink');
	$id = ($href !== null and isset($href->href)) ? (string)$href->href : '';
	if($id === ''){
		$suoi = $nodo->attributes();
		$id = ($suoi !== null and isset($suoi->id)) ? (string)$suoi->id : '';
	}
	return trim($id);
}

/**
 * Un campo del namespace `meetoo:` su un nodo del contenuto.
 *
 * Nel passaggio da JSON a XML `meetoo:childrenHeading` diventa un elemento in un
 * NAMESPACE, e `$nodo->{'meetoo:childrenHeading'}` non lo trova: SimpleXML cerca un
 * elemento che si chiama proprio così, con i due punti dentro. Si risolve il
 * prefisso, che è l'unica cosa stabile — l'indirizzo del namespace cambia a seconda
 * di come il contenuto dichiara il contesto.
 */
function meetoo_campo_meetoo($nodo, $campo){
	if(!is_object($nodo)){
		return '';
	}
	$suoi = $nodo->children('meetoo', true);
	return ($suoi !== null and isset($suoi->$campo)) ? trim((string)$suoi->$campo) : '';
}

/**
 * L'icona dichiarata su un NODO del contenuto (non su un documento a parte).
 *
 * La usano le voci di un albero — un municipio, un quartiere — che vivono dentro il
 * file di qualcun altro e non hanno un documento da cui andare a leggerla.
 */
function meetoo_icona_nodo($nodo){
	if(!is_object($nodo)){
		return null;
	}
	$suoi = $nodo->children('meetoo', true);
	if($suoi === null or !isset($suoi->icon)){
		return null;
	}
	$ico = $suoi->icon;
	/* `children()` a mani nude: l'icona sta nel namespace `meetoo:`, ma i suoi campi
	 * — `class`, `name` — no, stanno in quello predefinito. Chiedendoli
	 * direttamente (`$ico->name`) SimpleXML li cerca ancora fra i `meetoo:`, e non
	 * trova niente: una stringa vuota che sembrava «icona non dichiarata». */
	$dentro = $ico->children();
	/* E si accettano tutte e due le grafie, `name` e `meetoo:name`: dentro
	 * `meetoo:icon` non c'è ambiguità da sciogliere — quei due campi non vogliono
	 * dire niente altrove — ma un contenuto scritto con il prefisso non deve
	 * diventare un'icona che sparisce. */
	$pref = $ico->children('meetoo', true);
	$campo = function($come) use ($dentro, $pref){
		if(isset($dentro->$come)){
			return trim((string)$dentro->$come);
		}
		return ($pref !== null and isset($pref->$come)) ? trim((string)$pref->$come) : '';
	};
	$nome = $campo('name');
	if($nome === ''){
		// `<meetoo:icon>museum</meetoo:icon>`: l'icona detta e basta, senza campi.
		$nome = trim((string)$ico);
	}
	if($nome === ''){
		return null;
	}
	return array('name' => $nome, 'class' => $campo('class'));
}

/**
 * Un contenuto, letto dal disco e tenuto da parte.
 *
 * Serve alle card che linkano a qualcos'altro e vogliono sapere una cosa sola —
 * l'icona, il nome — senza caricarne il documento due volte. Si legge il JSON
 * direttamente, come fa il JSON-LD nella testa: è la forma in cui il contenuto è
 * scritto, e passare dall'albero XML per leggere un campo sarebbe un giro lungo.
 */
function meetoo_contenuto($rel){
	global $ws_query;
	static $letti = array();
	$rel = trim((string)$rel, '/');
	if($rel === ''){
		return null;
	}
	if(array_key_exists($rel, $letti)){
		return $letti[$rel];
	}
	$pezzi = explode('/', (string)$ws_query['content']);
	$radice = ws_root_abspath().'/'.WS_CONTENTS_RELPATH.'/'.$pezzi[0].'/'.($pezzi[1] ?? ws_locale());
	$file = "$radice/$rel/index.json";
	$doc = is_file($file) ? json_decode((string)@file_get_contents($file), true) : null;
	if(is_array($doc) and isset($doc['mainEntity']) and is_array($doc['mainEntity'])){
		$doc = $doc['mainEntity'];
	}
	return $letti[$rel] = (is_array($doc) ? $doc : null);
}

/**
 * L'icona di un contenuto, dichiarata dal contenuto stesso.
 *
 * `"meetoo:icon": {"class": "material-symbols-outlined", "name": "water"}`.
 *
 * L'icona appartiene alla cosa, non a chi la nomina: così il lungomare ha la sua
 * onda dovunque compaia — nella zona, dentro una raccolta, in un elenco — e per
 * cambiarla si tocca un posto solo. Chi non ce l'ha ancora ritorna null, e chi
 * chiama mette la sua.
 */
function meetoo_icona_di($rel, $salti = 0){
	$doc = meetoo_contenuto($rel);
	$ico = is_array($doc) ? ($doc['meetoo:icon'] ?? null) : null;
	if(is_string($ico) and trim($ico) !== ''){
		return array('name' => trim($ico), 'class' => '');
	}
	if(is_array($ico)){
		$nome = (string)($ico['name'] ?? $ico['meetoo:name'] ?? '');
		if($nome !== ''){
			return array('name' => $nome, 'class' => (string)($ico['class'] ?? $ico['meetoo:class'] ?? ''));
		}
	}
	/* Niente icona qui: la si chiede a ciò di cui questo contenuto PARLA.
	 * «BookCrossing a Ostia» non ha un'icona sua — è un BookCrossing, e l'icona
	 * del BookCrossing sta scritta una volta sola, nel catalogo delle categorie.
	 * Il salto è uno solo: `about` porta a una definizione, non a una catena. */
	$about = is_array($doc) ? ($doc['about'] ?? null) : null;
	$verso = is_array($about) ? ($about['@id'] ?? '') : (string)$about;
	if($salti < 1 and is_string($verso) and trim($verso, '/') !== '' and trim($verso, '/') !== trim((string)$rel, '/')){
		return meetoo_icona_di(trim($verso, '/'), $salti + 1);
	}
	return null;
}

/**
 * Il contenuto di QUESTA pagina, come percorso: `events/2026…`, `places/…`.
 *
 * È l'@id della cosa che si sta guardando, senza il sito e la lingua davanti —
 * la forma con cui la conoscono la mappa, gli indici e gli editor.
 */
function meetoo_rel_corrente(){
	global $ws_query;
	return preg_replace('#^[^/]+/[^/]+/#', '', trim((string)($ws_query['content'] ?? ''), '/'));
}

/**
 * Questa persona può modificare quello che sta guardando?
 *
 * La domanda la decide `ws_can_edit()`, la stessa funzione che risponde quando
 * si tenta di salvare: se qui dicesse di sì e là di no, la penna sarebbe una
 * porta che si apre su un muro. Un redattore vede l'evento che ha creato, un
 * amministratore vede tutto.
 */
function meetoo_puo_modificare(){
	global $meetoo_utente;
	if(!is_array($meetoo_utente) or !function_exists('ws_can_edit')){
		return false;
	}
	$doc = meetoo_contenuto(meetoo_rel_corrente());
	if(!is_array($doc)){
		return false;
	}
	return ws_can_edit($doc, (string)$meetoo_utente['uid'], (string)$meetoo_utente['role']);
}

/**
 * Dove si va per modificare questo contenuto, '' se non c'è dove andare.
 *
 * Solo per ciò che un editor sa aprire per nome: oggi gli eventi, che l'editor
 * carica con `?id=`. Per i luoghi e le organizzazioni l'editor esiste ma si apre
 * vuoto, e una penna che porta a un modulo bianco promette una cosa e ne fa
 * un'altra — meglio nessuna penna finché non sa aprire la scheda giusta.
 */
function meetoo_url_modifica(){
	$rel = meetoo_rel_corrente();
	if(strpos($rel, 'events/') !== 0){
		return '';
	}
	return ws_root_url().'ws-admin/events/edit/?id='.rawurlencode($rel);
}

/**
 * L'indirizzo web di un file che sta NELLA CARTELLA di un contenuto.
 *
 * Nei documenti l'immagine si scrive relativa a sé — `media-sources/cover.jpg` —
 * perché è lì che sta, accanto al file che la nomina, e resta valida anche se il
 * contenuto viene spostato. Ma una pagina la serve da un altro indirizzo
 * (`/meetoo/roma/…/eventi/<slug>`), e un percorso relativo letto di lì punta
 * altrove: era un 404 sulla copertina di ogni evento.
 *
 * Chi ha già un indirizzo suo — assoluto, o che comincia con uno slash — si
 * lascia com'è.
 */
function meetoo_media($rel, $file){
	$f = trim((string)$file);
	if($f === '' or preg_match('#^(https?:)?//#', $f) or $f[0] === '/'){
		return $f;
	}
	global $ws_query;
	$pezzi = explode('/', (string)$ws_query['content']);
	$sito = $pezzi[0];
	$locale = $pezzi[1] ?? ws_locale();
	return ws_contents_url().$sito.'/'.$locale.'/'.trim((string)$rel, '/').'/'.$f;
}

/**
 * Il titolo della pagina di un contenuto, dal suo @id.
 *
 * Serve alle collezioni che elencano RIFERIMENTI e basta — «Libri e letture» dice
 * `{"@id": "events/clubdellibro-ostia-reading_party"}` e niente più, ed è giusto
 * così: il nome sta nel documento di quell'evento, e ripeterlo qui vorrebbe dire
 * tenerne due copie che prima o poi divergono. La mappa quel nome ce l'ha già.
 */
function meetoo_titolo_contenuto($rel){
	$tab = meetoo_mappa();
	$rel = trim((string)$rel, '/');
	if(!isset($tab['perContenuto'][$rel])){
		return '';
	}
	return $tab['perIndirizzo'][$tab['perContenuto'][$rel]] ?? '';
}

/** Il titolo della pagina che risponde a un indirizzo, '' se non risponde nessuno. */
function meetoo_titolo($wspath){
	$tab = meetoo_mappa();
	return $tab['perIndirizzo'][trim((string)$wspath, '/')] ?? '';
}

/**
 * Il testo che si legge nella pagina.
 *
 * Due campi, due mestieri: il SOMMARIO (`abstract`) è quello che legge una
 * persona, e può essere formattato; la DESCRIZIONE (`description`) è la frase che
 * finisce nel risultato di ricerca e nell'anteprima di un link condiviso, ed è
 * testo semplice. Prima erano lo stesso campo, e la pagina lo mostrava due volte:
 * una come sommario in cima e una come corpo.
 *
 * Finché i contenuti scritti prima non saranno migrati a mano, la regola è
 * indulgente: si mostra il Sommario se c'è, altrimenti quello che c'è scritto
 * nella descrizione — che in quei file è ancora il corpo del testo.
 *
 * Ritorna XHTML già pronto da stampare (è marcatura validata dall'editor, non
 * testo di un estraneo), oppure '' se non c'è niente da dire.
 */
function meetoo_testo_visibile($e){
	foreach(array('abstract', 'description') as $campo){
		if(!empty($e->$campo)){
			$html = trim($e->$campo->innerHTML());
			if($html !== ''){
				return $html;
			}
		}
	}
	return '';
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
