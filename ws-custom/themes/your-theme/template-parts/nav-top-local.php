<?php
// The header top menu Php template
// @package WS
// @subpackage Localbiz
// @since WS 1.0
global $ws_sitemap, $ws_query, $ws_headings, $ws_content, $ws_content_languages, $ws_locales;
$contactPageDOMElement = $ws_sitemap->xpath('url[type="ContactPage"]');
$contact_page_url = ws_href($contactPageDOMElement[0]->wspath);
$areaServed = $ws_headings->mainEntity->areaServed;
$email = $ws_headings->mainEntity->email[0];
$telephone = $ws_headings->mainEntity->telephone;
?>
<nav <?php echo ws_html_attributes('header-top', array('id' => 'header-top')); ?>><div class="content-container">
<?php
if($contact_page_url and $areaServed){
?>
	<ul class="location">
		<li>
			<a href="<?php echo $contact_page_url; ?>" title="<?php _e('Where we are and how to contact us'); ?>">
				<i class="material-symbols-outlined">place</i><span class="text"><?php echo $areaServed->html->innerHTML(); ?></span>
			</a>
		</li>
	</ul>
<?php
}
if($email or $telephone){
?>
	<ul class="contacts">
<?php
	if($email){
?>
		<li><?php echo email($email); ?></li>
<?php
	}
	if($telephone){
?>
		<li><?php echo telephone($telephone); ?></li>
<?php
	}
?>
	</ul>
<?php
}
if($ws_headings->follow == 'true' or $ws_headings->share == 'true' or $ws_headings->search == 'true'){
?>
	<ul class="social print-no">
<?php
	if($ws_headings->follow == 'true'){
?>
		<li>
			<a href="#follow-container" title="<?php _e('Follow us on social media and newsletter'); ?>" data-toggle data-group="subheader" data-toggle-icon="close" data-close-title="<?php _e('Close “Follow us”'); ?>">
				<i class="material-symbols-outlined">rss_feed</i><span class="text vgrid-no"><?php _e('Follow us'); ?></span>
			</a>
		</li>
<?php
	}
	if($ws_headings->share == 'true'){
?>
		<li>
			<a href="#share-container" title="<?php _e('Share on social media or email'); ?>" data-toggle data-group="subheader" data-toggle-icon="close" data-close-title="<?php _e('Close “Share”'); ?>">
				<i class="material-symbols-outlined">share</i><span class="text vgrid-no"><?php _e('Share'); ?></span>
			</a>
		</li>
<?php
	}
?>
	</ul>
<?php
}
?>
	<ul class="settings print-no">
		<li>
			<a href="#impostazioni" title="<?php _e('Settings'); ?>" data-toggle data-group="subheader" data-toggle-icon="close" data-close-title="<?php _e('Close “Settings”'); ?>">
				<i class="material-symbols-outlined">settings</i><span class="text vgrid-no"><?php _e('Settings'); ?></span>
			</a>
		</li>
	</ul>
<?php
if($ws_headings->search == 'true'){
?>
	<ul class="search print-no">
		<li>
			<a href="#search" title="<?php _e('Search on this website'); ?>" data-toggle data-group="subheader" data-toggle-icon="close" data-close-title="<?php _e('Close “Search”'); ?>">
				<i class="material-symbols-outlined">search</i><span class="text vgrid-no"><?php _e('Search'); ?></span>
			</a>
		</li>
	</ul>
<?php
}
?>
<?php
if(!empty($ws_content_languages)){
	$current_language_item = $ws_content_languages->xpath('/*[1]/item[./locale="'.$ws_locales['content'].'"]')[0];
	if(count($ws_content_languages->children()) > 1){
?>
	<ul class="languages print-no">
		<li>
			<a href="#languages" title="<?php echo $current_language_item->title; ?>" data-toggle data-group="subheader" data-toggle-icon="close" data-close-title="<?php _e('Close “Languages”'); ?>">
				<i class="material-symbols-outlined">language</i><span class="text"><?php echo $current_language_item->name; ?></span>
			</a>
		</li>
	</ul>
<?php
	}
}
include_template('template-parts/nav-google-login');
?>
</div></nav>
