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
/* Fasce d'età: le stesse che si leggono nelle categorie, con la scuola accanto.
 *
 * PIASTRELLANO: non si sovrappongono e non lasciano buchi, e ogni anno sta in una
 * fascia sola. È quello che permette al campo di restare uno — il testo di
 * schema.org, «6-13» — anche quando le fasce spuntate sono più d'una: se sono
 * attaccate, l'unione È la scelta, e non c'è un altro insieme di fasce che dia lo
 * stesso testo. Nessun campo di servizio a ricordare le caselle.
 *
 * I confini sono quelli della scuola perché sono gli unici che tutti conoscono
 * senza doverli imparare: le medie finiscono a 13, le superiori cominciano a 14.
 * I suggerimenti di prima (0-3, 3-6, 6-12) si sovrapponevano ai bordi, e un bambino
 * di tre anni finiva in due fasce a seconda di chi compilava.
 *
 * Le categorie che si popolano da sole leggono QUESTO campo (`ageWithin` e
 * `ageOverlaps` in ws-listrule.php). Una fascia scritta a parole — «da 3 a 6 anni»
 * — resta valida per chi legge, ma la regola non la riconosce. */
const AGE_BANDS = [
  { id: 'baby',      name: 'Baby',        min: 0,  max: 2,   school: 'nido' },
  { id: 'materna',   name: 'Materna',     min: 3,  max: 5,   school: 'infanzia' },
  { id: 'bambini',   name: 'Bambini',     min: 6,  max: 10,  school: 'primaria' },
  { id: 'ragazzi',   name: 'Ragazzi',     min: 11, max: 13,  school: 'medie' },
  { id: 'adolesc',   name: 'Adolescenti', min: 14, max: 18,  school: 'superiori' },
  { id: 'giovani',   name: 'Giovani',     min: 19, max: 34,  school: '' },
  { id: 'adulti',    name: 'Adulti',      min: 35, max: 64,  school: '' },
  { id: 'terzaeta',  name: 'Terza età',   min: 65, max: 120, school: '' },
];
export const schema = {
  type: 'object',
  properties: {
    // Si compone da solo dai campi del form (vedi eventId.js); resta scrivibile.
    id: { type: 'string', title: '@id', format: 'event-id' },
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
    /* Due testi, due mestieri: il SOMMARIO è quello che legge una persona quando
     * apre la pagina (e può essere formattato); la DESCRIZIONE è la frase che
     * finisce nel risultato di ricerca e nell'anteprima di un link condiviso, ed è
     * testo semplice perché lì la marcatura non arriva. Prima erano lo stesso
     * campo, e la pagina lo mostrava due volte. */
    abstract: { type: 'string', title: 'Sommario', format: 'xhtml' },
    description: { type: 'string', title: 'Descrizione', format: 'seo' },
    image: { type: 'string', title: 'Immagine', format: 'image' },
    logo: { type: 'string', title: 'Logo', format: 'image' },
    // Il fuso in cui l'evento succede: da qui esce lo scarto (+02:00) che rende
    // le date non ambigue per chi legge il JSON da fuori.
    timezone: { type: 'string', title: 'Fuso orario', format: 'timezone' },
    startDate: { type: 'string', format: 'date-time', title: 'Dal' },
    endDate: { type: 'string', format: 'date-time', title: 'al' },
    typicalAgeRange: { type: 'string', title: "Fascia d'età" },
    eventStatus: { type: 'string', title: 'Stato evento', oneOf: EVENT_STATUS },
    eventAttendanceMode: { type: 'string', title: 'Modalità', oneOf: ATTENDANCE_MODE },
    /* «Posti limitati» non finisce nel contenuto: e' una domanda che si fa
     * all'editor, non un dato dell'evento. Se l'evento ha un tetto lo dicono le
     * capienze; se non ce l'ha, non c'e' niente da dire. Al caricamento la
     * spunta si accende da sola quando una capienza c'e' gia'. */
    hasLimitedCapacity: { type: 'boolean', title: 'Posti limitati' },
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
      format: 'place', // renderer con Google Places (nome autocomplete)
      properties: {
        id: { type: 'string', title: '@id' },
        type: { type: 'string', title: '@type', default: 'Place' },
        name: { type: 'string', title: 'Nome' },
        googlePlaceId: { type: 'string', format: 'hidden' },
      },
    },
    organizer: {
      type: 'array',
      title: 'Organizzatori',
      items: {
        type: 'object',
        properties: {
          // L'organizzatore è un'entità che sta GIÀ sul sito (organizations/… o un
          // luogo/attività): si sceglie dall'elenco, o si incolla l'@id e il nome
          // arriva da solo. Prima c'era l'autocomplete di Google Places, che però
          // proponeva mezzo mondo e lasciava l'@id da scrivere a memoria.
          name: { type: 'string', title: 'Nome', format: 'entity' },
          id: { type: 'string', title: '@id', format: 'entity-id' },
          googlePlaceId: { type: 'string', format: 'hidden' },
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
          // Il programma sta dentro la giornata dell'evento: qui si scelgono solo
          // gli orari, e la data la mette l'evento. Nel file resta un datetime
          // intero, perché un orario nudo non e' un istante.
          startDate: { type: 'string', format: 'ora', title: 'Dalle' },
          endDate: { type: 'string', format: 'ora', title: 'alle' },
        },
      },
    },
    // Serie contenitrice di quest'occorrenza (riferimento events/{slug} alla EventSeries).
    superEvent: { type: 'string', title: 'Appartiene a una Collezione (facoltativo)' },
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
const showIfLimited = { effect: 'SHOW', condition: { scope: '#/properties/hasLimitedCapacity', schema: { const: true } } };

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
            // Serie contenitrice accanto al tipo, solo per gli eventi non-serie (Evento singolo)
            ctrl('#/properties/superEvent', { options: { icon: 'account_tree' }, rule: showIfNotSeries }),
          ],
        },
        // L'@id sta su una riga sua: si compone da solo e si porta dietro una nota
        // (che cosa manca, dove finira' il file), che in un terzo di colonna
        // diventava un muro di testo a capo ogni due parole.
        ctrl('#/properties/id'),
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
        ctrl('#/properties/abstract', { options: { icon: 'subject' } }),
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
      /* Quello che il contenuto dice DI SÉ a chi non l'ha ancora aperto: il
       * risultato su un motore di ricerca, l'anteprima di un link su una chat.
       * Sta subito dopo il testo perché si scrive guardando quello — e infatti la
       * frase si propone da sola a partire dal Sommario. */
      type: 'Group',
      label: 'Ottimizzazione per i motori di ricerca (SEO)',
      options: { icon: 'travel_explore' },
      elements: [
        ctrl('#/properties/description', { options: { icon: 'description' } }),
        ctrl('#/properties/keywords', { options: { icon: 'sell' } }),
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
      ],
    },
    {
      type: 'Group',
      label: 'Dove',
      options: { icon: 'place' },
      elements: [ctrl('#/properties/location', { options: { icon: 'place' } })],
    },
    {
      type: 'Group',
      label: 'Quando',
      options: { icon: 'schedule' },
      elements: [
        // Il fuso viene prima delle date: senza, un'ora non dice quale istante è.
        ctrl('#/properties/timezone', { options: { icon: 'public' } }),
        {
          type: 'HorizontalLayout',
          options: { separator: '–' },
          elements: [ctrl('#/properties/startDate'), ctrl('#/properties/endDate')],
        },
        // Ricorrenza: solo per le serie
        ctrl('#/properties/eventSchedule', { rule: showIfSeries }),
        // Quello che non torna fra date, programma e occorrenze
        { type: 'Coerenza' },
        ctrl('#/properties/eventStatus'),
      ],
    },
    {
      type: 'Group',
      label: 'Pubblico',
      options: { icon: 'people' },
      elements: [
        ctrl('#/properties/eventAttendanceMode'),
        // Riga sua: sono otto fasce più due numeri, in mezza riga si accavallerebbero.
        ctrl('#/properties/typicalAgeRange', {
          options: { ageRange: true, bands: AGE_BANDS, icon: 'child_care' },
        }),
        // I posti si contano solo se sono contati: la spunta apre i campi, e finche'
        // e' spenta cinque caselle vuote non stanno li' a farsi guardare. Un evento
        // senza tetto e' il caso normale, e il caso normale non deve chiedere niente.
        ctrl('#/properties/hasLimitedCapacity', { options: { inline: true } }),
        // Capienze in griglia 3 colonne. I due calcolati (totale, rimasti) stanno
        // nella stessa colonna (3ª); uno spaziatore tiene libera la 2ª nella riga 2:
        //   presenza | remoto  | totale(calcolato)
        //   prenotati| ­        | rimasti(calcolato)
        {
          type: 'HorizontalLayout',
          options: { cols: 3 },
          rule: showIfLimited,
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
