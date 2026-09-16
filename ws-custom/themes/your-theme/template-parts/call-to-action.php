<?php
/**
 * The page's call to action: one sentence from the content, and the way in.
 *
 * `cta` is what the page asks of its reader, written for that page - not the
 * SEO `description`, which is written for a results page and says what the
 * page is. The button leads to THIS site's contact page, found in the site
 * map by its type rather than by an address someone would have to keep in
 * step - and confined to the site, since the map carries every site's.
 *
 * @package WS
 * @subpackage Your Theme
 */
global $ws_content;

if(empty($ws_content->cta)){
	return;
}
// This site's contact page - the map lists every site's, and each has one.
$contact = ws_sitemap_of_type('ContactPage');
$contact_url = $contact ? ws_href($contact->wspath) : '';
?>
					<aside class="call-to-action">
						<p><?php echo trim($ws_content->cta->innerHTML()); ?></p>
<?php if($contact_url !== ''){ ?>
						<p><a class="button" href="<?php echo $contact_url; ?>"><?php _e('Contact us'); ?></a></p>
<?php } ?>
					</aside>
