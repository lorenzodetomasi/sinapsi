<?php
// Displays Reviews from Google My Business
global $ws_headings, $ws_query, $ws_contents_url, $ws_content, $location_id, $google_place_id, $show_reviews;
$xml_file_abspath = ws_content_root_abspath().'/'.langArray()[0].'/locations/'.$location_id.'/'.$google_place_id.'.xml';
$google_place_obj = ws_load_file( $xml_file_abspath, $args = array( 'format_output' => true, 'input_type' => 'xml', 'output_type' => 'simplexml' ) );
$reviews = ($google_place_obj->result->review);
$reviews_url = $ws_headings->mainEntity->reviews_url;
$write_review_url = $ws_headings->mainEntity->write_review_url;
?>
<section<?php echo ws_html_attributes('reviews'); ?>>
	<div class="content-container text-align-center">
		<h1><?php _e('Reviews'); ?></h1>
		<p><?php _e('What our customers say about us'); ?></p>
	</div>
<?php
if(!isset($reviews)) {
?>
			<p><?php _e('This business has no reviews.'); ?></p>
			<p><?php _e('Write the first one.'); ?></p>
<?php
} else {
	//setlocale(LC_TIME, ws_locale());
?>
	<div class="hgrid-margin-v-2x content-container flex vgrid-cols1 hgrid-cols2 maxgrid-cols2 with-2px-border">
<?php
	$reviews_published = 0;
	foreach ($reviews as $review) {
		$GLOBALS['review'] = $review;
		global $review;
		$review_rating = $review->rating*1;
		if($reviews_published < 2){
			if(($review_rating) > 3) {
//				include_template('template-parts/review');
?>
<article class="vgrid-margin-bottom-2x">
	<h2 class="display-none"><?php printf(_n('%1$s star review by %2$s', '%1$s stars review by %2$s', $review_rating), $review_rating, $review->author_name); ?></h2>
	<div class="margin-bottom">
		<?php echo $review->text; ?>
	</div>
	<p>
		<cite>
<?php
/*
		<cite class="flex align-middle">
			<img class="profile_photo margin-right" src="<?php echo $review->profile_photo_url;?>" />
*/
?>
			<?php echo $review->author_name; ?>, <?php echo strftime("%e %B %Y"); ?> – Google
		</cite>
	</p>
	<p class="color2">
<?php
for($i=1;$i<=($review->rating);$i++){
echo '<i class="material-icons">star</i>';
}
for($i=1;$i<=5-($review->rating);$i++){
echo '<i class="material-icons">star_border</i>';
}
?>
	</p>
</article>
<?php
				$reviews_published++;
			}
		}
	}
?>
	</div>
<?php
}
?>
	<p class="content-container text-align-center"><a href="<?php echo $write_review_url; ?>" target="_blank" class="button"><?php _e('Write your review'); ?></a></p>
	<nav class="margin-top-2x flex width-full nav align-center horizontal background-color2">
		<ul class="flex align-middle">
<!--
			<li><a href="" title="<?php _e('Previous Review'); ?>"><i class="material-icons">navigate_before</i></a></li>
-->
			<li><a href="<?php echo $reviews_url; ?>" target="_blank"><?php _e('All Reviews'); ?></a></li>
<!--
			<li><a href="" title="<?php _e('Next Review'); ?>"><i class="material-icons">navigate_next</i></a></li>
-->
		</ul>
	</nav>
</section>
<?php
?>
