<?php
$ws_theme_url = ws_theme_url();
$ws_content_root_url = ws_content_root_url();
// Web font loader https://github.com/typekit/webfontloader
$GLOBALS['ws_webfonts'] = '{
	google: {
		families: ["Material+Icons", "Titillium+Web:200,200i,300,300i,400,400i,600,600i,700,700i,900", "Raleway:300,300i,400,400i,600,600i,700,700i"]
	},
	custom: {
		families: ["social-icons;ambroise"],
		urls: ["'.$ws_theme_url.'css/social_icons.css", "https://use.typekit.net/bvl7tyj.css"]
	}
}';
$GLOBALS['ws_scripts']['head']['js_webfont'] = '
<script defer="defer" onload="webfont_load();" src="'.is_ssl('http').'//ajax.googleapis.com/ajax/libs/webfont/1.6.26/webfont.js"></script>
<script>
  function webfont_load(){ WebFont.load('.$GLOBALS['ws_webfonts'].'); }
</script>';
ws_array_merge($GLOBALS, array('ws_html_attributes', 'body', 'class'), array('header-fixed', 'has-breadcrumbs'));
ws_array_merge($GLOBALS, array('ws_html_attributes', 'header-top', 'class'), array('background-color2'));
ws_array_merge($GLOBALS, array('ws_html_attributes', 'header', 'class'), array('background-color1', 'background-darkcolor', 'shadow-bottom'));
//ws_array_merge($GLOBALS, array('ws_html_attributes', 'header', 'class'), array('background-color1-transparent', 'background-darkcolor', 'shadow-bottom'));
ws_array_merge($GLOBALS, array('ws_html_attributes', 'header1', 'class'), array('flex', 'align-middle'));
ws_array_merge($GLOBALS, array('ws_html_attributes', 'header1-content', 'class'), array('flex', 'content-container', 'align-middle'));
ws_array_merge($GLOBALS, array('ws_html_attributes', 'header1-headline', 'class'), array('display-none'));
ws_array_merge($GLOBALS, array('ws_html_attributes', 'nav1', 'class'), array('nav', 'horizontal', 'flex', 'flex1', 'vgrid-vertical', 'vgrid-header-modal', 'uppercase'));
ws_array_merge($GLOBALS, array('ws_html_attributes', 'nav1-ol', 'class'), array('hgrid-flex', 'hgrid-flex1', 'align-right', 'align-middle'));
ws_array_merge($GLOBALS, array('ws_html_attributes', 'languages', 'class'), array('nav', 'vertical', 'languages'));
//ws_array_merge($GLOBALS, array('ws_html_attributes', 'languages-ul', 'class'), array(''));
ws_array_merge($GLOBALS, array('ws_html_attributes', 'main-content', 'class'), array('content-container', 'padding-v'));
ws_array_merge($GLOBALS, array('ws_html_attributes', 'footer', 'class'), array('padding', 'background-color1', 'background-darkcolor'));
ws_array_merge($GLOBALS, array('ws_html_attributes', 'footer-content', 'class'), array('hgrid-flex','content-container','hgrid-cols2'));

ws_array_merge($GLOBALS, array('ws_html_attributes', 'reviews', 'class'), array('reviews', 'section'));

$GLOBALS['ws_stylesheets']['head']['links']['all'] = '<link rel="stylesheet" type="text/css" media="all" href="'.$ws_theme_url.'css/all.css" />';
$GLOBALS['ws_stylesheets']['head']['links']['vgrid'] = '<link rel="stylesheet" type="text/css" media="screen and (max-width: 999px)" href="'.$ws_theme_url.'css/vgrid.css" />';
$GLOBALS['ws_stylesheets']['head']['links']['hgrid'] = '<link rel="stylesheet" type="text/css" media="screen and (min-width: 1000px)" href="'.$ws_theme_url.'css/hgrid.css" />';
$GLOBALS['ws_stylesheets']['head']['links']['maxgrid'] = '<link rel="stylesheet" type="text/css" media="screen and (min-width: 1280px)" href="'.$ws_theme_url.'css/maxgrid.css" />';

// https://favicon.io/
ws_array_merge($GLOBALS, array('ws_links'), array(
	'<link rel="apple-touch-icon" sizes="180x180" href="'.$ws_content_root_url.'/favicons/apple-touch-icon.png" />',
	'<link rel="icon" type="image/png" sizes="32x32" href="'.$ws_content_root_url.'/favicons/favicon-32x32.png" />',
	'<link rel="icon" type="image/png" sizes="16x16" href="'.$ws_content_root_url.'/favicons/favicon-16x16.png" />',
	'<link rel="manifest" href="'.$ws_content_root_url.'/favicons/site.webmanifest" />'
));
?>
