<?php
global $ws_query, $ws_content_root;
$js_forms_abspath = locate_file('js/forms.js');

if(!empty($ws_query['content'])){
  $GLOBALS['locations'] = ws_content($ws_content_root . '/it/locations/locations');
}
$GLOBALS['theme_breakpoints'] = array(
  'vgrid' => '@media screen and (max-width: 999px)',
  'hgrid' => '@media screen and (min-width: 1000px)',
  'maxgrid' => '@media screen and (min-width: 1280px)',
);
$GLOBALS['ws_stylesheets']['head']['abovethefold'] = array(
  'all' => array('css/all-abovethefold.css'),
  'vgrid' => array('css/vgrid-abovethefold.css'),
  'hgrid' => array('css/hgrid-abovethefold.css'),
);
$GLOBALS['ws_scripts']['bodyend']['js_toggle'] = '
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
$GLOBALS['ws_scripts']['head']['gtag'] = GTAG;
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
$GLOBALS['ws_html_attributes']['body'] = array(
  'itemscope' => null,
  'itemtype' => "http://schema.org/WebPage"
);
ws_array_merge($GLOBALS, array('ws_html_attributes', 'header1', 'id'), array('header1'));
function telephone($telephone, $args = array()){
  $default_args = array(
    'input' => 'simplexml',
    'output' => 'microdata',// microdata | iso
    'class' => 'telephone link',// telephone | mobile
    'type' => 'telephone',// telephone | mobile
  );
  $args = array_merge( $default_args, $args );
  $icon_mobile = '<abbr title="'.__('Mobile').'" class="material-icons">smartphone</abbr>';
  $icon_phone = '<abbr title="'.__('Phone').'" class="material-icons">phone</abbr>';
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
function email($email, $args = array()){
  $default_args = array(
    'input' => 'simplexml',
    'output' => 'microdata',
    'class' => 'work',// work|personal
  );
  $args = array_merge( $default_args, $args );
  $icon = '<abbr title="'.__('Email').'" class="material-icons">email</abbr>';
  if($args['input'] == 'string'){
    if($args['output'] == 'microdata') {
      return '<a class="link" title="'.__('Write us by email').'" href="tel:'.email($email, array('output' => 'iso')).'">'.$email.'</a>';
    } else if($args['output'] == 'iso'){
      return $email;
    }
  } else if($args['input'] == 'simplexml'){
    if($args['output'] == 'microdata') {
      return '<a title="'.__('Write us by email').'" href="tel:'.email($email, array('output' => 'iso')).'" class="email link">'.$icon.'<span class="text">'.$email.'</span></a>';
    } else if($args['output'] == 'iso'){
      return $email;
    }
  }
}
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
function url($url, $args = array()){
  $default_args = array(
    'input' => 'simplexml',
    'output' => 'microdata',// microdata | iso
    'class' => 'website link',// telephone | mobile
    'type' => 'website',// website | link
    'target' => '_blank',// _blank
  );
  $args = array_merge( $default_args, $args );
  $icon_website = '<abbr title="'.__('Website').'" class="material-icons">public</abbr>';
  $icon_link = '<abbr title="'.__('Link').'" class="material-icons">link</abbr>';
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
?>
