// Adapter tra il modello "piatto" del form e la forma JSON-LD reale (index.json).
// È qui che vivono le chiavi @-prefissate, i @type come array e i namespace:
// il form non le vede, l'adapter le aggiunge/rimuove in modo deterministico.

const CONTEXT = ['https://schema.org', { meetoo: 'https://meetoo.eu#' }];

/** JSON-LD (index.json) -> dati del form. */
export function fromJsonLd(doc) {
  const offers = doc.offers ?? {};
  return {
    name: doc.name ?? '',
    description: doc.description ?? '',
    startDate: doc.startDate ?? '',
    isAccessibleForFree: doc.isAccessibleForFree ?? false,
    eventStatus: doc.eventStatus ?? '',
    offers: {
      availability: offers.availability ?? '',
      price: offers.price ?? 0,
      priceCurrency: offers.priceCurrency ?? 'EUR',
    },
    organizer: (doc.organizer ?? []).map((o) => ({
      id: o['@id'] ?? '',
      name: o.name ?? '',
    })),
  };
}

/** Dati del form -> JSON-LD (index.json), reintroducendo @context/@type/@id. */
export function toJsonLd(data) {
  return {
    '@context': CONTEXT,
    '@type': ['Event', 'LiteraryEvent'],
    '@id': '20260723T1830-IT00122-reading_party',
    name: data.name ?? '',
    description: data.description ?? '',
    startDate: data.startDate ?? '',
    isAccessibleForFree: !!data.isAccessibleForFree,
    eventStatus: data.eventStatus ?? '',
    offers: {
      '@type': 'Offer',
      availability: data.offers?.availability ?? '',
      price: data.offers?.price ?? 0,
      priceCurrency: data.offers?.priceCurrency ?? 'EUR',
    },
    organizer: (data.organizer ?? []).map((o) => ({
      '@id': o.id ?? '',
      '@type': 'Organization',
      name: o.name ?? '',
    })),
  };
}

// Dati iniziali di esempio (dal nostro index.json).
export const sampleJsonLd = {
  '@context': CONTEXT,
  '@type': ['Event', 'LiteraryEvent'],
  '@id': '20260723T1830-IT00122-reading_party',
  name: 'Reading Party',
  description:
    'Portate un libro da <strong>leggere in spiaggia, in silenziosa compagnia</strong>. <br />Poi chiacchiereremo di libri e non solo!',
  startDate: '2026-07-23T18:30:00+02:00',
  isAccessibleForFree: true,
  eventStatus: 'https://schema.org/EventScheduled',
  offers: {
    '@type': 'Offer',
    availability: 'https://schema.org/LimitedAvailability',
    price: 0,
    priceCurrency: 'EUR',
  },
  organizer: [
    { '@id': 'organizations/clubdellibro-ostia', '@type': 'Organization', name: 'Club del libro Ostia' },
    { '@id': 'organizations/urbanbookclub-roma', '@type': 'Organization', name: 'Urbanbookclub' },
    { '@id': 'organizations/feltrinelli-ostia', '@type': 'Organization', name: 'Feltrinelli Librerie di Ostia' },
  ],
};
