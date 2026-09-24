<?php
// The Share Php template for inclusion
// @package WS
// @subpackage Localbiz
// @since WS 1.0
?>
<aside id="share" class="modal" hidden>
	<div>
		<header class="flex align-middle">
			<h3><?php _e('Share'); ?></h3>
			<nav>
				<ul>
					<li><a class="close link h48" href="#" data-close="#share"><i class="material-symbols-outlined">close</i><span class="button-text"><?php _e('Close'); ?></span></a></li>
				</ul>
			</nav>
		</header>
		<div>
			<nav>
				<ul class="share-links flex vgrid-cols2 hgrid-cols4">
<?php //include_template('socialmedia/share-li'); ?>
				</ul>
			</nav>
		</div>
	</div>
</aside>
