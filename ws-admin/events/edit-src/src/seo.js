/* Il sommario e la frase per i motori di ricerca: due testi, due mestieri.
 *
 * SOMMARIO (`abstract`) è quello che legge una persona quando apre la pagina:
 * può avere corsivi, collegamenti, un elenco. FRASE SEO (`description`) è quella
 * che appare nel risultato di ricerca e nell'anteprima di un collegamento
 * condiviso: è testo e basta, perché lì la marcatura non arriva.
 *
 * Finora erano lo stesso campo, e si vedeva: la stessa frase compariva due volte
 * nella pagina, una volta come sommario e una come corpo.
 *
 * Sulla LUNGHEZZA, per essere onesti: Google non taglia a caratteri ma a pixel
 * (circa 920 su desktop, 680 su telefono), e nella maggior parte dei casi la
 * frase se la riscrive da sé pescando dalla pagina. Bing sta intorno ai 160
 * caratteri. Quindi qui non si blocca niente: si dice dove sta la misura comoda e
 * si lascia decidere a chi scrive.
 */

export const SEO_LIMITI = {
  corta: 110,   // sotto: spazio sprecato, il risultato di ricerca ne mostrerebbe di più
  buona: 155,   // la banda comoda: entra quasi sempre intera
  lunga: 170,   // oltre: viene tagliata quasi certamente
};

/** Come sta la frase rispetto alle misure: 'corta' | 'buona' | 'lunga' | 'troppo'. */
export function statoLunghezza(testo) {
  const n = (testo || '').length;
  if (n === 0) return 'vuota';
  if (n < SEO_LIMITI.corta) return 'corta';
  if (n <= SEO_LIMITI.buona) return 'buona';
  if (n <= SEO_LIMITI.lunga) return 'lunga';
  return 'troppo';
}

/** C'è marcatura qui dentro? (Serve a dire «questo testo va nel Sommario».) */
export function haMarcatura(testo) {
  return /<[a-z][^>]*>/i.test(String(testo || ''));
}

/**
 * Da XHTML a testo semplice: via i tag, sciolte le entità, gli spazi normalizzati.
 * I blocchi che finiscono diventano uno spazio, non niente: senza, «…fine.<p>Inizio…»
 * si incollerebbe in «fine.Inizio».
 */
export function testoSemplice(xhtml) {
  const s = String(xhtml || '')
    .replace(/<\/(p|div|li|h[1-6]|blockquote|section|tr)>/gi, ' ')
    .replace(/<(br|hr)\s*\/?>/gi, ' ')
    .replace(/<[^>]*>/g, '');
  const d = document.createElement('textarea');
  d.innerHTML = s;
  return d.value.replace(/\s+/g, ' ').trim();
}

/**
 * La proposta di frase SEO a partire dal sommario.
 *
 * Si preferisce chiudere su una FRASE intera: un riassunto che finisce col punto
 * si legge come una frase scritta apposta, uno tagliato a metà si legge come un
 * troncamento — ed è la prima impressione che qualcuno ha del contenuto. Se però
 * la prima frase è già più lunga del limite, si taglia sull'ultima parola intera.
 */
export function riassunto(xhtml, max = SEO_LIMITI.buona) {
  const t = testoSemplice(xhtml);
  if (t.length <= max) return t;

  const finiFrase = [...t.slice(0, max + 1).matchAll(/[.!?](\s|$)/g)].map((m) => m.index + 1);
  const ultima = finiFrase.length ? finiFrase[finiFrase.length - 1] : 0;
  if (ultima >= SEO_LIMITI.corta - 20) return t.slice(0, ultima).trim();

  const tagliato = t.slice(0, max - 1);
  const spazio = tagliato.lastIndexOf(' ');
  return (spazio > 0 ? tagliato.slice(0, spazio) : tagliato).replace(/[ ,;:.]+$/, '') + '…';
}
