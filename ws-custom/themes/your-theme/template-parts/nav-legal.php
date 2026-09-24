<?php
// The Footer Legal Menu Php template
// @package WS
// @subpackage Localbiz
// @since WS 1.0
global $ws_contentmap, $ws_content_root, $ws_content_root_abspath;
/*
 * Il menu legale: quello della lingua, se c'è; poi quello del sito; e se non
 * c'è nessuno dei due, la mappa.
 *
 * Terza copia dello stesso difetto, dopo `nav1.php` e `header.php`: la seconda
 * condizione — `if($percorso.'/nav-legal.xml')` — verifica una STRINGA non
 * vuota, quindi è sempre vera, e quando il file non c'è `ws_content()` torna
 * `false`, su cui `->count()` è un errore fatale.
 *
 * Si vede la prima volta che una lingua quel menu non ce l'ha: con il 404 di
 * isotype disegnato sulla cartella `en_US`, che ha quasi niente dentro.
 */
$nav_legal = null;
foreach(array($ws_content_root.'/'.ws_locale().'/nav-legal', $ws_content_root.'/nav-legal') as $nav_legal_relpath){
  $nav_legal_abspath = ws_root_abspath().'/'.WS_CONTENTS_RELPATH.'/'.$nav_legal_relpath;
  if(file_exists($nav_legal_abspath.'.xml') or file_exists($nav_legal_abspath.'.wsx') or file_exists($nav_legal_abspath.'.json')){
    $nav_legal = ws_content($nav_legal_relpath);
    if($nav_legal){
      break;
    }
  }
}
if(!$nav_legal or $nav_legal->count() == 0){
  $nav_legal = $ws_contentmap;
}
if(isset($nav_legal)){
?>
<nav <?php echo ws_html_attributes('nav-legal', array('id'=>'nav-legal', 'class' => array('nav-legal', 'nav', 'vertical'))); ?>>
	<h3><?php _e('Legal informations'); ?></h3>
  <div>
	 <ul><?php ws_nav_items($nav_legal); ?></ul>
  </div>
</nav>
<?php
}
?>