/**
 * Aprire e chiudere le finestre della pagina.
 *
 * UNA convenzione, per tutte. Il tema ne aveva tre e nessuna generale:
 * `data-toggle`/`data-close` scritti nel markup che nessuno ascoltava, le
 * classi `-toggle`/`-box-wrapper` di functions.js che nessun template usava, e
 * la finestra delle preferenze che si arrangiava da sé. Risultato: «Condividi»,
 * «Seguici», «Ricerca» e «Lingue» erano comandi che non aprivano niente.
 *
 * Come funziona: un comando `href="#qualcosa"` apre l'`<aside class="modal">`
 * che si chiama così. Si chiude con la ✕ (`data-close`), con un clic fuori dal
 * riquadro, o con Esc. Aperto e chiuso si dicono con `hidden`, che è il modo in
 * cui l'HTML lo dice — niente stili in linea, e chi legge con la voce lo sa.
 *
 * Il fuoco entra sul primo comando e torna da dove è partito: una finestra che
 * si chiude lasciando il fuoco dentro fa ripartire il tab dall'inizio della
 * pagina, e chi naviga da tastiera perde il segno.
 *
 * Senza JavaScript le finestre restano chiuse e i loro comandi non fanno
 * niente: nessuna riga di contenuto dipende da questo.
 */
(function () {
	function avvia() {
		var aperta = null;
		var chiamante = null;

		function chiudi() {
			if (!aperta) { return; }
			aperta.setAttribute('hidden', '');
			if (chiamante) {
				chiamante.setAttribute('aria-expanded', 'false');
				chiamante.focus();
			}
			aperta = null;
			chiamante = null;
		}

		function apri(box, comando) {
			if (aperta === box) { return; }
			chiudi();   /* una per volta: due finestre sovrapposte sono un errore */
			box.removeAttribute('hidden');
			aperta = box;
			chiamante = comando || null;
			if (chiamante) { chiamante.setAttribute('aria-expanded', 'true'); }
			var primo = box.querySelector('button, [href], input, select, textarea');
			if (primo) { primo.focus(); }
		}

		document.addEventListener('click', function (e) {
			var chiusura = e.target.closest && e.target.closest('[data-close]');
			if (chiusura) { e.preventDefault(); chiudi(); return; }

			var comando = e.target.closest && e.target.closest('a[href^="#"], button[data-open]');
			if (comando) {
				var id = comando.getAttribute('data-open')
					|| (comando.getAttribute('href') || '').slice(1);
				var box = id && document.getElementById(id);
				if (box && box.classList.contains('modal')) {
					e.preventDefault();
					apri(box, comando);
					return;
				}
			}

			/* Il clic FUORI: la finestra copre tutta la pagina, quindi «fuori»
			   vuol dire sulla finestra stessa e non su ciò che contiene. */
			if (aperta && e.target === aperta) { chiudi(); }
		});

		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && aperta) { chiudi(); }
		});
	}

	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', avvia); }
	else { avvia(); }
})();
