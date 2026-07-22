// Adapter tra il modello "piatto" del form e la forma JSON-LD reale (index.json).
// Tutte le chiavi @-prefissate, i @type (anche array) e i namespace (meetoo:…)
// vivono QUI: il form non le vede, l'adapter le aggiunge/rimuove deterministicamente.

const CONTEXT = ['https://schema.org', { meetoo: 'https://meetoo.eu#' }];

const asArray = (v) => (Array.isArray(v) ? v : v == null ? [] : [v]);

/** JSON-LD (index.json) -> dati del form. */
export function fromJsonLd(doc) {
  const o = doc.offers ?? {};
  const loc = doc.location ?? {};
  const rating = doc.aggregateRating ?? {};
  const meetoo = doc.meetoo ?? {};
  return {
    id: doc['@id'] ?? '',
    types: asArray(doc['@type']),
    additionalType: doc.additionalType ?? '',
    keywords: doc.keywords ?? '',
    name: doc.name ?? '',
    description: doc.description ?? '',
    image: doc.image ?? '',
    logo: doc.logo ?? '',
    startDate: doc.startDate ?? '',
    endDate: doc.endDate ?? '',
    typicalAgeRange: doc.typicalAgeRange ?? '',
    eventAttendanceMode: doc.eventAttendanceMode ?? '',
    eventStatus: doc.eventStatus ?? '',
    maximumPhysicalAttendeeCapacity: doc.maximumPhysicalAttendeeCapacity ?? 0,
    maximumVirtualAttendeeCapacity: doc.maximumVirtualAttendeeCapacity ?? 0,
    maximumAttendeeCapacity: doc.maximumAttendeeCapacity ?? 0,
    remainingAttendeeCapacity: doc.remainingAttendeeCapacity ?? 0,
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
    },
    organizer: (doc.organizer ?? []).map((x) => ({ id: x['@id'] ?? '', name: x.name ?? '' })),
    subEvent: (doc.subEvent ?? []).map((s) => ({
      name: s.name ?? '',
      description: s.description ?? '',
      startDate: s.startDate ?? '',
      endDate: s.endDate ?? '',
    })),
    aggregateRating: {
      ratingValue: rating.ratingValue ?? '',
      bestRating: rating.bestRating ?? '',
    },
    meetoo: {
      type: meetoo['@type'] ?? 'meetoo:EventSingle',
      macrocategory: meetoo['meetoo:macrocategory'] ?? '',
    },
  };
}

/** Dati del form -> JSON-LD (index.json), reintroducendo @context/@type/@id/namespace.
 *  L'ordine delle chiavi segue index.json. */
export function toJsonLd(d) {
  const types = d.types?.length ? (d.types.length === 1 ? d.types[0] : d.types) : 'Event';

  const subEvent = (d.subEvent ?? []).map((s) => {
    const node = { '@type': 'Event', name: s.name ?? '' };
    if (s.description) node.description = s.description; // omessa se vuota (come in index.json)
    node.startDate = s.startDate ?? '';
    node.endDate = s.endDate ?? '';
    return node;
  });

  return {
    '@context': CONTEXT,
    '@id': d.id ?? '',
    '@type': types,
    additionalType: d.additionalType ?? '',
    keywords: d.keywords ?? '',
    name: d.name ?? '',
    description: d.description ?? '',
    image: d.image ?? '',
    logo: d.logo ?? '',
    startDate: d.startDate ?? '',
    endDate: d.endDate ?? '',
    typicalAgeRange: d.typicalAgeRange ?? '',
    eventAttendanceMode: d.eventAttendanceMode ?? '',
    maximumPhysicalAttendeeCapacity: d.maximumPhysicalAttendeeCapacity ?? 0,
    maximumVirtualAttendeeCapacity: d.maximumVirtualAttendeeCapacity ?? 0,
    maximumAttendeeCapacity: d.maximumAttendeeCapacity ?? 0,
    remainingAttendeeCapacity: d.remainingAttendeeCapacity ?? 0,
    isAccessibleForFree: !!d.isAccessibleForFree,
    offers: {
      '@type': 'Offer',
      availability: d.offers?.availability ?? '',
      price: d.offers?.price ?? 0,
      priceCurrency: d.offers?.priceCurrency ?? 'EUR',
      url: d.offers?.url ?? '',
    },
    location: {
      '@id': d.location?.id ?? '',
      '@type': d.location?.type ?? 'Place',
      name: d.location?.name ?? '',
    },
    organizer: (d.organizer ?? []).map((x) => ({
      '@id': x.id ?? '',
      '@type': 'Organization',
      name: x.name ?? '',
    })),
    subEvent,
    eventStatus: d.eventStatus ?? '',
    aggregateRating: {
      '@type': 'AggregateRating',
      ratingValue: d.aggregateRating?.ratingValue ?? '',
      bestRating: d.aggregateRating?.bestRating ?? '',
    },
    meetoo: {
      '@type': d.meetoo?.type ?? 'meetoo:EventSingle',
      'meetoo:macrocategory': d.meetoo?.macrocategory ?? '',
    },
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
  meetoo: { '@type': 'meetoo:EventSingle', 'meetoo:macrocategory': 'culture' },
};
