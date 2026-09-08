/**
 * Come si vede: chiaro, scuro, o come dice il sistema.
 *
 * È una preferenza di chi legge, non un dato del sito: resta sul suo
 * dispositivo e non risale al server. Per questo `localStorage` e non un
 * campo del profilo — la stessa persona può volere scuro sul telefono la sera
 * e chiaro sul portatile al lavoro, e sono due schermi diversi, non due
 * opinioni diverse.
 *
 * Come funziona: la scelta si scrive su `color-scheme`, che è ciò che i token
 * leggono con `light-dark()`. E si scrive ANCHE come attributo `data-theme`,
 * perché `light-dark()` vale solo per i colori: un'immagine di sfondo, una
 * maschera, un bordo disegnato non possono leggerlo, e con l'attributo una
 * pagina può scriverne una regola. Con 'auto' l'attributo si toglie: comanda
 * il sistema, e le `@media` lo leggono da sé.
 *
 * Sta nel tema genitore perché non è di nessun sito in particolare. Meetoo ne
 * ha una sua dentro il suo header, con le stesse tre voci e la stessa chiave
 * di lettura: il giorno che le due si somiglieranno abbastanza, resterà questa.
 *
 * Senza JavaScript non succede niente e la pagina resta com'era: nessuna riga
 * di contenuto dipende da questo.
 */
(function () {
	var CHIAVE = 'ws-tema';

	/* Chi non ha ancora scelto vede il sito com'è sempre stato: chiaro.
	 *
	 * Il valore giusto sarebbe 'auto' — segui il sistema — ed è il valore a cui
	 * questo file arriverà. Ma oggi solo il foglio principale parla per token:
	 * gli altri (le tre griglie) hanno ancora i colori scritti a mano, e con
	 * 'auto' chiunque abbia il sistema scuro si troverebbe una pagina mezza
	 * fatta senza aver chiesto niente. Chi vuole lo scuro lo chiede e ce l'ha.
	 * Quando anche gli altri fogli useranno i token, qui si cambia una parola. */
	var PREDEFINITO = 'light';

	function scelto() {
		try { return localStorage.getItem(CHIAVE) || PREDEFINITO; } catch (e) { return PREDEFINITO; }
	}

	/* `ricorda` solo quando la scelta è di chi guarda. Scrivere anche il valore
	   predefinito lo trasformerebbe in una scelta: il giorno che il predefinito
	   cambia, chi non aveva scelto niente resterebbe legato a quello vecchio. */
	function applica(modo, ricorda) {
		if (modo !== 'light' && modo !== 'dark') modo = 'auto';
		document.documentElement.style.colorScheme = (modo === 'auto') ? 'light dark' : modo;
		if (modo === 'auto') document.documentElement.removeAttribute('data-theme');
		else document.documentElement.setAttribute('data-theme', modo);
		if (ricorda) { try { localStorage.setItem(CHIAVE, modo); } catch (e) {} }
		segna(modo);
		/* Le pagine che hanno un tema proprio si agganciano qui. 'auto' arriva
		   già risolto: chi ascolta vuole sapere che colori disegnare, non che
		   cosa ha scelto la persona. */
		var risolto = (modo === 'auto')
			? (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
			: modo;
		try { document.dispatchEvent(new CustomEvent('ws:tema', { detail: { modo: modo, risolto: risolto } })); } catch (e) {}
	}

	function segna(modo) {
		var bottoni = document.querySelectorAll('#ws-aspetto [data-tema]');
		for (var i = 0; i < bottoni.length; i++) {
			var b = bottoni[i];
			var suo = b.getAttribute('data-tema') === modo;
			b.classList.toggle('scelto', suo);
			b.setAttribute('aria-pressed', suo ? 'true' : 'false');
		}
	}

	/* Prima che la pagina si veda: se la scelta arrivasse dopo il disegno, si
	   vedrebbe il lampo bianco di chi ha chiesto scuro. */
	applica(scelto());

	/* Aprire e chiudere la finestra.
	 *
	 * Il tema ha due convenzioni per farlo, e i suoi template usano quella che
	 * non è implementata da nessuna parte: `data-toggle` e `data-close` sono
	 * scritti nel markup (qui, nelle lingue, in «Condividi», in «Seguici»,
	 * nella ricerca) ma nessuno script li ascolta — `functions.js` ne conosce
	 * un'altra, fatta di classi `-toggle` e `-box-wrapper`. Perciò il comando
	 * c'era e non apriva niente.
	 *
	 * Questa finestra se ne occupa da sé: apre chi la chiama, chiude la ✕, il
	 * clic fuori e Esc. Niente di condiviso, perché non c'è ancora niente di
	 * condiviso che funzioni. */
	function apriChiudi() {
		var box = document.getElementById('impostazioni');
		if (!box) return;
		var comando = document.querySelector('a[href="#impostazioni"]');

		function apri(e) {
			if (e) e.preventDefault();
			box.style.display = '';
			if (comando) comando.setAttribute('aria-expanded', 'true');
			var primo = box.querySelector('[data-tema]');
			if (primo) primo.focus();
		}
		function chiudi(e) {
			if (e) e.preventDefault();
			box.style.display = 'none';
			if (comando) comando.setAttribute('aria-expanded', 'false');
			if (comando) comando.focus();
		}

		if (comando) {
			comando.setAttribute('aria-expanded', 'false');
			comando.addEventListener('click', apri);
		}
		var chiusura = box.querySelector('[data-close]');
		if (chiusura) chiusura.addEventListener('click', chiudi);
		/* Il clic FUORI dal riquadro: la finestra copre tutta la pagina, quindi
		   «fuori» vuol dire sulla finestra stessa e non su ciò che contiene. */
		box.addEventListener('click', function (e) { if (e.target === box) chiudi(); });
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && box.style.display !== 'none') chiudi();
		});
	}

	function avvia() {
		apriChiudi();
		var zona = document.getElementById('ws-aspetto');
		if (!zona) return;
		zona.addEventListener('click', function (e) {
			var b = e.target.closest && e.target.closest('[data-tema]');
			if (b) { applica(b.getAttribute('data-tema'), true); }
		});
		segna(scelto());
	}

	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', avvia); }
	else { avvia(); }
})();
