// JSON Schema (dati) — sottoinsieme di index.json che dimostra i punti difficili:
// tipi vari, enum, oggetto annidato (offers), fieldset RIPETIBILE (organizer),
// e un campo rich-text XHTML (description, via "format": "xhtml").
export const schema = {
  type: 'object',
  properties: {
    name: { type: 'string', title: 'Nome evento' },
    description: {
      type: 'string',
      title: 'Descrizione (XHTML)',
      format: 'xhtml',
    },
    startDate: {
      type: 'string',
      format: 'date-time',
      title: 'Inizio',
    },
    isAccessibleForFree: { type: 'boolean', title: 'Gratuito' },
    eventStatus: {
      type: 'string',
      title: 'Stato evento',
      enum: [
        'https://schema.org/EventScheduled',
        'https://schema.org/EventPostponed',
        'https://schema.org/EventCancelled',
        'https://schema.org/EventRescheduled',
      ],
    },
    offers: {
      type: 'object',
      title: 'Offerta',
      properties: {
        availability: {
          type: 'string',
          title: 'Disponibilità',
          enum: [
            'https://schema.org/InStock',
            'https://schema.org/LimitedAvailability',
            'https://schema.org/SoldOut',
          ],
        },
        price: { type: 'number', title: 'Prezzo' },
        priceCurrency: { type: 'string', title: 'Valuta', default: 'EUR' },
      },
    },
    organizer: {
      type: 'array',
      title: 'Organizzatori',
      items: {
        type: 'object',
        properties: {
          id: { type: 'string', title: 'ID (@id)' },
          name: { type: 'string', title: 'Nome' },
        },
      },
    },
  },
  required: ['name'],
};

// UI Schema (layout) — controlla ordine e resa, separata dai dati.
export const uischema = {
  type: 'VerticalLayout',
  elements: [
    { type: 'Control', scope: '#/properties/name' },
    { type: 'Control', scope: '#/properties/description' },
    {
      type: 'HorizontalLayout',
      elements: [
        { type: 'Control', scope: '#/properties/startDate' },
        { type: 'Control', scope: '#/properties/isAccessibleForFree' },
        { type: 'Control', scope: '#/properties/eventStatus' },
      ],
    },
    {
      type: 'Group',
      label: 'Offerta (oggetto annidato)',
      elements: [
        {
          type: 'HorizontalLayout',
          elements: [
            { type: 'Control', scope: '#/properties/offers/properties/availability' },
            { type: 'Control', scope: '#/properties/offers/properties/price' },
            { type: 'Control', scope: '#/properties/offers/properties/priceCurrency' },
          ],
        },
      ],
    },
    {
      type: 'Control',
      scope: '#/properties/organizer',
      label: 'Organizzatori (fieldset ripetibile)',
    },
  ],
};
