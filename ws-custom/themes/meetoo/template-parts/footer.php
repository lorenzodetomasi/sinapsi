<?php
/**
 * Il piede della pagina: in fondo al contenuto, non incollato allo schermo.
 *
 * Un piede fisso ruba una striscia di schermo a ogni riga letta, e su un telefono
 * la striscia è tanta. Qui il piede sta dov'è nato: alla fine, e ci si arriva
 * scorrendo — che è anche il modo in cui si capisce di essere arrivati alla fine.
 */
?>
				</main>
			</div>
			<footer<?php echo ws_html_attributes('footer'); ?>>
				<div class="mt-footer content-container" id="footer-content">
<?php
/* THE SAME FOOTER AS ISOTYPE'S (29 Sep 2026): who runs the site, with its
 * address and tax numbers; the legal menu; the credits. The parent theme's
 * three parts, fed by Meetoo's own content: the organisation in ws_headings,
 * the menu in nav-legal. The three links it had before - eventi, luoghi,
 * organizzatori - led nowhere: the lists live inside a zone. */
include_template('template-parts/fiscal-data');
include_template('template-parts/nav-legal');
include_template('template-parts/credits');
?>
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
