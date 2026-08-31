/*
 * Lo schema delle SCHEDE: un luogo (o un'attività) e un gruppo.
 *
 * Il vocabolario non è inventato: è quello che i 62 luoghi e i 6 gruppi già
 * scritti usano davvero. Dove un campo compare in cinquanta file su sessanta è un
 * campo del modulo; dove compare in tre è un campo del modulo lo stesso, ma in
 * fondo. Inventarne di nuovi qui vorrebbe dire che il modulo e i contenuti
 * parlano due lingue diverse.
 *
 * QUELLO CHE NON SI SCRIVE A MANO. `geo`, `hasMap`, `aggregateRating`,
 * `meetoo:satelliteView` e il `google_place_id` arrivano da Google o dai voti:
 * nel modulo si vedono ma non si toccano. Un campo che si può battere a mano e
 * che qualcun altro riscrive è un campo che mente due volte — la prima quando lo
 * compili, la seconda quando torni e non c'è più quello che avevi scritto.
 */

export const ctrl = (scope, extra = {}) => ({ type: 'Control', scope, ...extra });

/* I tipi schema.org che si usano qui. Il PRIMO @type è il discriminante — è
 * quello che decide come il sito disegna la scheda — e gli altri sono qualifiche
 * che si sommano: «LocalBusiness» + «Beach» + «Restaurant» è un chiosco sulla
 * spiaggia, e sono tre parole vere tutte e tre. */
const TIPO_PRIMARIO = [
  { const: 'Place', title: 'Luogo' },
  { const: 'LocalBusiness', title: 'Attività commerciale' },
  { const: 'CivicStructure', title: 'Struttura pubblica' },
  { const: 'LandmarksOrHistoricalBuildings', title: 'Monumento o edificio storico' },
];

const TIPI_SECONDARI = [
  'Beach', 'Park', 'Restaurant', 'BarOrPub', 'CafeOrCoffeeShop', 'Library',
  'BookStore', 'Store', 'Museum', 'MovieTheater', 'PerformingArtsTheater',
  'SportsActivityLocation', 'Campground', 'Hotel', 'TouristAttraction',
  'PlaceOfWorship', 'Playground', 'CommunityCenter', 'EventVenue',
];

/* Le funzionalità di accessibilità sono i nomi che usa Google: si scrivono così
 * perché è così che arrivano, e tradurli qui vorrebbe dire non riconoscerli più
 * quando il luogo si aggiorna. La traduzione la fa il sito, quando li mostra. */
const ACCESSIBILITA = [
  { const: 'wheelchairAccessibleEntrance', title: 'Ingresso accessibile in sedia a rotelle' },
  { const: 'wheelchairAccessibleParking', title: 'Parcheggio accessibile' },
  { const: 'wheelchairAccessibleRestroom', title: 'Servizi igienici accessibili' },
  { const: 'wheelchairAccessibleSeating', title: 'Posti a sedere accessibili' },
];

const STATO_ATTIVITA = [
  { const: 'OPERATIONAL', title: 'Aperta' },
  { const: 'CLOSED_TEMPORARILY', title: 'Chiusa temporaneamente' },
  { const: 'CLOSED_PERMANENTLY', title: 'Chiusa definitivamente' },
];

/* ---------------------------------------------------------------- LUOGO ---- */

export const schemaLuogo = {
  type: 'object',
  properties: {
    id: { type: 'string', title: 'Indirizzo della scheda (@id)' },
    primaryType: { type: 'string', title: 'Che cos’è', default: 'Place', oneOf: TIPO_PRIMARIO },
    subtypes: { type: 'array', title: 'Qualifiche', items: { type: 'string' } },
    name: { type: 'string', title: 'Nome' },
    additionalType: { type: 'array', title: 'Come lo chiamano', items: { type: 'string' } },
    abstract: { type: 'string', title: 'Sommario', format: 'xhtml' },
    description: { type: 'string', title: 'Descrizione per i motori di ricerca', format: 'seo' },
    url: { type: 'string', title: 'Sito web' },
    telephone: { type: 'string', title: 'Telefono' },
    sameAs: { type: 'array', title: 'Social e altri profili', items: { type: 'string' } },
    keywords: { type: 'array', title: 'Parole chiave', items: { type: 'string' } },

    streetAddress: { type: 'string', title: 'Via e numero' },
    postalCode: { type: 'string', title: 'CAP' },
    addressLocality: { type: 'string', title: 'Località' },
    addressRegion: { type: 'string', title: 'Regione e provincia' },
    addressCountry: { type: 'string', title: 'Paese' },
    latitude: { type: 'string', title: 'Latitudine' },
    longitude: { type: 'string', title: 'Longitudine' },

    priceRange: { type: 'string', title: 'Fascia di prezzo' },
    legalStatus: { type: 'string', title: 'Forma giuridica' },
    businessStatus: { type: 'string', title: 'Stato dell’attività', oneOf: STATO_ATTIVITA },
    accessibility: { type: 'array', title: 'Accessibilità', items: { type: 'string' } },
    amenityFeature: {
      type: 'array',
      title: 'Servizi',
      items: {
        type: 'object',
        properties: {
          name: { type: 'string', title: 'Servizio' },
          value: { type: 'boolean', title: 'C’è' },
        },
      },
    },

    logo: { type: 'string', title: 'Logo', format: 'image' },
    image: { type: 'string', title: 'Copertina', format: 'image' },
    iconName: { type: 'string', title: 'Icona' },

    containedInPlace: { type: 'string', title: 'Sta dentro (@id)' },
    isGroup: { type: 'boolean', title: 'È un gruppo, non un luogo fisico' },

    /* Chi altro può modificare QUESTA scheda. È un permesso della singola
     * scheda, non della persona: sta qui e non in Gestione utenti perché la
     * domanda «chi può toccare questa cosa» si fa guardando la cosa. */
    contributor: { type: 'array', title: 'Chi altro può modificarla', items: { type: 'string' } },

    googlePlaceId: { type: 'string', title: 'Google Place ID' },
    ratingValue: { type: 'string', title: 'Voto' },
    reviewCount: { type: 'string', title: 'Recensioni' },
    satelliteView: { type: 'string', title: 'Vista dal satellite' },
    satelliteCredit: { type: 'string', title: 'Crediti della vista' },
    mapCount: { type: 'string', title: 'Mappe collegate' },
  },
};

const gruppoCampi = (label, icon, elements) => ({ type: 'Group', label, options: { icon }, elements });

export const uischemaLuogo = {
  type: 'VerticalLayout',
  elements: [
    gruppoCampi('Che cos’è', 'label', [
      {
        type: 'HorizontalLayout',
        elements: [
          ctrl('#/properties/primaryType'),
          ctrl('#/properties/subtypes', { options: { select: true, suggestions: TIPI_SECONDARI, icon: 'sell' } }),
        ],
      },
      ctrl('#/properties/name', { options: { icon: 'title' } }),
      // «Come lo chiamano» non è un tipo schema.org: è la parola che usa il
      // quartiere — «chiosco sulla spiaggia» — e serve a farsi trovare.
      ctrl('#/properties/additionalType', { options: { icon: 'label' } }),
      ctrl('#/properties/id'),
      ctrl('#/properties/isGroup', { options: { inline: true } }),
    ]),

    gruppoCampi('Racconto', 'article', [
      ctrl('#/properties/abstract'),
      ctrl('#/properties/description'),
      ctrl('#/properties/keywords', { options: { icon: 'tag' } }),
    ]),

    gruppoCampi('Dove', 'place', [
      ctrl('#/properties/streetAddress', { options: { icon: 'signpost' } }),
      {
        type: 'HorizontalLayout',
        options: { cols: 3 },
        elements: [
          ctrl('#/properties/postalCode'),
          ctrl('#/properties/addressLocality'),
          ctrl('#/properties/addressRegion'),
        ],
      },
      {
        type: 'HorizontalLayout',
        options: { cols: 3 },
        elements: [
          ctrl('#/properties/addressCountry'),
          ctrl('#/properties/latitude', { options: { computed: true } }),
          ctrl('#/properties/longitude', { options: { computed: true } }),
        ],
      },
      ctrl('#/properties/containedInPlace', { options: { icon: 'account_tree' } }),
    ]),

    gruppoCampi('Contatti', 'contact_page', [
      {
        type: 'HorizontalLayout',
        elements: [
          ctrl('#/properties/url', { options: { icon: 'link' } }),
          ctrl('#/properties/telephone', { options: { icon: 'call' } }),
        ],
      },
      ctrl('#/properties/sameAs', { options: { icon: 'share' } }),
    ]),

    gruppoCampi('Servizi e accessibilità', 'accessible', [
      {
        type: 'HorizontalLayout',
        elements: [
          ctrl('#/properties/businessStatus'),
          ctrl('#/properties/priceRange', { options: { icon: 'euro' } }),
        ],
      },
      ctrl('#/properties/legalStatus', { options: { icon: 'gavel' } }),
      ctrl('#/properties/accessibility', { options: { select: true, suggestions: ACCESSIBILITA, icon: 'accessible' } }),
      ctrl('#/properties/amenityFeature', { label: 'Servizi', options: { icon: 'check_circle', variant: 'row' } }),
    ]),

    gruppoCampi('Immagini', 'image', [
      {
        type: 'HorizontalLayout',
        elements: [
          ctrl('#/properties/image'),
          ctrl('#/properties/logo'),
        ],
      },
      ctrl('#/properties/iconName', { options: { icon: 'emoji_symbols' } }),
    ]),

    /* Quello che scrive Google, e i voti. Si vedono perché sapere che ci sono fa
     * parte del capire la scheda; non si toccano perché al prossimo aggiornamento
     * quello che avessi scritto sparirebbe senza dire niente. */
    gruppoCampi('Chi ci mette le mani', 'manage_accounts', [
      ctrl('#/properties/contributor', { options: { icon: 'group_add' } }),
    ]),

    gruppoCampi('Da Google e dai voti', 'travel_explore', [
      {
        type: 'HorizontalLayout',
        options: { cols: 3 },
        elements: [
          ctrl('#/properties/ratingValue', { options: { computed: true } }),
          ctrl('#/properties/reviewCount', { options: { computed: true } }),
          ctrl('#/properties/mapCount', { options: { computed: true } }),
        ],
      },
      ctrl('#/properties/googlePlaceId', { options: { computed: true } }),
      {
        type: 'HorizontalLayout',
        elements: [
          ctrl('#/properties/satelliteView', { options: { computed: true } }),
          ctrl('#/properties/satelliteCredit', { options: { computed: true } }),
        ],
      },
    ]),
  ],
};

/* --------------------------------------------------------------- GRUPPO ---- */

export const schemaGruppo = {
  type: 'object',
  properties: {
    id: { type: 'string', title: 'Indirizzo della scheda (@id)' },
    name: { type: 'string', title: 'Nome del gruppo' },
    legalName: { type: 'string', title: 'Denominazione ufficiale' },
    abstract: { type: 'string', title: 'Sommario', format: 'xhtml' },
    description: { type: 'string', title: 'Descrizione per i motori di ricerca', format: 'seo' },
    url: { type: 'string', title: 'Sito web' },
    email: { type: 'string', title: 'Email' },
    telephone: { type: 'string', title: 'Telefono' },
    sameAs: { type: 'array', title: 'Social e altri profili', items: { type: 'string' } },
    keywords: { type: 'array', title: 'Parole chiave', items: { type: 'string' } },
    logo: { type: 'string', title: 'Logo', format: 'image' },
    image: { type: 'string', title: 'Copertina', format: 'image' },
    iconName: { type: 'string', title: 'Icona' },
    location: { type: 'string', title: 'Dove si trova (@id)' },
    areaServed: { type: 'string', title: 'Zona in cui opera' },
    /* Chi lo gestisce: gli utenti che possono pubblicare a suo nome e curarne la
     * scheda. È il campo su cui poggia tutto il resto — chi può creare, chi ha il
     * badge — e finora non aveva nessun posto dove scriverlo se non il file. */
    manager: { type: 'array', title: 'Chi lo gestisce', items: { type: 'string' } },
    contributor: { type: 'array', title: 'Chi altro può modificarne la scheda', items: { type: 'string' } },
    verified: { type: 'boolean', title: 'Gruppo verificato' },
    ratingValue: { type: 'string', title: 'Voto' },
    reviewCount: { type: 'string', title: 'Recensioni' },
  },
};

export const uischemaGruppo = {
  type: 'VerticalLayout',
  elements: [
    gruppoCampi('Il gruppo', 'groups', [
      ctrl('#/properties/name', { options: { icon: 'title' } }),
      ctrl('#/properties/legalName', { options: { icon: 'gavel' } }),
      ctrl('#/properties/id'),
    ]),
    gruppoCampi('Racconto', 'article', [
      ctrl('#/properties/abstract'),
      ctrl('#/properties/description'),
      ctrl('#/properties/keywords', { options: { icon: 'tag' } }),
    ]),
    gruppoCampi('Contatti', 'contact_page', [
      {
        type: 'HorizontalLayout',
        elements: [
          ctrl('#/properties/url', { options: { icon: 'link' } }),
          ctrl('#/properties/email', { options: { icon: 'mail' } }),
        ],
      },
      {
        type: 'HorizontalLayout',
        elements: [
          ctrl('#/properties/telephone', { options: { icon: 'call' } }),
          ctrl('#/properties/areaServed', { options: { icon: 'map' } }),
        ],
      },
      ctrl('#/properties/sameAs', { options: { icon: 'share' } }),
    ]),
    gruppoCampi('Dove', 'place', [
      ctrl('#/properties/location', { options: { icon: 'place' } }),
    ]),
    gruppoCampi('Immagini', 'image', [
      {
        type: 'HorizontalLayout',
        elements: [
          ctrl('#/properties/image'),
          ctrl('#/properties/logo'),
        ],
      },
      ctrl('#/properties/iconName', { options: { icon: 'emoji_symbols' } }),
    ]),
    gruppoCampi('Chi risponde', 'verified_user', [
      ctrl('#/properties/manager', { options: { icon: 'manage_accounts' } }),
      ctrl('#/properties/contributor', { options: { icon: 'group_add' } }),
      ctrl('#/properties/verified', { options: { inline: true } }),
      {
        type: 'HorizontalLayout',
        elements: [
          ctrl('#/properties/ratingValue', { options: { computed: true } }),
          ctrl('#/properties/reviewCount', { options: { computed: true } }),
        ],
      },
    ]),
  ],
};

/** Quale coppia schema/uischema serve a questo @id. */
export function schemaPer(id) {
  return String(id || '').startsWith('organizations/')
    ? { schema: schemaGruppo, uischema: uischemaGruppo, tipo: 'gruppo' }
    : { schema: schemaLuogo, uischema: uischemaLuogo, tipo: 'luogo' };
}
