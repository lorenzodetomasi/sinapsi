<?php
// The Homepage template
// @package WS
// @subpackage Localbiz Child
// @since WS 1.0
global $ws_headings, $ws_content;
$ws_contents_url = ws_contents_url();
$ws_content_root_url = ws_content_root_url();
$GLOBALS['ws_html_attributes']['html']['class'][] = 'about';
$GLOBALS['ws_media'] = $ws_content->image;
global $ws_media;
include_template('template-parts/header');
?>
<div class="content-container">
<?php
if($ws_content->name){
?>
<h1 itemprop="name"><?php echo $ws_content->name->innerHTML(); ?></h1>
<?php
}
?>
<?php
if($ws_content->mainContentOfPage){
	echo $ws_content->mainContentOfPage->innerHTML();
}
?>
<div class="content">
<?php
$section = $ws_content->xpath('id("history")')[0];
if($section){
?>
	<section id="history" class="section-padding-top">
			<h2 class="vgrid-text-align-center"><?php echo $section->name->innerHTML(); ?></h2>
<?php
echo $section->content->innerHTML();
?>
	</section>
<?php
}
$section = false;
?>
<?php
$section = $ws_content->xpath('id("partners")')[0];
if($section){
?>
	<section id="partners" class="section-padding-top">
			<h2 class="vgrid-text-align-center"><?php echo $section->name->innerHTML(); ?></h2>
<?php
echo $section->content->innerHTML();
?>
<?php
foreach ($section->organization as $organization) {
?>
<article>
	<h3 class="margin-top-2x"><?php echo $organization->name->innerHTML(); ?></h3>
	<div>
<?php echo $organization->description->innerHTML(); ?>
	</div>
</article>
<?php
}
?>
	</section>
<?php
}
$section = false;
?>
<?php
$section = $ws_content->xpath('id("design")')[0];
if($section){
?>
	<section id="design" class="section-padding-top">
			<h2 class="vgrid-text-align-center"><?php echo $section->name->innerHTML(); ?></h2>
<?php
echo $section->content->innerHTML();
?>
	</section>
<?php
}
$section = false;
?>
<?php
$section = $ws_content->xpath('id("party")')[0];
if($section){
?>
	<section id="party" class="section">
			<h2 class="vgrid-text-align-center"><?php echo $section->name->innerHTML(); ?></h2>
<?php
echo $section->content->innerHTML();
?>
	</section>
<?php
}
$section = false;
?>
</div>
<?php
include_template('template-parts/footer');
?>
