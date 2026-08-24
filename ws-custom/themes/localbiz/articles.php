<?php
// The Articles Archive template
// @package WS
// @subpackage Localbiz
// @since WS 1.0
global $ws_content;
$GLOBALS['ws_html_attributes']['html']['class'][] = 'articles';
include_template('template-parts/header');
?>
<div class="content-container padding-top-2x">
<?php
if($ws_content->name){
?>
	<h1 itemprop="name"><?php echo $ws_content->name->innerHTML(); ?></h1>
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
foreach ($ws_content->article as $article) {
	$src = ws_href($article->wspath);
?>
	<article class="flex hgrid-cols2 border-top-d2">
		<h2 class="padding-v flex align-middle"><a href="<?php echo $src; ?>"><?php echo $article->name; ?></a></h2>
		<a href="<?php echo $src; ?>">
			<figure class="preview">
				<?php echo get_media($article->figure->image, array('class' => $article->figure->class, 'style' => $article->figure->style)); ?>
			</figure>
		</a>
	</article>
<?php
}
?>
<?php
/*
?>
	<nav class="margin-top-2x flex width-full nav align-center horizontal background-color2">
		<ul class="flex align-middle">
			<li><a href="" title="<?php _e('Previous Article'); ?>"><i class="material-icons">navigate_before</i></a></li>
			<li><a href="" title="<?php _e('Next Article'); ?>"><i class="material-icons">navigate_next</i></a></li>
		</ul>
	</nav>
<?php
*/
?>
</div>
<?php
include_template('template-parts/footer');
?>
