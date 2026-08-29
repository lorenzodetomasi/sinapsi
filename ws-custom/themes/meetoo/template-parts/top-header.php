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
<?php
/* La penna: c'è solo per chi può davvero modificare QUESTA cosa, e solo se
 * esiste un editor che sappia aprirla. Il permesso lo decide la stessa funzione
 * che risponde al momento del salvataggio, così la penna non è mai una porta
 * che si apre su un muro. Chi non può modificare non la vede: un pulsante
 * spento sarebbe un modo per dire «questo non è tuo» a chi non l'aveva chiesto. */
$mt_modifica = function_exists('meetoo_url_modifica') ? meetoo_url_modifica() : '';
if($mt_modifica !== '' and function_exists('meetoo_puo_modificare') and meetoo_puo_modificare()){
?>
						<a class="mt-icon-btn" id="mt-modifica" href="<?php echo mt_esc($mt_modifica); ?>" title="<?php _e('Modifica'); ?>" aria-label="<?php _e('Modifica'); ?>">
							<span class="material-symbols-outlined" aria-hidden="true">edit</span>
						</a>
<?php } ?>
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
