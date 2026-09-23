/*
 * Lo schema delle PAGINE: quello che un sito dice di sé, non le sue entità.
 *
 * Il vocabolario non è inventato. È stato contato sui contenuti veri: le 9
 * pagine già in JSON e le 34 ancora scritte a mano dicono le stesse cose, e
 * dicono queste. `wspath`, `query`, `title`, `description`, `changefreq`,
 * `priority`, `robots` e le tre date ci sono in tutte; `parent`, `name`,
 * `headline`, `mainContentOfPage` quasi in tutte; `keywords`, `cta`,
 * `primaryImageOfPage`, `workTranslation`, `output` in poche. Inventarne di
 * nuovi qui vorrebbe dire che il modulo e i contenuti parlano due lingue.
 *
 * UNA PAGINA HA TRE STRATI, e il modulo li tiene separati perché lo sono:
 *
 *   l'INVOLUCRO   dove risponde, come si chiama nella scheda del browser, se i
 *                 motori la devono indicizzare. Sono i campi che la MAPPA legge:
 *                 cambiarli cambia l'indirizzo del sito, non il suo racconto.
 *   il CORPO      il nome, l'occhiello, il testo, le sezioni. Quello che legge
 *                 chi apre la pagina.
 *   di CHE COSA PARLA   il `mainEntity`, quando la pagina è a proposito di
 *                 qualcosa che ha una vita propria — un servizio, un'attività.
 *                 Qui si sceglie solo QUALE: la cosa si modifica dove abita.
 *
 * QUELLO CHE NON SI SCRIVE A MANO. `query` la compone il CMS dal tema, dal
 * template e dal percorso del contenuto; `dateModified` la mette il
 * salvataggio; `@id` è la cartella. Nel modulo si vedono ma non si toccano: un
 * campo che si può battere a mano e che qualcun altro riscrive mente due volte,
 * la prima quando lo compili e la seconda quando torni e non c'è più.
 */

import { t } from './i18n.js';

export const ctrl = (scope, extra = {}) => ({ type: 'Control', scope, ...extra });

const gruppoCampi = (label, icon, elements) => ({
  type: 'Group', label, options: { icon }, elements,
});

/*
 * I ruoli di pagina.
 *
 * I primi sono tipi di schema.org e vanno in `@type`. Gli altri sono ruoli che
 * schema.org non ha e che il CMS cerca (`url[type = 'PrivacyPage']` nel menu
 * legale, `ws_pageLink('PrivacyPage')` nella spunta del consenso): vanno in
 * `additionalType`, che è il modo che schema.org stesso indica per un tipo di
 * un altro vocabolario. Così ogni CHIAVE della pagina resta una chiave
 * schema.org e nostra è solo la parola.
 *
 * La mappa legge `additionalType` per primo, prima del mainEntity: senza quella
 * precedenza una home smetterebbe di essere un Index nel momento in cui il sito
 * dichiara di che attività parla.
 */
export const RUOLI_SCHEMA = [
  { const: '', title: t('No particular role') },
  { const: 'CollectionPage', title: t('Collects other pages') },
  { const: 'AboutPage', title: t('About us') },
  { const: 'ContactPage', title: t('Contact') },
  { const: 'FAQPage', title: t('Frequently asked questions') },
  { const: 'ItemPage', title: t('About one thing') },
  { const: 'ProfilePage', title: t('Someone’s profile') },
  { const: 'SearchResultsPage', title: t('Search results') },
  { const: 'CheckoutPage', title: t('Checkout') },
];

export const RUOLI_NOSTRI = [
  { const: '', title: t('None') },
  { const: 'Index', title: t('Home — where the site opens') },
  { const: 'PrivacyPage', title: t('Privacy policy') },
  { const: 'CookiesPage', title: t('Cookie policy') },
  { const: 'DisclaimerPage', title: t('Disclaimer') },
  { const: 'QuotePage', title: t('Ask for a quote') },
  { const: 'ServicesList', title: t('List of services') },
  { const: 'ProductsList', title: t('List of products') },
  { const: 'EventsList', title: t('List of events') },
  { const: 'NewsList', title: t('News') },
];

/* Ogni quanto cambia, per i motori. Sono i valori che sitemap.org ammette: non
 * una scala di importanza, ma una previsione — e una previsione sbagliata fa
 * tornare i motori quando non serve, o non farli tornare quando servirebbe. */
const FREQUENZE = [
  { const: 'always', title: t('Continuously') },
  { const: 'hourly', title: t('Hourly') },
  { const: 'daily', title: t('Daily') },
  { const: 'weekly', title: t('Weekly') },
  { const: 'monthly', title: t('Monthly') },
  { const: 'yearly', title: t('Yearly') },
  { const: 'never', title: t('Never') },
];

/* `robots` nei contenuti veri è sempre una di queste due coppie. Si offrono
 * come scelta e non come testo libero perché una virgola dimenticata o un
 * «nofollow» scritto «no-follow» non danno errore: tolgono la pagina dai
 * motori, in silenzio, e nessuno se ne accorge per mesi. */
const ROBOTS = [
  { const: 'index, follow', title: t('Findable') },
  { const: 'noindex, follow', title: t('Not findable, links followed') },
  { const: 'noindex, nofollow', title: t('Hidden from search engines') },
];

/* Le uscite che una pagina chiede oltre al gemello XML, che è implicito. */
const USCITE = [
  { const: 'html', title: t('Static HTML copy') },
  { const: 'amp', title: t('AMP page') },
];

/*
 * I template che `your-theme` offre, e che ogni tema figlio eredita.
 *
 * Sono i file .php alla radice del tema: `page` e' il caso normale, `index` la
 * home, `contacts` la pagina con il modulo, `section` una pagina che raccoglie
 * quelle sotto, `event-list` l'archivio degli eventi. Un tema figlio che ne
 * aggiunge uno suo lo avra' in piu' e qui non comparira': l'elenco e' scritto,
 * non scoperto, perche' il modulo gira nel browser e i file del tema stanno sul
 * server.
 */
const TEMPLATE = [
  { const: 'page', title: t('Normal page') },
  { const: 'index', title: t('Home') },
  { const: 'section', title: t('Collects the pages below') },
  { const: 'contacts', title: t('With a contact form') },
  { const: 'service', title: t('A service') },
  { const: 'offer', title: t('What is on offer') },
  { const: 'event-list', title: t('Archive of events') },
  { const: 'page-protected', title: t('Behind a login') },
];

export const schemaPagina = {
  type: 'object',
  properties: {
    /* --- l'involucro --- */
    wspath: {
      type: 'string',
      title: t('Address'),
      description: t('Where the page answers, from the site’s root. The home is “/”.'),
      pattern: '^/',
    },
    parent: {
      type: 'string',
      title: t('Under which page'),
      description: t('The address of the page above. Empty for a page at the top.'),
    },
    role: { type: 'string', title: t('Role'), oneOf: RUOLI_NOSTRI },
    schemaRole: { type: 'string', title: t('What it is, for schema.org'), oneOf: RUOLI_SCHEMA },
    collection: {
      type: 'boolean',
      title: t('Has pages under it'),
      description: t('Adds CollectionPage: a page that gathers the pages below.'),
    },

    /* --- la scheda del browser e i motori --- */
    title: {
      type: 'string',
      title: t('Title'),
      description: t('What the browser tab and the search results show.'),
    },
    description: { type: 'string', title: t('Description'), format: 'textarea' },
    keywords: { type: 'array', title: t('Keywords'), items: { type: 'string' } },
    robots: { type: 'string', title: t('Findable'), oneOf: ROBOTS },
    changefreq: { type: 'string', title: t('Changes'), oneOf: FREQUENZE },
    priority: {
      type: 'string',
      title: t('Priority'),
      description: t('From 0 to 1, among the pages of this site alone.'),
      pattern: '^(0(\\.\\d+)?|1(\\.0+)?)$',
    },
    inLanguage: { type: 'string', title: t('Language'), description: t('For example it-IT.') },
    output: { type: 'array', title: t('Also publish as'), items: { type: 'string', oneOf: USCITE } },

    /* --- il corpo --- */
    name: { type: 'string', title: t('Name'), description: t('What the page calls itself, in its heading.') },
    headline: { type: 'string', title: t('Strapline') },
    mainContentOfPage: { type: 'string', title: t('Text'), format: 'xhtml' },
    cta: { type: 'string', title: t('Call to action') },

    /* --- di che cosa parla --- */
    mainEntity: {
      type: 'string',
      title: t('What the page is about'),
      description: t('The @id of a thing that lives on its own: a service, a business, a place. Leave empty when the page is only itself.'),
    },

    /* Quale template disegna la pagina. E' una scelta vera — decide come la
     * pagina SI VEDE — e per questo si modifica; quello che il modulo non
     * scrive e' la `query`, che il server compone dal tema del sito, da questo
     * template e dal percorso del contenuto. Un editor che se la scrivesse da
     * solo la sbaglierebbe il giorno che il sito cambia tema. */
    template: { type: 'string', title: t('Template'), oneOf: TEMPLATE },

    /* --- non si toccano --- */
    id: { type: 'string', title: t('Folder (@id)'), readOnly: true },
    dateModified: { type: 'string', title: t('Last saved'), readOnly: true },
  },
  required: ['wspath', 'title'],
};

export const uischemaPagina = {
  type: 'VerticalLayout',
  elements: [
    gruppoCampi(t('Where it answers'), 'link', [
      {
        type: 'HorizontalLayout',
        elements: [
          ctrl('#/properties/wspath', { options: { icon: 'link' } }),
          ctrl('#/properties/parent', { options: { icon: 'subdirectory_arrow_right' } }),
        ],
      },
      /* Il ruolo sta accanto all'indirizzo, non fra i dati per i motori: è la
       * cosa che il CMS cerca per costruire i menu, quindi appartiene a dove la
       * pagina STA nel sito, non a come si racconta. */
      {
        type: 'HorizontalLayout',
        elements: [
          ctrl('#/properties/role', { options: { icon: 'label' } }),
          ctrl('#/properties/schemaRole', { options: { icon: 'category' } }),
        ],
      },
      ctrl('#/properties/collection', { options: { inline: true } }),
      {
        type: 'HorizontalLayout',
        options: { cols: 3 },
        elements: [
          ctrl('#/properties/id'),
          ctrl('#/properties/template'),
          ctrl('#/properties/inLanguage'),
        ],
      },
    ]),

    gruppoCampi(t('Content'), 'article', [
      ctrl('#/properties/name', { options: { icon: 'title' } }),
      ctrl('#/properties/headline'),
      ctrl('#/properties/mainContentOfPage'),
      ctrl('#/properties/cta', { options: { icon: 'ads_click' } }),
    ]),

    gruppoCampi(t('What it is about'), 'hub', [
      ctrl('#/properties/mainEntity', { options: { icon: 'link' } }),
    ]),

    gruppoCampi(t('Search engines'), 'travel_explore', [
      ctrl('#/properties/title', { options: { icon: 'tab' } }),
      ctrl('#/properties/description'),
      ctrl('#/properties/keywords', { options: { icon: 'tag' } }),
      {
        type: 'HorizontalLayout',
        options: { cols: 3 },
        elements: [
          ctrl('#/properties/robots'),
          ctrl('#/properties/changefreq'),
          ctrl('#/properties/priority'),
        ],
      },
      ctrl('#/properties/output', { options: { select: true } }),
      ctrl('#/properties/dateModified'),
    ]),
  ],
};

export function schemaPer() {
  return { schema: schemaPagina, uischema: uischemaPagina, tipo: 'pagina' };
}
