<?php
// The Homepage template
// @package WS
// @subpackage Localbiz Child
// @since WS 1.0
global $ws_headings, $ws_content, $longDateTime, $locations;
$GLOBALS['ws_contents_url'] = ws_contents_url();
$ws_content_root_url = ws_content_root_url();
$google_directions_url = $ws_headings->mainEntity->legalLocation->google_directions_url;
$workLocations = $locations->workLocation;
if(count($workLocations) == 1){
	$GLOBALS['google_place_id'] = $workLocations[0]->google_place_id;
	global $google_place_id;
}
$GLOBALS['ws_html_attributes']['html']['class'][] = 'home';
$GLOBALS['ws_media'] = $ws_content->image;
global $ws_media;
include_template('template-parts/header');
$slides = $ws_content->xpath('id("cover")/*');
?>
<div id="cover" class="flex cover">
<?php
foreach($slides as $index => $slide){
	if($index == 0){ $isSlideActive = ' active'; } else { $isSlideActive = ' display-none'; }
?>
	<div class="flex slide width-full background-darkcolor align-middle align-center<?php echo $isSlideActive; ?>" style="background-image: url(<?php echo get_media($slide->image[0], array('output' => 'src')); ?>); background-size: cover; background-position: center center;">
		<div class="hgrid-w1of3 background-color1-transparent padding">
			<?php echo $slide->content->innerHTML(); ?>
		</div>
	</div>
<?php
}
?>
</div>
<?php
if(count($slides) > 1) {
?>
<nav class="flex width-full nav align-center horizontal background-color2">
	<ul class="flex align-middle">
		<li><a href="" title="<?php _e('Previous Slide'); ?>"><i class="material-icons">navigate_before</i></a></li>
		<li>
			<a href=""><i class="material-icons">radio_button_checked</i></a>
			<a href=""><i class="material-icons">radio_button_unchecked</i></a>
			<a href=""><i class="material-icons">radio_button_unchecked</i></a>
		</li>
		<li><a href="" title="<?php _e('Next Slide'); ?>"><i class="material-icons">navigate_next</i></a></li>
	</ul>
</nav>
<?php
}
?>
<div class="content-container vgrid-padding-d2 hgrid-padding-2x">
<?php
if($ws_content->headline){
?>
	<h1 itemprop="name" class="text-align-center no-margin"><?php echo $ws_content->headline->innerHTML(); ?></h1>
<?php
}
?>
<?php
	include_template('locations/_opening_hours_today');
?>
</div>
<?php
if($ws_content->mainContentOfPage){
	echo $ws_content->mainContentOfPage->innerHTML();
}
?>
<div class="content">
<?php
$section = false;
$sectionID = "covid19";
$section = $ws_content->xpath('id("covid19")')[0];
if($section){
?>
	<section id="<?php echo $sectionID; ?>" class="background-color2 margin-bottom">
		<div class="content-container padding-v">
			<h1 class="text-align-center"><?php echo $section->name->innerHTML(); ?></h1>
<?php
	echo $section->content->innerHTML();
?>
		</div>
	</section>
<?php
}
$section = false;
$section = $ws_content->xpath('id("history")')[0];
if($section){
	if($section->image[0]->source->position){
		$position1 = $section->image[0]->source->position;
	} else {
		$position1 = 'center center';
	}
	if($section->image[1]->source->position){
		$position2 = $section->image[1]->source->position;
	} else {
		$position2 = 'center center';
	}
?>
<section id="history" class="flex hgrid-cols3 margin-bottom-2x">
	<div class="background-color2" style="background-image: url(<?php echo get_media($section->image[0], array('output' => 'src')); ?>); background-size: cover; background-position: <?php echo $position1; ?>;">

	</div>
	<div itemprop="description" class="square vgrid-padding-d2 hgrid-padding-2x background-color1 background-darkcolor">
		<h1 class="vgrid-text-align-center"><?php echo $section->name->innerHTML(); ?></h1>
<?php
echo $section->content->innerHTML();
?>
		<p class="margin-top-2x text-align-center"><a class="button" href="<?php echo ws_href($section->more->wspath); ?>" title="<?php echo $section->more->title; ?>"><?php echo $section->more->text; ?></a></p>
	</div>
	<div class="background-color2" style="background-image: url(<?php echo get_media($section->image[1], array('output' => 'src')); ?>); background-size: cover; background-position: <?php echo $position2; ?>;">

	</div>
</section>
<?php
}
$section = false;
$section = $ws_content->xpath('id("excellence")')[0];
if($section){
	if($section->image[0]->source->position){
		$position1 = $section->image[0]->source->position;
	} else {
		$position1 = 'center center';
	}
	if($section->image[1]->source->position){
		$position2 = $section->image[1]->source->position;
	} else {
		$position2 = 'center center';
	}
?>
	<section id="excellence" class="flex hgrid-cols3 margin-bottom-2x">
		<div class="background-color1" style="background-image: url(<?php echo get_media($section->image[0], array('output' => 'src')); ?>); background-size: cover; background-position: <?php echo $position1; ?>;">

		</div>
		<div itemprop="description" class="square vgrid-padding-d2 hgrid-padding-2x background-color2">
			<h1 class="vgrid-text-align-center"><?php echo $section->name->innerHTML(); ?></h1>
<?php
echo $section->content->innerHTML();
?>
			<p class="margin-top-2x text-align-center"><a class="button" href="<?php echo ws_href($section->more->wspath); ?>" title="<?php echo $section->more->title; ?>"><?php echo $section->more->text; ?></a></p>
		</div>
		<div class="background-color1" style="background-image: url(<?php echo get_media($section->image[1], array('output' => 'src')); ?>); background-size: cover; background-position: <?php echo $position2; ?>;">

		</div>
	</section>
<?php
}
$section = false;
?>
<?php
$section = $ws_content->xpath('id("specialties")')[0];
if($section){
	if($section->image[0]->source->position){
		$position = $section->image[0]->source->position;
	} else {
		$position = 'center center';
	}
?>
<section id="specialties" class="margin-top-2x">
	<div class="content-container text-align-center">
		<h1><?php echo $section->name->innerHTML(); ?></h1>
<?php
	echo $section->content->innerHTML();
?>
	</div>
	<div class="flex vgrid-cols1 hgrid-cols4">
<?php
foreach ($section->article as $index => $article) {
?>
<article class="text-align-center vgrid-margin-bottom-2x">
	<figure class="circle">
		<?php echo get_media($article->figure->image, array('pictureAttributes' => array('class' => $article->figure->class, 'style' => $article->figure->style))); ?>
	</figure>
	<h2><?php echo $article->name->innerHTML(); ?></h2>
</article>
<?php
}
?>
	</div>
	<p class="margin-top-2x text-align-center"><a class="button" href="<?php echo ws_href($section->more->wspath); ?>" title="<?php echo $section->more->title; ?>"><?php echo $section->more->text; ?></a></p>
<!--
	<nav class="margin-top-2x flex width-full nav align-center horizontal background-color2">
		<ul class="flex align-middle">
			<li><a href="" title="<?php _e('Previous Dish'); ?>"><i class="material-icons">navigate_before</i></a></li>
			<li><a href=""><?php _e('All Specialties'); ?></a></li>
			<li><a href="" title="<?php _e('Next Dish'); ?>"><i class="material-icons">navigate_next</i></a></li>
		</ul>
	</nav>
-->
</section>
<?php
}
$section = $ws_content->xpath('id("food_delivery")')[0];
if($section){
	if($section->image[0]->source->position){
		$position1 = $section->image[0]->source->position;
	} else {
		$position1 = 'center center';
	}
	if($section->image[1]->source->position){
		$position2 = $section->image[1]->source->position;
	} else {
		$position2 = 'center center';
	}
?>
	<section id="food_delivery" class="flex hgrid-cols3 margin-top-2x margin-bottom-2x">
		<div class="background-color2" style="background-image: url(<?php echo get_media($section->image[0], array('output' => 'src')); ?>); background-size: cover; background-position: <?php echo $position1; ?>;">

		</div>
		<div itemprop="description" class="square vgrid-padding-d2 hgrid-padding-2x background-color1 background-darkcolor">
			<h1 class="vgrid-text-align-center"><?php echo $section->name->innerHTML(); ?></h1>
<?php
echo $section->content->innerHTML();
?>
			<p class="margin-top-2x text-align-center"><a class="button" href="<?php echo ws_href($section->more->wspath); ?>" title="<?php echo $section->more->title; ?>"><?php echo $section->more->text; ?></a></p>
		</div>
		<div class="background-color2" style="background-image: url(<?php echo get_media($section->image[1], array('output' => 'src')); ?>); background-size: cover; background-position: <?php echo $position2; ?>;">

		</div>
	</section>
<?php
}
$section = false;
?>
<?php
include_template('template-parts/awards');
?>
<?php
$GLOBALS['location_id'] = 'roma-via-dei-gracchi';
$GLOBALS['google_place_id'] = 'ChIJgRQbkGBgLxMRTYfjt7dE990';
$GLOBALS['show_reviews'] = 2;
global $google_place_id;
if(!empty($google_place_id)){
	include_template('template-parts/reviews');
}
?>
<?php
$section = false;
$section = $ws_content->xpath('id("design")')[0];
if($section){
	if($section->image[0]->source->position){
		$position1 = $section->image[0]->source->position;
	} else {
		$position1 = 'center center';
	}
	if($section->image[1]->source->position){
		$position2 = $section->image[1]->source->position;
	} else {
		$position2 = 'center center';
	}
?>
	<section id="design" class="flex hgrid-cols3 margin-bottom-2x">
		<div class="background-color2" style="background-image: url(<?php echo get_media($section->image[0], array('output' => 'src')); ?>); background-size: cover; background-position: <?php echo $position1; ?>;">

		</div>
		<div itemprop="description" class="square vgrid-padding-d2 hgrid-padding-2x background-color1 background-darkcolor">
			<h1 class="vgrid-text-align-center"><?php echo $section->name->innerHTML(); ?></h1>
<?php
echo $section->content->innerHTML();
?>
		</div>
		<div class="background-color2" style="background-image: url(<?php echo get_media($section->image[1], array('output' => 'src')); ?>); background-size: cover; background-position: <?php echo $position2; ?>;">

		</div>
	</section>
<?php
}
?>
<?php
$section = false;
$section = $ws_content->xpath('id("party")')[0];
if($section){
	if($section->image[0]->source->position){
		$position1 = $section->image[0]->source->position;
	} else {
		$position1 = 'center center';
	}
	if($section->image[1]->source->position){
		$position2 = $section->image[1]->source->position;
	} else {
		$position2 = 'center center';
	}
?>
	<section id="party" class="flex hgrid-cols3 section-padding-bottom">
		<div class="background-color1" style="background-image: url(<?php echo get_media($section->image[0], array('output' => 'src')); ?>); background-size: cover; background-position: <?php echo $position1; ?>;">

		</div>
		<div itemprop="description" class="square vgrid-padding-d2 hgrid-padding-2x background-color2">
			<h1><?php echo $section->name->innerHTML(); ?></h1>
<?php
echo $section->content->innerHTML();
?>
			<p class="margin-top-2x text-align-center"><a class="button" href="<?php echo ws_href($section->more->wspath); ?>" title="<?php echo $section->more->title; ?>"><?php echo $section->more->text; ?></a></p>
		</div>
		<div class="background-color1" style="background-image: url(<?php echo get_media($section->image[1], array('output' => 'src')); ?>); background-size: cover; background-position: <?php echo $position2; ?>;">

		</div>
	</section>
<?php
}
$section = false;
$section = $ws_content->xpath('id("news")')[0];
?>
<section id="news" class="section-padding-bottom">
	<div class="content-container text-align-center">
	<h1><?php echo $section->name->innerHTML(); ?></h1>
	<?php echo $section->content->innerHTML(); ?>
	</div>
	<div class="flex vgrid-cols1 hgrid-cols3 vgrid-with-2px-border">
<?php
foreach ($section->article as $article) {
	$src = ws_href($article->wspath);
?>
		<article>
			<a class="preview border-top-d2" href="<?php echo $src; ?>">
				<?php echo get_media($article->figure->image, array('class' => $article->figure->class, 'style' => $article->figure->style)); ?>
			</a>
			<h2 class="padding-top-d2 padding-bottom padding-h no-margin"><a href="<?php echo $src; ?>"><?php echo $article->name; ?></a></h2>
		</article>
<?php
}
?>
	</div>
	<nav class="margin-top-2x flex width-full nav align-center horizontal background-color2">
		<ul class="flex align-middle">
<!--
			<li><a href="" title="<?php _e('Previous Article'); ?>"><i class="material-icons">navigate_before</i></a></li>
-->
			<li><a href="<?php echo ws_href($section->more->wspath); ?>" title="<?php echo $section->more->title; ?>"><?php _e('All Articles'); ?></a></li>
<!--
			<li><a href="" title="<?php _e('Next Article'); ?>"><i class="material-icons">navigate_next</i></a></li>
-->
		</ul>
	</nav>
</section>
<?php
$section = false;
$section = $ws_content->xpath('id("contacts")')[0];
?>
<section id="contacts">
	<div class="content-container vgrid-padding-d2 hgrid-padding-2x text-align-center">
		<h1><?php echo $section->name->innerHTML(); ?></h1>
		<?php echo $section->content->innerHTML(); ?>
	</div>
	<?php include_template('locations/_locations'); ?>
</section>
			</div>
<?php
include_template('template-parts/footer');
?>
