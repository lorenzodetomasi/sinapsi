// Come nasce l'@id di un evento. La forma non è un'invenzione: è quella dei file
// già scritti, letta e messa in regola.
//
//   Evento singolo   20261017T1000-IT00122-giornata_della_gentilezza
//                    └ quando ──┘ └ dove ┘ └ che cosa ──────────────┘
//   Serie            clubdellibro-ostia-reading_party
//                    └ chi ───────────┘ └ che cosa ─┘
//
// Un evento singolo si distingue per QUANDO e DOVE succede (due eventi con lo
// stesso nome nello stesso posto, in date diverse, restano distinti); una serie
// non ha una data propria e si distingue per CHI la organizza.
//
// Le parole del nome si legano con `_`, i pezzi fra loro con `-`: così si vede a
// colpo d'occhio dove finisce una parte e comincia l'altra.
//
// L'@id porta SEMPRE la cartella (`events/…`), come i file già scritti e come i
// riferimenti che li citano (`superEvent: "events/clubdellibro-ostia-junior"`): un
// @id è un riferimento, e senza la cartella non si risolve.

/** Una parola per volta: senza accenti, minuscola, solo lettere e numeri. */
const parole = (testo) =>
  String(testo ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .split(/[^a-z0-9]+/)
    .filter(Boolean);

/** Le parole del nome legate da `_`. Niente rimozione di articoli e preposizioni:
 *  un @id si legge una volta e si scrive per sempre, meglio prevedibile che breve
 *  (chi vuole accorciarlo lo fa a mano, è un campo scrivibile). */
export const slugNome = (nome) => parole(nome).join('_');

/** L'ultimo segmento di un @id: `organizations/clubdellibro-ostia` → `clubdellibro-ostia`. */
const ultimoSegmento = (id) =>
  String(id ?? '')
    .trim()
    .replace(/^\/+|\/+$/g, '')
    .split('/')
    .pop() || '';

/** Regione dall'@id del luogo: `places/IT00122/piazzaancomarzio` → `IT00122`.
 *  Vale solo la forma paese+CAP: `places/lido-di-ostia/lungomare` non ne ha una. */
export function regioneDaLuogo(idLuogo) {
  const pezzi = String(idLuogo ?? '').split('/');
  const forse = pezzi[1] || '';
  return /^[A-Z]{2}\d{4,5}$/.test(forse) ? forse : '';
}

/** Data e ora compatte da un `datetime-local` (2026-10-17T10:00 → 20261017T1000).
 *  Si prendono le cifre come sono scritte: l'ora dell'evento è l'ora del posto in
 *  cui succede, e convertirla in UTC sposterebbe di un'ora il nome della cartella. */
export function dataCompatta(startDate) {
  const m = /^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})/.exec(String(startDate ?? '').trim());
  return m ? `${m[1]}${m[2]}${m[3]}T${m[4]}${m[5]}` : '';
}

/** Le parole del nome che l'organizzatore non ha già detto. «Club del libro
 *  Junior» organizzato da `clubdellibro-ostia` diventa `junior`: ripetere il nome
 *  dell'organizzatore dentro l'@id non aggiunge niente. */
function codaSerie(nome, idOrganizzatore) {
  const gia = ultimoSegmento(idOrganizzatore).replace(/[^a-z0-9]/gi, '').toLowerCase();
  const restanti = parole(nome).filter((p) => !gia.includes(p));
  return (restanti.length ? restanti : parole(nome)).join('_');
}

/** I pezzi che servono e che ancora mancano, con il nome che hanno nel form. */
export function pezziMancanti(d) {
  const serie = d?.primaryType === 'EventSeries';
  const mancano = [];
  if (!slugNome(d?.name)) mancano.push('il nome');
  if (serie) {
    if (!ultimoSegmento(d?.organizer?.[0]?.id)) mancano.push('l’organizzatore');
  } else {
    if (!dataCompatta(d?.startDate)) mancano.push('la data di inizio');
    if (!regioneDaLuogo(d?.location?.id)) mancano.push('il luogo (con paese e CAP nell’@id)');
  }
  return mancano;
}

/** L'@id proposto, con quello che c'è finora. Ritorna '' se manca l'essenziale. */
export function proponiId(d) {
  const serie = d?.primaryType === 'EventSeries';
  const nome = slugNome(d?.name);
  if (!nome) return '';

  if (serie) {
    const chi = ultimoSegmento(d?.organizer?.[0]?.id);
    if (!chi) return '';
    return `events/${chi}-${codaSerie(d.name, d.organizer[0].id)}`;
  }

  const quando = dataCompatta(d?.startDate);
  const dove = regioneDaLuogo(d?.location?.id);
  if (!quando || !dove) return '';
  // Un'occorrenza di una serie porta il nome della serie, non il proprio: così le
  // occorrenze della stessa serie si riconoscono in ordine alfabetico.
  const coda = ultimoSegmento(d?.superEvent) || nome;
  return `events/${quando}-${dove}-${coda}`;
}

/** Che cosa non va in un @id. Ritorna '' se va bene.
 *  Si guarda la forma INTERA, non i singoli caratteri: cercare «le maiuscole
 *  fuori posto» significa inciampare nella T della data e nel CAP, che maiuscoli
 *  ci vanno. */
export function difettoId(id, primaryType) {
  const v = String(id ?? '').trim();
  if (!v) return 'manca';
  if (/^\/|\/$/.test(v)) return 'non deve cominciare né finire con «/»';
  if (/\s/.test(v)) return 'non può contenere spazi';
  if (!/^[A-Za-z0-9/_-]+$/.test(v)) return 'ammette solo lettere, numeri, «-», «_» e «/»';

  const nudo = v.replace(/^events\//, '');
  if (primaryType === 'EventSeries') {
    return /^[a-z0-9][a-z0-9_-]*$/.test(nudo)
      ? ''
      : 'una serie si scrive in minuscolo (es. clubdellibro-ostia-reading_party)';
  }
  if (!/^\d{8}T\d{4}-/.test(nudo)) return 'un evento singolo comincia con data e ora (es. 20261017T1000-…)';
  return /^\d{8}T\d{4}-[A-Z]{2}\d{4,5}-[a-z0-9][a-z0-9_-]*$/.test(nudo)
    ? ''
    : 'la forma è data-CAP-nome (es. 20261017T1000-IT00122-giornata_della_gentilezza)';
}

/** Il percorso in cui finirà il file. */
export const percorsoDa = (id) => {
  const v = String(id ?? '').trim().replace(/^\/+|\/+$/g, '');
  if (!v) return '';
  return v.includes('/') ? v : 'events/' + v;
};
