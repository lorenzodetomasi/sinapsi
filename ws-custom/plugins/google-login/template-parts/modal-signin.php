<?php
/**
 * Entrare nel sito.
 *
 * Una finestra come le altre, e come le altre la stampa il footer. Il comando
 * che la apre è l'icona nell'header — nostra, che segue il tema.
 *
 * Il pulsante di Google sta QUI e non lassù perché il suo disegno lo fa Google,
 * dentro un iframe, e cambia forma da sé: anonimo è una pastiglia che il tema lo
 * prende, ma a chi ha una sessione Google attiva diventa «Accedi come Nome» con
 * la foto — bianco, e largo il triplo. In una riga di comandi quella è una
 * lastra che copre il menu; qui ha lo spazio che gli serve.
 *
 * E ci sta su una PIASTRA CHIARA dichiarata: il riquadro di Google è bianco
 * comunque, e su un fondo bianco è una scelta invece che un buco. È la stessa
 * risposta che il tema dà a un marchio senza versione scura.
 *
 * Chi è già collegato non la vede: per lui c'è la scheda del profilo.
 *
 * @package WS
 * @subpackage Google Login
 */
$profilo = function_exists('google_login_profilo') ? google_login_profilo() : array('collegato' => false);
if (!empty($profilo['collegato'])) {
	return;
}
?>
<aside id="signin" class="modal" hidden>
	<div>
		<header>
			<h3><?php _e('Sign in'); ?></h3>
			<nav>
				<ul>
					<li><a class="close link h48" href="#" data-close="#signin"><i class="material-symbols-outlined">close</i><span class="button-text"><?php _e('Close'); ?></span></a></li>
				</ul>
			</nav>
		</header>
		<p class="signin-perche"><?php _e('Sign in with Google to comment, to say you are interested, and to edit what is yours.'); ?></p>
		<div id="g_id_onload"
			data-client_id="<?= GoogleAuth::getClientId() ?>"
			data-context="signin"
			data-ux_mode="popup"
			data-callback="handleGoogleLogin"
			data-auto_prompt="false"></div>
		<div class="signin-piastra">
			<div id="g_id_signin_btn" class="g_id_signin" data-type="standard" data-shape="pill" data-text="signin" data-size="large" data-theme="outline"></div>
		</div>
	</div>
</aside>
<script>
/* Il pulsante si disegna QUANDO LA FINESTRA SI APRE, non al caricamento.
 *
 * Una finestra chiusa è `hidden`, e Google, dentro un contenitore che non
 * occupa spazio, disegna un pulsante alto zero: si aprirebbe su un riquadro
 * vuoto. `modal.js` annuncia le aperture, e alla prima si chiede il disegno.
 *
 * Una volta sola: ridisegnarlo a ogni apertura lo farebbe sfarfallare, e non
 * c'è niente da aggiornare — il tema qui non cambia mai, perché la piastra
 * sotto è chiara per scelta. */
(function () {
	var fatto = false;
	document.addEventListener('ws:modale', function (e) {
		if (fatto || !e.detail || e.detail.id !== 'signin' || !e.detail.aperta) { return; }
		var posto = document.getElementById('g_id_signin_btn');
		var gis = window.google && google.accounts && google.accounts.id;
		if (!posto || !gis) { return; }
		fatto = true;
		gis.renderButton(posto, { type: 'standard', shape: 'pill', text: 'signin', size: 'large', theme: 'outline' });
	});
})();
</script>
