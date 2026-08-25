// Maiuscole e minuscole. Non è formattazione: cambia i caratteri, perché lo stile
// (`text-transform`) qui non c'è — l'editor toglie gli stili in linea per scelta, e
// un testo «tutto maiuscolo» solo a schermo tornerebbe minuscolo appena il JSON
// finisce in un feed, in un PDF o in un altro sito.

/* Le parole che in «Alto e Basso» restano minuscole: articoli, preposizioni e
 * congiunzioni brevi. Prima e ultima parola fanno eccezione, sempre maiuscole:
 * «Il Libro del Mese», non «il Libro del mese». */
const MINORI = new Set([
  'a','ad','ai','al','alla','alle','allo','agli','con','coi','col','da','dai','dal','dalla','dalle',
  'dallo','degli','dei','del','della','delle','dello','di','e','ed','fra','gli','i','il','in','la',
  'le','lo','ma','nei','nel','nella','nelle','nello','negli','o','od','per','su','sui','sul','sulla',
  'sulle','sullo','tra','un','una','uno',
]);

const LETTERA = /[\p{L}\p{N}]/u;

/** Confini della parola che contiene (o comincia a) `i`. */
function parolaIntorno(testo, i) {
  let da = i;
  while (da > 0 && LETTERA.test(testo[da - 1])) da--;
  let a = i;
  while (a < testo.length && LETTERA.test(testo[a])) a++;
  return [da, a];
}

/** Prima lettera di una frase? Si guarda indietro fino a un punto fermo. */
function inizioFrase(testo, i) {
  for (let k = i - 1; k >= 0; k--) {
    const c = testo[k];
    if (/\s/.test(c)) continue;
    if (/["'«»()\[\]]/.test(c)) continue;
    return /[.!?…]/.test(c);
  }
  return true; // niente prima: è l'inizio
}

/**
 * Trasforma un testo. I quattro casi sono quelli di un correttore di bozze:
 *   'alto'    MAIUSCOLO
 *   'basso'   minuscolo
 *   'altobasso'  Alto e Basso (iniziali maiuscole, parole brevi minuscole)
 *   'frase'   Iniziale maiuscola (solo la prima lettera di ogni frase)
 */
export function applicaCaso(testo, caso) {
  const s = String(testo ?? '');
  if (!s) return s;
  if (caso === 'alto') return s.toUpperCase();
  if (caso === 'basso') return s.toLowerCase();

  if (caso === 'frase') {
    const basso = s.toLowerCase();
    let out = '';
    for (let i = 0; i < basso.length; i++) {
      const c = basso[i];
      out += LETTERA.test(c) && inizioFrase(basso, i) ? c.toUpperCase() : c;
    }
    return out;
  }

  // Alto e Basso
  const basso = s.toLowerCase();
  let out = '';
  let i = 0;
  while (i < basso.length) {
    if (!LETTERA.test(basso[i])) {
      out += basso[i];
      i++;
      continue;
    }
    const [da, a] = parolaIntorno(basso, i);
    const parola = basso.slice(da, a);
    const primaParola = da === 0 || !/[\p{L}\p{N}]/u.test(basso.slice(0, da));
    const ultimaParola = !LETTERA.test(basso.slice(a).replace(/[^\p{L}\p{N}]/gu, '')[0] ?? '');
    const minuscola = MINORI.has(parola) && !primaParola && !ultimaParola;
    out += minuscola ? parola : parola[0].toUpperCase() + parola.slice(1);
    i = a;
  }
  return out;
}
