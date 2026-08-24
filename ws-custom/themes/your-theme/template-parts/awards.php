<?php
// Displays Awards
global $ws_headings, $ws_query, $ws_content, $ws_contents_url;
$awards = $ws_content->awards->award;
$awards_number = $awards->count();
?>
<section<?php echo ws_html_attributes('awards'); ?>>
	<div class="content-container text-align-center">
		<h1><?php _e('Awards'); ?></h1>
		<p><?php _e('The awards and acknowledgments we have obtained'); ?></p>
	</div>
	<div class="flex content-container distribute-horizontal">
<?php
foreach ($awards as $award) {
	if($award->image->destination){
		$image = $award->image->destination;
	} else if($award->image->source){
		$image = $award->image->source;
	}
?>
		<a href="<?php echo $award->url; ?>" target="_blank" title="<?php printf(__('Visit “%s” website'), $award->name); ?>"><img itemprop="award" lang="en" style="width: 120px; height: auto;" alt="<?php echo $image->alt; ?>" src="<?php echo $ws_contents_url.'/'.$image->relpath; ?>" /></a>
<?php
}
?>
	</div>
</section>
<?php
?>
