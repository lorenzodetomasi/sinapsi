<?php
// The Reviews template file
global $locations;
$google_place_id = 'ChIJVwGbntfvJRMRKcN3O_N0SYc';
$google_place_obj = get_google_place_details($google_place_id);
if($google_place_obj->error_message){
	echo "Error in \$google_place_obj: \n";
	print_r($google_place_obj);
}
//the_header();
?>
		<main>
			<h1><?php _e('Recensioni', 'localbiz'); ?></h1>
<?php
$reviews = ($google_place_obj->result->reviews);
$show_reviews = 2;
$opening_hours = $google_place_obj->result->opening_hours;
if($opening_hours->open_now == 1){
	$open_now = __('Siamo aperti', 'isotype');
} else {
	$open_now = __('In questo momento siamo chiusi', 'isotype');
}
if(!isset($reviews)) {
?>
			<p>Questa attività non ha recensioni.</p>
			<p>Scrivi tu la prima.</p>
<?php
} else {
?>
			<section id="reviews" class="content-container page-padding-h border-top">
				<h1 class="widget-title"><?php _e('Dicono di noi'); ?></h1>
				<div class="flex row hgrid-cols3">
<?php
$reviews_published = 0;
//setlocale(LC_TIME, ws_locale());
foreach ($reviews as $review) {
	if($reviews_published < 2){
		$datetime = new DateTime('@'.$review->time);
		if(($review->rating) > 3) {
?>
					<article>
						<h1 class="h2"><?php echo $review->author_name; ?></h1>
						<p><?php echo strftime("%e %B %Y"); ?></p>
						<p>
<?php
			for($i=1;$i<=($review->rating);$i++){
				echo '<i class="material-icons">star</i>';
			}
			for($i=1;$i<=5-($review->rating);$i++){
				echo '<i class="material-icons">star_half</i>';
			}
?>
						</p>
						<div>
							<?php echo $review->text; ?>
						</div>
					</article>
<?php
				$reviews_published++;
			}
		}
	}
}
?>
					<section id="review">
						<h1 class="widget-title"><?php printf(__("Hai provato %s?", 'isotype'), $sitename); ?></h1>
						<p><?php _e('Scrivi una recensione breve e sincera.','isotype'); ?></p>
						<a class="social-icon-gplus button h48" title="<?php printf(__('Scrivi una recensione su %s','isotype'), 'Google+'); ?>" href="<?php echo $site_url; ?>/google-review">Google+</a>
						<a class="social-icon-facebook button h48" title="<?php printf(__('Scrivi una recensione su %s','isotype'), 'Facebook'); ?>" href="<?php echo $site_url; ?>/facebook-review">Facebook</a>
						<p><small>Devi possedere un account Google o Facebook.</small></p>
					</section>
				</div>
			</section>
		</main>
<?php
//the_footer();
?>
