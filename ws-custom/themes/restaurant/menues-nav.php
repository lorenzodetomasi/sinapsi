<?php
// The Menues navigation Php template
// @package WS
// @subpackage Localbiz
// @since WS 1.0
global $ws_sitemap;
?>
<?php
$menuUrls = $ws_sitemap->xpath('url[type="Menu"]');
$menues = $ws_sitemap->xpath('url[type="MenuList"]')[0];
$menuesName = $menues->name->innerHTML();
if($menuUrls){
?>
<nav class="menues-nav nav">
	<h1><?php _e('Other <strong>menues</strong>'); ?></h1>
</h1>
	<ul>
<?php
	foreach ($menuUrls as $menuUrl) {
?>
			<li>
				<a href="<?php echo ws_href($menuUrl->wspath); ?>">
					<strong><?php echo $menuUrl->name->innerHTML(); ?></strong>
					<span class="description"><?php echo $menuUrl->description->innerHTML(); ?></span>
				</a>
			</li>
<?php
	}
?>
	</ul>
</nav>
<?php
}
?>
