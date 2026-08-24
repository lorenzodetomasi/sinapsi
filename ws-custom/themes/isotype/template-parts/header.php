<?php
// The main header Php template
// @package WS
// @subpackage Your Theme
// @since WS 1.0
global $ws_query, $rewrite_rule, $ws_headings, $ws_contentmap, $ws_content, $ws_content_root, $ws_content_root_abspath;
$index_url = $ws_headings->url[0];
if(file_exists($ws_content_root_abspath.'/'.ws_locale().'/nav1.xml')){
  $nav1 = ws_content($ws_content_root.'/'.ws_locale().'/nav1');
} else if($ws_content_root_abspath.'/nav1.xml'){
  $nav1 = ws_content($ws_content_root.'/nav1');
}
if($nav1->count() == 0){
  $nav1 = $ws_contentmap;
}
?>
<!DOCTYPE html>
<html<?php echo ws_html_attributes('html'); ?>>
	<head>
		<title><?php echo $rewrite_rule->title; ?></title>
<?php
echo ws_metas();
echo ws_scripts('head');
echo ws_links();
echo ws_styles('head');
?>
	</head>
	<body<?php echo ws_html_attributes('body'); ?>>
		<div<?php echo ws_html_attributes('page'); ?>>
			<header<?php echo ws_html_attributes('header'); ?>>
				<div<?php echo ws_html_attributes('header-content'); ?>>
<?php
if($ws_headings->header_top){
	include_template($ws_headings->header_top);
}
?>
					<div <?php echo ws_html_attributes('header1'); ?>>
						<div>
<?php
$logoImage = $ws_headings->xpath("id('logo')");
echo get_media($logoImage, array('pictureAttributes' => array( 'itemprop' => 'logo', 'class' => '')));
?>
							<hgroup>
								<h1 itemprop="name">
									<a href="<?php echo $index_url; ?>" title="<?php _e('Go to homepage'); ?>">
										<?php echo $ws_headings->mainEntity->name->innerHTML(); ?>
									</a>
								</h1>
								<h2 itemprop="headline"<?php echo ws_html_attributes('header1-headline'); ?>><?php echo $ws_headings->mainEntity->headline->innerHTML(); ?></h2>
							</hgroup>
							<a href="#nav1" onclick="toggle('header1-nav1', this);" class="vgrid" title="<?php _e('Show the Main menu'); ?>"><span class="icon material-symbols-outlined">menu</span> <span class="text-label all-no"><?php _e('Main menu'); ?></span></a>
						</div>
						<nav <?php echo ws_html_attributes('header1-nav1', array('id' => 'header1-nav1', 'class' => array('header1-nav1', 'nav', 'vgrid-fullwidth', 'padding-h-d2', 'hgrid-horizontal'))); ?>>
							<h3 class="vgrid all-no"><?php _e('Main menu'); ?></h3>
							<div>
								<ul><?php ws_nav_items($nav1); ?></ul>
							</div>
						</nav>
					</div>
				</div>
			</header>
			<div<?php echo ws_html_attributes('main-container'); ?>>
				<main<?php echo ws_html_attributes('main'); ?>>
<?php
include_template('template-parts/nav-contents');
?>
