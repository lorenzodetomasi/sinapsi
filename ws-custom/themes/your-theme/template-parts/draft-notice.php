<?php
/**
 * The notice a draft page shows above its content (see ws_is_draft()).
 *
 * @package WS
 * @subpackage Your Theme
 */
global $ws_content;
if(!ws_is_draft($ws_content)){
	return;
}
?>
				<p class="draft-notice" role="note"><strong><?php _e('Draft'); ?></strong> <?php _e('This page is not published yet: it is on the site, but search engines do not index it.'); ?></p>
