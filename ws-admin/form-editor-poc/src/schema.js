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
  { const: 'https://schema.org/EventScheduled', title: 'Programmato' },
  { const: 'https://schema.org/EventPostponed', title: 'Rimandato' },
  { const: 'https://schema.org/EventRescheduled', title: 'Riprogrammato' },
  { const: 'https://schema.org/EventCancelled', title: 'Annullato' },
  { const: 'https://schema.org/EventMovedOnline', title: 'Spostato online' },
];
const AVAILABILITY = [
  { const: 'https://schema.org/InStock', title: 'Disponibile' },
  { const: 'https://schema.org/LimitedAvailability', title: 'Disponibilità limitata' },
  { const: 'https://schema.org/SoldOut', title: 'Esaurito' },
  { const: 'https://schema.org/PreOrder', title: 'Preordine' },
  { const: 'https://schema.org/OutOfStock', title: 'Non disponibile' },
];

export const schema = {
  type: 'object',
  properties: {
    id: { type: 'string', title: '@id' },
    types: { type: 'array', title: 'Tipi (@type)', items: { type: 'string' } },
    additionalType: { type: 'string', title: 'additionalType' },
    keywords: { type: 'array', title: 'Keywords', items: { type: 'string' } },
    name: { type: 'string', title: 'Nome evento' },
    description: { type: 'string', title: 'Descrizione', format: 'xhtml' },
    image: { type: 'string', title: 'Immagine', format: 'image' },
    logo: { type: 'string', title: 'Logo', format: 'image' },
    startDate: { type: 'string', format: 'date-time', title: 'Inizio' },
    endDate: { type: 'string', format: 'date-time', title: 'Fine' },
    typicalAgeRange: { type: 'string', title: "Fascia d'età" },
    eventAttendanceMode: { type: 'string', title: 'Modalità', oneOf: ATTENDANCE_MODE },
    eventStatus: { type: 'string', title: 'Stato evento', oneOf: EVENT_STATUS },
    maximumPhysicalAttendeeCapacity: { type: 'integer', title: 'Cap. fisica max' },
    maximumVirtualAttendeeCapacity: { type: 'integer', title: 'Cap. virtuale max' },
    maximumAttendeeCapacity: { type: 'integer', title: 'Cap. totale max' },
    remainingAttendeeCapacity: { type: 'integer', title: 'Posti rimasti' },
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
          description: { type: 'string', title: 'Descrizione', format: 'xhtml' },
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
        ctrl('#/properties/types', { options: { icon: 'category' } }),
      ],
    },
    {
      type: 'Group',
      label: 'Contenuto',
      elements: [
        ctrl('#/properties/name'),
        ctrl('#/properties/keywords', { options: { icon: 'sell' } }),
        ctrl('#/properties/description', { options: { icon: 'description' } }),
        {
          type: 'HorizontalLayout',
          elements: [
            ctrl('#/properties/image', { options: { icon: 'image' } }),
            ctrl('#/properties/logo', { options: { icon: 'branding_watermark' } }),
          ],
        },
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
    ctrl('#/properties/organizer', { label: 'Organizzatori', options: { icon: 'groups', variant: 'row' } }),
    ctrl('#/properties/subEvent', { label: 'Sotto-eventi', options: { icon: 'event', variant: 'stack' } }),
    {
      type: 'Group',
      label: 'Valutazione',
      elements: [
        {
          type: 'HorizontalLayout',
          elements: [
            ctrl('#/properties/aggregateRating/properties/ratingValue'),
            ctrl('#/properties/aggregateRating/properties/bestRating'),
          ],
        },
      ],
    },
    {
      type: 'Group',
      label: 'Meetoo',
      elements: [
        {
          type: 'HorizontalLayout',
          elements: [
            ctrl('#/properties/meetoo/properties/type'),
            ctrl('#/properties/meetoo/properties/macrocategory'),
          ],
        },
      ],
    },
  ],
};
