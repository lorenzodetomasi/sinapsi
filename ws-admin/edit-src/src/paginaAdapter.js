/*
 * Fra il file e il modulo: appiattire per scrivere, ricomporre per salvare.
 *
 * LA REGOLA CHE CONTA, la stessa delle schede: salvando si PARTE DAL DOCUMENTO
 * ORIGINALE e ci si scrive sopra solo quello che il modulo possiede.
 *
 * Per una pagina non è una precauzione, è il requisito. Il modulo mostra
 * l'involucro e il testo principale; una pagina vera porta anche `section[]`
 * con dentro XInclude, `xpath`, `@xml:id`, `itemOffered`, `primaryImageOfPage`
 * con la sua figura, `workTranslation`, `datePublished`. Sono cose che nessun
 * modulo ragionevole mostrerà mai tutte, e che un salvataggio ricostruito da
 * zero cancellerebbe in silenzio. Un modulo che cancella quello che non sa
 * mostrare è peggio di nessun modulo.
 *
 * Il conto, sui contenuti veri: delle 9 pagine JSON di isotype, 7 hanno
 * `section[]`, 6 di quelle sezioni contengono un `xi:include` o un `xpath`, e
 * 1 un `itemOffered`. Niente di tutto questo è nel modulo.
 */

const testo = (v) => (v === undefined || v === null ? '' : String(v));

/** Sempre un array, anche quando nel file c'è un valore solo: schema.org
 *  permette tutte e due le forme e i contenuti veri le usano tutte e due. */
const lista = (x) => (x === undefined || x === null ? [] : Array.isArray(x) ? x : [x]);

/** Un riferimento (`{"@id": …}` oppure una stringa) → il suo @id. */
const rifId = (x) => (x && typeof x === 'object' ? testo(x['@id']) : testo(x));

/* I tipi di pagina che schema.org ha e che il modulo offre come ruolo. Serve a
 * sapere, leggendo un file, quale dei suoi `@type` è il ruolo e quali sono
 * l'impalcatura (`WebPage`, `CollectionPage`). */
const RUOLI_SCHEMA = new Set([
  'CollectionPage', 'AboutPage', 'ContactPage', 'FAQPage', 'ItemPage',
  'ProfilePage', 'SearchResultsPage', 'CheckoutPage', 'QAPage',
  'RealEstateListing', 'MedicalWebPage',
]);

/* ------------------------------------------------------------------ LEGGERE - */

export function paginaDaJsonLd(doc) {
  const d = doc && typeof doc === 'object' ? doc : {};
  const tipi = lista(d['@type']).map(testo);

  /* Il ruolo del CMS sta in `additionalType`. Si legge anche la vecchia forma,
   * `ws:PrivacyPage` dentro `@type`, perché i file migrati prima che la
   * questione fosse decisa la usano ancora e devono potersi aprire. */
  const vecchioRuolo = tipi.find((x) => x.startsWith('ws:'));
  const ruolo = testo(lista(d.additionalType)[0]) || (vecchioRuolo ? vecchioRuolo.slice(3) : '');

  return {
    wspath: testo(d.wspath),
    parent: testo(d.parent && d.parent.wspath),
    role: ruolo,
    /* Il ruolo schema.org è il primo `@type` che non sia impalcatura. */
    schemaRole: tipi.find((x) => RUOLI_SCHEMA.has(x) && x !== 'CollectionPage') || '',
    collection: tipi.includes('CollectionPage'),

    title: testo(d.title),
    description: testo(d.description),
    /* Le parole chiave, nei contenuti veri, sono scritte in tutte e due le
     * forme che schema.org ammette: un elenco (Meetoo) o una stringa con le
     * virgole (isotype). Il modulo le mostra sempre come elenco, e a salvare
     * si rimette la forma che il file aveva — trasformarla vorrebbe dire
     * cambiare un file che nessuno ha modificato. */
    keywords: keywordsDaJsonLd(d.keywords),
    robots: testo(d.robots),
    changefreq: testo(d.changefreq),
    priority: testo(d.priority),
    inLanguage: testo(d.inLanguage),
    output: lista(d.output).map(testo).filter(Boolean),

    name: testo(d.name),
    headline: testo(d.headline),
    mainContentOfPage: testo(d.mainContentOfPage),
    cta: testo(d.cta),

    sections: sezioniDaJsonLd(d.section),

    mainEntity: rifId(d.mainEntity),

    id: testo(d['@id']),
    template: (testo(d.query).match(/[?&]template=([a-z0-9_-]+)/i) || [])[1] || '',
    dateModified: testo(d.dateModified),
  };
}

/* ----------------------------------------------------------------- SALVARE - */

/*
 * Ricompone il documento: l'originale, con sopra quello che il modulo possiede.
 *
 * `docOriginale` è il file com'era. Tutto ciò che non viene toccato qui resta
 * dov'è e com'è — `section`, `primaryImageOfPage`, `workTranslation`,
 * `dateCreated`, `datePublished` e qualunque cosa qualcuno abbia scritto a mano
 * che questo modulo non conosce.
 */
export function paginaAJsonLd(d, docOriginale) {
  const out = { ...(docOriginale && typeof docOriginale === 'object' ? docOriginale : {}) };

  /*
   * I tipi, rifatti dalle tre scelte del modulo: `WebPage` sempre,
   * `CollectionPage` se la pagina ne raccoglie altre, poi il ruolo schema.org.
   * Dal generale allo specifico, come la regola della mappa si aspetta.
   */
  const tipi = ['WebPage'];
  if (d.collection) tipi.push('CollectionPage');
  if (d.schemaRole && d.schemaRole !== 'CollectionPage') tipi.push(d.schemaRole);
  out['@type'] = tipi.length === 1 ? tipi[0] : tipi;

  /* Il ruolo del CMS in `additionalType`, e la vecchia forma tolta di mezzo:
   * lasciare `ws:PrivacyPage` in `@type` accanto al nuovo vorrebbe dire due
   * dichiarazioni dello stesso ruolo, e la mappa ne leggerebbe una sola. */
  if (d.role) out.additionalType = d.role;
  else delete out.additionalType;

  /* Il contesto: schema.org e basta. Il `ws:` serviva solo ai tipi prefissati,
   * che non ci sono più. */
  out['@context'] = 'https://schema.org';

  scrivi(out, 'wspath', d.wspath, docOriginale);
  if (testo(d.parent)) out.parent = { wspath: testo(d.parent) };
  else delete out.parent;

  scrivi(out, 'title', d.title, docOriginale);
  scrivi(out, 'description', d.description, docOriginale);
  /* `keywords` torna com'era: stringa se era stringa. */
  const kw = (d.keywords || []).map(testo).map((x) => x.trim()).filter(Boolean);
  if (!kw.length) delete out.keywords;
  else if (typeof (docOriginale || {}).keywords === 'string') out.keywords = kw.join(', ');
  else out.keywords = kw;
  scrivi(out, 'robots', d.robots, docOriginale);
  scrivi(out, 'changefreq', d.changefreq, docOriginale);
  scrivi(out, 'priority', d.priority, docOriginale);
  scrivi(out, 'inLanguage', d.inLanguage, docOriginale);
  scriviLista(out, 'output', d.output);

  scrivi(out, 'name', d.name, docOriginale);
  scrivi(out, 'headline', d.headline, docOriginale);
  scrivi(out, 'mainContentOfPage', d.mainContentOfPage, docOriginale);
  scrivi(out, 'cta', d.cta, docOriginale);

  scriviSezioni(out, d.sections, docOriginale);

  /*
   * Il mainEntity: si cambia SOLO l'@id, e il resto del nodo resta.
   *
   * Una home dice anche di che tipo è l'attività di cui parla
   * (`["LocalBusiness", "RealEstateAgent"]`), e quel tipo il modulo non lo
   * chiede: scriverlo come `{"@id": …}` nudo lo butterebbe via.
   */
  const rif = testo(d.mainEntity);
  if (rif) {
    const prima = out.mainEntity && typeof out.mainEntity === 'object' ? out.mainEntity : {};
    out.mainEntity = { ...prima, '@id': rif };
  } else {
    delete out.mainEntity;
  }

  /* La data dell'ultimo salvataggio la mette chi salva, non chi compila. */
  out.dateModified = new Date().toISOString();

  return out;
}

/*
 * Scrive quando c'è qualcosa; quando non c'è, toglie la chiave — ma solo se
 * non c'era nemmeno prima.
 *
 * Una chiave con dentro il vuoto è rumore, e in una pagina NUOVA non si
 * aggiunge. In una che esiste si lascia: `"description": ""` scritto da
 * qualcuno è una decisione, e aprire una pagina, non toccare niente e salvarla
 * non deve cambiare il file. Un editor di cui non ci si può fidare su questo è
 * un editor che si smette di usare.
 */
function scrivi(out, chiave, valore, docOriginale) {
  const v = testo(valore).trim();
  if (v) { out[chiave] = v; return; }
  if (docOriginale && Object.prototype.hasOwnProperty.call(docOriginale, chiave)) {
    out[chiave] = testo(valore);
    return;
  }
  delete out[chiave];
}

/* Le parole chiave, da una forma o dall'altra, sempre come elenco. Una stringa
 * si divide sulle virgole: e' cosi' che la scrive chi la scrive cosi', ed e'
 * cosi' che i motori la leggono. */
function keywordsDaJsonLd(x) {
  if (typeof x === 'string') return x.split(',').map((s) => s.trim()).filter(Boolean);
  return lista(x).map(testo).map((s) => s.trim()).filter(Boolean);
}

function scriviLista(out, chiave, valore) {
  const v = (valore || []).map(testo).map((x) => x.trim()).filter(Boolean);
  if (v.length) out[chiave] = v;
  else delete out[chiave];
}

/* ------------------------------------------------------------- SEZIONI ---- */

/*
 * Una sezione, come la mostra il modulo.
 *
 * `from` dice che il corpo viene da un altro file — un `xi:include`, con o
 * senza un `xpath` che ne sceglie un pezzo — ed è in sola lettura. Il modulo
 * non offre di modificarlo perché non ce l'ha: offrirebbe un campo vuoto, e
 * riempirlo cancellerebbe il riferimento.
 */
function sezioniDaJsonLd(x) {
  return lista(x).map((s) => {
    if (!s || typeof s !== 'object') return { name: '', id: '', cls: '', text: testo(s), from: '' };
    const incl = s['xi:include'];
    const da = incl
      ? (typeof incl === 'object' ? testo(incl['@href'] || incl.href || '') : testo(incl))
      : '';
    return {
      name: testo(s.name),
      id: testo(s['@xml:id']),
      cls: testo(s['@class']),
      text: testo(s['#text']),
      from: da || (s.xpath ? testo(s.xpath) : ''),
    };
  });
}

/*
 * Le sezioni, riscritte sulle originali.
 *
 * Ogni sezione del modulo ritrova la sua nell'originale per `@xml:id`, e in
 * mancanza di quello per posizione; poi si sovrascrivono i quattro campi che il
 * modulo possiede e tutto il resto resta — `xi:include`, `xpath`,
 * `itemOffered`, e qualunque cosa qualcuno abbia scritto a mano.
 *
 * Una sezione tolta dal modulo sparisce davvero: toglierla è una decisione, e
 * conservarla «per prudenza» vorrebbe dire che il modulo non sa cancellare.
 */
function scriviSezioni(out, sezioni, docOriginale) {
  const prima = lista((docOriginale || {}).section);
  const perId = new Map();
  prima.forEach((s, i) => {
    if (s && typeof s === 'object' && s['@xml:id']) perId.set(String(s['@xml:id']), s);
  });

  const nuove = (sezioni || []).map((s, i) => {
    const base = (s.id && perId.get(String(s.id)))
      || (typeof prima[i] === 'object' && prima[i] !== null ? prima[i] : {});
    const fuori = { ...base };

    if (testo(s.id).trim()) fuori['@xml:id'] = testo(s.id).trim(); else delete fuori['@xml:id'];
    if (testo(s.cls).trim()) fuori['@class'] = testo(s.cls).trim(); else delete fuori['@class'];
    if (testo(s.name).trim()) fuori.name = testo(s.name).trim(); else delete fuori.name;
    /* Il testo si scrive solo dove il modulo ce l'ha davvero: su una sezione
     * che è un riferimento il campo è vuoto per costruzione, e scriverlo
     * metterebbe un `#text` vuoto accanto all'include. */
    if (testo(s.text) !== '') fuori['#text'] = testo(s.text);
    else if (!fuori['xi:include'] && !fuori.xpath) delete fuori['#text'];

    return fuori;
  }).filter((s) => Object.keys(s).length > 0);

  if (nuove.length) out.section = nuove;
  else delete out.section;
}

/* ------------------------------------------------------------------ NUOVA -- */

/*
 * Una pagina nuova. Non vuota: con i valori che quasi tutte le pagine vere
 * hanno, così chi la crea corregge invece di compilare.
 *
 * `query` non c'è, ed è voluto: la compone il server, che sa il tema del sito e
 * il percorso del contenuto. Un editor che se la scrivesse da solo la
 * sbaglierebbe il giorno che il sito cambia tema.
 */
export function docVuoto() {
  const adesso = new Date().toISOString();
  return {
    '@context': 'https://schema.org',
    '@type': 'WebPage',
    wspath: '',
    title: '',
    description: '',
    robots: 'index, follow',
    changefreq: 'monthly',
    priority: '0.5',
    dateCreated: adesso,
    datePublished: adesso,
    dateModified: adesso,
    name: '',
  };
}

export function daJsonLd(doc) { return paginaDaJsonLd(doc); }
export function aJsonLd(d, docOriginale) { return paginaAJsonLd(d, docOriginale); }
