<?php
global $site_url, $sitetitle;
if( have_rows('social_media', 'option') ):
	while ( have_rows('social_media', 'option') ) : the_row();
		$name = get_sub_field('name');
		$url = get_sub_field('url');
		if($name == 'Google+'){
			$liClass = "gplus";
			$aClass = "social-icon-gplus";
		} elseif($name == 'Facebook'){
			$liClass = "facebook";
			$aClass = "social-icon-facebook";			
		} elseif($name == 'Linkedin'){
			$liClass = "linkedin";
			$aClass = "social-icon-linkedin";			
		} elseif($name == 'Pinterest'){
			$liClass = "pinterest";
			$aClass = "social-icon-pinterest";			
		} elseif($name == 'Instagram'){
			$liClass = "instagram";
			$aClass = "social-icon-instagram";			
		}
?>
		<li class="<?php echo $liClass; ?>"><a href="<?php echo $url; ?>" itemprop="sameAs" class="<?php echo $aClass; ?>" rel="publisher" target="_blank" title="Visita la pagina <?php echo $name; ?>+ di <?php echo $sitetitle; ?>"><span class="button-text"><?php echo $name; ?></span></a></li>
<?php
	endwhile;
else :
endif;
?>
		<li class="feed"><a href="<?php echo $site_url; ?>/feed/" itemprop="sameAs" class="social-icon-feed" target="_blank" title="<?php printf(__('Seguici su %s','isotype'), __('Rss Feed', 'isotype')); ?>"><span class="button-text"><?php _e('Rss Feed', 'isotype'); ?></span></a></li>					