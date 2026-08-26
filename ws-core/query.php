<?php
// Query
function get_query($path){
	if(empty($path)){
		global $ws_query;
		return $ws_query;
	} else {
		$path_parts = parse_url($path);
		parse_str($path_parts['query'], $path_query);
		return $path_query;
	}
}
global $ws_query;
// Set query defaults (Priority 3)
$ws_query_defaults = array(
	'locale' => false,
	'lang' => false,
	'themes' => false,
	'plugins' => false,
	'template' => false,
	'content' => false
);
// Get query from url (Priority 1)
$uri = rtrim( dirname($_SERVER["SCRIPT_NAME"]), '/' );
$uri = '/' . trim( str_replace( $uri, '', $_SERVER['REQUEST_URI'] ), '/' );
$uri = urldecode( $uri );
$uri_parts = parse_url($uri);
if(!empty($uri_parts['query'])){
	parse_str($uri_parts['query'], $uri_query);
}
// Load sitemap and rewrite rules
if(file_exists(ws_contents_abspath().'/ws_sitemap.wsx')){
	$ws_sitemap_abspath = ws_contents_abspath().'/ws_sitemap.wsx';
} else {
	$ws_sitemap_abspath = ws_admin_abspath().'/ws_sitemap.wsx';
}
if(file_exists($ws_sitemap_abspath)){
	$ws_sitemap = ws_load_file($ws_sitemap_abspath);
	$ws_sitemap_array = simplexml2array($ws_sitemap)['urlset']['url'];
}
// Array with rewrite rule that transforms a URL structure to a set of query vars.
// Any value in the $after parameter that isn't 'bottom' will result in the rule being placed at the top of the rewrite rules.
// @since 0.0.1
// @param string       $wspath wspath or regular expression to match request against.
// @param string|array $query The corresponding query vars for this rewrite rule.
// @param string       $after Optional. Priority of the new rule. Accepts 'top' or 'bottom'. Default 'bottom'.
$rewrite_rules = $ws_sitemap_array;

// Get query from rewrite rule (Priority 2)
$wspath = $uri_parts['path'];
// If /sitemap.xml load it
if($wspath == '/sitemap.xml' and file_exists(ws_contents_abspath().'/sitemap.xml')){
	$sitemap_xml = new \DOMDocument('1.0');
	$sitemap_xml->preserveWhiteSpace = true;
	$sitemap_xml->formatOutput = true;
	$sitemap_xml->load(ws_contents_abspath().'/sitemap.xml');
	header('Content-Type: text/xml; charset=utf-8');
	echo $sitemap_xml->saveXML();
	exit;
}
if($wspath !== '/' or $wspath !== ''){
	$wspath = ws_normalize_relpath($uri_parts['path']);
}
// Search for uri/path in rewrite_rules/wspath
$ws_mount = '';
$ws_mount_richiesto = '';   // il prefisso sotto cui è arrivata la richiesta…
$ws_sito_richiesto = '';    // …e il sito che quel prefisso serve: servono al 404
// `[0]` su una ricerca vuota è un avviso PHP stampato in cima alla pagina: qui
// «nessuna regola» è un esito normale, non un errore.
$trovate = $ws_sitemap->xpath('./url[./wspath = "/'.$wspath.'"]');
$rewrite_rule = $trovate[0] ?? null;
/* Siti INNESTATI. Più siti convivono in un dominio solo — oggi meetoo dentro
 * isotype.org, domani su meetoo.it — e i loro contenuti non devono saperlo: i
 * wspath restano quelli del giorno in cui avranno un dominio proprio, e il
 * prefisso si toglie qui prima di cercare la regola. Da qui in poi il prefisso
 * vive in $ws_query['mount'], e ws_href() lo rimette nei link.
 * I prefissi si dichiarano in ws-custom/ws-config.php: define('WS_MOUNTS', ['/meetoo' => 'meetoo']); */
if(empty($rewrite_rule) and defined('WS_MOUNTS') and is_array(WS_MOUNTS)){
	foreach(WS_MOUNTS as $prefisso => $sito){
		$nudo = trim((string)$prefisso, '/');
		if($nudo === ''){
			continue;
		}
		if($wspath === $nudo or strpos($wspath, $nudo.'/') === 0){
			$dentro = trim(substr($wspath, strlen($nudo)), '/');
			// L'indirizzo sta sotto questo prefisso: qualunque cosa succeda dopo,
			// la risposta è di questo sito. Serve al 404, qui sotto.
			$ws_mount_richiesto = $nudo;
			$ws_sito_richiesto = $sito;
			/* Si cerca DENTRO la parte di mappa del sito innestato, non in tutta la
			 * mappa: la home di un sito ospite ha lo stesso wspath («/») di quella
			 * dell'ospitante, e senza questo filtro vincerebbe sempre la prima
			 * dichiarata — cioè /meetoo/ aprirebbe la home di isotype. A quale sito
			 * appartiene una voce lo dice il `content=` della sua query. */
			$trovata = $ws_sitemap->xpath('./url[./wspath = "/'.$dentro.'" and contains(./query, "content='.$sito.'/")]');
			if(empty($trovata[0])){
				$trovata = $ws_sitemap->xpath('./url[./wspath = "/'.$dentro.'"]');
			}
			if(!empty($trovata[0])){
				$rewrite_rule = $trovata[0];
				$ws_mount = $nudo;
				$wspath = $dentro;
				break;
			}
		}
	}
}
if(empty($rewrite_rule)){
	/* Indirizzo che non esiste.
	 *
	 * Due cose andavano storte, e tutte e due contavano. La prima: si prendeva la
	 * PRIMA voce con wspath «/», cioè la home dell'ospitante — quindi ogni
	 * indirizzo sbagliato sotto /meetoo/ rispondeva con isotype, tema compreso.
	 * La seconda, peggiore: lo stato restava 200. Per un motore di ricerca
	 * significa che quella pagina esiste, e ne esistono infinite copie: è il modo
	 * più efficace di farsi buttare fuori da un indice.
	 *
	 * Ora la radice si cerca nel sito a cui l'indirizzo APPARTIENE — lo dice il
	 * prefisso da cui è entrato — e la risposta dice quello che è: 404. */
	$radice = array();
	if(!empty($ws_sito_richiesto)){
		$radice = $ws_sitemap->xpath('./url[./wspath = "/" and contains(./query, "content='.$ws_sito_richiesto.'/")]');
		if(!empty($radice[0])){
			$ws_mount = $ws_mount_richiesto;
		}
	}
	if(empty($radice[0])){
		$radice = $ws_sitemap->xpath('./url[./wspath = "/"]');
	}
	$rewrite_rule = $radice[0] ?? null;
	$rewrite_rule_query = get_query($rewrite_rule->query);
	$rewrite_rule_query['template'] = "404";
	$rewrite_rule_query['content'] = explode_and_remove_last('/', $rewrite_rule_query['content'])."/404";
	if(!headers_sent()){
		http_response_code(404);
	}
} else {
	$rewrite_rule_query = get_query($rewrite_rule->query);
}
if(isset($rewrite_rule_query['theme'])){
	$rewrite_rule_query['themes'][] = $rewrite_rule_query['theme'];
	unset($rewrite_rule_query['theme']);
}
if(isset($rewrite_rule_query['plugin'])){
	foreach($rewrite_rule_query['plugin'] as $ws_plugin){
		$ws_logs[] = sprintf('Content plugin added to ws_query: %1$s.', $ws_plugin);
		$rewrite_rule_query['plugins'][] = $ws_plugin;
	}
	unset($rewrite_rule_query['plugin']);
}
// Removes first "/" from content value
if(isset($rewrite_rule_query['content'])){
	$rewrite_rule_query['content'] = ws_normalize_relpath($rewrite_rule_query['content']);
}
$ws_query = array_merge($ws_query_defaults, $rewrite_rule_query);
if(!empty($uri_query)){
	$ws_query = array_merge($ws_query, $uri_query);
}
$ws_query['wspath'] = $wspath;
$ws_query['mount'] = $ws_mount;
?>
