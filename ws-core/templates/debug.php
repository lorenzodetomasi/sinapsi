<?php
global $ws_themes;
// Print debug and logs, if active.
?>
<a title="Refresh contents" target="_blank" href="<?php echo ws_href('/refresh'); ?>"><i class="material-icons">refresh</i></a>
<a id="ws_debug-toggle" href="#ws_debug" onclick="toggle('ws_debug', this);"><i class="material-icons">bug_report</i></a>
<a title="W3C Validator" target="_blank" href="https://validator.w3.org/nu/?showsource=yes&doc=<?php echo ws_href($ws_query['wspath']); ?>"><i class="material-icons">code</i></a>
<a title="Google PageSpeed Insights" target="_blank" href="https://developers.google.com/speed/pagespeed/insights/?hl=IT&url=<?php echo ws_href($ws_query['wspath']); ?>"><i class="material-icons">speed</i></a>
<aside id="ws_debug" style="display: none; padding-left: 2em; padding-right: 2em;">
	<h1><?php _e('Debug'); ?></h1>
	<button onclick="toggle('ws_debug');"><i class="material-icons">close</i></button>
<?php
if(WS_DEBUG_LOG == true){
?>
	<section id="ws_debug">
		<h1><?php _e('Logs'); ?></h1>
		<ol>
<?php
foreach ($ws_logs as $ws_log) {
?>
		<li><?php echo $ws_log; ?></li>
<?php
}
?>
		</ol>
	</section>
<?php
}
?>
	<section id="ws_query">
		<h1>ws_root_url()</h1>
		<p><?php echo ws_root_url(); ?></p>
		<h1>ws_custom_url()</h1>
		<p><?php echo ws_custom_url(); ?></p>
		<h1>$ws_query</h1>
		<p><?php print_r($ws_query); ?></p>
<?php
if($rewrite_rule){
?>
		<h2>$rewrite_rule</h2>
		<p><?php print_r($rewrite_rule); ?></p>
<?php
}
?>
<?php
if($ws_content_languages){
?>
	<h2>$ws_content_languages</h2>
	<p><?php print_r($ws_content_languages); ?></p>
<?php
}
?>
<?php
if($ws_locales){
?>
		<h2>$ws_locales</h2>
		<p><?php print_r($ws_locales); ?></p>
		<p>ws_locale(): <?php echo ws_locale(); ?></p>
		<p>ws_lang(): <?php echo ws_lang(); ?></p>
<?php
}
?>
		<h2>Themes</h2>
		<p>$ws_query['themes']: <?php print_r($ws_query['themes']); ?></p>
		<p>$ws_themes: <?php print_r($ws_themes); ?></p>

		<h2>Plugins</h2>
		<p>$ws_query['plugins']: <?php print_r($ws_query['plugins']); ?></p>
		<p>ws_plugins('all'): <?php print_r(ws_plugins('all')); ?></p>
		<p>ws_plugins('theme'): <?php print_r(ws_plugins('theme')); ?></p>
		<p>ws_plugins('content'): <?php print_r(ws_plugins('content')); ?></p>

		<h2>$locations</h2>
<?php
if($locations){
?>
<p><?php print_r($locations); ?></p>
<?php
} else {
?>
<p><?php _e('$locations not set.'); ?></p>
<?php
}
?>
		<h2>$ws_headings</h2>
<?php
if($ws_headings){
?>
		<p><?php print_r($ws_headings); ?></p>
<?php
} else {
?>
		<p><?php _e('$ws_headings not set.'); ?></p>
<?php
}
?>
		<h2>ws_content_id()</h2>
		<p><?php echo ws_content_id(); ?></p>
		<h2>$ws_content</h2>
		<p><?php print_r(htmlspecialchars($ws_content->asXML())); ?></p>
		<h2>$ws_sitemap</h2>
		<p><?php print_r(htmlspecialchars($ws_sitemap->asXML())); ?></p>
		<h2>$ws_contentmap</h2>
		<p><?php print_r(htmlspecialchars($ws_contentmap->asXML())); ?></p>
	</section>
</aside>
