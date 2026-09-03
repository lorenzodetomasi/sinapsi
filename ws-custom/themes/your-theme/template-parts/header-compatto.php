<?php
/**
 * L'header che si restringe al primo scorrimento.
 *
 * Aperto quando la pagina si apre — logo grande, titolo, aria intorno — e stretto
 * appena si comincia a leggere, per non rubare schermo. Una volta stretto ci resta:
 * un header che si riapre e si richiude a ogni cambio di direzione dello
 * scorrimento fa ballare il testo sotto, ed è la ragione per cui tanti siti
 * preferiscono l'header sempre piccolo. Qui si tiene il meglio dei due: la prima
 * impressione è quella grande, la lettura è quella stretta.
 *
 * Sta nel tema genitore perché non è una scelta di Meetoo: serve a qualunque sito
 * che voglia un'intestazione presente all'apertura e discreta dopo. I temi figli
 * l'accendono aggiungendo la classe `header-compatto` all'header.
 *
 * Senza JavaScript resta aperto, che è la forma leggibile: nessuna riga di
 * contenuto dipende da questo.
 */
?>
<style media="screen">
	/* La transizione è sulle misure, non su `height`: così il contenuto non si
	   schiaccia, si accorcia il contorno. */
	.header-compatto {
		/* Quanto è alto il logo a riposo lo decide il sito, non questo file: il
		   logo orizzontale di Meetoo a 1,5rem si legge già tutto, quello di
		   isotype all'apertura vuole i suoi 4em. Il tema lo dice una volta con
		   `--header-logo-aperto`; qui c'è solo la misura di chi non lo dice. */
		--header-logo: var(--header-logo-aperto, 1.5rem);
		transition: padding .22s cubic-bezier(.22,1,.36,1), box-shadow .22s ease;
		position: sticky; top: 0; z-index: 40;
	}
	.header-compatto img, .header-compatto svg, .header-compatto .logo {
		height: var(--header-logo); width: auto;
		/* Un logo orizzontale alto 3,5rem è largo il triplo: su un telefono usciva
		   dalla riga e finiva sotto le icone dell'account. Non può mai essere più
		   largo dello spazio che ha; `contain` lo rimpicciolisce invece di
		   schiacciarlo. */
		max-width: 100%; object-fit: contain; object-position: left center;
		transition: height .22s cubic-bezier(.22,1,.36,1);
	}
	/* E sui telefoni parte già più piccolo: l'impressione grande la può dare uno
	   schermo grande, qui lo spazio è tutto quello che c'è. */
	@media (max-width: 30rem) {
		.header-compatto { --header-logo: var(--header-logo-stretto, 1.5rem); }
	}
	/* Due modi di sparire.
	   `.header-espanso-solo` se ne va e non torna: è l'aria dell'apertura, e
	   chi legge non la rivuole più.
	   `.header-cima-solo` torna quando si torna in cima. La barra dei contatti
	   — dove siamo, la mail, il telefono — serve a chi arriva, e chi risale sta
	   arrivando di nuovo. L'header intanto resta stretto: è l'ALTEZZA che non
	   deve ballare mentre si legge, non il contenuto quando la lettura finisce. */
	.header-compatto .header-espanso-solo,
	.header-compatto .header-cima-solo {
		transition: opacity .18s ease, max-height .22s cubic-bezier(.22,1,.36,1);
		overflow: hidden; max-height: 6rem; opacity: 1;
	}
	.header-compatto.stretto {
		--header-logo: var(--header-logo-stretto, 1.5rem);
		--header-aria: 0rem;
		box-shadow: 0 1px 0 0 var(--mt-border, rgba(0,0,0,.12));
	}
	/* `max-height` stringe il contenuto, non il bordo: la barra dei contatti di
	   isotype ha una riga sotto, e chiusa sarebbe rimasta lì da sola. */
	.header-compatto.stretto .header-espanso-solo,
	.header-compatto.stretto:not(.in-cima) .header-cima-solo {
		max-height: 0; opacity: 0; border-width: 0;
	}
	@media (prefers-reduced-motion: reduce) {
		.header-compatto, .header-compatto img, .header-compatto .header-espanso-solo { transition: none; }
	}
</style>
<script>
(function(){
	/* Lo script sta nella testa, quindi parte prima che l'header esista: se
	   cercasse l'elemento subito non troverebbe niente e non succederebbe più
	   nulla. Si aspetta il documento, e se è già pronto si parte e basta. */
	function avvia(){
	var header = document.querySelector('.header-compatto');
	if(!header) return;
	/* Si stringe al primo scorrimento e RESTA stretto. Non si riapre tornando in
	   cima: un header che cambia misura avanti e indietro fa ballare il testo
	   sotto a ogni rimbalzo, e la prima impressione — quella grande — l'ha già
	   data. Una volta che si legge, lo spazio serve alla lettura. */
	var soglia = 24;
	var fermo = false;
	function guarda(){
		fermo = false;
		if(window.scrollY > soglia){
			header.classList.add('stretto');
		}
		/* Questa invece va e viene: dice se siamo in cima. Quello che è appeso a
		   `.header-cima-solo` torna quando si risale — l'header resta stretto. */
		header.classList.toggle('in-cima', window.scrollY <= soglia);
	}
	window.addEventListener('scroll', function(){
		if(fermo) return;
		fermo = true;
		window.requestAnimationFrame(guarda);
	}, { passive: true });
	guarda();
	}
	if(document.readyState === 'loading'){
		document.addEventListener('DOMContentLoaded', avvia);
	} else {
		avvia();
	}
})();
</script>
