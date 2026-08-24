<?php
// The ContactPage top menu Php template
// @package WS
// @subpackage Localbiz
// @since WS 1.0
?>
<?php
global $ws_content, $ws_headings;
// Show main contacts specified in $ws_headings.
$email = $ws_headings->mainEntity->email;
$telephone = $ws_headings->mainEntity->telephone;
if($email or $telephone){
?>
				<nav class="box nav vertical">
					<div>
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
						<ul>
<?php
// Show CTA.
	if(!empty($cta)){
?>
							<li><?php echo $cta; ?></li>
<?php
	}
?>						</ul>
					</div>
				</nav>
<?php
}
?>
<!--
<ul>
	<li><a href=""><?php _e('Request a quote'); ?></a></li>
	<li><a href=""><?php _e('Book an appointment'); ?></a></li>
	<li><a href=""><?php _e('Request information'); ?></a></li>
</ul>
-->