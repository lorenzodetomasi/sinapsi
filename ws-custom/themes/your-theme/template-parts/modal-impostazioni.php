<?php
/**
 * Le impostazioni del sito.
 *
 * Per ora ce n'è una sola — come si vede — ed è quella che serve a tutti:
 * chiaro, scuro, o come il sistema. Le altre (lingua, avvisi) sono di chi è
 * collegato e stanno dove sta il resto di ciò che riguarda quella persona.
 *
 * Segue la forma delle altre finestre del tema (`modal-share`,
 * `modal-languages`): un `aside` chiuso, che si apre con `data-toggle`. Non
 * inventa un guscio nuovo perché sul sito ce n'è già uno, e due gusci diversi
 * nella stessa pagina si notano.
 *
 * @package WS
 * @subpackage Your Theme
 */
?>
<aside id="impostazioni" class="modal full-page" style="display: none;">
	<div class="content-container background-white padding-h padding-bottom shadow-bottom">
		<header class="flex align-middle">
			<h1 class="flex1 h5" style="margin-bottom: 0; margin-top: 0;"><?php _e('Settings'); ?></h1>
			<a class="close link h48" href="#" data-close="#impostazioni"><i class="material-icons">close</i><span class="button-text"><?php _e('Close'); ?></span></a>
		</header>
		<div class="ws-aspetto" id="ws-aspetto" role="group" aria-label="<?php _e('Appearance'); ?>">
			<p class="ws-aspetto-titolo"><?php _e('Appearance'); ?></p>
			<button type="button" data-tema="auto" aria-pressed="false"><span class="material-symbols-outlined">brightness_auto</span><?php _e('Automatic'); ?></button>
			<button type="button" data-tema="light" aria-pressed="false"><span class="material-symbols-outlined">light_mode</span><?php _e('Light'); ?></button>
			<button type="button" data-tema="dark" aria-pressed="false"><span class="material-symbols-outlined">dark_mode</span><?php _e('Dark'); ?></button>
		</div>
	</div>
</aside>
