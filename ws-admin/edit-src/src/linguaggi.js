/**
 * Che cosa sa la Gestione dei linguaggi che si scrivono nei suoi campi.
 *
 * Due domande per ognuno, e sono domande diverse:
 *   FORMATTA  — rimettilo in ordine senza cambiare quello che dice.
 *   VALIDA    — dimmi se è rotto, e a che riga.
 *
 * Un posto solo perché sono le stesse due domande ovunque: il corpo di una
 * pagina è XHTML, la pagina intera è JSON-LD, un tema è CSS. Averle sparse
 * significa che un campo sa correggersi e quello accanto no, senza una ragione
 * che si possa spiegare.
 *
 * Nessuna libreria: il browser un parser XML ce l'ha (`DOMParser`), un parser
 * JSON ce l'ha (`JSON.parse`) e un parser JavaScript pure (`new Function`, che
 * COMPILA e non esegue). Usare quelli vuol dire che le regole sono le vere
 * regole del linguaggio, non la nostra idea di come dovrebbero essere.
 */

/* ------------------------------------------------------------------ JSON */

function validaJson(src) {
	if (!src.trim()) return { ok: true, errori: [] };
	try {
		JSON.parse(src);
		return { ok: true, errori: [] };
	} catch (e) {
		return { ok: false, errori: [{ line: rigaDalMessaggio(e.message, src), message: e.message }] };
	}
}

/* I motori dicono dove hanno inciampato in due modi diversi - «at position 417»
   oppure «line 12 column 3» - e nessuno dei due è quello che serve a chi guarda
   un gutter. Si accettano tutti e due e si risponde sempre con un numero di
   riga. */
function rigaDalMessaggio(messaggio, src) {
	const perRiga = /line (\d+)/i.exec(messaggio);
	if (perRiga) return Number(perRiga[1]);
	const perPosizione = /position (\d+)/i.exec(messaggio);
	if (perPosizione) {
		const fino = src.slice(0, Number(perPosizione[1]));
		return fino.split('\n').length;
	}
	return 0;
}

function formattaJson(src) {
	return JSON.stringify(JSON.parse(src), null, 2);
}

/* ------------------------------------------------------------- XML e XHTML */

/* Le entità con un nome sono di HTML, non di XML: un parser XML che incontra
   `&nbsp;` si ferma, e direbbe rotto un frammento che rotto non è. Si
   sostituiscono con il loro numero PRIMA di far leggere, e si scelgono scritture
   della stessa lunghezza dove si può, così le colonne restano dov'erano. Le
   righe comunque non si spostano: nessuna di queste contiene un a capo. */
const ENTITA = {
	'&nbsp;': '&#160;', '&copy;': '&#169;', '&reg;': '&#174;', '&deg;': '&#176;',
	'&laquo;': '&#171;', '&raquo;': '&#187;', '&hellip;': '&#8230;',
	'&ndash;': '&#8211;', '&mdash;': '&#8212;', '&euro;': '&#8364;',
	'&larr;': '&#8592;', '&rarr;': '&#8594;', '&times;': '&#215;', '&middot;': '&#183;',
	'&lsquo;': '&#8216;', '&rsquo;': '&#8217;', '&ldquo;': '&#8220;', '&rdquo;': '&#8221;',
};

function conEntitaNumeriche(src) {
	return src.replace(/&[a-zA-Z][a-zA-Z0-9]+;/g, (e) => ENTITA[e] || e);
}

function validaMarkup(src, { frammento = true } = {}) {
	if (!src.trim()) return { ok: true, errori: [] };
	const testo = frammento ? `<ws-radice>${conEntitaNumeriche(src)}</ws-radice>` : conEntitaNumeriche(src);
	const doc = new DOMParser().parseFromString(testo, 'application/xml');
	const errore = doc.querySelector('parsererror');
	if (!errore) return { ok: true, errori: [] };

	const messaggio = (errore.textContent || '').replace(/\s+/g, ' ').trim();
	/* La radice finta occupa la riga 1 insieme alla prima riga vera, quindi il
	   numero che torna il parser è già quello giusto - ma solo perché non ci
	   mettiamo un a capo dopo di lei. Se un giorno ce lo mettessimo, andrebbe
	   tolto uno. */
	return { ok: false, errori: [{ line: rigaDalMessaggio(messaggio, testo), message: ripulisci(messaggio) }] };
}

function validaXml(src) {
	return validaMarkup(src, { frammento: false });
}

/* Il messaggio dei parser è verboso e ripete il documento: si tiene la frase. */
function ripulisci(messaggio) {
	const taglio = messaggio.indexOf('error on line');
	const utile = taglio >= 0 ? messaggio.slice(taglio) : messaggio;
	return utile.split('\n')[0].slice(0, 300);
}

/* Elementi che stanno DENTRO una riga di testo: mandarli a capo cambierebbe
   quello che si legge, perché fra due tag in linea uno spazio conta. */
const IN_LINEA = new Set([
	'a', 'abbr', 'b', 'bdi', 'bdo', 'br', 'button', 'cite', 'code', 'data', 'dfn',
	'em', 'i', 'img', 'input', 'kbd', 'label', 'mark', 'q', 'rp', 'rt', 'ruby',
	's', 'samp', 'select', 'small', 'span', 'strong', 'sub', 'sup', 'textarea',
	'time', 'u', 'var', 'wbr',
]);

const VUOTI = new Set([
	'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta',
	'param', 'source', 'track', 'wbr',
]);

/**
 * Rimette il markup in colonna: un elemento di blocco per riga, rientrato di
 * quanto è profondo. Quello che sta in linea resta in linea.
 *
 * Non tocca il testo: nessuno spazio aggiunto o tolto dentro una riga, perché
 * in HTML uno spazio fra due tag si vede.
 */
function formattaMarkup(src) {
	const pezzi = src.match(/<[^>]+>|[^<]+/g) || [];
	const righe = [];
	let profondita = 0;

	const nomeDi = (pezzo) => (/^<\/?\s*([a-zA-Z][\w:-]*)/.exec(pezzo) || [])[1];
	const diBlocco = (pezzo) => {
		if (pezzo[0] !== '<' || /^<[!?]/.test(pezzo)) return false;
		const n = nomeDi(pezzo);
		return !!n && !IN_LINEA.has(n.toLowerCase());
	};
	const scrivi = (testo) => { const t = testo.trim(); if (t) righe.push('\t'.repeat(Math.max(0, profondita)) + t); };

	/* Dove chiude il blocco aperto in `da`, o -1. Serve per la domanda che
	   decide tutto: quello che c'è dentro merita di andare a capo? */
	const chiusuraDi = (da) => {
		const nome = nomeDi(pezzi[da]);
		let livello = 0;
		for (let i = da; i < pezzi.length; i += 1) {
			const p = pezzi[i];
			if (p[0] !== '<' || nomeDi(p) !== nome) continue;
			if (/^<\//.test(p)) { livello -= 1; if (livello === 0) return i; continue; }
			if (!/\/>$/.test(p)) livello += 1;
		}
		return -1;
	};

	let i = 0;
	let corrente = '';
	while (i < pezzi.length) {
		const pezzo = pezzi[i];

		if (!diBlocco(pezzo)) { corrente += pezzo; i += 1; continue; }
		if (/^<\//.test(pezzo)) {
			scrivi(corrente); corrente = '';
			profondita -= 1;
			scrivi(pezzo);
			i += 1; continue;
		}

		scrivi(corrente); corrente = '';

		/* UN BLOCCO CHE CONTIENE SOLO TESTO E TAG IN LINEA STA SU UNA RIGA.
		   `<h1>Campi</h1>` spezzato in tre righe non si legge meglio: si legge
		   peggio, perché costa tre righe per dire una cosa sola. Va a capo solo
		   chi dentro ha altri blocchi, che è quando il rientro serve davvero. */
		const chiude = chiusuraDi(i);
		const autochiuso = /\/>$/.test(pezzo) || VUOTI.has((nomeDi(pezzo) || '').toLowerCase());
		if (!autochiuso && chiude > i) {
			let soloInLinea = true;
			for (let j = i + 1; j < chiude; j += 1) { if (diBlocco(pezzi[j])) { soloInLinea = false; break; } }
			if (soloInLinea) {
				scrivi(pezzi.slice(i, chiude + 1).join('').replace(/\s+/g, ' '));
				i = chiude + 1; continue;
			}
		}

		scrivi(pezzo);
		if (!autochiuso) profondita += 1;
		i += 1;
	}
	scrivi(corrente);
	return righe.join('\n');
}

/* ------------------------------------------------------------------- CSS */

/* Il browser un parser CSS ce l'ha, ma perdona: una regola che non capisce la
   salta in silenzio, e un foglio mezzo rotto gli va benissimo. Quello che
   davvero rompe un foglio - e che lui non segnala - sono le graffe che non
   tornano, perché da lì in poi tutto finisce dentro la regola sbagliata. Quelle
   si contano qui, saltando stringhe e commenti. */
function validaCss(src) {
	if (!src.trim()) return { ok: true, errori: [] };
	const errori = [];
	const aperte = [];
	let riga = 1;
	let i = 0;

	while (i < src.length) {
		const c = src[i];
		if (c === '\n') { riga += 1; i += 1; continue; }
		if (c === '/' && src[i + 1] === '*') {
			const fine = src.indexOf('*/', i + 2);
			const dentro = src.slice(i, fine < 0 ? src.length : fine + 2);
			riga += (dentro.match(/\n/g) || []).length;
			if (fine < 0) { errori.push({ line: riga, message: 'Commento aperto e mai chiuso.' }); break; }
			i = fine + 2; continue;
		}
		if (c === '"' || c === "'") {
			let j = i + 1;
			while (j < src.length && src[j] !== c) { if (src[j] === '\\') j += 1; if (src[j] === '\n') break; j += 1; }
			if (src[j] !== c) { errori.push({ line: riga, message: 'Stringa aperta e mai chiusa.' }); break; }
			i = j + 1; continue;
		}
		if (c === '{') { aperte.push(riga); i += 1; continue; }
		if (c === '}') {
			if (!aperte.length) { errori.push({ line: riga, message: 'Una graffa chiude un blocco che non è mai stato aperto.' }); }
			else aperte.pop();
			i += 1; continue;
		}
		i += 1;
	}
	for (const r of aperte) errori.push({ line: r, message: 'Questo blocco non viene mai chiuso.' });
	return { ok: !errori.length, errori };
}

/** Un blocco per riga, rientrato di quanto è profondo; una dichiarazione per riga. */
function formattaCss(src) {
	const righe = [];
	let profondita = 0;
	let buffer = '';
	const scrivi = (testo) => { const t = testo.trim(); if (t) righe.push('\t'.repeat(Math.max(0, profondita)) + t); };
	/* Uno spazio dopo i due punti: `color: red`. Solo al PRIMO, perché quelli
	   dopo appartengono al valore - `background: url(http://…)`. */
	const dichiarazione = (testo) => testo.trim().replace(/^([^:]+):\s*/, '$1: ');

	for (let i = 0; i < src.length; i += 1) {
		const c = src[i];
		if (c === '{') { scrivi(buffer + ' {'); buffer = ''; profondita += 1; continue; }
		/* L'ultima dichiarazione del blocco, quella senza punto e virgola, passa
		   di qui: va spaziata come le altre, se no è l'unica scritta storta. */
		if (c === '}') { scrivi(dichiarazione(buffer)); buffer = ''; profondita -= 1; scrivi('}'); continue; }
		if (c === ';') { scrivi(dichiarazione(buffer) + ';'); buffer = ''; continue; }
		if (c === '\n') { continue; }
		buffer += c;
	}
	scrivi(buffer);
	return righe.join('\n');
}

/* -------------------------------------------------------------- JavaScript */

/* `new Function` COMPILA e non esegue: è il parser vero del motore, quindi dice
   di sì e di no esattamente come il browser di chi legge la pagina. Non dà però
   un numero di riga utilizzabile - il nostro sorgente finisce dentro un
   involucro suo - e inventarne uno sarebbe peggio che non darlo: si risponde
   con la riga 0, che il gutter non evidenzia. */
function validaJs(src) {
	if (!src.trim()) return { ok: true, errori: [] };
	try {
		// eslint-disable-next-line no-new-func
		new Function(src);
		return { ok: true, errori: [] };
	} catch (e) {
		return { ok: false, errori: [{ line: 0, message: e.message }] };
	}
}

/* ------------------------------------------------------------------------- */

export const LINGUAGGI = {
	xhtml: { nome: 'XHTML', formatta: formattaMarkup, valida: (s) => validaMarkup(s) },
	xml:   { nome: 'XML',   formatta: formattaMarkup, valida: validaXml },
	json:  { nome: 'JSON',  formatta: formattaJson,   valida: validaJson },
	css:   { nome: 'CSS',   formatta: formattaCss,    valida: validaCss },
	/* JavaScript si sa validare ma non formattare: rimetterlo in colonna senza
	   un parser vero vuol dire spezzare una stringa o un'espressione regolare
	   prima o poi, e un formattatore che ogni tanto rompe il codice è peggio di
	   nessun formattatore. Il pulsante non compare. */
	js:    { nome: 'JavaScript', formatta: null, valida: validaJs },
};

export function linguaggio(nome) {
	return LINGUAGGI[nome] || LINGUAGGI.xhtml;
}
