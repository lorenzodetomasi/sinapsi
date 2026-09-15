<?php
/* Le funzioni di questo tema sono SOSTITUIBILI: ognuna sta dentro un
 * `function_exists`, cosi' un tema figlio che ne definisce una con lo stesso
 * nome vince, invece di far morire la pagina.
 *
 * Serviva davvero: `functions.php` si carica a cascata — il figlio E il genitore —
 * e isotype ridefinisce cinque delle funzioni di qui. Senza guardiano il
 * caricamento finiva con «Cannot redeclare function telephone()», cioe' con il
 * sito bianco.
 */
global $ws_query, $ws_content_root;
$ws_theme_url = ws_theme_url();
/* Where the shared stylesheets and scripts are served from.
 *
 * They belong to THIS theme. A child theme (isotype, meetoo) reaches them
 * through its parent; this theme, which has no parent, reaches them as itself.
 * ws_parent_theme_url() has no answer for "no parent": it builds …/themes//,
 * an empty segment, and every link made from it is a 404. That is how
 * /profilo-utente — served with theme=your-theme directly — lost all.css,
 * vgrid, hgrid and maxgrid, and with hgrid the footer's two-column grid: the
 * inline above-the-fold sheets come from disk and survived, the linked ones did
 * not, and the footer stacked into one column. */
$ws_assets_theme_url = ws_parent_theme_id() ? ws_parent_theme_url() : ws_theme_url();
$ws_content_root_url = ws_content_root_url();

ws_globals_set(array('ws_links'), array(
	'<link rel="apple-touch-icon" sizes="180x180" href="'.$ws_content_root_url.'/favicons/apple-touch-icon.png" />',
	'<link rel="icon" type="image/png" sizes="32x32" href="'.$ws_content_root_url.'/favicons/favicon-32x32.png" />',
	'<link rel="icon" type="image/png" sizes="16x16" href="'.$ws_content_root_url.'/favicons/favicon-16x16.png" />',
	'<link rel="manifest" href="'.$ws_content_root_url.'/favicons/site.webmanifest" />'
));

// Web fonts
// 1. Families
$GLOBALS['ws_webfonts'] = '{
	google: {
		families: ["Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200", "Titillium+Web:200,200i,300,300i,400,400i,600,600i,700,700i,900", "Raleway:300,300i,400,400i,600,600i,700,700i"]
	}
}';

// Scripts for all pages
// - webfont.js
$GLOBALS['ws_scripts']['head']['js_webfont'] = '
<!-- WebFont.load -->
<script defer="defer" onload="webfont_load();" src="'.is_ssl('http').'//ajax.googleapis.com/ajax/libs/webfont/1.6.26/webfont.js"></script>
<script>
  function webfont_load(){ WebFont.load('.$GLOBALS['ws_webfonts'].'); }
</script>';
$GLOBALS['ws_scripts']['head']['js_header'] = '
<!-- js_header -->
<script>
function header1(){
    var header1 = document.getElementById("header1");
    var header1_hgroup = header1.firstElementChild;
    var header1_nav1 = document.getElementById("header1-nav1");
    var header1_nav1_width = header1.offsetWidth - header1_hgroup.offsetWidth;
    header1_nav1.style["max-width"] = header1_nav1_width+"px"
}
document.addEventListener("DOMContentLoaded", () => {
  header1();
});
</script>';
// - Global site tag (gtag.js) - Google Analytics
// Defined in ws-custom/ws-config.php
if(GTAG){
  $GLOBALS['ws_scripts']['head']['js_gtag'] = GTAG;
}
// Stylesheets
// 1. AboveTheFold
/* Il foglio si stampa nella testa solo se esiste davvero. Prima si chiamava
 * `file_get_contents(locate_file(...))` senza guardare l'esito: un tema figlio
 * senza `css/` (per esempio meetoo, che ha fogli suoi) faceva morire la pagina con
 * «Path must not be empty», e nessuno lo sapeva finché non si apriva quel tema.
 * Due delle cinque righe avevano anche il `>` di chiusura mancante: il foglio
 * finiva nell'HTML dentro un tag rotto. */
function ws_stile_se_esiste($chiave, $media, $basename){
	$abspath = locate_file($basename);
	if(!$abspath or !file_exists($abspath)){
		return;
	}
	$GLOBALS['ws_styles']['head'][$chiave] = '<style media="'.$media.'">'.file_get_contents($abspath).'</style>';
}
ws_stile_se_esiste('all', 'all', 'css/all-abovethefold.css');
ws_stile_se_esiste('screen', 'screen', 'css/screen-abovethefold.css');
ws_stile_se_esiste('vgrid', 'screen and (max-width: 999px)', 'css/vgrid-abovethefold.css');
ws_stile_se_esiste('hgrid', 'screen and (min-width: 1000px)', 'css/hgrid-abovethefold.css');
ws_stile_se_esiste('maxgrid', 'screen and (min-width: 1280px)', 'css/maxgrid-abovethefold.css');
// 2. Linked
ws_globals_set(array('ws_links'), array(
	'<link rel="stylesheet" type="text/css" media="all" href="'.$ws_assets_theme_url.'css/all.css" />',
	'<link rel="stylesheet" type="text/css" media="screen and (max-width: 999px)" href="'.$ws_assets_theme_url.'css/vgrid.css" />',
	'<link rel="stylesheet" type="text/css" media="screen and (min-width: 1000px)" href="'.$ws_assets_theme_url.'css/hgrid.css" />',
	'<link rel="stylesheet" type="text/css" media="screen and (min-width: 1280px)" href="'.$ws_assets_theme_url.'css/maxgrid.css" />'
));
// If page has a section[class="form"]

// Translations
// # To be completed
$lang = 'en';
$langName = 'English';
$translationUrl = '';
// 1. 
$translationLink = '<link rel="alternate" hreflang="'.$lang.'" href="/'.$lang.'/" title="This document in '.$langName.'">';
// 2. https://schema.org/workTranslation
$translationA = '<a href="" itemprop="workTranslation" itemscope itemtype="https://schema.org/CreativeWork" itemid="'.$translationUrl.'">'.$langName.'</a>';
// WS Html Attributes
/* The body used to open a microdata scope (`itemscope itemtype=WebPage`) and
 * the templates filled it with itemprops. Not any more: what a page is, it now
 * says once, as JSON-LD in the head (see ws_page_jsonld below), from the same
 * content the templates draw. Two declarations of the same thing drift apart -
 * the header was naming the site inside the page's scope, the h1 and the h2
 * gave the page a second `name` and `headline` - and a search engine reading
 * both cannot tell which to believe. */
ws_globals_set(array('ws_html_attributes', 'page', 'class'), array('center'));
ws_globals_set(array('ws_html_attributes', 'header-content', 'id'), array('header-content'));
ws_globals_set(array('ws_html_attributes', 'header-top', 'class'), array('nav', 'horizontal', 'padding-h-d2'));
ws_globals_set(array('ws_html_attributes', 'header1', 'id'), array('header1'));
ws_globals_set(array('ws_html_attributes', 'header1', 'class'), array('content-container'));
ws_globals_set(array('ws_html_attributes', 'main-container', 'id'), array('main-container'));
ws_globals_set(array('ws_html_attributes', 'main-content', 'class'), array('content-container'));
// ws_globals_set(array('ws_html_attributes', 'footer', 'class'), array(''));
ws_globals_set(array('ws_html_attributes', 'footer-content', 'class'), array('content-container'));
/*
$js_forms_abspath = locate_file('js/forms.js');

if(!empty($ws_query['content'])){
  $GLOBALS['locations'] = ws_content($ws_content_root . '/'.$ws_query['langArray'][0].'/locations/locations');
}
*/
$GLOBALS['ws_scripts']['footerend'] = '';
$GLOBALS['ws_scripts']['bodyend']['js_toggle'] = '
<!-- Toggle -->
<script>
function close(target){
	target.style.display = "none";
}
function show(target){
	target.style.display = "inherit";
}
function toggle(id, button){
	var target = document.getElementById(id);
	var computedStyleDisplay = target.currentStyle ? target.currentStyle.display :
    getComputedStyle(target, null).display;
  console.log(computedStyleDisplay);
	if(target.style.display == "none" || computedStyleDisplay == "none"){
		show(target);
	} else {
		close(target);
	}
}
</script>';
/*
$GLOBALS['ws_scripts']['head']['jquery'] = '<script defer="defer" src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>';
if(GOOGLE_API_KEY){
  $GLOBALS['ws_scripts']['head']['googlemaps'] = '<script type="text/javascript" defer="defer" src="https://maps.google.com/maps/api/js?key='.GOOGLE_API_KEY.'&#038;libraries=places&#038;callback=googlemaps_callback"></script>';
} else {
  $GLOBALS['ws_scripts']['bodyend']['js_forms'] = '
  <script>
  function loadJavascript(src, attrs){
    var script = document.createElement("script");
    script.src = src;
    if(attrs){
      console.log(attrs);
    }
    document.getElementsByTagName("head")[0].appendChild(script);
  }
  loadJavascript("'.abspath2url($js_forms_abspath).'", "async");
  </script>';
}
$googlemaps = true;
$jquery_sortable_abspath = locate_file('js/jquery-sortable.min.js');
$GLOBALS['ws_scripts']['head']['jquery_sortable'] = '<script defer="defer" src="'.abspath2url($jquery_sortable_abspath).'"></script>';
if(file_exists($js_forms_abspath)){
  if($googlemaps){
    $GLOBALS['ws_scripts']['bodyend']['googlemaps_callback'] = '
    <script>
    function loadJavascript(src, attrs){
    	var script = document.createElement("script");
    	script.src = src;
    	if(attrs){
    		console.log(attrs);
    	}
    	document.getElementsByTagName("head")[0].appendChild(script);
    }
    function googlemaps_callback(){
    	//Load Google Maps API Dependecies
    	loadJavascript("'.abspath2url($js_forms_abspath).'", "async");
    }
    </script>';
  } else {
	   $GLOBALS['ws_scripts']['head']['js_forms'] = '<script defer="defer" src="'.abspath2url($js_forms_abspath).'"></script>';
  }
}
*/
// Functions
// - Formatting
if(!function_exists('telephone')){
	function telephone($telephone, $args = array()){
	  $default_args = array(
	    'input' => 'simplexml',
	    'output' => 'microdata',// microdata | iso
	    'class' => 'telephone link',// telephone | mobile
	    'type' => 'telephone',// telephone | mobile
	  );
	  $args = array_merge( $default_args, $args );
	  $icon_mobile = '<abbr title="'.__('Mobile').'" class="material-symbols-outlined">smartphone</abbr>';
	  $icon_phone = '<abbr title="'.__('Phone').'" class="material-symbols-outlined">phone</abbr>';
	  if($args['type'] == 'mobile'){
	    $icon = $icon_mobile;
	  } else {
	    $icon = $icon_phone;
	  }
	  if($args['input'] == 'string'){
	    if($args['output'] == 'microdata') {
	      return '<a href="tel:'.telephone($telephone, array('output' => 'iso')).'">'.$telephone.'</a>';
	    } else if($args['output'] == 'iso'){
	      return remove_whitespaces($telephone);
	    }
	  } else if($args['input'] == 'simplexml'){
	    if($args['output'] == 'microdata') {
	      return '<a title="'.__('Call now').'" href="tel:'.telephone($telephone, array('output' => 'iso')).'" class="'.$args['class'].'">'.$icon.'<span class="text">'.$telephone.'</span></a>';
	    } else if($args['output'] == 'iso'){
	      return remove_whitespaces($telephone);
	    }
	  }
	}
}
if(!function_exists('email')){
	function email($email, $args = array()){
	  $default_args = array(
	    'input' => 'simplexml',
	    'output' => 'microdata',
	    'class' => 'work',// work|personal
	  );
	  $args = array_merge( $default_args, $args );
	  $icon = '<abbr title="'.__('Email').'" class="material-symbols-outlined">email</abbr>';
	  if($args['input'] == 'string'){
	    if($args['output'] == 'microdata') {
	      return '<a class="link" title="'.__('Write us by email').'" href="mailto:'.email($email, array('output' => 'iso')).'">'.$email.'</a>';
	    } else if($args['output'] == 'iso'){
	      return $email;
	    }
	  } else if($args['input'] == 'simplexml'){
	    if($args['output'] == 'microdata') {
	      return '<a title="'.__('Write us by email').'" href="mailto:'.email($email, array('output' => 'iso')).'" class="email link">'.$icon.'<span class="text">'.$email.'</span></a>';
	    } else if($args['output'] == 'iso'){
	      return $email;
	    }
	  }
	}
}
if(!function_exists('PostalAddress')){
	function PostalAddress($address, $args = array()){
	  //input: 'simplexml'
	  //output: 'microdata'|'text'
	  //format: 'multiline'|'singleline'
	  $default_args = array(
	    'input' => 'simplexml',
	    'output' => 'microdata',
	    'format' => 'multiline',
	  );
	  $args = array_merge( $default_args, $args );
	  if($args['format'] == 'multiline') {
	    if($args['output'] == 'microdata') {
	      $html = $address->streetAddress.'<br />';
	      $html .= $address->district.'<br />';
	      $html .= $address->postalCode.' ';
	      $html .= $address->addressLocality;
	      $html .= ' ('.$address->addressRegion.')<br />';
	      $html .= $address->administrativeArea;
	      $html .= ', '.$address->addressCountry;
	      return $html;
	    } else if($args['output'] == 'text'){
	      $html = $address->streetAddress.'<br />';
	      $html .= $address->district.'<br />';
	      $html .= $address->postalCode.' ';
	      $html .= $address->addressLocality;
	      $html .= ' ('.$address->addressRegion.')<br />';
	      $html .= $address->administrativeArea;
	      $html .= ', '.$address->addressCountry;
	      return $html;
	    }
	  } elseif($args['format'] == 'singleline') {
	    if($args['output'] == 'microdata') {
	      $html = $address->streetAddress.', ';
	      $html .= $address->postalCode.' ';
	      if(!empty($address->district)){
	        $html .= $address->district.', ';
	      }
	      $html .= $address->addressLocality.' ';
	      $html .= ' ('.$address->addressRegion.'), ';
	      $html .= $address->administrativeArea;
	      $html .= ', '.$address->addressCountry;
	      return $html;
	    } else if($args['output'] == 'text'){
	      $html = $address->streetAddress.', ';
	//      $html .= $address->district.'<br />';
	      $html .= $address->postalCode.' ';
	      $html .= $address->addressLocality.' ';
	      $html .= ' ('.$address->addressRegion.'), ';
	      $html .= $address->administrativeArea;
	      $html .= ', '.$address->addressCountry;
	      return $html;
	    }
	  }
	}
}
if(!function_exists('url')){
	function url($url, $args = array()){
	  $default_args = array(
	    'input' => 'simplexml',
	    'output' => 'microdata',// microdata | iso
	    'class' => 'website link',// telephone | mobile
	    'type' => 'website',// website | link
	    'target' => '_blank',// _blank
	  );
	  $args = array_merge( $default_args, $args );
	  $icon_website = '<abbr title="'.__('Website').'" class="material-symbols-outlined">public</abbr>';
	  $icon_link = '<abbr title="'.__('Link').'" class="material-symbols-outlined">link</abbr>';
	  if(!empty($args['target'])){
	    $target = ' target="'.$args['target'].'"';
	  }
	  $a_text = explode('://', $url)[1];
	  if($args['type'] == 'website'){
	    $icon = $icon_website;
	  } else {
	    $icon = $icon_link;
	  }
	  if($args['input'] == 'string'){
	    if($args['output'] == 'microdata') {
	      return '<a href="'.$url.'">'.$url.'</a>';
	    } else if($args['output'] == 'iso'){
	      return $url;
	    }
	  } else if($args['input'] == 'simplexml'){
	    if($args['output'] == 'microdata') {
	      return '<a title="'.__('Visit website').'" href="'.$url.'" class="'.$args['class'].'"'.$target.'>'.$icon.'<span class="text">'.$a_text.'</span></a>';
	    } else if($args['output'] == 'iso'){
	      return $url;
	    }
	  }
	}
}
// WS Nav
if(!function_exists('ws_nav_items')){
	function ws_nav_items($nav){
	  global $ws_query;
	  $nav_id = $nav['id'];
	  $nav_items = $nav->item;
	  $nav_item_index = 0;
	  foreach($nav_items as $key => $item){
	    if(!empty($item->name) and !empty($item->wspath)){
	      if(ws_normalize_relpath($item->wspath) == $ws_query['wspath']){
	        $GLOBALS['ws_html_attributes'][$nav_id.'-item-'.$nav_item_index]['class'] = 'current-menu-item';
	      }
	      if(!empty($item->class)){
	        $GLOBALS['ws_html_attributes'][$nav_id.'-item-'.$nav_item_index]['class'] = $item->class;
	      }
	  ?>
	      <li<?php echo ws_html_attributes($nav_id.'-item-'.$nav_item_index); ?>><a href="<?php echo ws_href($item->wspath); ?>"><?php echo $item->name->innerHTML(); ?></a></li>
	  <?php
	      $nav_item_index++;
	    }
	  }
	}
}

/* L'header che si restringe al primo scorrimento: markup zero, solo stile e un
 * ascoltatore. */
ob_start();
include_template('template-parts/header-compatto');
$GLOBALS['ws_styles']['head']['header_compatto'] = ob_get_clean();

/* Come si vede la pagina: chiaro, scuro, o come il sistema.
 *
 * Nella TESTA e senza `defer`: la scelta va applicata prima che la pagina si
 * disegni, se no chi ha chiesto scuro vede il lampo bianco. È un file piccolo,
 * e quel lampo si nota molto più di qualche millesimo di secondo. */
$GLOBALS['ws_scripts']['head']['ws_impostazioni'] =
	'<script src="'.$ws_assets_theme_url.'js/impostazioni.js"></script>';

/* Ed è acceso di suo, per tutti i siti. Era una scelta di Meetoo; ma
 * un'intestazione grande all'apertura e discreta durante la lettura non è un
 * gusto di quel sito, è come si legge una pagina lunga ovunque. Un tema figlio
 * che non lo voglia toglie la classe:
 *   $GLOBALS['ws_html_attributes']['header']['class'] = array_diff(
 *       $GLOBALS['ws_html_attributes']['header']['class'], array('header-compatto'));
 */
$GLOBALS['ws_html_attributes']['header']['class'][] = 'header-compatto';

/* La barra dei contatti in cima — dov'è, la mail, il telefono, le lingue — è la
 * prima cosa che deve cedere il posto: serve quando si arriva, non mentre si
 * legge. Ma torna appena si risale in cima, perché chi risale sta arrivando di
 * nuovo; l'header, quello, resta stretto. Nome e logo non se ne vanno mai:
 * sono l'orientamento. */
$GLOBALS['ws_html_attributes']['header-top']['class'][] = 'header-cima-solo';

/* E con lei il sottotitolo: dice cos'è il sito, e serve a chi arriva. Chi sta
 * leggendo lo sa già, e quella riga è quasi tutta l'altezza che l'header si
 * porta dietro — senza, l'intestazione stretta è davvero stretta. Il nome
 * resta. */
$GLOBALS['ws_html_attributes']['header1-headline']['class'][] = 'header-espanso-solo';

/* ---------- Where a page stands in the site map ----------
 *
 * Every page names its parent in its own content, and the site map carries
 * that relation (the `parent` xi:includes in ws_sitemap.wsx). So "what is
 * under /servizi" and "what stands next to /servizi/book-design" are questions
 * the map already answers, and no second list has to be kept in step with it:
 * a page is listed because it exists, and it disappears when it does not.
 *
 * A page whose robots say noindex is left out. It is not finished for readers,
 * so it is not finished for a menu either — and that is the switch to use for
 * a page that exists but is not ready to be found. */
if(!function_exists('ws_sitemap_normalize_path')){
function ws_sitemap_normalize_path($wspath){
	$p = '/' . trim((string)$wspath, "/ \t\n\r");
	return $p;
}
}
if(!function_exists('ws_sitemap_entry')){
/** The map entry of a page, by wspath — or null. */
function ws_sitemap_entry($wspath){
	global $ws_sitemap;
	if(empty($ws_sitemap)) return null;
	$wanted = ws_sitemap_normalize_path($wspath);
	foreach($ws_sitemap->url as $entry){
		if(!empty($entry->wspath) and ws_sitemap_normalize_path($entry->wspath) === $wanted) return $entry;
	}
	return null;
}
}
if(!function_exists('ws_sitemap_children')){
/** The pages under a page, in map order, ready to be found. */
function ws_sitemap_children($wspath){
	global $ws_sitemap;
	$children = array();
	if(empty($ws_sitemap)) return $children;
	$wanted = ws_sitemap_normalize_path($wspath);
	foreach($ws_sitemap->url as $entry){
		if(empty($entry->parent) or empty($entry->parent->wspath)) continue;
		if(ws_sitemap_normalize_path($entry->parent->wspath) !== $wanted) continue;
		if(stripos((string)$entry->robots, 'noindex') !== false) continue;
		$children[] = $entry;
	}
	return $children;
}
}

/* ---------- What the page is, declared once, in the head ----------
 *
 * A page's schema.org lives in its content: `index.json` is JSON-LD, and the
 * XML twin the CMS reads is made from it. The head says it back, as a
 * <script type="application/ld+json">, with three things the content does not
 * author because the site already knows them:
 *   - `hasPart`   the pages under this one, from the site map;
 *   - `isPartOf`  the page above, or the site itself for a top-level page;
 *   - `publisher` the organisation the headings name.
 * Content ids (`services/brand-design`, `…#service`) become the page's own
 * URL; relative `url`s become absolute. The CMS's routing and SEO fields
 * (wspath, query, title, robots…) and the HTML bodies are the page's business,
 * not the declaration's, and stay out of it.
 *
 * A page that still lives in XML alone gets the short form - its type, name,
 * headline, description, dates, and the same three derived relations - so no
 * page is left saying nothing while the rest catch up. */
if(!function_exists('ws_page_jsonld')){
function ws_page_jsonld(){
	global $ws_content, $ws_headings, $rewrite_rule;
	if(empty($ws_content)) return '';
	$wspath = !empty($ws_content->wspath) ? (string)$ws_content->wspath : (string)($rewrite_rule->wspath ?? '');
	if($wspath === '') return '';
	$page_url = ws_href($wspath);
	$page_id = '';

	// The content: its JSON when it has one, its XML otherwise.
	$json_abspath = preg_replace('/\.(xml|wsx|json)$/', '.json', ws_content_abspath());
	$data = null;
	if(is_file($json_abspath)){
		$data = json_decode((string)file_get_contents($json_abspath), true);
	}
	if(is_array($data)){
		$page_id = (string)($data['@id'] ?? '');
		// The CMS's own fields, the SEO ones and the HTML bodies are not schema.org.
		foreach(array('wspath', 'query', 'type', 'parent', 'title', 'keywords', 'changefreq', 'priority', 'robots', 'cta', 'section', 'mainContentOfPage', 'xi:include') as $cms){
			unset($data[$cms]);
		}
	} else {
		$data = array('@context' => 'https://schema.org', '@type' => ws_page_type($ws_content->type, $wspath));
		foreach(array('name', 'headline', 'description', 'inLanguage', 'dateCreated', 'datePublished', 'dateModified') as $field){
			if(!empty($ws_content->$field)) $data[$field] = trim(strip_tags($ws_content->$field->innerHTML()));
		}
	}
	$data['@id'] = $page_url;
	$data['url'] = $page_url;
	if(empty($data['inLanguage'])) $data['inLanguage'] = str_replace('_', '-', ws_lang());

	// The page above - or the site, for a page at the top.
	$parent = !empty($ws_content->parent->wspath) ? ws_sitemap_entry($ws_content->parent->wspath) : null;
	if($parent and ws_sitemap_normalize_path($parent->wspath) !== '/'){
		$data['isPartOf'] = array(
			'@type' => ws_page_type($parent->type, $parent->wspath),
			'@id' => ws_href($parent->wspath),
			'name' => trim(strip_tags(!empty($parent->name) ? $parent->name->innerHTML() : (string)$parent->title)),
		);
	} else if(!empty($ws_headings)){
		$data['isPartOf'] = array(
			'@type' => 'WebSite',
			'@id' => rtrim((string)$ws_headings->url, '/') . '/#website',
			'url' => (string)$ws_headings->url,
			'name' => trim(strip_tags(!empty($ws_headings->title) ? $ws_headings->title->innerHTML() : '')),
		);
	}
	// The pages under this one.
	$parts = array();
	foreach(ws_sitemap_children($wspath) as $child){
		$part = array(
			'@type' => ws_page_type($child->type, $child->wspath),
			'@id' => ws_href($child->wspath),
			'name' => trim(strip_tags(!empty($child->name) ? $child->name->innerHTML() : (string)$child->title)),
		);
		if(!empty($child->description)) $part['description'] = trim(strip_tags($child->description->innerHTML()));
		$parts[] = $part;
	}
	if($parts) $data['hasPart'] = $parts;
	// Who publishes it.
	if(!empty($ws_headings->mainEntity->name)){
		$data['publisher'] = array(
			'@type' => 'Organization',
			'name' => trim(strip_tags($ws_headings->mainEntity->name->innerHTML())),
			'url' => (string)$ws_headings->url,
		);
		if(!empty($ws_headings->mainEntity->image[0]->source->relpath)){
			$data['publisher']['logo'] = ws_contents_url() . (string)$ws_headings->mainEntity->image[0]->source->relpath;
		}
	}

	ws_jsonld_clean($data, $page_id, $page_url);
	$json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
	if($json === false) return '';
	// `</script` inside a string would close the block early; JSON allows the escape.
	$json = str_replace('</', '<\/', $json);
	return "\n<script type=\"application/ld+json\">\n" . $json . "\n</script>";
}
}
if(!function_exists('ws_page_type')){
/**
 * The schema.org type of a PAGE, from what its content or map entry says.
 * A page's `type` is either a page type (ContactPage, AboutPage…) or the type
 * of what the page is about (Service, Product); only the first names the
 * page. The rest are a WebPage - a CollectionPage when pages stand under it.
 */
function ws_page_type($type, $wspath){
	$type = trim((string)$type);
	if($type !== '' and substr($type, -4) === 'Page') return $type;
	return ws_sitemap_children($wspath) ? 'CollectionPage' : 'WebPage';
}
}
if(!function_exists('ws_jsonld_clean')){
/**
 * The tree made fit to declare, top to bottom.
 * Content ids become URLs: the page's own id (`services/brand-design`) is
 * its URL, an anchor on it (`services/brand-design#service`) an anchor on
 * that URL; a `url` that begins with `/` is on this site. Anything else is
 * left as written. And a text that carries HTML - a description with a
 * table in it, written to be drawn - is declared as text: tags become
 * spaces, entities become characters.
 */
function ws_jsonld_clean(array &$node, $page_id, $page_url){
	foreach($node as $key => &$value){
		if(is_array($value)){
			ws_jsonld_clean($value, $page_id, $page_url);
		} else if(!is_string($value)){
			continue;
		} else if($key === '@id'){
			if($page_id !== '' and $value === $page_id){
				$value = $page_url;
			} else if($page_id !== '' and strpos($value, $page_id . '#') === 0){
				$value = $page_url . substr($value, strlen($page_id));
			}
		} else if($key === 'url'){
			if(strpos($value, '/') === 0) $value = ws_href($value);
		} else if(strpos($value, '<') !== false or strpos($value, '&') !== false){
			$value = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(preg_replace('/<[^>]+>/', ' ', $value)), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
		}
	}
}
}
$GLOBALS['ws_scripts']['head']['jsonld'] = ws_page_jsonld();
?>
