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
	/* Al primo assestamento non si anima.
	   Chi ricarica a metà pagina vedeva l'header aprirsi grande e rimpicciolirsi
	   sotto gli occhi: la pagina si dipinge prima che lo script possa dire com'era.
	   Per un fotogramma le transizioni si spengono, così si apre già stretta. */
	.header-compatto.senza-moto,
	.header-compatto.senza-moto * { transition: none !important; }
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

	/* DUE soglie per la barra che va e viene, non una.
	 *
	 * Con una sola, l'header tremava a ogni ricaricamento. Non è un difetto di
	 * disegno, è un anello: la barra si chiude → l'header si accorcia di 47px →
	 * il browser sposta lo scorrimento per tenere fermo quello che si sta
	 * guardando (scroll anchoring) → la quota riattraversa la soglia → la barra
	 * si riapre → e da capo, finché non si scorre apposta e si esce dal giro.
	 *
	 * Con due soglie distanti più del salto che l'header stesso provoca, quel
	 * salto non può più riportare la quota oltre l'altra soglia: fra 4 e 72 non
	 * si decide niente e si tiene quello che c'è. È la stessa ragione per cui un
	 * termostato non accende e spegne alla stessa temperatura. */
	var APRE = 4;    // sotto: la barra torna
	var CHIUDE = 72; // sopra: la barra se ne va

	function guarda(){
		fermo = false;
		var y = window.scrollY;
		if(y > soglia){
			header.classList.add('stretto');
		}
		if(y <= APRE){ header.classList.add('in-cima'); }
		else if(y >= CHIUDE){ header.classList.remove('in-cima'); }
		// In mezzo: si tiene lo stato che c'è. È la fascia dove il cambio
		// d'altezza dell'header si rimangerebbe la propria decisione.
	}
	window.addEventListener('scroll', function(){
		if(fermo) return;
		fermo = true;
		window.requestAnimationFrame(guarda);
	}, { passive: true });
	/* Lo stato iniziale si prende SENZA animarlo: due fotogrammi di silenzio,
	   il tempo che il browser dipinga la pagina già com'era. */
	header.classList.add('senza-moto');
	guarda();
	window.requestAnimationFrame(function(){
		window.requestAnimationFrame(function(){ header.classList.remove('senza-moto'); });
	});
	}
	if(document.readyState === 'loading'){
		document.addEventListener('DOMContentLoaded', avvia);
	} else {
		avvia();
	}
})();
</script>
