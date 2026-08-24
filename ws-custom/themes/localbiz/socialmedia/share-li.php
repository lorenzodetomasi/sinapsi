<?php
global $sitename, $twitter_text, $twitter_user, $twitter_hashtags;
$page_url = esc_url(get_permalink());
if(get_the_post_thumbnail_url(get_the_ID())){
	$image_url = esc_url(get_the_post_thumbnail_url(get_the_ID(),'full'));	
} else {
	$image_url = false;
}
$description = esc_url(get_the_excerpt());
$twitter_href = 'http://twitter.com/share?';
if($twitter_text and $twitter_user){
	$twitter_href .= 'text='.sprintf(esc_attr__($twitter_text, 'isotype'), $twitter_user);
} else if ($twitter_user){
	$twitter_href .= 'text='.rawurlencode(sprintf(esc_attr__('Ti segnalo questa interessante pagina di %s', 'isotype')), $twitter_user);
} else {
	$twitter_href .= 'text='.rawurlencode(esc_attr__('Ti segnalo questa interessante pagina', 'isotype'));
}
$twitter_href .= '&amp;url='.$page_url;
if($twitter_hashtags){
	$twitter_href .= '&amp;hashtags='.$twitter_hashtags;	
}
?>
<li class="facebook">
	<a rel="nofollow" href="http://www.facebook.com/share.php?u=<?php echo $page_url;?>" target="_blank" class="social-icon-facebook" title="<?php printf(__('Condividi questa pagina su %s','isotype'), __('Facebook', 'isotype')); ?>">
		<span class="button-text"><?php _e('Facebook', 'isotype'); ?></span>
	</a>
</li>
<li class="googleplus">
	<a rel="nofollow" href="https://plus.google.com/share?url=<?php echo $page_url;?>" target="_blank" class="social-icon-gplus" title="<?php printf(__('Condividi questa pagina su %s','isotype'), __('Google+', 'isotype')); ?>">
		<span class="button-text"><?php _e('Google+', 'isotype'); ?></span>
	</a>
</li>
<li class="linkedin">
	<a rel="nofollow" href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo $page_url;?>" target="_blank" class="social-icon-linkedin" title="<?php printf(__('Condividi questa pagina su %s','isotype'), __('Linkedin', 'isotype')); ?>">
		<span class="button-text"><?php _e('Linkedin', 'isotype'); ?></span>
	</a>
</li>
<li class="twitter">
	<a rel="nofollow" href="<?php echo $twitter_href; ?>" target="_blank" class="social-icon-twitter" title="<?php printf(__('Condividi questa pagina su %s','isotype'), __('Twitter', 'isotype')); ?>">
		<span class="button-text"><?php _e('Twitter', 'isotype'); ?></span>
	</a>
</li>
<?php
if($image_url){
?>
<li class="pinterest">
	<a href="http://pinterest.com/pin/create/button/?url=<?php echo $page_url;?>&amp;media=<?php echo $image_url; ?>&amp;description=<?php echo $description; ?>" target="_blank" class="social-icon-pinterest" title="<?php printf(__('Condividi questa pagina su %s','isotype'), __('Pinterest', 'isotype')); ?>">
		<span class="button-text"><?php _e('Pinterest', 'isotype'); ?></span>
	</a>
</li>
<?php
}
?>
<li class="email">
	<a rel="nofollow" href="mailto:?subject=<?php echo rawurlencode(sprintf(esc_attr__('Dai un’occhiata a %s', 'isotype'), $sitename)); ?>&amp;body=<?php echo rawurlencode(sprintf(esc_attr__('Ciao, penso che questa pagina web possa interessarti &lt;%s&gt;', 'isotype'), $page_url)); ?>" target="_blank" title="<?php _e('Condividi questa pagina via email','isotype'); ?>">
		<i class="material-icons">email</i>
		<span class="button-text"><?php _e('Email', 'isotype'); ?></span>
	</a>
</li>