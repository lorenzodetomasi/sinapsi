<?php
/**
 * Le impostazioni del sito.
 *
 * Due sezioni, e la seconda non c'è sempre.
 *
 * ASPETTO — chiaro, scuro, o come il sistema — la vedono tutti: è una
 * preferenza di lettura, non riguarda chi sei. Vale anche da scollegati, ed è
 * la ragione per cui non sta dentro il profilo.
 *
 * PREFERENZE — la lingua, gli avvisi — la vede solo chi è collegato, perché
 * sono di quella persona; e ci finisce dentro solo quello che il sito sa
 * davvero fare. Una riga che non porta da nessuna parte è peggio di una riga
 * che manca: la prima promette.
 *
 * Segue la forma delle altre finestre del tema (`modal-share`,
 * `modal-languages`): un `aside` chiuso che si apre dal suo comando. Non
 * inventa un guscio nuovo perché sul sito ce n'è già uno, e due gusci diversi
 * nella stessa pagina si notano.
 *
 * @package WS
 * @subpackage Your Theme
 */
global $ws_content_languages, $ws_locales;

/* Chi sta guardando lo dice il plugin del login, dallo stesso posto da cui lo
 * chiede la pagina protetta. Se il plugin non c'è (o è più vecchio dei file del
 * tema), «scollegato» è la risposta giusta: si vede l'aspetto e basta. */
$profilo = function_exists('google_login_profilo') ? google_login_profilo() : array('collegato' => false);
$collegato = !empty($profilo['collegato']);

/* La lingua si offre solo se ce n'è più d'una da scegliere. Con una lingua
 * sola sarebbe un comando che mostra la risposta che hai già. */
$lingue = (!empty($ws_content_languages) && isset($ws_content_languages->item)) ? $ws_content_languages->item : array();
$piu_lingue = (is_object($lingue) || is_array($lingue)) ? (count($lingue) > 1) : false;
$lingua_corrente = isset($ws_locales['content']) ? explode('-', $ws_locales['content'])[0] : '';

/* Gli avvisi non ci sono ancora: non esiste un posto dove tenerli — il profilo
 * non ha quel campo. Il giorno che ci sarà, questa riga diventa la domanda
 * giusta e la sezione se li prende senza toccare altro. */
$notifiche_attive = false;

$mostra_preferenze = $collegato && ($piu_lingue || $notifiche_attive);
?>
<aside id="impostazioni" class="modal full-page" style="display: none;">
	<div class="content-container background-white padding-h padding-bottom shadow-bottom">
		<header class="flex align-middle">
			<h1 class="flex1 h5" style="margin-bottom: 0; margin-top: 0;"><?php _e('Settings'); ?></h1>
			<a class="close link h48" href="#" data-close="#impostazioni"><i class="material-symbols-outlined">close</i><span class="button-text"><?php _e('Close'); ?></span></a>
		</header>

		<div class="ws-aspetto" id="ws-aspetto" role="group" aria-label="<?php _e('Appearance'); ?>">
			<p class="ws-aspetto-titolo"><?php _e('Appearance'); ?></p>
			<button type="button" data-tema="auto" aria-pressed="false"><span class="material-symbols-outlined">brightness_auto</span><?php _e('Automatic'); ?></button>
			<button type="button" data-tema="light" aria-pressed="false"><span class="material-symbols-outlined">light_mode</span><?php _e('Light'); ?></button>
			<button type="button" data-tema="dark" aria-pressed="false"><span class="material-symbols-outlined">dark_mode</span><?php _e('Dark'); ?></button>
		</div>

<?php if ($mostra_preferenze): ?>
		<div class="ws-preferenze">
			<p class="ws-aspetto-titolo"><?php _e('Preferences'); ?></p>
<?php if ($piu_lingue): ?>
			<p class="ws-pref-voce"><span class="material-symbols-outlined">language</span><?php _e('Language'); ?></p>
			<ul class="ws-lingue">
<?php foreach ($lingue as $item): ?>
				<li<?php echo ((string)$item->locale === $lingua_corrente) ? ' class="corrente"' : ''; ?>><a href="<?php echo ws_href($item->wspath); ?>"><?php echo $item->name->innerHTML(); ?></a></li>
<?php endforeach; ?>
			</ul>
<?php endif; ?>
		</div>
<?php endif; ?>
	</div>
</aside>
