/*
 * Fra il file e il modulo: appiattire per scrivere, ricomporre per salvare.
 *
 * LA REGOLA CHE CONTA: salvando si PARTE DAL DOCUMENTO ORIGINALE e ci si scrive
 * sopra solo quello che il modulo possiede. Ricostruirlo da zero — come fa
 * l'editor degli eventi, che conosce tutti i suoi campi — qui vorrebbe dire
 * perdere tutto ciò che il modulo non mostra: le tre mappe che genera Google, i
 * crediti del satellite, `creator`, `dateCreated`, un `hasPart` scritto a mano.
 * Un modulo che cancella quello che non sa mostrare è peggio di nessun modulo.
 */

const testo = (v) => (v === undefined || v === null ? '' : String(v));

/** Un riferimento (`{"@id": …}` oppure una stringa) → il suo @id. */
const rifId = (x) => (x && typeof x === 'object' ? testo(x['@id']) : testo(x));

/** Sempre un array, anche quando nel file c'è un valore solo: schema.org
 *  permette tutte e due le forme e i contenuti veri le usano tutte e due. */
const lista = (x) => (x === undefined || x === null ? [] : Array.isArray(x) ? x : [x]);

/** Una lista di uid → riferimenti a persone. Si scrive come oggetto e non come
 *  stringa nuda perché è così che il server li legge — e perché un @id dentro un
 *  oggetto è un riferimento, mentre una stringa è solo una stringa. */
const persone = (uids) => (uids || [])
  .map((x) => String(x).trim())
  .filter(Boolean)
  .map((uid) => ({ '@type': 'Person', '@id': uid.startsWith('users/') ? uid : `users/${uid}` }));

/** L'entità dentro il documento (i contenuti hanno il guscio di pagina). */
export function entitaDi(doc) {
  return doc && typeof doc === 'object' && doc.mainEntity && typeof doc.mainEntity === 'object'
    ? doc.mainEntity
    : (doc || {});
}

/* --------------------------------------------------------------- LUOGO ---- */

export function luogoDaJsonLd(doc) {
  const e = entitaDi(doc);
  const tipi = lista(e['@type']).map(testo).filter(Boolean);
  const ind = e.address && typeof e.address === 'object' ? e.address : {};
  const geo = e.geo && typeof e.geo === 'object' ? e.geo : {};
  const voto = e.aggregateRating && typeof e.aggregateRating === 'object' ? e.aggregateRating : {};
  const icona = e['meetoo:icon'] && typeof e['meetoo:icon'] === 'object' ? e['meetoo:icon'] : {};
  return {
    id: testo(e['@id']),
    primaryType: tipi[0] || 'Place',
    subtypes: tipi.slice(1),
    name: testo(e.name),
    additionalType: lista(e.additionalType).map(testo).filter(Boolean),
    abstract: testo(e.abstract),
    description: testo(e.description),
    url: testo(e.url),
    telephone: testo(e.telephone),
    // Un `sameAs` con dentro una stringa vuota è un rumore che i file veri hanno:
    // si toglie leggendo, così non torna indietro al primo salvataggio.
    sameAs: lista(e.sameAs).map(testo).filter((s) => s.trim() !== ''),
    keywords: lista(e.keywords).map(testo).filter(Boolean),

    streetAddress: testo(ind.streetAddress),
    postalCode: testo(ind.postalCode),
    addressLocality: testo(ind.addressLocality),
    addressRegion: lista(ind.addressRegion).map(testo).filter(Boolean).join(', '),
    addressCountry: testo(ind.addressCountry),
    latitude: testo(geo.latitude),
    longitude: testo(geo.longitude),

    priceRange: testo(e.priceRange),
    legalStatus: testo(e['meetoo:legalStatus']),
    businessStatus: testo(e['meetoo:business_status']),
    accessibility: lista(e['meetoo:accessibilityFeature']).map(testo).filter(Boolean),
    amenityFeature: lista(e.amenityFeature)
      .filter((a) => a && typeof a === 'object')
      .map((a) => ({ name: testo(a.name), value: a.value !== false })),

    logo: testo(e.logo),
    image: testo(e.image),
    iconName: testo(icona.name || icona['meetoo:name']),

    containedInPlace: rifId(e.containedInPlace),
    isGroup: e['meetoo:isGroup'] === true,

    contributor: lista(e.contributor).map(rifId).filter(Boolean),
    googlePlaceId: testo(e['meetoo:google_place_id']),
    ratingValue: testo(voto.ratingValue),
    reviewCount: testo(voto.reviewCount),
    satelliteView: testo(e['meetoo:satelliteView']),
    satelliteCredit: testo(e['meetoo:satelliteCredit']),
    mapCount: lista(e.hasMap).length ? String(lista(e.hasMap).length) : '',
  };
}

export function luogoAJsonLd(d, docOriginale) {
  const doc = JSON.parse(JSON.stringify(docOriginale || {}));
  const dentro = doc.mainEntity && typeof doc.mainEntity === 'object';
  const e = dentro ? doc.mainEntity : doc;

  const metti = (chiave, valore) => {
    if (valore === undefined || valore === '' || (Array.isArray(valore) && !valore.length)) delete e[chiave];
    else e[chiave] = valore;
  };

  const tipi = [d.primaryType || 'Place', ...(d.subtypes || [])].filter(Boolean);
  e['@type'] = tipi.length > 1 ? tipi : tipi[0];
  e['@id'] = d.id || '';

  metti('name', d.name || undefined);
  metti('additionalType', (d.additionalType || []).filter(Boolean));
  metti('abstract', d.abstract || undefined);
  metti('description', d.description || undefined);
  metti('url', d.url || undefined);
  metti('telephone', d.telephone || undefined);
  metti('sameAs', (d.sameAs || []).filter((s) => String(s).trim() !== ''));
  metti('keywords', (d.keywords || []).filter(Boolean));

  /* L'indirizzo si ricompone tenendo quello che c'era: il `@type` e i campi che
   * il modulo non mostra restano dove sono. */
  const ind = (e.address && typeof e.address === 'object') ? e.address : { '@type': 'PostalAddress' };
  const indMetti = (k, v) => { if (v === undefined || v === '') delete ind[k]; else ind[k] = v; };
  indMetti('streetAddress', d.streetAddress);
  indMetti('postalCode', d.postalCode);
  indMetti('addressLocality', d.addressLocality);
  const regione = String(d.addressRegion || '').split(',').map((s) => s.trim()).filter(Boolean);
  if (!regione.length) delete ind.addressRegion;
  else ind.addressRegion = regione.length === 1 ? regione[0] : regione;
  indMetti('addressCountry', d.addressCountry);
  if (Object.keys(ind).filter((k) => k !== '@type').length) e.address = ind; else delete e.address;

  metti('priceRange', d.priceRange || undefined);
  metti('meetoo:legalStatus', d.legalStatus || undefined);
  metti('meetoo:business_status', d.businessStatus || undefined);
  metti('meetoo:accessibilityFeature', (d.accessibility || []).filter(Boolean));
  metti('amenityFeature', (d.amenityFeature || [])
    .filter((a) => a && String(a.name || '').trim() !== '')
    .map((a) => ({ '@type': 'LocationFeatureSpecification', name: a.name, value: a.value !== false })));

  metti('logo', d.logo || undefined);
  metti('image', d.image || undefined);
  if (d.iconName) {
    const vecchia = (e['meetoo:icon'] && typeof e['meetoo:icon'] === 'object') ? e['meetoo:icon'] : {};
    e['meetoo:icon'] = { class: vecchia.class || 'material-symbols-outlined', name: d.iconName };
  } else delete e['meetoo:icon'];

  if (d.containedInPlace) {
    const vecchio = (e.containedInPlace && typeof e.containedInPlace === 'object') ? e.containedInPlace : {};
    e.containedInPlace = { ...vecchio, '@id': d.containedInPlace };
  } else delete e.containedInPlace;

  metti('contributor', persone(d.contributor));

  if (d.isGroup) e['meetoo:isGroup'] = true; else delete e['meetoo:isGroup'];

  if (dentro) { doc.mainEntity = e; doc['@id'] = e['@id']; }
  return doc;
}

/* -------------------------------------------------------------- GRUPPO ---- */

export function gruppoDaJsonLd(doc) {
  const e = entitaDi(doc);
  const voto = e.aggregateRating && typeof e.aggregateRating === 'object' ? e.aggregateRating : {};
  const icona = e['meetoo:icon'] && typeof e['meetoo:icon'] === 'object' ? e['meetoo:icon'] : {};
  // I contatti nei file stanno in `contactPoint`, che è una lista di modi per
  // farsi trovare. Nel modulo diventano due campi, che è come li pensa chi scrive.
  const contatti = lista(e.contactPoint).filter((c) => c && typeof c === 'object');
  const primo = (chiave) => testo((contatti.find((c) => c[chiave]) || {})[chiave]);
  return {
    id: testo(e['@id']),
    name: testo(e.name),
    legalName: testo(e.legalName),
    abstract: testo(e.abstract),
    description: testo(e.description),
    url: testo(e.url),
    email: primo('email'),
    telephone: primo('telephone') || testo(e.telephone),
    sameAs: lista(e.sameAs).map(testo).filter((s) => s.trim() !== ''),
    keywords: lista(e.keywords).map(testo).filter(Boolean),
    logo: testo(e.logo),
    image: testo(e.image),
    iconName: testo(icona.name || icona['meetoo:name']),
    location: rifId(e.location) || rifId(e.containedInPlace),
    areaServed: testo(e.areaServed && typeof e.areaServed === 'object' ? e.areaServed.name : e.areaServed),
    manager: lista(e['meetoo:manager']).map(rifId).filter(Boolean),
    contributor: lista(e.contributor).map(rifId).filter(Boolean),
    verified: e['meetoo:verified'] === true,
    ratingValue: testo(voto.ratingValue),
    reviewCount: testo(voto.reviewCount),
  };
}

export function gruppoAJsonLd(d, docOriginale) {
  const doc = JSON.parse(JSON.stringify(docOriginale || {}));
  const dentro = doc.mainEntity && typeof doc.mainEntity === 'object';
  const e = dentro ? doc.mainEntity : doc;
  const metti = (k, v) => {
    if (v === undefined || v === '' || (Array.isArray(v) && !v.length)) delete e[k];
    else e[k] = v;
  };

  if (!e['@type']) e['@type'] = 'Organization';
  e['@id'] = d.id || '';
  metti('name', d.name || undefined);
  metti('legalName', d.legalName || undefined);
  metti('abstract', d.abstract || undefined);
  metti('description', d.description || undefined);
  metti('url', d.url || undefined);
  metti('sameAs', (d.sameAs || []).filter((s) => String(s).trim() !== ''));
  metti('keywords', (d.keywords || []).filter(Boolean));
  metti('logo', d.logo || undefined);
  metti('image', d.image || undefined);
  metti('areaServed', d.areaServed || undefined);

  const punti = [];
  if (d.email || d.telephone) {
    punti.push({
      '@type': 'ContactPoint',
      ...(d.email ? { email: d.email } : {}),
      ...(d.telephone ? { telephone: d.telephone } : {}),
    });
  }
  metti('contactPoint', punti);

  if (d.iconName) {
    const vecchia = (e['meetoo:icon'] && typeof e['meetoo:icon'] === 'object') ? e['meetoo:icon'] : {};
    e['meetoo:icon'] = { class: vecchia.class || 'material-symbols-outlined', name: d.iconName };
  } else delete e['meetoo:icon'];

  if (d.location) {
    const vecchio = (e.location && typeof e.location === 'object') ? e.location : {};
    e.location = { ...vecchio, '@id': d.location };
  } else delete e.location;

  /* Chi gestisce: si scrive come riferimento a una persona, non come stringa
   * nuda, perché è così che `ws_gruppi_gestiti()` lo legge — e perché un @id
   * dentro un oggetto è un riferimento, mentre una stringa è solo una stringa. */
  metti('meetoo:manager', persone(d.manager));
  metti('contributor', persone(d.contributor));
  if (d.verified) e['meetoo:verified'] = true; else delete e['meetoo:verified'];

  if (dentro) { doc.mainEntity = e; doc['@id'] = e['@id']; }
  return doc;
}

/* --------------------------------------------------------------------------- */

export function daJsonLd(doc, tipo) {
  return tipo === 'gruppo' ? gruppoDaJsonLd(doc) : luogoDaJsonLd(doc);
}
export function aJsonLd(d, docOriginale, tipo) {
  return tipo === 'gruppo' ? gruppoAJsonLd(d, docOriginale) : luogoAJsonLd(d, docOriginale);
}

/** Il guscio di pagina di una scheda nuova. */
export function docVuoto(tipo) {
  return {
    '@context': ['https://schema.org', { meetoo: 'https://meetoo.eu#' }],
    '@id': '',
    '@type': 'ItemPage',
    mainEntity: { '@id': '', '@type': tipo === 'gruppo' ? 'Organization' : 'Place', name: '' },
  };
}
