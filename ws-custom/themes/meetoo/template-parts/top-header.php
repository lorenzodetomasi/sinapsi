<?php
/**
 * Le azioni in cima a destra: impostazioni e accesso.
 *
 * Nell'header in JavaScript questa è la parte che si riempiva a runtime (`mt-slot`,
 * `mt-account`). Qui il posto è lo stesso e le classi sono le stesse: quello che
 * si può decidere sul server — chi sei, se puoi modificare — arriva già scritto;
 * quello che resta al browser (la modale delle impostazioni) trova gli stessi
 * appigli di prima.
 */
?>
						<span id="mt-slot"></span>
						<button class="mt-icon-btn" id="mt-settings" title="<?php _e('Impostazioni'); ?>" aria-label="<?php _e('Impostazioni'); ?>">
							<span class="material-symbols-outlined" aria-hidden="true">settings</span>
						</button>
						<span id="mt-account">
<?php
// Il pulsante di accesso lo disegna il plugin, se c'è: il tema non sa (e non deve
// sapere) come si entra.
if(locate_file('template-parts/nav-google-login.php')){
	include_template('template-parts/nav-google-login');
}
?>
						</span>
