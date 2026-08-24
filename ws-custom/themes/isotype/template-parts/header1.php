<?php
// The Header1 Php template
// @package WS
// @subpackage Your Theme
// @since WS 1.0
global $ws_query, $rewrite_rule, $ws_headings, $ws_contentmap, $ws_content;
$index_url = $ws_headings->url[0];
?>
<div<?php echo ws_html_attributes('header1'); ?>>
	<div<?php echo ws_html_attributes('header1-content'); ?>>
		<div class="hgrid-display-none maxgrid-display-none">
			<a href="#nav1" onclick="toggle('nav1', this);" title="<?php _e('Show main menu'); ?>" class="link"><i class="material-icons menu">menu</i></a>
		</div>
		<hgroup class="flex align-middle vgrid-padding-left">
			<h1 itemprop="name">
<?php
$logoImage = $ws_headings->xpath("id('logo')")[0];
$vgridLogoImage = $ws_headings->xpath("id('logo-vgrid')")[0];
if(file_exists(ws_contents_abspath().'/'.$logoImage->source->relpath)){
?>
				<a href="<?php echo $index_url; ?>" title="<?php _e('Go to homepage'); ?>" class="logo-container">
<?php
if(!empty($vgridLogoImage->source->relpath) and file_exists(ws_contents_abspath().'/'.$vgridLogoImage->source->relpath)){
echo get_media($vgridLogoImage, array('pictureAttributes' => array( 'itemprop' => 'logo', 'class' => 'hgrid-display-none maxgrid-display-none')));
$logoImageClass = 'vgrid-display-none';
}
?>
<?php
echo get_media($logoImage, array('pictureAttributes' => array( 'itemprop' => 'logo', 'class' => $logoImageClass)));
?>
				</a>
<?php
} else {
?>
				<a href="<?php echo $index_url; ?>" title="<?php _e('Go to homepage'); ?>">
					<span><?php echo $ws_headings->mainEntity->name->innerHTML(); ?></span>
				</a>
<?php
}
?>
			</h1>
			<h2 itemprop="headline"<?php echo ws_html_attributes('header1-headline'); ?>><?php echo $ws_headings->mainEntity->headline->innerHTML(); ?></h2>
		</hgroup>
		<nav id="nav1"<?php echo ws_html_attributes('nav1'); ?>>
			<div class="flex align-middle width-full hgrid-display-none maxgrid-display-none">
				<h1 class="h3 padding-h-d2 flex1 no-margin"><?php _e('Main <strong>menu</strong>'); ?></h1>
				<button class="link" onclick="toggle('nav1');" title="<?php _e('Close'); ?>"><i class="material-icons close">close</i></button>
			</div>
			<ol<?php echo ws_html_attributes('nav1-ol'); ?>>
<?php
include_template('template-parts/nav1');
?>
			</ol>
		</nav>
<?php
include_template('template-parts/headings');
?>
	</div>
</div>
