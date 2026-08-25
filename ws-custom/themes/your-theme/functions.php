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
$ws_parent_theme_url = ws_parent_theme_url();
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
	'<link rel="stylesheet" type="text/css" media="all" href="'.$ws_parent_theme_url.'css/all.css" />',
	'<link rel="stylesheet" type="text/css" media="screen and (max-width: 999px)" href="'.$ws_parent_theme_url.'css/vgrid.css" />',
	'<link rel="stylesheet" type="text/css" media="screen and (min-width: 1000px)" href="'.$ws_parent_theme_url.'css/hgrid.css" />',
	'<link rel="stylesheet" type="text/css" media="screen and (min-width: 1280px)" href="'.$ws_parent_theme_url.'css/maxgrid.css" />'
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
$GLOBALS['ws_html_attributes']['body'] = array(
  'itemscope' => null,
  'itemtype' => "http://schema.org/WebPage"
);
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
 * ascoltatore. Si accende aggiungendo la classe `header-compatto` all'header —
 * lo fa il tema figlio che lo vuole (vedi meetoo/functions.php). */
ob_start();
include_template('template-parts/header-compatto');
$GLOBALS['ws_styles']['head']['header_compatto'] = ob_get_clean();
?>