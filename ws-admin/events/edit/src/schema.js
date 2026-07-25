// JSON Schema (dati) — copertura COMPLETA di index.json.
// I campi rich-text usano "format": "xhtml" (renderer custom).
// Tendine con etichetta (title) distinta dal valore salvato (const): JSON Forms
// mostra il title e memorizza il const. Modifica pure le etichette qui sotto.
const ATTENDANCE_MODE = [
  { const: 'https://schema.org/OfflineEventAttendanceMode', title: 'In presenza' },
  { const: 'https://schema.org/OnlineEventAttendanceMode', title: 'Online' },
  { const: 'https://schema.org/MixedEventAttendanceMode', title: 'Ibrido (presenza + online)' },
];
const EVENT_STATUS = [
  { const: 'https://schema.org/EventScheduled', title: 'In programma' },
  { const: 'https://schema.org/EventRescheduled', title: 'Riprogrammato' },
  { const: 'https://schema.org/EventPostponed', title: 'Rimandato' },
  { const: 'https://schema.org/EventMovedOnline', title: 'Spostato online' },
  { const: 'https://schema.org/EventCancelled', title: 'Annullato' },
];
const AVAILABILITY = [
  { const: 'https://schema.org/InStock', title: 'Disponibile' },
  { const: 'https://schema.org/LimitedAvailability', title: 'Disponibilità limitata' },
  { const: 'https://schema.org/SoldOut', title: 'Esaurito' },
  { const: 'https://schema.org/PreOrder', title: 'Preordine' },
  { const: 'https://schema.org/OutOfStock', title: 'Non disponibile' },
];
const MACROCATEGORY = [
  { const: 'Event', title: 'Evento' },
  { const: 'VisualArtsEvent', title: 'Arti visive' },
  { const: 'meetoo:DesignEvent', title: 'Design' },
  { const: 'meetoo:ArchitectureEvent', title: 'Architettura' },
  { const: 'TheaterEvent', title: 'Teatro' },
  { const: 'meetoo:PsycologyEvent', title: 'Psicologia' }, 
  { const: 'ComedyEvent', title: 'Commedia' },
  { const: 'Hackathon', title: 'Hackathon' },
  { const: 'DanceEvent', title: 'Danza' },
  { const: 'FoodEvent', title: 'Cibo' },
  { const: 'LiteraryEvent', title: 'Letteratura' },
  { const: 'MusicEvent', title: 'Musica' },
  { const: 'ScreeningEvent', title: 'Cinema' },
  { const: 'SportsEvent', title: 'Sport' },
  { const: 'GamingEvent', title: 'Giochi' },
  { const: 'BusinessEvent', title: 'Lavoro' },
  { const: 'meetoo:WellbeingEvent', title: 'Benessere' },
  { const: 'meetoo:NatureEvent', title: 'Natura' },
  { const: 'meetoo:AnimalsEvent', title: 'Animali domestici' },
  { const: 'meetoo:FriendshipEvent', title: 'Amicizia' },
  { const: 'meetoo:LoveEvent', title: 'Relazioni' },
  { const: 'meetoo:LoveEvent', title: 'Amore' },
];
const MEETOO_GENERIC_CATEGORY = [
  'ChildrensEvent', 
  'DeliveryEvent', 
  'EducationEvent', 
  'ExhibitionEvent', 
  'Festival', 
  'PublicationEvent', 
  'SaleEvent', 
  'SocialEvent',
  { const: 'PartyEvent', title: 'Party' },
  'CourseInstance', 
]
const MEETOO_CATEGORY = [
  { const: 'ReadingPartyEvent', title: 'Reading Party' },
  { const: 'DatingEvent', title: 'Dating' },
  { const: 'BusinessDatingEvent', title: 'Business Dating' },
  { const: 'FriendshipDatingEvent', title: 'Friendship Dating' },
  { const: 'LoveDatingEvent', title: 'Love Dating' },
];
// Tipo primario schema.org: unico discriminante dei conditional. È il primo @type,
// è required e NON compare nel campo Tipi.
const PRIMARY_TYPE = [
  { const: 'Event', title: 'Evento singolo' },
  { const: 'EventSeries', title: 'Collezione di eventi' },
];
// Suggerimenti per il campo "Social" (accetta anche valori personalizzati).
const SOCIAL_SUGGEST = [
  'Facebook', 'Instagram', 'LinkedIn', 'TikTok', 'YouTube', 'X',
  'Telegram', 'WhatsApp', 'Threads', 'Pinterest', 'Blog', 'Sito web',
];
// Fasce d'età suggerite (il campo accetta anche valori personalizzati).
const AGE_RANGES = ['All Ages', '0-3', '3-6', '6-12', '12-18', '18+', '18-30', '30-60', '60+'];

export const schema = {
  type: 'object',
  properties: {
    id: { type: 'string', title: '@id' },
    url: { type: 'string', title: 'Sito web' },
    // Altri profili/siti (schema.org sameAs): url + tipo di social (select-search
    // con valori anche personalizzati). Il "social" è un aiuto UI: in JSON-LD
    // esce solo l'array di url (il tipo si ricava dall'host al ricaricamento).
    sameAs: {
      type: 'array',
      title: 'Social e altri siti',
      items: {
        type: 'object',
        properties: {
          social: { type: 'string', title: 'Social', format: 'suggest', examples: SOCIAL_SUGGEST },
          url: { type: 'string', title: 'URL' },
        },
      },
    },
    primaryType: { type: 'string', title: 'Tipo di evento', default: 'Event', oneOf: PRIMARY_TYPE },
    types: { type: 'array', title: 'Macrocategorie', items: { type: 'string' } },
    additionalType: { type: 'array', title: 'additionalType', format: 'tags', items: { type: 'string' } },
    keywords: { type: 'array', title: 'Keywords', format: 'tags', items: { type: 'string' } },
    name: { type: 'string', title: 'Nome evento' },
    description: { type: 'string', title: 'Descrizione', format: 'xhtml' },
    image: { type: 'string', title: 'Immagine', format: 'image' },
    logo: { type: 'string', title: 'Logo', format: 'image' },
    startDate: { type: 'string', format: 'date-time', title: 'Dal' },
    endDate: { type: 'string', format: 'date-time', title: 'al' },
    typicalAgeRange: { type: 'string', title: "Fascia d'età" },
    eventStatus: { type: 'string', title: 'Stato evento', oneOf: EVENT_STATUS },
    eventAttendanceMode: { type: 'string', title: 'Modalità', oneOf: ATTENDANCE_MODE },
    maximumPhysicalAttendeeCapacity: { type: 'integer', title: 'Posti in presenza' },
    maximumVirtualAttendeeCapacity: { type: 'integer', title: 'Posti da remoto' },
    maximumAttendeeCapacity: { type: 'integer', title: 'Posti totali' },
    bookedAttendeeCapacity: { type: 'integer', title: 'Posti prenotati' },
    remainingAttendeeCapacity: { type: 'integer', title: 'Posti rimasti' },
    isChildrensEvent: { type: 'boolean', title: 'Adatto ai bambini' },
    childrenMustBeAccompanied: { type: 'boolean', title: 'Bambini accompagnati dai genitori' },
    forSeparatedParents: { type: 'boolean', title: 'Solo genitori separati' },
    isAccessibleForFree: { type: 'boolean', title: 'Gratuito' },
    offers: {
      type: 'object',
      title: 'Offerta',
      properties: {
        availability: { type: 'string', title: 'Disponibilità', oneOf: AVAILABILITY },
        price: { type: 'number', title: 'Prezzo' },
        priceCurrency: { type: 'string', title: 'Valuta', default: 'EUR' },
        url: { type: 'string', title: 'URL' },
      },
    },
    location: {
      type: 'object',
      title: 'Luogo',
      properties: {
        id: { type: 'string', title: '@id' },
        type: { type: 'string', title: '@type', default: 'Place' },
        name: { type: 'string', title: 'Nome' },
      },
    },
    organizer: {
      type: 'array',
      title: 'Organizzatori',
      items: {
        type: 'object',
        properties: {
          name: { type: 'string', title: 'Nome' },
          id: { type: 'string', title: '@id' },
        },
      },
    },
    subEvent: {
      type: 'array',
      title: 'Sotto-eventi',
      items: {
        type: 'object',
        properties: {
          name: { type: 'string', title: 'Nome' },
          description: { type: 'string', title: 'Descrizione', format: 'xhtml' },
          startDate: { type: 'string', format: 'date-time', title: 'Dal' },
          endDate: { type: 'string', format: 'date-time', title: 'al' },
        },
      },
    },
    // Occorrenze di una serie: riferimenti @id (+ nome) agli eventi figli.
    occurrences: {
      type: 'array',
      title: 'Occorrenze',
      items: {
        type: 'object',
        properties: {
          name: { type: 'string', title: 'Nome' },
          id: { type: 'string', title: '@id' },
        },
      },
    },
    // Ricorrenza (schema.org Schedule) — renderer custom stile Google Calendar.
    eventSchedule: {
      type: 'object',
      title: 'Ricorrenza',
      format: 'recurrence',
      properties: {
        frequency: { type: 'string' },
        interval: { type: 'integer' },
        byDay: { type: 'array', items: { type: 'string' } },
        endMode: { type: 'string' },
        until: { type: 'string' },
        count: { type: 'integer' },
        timezone: { type: 'string' },
      },
    },
    aggregateRating: {
      type: 'object',
      title: 'Valutazione',
      properties: {
        ratingValue: { type: 'string', title: 'Voto' },
        bestRating: { type: 'string', title: 'Voto max' },
      },
    },
  },
  required: ['name', 'primaryType'],
};

// Sottotipi di Event di schema.org (suggerimenti ricercabili; @type resta creatable).
const EVENT_TYPES = [
  'Event', 
  'BusinessEvent', 
  'ChildrensEvent', 
  'ComedyEvent', 
  'CourseInstance', 
  'DanceEvent',
  'DeliveryEvent', 
  'EducationEvent', 
  'ExhibitionEvent', 
  'Festival', 
  'FoodEvent', 
  'Hackathon',
  'LiteraryEvent', 
  'MusicEvent', 
  'PublicationEvent', 
  'SaleEvent', 
  'ScreeningEvent', 
  'SocialEvent',
  'SportsEvent', 
  'TheaterEvent', 
  'VisualArtsEvent',
];

// Tipi meetoo: valori predefiniti ricercabili (il campo accetta anche testo libero,
// così puoi correggere/estendere il vocabolario quando sarà definitivo).
const MEETOO_TYPES = [
  'meetoo:EventSingle',
  'meetoo:EventSeries',
  'meetoo:EventRecurring',
  'meetoo:EventArchived',
];

const ctrl = (scope, extra = {}) => ({ type: 'Control', scope, ...extra });

// Regole condizionali guidate dal tipo primario schema.org (Event/EventSeries).
const PRIMARY_SCOPE = '#/properties/primaryType';
const showIfSeries = { effect: 'SHOW', condition: { scope: PRIMARY_SCOPE, schema: { const: 'EventSeries' } } };
const showIfNotSeries = { effect: 'SHOW', condition: { scope: PRIMARY_SCOPE, schema: { not: { const: 'EventSeries' } } } };
const showIfChildren = { effect: 'SHOW', condition: { scope: '#/properties/isChildrensEvent', schema: { const: true } } };

export const uischema = {
  type: 'VerticalLayout',
  elements: [
    {
      type: 'Group',
      label: 'Identità',
      options: { icon: 'badge' },
      elements: [
        {
          type: 'HorizontalLayout',
          elements: [
            ctrl('#/properties/primaryType', { options: { icon: 'event' } }),
            ctrl('#/properties/id')
          ],
        },
        ctrl('#/properties/url', { options: { icon: 'link' } }),
        ctrl('#/properties/sameAs', { options: { icon: 'link_2', variant: 'row' } }),
      ]
    },
    {
      type: 'Group',
      label: 'Contenuto',
      options: { icon: 'article' },
      elements: [
        ctrl('#/properties/name'),
        ctrl('#/properties/description', { options: { icon: 'description' } }),
        {
          type: 'HorizontalLayout',
          elements: [
            ctrl('#/properties/logo', { options: { icon: 'branding_watermark' } }),
            ctrl('#/properties/image', { options: { icon: 'image' } }),
          ],
        },
      ],
    },
    {
      type: 'Group',
      label: 'Classificazione',
      options: { icon: 'category' },
      elements: [
        // Tipi = altri @type, scelti dalle opzioni MACROCATEGORY, senza valori custom
        ctrl('#/properties/types', { options: { icon: 'category', select: true, suggestions: MACROCATEGORY } }),
        ctrl('#/properties/additionalType', { options: { icon: 'label' } }),
        ctrl('#/properties/keywords', { options: { icon: 'sell' } }),
      ],
    },
    {
      type: 'Group',
      label: 'Dove',
      options: { icon: 'place' },
      elements: [
        {
          type: 'HorizontalLayout',
          elements: [
            ctrl('#/properties/location/properties/name'),
            ctrl('#/properties/location/properties/type'),
            ctrl('#/properties/location/properties/id'),
          ],
        },
      ],
    },
    {
      type: 'Group',
      label: 'Quando',
      options: { icon: 'schedule' },
      elements: [
        {
          type: 'HorizontalLayout',
          options: { separator: '–' },
          elements: [ctrl('#/properties/startDate'), ctrl('#/properties/endDate')],
        },
        ctrl('#/properties/eventStatus'),
        // Ricorrenza: solo per le serie
        ctrl('#/properties/eventSchedule', { rule: showIfSeries }),
      ],
    },
    {
      type: 'Group',
      label: 'Pubblico',
      options: { icon: 'people' },
      elements: [
        {
          type: 'HorizontalLayout',
          elements: [
            ctrl('#/properties/eventAttendanceMode'),
            ctrl('#/properties/typicalAgeRange', {
              options: { searchable: true, suggestions: AGE_RANGES, icon: 'child_care' },
            }),
          ],
        },
        // Capienze in griglia 3 colonne. I due calcolati (totale, rimasti) stanno
        // nella stessa colonna (3ª); uno spaziatore tiene libera la 2ª nella riga 2:
        //   presenza | remoto  | totale(calcolato)
        //   prenotati| ­        | rimasti(calcolato)
        {
          type: 'HorizontalLayout',
          options: { cols: 3 },
          elements: [
            ctrl('#/properties/maximumPhysicalAttendeeCapacity'),
            ctrl('#/properties/maximumVirtualAttendeeCapacity'),
            ctrl('#/properties/maximumAttendeeCapacity', { options: { computed: true } }),
            ctrl('#/properties/bookedAttendeeCapacity'),
            { type: 'Label', options: { spacer: true } },
            ctrl('#/properties/remainingAttendeeCapacity', { options: { computed: true } }),
          ],
        },
        // I tre flag "bambini/genitori" su una riga, Gratuito sulla successiva
        {
          type: 'HorizontalLayout',
          options: { inline: true },
          elements: [
            ctrl('#/properties/isChildrensEvent'),
            ctrl('#/properties/childrenMustBeAccompanied', { rule: showIfChildren }),
            ctrl('#/properties/forSeparatedParents'),
          ],
        },
        ctrl('#/properties/isAccessibleForFree'),
      ],
    },
    {
      type: 'Group',
      label: 'Offerta',
      options: { icon: 'payments' },
      // CONDIZIONE: mostra la sezione solo se l'evento NON è gratuito.
      rule: {
        effect: 'SHOW',
        condition: {
          scope: '#/properties/isAccessibleForFree',
          schema: { const: false },
        },
      },
      elements: [
        {
          type: 'HorizontalLayout',
          elements: [
            ctrl('#/properties/offers/properties/availability'),
            ctrl('#/properties/offers/properties/price'),
            ctrl('#/properties/offers/properties/priceCurrency'),
            ctrl('#/properties/offers/properties/url'),
          ],
        },
      ],
    },
    ctrl('#/properties/organizer', { label: 'Organizzatori', options: { icon: 'groups', variant: 'row' } }),
    // Single → programma interno; Series → occorrenze (link @id)
    ctrl('#/properties/subEvent', {
      label: 'Programma dell’evento',
      options: { icon: 'event', variant: 'stack' },
      rule: showIfNotSeries,
    }),
    ctrl('#/properties/occurrences', {
      label: 'Occorrenze',
      options: { icon: 'event_repeat', variant: 'row' },
      rule: showIfSeries,
    }),
    {
      type: 'Group',
      label: 'Valutazione media degli utenti',
      options: { icon: 'star' },
      elements: [
        {
          type: 'HorizontalLayout',
          options: { separator: '/' },
          elements: [
            ctrl('#/properties/aggregateRating/properties/ratingValue'),
            ctrl('#/properties/aggregateRating/properties/bestRating'),
          ],
        },
      ],
    },
  ],
};
