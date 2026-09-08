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

import { AGE_BANDS } from './schema.js';
import { t } from './i18n.js';

export const ctrl = (scope, extra = {}) => ({ type: 'Control', scope, ...extra });

/* I tipi schema.org che si usano qui. Il PRIMO @type è il discriminante — è
 * quello che decide come il sito disegna la scheda — e gli altri sono qualifiche
 * che si sommano: «LocalBusiness» + «Beach» + «Restaurant» è un chiosco sulla
 * spiaggia, e sono tre parole vere tutte e tre. */
const TIPO_PRIMARIO = [
  { const: 'Place', title: t('Place') },
  { const: 'LocalBusiness', title: t('Business') },
  { const: 'CivicStructure', title: t('Public facility') },
  { const: 'LandmarksOrHistoricalBuildings', title: t('Landmark or historical building') },
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
  { const: 'wheelchairAccessibleEntrance', title: t('Wheelchair accessible entrance') },
  { const: 'wheelchairAccessibleParking', title: t('Accessible parking') },
  { const: 'wheelchairAccessibleRestroom', title: t('Accessible toilets') },
  { const: 'wheelchairAccessibleSeating', title: t('Accessible seating') },
];

const STATO_ATTIVITA = [
  { const: 'OPERATIONAL', title: t('Open') },
  { const: 'CLOSED_TEMPORARILY', title: t('Temporarily closed') },
  { const: 'CLOSED_PERMANENTLY', title: t('Permanently closed') },
];

/* ---------------------------------------------------------------- LUOGO ---- */

export const schemaLuogo = {
  type: 'object',
  properties: {
    id: { type: 'string', title: t('Page address (@id)') },
    primaryType: { type: 'string', title: t('What it is'), default: 'Place', oneOf: TIPO_PRIMARIO },
    subtypes: { type: 'array', title: t('Additional types'), items: { type: 'string' } },
    name: { type: 'string', title: t('Name') },
    additionalType: { type: 'array', title: t('Also known as'), items: { type: 'string' } },
    abstract: { type: 'string', title: t('Summary'), format: 'xhtml' },
    description: { type: 'string', title: t('Search engine description'), format: 'seo' },
    url: { type: 'string', title: t('Website') },
    telephone: { type: 'string', title: t('Phone') },
    sameAs: { type: 'array', title: t('Social and other profiles'), items: { type: 'string' } },
    keywords: { type: 'array', title: t('Keywords'), items: { type: 'string' } },

    streetAddress: { type: 'string', title: t('Street and number') },
    postalCode: { type: 'string', title: t('Postcode') },
    addressLocality: { type: 'string', title: t('Town') },
    addressRegion: { type: 'string', title: t('Region and province') },
    addressCountry: { type: 'string', title: t('Country') },
    latitude: { type: 'string', title: t('Latitude') },
    longitude: { type: 'string', title: t('Longitude') },

    priceRange: { type: 'string', title: t('Price range') },
    legalStatus: { type: 'string', title: t('Legal form') },
    businessStatus: { type: 'string', title: t('Business status'), oneOf: STATO_ATTIVITA },
    accessibility: { type: 'array', title: t('Accessibility'), items: { type: 'string' } },
    amenityFeature: {
      type: 'array',
      title: t('Amenities'),
      items: {
        type: 'object',
        properties: {
          name: { type: 'string', title: t('Amenity') },
          value: { type: 'boolean', title: t('Available') },
        },
      },
    },

    logo: { type: 'string', title: t('Logo'), format: 'image' },
    image: { type: 'string', title: t('Cover image'), format: 'image' },
    iconName: { type: 'string', title: t('Icon') },

    containedInPlace: { type: 'string', title: t('Contained in (@id)') },
    isGroup: { type: 'boolean', title: t('It is a group, not a physical place') },

    /* A chi va bene questo posto. Stesso campo degli eventi — `typicalAgeRange`
     * di schema.org, nella stessa forma testuale — perché a leggerlo è lo stesso
     * motore: le liste di fascia (ws-listrule.php) interrogano questo nome, e un
     * luogo che lo dichiara entra nelle stesse raccolte in cui entrano gli
     * eventi. Un nome diverso qui avrebbe voluto dire un motore diverso. */
    typicalAgeRange: { type: 'string', title: t('This place suits') },
    childrenMustBeAccompanied: { type: 'boolean', title: t('Minors only if accompanied') },
    forSeparatedParents: { type: 'boolean', title: t('Separated parents only') },
    isAccessibleForFree: { type: 'boolean', title: t('Free') },

    /* Chi altro può modificare QUESTA scheda. È un permesso della singola
     * scheda, non della persona: sta qui e non in Gestione utenti perché la
     * domanda «chi può toccare questa cosa» si fa guardando la cosa. */
    contributor: { type: 'array', title: t('Who else can edit it'), items: { type: 'string' } },

    googlePlaceId: { type: 'string', title: t('Google Place ID') },
    ratingValue: { type: 'string', title: t('Rating') },
    /* Due conteggi, due cose. `ratingCount` sono le stelline — è quello che
     * Google manda come `user_ratings_total` — e `reviewCount` le recensioni
     * scritte. Scriverli con lo stesso nome faceva dire alla scheda che 426
     * persone avevano scritto qualcosa, quando avevano solo votato. */
    ratingCount: { type: 'string', title: t('Ratings') },
    reviewCount: { type: 'string', title: t('Written reviews') },
    satelliteView: { type: 'string', title: t('Satellite view') },
    satelliteCredit: { type: 'string', title: t('View credits') },
    mapCount: { type: 'string', title: t('Linked maps') },
  },
};

const gruppoCampi = (label, icon, elements) => ({ type: 'Group', label, options: { icon }, elements });

/* «Minori solo se accompagnati» si chiede solo a chi ha dichiarato una fascia
 * che comprende minorenni. Stessa regola dell'editor eventi, stesso pattern: «la
 * fascia comincia sotto i diciotto» — 0-9 e 10-17 seguiti da trattino o più,
 * oltre a «All Ages» dei file di prima. La valuta il browser, quindi niente
 * `(?i)`, che è sintassi PHP. */
const mostraSeMinori = {
  effect: 'SHOW',
  condition: {
    scope: '#/properties/typicalAgeRange',
    schema: { type: 'string', pattern: '^(?:[Aa]ll\\s*[Aa]ges|(?:1[0-7]|[0-9])\\s*[-+])' },
  },
};

export const uischemaLuogo = {
  type: 'VerticalLayout',
  elements: [
    gruppoCampi(t('What it is'), 'label', [
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

    gruppoCampi(t('The story'), 'article', [
      ctrl('#/properties/abstract'),
      ctrl('#/properties/description'),
      ctrl('#/properties/keywords', { options: { icon: 'tag' } }),
    ]),

    gruppoCampi(t('Where'), 'place', [
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
      ctrl('#/properties/containedInPlace', { options: { icon: 'account_tree', riferimento: true, ambito: 'organizer' } }),
    ]),

    /* La stessa sezione degli eventi, con le stesse fasce e le stesse parole.
     * Un parco «adatto a 0-5» e una festa «adatta a 0-5» devono poter finire
     * nella stessa lista: se il luogo lo dicesse in un modo suo, non ci
     * finirebbe. «Minori solo se accompagnati» compare solo quando la fascia
     * dichiarata comprende minorenni — sotto la fascia, perché è di quella che
     * parla. */
    gruppoCampi(t('Audience'), 'people', [
      ctrl('#/properties/typicalAgeRange', {
        options: { ageRange: true, bands: AGE_BANDS, icon: 'child_care' },
      }),
      ctrl('#/properties/childrenMustBeAccompanied', { rule: mostraSeMinori, options: { inline: true } }),
      ctrl('#/properties/forSeparatedParents', { options: { inline: true } }),
      ctrl('#/properties/isAccessibleForFree', { options: { inline: true } }),
    ]),

    gruppoCampi(t('Contacts'), 'contact_page', [
      {
        type: 'HorizontalLayout',
        elements: [
          ctrl('#/properties/url', { options: { icon: 'link' } }),
          ctrl('#/properties/telephone', { options: { icon: 'call' } }),
        ],
      },
      ctrl('#/properties/sameAs', { options: { icon: 'share' } }),
    ]),

    gruppoCampi(t('Amenities and accessibility'), 'accessible', [
      {
        type: 'HorizontalLayout',
        elements: [
          ctrl('#/properties/businessStatus'),
          ctrl('#/properties/priceRange', { options: { icon: 'euro' } }),
        ],
      },
      ctrl('#/properties/legalStatus', { options: { icon: 'gavel' } }),
      ctrl('#/properties/accessibility', { options: { select: true, suggestions: ACCESSIBILITA, icon: 'accessible' } }),
      ctrl('#/properties/amenityFeature', { label: t('Amenities'), options: { icon: 'check_circle', variant: 'row' } }),
    ]),

    gruppoCampi(t('Images'), 'image', [
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
    gruppoCampi(t('Who works on it'), 'manage_accounts', [
      ctrl('#/properties/contributor', { options: { icon: 'group_add' } }),
    ]),

    gruppoCampi(t('From Google and from ratings'), 'travel_explore', [
      {
        type: 'HorizontalLayout',
        options: { cols: 3 },
        elements: [
          ctrl('#/properties/ratingValue', { options: { computed: true } }),
          ctrl('#/properties/ratingCount', { options: { computed: true } }),
          ctrl('#/properties/reviewCount', { options: { computed: true } }),
        ],
      },
      {
        type: 'HorizontalLayout',
        elements: [
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
    id: { type: 'string', title: t('Page address (@id)') },
    name: { type: 'string', title: t('Group name') },
    legalName: { type: 'string', title: t('Legal name') },
    abstract: { type: 'string', title: t('Summary'), format: 'xhtml' },
    description: { type: 'string', title: t('Search engine description'), format: 'seo' },
    url: { type: 'string', title: t('Website') },
    email: { type: 'string', title: t('Email') },
    telephone: { type: 'string', title: t('Phone') },
    sameAs: { type: 'array', title: t('Social and other profiles'), items: { type: 'string' } },
    keywords: { type: 'array', title: t('Keywords'), items: { type: 'string' } },
    logo: { type: 'string', title: t('Logo'), format: 'image' },
    image: { type: 'string', title: t('Cover image'), format: 'image' },
    iconName: { type: 'string', title: t('Icon') },
    location: { type: 'string', title: t('Where it is (@id)') },
    areaServed: { type: 'string', title: t('Area served') },
    /* Chi lo gestisce: gli utenti che possono pubblicare a suo nome e curarne la
     * scheda. È il campo su cui poggia tutto il resto — chi può creare, chi ha il
     * badge — e finora non aveva nessun posto dove scriverlo se non il file. */
    manager: { type: 'array', title: t('Who runs it'), items: { type: 'string' } },
    contributor: { type: 'array', title: t('Who else can edit its page'), items: { type: 'string' } },
    verified: { type: 'boolean', title: t('Verified group') },
    ratingValue: { type: 'string', title: t('Rating') },
    ratingCount: { type: 'string', title: t('Ratings') },
    reviewCount: { type: 'string', title: t('Written reviews') },
  },
};

export const uischemaGruppo = {
  type: 'VerticalLayout',
  elements: [
    gruppoCampi(t('The group'), 'groups', [
      ctrl('#/properties/name', { options: { icon: 'title' } }),
      ctrl('#/properties/legalName', { options: { icon: 'gavel' } }),
      ctrl('#/properties/id'),
    ]),
    gruppoCampi(t('The story'), 'article', [
      ctrl('#/properties/abstract'),
      ctrl('#/properties/description'),
      ctrl('#/properties/keywords', { options: { icon: 'tag' } }),
    ]),
    gruppoCampi(t('Contacts'), 'contact_page', [
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
    gruppoCampi(t('Where'), 'place', [
      // Stessa ricerca di «Sta dentro»: è la stessa domanda — quale scheda? —
      // e finora si rispondeva battendo un indirizzo a memoria.
      ctrl('#/properties/location', { options: { icon: 'place', riferimento: true, ambito: 'organizer' } }),
    ]),
    gruppoCampi(t('Images'), 'image', [
      {
        type: 'HorizontalLayout',
        elements: [
          ctrl('#/properties/image'),
          ctrl('#/properties/logo'),
        ],
      },
      ctrl('#/properties/iconName', { options: { icon: 'emoji_symbols' } }),
    ]),
    gruppoCampi(t('Who answers for it'), 'verified_user', [
      ctrl('#/properties/manager', { options: { icon: 'manage_accounts' } }),
      ctrl('#/properties/contributor', { options: { icon: 'group_add' } }),
      ctrl('#/properties/verified', { options: { inline: true } }),
      {
        type: 'HorizontalLayout',
        options: { cols: 3 },
        elements: [
          ctrl('#/properties/ratingValue', { options: { computed: true } }),
          ctrl('#/properties/ratingCount', { options: { computed: true } }),
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
