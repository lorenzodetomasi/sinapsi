/**
 * Il cassetto del menu.
 *
 * L'hamburger sta all'estrema destra dell'header e apre un pannello che entra
 * da sinistra, sopra un velo. Non è una finestra: è il menu del sito, e su uno
 * schermo stretto è l'unico posto dove ci sta tutto.
 *
 * Aperto e chiuso li dice una classe sola, `open`, su cassetto e velo: la
 * transizione la disegna il CSS, che è l'unico che sa quanto dura. Con
 * `hidden` non ci sarebbe transizione affatto — l'elemento sparirebbe di
 * colpo — e per questo chiuso NON è `hidden` ma `inert`: si vede che non c'è
 * (sta fuori schermo) e non lo si raggiunge col tab.
 *
 * Senza JavaScript non succede niente: il menu orizzontale in #header1-nav1
 * resta, e su uno schermo largo è quello che si usa comunque.
 */
(function () {
	function avvia() {
		var cassetto = document.getElementById('drawer');
		var velo = document.getElementById('drawer-overlay');
		var apri = document.getElementById('menu-open');
		var chiudiBtn = document.getElementById('drawer-close');
		if (!cassetto || !velo || !apri) { return; }

		function aperto() { return cassetto.classList.contains('open'); }

		function mostra(e) {
			if (e) { e.preventDefault(); }
			cassetto.classList.add('open');
			velo.classList.add('open');
			cassetto.removeAttribute('inert');
			apri.setAttribute('aria-expanded', 'true');
			var primo = cassetto.querySelector('a, button');
			if (primo) { primo.focus(); }
		}

		function nascondi(e) {
			if (e) { e.preventDefault(); }
			cassetto.classList.remove('open');
			velo.classList.remove('open');
			cassetto.setAttribute('inert', '');
			apri.setAttribute('aria-expanded', 'false');
			/* Il fuoco torna da dove è partito. Lasciarlo dentro un pannello
			   chiuso vuol dire che il tab successivo riparte dall'inizio della
			   pagina, e chi naviga da tastiera perde il segno. */
			apri.focus();
		}

		apri.addEventListener('click', mostra);
		velo.addEventListener('click', nascondi);
		if (chiudiBtn) { chiudiBtn.addEventListener('click', nascondi); }
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && aperto()) { nascondi(); }
		});
		/* Si è andati da qualche parte: il cassetto ha finito il suo lavoro.
		   Senza questo resterebbe aperto sopra la pagina nuova, ogni volta che
		   il collegamento porta a un'ancora della stessa pagina. */
		cassetto.addEventListener('click', function (e) {
			if (e.target.closest && e.target.closest('a[href]')) { nascondi(); }
		});
	}

	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', avvia); }
	else { avvia(); }
})();
