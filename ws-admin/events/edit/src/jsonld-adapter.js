// Adapter tra il modello "piatto" del form e la forma JSON-LD reale (index.json).
// Tutte le chiavi @-prefissate, i @type (anche array) e i namespace (meetoo:…)
// vivono QUI: il form non le vede, l'adapter le aggiunge/rimuove deterministicamente.

const CONTEXT = ['https://schema.org', { meetoo: 'https://meetoo.eu#' }];

const asArray = (v) => (Array.isArray(v) ? v : v == null ? [] : [v]);

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
    keywords: doc.keywords
      ? String(doc.keywords).split(',').map((s) => s.trim()).filter(Boolean)
      : [],
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
 * Le keywords includono SEMPRE i `name` di tutti gli organizer e del luogo:
 * quelli mancanti vengono aggiunti in coda (confronto case-insensitive, nessun
 * duplicato). Le keywords scritte a mano restano e mantengono il loro ordine.
 */
function mergeKeywords(d) {
  const base = Array.isArray(d.keywords) ? [...d.keywords] : d.keywords ? [String(d.keywords)] : [];
  const auto = [...(d.organizer ?? []).map((o) => o?.name), d.location?.name];

  auto.forEach((name) => {
    const value = (name ?? '').trim();
    if (!value) return;
    const already = base.some((k) => (k ?? '').trim().toLowerCase() === value.toLowerCase());
    if (!already) base.push(value);
  });

  return base.map((k) => (k ?? '').trim()).filter(Boolean).join(', ');
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
    .map((o) => ({ ...(o.id ? { '@id': o.id } : {}), '@type': 'Event', ...(o.name ? { name: o.name } : {}) }));
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
    ...(d.eventStatus ? { eventStatus: d.eventStatus } : {}),
    ...(aggregateRating ? { aggregateRating } : {}),
    // Flag pubblico: emessi solo se attivi (accompagnati solo se adatto ai bambini)
    ...(d.isChildrensEvent ? { 'meetoo:isChildrensEvent': true } : {}),
    ...(d.isChildrensEvent && d.childrenMustBeAccompanied ? { 'meetoo:childrenMustBeAccompanied': true } : {}),
    ...(d.forSeparatedParents ? { 'meetoo:forSeparatedParents': true } : {}),
  };
}

// Dati iniziali di esempio (il nostro index.json completo).
export const sampleJsonLd = {
  '@context': CONTEXT,
  '@id': '20260723T1830-IT00122-reading_party',
  '@type': ['Event', 'LiteraryEvent'],
  additionalType: 'Leggere insieme',
  keywords:
    "Club del libro Ostia, Urbanbookclub, Feltrinelli Librerie di Ostia, L'Amanusa Beach, reading party, lettura ad alta voce",
  name: 'Reading Party',
  description:
    'Portate un libro da <strong>leggere in spiaggia, in silenziosa compagnia</strong>. <br />Poi chiacchiereremo di libri e non solo!',
  image: 'media-sources/cover.jpg',
  logo: 'media-sources/logo.svg',
  startDate: '2026-07-23T18:30:00+02:00',
  endDate: '2026-07-23T21:00:00+02:00',
  typicalAgeRange: 'All Ages',
  eventAttendanceMode: 'https://schema.org/OfflineEventAttendanceMode',
  maximumPhysicalAttendeeCapacity: 100,
  maximumVirtualAttendeeCapacity: 0,
  maximumAttendeeCapacity: 100,
  remainingAttendeeCapacity: 20,
  isAccessibleForFree: true,
  offers: {
    '@type': 'Offer',
    availability: 'https://schema.org/LimitedAvailability',
    price: 0,
    priceCurrency: 'EUR',
    url: '',
  },
  location: { '@id': 'places/IT00122-spiaggialamanusa', '@type': 'Place', name: "L'Amanusa Beach" },
  organizer: [
    { '@id': 'organizations/clubdellibro-ostia', '@type': 'Organization', name: 'Club del libro Ostia' },
    { '@id': 'organizations/urbanbookclub-roma', '@type': 'Organization', name: 'Urbanbookclub' },
    { '@id': 'organizations/feltrinelli-ostia', '@type': 'Organization', name: 'Feltrinelli Librerie di Ostia' },
  ],
  subEvent: [
    { '@type': 'Event', name: 'Accoglienza', startDate: '2026-07-23T18:30:00+02:00', endDate: '2026-07-23T19:00:00+02:00' },
    {
      '@type': 'Event',
      name: 'Lettura silenziosa',
      description: 'Lettura silenziosa del proprio libro',
      startDate: '2026-07-23T19:00:00+02:00',
      endDate: '2026-07-23T20:00:00+02:00',
    },
    {
      '@type': 'Event',
      name: 'Chiacchierata',
      description: 'Chiacchierata tra i partecipanti su libri e non solo',
      startDate: '2026-07-23T20:00:00+02:00',
      endDate: '2026-07-23T21:00:00+02:00',
    },
  ],
  eventStatus: 'https://schema.org/EventScheduled',
  aggregateRating: { '@type': 'AggregateRating', ratingValue: '5', bestRating: '5' },
};
