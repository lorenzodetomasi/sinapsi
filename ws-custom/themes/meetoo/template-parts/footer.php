<?php
/**
 * Il piede della pagina: in fondo al contenuto, non incollato allo schermo.
 *
 * Un piede fisso ruba una striscia di schermo a ogni riga letta, e su un telefono
 * la striscia è tanta. Qui il piede sta dov'è nato: alla fine, e ci si arriva
 * scorrendo — che è anche il modo in cui si capisce di essere arrivati alla fine.
 */
global $ws_headings, $ws_contentmap;
$nome_sito = !empty($ws_headings->mainEntity->name) ? (string)$ws_headings->mainEntity->name : 'Meetoo';
?>
				</main>
			</div>
			<footer<?php echo ws_html_attributes('footer'); ?>>
				<div class="mt-footer">
					<nav class="mt-footer-voci" aria-label="<?php _e('Collegamenti'); ?>">
						<ul>
							<li><a href="<?php echo ws_href('eventi'); ?>"><?php _e('Eventi'); ?></a></li>
							<li><a href="<?php echo ws_href('luoghi'); ?>"><?php _e('Luoghi'); ?></a></li>
							<li><a href="<?php echo ws_href('organizzatori'); ?>"><?php _e('Chi organizza'); ?></a></li>
<?php
// Le pagine di servizio (privacy, cookie) le dichiara il contenuto, non il tema:
// se ci sono nella mappa compaiono, altrimenti no.
foreach(array('PrivacyPage' => __('Privacy'), 'CookiePage' => __('Cookie')) as $tipo => $etichetta){
	$link = function_exists('ws_pageLink') ? ws_pageLink($tipo, $etichetta) : false;
	if($link){ echo "\t\t\t\t\t\t\t<li>$link</li>\n"; }
}
?>
						</ul>
					</nav>
					<p class="mt-footer-firma">
						<?php echo htmlspecialchars($nome_sito, ENT_QUOTES, 'UTF-8'); ?> · <?php echo date('Y'); ?>
					</p>
				</div>
			</footer>
		</div>
<?php
/* LE FINESTRE della pagina: le preferenze, «condividi».
 *
 * Questo footer e' di Meetoo e non le includeva, perche' le impostazioni se le
 * disegnava header.js. Da quando l'ingranaggio nell'header apre `#preferences`,
 * senza queste righe apriva il vuoto: il comando c'era e non faceva niente.
 * Sono le stesse due del tema genitore - un guscio solo per tutti i siti. */
include_template('template-parts/modal-share');
include_template('template-parts/modal-preferences');
/* E quella del profilo, se c'e' un plugin che sa chi sei. La stampa qui e non
   nell'header, dove sta il comando che la apre: una finestra scritta dentro
   l'header si veste da header. */
if(locate_file('template-parts/modal-profile.php')){
	include_template('template-parts/modal-profile');
}
/* E quella per entrare, per chi non e' ancora entrato. Sono due e non una
   perche' sono due momenti diversi: una dice chi sei, l'altra te lo chiede. */
if(locate_file('template-parts/modal-signin.php')){
	include_template('template-parts/modal-signin');
}
?>
<?php echo ws_scripts('bodyend'); ?>
	</body>
</html>
