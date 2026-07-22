// JSON Schema (dati) — copertura COMPLETA di index.json.
// I campi rich-text usano "format": "xhtml" (renderer custom).
const ATTENDANCE_MODE = [
  'https://schema.org/OfflineEventAttendanceMode',
  'https://schema.org/OnlineEventAttendanceMode',
  'https://schema.org/MixedEventAttendanceMode',
];
const EVENT_STATUS = [
  'https://schema.org/EventScheduled',
  'https://schema.org/EventPostponed',
  'https://schema.org/EventRescheduled',
  'https://schema.org/EventCancelled',
  'https://schema.org/EventMovedOnline',
];
const AVAILABILITY = [
  'https://schema.org/InStock',
  'https://schema.org/LimitedAvailability',
  'https://schema.org/SoldOut',
  'https://schema.org/PreOrder',
  'https://schema.org/OutOfStock',
];

export const schema = {
  type: 'object',
  properties: {
    id: { type: 'string', title: '@id' },
    types: { type: 'array', title: 'Tipi (@type)', items: { type: 'string' } },
    additionalType: { type: 'string', title: 'additionalType' },
    keywords: { type: 'string', title: 'Keywords' },
    name: { type: 'string', title: 'Nome evento' },
    description: { type: 'string', title: 'Descrizione (XHTML)', format: 'xhtml' },
    image: { type: 'string', title: 'Immagine' },
    logo: { type: 'string', title: 'Logo' },
    startDate: { type: 'string', format: 'date-time', title: 'Inizio' },
    endDate: { type: 'string', format: 'date-time', title: 'Fine' },
    typicalAgeRange: { type: 'string', title: "Fascia d'età" },
    eventAttendanceMode: { type: 'string', title: 'Modalità', enum: ATTENDANCE_MODE },
    eventStatus: { type: 'string', title: 'Stato evento', enum: EVENT_STATUS },
    maximumPhysicalAttendeeCapacity: { type: 'integer', title: 'Cap. fisica max' },
    maximumVirtualAttendeeCapacity: { type: 'integer', title: 'Cap. virtuale max' },
    maximumAttendeeCapacity: { type: 'integer', title: 'Cap. totale max' },
    remainingAttendeeCapacity: { type: 'integer', title: 'Posti rimasti' },
    isAccessibleForFree: { type: 'boolean', title: 'Gratuito' },
    offers: {
      type: 'object',
      title: 'Offerta',
      properties: {
        availability: { type: 'string', title: 'Disponibilità', enum: AVAILABILITY },
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
          id: { type: 'string', title: '@id' },
          name: { type: 'string', title: 'Nome' },
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
          description: { type: 'string', title: 'Descrizione (XHTML)', format: 'xhtml' },
          startDate: { type: 'string', format: 'date-time', title: 'Inizio' },
          endDate: { type: 'string', format: 'date-time', title: 'Fine' },
        },
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
    meetoo: {
      type: 'object',
      title: 'Meetoo',
      properties: {
        type: { type: 'string', title: '@type', default: 'meetoo:EventSingle' },
        macrocategory: { type: 'string', title: 'Macrocategoria' },
      },
    },
  },
  required: ['name'],
};

const ctrl = (scope, extra = {}) => ({ type: 'Control', scope, ...extra });

export const uischema = {
  type: 'VerticalLayout',
  elements: [
    {
      type: 'Group',
      label: 'Identità',
      elements: [
        { type: 'HorizontalLayout', elements: [ctrl('#/properties/id'), ctrl('#/properties/additionalType')] },
        ctrl('#/properties/types'),
      ],
    },
    {
      type: 'Group',
      label: 'Contenuto',
      elements: [
        ctrl('#/properties/name'),
        ctrl('#/properties/keywords', { options: { multi: true } }),
        ctrl('#/properties/description'),
        { type: 'HorizontalLayout', elements: [ctrl('#/properties/image'), ctrl('#/properties/logo')] },
      ],
    },
    {
      type: 'Group',
      label: 'Quando & pubblico',
      elements: [
        { type: 'HorizontalLayout', elements: [ctrl('#/properties/startDate'), ctrl('#/properties/endDate')] },
        {
          type: 'HorizontalLayout',
          elements: [
            ctrl('#/properties/typicalAgeRange'),
            ctrl('#/properties/eventAttendanceMode'),
            ctrl('#/properties/eventStatus'),
          ],
        },
        {
          type: 'HorizontalLayout',
          elements: [
            ctrl('#/properties/maximumPhysicalAttendeeCapacity'),
            ctrl('#/properties/maximumVirtualAttendeeCapacity'),
            ctrl('#/properties/maximumAttendeeCapacity'),
            ctrl('#/properties/remainingAttendeeCapacity'),
          ],
        },
        ctrl('#/properties/isAccessibleForFree'),
      ],
    },
    {
      type: 'Group',
      label: 'Offerta (oggetto annidato)',
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
    {
      type: 'Group',
      label: 'Luogo (oggetto annidato)',
      elements: [
        {
          type: 'HorizontalLayout',
          elements: [
            ctrl('#/properties/location/properties/id'),
            ctrl('#/properties/location/properties/type'),
            ctrl('#/properties/location/properties/name'),
          ],
        },
      ],
    },
    ctrl('#/properties/organizer', { label: 'Organizzatori (fieldset ripetibile)' }),
    ctrl('#/properties/subEvent', { label: 'Sotto-eventi (fieldset ripetibile con XHTML)' }),
    {
      type: 'Group',
      label: 'Valutazione & Meetoo',
      elements: [
        {
          type: 'HorizontalLayout',
          elements: [
            ctrl('#/properties/aggregateRating/properties/ratingValue'),
            ctrl('#/properties/aggregateRating/properties/bestRating'),
            ctrl('#/properties/meetoo/properties/type'),
            ctrl('#/properties/meetoo/properties/macrocategory'),
          ],
        },
      ],
    },
  ],
};
