<?php
/**
 * La scheda di chi è collegato.
 *
 * Una finestra come le altre — stesso guscio, stesso modo di aprirsi e di
 * chiudersi — e come le altre la stampa il FOOTER, non l'header. Il comando che
 * la apre è l'avatar, in `nav-google-login.php`.
 *
 * Perché non insieme al comando: quel pezzo viene incluso dentro `#header`, e
 * `#header a { color: … }` porta un id, quindi vince su qualunque classe. I
 * comandi del profilo uscivano rossi su fondo blu. Una finestra non deve
 * vestirsi di dove è scritta nel documento, e il modo di non farlo è non
 * scriverla lì.
 *
 * Chi non è collegato non ha profilo: il file non stampa niente.
 *
 * @package WS
 * @subpackage Google Login
 */
$profilo = function_exists('google_login_profilo') ? google_login_profilo() : array('collegato' => false);
if(empty($profilo['collegato']) or empty($profilo['sessione'])){
	return;
}
$sessione = $profilo['sessione'];
$registrato = !empty($profilo['registrato']);

$radice = function_exists('ws_root_url') ? rtrim(ws_root_url(), '/') : '';
$profilo_url = htmlspecialchars($radice) . '/profilo-utente';
$registrazione = $registrato ? '' : '?init=register';
$incorporato = $profilo_url . ($registrazione ? $registrazione . '&' : '?') . 'embed=1';
?>
<aside id="profile" class="modal" hidden>
	<div>
		<header>
			<h3><?php _e('Your User Profile'); ?></h3>
			<nav>
				<ul>
					<li><a class="close link h48" href="#" data-close="#profile"><i class="material-symbols-outlined">close</i><span class="button-text"><?php _e('Close'); ?></span></a></li>
				</ul>
			</nav>
		</header>
		<section class="identity">
			<img src="<?= htmlspecialchars($sessione->picture) ?>" class="avatar" alt="" referrerpolicy="no-referrer">
			<p class="identity-name"><?= htmlspecialchars($sessione->name) ?></p>
			<p class="identity-email"><?= htmlspecialchars($sessione->email) ?></p>
			<p class="identity-role"><?= htmlspecialchars($profilo['role']) ?></p>
		</section>
		<nav>
			<ul class="pills identity-actions">
				<li><a class="pill pill-strong" href="<?= $profilo_url . $registrazione ?>" data-mt-profilo-form="<?= $incorporato ?>"><?= $registrato ? __('Edit User Profile') : __('Complete User Subscription') ?></a></li>
				<li><a class="pill" href="?logout=1"><?php _e('Logout'); ?></a></li>
			</ul>
		</nav>
	</div>
</aside>
