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

/* The icons of this site, if it has any.
 *
 * WHERE they are is the brand's business - `brand/index.xml`, element
 * `favicons` - and the headings carry it here. It used to be computed from the
 * content root instead, and that was wrong twice over: the English root of
 * isotype has no icons of its own, it uses the Italian ones, and no site
 * anywhere keeps a `favicons/` folder at its content root. So the path pointed
 * at nothing, for everybody.
 *
 * DECLARED OR SILENT: a site that says nothing gets no icon links at all,
 * because four links to files that are not there are worse than no links. That
 * is also what every page looked like until today - a later `ws_globals_set`
 * was wiping this list before it reached the head, so nobody had seen these
 * four lines work, and nobody had seen them fail either. */
if(!empty($GLOBALS['ws_headings']->favicons->relpath)){
	/* `mount => false`: le icone sono un file, non un indirizzo del sito. Su un
	   sito innestato il prefisso le manderebbe a /meetoo/ws-custom/..., che non
	   esiste - lo stesso inciampo di `get_media`. */
	$ws_favicons_url = ws_href(WS_CONTENTS_RELPATH.'/'.trim((string)$GLOBALS['ws_headings']->favicons->relpath), array('mount' => false));
	ws_globals_set(array('ws_links'), array(
		'<link rel="apple-touch-icon" sizes="180x180" href="'.$ws_favicons_url.'/apple-touch-icon.png" />',
		'<link rel="icon" type="image/png" sizes="32x32" href="'.$ws_favicons_url.'/favicon-32x32.png" />',
		'<link rel="icon" type="image/png" sizes="16x16" href="'.$ws_favicons_url.'/favicon-16x16.png" />',
		'<link rel="manifest" href="'.$ws_favicons_url.'/site.webmanifest" />'
	));
}

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
/* L'header per primo, e in un foglio suo: un sito puo' volere questo e non il
   resto. Vedi la testa di header-abovethefold.css. */
ws_stile_se_esiste('header', 'all', 'css/header-abovethefold.css');
ws_stile_se_esiste('all', 'all', 'css/all-abovethefold.css');
ws_stile_se_esiste('screen', 'screen', 'css/screen-abovethefold.css');
ws_stile_se_esiste('vgrid', 'screen and (max-width: 999px)', 'css/vgrid-abovethefold.css');
ws_stile_se_esiste('hgrid', 'screen and (min-width: 1000px)', 'css/hgrid-abovethefold.css');
ws_stile_se_esiste('maxgrid', 'screen and (min-width: 1280px)', 'css/maxgrid-abovethefold.css');
// 2. Linked
/* APPENDED, not set: `ws_globals_set` replaces at the leaf - it says so itself
   in ws-core/templates.php - so this call used to wipe the icons declared
   above, and no site on this theme has ever served a favicon. A list is added
   to one item at a time. */
$GLOBALS['ws_links'][] = '<link rel="stylesheet" type="text/css" media="all" href="'.$ws_assets_theme_url.'css/all.css" />';
$GLOBALS['ws_links'][] = '<link rel="stylesheet" type="text/css" media="screen and (max-width: 999px)" href="'.$ws_assets_theme_url.'css/vgrid.css" />';
$GLOBALS['ws_links'][] = '<link rel="stylesheet" type="text/css" media="screen and (min-width: 1000px)" href="'.$ws_assets_theme_url.'css/hgrid.css" />';
$GLOBALS['ws_links'][] = '<link rel="stylesheet" type="text/css" media="screen and (min-width: 1280px)" href="'.$ws_assets_theme_url.'css/maxgrid.css" />';
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
	    /* One line, and the punctuation belongs to the pieces that are there.
	     *
	     * This used to concatenate every field with its separator whether the
	     * field had a value or not, so an address with nothing in it printed
	     * ", 0, ," - which is what a brand-new site showed in its footer, and
	     * what any site shows for a location it has not filled in yet. The
	     * commas are put BETWEEN the parts that exist, so an empty address
	     * prints nothing at all, which is the truth. */
	    $parts = array();
	    $street = trim((string)$address->streetAddress);
	    if($street !== ''){ $parts[] = $street; }

	    $town = trim(trim((string)$address->postalCode).' '.trim((string)$address->addressLocality));
	    $region = trim((string)$address->addressRegion);
	    if($region !== ''){ $town = trim($town.' ('.$region.')'); }
	    if($town !== ''){ $parts[] = $town; }

	    $district = trim((string)$address->district);
	    if($district !== ''){ array_splice($parts, 1, 0, array($district)); }

	    foreach(array($address->administrativeArea, $address->addressCountry) as $more){
	      $more = trim((string)$more);
	      if($more !== ''){ $parts[] = $more; }
	    }
	    return implode(', ', $parts);
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
	/* `$args['icons']`: le voci escono con la loro icona davanti. Il menu
	   orizzontale non le vuole — lo spazio è poco e i nomi bastano — il
	   cassetto sì, perché lì una fila di righe tutte uguali si legge peggio. */
	function ws_nav_items($nav, $args = array()){
	  global $ws_query;
	  $con_icone = !empty($args['icons']);
	  /* DOVE porta una voce e CON CHE COSA si annuncia sono due domande a parte,
	     perche' un sito figlio puo' rispondere diversamente: Meetoo scrive nelle
	     voci l'@id di un contenuto e l'indirizzo lo chiede alla sua mappa. Sono
	     due funzioni sostituibili, non due `if` qui dentro: questo file non deve
	     sapere che Meetoo esiste. */
	  $nav_id = $nav['id'];
	  $nav_items = $nav->item;
	  $nav_item_index = 0;
	  foreach($nav_items as $key => $item){
	    $href = ws_nav_href($item);
	    if(!empty($item->name) and $href !== ''){
	      if(trim(parse_url($href, PHP_URL_PATH) ?: '', '/') === trim(ws_mount().'/'.trim((string)($ws_query['wspath'] ?? ''), '/'), '/')){
	        $GLOBALS['ws_html_attributes'][$nav_id.'-item-'.$nav_item_index]['class'] = 'current-menu-item';
	      }
	      if(!empty($item->class)){
	        $GLOBALS['ws_html_attributes'][$nav_id.'-item-'.$nav_item_index]['class'] = $item->class;
	      }
	  ?>
	      <li<?php echo ws_html_attributes($nav_id.'-item-'.$nav_item_index); ?>><a href="<?php echo $href; ?>"><?php if($con_icone){ echo ws_nav_icon($item); } ?><?php echo $item->name->innerHTML(); ?></a></li>
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
$GLOBALS['ws_scripts']['head']['ws_preferences'] =
	'<script src="'.$ws_assets_theme_url.'js/preferences.js"></script>';

/* Il cassetto del menu, invece, può aspettare: non disegna niente prima che si
 * tocchi l'hamburger, e il menu orizzontale intanto c'è già. Quindi in fondo al
 * corpo, dove non trattiene la pagina. */
$GLOBALS['ws_scripts']['bodyend']['ws_drawer'] =
	'<script defer="defer" src="'.$ws_assets_theme_url.'js/drawer.js"></script>';

/* E le finestre: una convenzione sola per aprirle e chiuderle tutte - le
 * preferenze, il profilo, «condividi». Anche questa puo' aspettare: una
 * finestra chiusa e' chiusa comunque. */
$GLOBALS['ws_scripts']['bodyend']['ws_modal'] =
	'<script defer="defer" src="'.$ws_assets_theme_url.'js/modal.js"></script>';

/* E le due voci di «Condividi» che senza JavaScript non potrebbero esistere:
 * copiare negli appunti e il foglio di sistema. Tutte le altre sono
 * collegamenti e stanno nel markup, cosi' funzionano anche senza. */
$GLOBALS['ws_scripts']['bodyend']['ws_share'] =
	'<script defer="defer" src="'.$ws_assets_theme_url.'js/share.js"></script>';

/* Ed è acceso di suo, per tutti i siti. Era una scelta di Meetoo; ma
 * un'intestazione grande all'apertura e discreta durante la lettura non è un
 * gusto di quel sito, è come si legge una pagina lunga ovunque. Un tema figlio
 * che non lo voglia toglie la classe:
 *   $GLOBALS['ws_html_attributes']['header']['class'] = array_diff(
 *       $GLOBALS['ws_html_attributes']['header']['class'], array('header-compatto'));
 */
$GLOBALS['ws_html_attributes']['header']['class'][] = 'header-compatto';

/* COME SI ARRIVA AL MENU, dichiarato dal sito in `contents/<sito>/ws-config.php`
 * e scritto da ws-admin/sites.php.
 *
 *   'responsive' (il predefinito) - il menu orizzontale dove ci sta, il cassetto
 *                 dove non ci sta. Mai tutti e due: due porte per la stessa
 *                 stanza si contano come due stanze.
 *   'drawer'    - solo l'hamburger, a qualunque larghezza.
 *
 * Arriva come attributo su <html> e non come classe su un pezzo dell'header,
 * perche' la scelta riguarda due elementi lontani - il menu e l'hamburger - e
 * un attributo sulla radice li raggiunge tutti e due senza che nessuno dei due
 * debba sapere dell'altro. Il foglio di stile fa il resto. */
$GLOBALS['ws_html_attributes']['html']['data-menu'] =
	(defined('WS_SITE_MENU') && WS_SITE_MENU === 'drawer') ? 'drawer' : 'responsive';

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
 * a page that exists but is not ready to be found.
 *
 * The map the CMS routes with is ONE for every site it serves (isotype,
 * your-website, meetoo, the admin's pages), and every site's top pages name
 * `/` as their parent. Asked for the pages under `/`, the map would answer
 * with all of them - and /servizi listed your-website's /menu and /news among
 * its siblings. So every answer here is confined to the site the request is
 * for: an entry belongs to the site its query's `content=` names, the same
 * key query.php routes by. */
if(!function_exists('ws_sitemap_normalize_path')){
function ws_sitemap_normalize_path($wspath){
	$p = '/' . trim((string)$wspath, "/ \t\n\r");
	return $p;
}
}
if(!function_exists('ws_sitemap_site_of')){
/** The site a map entry belongs to: the first segment of its query's `content=`, '' if none. */
function ws_sitemap_site_of($entry){
	if(!empty($entry->query) and preg_match('/(?:^|[?&;])content=([^\/&;\s]+)/', (string)$entry->query, $m)) return $m[1];
	return '';
}
}
if(!function_exists('ws_sitemap_is_ours')){
/** Whether a map entry is a page of the site this request is for. */
function ws_sitemap_is_ours($entry){
	static $site = null;
	if($site === null) $site = (string)ws_content_root();
	return $site === '' or ws_sitemap_site_of($entry) === $site;
}
}
if(!function_exists('ws_sitemap_entry')){
/** The map entry of a page of this site, by wspath — or null. */
function ws_sitemap_entry($wspath){
	global $ws_sitemap;
	if(empty($ws_sitemap)) return null;
	$wanted = ws_sitemap_normalize_path($wspath);
	foreach($ws_sitemap->url as $entry){
		if(!empty($entry->wspath) and ws_sitemap_normalize_path($entry->wspath) === $wanted and ws_sitemap_is_ours($entry)) return $entry;
	}
	return null;
}
}
if(!function_exists('ws_sitemap_children')){
/** The pages of this site under a page, in map order, ready to be found. */
function ws_sitemap_children($wspath){
	global $ws_sitemap;
	$children = array();
	if(empty($ws_sitemap)) return $children;
	$wanted = ws_sitemap_normalize_path($wspath);
	foreach($ws_sitemap->url as $entry){
		if(empty($entry->parent) or empty($entry->parent->wspath)) continue;
		if(ws_sitemap_normalize_path($entry->parent->wspath) !== $wanted) continue;
		if(stripos((string)$entry->robots, 'noindex') !== false) continue;
		if(!ws_sitemap_is_ours($entry)) continue;
		$children[] = $entry;
	}
	return $children;
}
}
if(!function_exists('ws_sitemap_of_type')){
/** The first page of this site the map gives a type - a role - to (ContactPage, PrivacyPage…), or null. */
function ws_sitemap_of_type($type){
	global $ws_sitemap;
	if(empty($ws_sitemap)) return null;
	foreach($ws_sitemap->url as $entry){
		if(trim((string)$entry->type) === $type and ws_sitemap_is_ours($entry)) return $entry;
	}
	return null;
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
		foreach(array('wspath', 'query', 'type', 'parent', 'title', 'keywords', 'changefreq', 'priority', 'robots', 'cta', 'section', 'mainContentOfPage', 'output', 'xi:include') as $cms){
			unset($data[$cms]);
		}
		// A page's ROLE in the CMS's own vocabulary (ws:PrivacyPage) is for
		// the CMS to look up, not for the engines to read: the head declares
		// the schema.org types alone, and drops the vocabulary with them.
		$types = array_values(array_filter((array)($data['@type'] ?? array()), function($t){ return strpos((string)$t, ':') === false; }));
		$data['@type'] = count($types) === 1 ? $types[0] : ($types ?: 'WebPage');
		if(is_array($data['@context'] ?? null)) $data['@context'] = 'https://schema.org';
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
	// The map's `type` is the page's role or what it is about; only a
	// schema.org page type among them names the page itself.
	static $pages = array('AboutPage', 'CheckoutPage', 'CollectionPage', 'ContactPage', 'FAQPage', 'ItemPage', 'MedicalWebPage', 'ProfilePage', 'QAPage', 'RealEstateListing', 'SearchResultsPage', 'WebPage');
	if(in_array($type, $pages, true)) return $type;
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


/*
 * Resolving a reference.
 *
 * Content in this CMS names other content by `@id` and never copies it: the
 * site's headings say `{"@id": "places/IT00122/…"}` and the address, the
 * telephone and the VAT number live in that one place. Somebody has to follow
 * the pointer, and until now nobody did — which is why a site whose business
 * was properly referenced showed an empty footer.
 *
 * `ws_entity()` follows it. Loading goes through `ws_content()`, the CMS's own
 * reader, so the referenced file gets the same lazy twin as any other content
 * and no second way of opening a file appears in the theme.
 *
 * Answers are kept for the request: a page asks for the same business in the
 * header, in the footer and beside every location, and reading it four times
 * from disk to get the same four answers is work nobody asked for.
 */
if(!function_exists('ws_entity')){
	function ws_entity($id){
		global $ws_content_root;
		static $cache = array();

		$id = trim((string)$id, '/');
		/* A reference is a path inside the locale, and nothing else: no
		 * absolute path, no climbing out of the content tree. */
		if($id === '' or strpos($id, '..') !== false or $id[0] === '/'){
			return null;
		}
		if(array_key_exists($id, $cache)){
			return $cache[$id];
		}

		/* The locale folder, as header.php already asks for it. */
		$content = ws_content($ws_content_root.'/'.ws_locale().'/'.$id);
		/* The content of an entity is a page ABOUT it: what the caller wants is
		 * the thing, not the page around it. */
		$entity = ($content and isset($content->mainEntity)) ? $content->mainEntity : $content;
		$cache[$id] = $entity ?: null;
		return $cache[$id];
	}
}

/*
 * The @id a node points at, whichever way the twin wrote it.
 *
 * Going from JSON to XML, the `@id` of the root becomes the `id` attribute and
 * the `@id` of a node inside becomes `xlink:href` — because there it is a
 * REFERENCE to something else, not this thing's own name. Both are looked at,
 * so a caller does not have to know where in the tree it is standing. (Meetoo
 * has the same function under its own name; when Meetoo is a WS site like the
 * others, this is the one that stays.)
 */
if(!function_exists('ws_reference')){
	function ws_reference($node){
		if(!is_object($node)){
			return '';
		}
		$href = $node->attributes('http://www.w3.org/1999/xlink');
		$id = ($href !== null and isset($href->href)) ? (string)$href->href : '';
		if($id === ''){
			$own = $node->attributes();
			$id = ($own !== null and isset($own->id)) ? (string)$own->id : '';
		}
		return trim($id);
	}
}

/*
 * A node, with what it points at behind it.
 *
 * Returns the referenced entity when the node is a reference and the entity can
 * be read, and the node itself otherwise. That is what lets a template write
 * `ws_resolved($ws_headings->mainEntity)->vatID` without asking first whether
 * the business was written out or pointed at: both are answered the same way,
 * and a reference that cannot be followed degrades to what is on the node
 * rather than to a fatal error.
 */
if(!function_exists('ws_resolved')){
	function ws_resolved($node){
		$id = ws_reference($node);
		if($id === ''){
			return $node;
		}
		$entity = ws_entity($id);
		return $entity ?: $node;
	}
}

/*
 * The opening hours of a place, as a definition list.
 *
 * A function and not a template part on purpose: `include_template()` loads
 * with `require_once`, so a page with two locations would print the first
 * one's hours and then silently nothing for the second.
 *
 * schema.org records one `OpeningHoursSpecification` per stretch, so a place
 * that shuts for lunch has two for that day; they are grouped back under the
 * day here, which is how a person reads them ("Monday 9:30-13:00, 15:00-19:00")
 * and not how the data is shaped.
 *
 * The order is the week's, starting on Monday, whatever order the file is in:
 * hours that come back from Google start on Sunday, and a list that begins on
 * Sunday looks like a mistake to everyone who reads it.
 */
if(!function_exists('ws_opening_hours')){
	function ws_opening_hours($specs){
		if(empty($specs)){
			return '';
		}
		$week = array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday');
		$names = array(
			'Monday' => __('Monday'), 'Tuesday' => __('Tuesday'), 'Wednesday' => __('Wednesday'),
			'Thursday' => __('Thursday'), 'Friday' => __('Friday'), 'Saturday' => __('Saturday'),
			'Sunday' => __('Sunday'),
		);

		$byDay = array();
		foreach($specs as $spec){
			/* The day may be written as a bare word or as a schema.org URL
			 * (http://schema.org/Monday): the last segment is the day either way. */
			$day = trim((string)$spec->dayOfWeek);
			if($day === ''){ continue; }
			$day = substr($day, strrpos($day, '/') === false ? 0 : strrpos($day, '/') + 1);
			$opens = trim((string)$spec->opens);
			$closes = trim((string)$spec->closes);
			if(!isset($byDay[$day])){ $byDay[$day] = array(); }
			if(trim((string)$spec->isClosed) === 'true' or ($opens === '' and $closes === '')){
				$byDay[$day][] = __('Closed');
			} else {
				$byDay[$day][] = substr($opens, 0, 5).'–'.substr($closes, 0, 5);
			}
		}
		if(!$byDay){
			return '';
		}

		$html = '<dl class="opening-hours">';
		foreach($week as $day){
			if(empty($byDay[$day])){ continue; }
			$html .= '<dt class="opening-hours-day">'.($names[$day] ?? $day).'</dt>';
			$html .= '<dd class="opening-hours-when">'.implode(', ', $byDay[$day]).'</dd>';
		}
		return $html.'</dl>';
	}
}

if(!function_exists('ws_nav_href')){
	/**
	 * Dove porta una voce di menu. Qui: l'indirizzo che la voce si scrive.
	 *
	 * PASSA DA UN FILTRO, e non e' una cerimonia: un tema figlio NON puo'
	 * vincere ridefinendo questa funzione. Il caricamento a cascata
	 * (`locate_file`, ramo `cascading`) scorre `array_reverse($ws_query['themes'])`,
	 * cioe' il GENITORE per primo: quando tocca al figlio la funzione esiste gia'
	 * e il suo `function_exists` la salta. E' anche il motivo per cui Meetoo, nel
	 * suo functions.php, DISFA i globali del genitore invece di prevenirli.
	 *
	 * Il filtro si registra al caricamento e si applica al disegno, quindi
	 * l'ordine non conta: e' la sola strada per cui un figlio decide davvero.
	 */
	function ws_nav_href($item){
		$href = !empty($item->wspath) ? ws_href($item->wspath) : '';
		return apply_filters('ws_nav_href', $href, $item);
	}
}

if(!function_exists('ws_nav_icon')){
	/**
	 * Con che cosa si annuncia una voce di menu.
	 *
	 * Due forme in giro, e si accettano tutte e due perche' esistono tutte e due:
	 * isotype scrive nel contenuto lo `<span>` gia' fatto, Meetoo scrive il nome
	 * del simbolo e basta (`home`). La seconda e' quella giusta - un contenuto
	 * dice che cosa, non come si veste - e la prima si convertira'; intanto
	 * riconoscerle si paga con una riga.
	 */
	function ws_nav_icon($item){
		if(empty($item->icon)){
			return '';
		}
		/* Anche questa passa dal filtro, per la ragione scritta qui sopra. */
		$dentro = trim($item->icon->innerHTML());
		if($dentro === ''){
			return '';
		}
		$html = (strpos($dentro, '<') !== false)
			? $dentro
			: '<span class="material-symbols-outlined" aria-hidden="true">'
				. htmlspecialchars($dentro, ENT_QUOTES, 'UTF-8') . '</span>';
		return apply_filters('ws_nav_icon', $html, $item);
	}
}

if(!function_exists('ws_media_pair')){
	/**
	 * A drawing, and the classes that decide when it is on screen.
	 *
	 * Given one image it prints one picture, wearing `ws-media-plate`: that is
	 * the white plate a drawing made for a light ground needs to survive a dark
	 * one. Given two it prints both, `ws-only-light` and `ws-only-dark`, and the
	 * stylesheet shows the one the chosen colour scheme asks for - see
	 * css/all-abovethefold.css for why the choice is made there and not with a
	 * `<source media>` inside the picture.
	 *
	 * One place, because three callers needed it: the mark in the header, the
	 * mark in the drawer, and the primary image of a page.
	 */
	function ws_media_pair($light, $dark = null, $args = array()){
		if(empty($light)){
			return '';
		}
		$piastra = !isset($args['plate']) || $args['plate'];
		unset($args['plate']);
		$was = isset($args['pictureAttributes']['class']) ? $args['pictureAttributes']['class'].' ' : '';
		if(empty($dark)){
			if($piastra){ $args['pictureAttributes']['class'] = $was.'ws-media-plate'; }
			return get_media($light, $args);
		}
		$html = '';
		foreach(array('light' => $light, 'dark' => $dark) as $scheme => $image){
			$one = $args;
			$one['pictureAttributes']['class'] = $was.'ws-only-'.$scheme;
			$html .= get_media($image, $one);
		}
		return $html;
	}
}

if(!function_exists('ws_brand_mark')){
	/**
	 * The mark of the site - and whether its name still has to be written out.
	 *
	 * TWO SHAPES, told apart by the id the brand gives them. It is the same
	 * trick as `-neg`: the id carries the meaning, so nothing new is invented
	 * and no template has to know the name of a site.
	 *
	 *   `logotype` - a sign that already CONTAINS the name, the way Meetoo's
	 *                wordmark does. Writing the name beside it would write it
	 *                twice, so the header prints the sign alone.
	 *   `logo`     - a sign that stands BESIDE the name, the way isotype's
	 *                symbol does. The name, and the headline if the brand has
	 *                one, are printed as text next to it - which is also what
	 *                makes them selectable, translatable and readable aloud.
	 *
	 * Either may have its `-neg` twin for the dark; the pair is handled above.
	 * A brand that declares neither gets no mark and keeps its name, which is
	 * the least wrong thing to show.
	 *
	 * @return array{html: string, name: bool}
	 */
	function ws_brand_mark($args = array()){
		global $ws_headings;
		if(empty($ws_headings)){
			return array('html' => '', 'name' => true);
		}
		foreach(array('logotype', 'logo') as $id){
			$light = $ws_headings->xpath("id('".$id."')");
			if(!empty($light)){
				$dark = $ws_headings->xpath("id('".$id."-neg')");
				/* NIENTE PIASTRA sul marchio. La piastra e' un'ipotesi - «questo
				   disegno sul fondo scuro sparirebbe, mettiamogli il bianco
				   sotto» - e su una marca l'ipotesi non serve: se il marchio ha
				   bisogno di una versione scura, la marca la dichiara, ed e'
				   quella la risposta giusta. Il marchio di Meetoo e' colorato e
				   sullo scuro si legge da solo: la piastra gli metteva un
				   riquadro bianco intorno per niente. Resta dove serve davvero,
				   sull'immagine di una pagina, che puo' essere qualunque cosa e
				   non la sceglie chi ha disegnato la marca. */
				return array(
					'html' => ws_media_pair($light, !empty($dark) ? $dark : null, $args + array('plate' => false)),
					'name' => ($id === 'logo'),
				);
			}
		}
		return array('html' => '', 'name' => true);
	}
}

if(!function_exists('ws_figure_media')){
	/**
	 * The image of a figure, in the version the current colour scheme asks for.
	 *
	 * A figure may carry the same drawing twice - one made for a light ground,
	 * one for a dark one - told apart by an id ending in `-neg`. It finds the
	 * pair and hands it to `ws_media_pair()`.
	 *
	 * A figure that carries one image comes out exactly as `get_media` would
	 * have printed it. That is the point: every template can call this one and
	 * no template has to ask whether a dark twin exists.
	 */
	function ws_figure_media($figure, $args = array()){
		if(empty($figure) or empty($figure->image)){
			return '';
		}
		$light = null;
		$dark = null;
		foreach($figure->image as $image){
			$id = (string)$image->attributes('xml', true)->id;
			if(substr($id, -4) === '-neg'){
				if($dark === null){ $dark = $image; }
			} else if($light === null){
				$light = $image;
			}
		}
		if($light === null){ $light = $figure->image; }
		return ws_media_pair($light, $dark, $args);
	}
}
