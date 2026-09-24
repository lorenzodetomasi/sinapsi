/**
 * Le due voci di «Condividi» che senza JavaScript non possono esistere.
 *
 * Tutte le altre sono collegamenti e funzionano da sole — è il motivo per cui
 * sono scritte nel markup e non qui. Queste due no: copiare negli appunti e
 * aprire il foglio di sistema sono gesti del browser, non indirizzi.
 *
 * Il foglio di sistema (`navigator.share`) compare solo dove c'è davvero, e
 * quando c'è va in cima: contiene quello che la persona ha installato, che è
 * sempre più di quello che possiamo indovinare noi.
 */
(function () {
	function avvia() {
		var zona = document.getElementById('share-where');
		if (!zona) { return; }
		var url = zona.getAttribute('data-url') || location.href;
		var titolo = zona.getAttribute('data-title') || document.title;

		var nativa = document.getElementById('share-native');
		if (nativa && navigator.share) {
			nativa.hidden = false;
			nativa.querySelector('button').addEventListener('click', function () {
				/* `catch` e basta: chi annulla il foglio di sistema ha appena
				   detto di no, e dirglielo una seconda volta con un errore
				   sarebbe insistere. */
				navigator.share({ title: titolo, url: url }).catch(function () {});
			});
		}

		var copia = document.getElementById('share-copy');
		if (copia) {
			var etichetta = copia.querySelector('.share-label');

			/* Si dice che è fatto, e poi si torna com'era: un pulsante che resta
			   «Copiato» per sempre non si può premere una seconda volta senza
			   dubitare che abbia funzionato. */
			function fatto() {
				if (!etichetta) { return; }
				var era = etichetta.textContent;
				etichetta.textContent = copia.getAttribute('data-done') || era;
				setTimeout(function () { etichetta.textContent = era; }, 1600);
			}

			/* La via di prima, per quando l'altra non c'è (http, browser vecchi)
			   o dice di no: copiare vuole un campo vero da cui copiare. */
			function conIlCampo() {
				var campo = document.createElement('textarea');
				campo.value = url;
				campo.setAttribute('readonly', '');
				campo.style.position = 'fixed';
				campo.style.opacity = '0';
				document.body.appendChild(campo);
				campo.select();
				var riuscito = false;
				try { riuscito = document.execCommand('copy'); } catch (e) {}
				document.body.removeChild(campo);
				return riuscito;
			}

			copia.addEventListener('click', function () {
				/* IL RIPIEGO VALE ANCHE SUL RIFIUTO, non solo sull'assenza.
				   `navigator.clipboard` può esserci e rifiutare lo stesso - la
				   pagina non ha il fuoco, il permesso è negato - e il primo giro
				   di questo file, in quel caso, non faceva niente: né copiava né
				   lo diceva. Un pulsante che tace è peggio di uno che manca. */
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(url).then(fatto, function () {
						if (conIlCampo()) { fatto(); }
					});
					return;
				}
				if (conIlCampo()) { fatto(); }
			});
		}
	}

	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', avvia); }
	else { avvia(); }
})();
