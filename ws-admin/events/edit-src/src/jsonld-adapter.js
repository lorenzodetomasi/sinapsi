import { conOffset, senzaOffset, fusoPer } from './quando.js';

// Adapter tra il modello "piatto" del form e la forma JSON-LD reale (index.json).
// Tutte le chiavi @-prefissate, i @type (anche array) e i namespace (meetoo:…)
// vivono QUI: il form non le vede, l'adapter le aggiunge/rimuove deterministicamente.

const CONTEXT = ['https://schema.org', { meetoo: 'https://meetoo.eu#' }];

const asArray = (v) => (Array.isArray(v) ? v : v == null ? [] : [v]);

// Nei file veri un sì/no può essere arrivato come STRINGA ("true", "false"), da
// un'importazione o da una modifica a mano. Il form vuole un booleano: senza
// conversione JSON Forms non disegna proprio la casella e al suo posto lascia un
// «must be boolean» — il campo diventa illeggibile e non modificabile. `!!` non
// basta: la stringa "false" è vera.
const asBool = (v) =>
  typeof v === 'string' ? /^(true|1|s[iì]|yes|on)$/i.test(v.trim()) : !!v;

// Riferimento a un evento in forma canonica `events/{slug}` (lo slug nudo è la forma
// self-@id, NON un riferimento). Se già qualificato (contiene "/") resta invariato.
const toEventRef = (id) => {
  const s = String(id ?? '').trim();
  return !s || s.includes('/') ? s : 'events/' + s;
};

// Tipi schema.org di RADICE derivati da meetoo:@type (non editabili tra i tag):
// meetoo:EventSingle -> Event, meetoo:EventSeries -> EventSeries.
const BASE_TYPES = ['Event', 'EventSeries'];

// sameAs (schema.org) è un array di url. Il "social" nel form è solo un'etichetta
// d'aiuto: al caricamento lo deduciamo dall'host, in uscita emettiamo solo gli url.
const SOCIAL_HOSTS = [
  [/facebook\.com/i, 'Facebook'],
  [/instagram\.com/i, 'Instagram'],
  [/linkedin\.com/i, 'LinkedIn'],
  [/tiktok\.com/i, 'TikTok'],
  [/(youtube\.com|youtu\.be)/i, 'YouTube'],
  [/(twitter\.com|x\.com)/i, 'X'],
  [/(t\.me|telegram\.)/i, 'Telegram'],
  [/(wa\.me|whatsapp\.)/i, 'WhatsApp'],
  [/threads\.net/i, 'Threads'],
  [/pinterest\./i, 'Pinterest'],
];
const detectSocial = (url) => {
  for (const [re, name] of SOCIAL_HOSTS) if (re.test(url)) return name;
  return '';
};

// eventSchedule (schema.org Schedule) <-> modello del form della ricorrenza.
function fromSchedule(sched) {
  /* `presente` distingue «non si ripete» da «si ripete ogni settimana», che senza
   * di lui erano la stessa cosa: il form parte da valori di comodo (settimanale,
   * ogni 1) e salvando li scriveva nel file anche per una collezione che una
   * ricorrenza non l'aveva mai avuta — inventandogliela. */
  const d = { presente: false, aderenza: 'fissa', frequency: 'weekly', interval: 1, byDay: [], endMode: 'never', until: '', count: 10 };
  if (!sched) return d;
  d.presente = true;
  if (sched['meetoo:aderenza'] === 'indicativa') d.aderenza = 'indicativa';
  const m = /^P(\d+)([DWMY])$/.exec(sched.repeatFrequency || '');
  if (m) {
    d.frequency = { D: 'daily', W: 'weekly', M: 'monthly', Y: 'yearly' }[m[2]];
    d.interval = Number(m[1]);
  }
  if (sched.byDay) d.byDay = String(sched.byDay).split(',').map((x) => x.trim()).filter(Boolean);
  if (sched.endDate) { d.endMode = 'until'; d.until = sched.endDate; }
  else if (sched.repeatCount) { d.endMode = 'count'; d.count = Number(sched.repeatCount); }
  if (sched.scheduleTimezone) d.timezone = sched.scheduleTimezone;
  return d;
}
function toSchedule(s, fuso) {
  if (!s || !s.presente) return undefined;
  const unit = { daily: 'D', weekly: 'W', monthly: 'M', yearly: 'Y' }[s.frequency] || 'W';
  const out = { '@type': 'Schedule', repeatFrequency: `P${Math.max(1, Number(s.interval) || 1)}${unit}` };
  if (s.frequency === 'weekly' && s.byDay?.length) out.byDay = s.byDay.join(',');
  if (s.endMode === 'until' && s.until) out.endDate = s.until;
  if (s.endMode === 'count' && s.count) out.repeatCount = Number(s.count);
  // Il fuso è quello dell'evento: la ricorrenza non ne ha uno suo.
  if (fuso) out.scheduleTimezone = fuso;
  // «Indicativa» si scrive; «fissa» è la regola normale e non serve dirla.
  if (s.aderenza === 'indicativa') out['meetoo:aderenza'] = 'indicativa';
  return out;
}

/** JSON-LD (index.json) -> dati del form. */
export function fromJsonLd(doc) {
  const o = doc.offers ?? {};
  const loc = doc.location ?? {};
  const rating = doc.aggregateRating ?? {};
  const typeArr = asArray(doc['@type']);
  const primaryType = typeArr.find((t) => BASE_TYPES.includes(t)) || 'Event';
  const isSeries = primaryType === 'EventSeries';
  const subEventArr = doc.subEvent ?? [];
  // Capienze: il totale è presenza+remoto (o il valore salvato), i prenotati si
  // ricavano da totale − rimasti così il round-trip resta coerente.
  const maxPhysical = doc.maximumPhysicalAttendeeCapacity ?? 0;
  const maxVirtual = doc.maximumVirtualAttendeeCapacity ?? 0;
  const maxTotal = doc.maximumAttendeeCapacity ?? maxPhysical + maxVirtual;
  const remaining = doc.remainingAttendeeCapacity ?? maxTotal;
  /* «Posti limitati» si deduce dal DOCUMENTO, non da un campo suo: se una capienza
   * c'e' scritta, quell'evento i posti li conta. Si guarda il documento originale e
   * non i valori qui sopra, che un numero ce l'hanno sempre (zero) anche quando nel
   * file non c'era niente — e aprendo un evento vecchio i suoi posti devono
   * comparire, non nascondersi dietro una spunta spenta. */
  const haCapienza = [
    'maximumPhysicalAttendeeCapacity',
    'maximumVirtualAttendeeCapacity',
    'maximumAttendeeCapacity',
    'bookedAttendeeCapacity',
    'remainingAttendeeCapacity',
  ].some((k) => Number(doc[k]) > 0);
  return {
    id: doc['@id'] ?? '',
    url: doc.url ?? '',
    sameAs: asArray(doc.sameAs)
      .map((u) => String(u).trim())
      .filter(Boolean)
      .map((u) => ({ social: detectSocial(u), url: u })),
    primaryType,
    // Tipi = @type esclusi i tipi primari (Event/EventSeries)
    types: typeArr.filter((t) => !BASE_TYPES.includes(t)),
    additionalType: asArray(doc.additionalType).map((s) => String(s).trim()).filter(Boolean),
    // In lettura si accetta sia l'array (forma attuale) sia la vecchia stringa
    // separata da virgole: i file già scritti non vanno riaperti per forza.
    keywords: dedupeKeywords(asArray(doc.keywords)),
    name: doc.name ?? '',
    // Sommario e frase SEO: due campi distinti. I contenuti scritti prima che si
    // separassero hanno il testo formattato dentro `description`; si lasciano
    // dove sono — la migrazione è a mano — e il campo Descrizione lo segnala.
    abstract: doc.abstract ?? '',
    description: doc.description ?? '',
    image: doc.image ?? '',
    logo: doc.logo ?? '',
    // Nel form le date stanno senza scarto: i campi datetime-local vogliono l'ora
    // di parete, che è anche quella scritta sulla locandina.
    startDate: senzaOffset(doc.startDate ?? ''),
    endDate: senzaOffset(doc.endDate ?? ''),
    // Il fuso: prima quello salvato, poi quello della ricorrenza (dove viveva
    // finora), poi niente — e ci pensa il campo a proporre quello del browser.
    timezone: doc['meetoo:timezone'] || doc.eventSchedule?.scheduleTimezone || '',
    typicalAgeRange: doc.typicalAgeRange ?? '',
    eventAttendanceMode: doc.eventAttendanceMode ?? '',
    eventStatus: doc.eventStatus ?? '',
    maximumPhysicalAttendeeCapacity: maxPhysical,
    maximumVirtualAttendeeCapacity: maxVirtual,
    maximumAttendeeCapacity: maxTotal,
    bookedAttendeeCapacity: Math.max(0, maxTotal - remaining),
    remainingAttendeeCapacity: remaining,
    isAccessibleForFree: asBool(doc.isAccessibleForFree),
    offers: {
      availability: o.availability ?? '',
      price: o.price ?? 0,
      priceCurrency: o.priceCurrency ?? 'EUR',
      url: o.url ?? '',
    },
    location: {
      id: loc['@id'] ?? '',
      type: loc['@type'] ?? 'Place',
      name: loc.name ?? '',
      googlePlaceId: loc['meetoo:googlePlaceId'] ?? '',
    },
    organizer: (doc.organizer ?? []).map((x) => ({
      id: x['@id'] ?? '',
      name: x.name ?? '',
      googlePlaceId: x['meetoo:googlePlaceId'] ?? '',
    })),
    // subEvent è il programma per un Single, le occorrenze (link @id) per una Series
    subEvent: isSeries
      ? []
      : subEventArr.map((s) => ({
          name: s.name ?? '',
          description: s.description ?? '',
          startDate: senzaOffset(s.startDate ?? ''),
          endDate: senzaOffset(s.endDate ?? ''),
        })),
    occurrences: isSeries ? subEventArr.map((s) => ({ id: s['@id'] ?? '', name: s.name ?? '' })) : [],
    // Riferimento alla serie contenitrice (occorrenza → serie). Non ha un campo UI dedicato,
    // ma va preservato nel round-trip per non perdere l'appartenenza alla collection al salvataggio.
    superEvent: typeof doc.superEvent === 'string' ? doc.superEvent : (doc.superEvent?.['@id'] ?? ''),
    eventSchedule: fromSchedule(doc.eventSchedule),
    aggregateRating: {
      ratingValue: rating.ratingValue ?? '',
      bestRating: rating.bestRating ?? '',
    },
    // Flag pubblico (booleani meetoo dedicati)
    hasLimitedCapacity: haCapienza,
    childrenMustBeAccompanied: asBool(doc['meetoo:childrenMustBeAccompanied']),
    forSeparatedParents: asBool(doc['meetoo:forSeparatedParents']),
    contributor: (Array.isArray(doc.contributor) ? doc.contributor : (doc.contributor ? [doc.contributor] : []))
      .map((x) => (x && typeof x === 'object' ? String(x['@id'] ?? '') : String(x))).filter(Boolean),
  };
}

/**
 * Ripulisce una lista di keywords: toglie spazi e vuoti, scarta i doppioni
 * (confronto senza maiuscole/minuscole) tenendo la PRIMA grafia incontrata — così
 * l'ordine di chi scrive non cambia — e SPEZZA le voci che contengono una virgola.
 *
 * Lo spezzare sulle virgole serve ancora, anche ora che si salva un ARRAY: i file
 * scritti prima hanno la stringa unica, e chi digita continua a incollare elenchi
 * separati da virgole. È da qui che nascevano i doppioni di località e regione — il
 * luogo della serie si chiama «Lido di Ostia, Roma»: nella vecchia stringa veniva
 * spezzato in due voci e al salvataggio dopo si ri-aggiungeva intero, senza
 * combaciare con nessuna delle due metà. Ogni giro ne produceva un paio in più.
 */
export function dedupeKeywords(list) {
  const viste = new Set();
  const out = [];
  (Array.isArray(list) ? list : []).forEach((k) => {
    String(k ?? '')
      .split(',')
      .forEach((parte) => {
        const value = parte.trim();
        if (!value) return;
        const chiave = value.toLowerCase();
        if (viste.has(chiave)) return;
        viste.add(chiave);
        out.push(value);
      });
  });
  return out;
}

/**
 * Le keywords includono SEMPRE i `name` di tutti gli organizer e del luogo:
 * quelli mancanti vengono aggiunti in coda. Le keywords scritte a mano restano e
 * mantengono il loro ordine; i doppioni spariscono (vedi dedupeKeywords).
 */
function mergeKeywords(d) {
  const auto = [...(d.organizer ?? []).map((o) => o?.name), d.location?.name];
  return dedupeKeywords([...asArray(d.keywords), ...auto]);
}

/** Dati del form -> JSON-LD (index.json), reintroducendo @context/@type/@id/namespace.
 *  L'ordine delle chiavi segue index.json. */
export function toJsonLd(d) {
  // primaryType (Event/EventSeries) è il primo @type e il discriminante.
  const primaryType = d.primaryType === 'EventSeries' ? 'EventSeries' : 'Event';
  const isSeries = primaryType === 'EventSeries';
  const subtypes = (d.types ?? []).filter((t) => !BASE_TYPES.includes(t));
  const typeArr = [primaryType, ...subtypes];
  const types = typeArr.length === 1 ? typeArr[0] : typeArr;

  const addTypeArr = Array.isArray(d.additionalType) ? d.additionalType.filter(Boolean) : d.additionalType ? [d.additionalType] : [];
  const additionalType = addTypeArr.length === 0 ? '' : addTypeArr.length === 1 ? addTypeArr[0] : addTypeArr;

  // Il fuso da usare per lo scarto: quello scelto, o quello del computer che sta
  // redigendo (il campo vuoto significa proprio quello, non «nessun fuso»).
  const fuso = fusoPer(d);

  // Nodi figli con i soli campi valorizzati (niente stringhe/valori vuoti).
  const program = (d.subEvent ?? [])
    .filter((s) => s.name || s.description || s.startDate || s.endDate)
    .map((s) => ({
      '@type': 'Event',
      ...(s.name ? { name: s.name } : {}),
      ...(s.description ? { description: s.description } : {}),
      ...(s.startDate ? { startDate: conOffset(s.startDate, fuso) } : {}),
      ...(s.endDate ? { endDate: conOffset(s.endDate, fuso) } : {}),
    }));
  const occurrences = (d.occurrences ?? [])
    .filter((o) => o.id || o.name)
    .map((o) => ({ ...(o.id ? { '@id': toEventRef(o.id) } : {}), '@type': 'Event', ...(o.name ? { name: o.name } : {}) }));
  const subEvent = isSeries ? occurrences : program;
  const schedule = isSeries ? toSchedule(d.eventSchedule, fuso) : undefined;

  // sameAs: solo gli url (schema.org), il "social" del form è d'aiuto UI
  const sameAs = (d.sameAs ?? []).map((s) => (s?.url ?? '').trim()).filter(Boolean);

  /* Spunta spenta = nessun tetto, e allora nel file non si scrive nessuna
   * capienza: un `maximumAttendeeCapacity: 0` direbbe «zero posti», che e' un'altra
   * cosa. I numeri restano nel form, cosi' riaccendendo la spunta si ritrovano. */
  const num = (v) => (d.hasLimitedCapacity ? Number(v) || 0 : 0);
  const physical = num(d.maximumPhysicalAttendeeCapacity);
  const virtual = num(d.maximumVirtualAttendeeCapacity);
  const total = num(d.maximumAttendeeCapacity);
  const remaining = num(d.remainingAttendeeCapacity);
  const kw = mergeKeywords(d);

  // Sotto-oggetti opzionali: inclusi solo se hanno contenuto reale.
  const price = num(d.offers?.price);
  const offers =
    d.offers?.availability || price || d.offers?.url
      ? {
          '@type': 'Offer',
          ...(d.offers?.availability ? { availability: d.offers.availability } : {}),
          ...(price ? { price } : {}),
          priceCurrency: d.offers?.priceCurrency || 'EUR',
          ...(d.offers?.url ? { url: d.offers.url } : {}),
        }
      : null;
  const location =
    d.location?.id || d.location?.name
      ? {
          ...(d.location.id ? { '@id': d.location.id } : {}),
          '@type': d.location.type || 'Place',
          ...(d.location.name ? { name: d.location.name } : {}),
          ...(d.location.googlePlaceId ? { 'meetoo:googlePlaceId': d.location.googlePlaceId } : {}),
        }
      : null;
  const organizer = (d.organizer ?? [])
    .filter((x) => x.id || x.name)
    .map((x) => ({
      ...(x.id ? { '@id': x.id } : {}),
      '@type': 'Organization',
      ...(x.name ? { name: x.name } : {}),
      ...(x.googlePlaceId ? { 'meetoo:googlePlaceId': x.googlePlaceId } : {}),
    }));
  const aggregateRating = d.aggregateRating?.ratingValue
    ? {
        '@type': 'AggregateRating',
        ratingValue: d.aggregateRating.ratingValue,
        ...(d.aggregateRating.bestRating ? { bestRating: d.aggregateRating.bestRating } : {}),
      }
    : null;

  return {
    '@context': CONTEXT,
    '@id': d.id ?? '',
    ...(d.url ? { url: d.url } : {}),
    ...(sameAs.length ? { sameAs } : {}),
    '@type': types,
    ...(additionalType ? { additionalType } : {}),
    ...(kw.length ? { keywords: kw } : {}),
    name: d.name ?? '',
    ...(d.abstract ? { abstract: d.abstract } : {}),
    ...(d.description ? { description: d.description } : {}),
    ...(d.image ? { image: d.image } : {}),
    ...(d.logo ? { logo: d.logo } : {}),
    /* Le date escono con lo scarto da UTC. Un'ora senza scarto è ambigua: «17:30»
     * a Ostia e «17:30» a Berlino sono due istanti diversi, e chi legge il JSON da
     * fuori non ha modo di saperlo. Il NOME del fuso viaggia a parte, perché dallo
     * scarto non si ricava (+02:00 d'estate ce l'ha mezza Europa). */
    ...(d.startDate ? { startDate: conOffset(d.startDate, fuso) } : {}),
    ...(d.endDate ? { endDate: conOffset(d.endDate, fuso) } : {}),
    ...(d.startDate || d.endDate ? { 'meetoo:timezone': fuso } : {}),
    ...(d.typicalAgeRange ? { typicalAgeRange: d.typicalAgeRange } : {}),
    ...(d.eventAttendanceMode ? { eventAttendanceMode: d.eventAttendanceMode } : {}),
    ...(physical ? { maximumPhysicalAttendeeCapacity: physical } : {}),
    ...(virtual ? { maximumVirtualAttendeeCapacity: virtual } : {}),
    ...(total ? { maximumAttendeeCapacity: total } : {}),
    ...(remaining ? { remainingAttendeeCapacity: remaining } : {}),
    isAccessibleForFree: !!d.isAccessibleForFree,
    ...(offers ? { offers } : {}),
    ...(location ? { location } : {}),
    ...(organizer.length ? { organizer } : {}),
    ...(schedule ? { eventSchedule: schedule } : {}),
    ...(subEvent.length ? { subEvent } : {}),
    ...(d.superEvent ? { superEvent: toEventRef(d.superEvent) } : {}),
    ...(d.eventStatus ? { eventStatus: d.eventStatus } : {}),
    ...(aggregateRating ? { aggregateRating } : {}),
    /* Flag pubblico: emessi solo se attivi. `meetoo:isChildrensEvent` non si scrive
     * più e non si legge più — lo diceva già la fascia, e un fatto scritto in due
     * posti prima o poi si contraddice. Nei file di prima resta finché non li si
     * risalva; le liste non lo guardano più. */
    ...(d.childrenMustBeAccompanied ? { 'meetoo:childrenMustBeAccompanied': true } : {}),
    ...(d.forSeparatedParents ? { 'meetoo:forSeparatedParents': true } : {}),
    // Chi altro può modificarlo. Sempre presente (anche vuoto) perché il server
    // distingue «non l'ho toccato» da «l'ho svuotato»: senza la chiave, ripristina.
    contributor: (d.contributor || [])
      .map((x) => String(x).trim()).filter(Boolean)
      .map((uid) => ({ '@type': 'Person', '@id': uid.startsWith('users/') ? uid : `users/${uid}` })),
  };
}

// Configurazione base di un nuovo evento: solo @context e @type radice (Event).
// Tutti gli altri campi partono dai default di fromJsonLd (form vuoto ma valido nel modello).
export const blankJsonLd = {
  '@context': CONTEXT,
  '@id': '',
  '@type': 'Event',
};

/* Il documento di partenza secondo il TIPO scelto prima del modulo (`?tipo=`).
 *
 * Non è comodità: è la sola cosa che il modulo non può dedurre da solo, e
 * sbagliarla non si disfa. Una giornata a blocchi resta UN evento con dentro il
 * suo programma — la tentazione è farne una collezione, «sono tre cose», ma le
 * occorrenze di una collezione sono eventi veri con un indirizzo ciascuno, e tre
 * conferenze dello stesso pomeriggio non lo sono. Una rassegna che si ripete
 * invece è una collezione per davvero, e parte già con la ricorrenza accesa
 * perché è quella la sua ragione di esistere.
 */
export function docNuovo(tipo) {
  if (tipo === 'giornata') {
    // Una riga di programma vuota: dice dove vanno i blocchi, senza compilarli.
    return { ...blankJsonLd, subEvent: [{ '@type': 'Event', name: '', startDate: '', endDate: '' }] };
  }
  if (tipo === 'serie-regolare') {
    return { ...blankJsonLd, '@type': 'EventSeries', eventSchedule: { '@type': 'Schedule', repeatFrequency: 'P1W' } };
  }
  if (tipo === 'serie-variabile') {
    return { ...blankJsonLd, '@type': 'EventSeries' };
  }
  return blankJsonLd;   // «singolo», e qualunque cosa non riconosciamo
}
