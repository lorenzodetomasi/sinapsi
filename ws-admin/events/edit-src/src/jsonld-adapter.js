// Adapter tra il modello "piatto" del form e la forma JSON-LD reale (index.json).
// Tutte le chiavi @-prefissate, i @type (anche array) e i namespace (meetoo:…)
// vivono QUI: il form non le vede, l'adapter le aggiunge/rimuove deterministicamente.

const CONTEXT = ['https://schema.org', { meetoo: 'https://meetoo.eu#' }];

const asArray = (v) => (Array.isArray(v) ? v : v == null ? [] : [v]);

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
  const d = { frequency: 'weekly', interval: 1, byDay: [], endMode: 'never', until: '', count: 10, timezone: '' };
  if (!sched) return d;
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
function toSchedule(s) {
  if (!s) return undefined;
  const unit = { daily: 'D', weekly: 'W', monthly: 'M', yearly: 'Y' }[s.frequency] || 'W';
  const out = { '@type': 'Schedule', repeatFrequency: `P${Math.max(1, Number(s.interval) || 1)}${unit}` };
  if (s.frequency === 'weekly' && s.byDay?.length) out.byDay = s.byDay.join(',');
  if (s.endMode === 'until' && s.until) out.endDate = s.until;
  if (s.endMode === 'count' && s.count) out.repeatCount = Number(s.count);
  if (s.timezone) out.scheduleTimezone = s.timezone;
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
    keywords: dedupeKeywords(
      doc.keywords ? String(doc.keywords).split(',').map((s) => s.trim()) : []
    ),
    name: doc.name ?? '',
    description: doc.description ?? '',
    image: doc.image ?? '',
    logo: doc.logo ?? '',
    startDate: doc.startDate ?? '',
    endDate: doc.endDate ?? '',
    typicalAgeRange: doc.typicalAgeRange ?? '',
    eventAttendanceMode: doc.eventAttendanceMode ?? '',
    eventStatus: doc.eventStatus ?? '',
    maximumPhysicalAttendeeCapacity: maxPhysical,
    maximumVirtualAttendeeCapacity: maxVirtual,
    maximumAttendeeCapacity: maxTotal,
    bookedAttendeeCapacity: Math.max(0, maxTotal - remaining),
    remainingAttendeeCapacity: remaining,
    isAccessibleForFree: doc.isAccessibleForFree ?? false,
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
          startDate: s.startDate ?? '',
          endDate: s.endDate ?? '',
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
    isChildrensEvent: !!doc['meetoo:isChildrensEvent'],
    childrenMustBeAccompanied: !!doc['meetoo:childrenMustBeAccompanied'],
    forSeparatedParents: !!doc['meetoo:forSeparatedParents'],
  };
}

/**
 * Ripulisce una lista di keywords: toglie spazi e vuoti, scarta i doppioni
 * (confronto senza maiuscole/minuscole) tenendo la PRIMA grafia incontrata — così
 * l'ordine di chi scrive non cambia — e SPEZZA le voci che contengono una virgola.
 *
 * Lo spezzare non è un vezzo: le keywords si salvano come un'unica stringa separata
 * da virgole, quindi una keyword con la virgola dentro non esiste — al primo
 * salvataggio diventa comunque due voci. È da qui che nascevano i doppioni di
 * località e regione: il luogo della serie si chiama «Lido di Ostia, Roma», il
 * salvataggio lo spezzava in due keywords e il salvataggio dopo ri-aggiungeva il
 * nome intero, che non combaciava con nessuna delle due metà. Ogni giro ne
 * produceva un paio in più.
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
  const base = Array.isArray(d.keywords) ? [...d.keywords] : d.keywords ? [String(d.keywords)] : [];
  const auto = [...(d.organizer ?? []).map((o) => o?.name), d.location?.name];
  return dedupeKeywords([...base, ...auto]).join(', ');
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

  // Nodi figli con i soli campi valorizzati (niente stringhe/valori vuoti).
  const program = (d.subEvent ?? [])
    .filter((s) => s.name || s.description || s.startDate || s.endDate)
    .map((s) => ({
      '@type': 'Event',
      ...(s.name ? { name: s.name } : {}),
      ...(s.description ? { description: s.description } : {}),
      ...(s.startDate ? { startDate: s.startDate } : {}),
      ...(s.endDate ? { endDate: s.endDate } : {}),
    }));
  const occurrences = (d.occurrences ?? [])
    .filter((o) => o.id || o.name)
    .map((o) => ({ ...(o.id ? { '@id': toEventRef(o.id) } : {}), '@type': 'Event', ...(o.name ? { name: o.name } : {}) }));
  const subEvent = isSeries ? occurrences : program;
  const schedule = isSeries ? toSchedule(d.eventSchedule) : undefined;

  // sameAs: solo gli url (schema.org), il "social" del form è d'aiuto UI
  const sameAs = (d.sameAs ?? []).map((s) => (s?.url ?? '').trim()).filter(Boolean);

  const num = (v) => Number(v) || 0;
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
    ...(kw ? { keywords: kw } : {}),
    name: d.name ?? '',
    ...(d.description ? { description: d.description } : {}),
    ...(d.image ? { image: d.image } : {}),
    ...(d.logo ? { logo: d.logo } : {}),
    ...(d.startDate ? { startDate: d.startDate } : {}),
    ...(d.endDate ? { endDate: d.endDate } : {}),
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
    // Flag pubblico: emessi solo se attivi (accompagnati solo se adatto ai bambini)
    ...(d.isChildrensEvent ? { 'meetoo:isChildrensEvent': true } : {}),
    ...(d.isChildrensEvent && d.childrenMustBeAccompanied ? { 'meetoo:childrenMustBeAccompanied': true } : {}),
    ...(d.forSeparatedParents ? { 'meetoo:forSeparatedParents': true } : {}),
  };
}

// Configurazione base di un nuovo evento: solo @context e @type radice (Event).
// Tutti gli altri campi partono dai default di fromJsonLd (form vuoto ma valido nel modello).
export const blankJsonLd = {
  '@context': CONTEXT,
  '@id': '',
  '@type': 'Event',
};
