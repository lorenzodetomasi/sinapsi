<?php
// The Article template
// @package WS
// @subpackage Localbiz
// @since WS 1.0
global $ws_content, $longDate, $longTime;
$GLOBALS['ws_html_attributes']['html']['class'][] = 'article';
include_template('template-parts/header');
?>
			<div<?php echo ws_html_attributes('main-content'); ?>>
				<div>
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
					<div itemprop="description">
						<?php echo $ws_content->description->innerHTML(); ?>
					</div>
<?php
}
?>
					<div class="content">
<?php
if($ws_content->articleBody){
	echo $ws_content->articleBody->innerHTML();
}
?>
					</div>
					<aside class="meta">
<?php
if($ws_content->datePublished){
$dateModified = DateTime::createFromFormat(DATE_ATOM, $ws_content->dateModified);
$datePublished = DateTime::createFromFormat(DATE_ATOM, $ws_content->datePublished);
?>
						<p class="content-container small meta padding-v">
							<span itemprop="datePublished"><?php printf(__('Article published on %1$s, at %2$s'), $longDate->format($datePublished), $longTime->format($datePublished)); ?></span>
						</p>
<?php
}
?>
					</aside>
				</div>
				<figure class="no-margin">
<?php
if($ws_content->figure->image[0]){
	echo get_media($ws_content->figure->image[0], array('class' => 'width-full', 'style' => ''));
}
?>
				</figure>
			</div>
<?php
include_template('template-parts/footer');
?>
