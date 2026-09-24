<?php
/**
 * Le preferenze di chi legge.
 *
 * PREFERENZE e non «impostazioni»: tutto quello che c'è qui dentro è una scelta
 * di chi guarda la pagina — come la vede, in che lingua la legge — mentre
 * «impostazioni» promette di configurare il sito, che è un'altra cosa e non si
 * fa da qui.
 *
 * ASPETTO e LINGUA non chiedono chi sei. Restano sul dispositivo di chi legge e
 * valgono anche da scollegati; è la ragione per cui non stanno nel profilo. La
 * lingua è stata a lungo visibile solo ai collegati, insieme agli avvisi: era un
 * errore di raggruppamento — la lingua non riguarda l'account più di quanto lo
 * riguardi il chiaro/scuro.
 *
 * ACCOUNT — gli avvisi, e quello che verrà — la vede solo chi è collegato,
 * perché quelle sì sono di quella persona; e ci finisce dentro solo ciò che il
 * sito sa davvero fare. Una riga che non porta da nessuna parte è peggio di una
 * riga che manca: la prima promette.
 *
 * @package WS
 * @subpackage Your Theme
 */
global $ws_content_languages, $ws_locales;

/* La lingua si offre solo se ce n'è più d'una da scegliere. Con una lingua
 * sola sarebbe un comando che mostra la risposta che hai già. Prima stava
 * anche nell'header, con il mappamondo e la sigla: ora sta qui e basta, in un
 * posto solo. */
$lingue = (!empty($ws_content_languages) && isset($ws_content_languages->item)) ? $ws_content_languages->item : array();
$piu_lingue = (is_object($lingue) || is_array($lingue)) ? (count($lingue) > 1) : false;
$lingua_corrente = isset($ws_locales['content']) ? explode('-', $ws_locales['content'])[0] : '';

/* La sezione ACCOUNT non c'è ancora perché non c'è niente da metterci: gli
 * avvisi non hanno un posto dove stare — il profilo non ha quel campo. Quando
 * nascerà servirà anche sapere chi sta guardando, e quello lo dice
 * `google_login_profilo()`, dallo stesso posto da cui lo chiede la pagina
 * protetta. Finché la sezione non c'è, non lo si chiede: niente qui dentro
 * dipende da chi sei. */
?>
<aside id="preferences" class="modal" hidden>
	<div>
		<header>
			<h3><?php _e('Preferences'); ?></h3>
			<nav>
				<ul>
					<li><a class="close link h48" href="#" data-close="#preferences"><i class="material-symbols-outlined">close</i><span class="button-text"><?php _e('Close'); ?></span></a></li>
				</ul>
			</nav>
		</header>

		<section id="appearance" role="group" aria-label="<?php _e('Appearance'); ?>">
			<h4><?php _e('Appearance'); ?></h4>
			<button type="button" data-appearance="auto" aria-pressed="false"><span class="material-symbols-outlined">brightness_auto</span><?php _e('Automatic'); ?></button>
			<button type="button" data-appearance="light" aria-pressed="false"><span class="material-symbols-outlined">light_mode</span><?php _e('Light'); ?></button>
			<button type="button" data-appearance="dark" aria-pressed="false"><span class="material-symbols-outlined">dark_mode</span><?php _e('Dark'); ?></button>
		</section>

<?php if ($piu_lingue): ?>
		<section id="language" role="group" aria-label="<?php _e('Language'); ?>">
			<h4><span class="material-symbols-outlined" aria-hidden="true">language</span><?php _e('Language'); ?></h4>
			<ul>
<?php foreach ($lingue as $item): ?>
				<li><a href="<?php echo ws_href($item->wspath); ?>"<?php echo ((string)$item->locale === $lingua_corrente) ? ' aria-current="true"' : ''; ?>><?php echo $item->name->innerHTML(); ?></a></li>
<?php endforeach; ?>
			</ul>
		</section>
<?php endif; ?>
	</div>
</aside>
