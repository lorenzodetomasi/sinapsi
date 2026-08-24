<?php
// The Daily Menu Php template
// @package WS
// @subpackage Localbiz
// @since WS 1.0
global $ws_sitemap, $ws_content, $longDate;
$GLOBALS['ws_html_attributes']['html']['class'][] = 'menues';
include_template('template-parts/header');
?>
<div class="content-container padding-top-2x padding-bottom-2x">
<?php
if($ws_content->name){
?>
	<h1 itemprop="name"><?php echo $ws_content->name->innerHTML(); ?></h1>
<?php
}
?>
<?php
if($ws_content->description){
?>
	<p itemprop="description"><?php echo $ws_content->description->innerHTML(); ?></p>
<?php
}
?>
<?php
if($ws_content->mainContentOfPage){
?>
	<div class="content"><?php echo $ws_content->mainContentOfPage->innerHTML(); ?></div>
<?php
}
?>
<?php
$menuUrls = $ws_sitemap->xpath('url[type="Menu"]');
if($menuUrls){
	foreach ($menuUrls as $article) {
		$src = ws_href($article->wspath);
		if($article->figure->image){
			$class = "flex hgrid-cols2 border-top-d2";
		} else {
			$class = "border-top-d2";
		}
?>
	<article class="<?php echo $class; ?>">
		<div class="padding-v align-middle">
			<h2><a href="<?php echo $src; ?>"><?php echo $article->name->innerHTML(); ?></a></h2>
			<div itemprop="description"><?php echo $article->description->innerHTML(); ?></div>
		</div>
<?php
if($article->figure->image){
?>
		<a href="<?php echo $src; ?>">
			<figure class="preview">
				<?php echo get_media($article->figure->image, array('class' => $article->figure->class, 'style' => $article->figure->style)); ?>
			</figure>
		</a>
<?php
}
?>
	</article>
<?php
	}
}
?>
</div>
<?php
include_template('template-parts/footer');
?>
