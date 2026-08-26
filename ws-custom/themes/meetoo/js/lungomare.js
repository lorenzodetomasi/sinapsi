/* Il lungomare: la linea delle fermate, da Sud a Nord.
 *
 * È lo script che stava dentro `waterfront.html`, spostato qui perché ora la
 * pagina la serve il CMS. NON è stato riscritto: quella pagina funziona — le
 * fermate raggruppate per distanza, le distanze fra un gruppo e l'altro, le
 * modali, la legenda, i capolinea — e riscriverla sarebbe stato il modo più
 * sicuro di perderne dei pezzi. È già successo, con l'header.
 *
 * Tre cose sole sono cambiate, e sono segnate dove stanno:
 *   1. le briciole non le scrive più questa pagina, le stampa il server;
 *   2. da dove vengono i dati lo dice il CMS, non un percorso relativo;
 *   3. a linea pronta si accende la modalità a tutto schermo e si toglie l'elenco
 *      che il server ha scritto — che resta lì, e resta l'unica cosa che si vede,
 *      se il JavaScript non arriva o i dati non si caricano.
 */
(function () {

const carousel = document.getElementById('carousel');
if (carousel) {

/* La legenda sta in basso a destra, sopra il «+»: sono due gesti sulla stessa
 * cosa — capire che cosa si guarda, e chiedere di aggiungerci qualcosa — e stanno
 * vicini. Prima era un'azione dell'header, lontana dalla linea a cui si riferisce. */
(function legenda() {
  var b = document.getElementById('legend-fab');
  if (b) b.addEventListener('click', apriLegenda);
})();

/* Dove stanno i dati lo dice il CMS: la base dei contenuti arriva dal <meta> che
 * stampa il tema, e QUALE raccolta disegnare lo dice il contenitore. Prima era un
 * percorso relativo scritto a mano — `../../contents/…/lungomare/index.json` — che
 * funzionava solo dalla cartella del tema e legava questa pagina a un lungomare
 * solo. Così invece la stessa linea vale per qualunque percorso. */
const RACCOLTA = carousel.dataset.raccolta || 'places/lido-di-ostia/lungomare';

// --- Dati dei place contenuti (containsPlace) con cache condivisa ---
// Il JSON del lungomare tiene solo il rimando {@type,@id,name}; i dati ricchi
// (rating, tipo, descrizione, sito) stanno nell'index.json di ciascun place.
const PLACE_BASE = (window.Meetoo && Meetoo.contentBase) ? Meetoo.contentBase() : '../../contents/meetoo/it_IT/';
const jsonPath = PLACE_BASE + RACCOLTA + '/index.json';
const placeCache = new Map(); // pid -> Promise<doc|null> (deduplica le richieste)

function fetchPlace(pid) {
  if (placeCache.has(pid)) return placeCache.get(pid);
  // Il tipo va controllato, non solo lo stato: un file mancante non dà 404
  // ma la pagina del CMS con stato 200, e `r.json()` esploderebbe sull'HTML.
  const p = fetch(`${PLACE_BASE}${pid}/index.json`)
    .then(r => (r.ok && (r.headers.get('content-type') || '').includes('json')) ? r.json() : null)
    .catch(() => null);
  placeCache.set(pid, p);
  return p;
}

// Cartella dell'index.json del lungomare (per risolvere image relative del lungomare).
const LUNGOMARE_DIR = jsonPath.replace(/[^/]*$/, '');
// Dati completi delle fermate (per la modale spiaggia e la risoluzione immagini).
const beachData = {};
// Destinatario del form "richiedi inserimento": super-admin da users.xml
// (100449157359400577039 → persons/…/email). Sostituibile con un alias.
const CONTACT_EMAIL = 'lorenzo.detomasi@gmail.com';

// ===== Palette/legenda come FONTE UNICA (colore + icona + etichetta) =====
// Il colore del bordo/testata card e la Legenda derivano da qui.
const CATEGORY = {
  stabilimento: { label: 'Stabilimento in concessione', color: '#2e3192', icon: 'holiday_village', desc: 'Arenile dato in concessione a privati o associazioni, con servizi a pagamento.' },
  attrezzata:   { label: 'Spiaggia libera attrezzata',  color: '#2e3192', icon: 'deck',            desc: 'Accesso gratuito con servizi facoltativi a pagamento (chioschi, docce, wc).' },
  libera:       { label: 'Spiaggia libera',             color: '#406abf', icon: 'beach_access',    desc: 'Arenile accessibile gratuitamente a tutti, senza servizi aggiuntivi.' },
  riservata:    { label: 'Accesso riservato',           color: '#8ba5c2', icon: 'block',           desc: 'Riservata a categorie specifiche (forze armate, circoli privati).' },
  dog:          { label: 'Spiaggia per cani — BauBeach', color: '#00a99d', icon: 'pets',           desc: 'Aree, libere o attrezzate, dove è consentito l’accesso ai cani.' },
  monumento:    { label: 'Monumento storico',           color: '#00a99d', icon: 'photo_camera',    desc: 'Punto d’interesse storico o panoramico del litorale.' },
  pedonale:     { label: 'Area pedonale',               color: '#00a99d', icon: 'directions_walk', desc: 'Piazze e passeggiate esclusivamente pedonali.' },
  struttura:    { label: 'Struttura pubblica',          color: '#406abf', icon: 'account_balance', desc: 'Pontili, approdi e altre strutture pubbliche.' },
};
const STATUS = {
  chiusa: { label: 'Area chiusa', color: '#c1272d', icon: 'dangerous', desc: 'Chiusa per questioni legali o altri motivi.' },
};

// Categoria della fermata → chiave di CATEGORY (da additionalType + @type).
function categoryOf(item) {
  const at = Array.isArray(item.additionalType) ? item.additionalType : (item.additionalType ? [item.additionalType] : []);
  const ty = Array.isArray(item['@type']) ? item['@type'].join(' ') : (item['@type'] || '');
  const s = (at.join(' ') + ' ' + ty).toLowerCase();
  if (s.includes('cani') || s.includes('baubeach')) return 'dog';
  if (s.includes('riservat')) return 'riservata';
  if (s.includes('concessione') || s.includes('stabilimento')) return 'stabilimento';
  if (s.includes('attrezzat')) return 'attrezzata';
  if (s.includes('monument') || s.includes('storic') || s.includes('touristattraction') || s.includes('panoram')) return 'monumento';
  if (s.includes('pedonale') || s.includes('piazza')) return 'pedonale';
  if (s.includes('civicstructure') || s.includes('pontile') || s.includes('struttura') || s.includes('boatterminal')) return 'struttura';
  if (s.includes('libera') || s.includes('beach')) return 'libera';
  return '';
}

// Escape HTML e risoluzione path immagine (assoluto → invariato; relativo → base+rel).
function escHtml(s) { return String(s).replace(/[&<>"]/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[c])); }
function resolveImg(base, rel) { if (!rel) return ''; return /^(https?:)?\/\/|^\//.test(rel) ? rel : base + rel; }
// Item-riferimento: @id verso una cartella place/organization (senza traversal).
function isPlaceRef(id) { return /^(places\/[^/]+\/[^/]+|organizations\/[^/]+)$/.test(String(id || '')); }

// ===== Preferiti (heart): persistono in localStorage (nessun account) =====
// Preferiti, condivisione e messaggi passeggeri: li fa cards.js, per tutte le
// card del sito (stessa chiave 'meetoo:favorites' di prima: i preferiti già
// segnati restano). Qui si usano solo i suoi due ingressi.
const toast = (msg, ico) => Meetoo.toast(msg, ico);

// ===== FAB "richiedi inserimento" → form mailto (nessun invio automatico) =====
function openContact() { document.getElementById('contact-modal').classList.add('open'); }
function closeContact() { document.getElementById('contact-modal').classList.remove('open'); }
function submitContact(e) {
  e.preventDefault();
  const f = e.target, g = n => (f.elements[n] && f.elements[n].value || '').trim();
  const subject = `[MeeToo] Richiesta inserimento: ${g('luogo') || '(senza nome)'}`;
  const body = `Nome: ${g('nome')}\nEmail: ${g('email')}\nTipo: ${g('tipo')}\nLuogo/Attività: ${g('luogo')}\n\nMessaggio:\n${g('messaggio')}\n`;
  window.location.href = `mailto:${CONTACT_EMAIL}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
  closeContact(); toast('Apertura del client email…');
  return false;
}

// Aspetto (chiaro/scuro/automatico): lo gestisce l'header condiviso, che usa
// la stessa chiave 'meetoo:theme' — la preferenza già salvata resta valida.

// ===== Legenda generata dal const (fonte unica) =====
function buildLegend() {
  const el = document.getElementById('legend-list'); if (!el) return;
  const row = d => `<div class="legend-row"><span class="legend-swatch" style="background:${d.color};color:#fff"><span class="material-symbols-outlined">${d.icon}</span></span><div><strong>${escHtml(d.label)}</strong><span>${escHtml(d.desc || '')}</span></div></div>`;
  // Solo tipologie di SPIAGGIA (niente monumento/pedonale/struttura); "Accesso riservato" appena sopra "Area chiusa".
  const LEGEND_TIPI = ['stabilimento', 'attrezzata', 'libera', 'dog', 'riservata'];
  el.innerHTML = LEGEND_TIPI.map(k => row(CATEGORY[k])).join('') + row(STATUS.chiusa);
}

// ===== Modale della SPIAGGIA (fermata): dettaglio esteso + Mappa =====
function renderBeachModal(item) {
  const at = Array.isArray(item.additionalType) ? item.additionalType : (item.additionalType ? [item.additionalType] : []);
  const cats = [...translateTypes(item['@type']), ...at].filter(Boolean);
  const name = item.name || at[0] || 'Luogo';
  const rv = item.aggregateRating && item.aggregateRating.ratingValue;
  const rc = item.aggregateRating && (item.aggregateRating.reviewCount || item.aggregateRating.ratingCount);
  const a = item.address || {};
  const addr = a.streetAddress ? (a.streetAddress + (a.addressLocality ? ', ' + a.addressLocality : '')) : '';
  const cip = item.containedInPlace; const zone = (cip && cip.name) || '';
  const ex = previousOperatorName(item['meetoo:managementHistory']);
  const desc = item.description || '';
  const url = item.url || '';
  const maps = item['meetoo:_mapsLink'] || '';
  const amen = Array.isArray(item.amenityFeature) ? item.amenityFeature.filter(x => x && x.name) : [];
  let h = `<h2 class="modal-title">${escHtml(name)}</h2>`;
  if (cats.length) h += `<div class="modal-sub">${escHtml(cats.join(' · '))}</div>`;
  // Il voto rimanda alle recensioni, come nelle card: stessa pastiglia, stesso link.
  const votoHtml = pastigliaVoto(item, { esteso: true });
  if (votoHtml) h += `<div class="modal-rating">${votoHtml}</div>`;
  if (ex) h += `<p class="ex-operator" style="margin:6px 0 0"><b>Nuova gestione</b> <span>ex ${escHtml(ex)}</span></p>`;
  if (desc) h += `<p class="modal-desc">${desc}</p>`;
  if (zone) h += `<div class="modal-line"><span class="material-symbols-outlined">location_city</span> ${escHtml(zone)}</div>`;
  if (addr) h += `<div class="modal-line"><span class="material-symbols-outlined">place</span> ${escHtml(addr)}</div>`;
  if (amen.length) {
    const items = amen.map(x => { const no = x.value === false; return `<span class="amenity${no ? ' amenity-no' : ''}"><span class="material-symbols-outlined">${iconForAmenity(x.name)}</span>${escHtml(x.name)}</span>`; }).join('');
    h += `<div class="modal-services"><div class="modal-services-label">Servizi</div><div class="modal-amenities">${items}</div></div>`;
  }
  const acts = [];
  if (maps) acts.push(`<a class="btn" href="${maps}" target="_blank" rel="noopener"><span class="material-symbols-outlined">map</span> Mappa</a>`);
  if (url) acts.push(`<a class="btn" href="${escHtml(url)}" target="_blank" rel="noopener"><span class="material-symbols-outlined">public</span> Sito</a>`);
  if (acts.length) h += `<div class="modal-actions">${acts.join('')}</div>`;
  return h;
}
function openBeachModal(id) {
  const item = beachData[id];
  if (!item) { openPlaceModal(id); return; }
  const modal = document.getElementById('place-modal');
  document.getElementById('place-modal-body').innerHTML = renderBeachModal(item);
  modal.classList.add('open');
  requestAnimationFrame(() => updateScrollHint(document.querySelector('#place-modal .modal-scroll')));
}

// Risolve/carica l'immagine della card (image lungomare → satelliteView place → 1ª attività).
function fillMedia(mediaEl) {
  const id = mediaEl.dataset.mediaId;
  if (!id || mediaEl.dataset.filled) return;
  mediaEl.dataset.filled = '1';
  const item = beachData[id]; if (!item) return;
  const setImg = (src, credit) => {
    if (!src) return;
    const img = new Image();
    img.onload = () => {
      mediaEl.insertBefore(img, mediaEl.firstChild);
      const c = mediaEl.querySelector('.credit');
      if (c && credit) c.textContent = credit;
      updateScrollHint(mediaEl.closest('.wf-scroll'));
    };
    img.src = src;
  };
  if (item.image) { setImg(resolveImg(LUNGOMARE_DIR, item.image), item['meetoo:imageCredit'] || ''); return; }
  const pid = item['@id'];
  if (pid && isPlaceRef(pid)) {
    fetchPlace(pid).then(doc => {
      const pe = doc && (doc.mainEntity || doc);
      if (pe && (pe['meetoo:satelliteView'] || pe.image)) {
        setImg(resolveImg(PLACE_BASE + pid + '/', pe['meetoo:satelliteView'] || pe.image), pe['meetoo:satelliteCredit'] || '');
        return;
      }
      const cp = item.containsPlace, first = Array.isArray(cp) ? cp[0] : cp, fid = first && first['@id'];
      if (fid) fetchPlace(fid).then(d2 => {
        const p2 = d2 && (d2.mainEntity || d2);
        if (p2 && (p2['meetoo:satelliteView'] || p2.image))
          setImg(resolveImg(PLACE_BASE + fid + '/', p2['meetoo:satelliteView'] || p2.image), p2['meetoo:satelliteCredit'] || '');
      });
    });
  }
}

// --- Traduzioni e icone (schema.org @type → IT + Material Icons) ---
const TYPE_LABELS = {
  Beach: 'Spiaggia', Restaurant: 'Ristorante', Park: 'Parco', Playground: 'Area giochi',
  BoatTerminal: 'Approdo', NGO: 'No-profit', TouristAttraction: 'Attrazione turistica',
  CivicStructure: 'Struttura pubblica',
  // Tipi che l'importazione da Google assegna davvero (vedi TYPE_MAP in
  // googleMaps.js): senza etichetta finirebbero a schermo in inglese.
  SportsClub: 'Circolo sportivo', BarOrPub: 'Bar', CafeOrCoffeeShop: 'Caffè',
  NightClub: 'Locale notturno', Museum: 'Museo', Library: 'Biblioteca',
  BookStore: 'Libreria', Store: 'Negozio', LodgingBusiness: 'Struttura ricettiva',
  MovieTheater: 'Cinema', StadiumOrArena: 'Impianto sportivo', School: 'Scuola',
  CollegeOrUniversity: 'Università', Church: 'Chiesa', PlaceOfWorship: 'Luogo di culto',
  GovernmentBuilding: 'Edificio pubblico', CityHall: 'Municipio'
};
// Tipi generici che non mostriamo (es. LocalBusiness).
const HIDDEN_TYPES = new Set(['LocalBusiness', 'Place', 'Organization', 'Thing', 'ItemList']);

function translateTypes(t) {
  const arr = Array.isArray(t) ? t : (t ? [t] : []);
  return arr.filter(x => x && !HIDDEN_TYPES.has(x)).map(x => TYPE_LABELS[x] || x);
}

const RestrictedDiet = [
  "DiabeticDiet",
  "GlutenFreeDiet",
  "HalalDiet",
  "HinduDiet",
  "KosherDiet",
  "LowCalorieDiet",
  "LowFatDiet",
  "LowLactoseDiet",
  "LowSaltDiet",
  "VeganDiet",
  "VegetarianDiet"
];
const servesCuisine = [
  "Pizza",
  "Meat",
  "Fish"
];
const FoodEstablishment = [
  "Bakery",
  "BarOrPub",
  "Brewery",
  "CafeOrCoffeeShop",
  "Distillery",
  "FastFoodRestaurant",
  "IceCreamShop",
  "Restaurant",
  "Winery"
];

// Icona Material per una categoria (tipo tradotto o additionalType, in IT).
function iconForCategory(label) {
  const s = String(label).toLowerCase();
  if (s.includes('spiaggia')) return 'beach_access';
  if (s.includes('ristorante')) return 'restaurant';
  if (s.includes('piscina')) return 'pool';
  if (s.includes('parco') || s.includes('naturale') || s.includes('riserva')) return 'park';
  if (s.includes('pontile') || s.includes('approdo') || s.includes('barche')) return 'directions_boat';
  if (s.includes('pedonale')) return 'directions_walk';
  if (s.includes('fontana')) return 'water_drop';
  if (s.includes('panoramico') || s.includes('borgo') || s.includes('attrazione')) return 'photo_camera';
  if (s.includes('ludico') || s.includes('giochi')) return 'attractions';
  if (s.includes('formazione')) return 'school';
  if (s.includes('associazione') || s.includes('no-profit')) return 'volunteer_activism';
  if (s.includes('struttura pubblica')) return 'account_balance';
  return '';
}

// Icona Material per un servizio (amenityFeature, nome in IT).
function iconForAmenity(name) {
  const s = String(name).toLowerCase();
  if (s.includes('parcheggio')) return 'local_parking';
  if (s.includes('bagn')) return 'wc';
  if (s.includes('bambin')) return 'child_friendly';
  if (s.includes('gruppi')) return 'groups';
  if (s.includes('contactless')) return 'contactless';
  if (s.includes('carte') || s.includes('credito') || s.includes('debito') || s.includes('pagament')) return 'credit_card';
  if (s.includes('wi-fi') || s.includes('wifi')) return 'wifi';
  if (s.includes('cani') || s.includes('animal')) return 'pets';
  if (s.includes('asporto')) return 'takeout_dining';
  if (s.includes('consumo')) return 'restaurant';
  if (s.includes('prenot')) return 'event_available';
  if (s.includes('colazione')) return 'free_breakfast';
  if (s.includes('pranzo')) return 'lunch_dining';
  if (s.includes('cena')) return 'dinner_dining';
  if (s.includes('caff')) return 'local_cafe';
  if (s.includes('birra')) return 'sports_bar';
  if (s.includes('vino')) return 'wine_bar';
  if (s.includes('cocktail')) return 'local_bar';
  if (s.includes('dolci')) return 'cake';
  if (s.includes('aperto')) return 'deck';
  return 'check_circle';
}

// A. TIPO (schema.org @type) → icona. Beach = ombrellone.
/* Dotazioni che non distinguono un'attività da un'altra: come si paga lo fanno
 * quasi tutti allo stesso modo (carte e contactless sono le tre voci più
 * frequenti dell'intero lungomare). Restano nel dettaglio, non nella riga
 * essenziale, che deve dire che cosa rende QUESTO posto diverso.
 * Elenco esplicito e non un'euristica: «Parcheggio a pagamento» contiene la
 * parola pagamento ma è una dotazione vera. */
const AMENITIES_PAGAMENTO = new Set(['Carte di credito', 'Carte di debito', 'Pagamento contactless', 'Contanti', 'Buoni pasto']);
const amenitiesCaratterizzanti = (lista) =>
  (Array.isArray(lista) ? lista : []).filter(x => x && x.name && x.value !== false && !AMENITIES_PAGAMENTO.has(x.name));

/* Le parole che distinguono il luogo, in ordine di forza:
 * la tipologia del Comune (additionalType), poi il tipo schema.org tradotto,
 * poi la cucina servita. I tipi generici (LocalBusiness, Place…) restano fuori:
 * «Attività locale» non distingue nulla. Quando non resta niente da dire la
 * riga non compare — ed è il segnale che a quel luogo mancano i dati. */
function paroleDelLuogo(e) {
  const out = [];
  const add = Array.isArray(e.additionalType) ? e.additionalType : (e.additionalType ? [e.additionalType] : []);
  add.filter(Boolean).forEach(x => out.push(String(x)));
  const tipi = (Array.isArray(e['@type']) ? e['@type'] : (e['@type'] ? [e['@type']] : []))
    .filter(x => x && !HIDDEN_TYPES.has(x))
    .map(x => TYPE_LABELS[x])            // senza etichetta italiana non si mostra
    .filter(x => x && !out.includes(x));
  // «Attività locale» (LocalBusiness) entra solo come ultima risorsa.
  out.push(...tipi);
  const cucina = Array.isArray(e.servesCuisine) ? e.servesCuisine : (e.servesCuisine ? [e.servesCuisine] : []);
  cucina.filter(Boolean).forEach(x => out.push(String(x)));
  return out;
}

/* Riga 2 di un luogo: parole + icone delle dotazioni, che scorrono; il voto
 * resta fuori dallo scorrimento, ancorato a destra. `parole: false` per il
 * contenitore, che tipo e tipologia li ha già scritti nella barra colorata. */
/* Una riga che non ha niente da dire non si mostra: meglio lo spazio bianco
 * di una fila di segnaposto. (Ed è visibile a colpo d'occhio quali luoghi
 * aspettano ancora i dati.) */
function nascondiSeVuota(riga) {
  if (!riga) return;
  const vuota = !riga.querySelector('.trait-type, .trait-amen, .rating-pill');
  riga.hidden = vuota;
}

function rigaCaratteristiche(e, opzioni) {
  const conParole = !opzioni || opzioni.parole !== false;
  const parole = conParole ? paroleDelLuogo(e) : [];
  const amen = amenitiesCaratterizzanti(e.amenityFeature);
  return parole.map(x => `<span class="trait-type">${escHtml(x)}</span>`).join('') +
    amen.map(x => `<span class="trait-amen material-symbols-outlined" title="${String(x.name).replace(/"/g, '&quot;')}">${iconForAmenity(x.name)}</span>`).join('');
}

/* Recensioni su Google. Il pulsante «scrivi una recensione» apre la scheda del
 * luogo con la valutazione in evidenza; senza Place ID non si può puntare a
 * una scheda precisa e si ripiega su una ricerca per nome. */
const URL_RECENSIONI = 'https://search.google.com/local/writereview?placeid=';

/* La pastiglia del voto, IDENTICA ovunque compaia — riga della card, blocco
 * di un'attività, modale — e sempre cliccabile: il voto senza le recensioni
 * è un numero che non si può verificare.
 * `esteso` scrive «(128 recensioni)» invece di «(128)»: nelle modali c'è lo
 * spazio, nella riga di una card no. */
function pastigliaVoto(e, opzioni) {
  const r = (e && e.aggregateRating) || null;
  const rv = r && r.ratingValue;
  if (!rv) return '';
  const rc = r.reviewCount || r.ratingCount || 0;
  const conteggio = rc ? (opzioni && opzioni.esteso ? ` (${escHtml(rc)} recensioni)` : ` (${escHtml(rc)})`) : '';
  const gid = e['meetoo:google_place_id'] || e['meetoo:googlePlaceId'] || '';
  const href = gid
    ? URL_RECENSIONI + encodeURIComponent(gid)
    : `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(e.name || '')}`;
  return `<a class="rating-pill" href="${href}" target="_blank" rel="noopener" title="Recensioni su Google Maps" onclick="event.stopPropagation()">` +
    `<span class="material-symbols-outlined">star</span>${escHtml(rv)}${conteggio}</a>`;
}

function iconForType(t) {
  const arr = Array.isArray(t) ? t : (t ? [t] : []);
  const map = {
    Beach: 'beach_access', Park: 'park', Playground: 'attractions',
    BoatTerminal: 'directions_boat', Restaurant: 'restaurant', NGO: 'volunteer_activism',
    TouristAttraction: 'photo_camera', CivicStructure: 'account_balance'
  };
  for (const x of arr) if (map[x]) return map[x];
  return 'place';
}

// B. TIPOLOGIA = prima voce di additionalType (classificazione Comune),
// mostrata verbatim. Il colore deriva dalla categoria riconosciuta.
function tipoClass(label) {
  const s = String(label).toLowerCase();
  if (s.includes('cani')) return 'tip-dog';
  if (s.includes('riservat')) return 'tip-riservato';
  if (s.includes('concessione') || s.includes('stabilimento')) return 'tip-concessione';
  if (s.includes('attrezzat')) return 'tip-attrezzata';
  if (s.includes('libera')) return 'tip-libera';
  return 'cat-neutral'; // non-beach o non classificato
}
// Icona Material per la tipologia della spiaggia.
function iconForTipologia(label) {
  const s = String(label).toLowerCase();
  if (s.includes('attrezzat')) return 'deck';
  if (s.includes('concessione') || s.includes('stabilimento')) return 'holiday_village';
  if (s.includes('riservat')) return 'block';
  if (s.includes('cani')) return 'pets';
  if (s.includes('libera')) return 'beach_access';
  return ''; // non-beach
}

// C. STATO LEGALE: "chiusa" è l'unica eccezione mostrata come badge.
function legalStatusOf(item) {
  const s = String(item['meetoo:legalStatus'] || '').toLowerCase();
  if (s.includes('chius') || s.includes('closed')) return 'chiusa';
  return ''; // aperta / non specificato → nessun badge
}

// Nome dello stabilimento precedente (meetoo:managementHistory): operatore con
// endDate più alta (a parità, l'ultimo elencato). Preferisce alternateName.
function previousOperatorName(mh) {
  if (!Array.isArray(mh) || !mh.length) return '';
  let best = null, bestYear = -Infinity;
  mh.forEach(role => {
    const ed = role && role.endDate;
    const y = ed ? parseInt((String(ed).match(/\d{4}/) || [])[0], 10) : NaN;
    const key = isNaN(y) ? -1 : y;
    if (key >= bestYear) { bestYear = key; best = role; }
  });
  const op = best && best['meetoo:operator'];
  return op ? (op.alternateName || op.name || '') : '';
}

// Indicatore di scroll: mostra l'hint sul contenitore (.wf-card/.modal) solo se
// c'è ancora contenuto sotto la parte visibile dello scroller.
function updateScrollHint(scrollEl) {
  if (!scrollEl) return;
  const host = scrollEl.closest('.wf-card, .modal');
  if (!host) return;
  const more = (scrollEl.scrollHeight - scrollEl.clientHeight - scrollEl.scrollTop) > 4;
  host.classList.toggle('scroll-more', more);
}
// Un solo listener in cattura: intercetta lo scroll di qualsiasi scroller (lo
// scroll non fa bubbling, quindi serve la fase di capture).
document.addEventListener('scroll', (e) => {
  const sc = e.target && e.target.closest && e.target.closest('.wf-scroll, .modal-scroll');
  if (sc) updateScrollHint(sc);
}, true);
window.addEventListener('resize', () =>
  document.querySelectorAll('.wf-scroll, .modal-scroll').forEach(updateScrollHint));

// Centra un wrapper con uno scrollTo CALCOLATO: scrollIntoView({inline:'center'})
// con scroll-snap mandatory non aggancia i wrapper stretti tra wrapper larghi.
function centerWrapper(w, smooth) {
  if (!w) return;
  const left = w.offsetLeft + w.offsetWidth / 2 - carousel.clientWidth / 2;
  // 'instant' (non 'auto') per NON ereditare scroll-behavior:smooth del CSS:
  // su distanze lunghe lo smooth + snap-mandatory si blocca a metà.
  carousel.scrollTo({ left, behavior: smooth ? 'smooth' : 'instant' });
}

// Riempie la riga attività con il rating (le altre info sono nel modale).
function fillBiz(bizEl) {
  const pid = bizEl.dataset.placeId;
  if (!pid || bizEl.dataset.filled) return;
  bizEl.dataset.filled = '1';
  fetchPlace(pid).then(doc => {
    if (!doc) return; // place non ancora creato: resta il solo nome
    const pe = doc.mainEntity || doc;
    // Nome mancante (era solo un @id): completalo dal place.
    if (bizEl.dataset.nameMissing && pe.name) {
      const nameEl = bizEl.querySelector('.place-name');
      if (nameEl) nameEl.textContent = pe.name;
    }
    const ratingSlot = bizEl.querySelector('.rating-slot');
    if (ratingSlot) ratingSlot.innerHTML = pastigliaVoto(pe);
    // Riga essenziale: le parole che distinguono l'attività + le dotazioni
    // che la caratterizzano (i modi di pagamento restano nel dettaglio).
    const strip = bizEl.querySelector('.traits-scroll');
    if (strip) strip.innerHTML = rigaCaratteristiche(pe);
    nascondiSeVuota(bizEl.querySelector('.place-traits'));
    updateScrollHint(bizEl.closest('.wf-scroll'));
  });
}

// Carica il nome del containedInPlace (quando è solo un @id) dal place.
function fillContainedIn(el) {
  const pid = el.dataset.cip;
  if (!pid || el.dataset.filled) return;
  el.dataset.filled = '1';
  fetchPlace(pid).then(doc => {
    if (!doc) return;
    const pe = doc.mainEntity || doc;
    const nameEl = el.querySelector('.cip-name');
    if (nameEl && pe.name) nameEl.textContent = pe.name;
    updateScrollHint(el.closest('.wf-scroll'));
  });
}

// Carica le attività della fermata corrente e delle due adiacenti (i 3 a video).
function loadWindow(wrapper) {
  [wrapper.previousElementSibling, wrapper, wrapper.nextElementSibling].forEach(w => {
    if (w && w.classList && w.classList.contains('station-wrapper')) {
      w.querySelectorAll('.place-block[data-place-id]').forEach(fillBiz);
      w.querySelectorAll('[data-cip]').forEach(fillContainedIn);
      w.querySelectorAll('.place-media[data-media-id]').forEach(fillMedia);
    }
  });
}

// Scheda dettagli in sovrimpressione per un'attività.
function openPlaceModal(pid) {
  const modal = document.getElementById('place-modal');
  const body = document.getElementById('place-modal-body');
  body.innerHTML = '<div class="modal-loading">Caricamento…</div>';
  modal.classList.add('open');
  fetchPlace(pid).then(doc => {
    const pe = doc && (doc.mainEntity || doc);
    if (!pe) { body.innerHTML = '<div class="modal-loading">Dettagli non ancora disponibili per questo luogo.</div>'; return; }
    const esc = s => String(s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
    const name = pe.name || pid.split('/').pop();
    // Tipi: tradotti in IT, generici (LocalBusiness…) esclusi, più additionalType.
    const addTypeArr = Array.isArray(pe.additionalType) ? pe.additionalType : (pe.additionalType ? [pe.additionalType] : []);
    const cats = [...translateTypes(pe['@type']), ...addTypeArr].filter(Boolean);
    const desc = pe.description || '';
    const rv = pe.aggregateRating && pe.aggregateRating.ratingValue;
    const rc = pe.aggregateRating && (pe.aggregateRating.reviewCount || pe.aggregateRating.ratingCount);
    const a = pe.address || {};
    const addr = a.streetAddress ? (a.streetAddress + (a.addressLocality ? ', ' + a.addressLocality : '')) : '';
    const url = pe.url || '';
    const amen = Array.isArray(pe.amenityFeature)
      ? pe.amenityFeature.filter(x => x && x.name) : [];

    let html = `<h2 class="modal-title">${esc(name)}</h2>`;
    if (cats.length) html += `<div class="modal-sub">${esc(cats.join(' · '))}</div>`;
    const votoHtml = pastigliaVoto(pe, { esteso: true });
    if (votoHtml) html += `<div class="modal-rating">${votoHtml}</div>`;
    if (desc) html += `<p class="modal-desc">${esc(desc)}</p>`;
    if (addr) html += `<div class="modal-line"><span class="material-symbols-outlined">place</span> ${esc(addr)}</div>`;
    if (amen.length) {
      // Servizi: semplice icona + testo (niente pill/spunta); il non disponibile è barrato.
      const items = amen.map(x => {
        const no = x.value === false;
        return `<span class="amenity${no ? ' amenity-no' : ''}"><span class="material-symbols-outlined">${iconForAmenity(x.name)}</span>${esc(x.name)}</span>`;
      }).join('');
      html += `<div class="modal-services"><div class="modal-services-label">Servizi</div><div class="modal-amenities">${items}</div></div>`;
    }
    if (url) {
      html += `<div class="modal-actions"><a class="btn" href="${esc(url)}" target="_blank" rel="noopener"><span class="material-symbols-outlined">public</span> Sito</a></div>`;
    }
    body.innerHTML = html;
    updateScrollHint(document.querySelector('#place-modal .modal-scroll'));
  });
}
function closePlaceModal() { document.getElementById('place-modal').classList.remove('open'); }

// Legenda delle tipologie (header)
function closeLegend() { document.getElementById('legend-modal').classList.remove('open'); }
// La apre il pulsante messo nell'header condiviso (vedi in cima allo script).
function apriLegenda() {
  document.getElementById('legend-modal').classList.add('open');
  requestAnimationFrame(() => updateScrollHint(document.querySelector('#legend-modal .modal-scroll')));
}
buildLegend();                                    // legenda dal const (fonte unica)
document.getElementById('add-place-fab').addEventListener('click', openContact);

document.addEventListener('keydown', e => { if (e.key === 'Escape') { closePlaceModal(); closeLegend(); closeContact(); } });

async function initApp() {
  try {
    const response = await fetch(jsonPath);
    if (!response.ok) throw new Error("Errore nel caricamento del JSON");

    const schemaData = await response.json();

    const scriptTag = document.createElement('script');
    scriptTag.type = 'application/ld+json';
    scriptTag.text = JSON.stringify(schemaData);
    document.head.appendChild(scriptTag);

    carousel.innerHTML = '';
    let pontileElement = null;

    // Raggruppamento degli item per distanza in metri da sud.
    // itemListElement può stare al primo livello (vecchio formato) o dentro
    // mainEntity (envelope ItemPage, come gli altri file del CMS).
    const itemList = (schemaData.mainEntity && schemaData.mainEntity.itemListElement)
                     || schemaData.itemListElement || [];

    // Item-riferimento: se un item ha SOLO un @id (a una cartella place/organization)
    // e nessun dato proprio (name), carica il place dalla relativa cartella e ne
    // fonde i campi mancanti (i campi già presenti sull'item vincono).
    const refItems = itemList
      .map(el => el && el.item)
      .filter(it => it && isPlaceRef(it['@id']) && !it.name);
    if (refItems.length) {
      await Promise.all(refItems.map(it => fetchPlace(it['@id']).then(doc => {
        if (!doc) return; // cartella assente: resta il fallback dal nome dell'@id
        const pe = doc.mainEntity || doc;
        for (const k in pe) if (!(k in it)) it[k] = pe[k];
      })));
    }

    // I capolinea (@type City, coastalPosition "all") NON entrano nel raggruppamento
    // per distanza: vanno come card speciali agli estremi della linea.
    const termini = [];
    const groupsMap = new Map();
    itemList.forEach((element) => {
      const item = element.item;
      const types = Array.isArray(item['@type']) ? item['@type'] : [item['@type']];
      if (types.includes('City') || item['meetoo:coastalPosition'] === 'all') { termini.push(item); return; }
      const dist = item["meetoo:m_from_border_south"] !== undefined && item["meetoo:m_from_border_south"] !== null
                   ? item["meetoo:m_from_border_south"]
                   : 0;

      if (!groupsMap.has(dist)) {
        groupsMap.set(dist, { distance: dist, items: [] });
      }
      groupsMap.get(dist).items.push(item);
    });

    const sortedGroups = Array.from(groupsMap.values()).sort((a, b) => a.distance - b.distance);

    let prevDistanceGapPx = 0; // Traccia la distanza in pixel del blocco precedente per calcolare la mezzeria esatta

    sortedGroups.forEach((group, index) => {
      const currentDistance = group.distance;

      let prevDistance = index > 0 ? sortedGroups[index - 1].distance : 0;
      let distanceDiff = Math.abs(currentDistance - prevDistance);

      let diffLabel = "";
      if (index > 0) {
        // Rimosso il "+" e mantenuto solo il valore
        if (distanceDiff >= 1000) diffLabel = `${(distanceDiff / 1000).toFixed(1)} km`;
        else diffLabel = `${distanceDiff} m`;
      }

      let topCardHtml = '';
      let bottomCardHtml = '';
      let mainItemForId = group.items[0];
      let groupAddr = '';                                   // → metro-line (centro)
      let wfZoneName = '', wfZoneId = '', stZoneName = '', stZoneId = ''; // → binari esterni
      let topCatColor = '', bottomCatColor = '';            // → colore tab connettore

      group.items.forEach(item => {
        // --- Identità della SPIAGGIA (fermata) ---
        let displayName = item.name;
        if (!displayName && item.address && item.address.streetAddress) displayName = item.address.streetAddress;
        if (!displayName && item["@id"]) {
          let idParts = item["@id"].split('/');
          let rawName = idParts[idParts.length - 1].replace(/[-_]/g, ' ');
          displayName = rawName.charAt(0).toUpperCase() + rawName.slice(1);
        }
        if (!displayName) displayName = "Fermata";

        // ---- Categoria / colore / icona (fonte: CATEGORY / STATUS) ----
        const cat = categoryOf(item);
        const catDef = CATEGORY[cat] || { label: (translateTypes(item['@type'])[0] || 'Luogo'), color: '#455a64', icon: 'place' };
        const closed = legalStatusOf(item) === 'chiusa';
        const headColor = closed ? STATUS.chiusa.color : catDef.color;
        const headIcon  = closed ? STATUS.chiusa.icon  : catDef.icon;
        const addTypeArr = Array.isArray(item.additionalType) ? item.additionalType : (item.additionalType ? [item.additionalType] : []);
        let headLabel = addTypeArr[0] || catDef.label;
        if (/concessione/i.test(headLabel) && !/stabilimento/i.test(headLabel)) headLabel = 'Stabilimento in concessione';

        // Attività contenute (containsPlace: 0, 1 o più)
        const cpRaw = item.containsPlace;
        const businesses = cpRaw ? (Array.isArray(cpRaw) ? cpRaw : [cpRaw]) : [];

        // Nome: se manca name E non ci sono attività → "ex {gestione storica}".
        const exName = previousOperatorName(item['meetoo:managementHistory']);
        let title = displayName;
        if (!item.name && businesses.length === 0 && exName) title = 'ex ' + exName;
        // "Nuova gestione ex …" solo quando esiste un name proprio (niente doppione col titolo).
        const exHtml = (exName && item.name) ? `<div class="ex-operator"><b>Nuova gestione</b> <span>ex ${escHtml(exName)}</span></div>` : '';

        const descRaw = item.description || '';
        const descHtml = descRaw ? `<div class="description">${descRaw}</div>` : '';

        // Indirizzo (metro-line, centro) raccolto a livello di gruppo.
        if (!groupAddr && item.address && item.address.streetAddress) groupAddr = item.address.streetAddress;

        // Link a Google Maps
        const locality = (item.address && item.address.addressLocality) || '';
        const regionRaw = (item.address && item.address.addressRegion) || '';
        const region = Array.isArray(regionRaw) ? regionRaw.join(' ') : regionRaw;
        const mapsQuery = locality ? `${title}, ${locality} ${region}` : title;
        const mapsLink = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(mapsQuery).replace(/%20/g, '+')}`;

        // Rating della fermata (formato mockup: ★ 4.6 (226))
        const rObj = item.aggregateRating || {};
        const rv = rObj.ratingValue, rc = rObj.reviewCount || rObj.ratingCount;
        const ratingPill = pastigliaVoto(item);

        // Icona sottotipo (nel corpo, a sx della riga azione) dal @type schema.org
        const subIcon = iconForType(item['@type']);

        // Azione: Info (apre la modale, con la Mappa dentro) se c'è dettaglio extra,
        // altrimenti Mappa diretta.
        const hasInfo = businesses.length > 0 || closed || descRaw.length > 110
          || (Array.isArray(item.amenityFeature) && item.amenityFeature.length)
          || (Array.isArray(item['meetoo:managementHistory']) && item['meetoo:managementHistory'].length)
          || !!item.url;
        const idAttr = item['@id'] || '';
        if (idAttr) { beachData[idAttr] = item; item['meetoo:_mapsLink'] = mapsLink; }
        const actionBtn = hasInfo
          ? `<button class="card-act primary icon-only" title="Dettagli" onclick="event.stopPropagation(); openBeachModal('${idAttr}')"><span class="material-symbols-outlined">info</span></button>`
          : `<a class="card-act primary icon-only" href="${mapsLink}" title="Apri in Google Maps" target="_blank" rel="noopener" onclick="event.stopPropagation()"><span class="material-symbols-outlined">map</span></a>`;

        // Righe attività (rating + info riempiti in lazyload)
        let containedHtml = '';
        if (businesses.length) {
          const rows = businesses.map(b => {
            const bid = b["@id"] || '';
            let bname = b.name;
            const nameMissing = !bname;
            if (!bname && bid) { const s = bid.split('/').pop().replace(/[-_]/g, ' '); bname = s.charAt(0).toUpperCase() + s.slice(1); }
            // Stesso blocco del contenitore: riga nome+dettagli, riga
            // caratteristiche+voto. Le due righe si riempiono al volo quando
            // la card entra in vista (fillBiz), leggendo il file del luogo.
            return `
                <div class="place-block" data-place-id="${bid}"${nameMissing ? ' data-name-missing="1"' : ''}>
                  <div class="place-row">
                    <span class="place-name">${escHtml(bname || 'Attività')}</span>
                    <button class="card-act primary icon-only" data-place-id="${bid}" title="Dettagli"
                            onclick="event.stopPropagation(); openPlaceModal(this.dataset.placeId)">
                      <span class="material-symbols-outlined">info</span>
                    </button>
                  </div>
                  <div class="place-traits">
                    <div class="traits-scroll"></div>
                    <span class="rating-slot"></span>
                  </div>
                </div>`;
          }).join('');
          // Niente separatore in testa: ogni blocco porta già il proprio filo.
          containedHtml = `<div class="contained">${rows}</div>`;
        }

        // Footer immagine (cover/satellite in lazyload) con share + heart
        // Immagine con SOLO il credit; share/like stanno nel piede fisso della card.
        const mediaHtml = `
          <div class="place-media" data-media-id="${idAttr}">
            <div class="media-bar"><span class="credit"></span></div>
          </div>`;
        // Piede fisso: freccia scroll (centro, condizionale) + share/like (dx, sempre).
        // Condividi + «mi interessa»: componente condiviso (cards.js), lo stesso
        // delle card di eventi e luoghi. Qui vive dentro il piede fisso della
        // card quadrata, sopra la velatura.
        const footHtml = `
          <div class="wf-foot">
            <span class="scroll-arrow"><span class="material-symbols-outlined">keyboard_arrow_down</span></span>
            ${Meetoo.social({ kind: 'place', id: idAttr, url: '#' + idAttr })}
          </div>`;

        // Header FISSO = categoria + Name; tutto il resto (azioni, attività, immagine) scorre.
        const cardContent = `
          <div class="wf-card"${idAttr ? ` id="${idAttr}"` : ''} style="--cat:${headColor}">
            <div class="wf-head">
              <span class="wf-head-label">${escHtml(headLabel)}</span>
              <span class="wf-head-icon"><span class="material-symbols-outlined">${headIcon}</span></span>
            </div>
            <div class="place-row wf-name">
              <h3 class="place-name">${escHtml(title)}</h3>
              ${actionBtn}
            </div>
            <div class="wf-body">
              <div class="wf-scroll">
                <div class="place-traits"${(rigaCaratteristiche(item, { parole: false }) || ratingPill) ? '' : ' hidden'}>
                  <div class="traits-scroll">${rigaCaratteristiche(item, { parole: false })}</div>
                  ${ratingPill}
                </div>
                ${exHtml}
                ${descHtml}
                ${containedHtml}
                ${mediaHtml}
              </div>
              ${footHtml}
            </div>
          </div>
        `;

        const cipN = (item.containedInPlace && item.containedInPlace.name) || '';
        const cipI = (item.containedInPlace && item.containedInPlace['@id']) || '';
        if (item["meetoo:coastalPosition"] === "waterfrontStreet") {
          bottomCardHtml = cardContent; bottomCatColor = headColor;
          stZoneName = stZoneName || cipN; stZoneId = stZoneId || cipI;
        } else {
          topCardHtml = cardContent; topCatColor = headColor;
          wfZoneName = wfZoneName || cipN; wfZoneId = wfZoneId || cipI;
        }

        if (item["@id"] && item["@id"].includes("pontile")) {
          mainItemForId = item;
        }
      });

      const baseWidth = 33.33; 
      const distanceGapPx = distanceDiff * 0.1; 

      const wrapper = document.createElement('div');
      wrapper.className = 'station-wrapper';
      wrapper.dataset.station = mainItemForId["@id"] || ''; // id vero è sulle card
      // Larghezza da --base-vw (responsive: più grande su mobile) + scarto distanza.
      wrapper.style.width = `calc(var(--base-vw, ${baseWidth}) * 1vw + ${distanceGapPx}px)`;

      // Calcolo della posizione esattamente a metà tra i due nodi.
      // Ogni nodo è situato al 50% della propria card.
      // Spostando l'etichetta al 50% e decurtando metà della larghezza base e lo scarto proporzionale, troviamo il punto mediano geometrico perfetto.
      // Distanza dalla fermata precedente, posizionata al MIDPOINT tra i due punti
      // (i dot sono centrati sulle card): 50% − metà larghezza base − ¼ degli scarti.
      let distHtml = '';
      if (index > 0 && diffLabel) {
        const offsetPx = (distanceGapPx + prevDistanceGapPx) / 4;
        distHtml = `<span class="line-dist" style="left: calc(50% - var(--base-vw, 33.33) * 0.5vw - ${offsetPx}px)">${diffLabel}</span>`;
      }

      // Indirizzo al centro della metro-line (visibile solo sul focus, via CSS).
      const addrHtml = groupAddr
        ? `<div class="line-address" title="${escHtml(groupAddr)}"><span class="material-symbols-outlined">place</span><span class="addr">${escHtml(groupAddr)}</span></div>` : '';

      // Zone dei binari (containedInPlace). DEDUP se identiche → tengo solo quella lato strada.
      const sameZone = (!!wfZoneName && wfZoneName === stZoneName) || (!!wfZoneId && wfZoneId === stZoneId);
      const zoneEl = (name, id) => {
        if (!name && !(id && isPlaceRef(id))) return '';
        const attr = (!name && id && isPlaceRef(id)) ? ` data-cip="${id}"` : '';
        return `<span class="zone"${attr}><span class="cip-name">${escHtml(name || '…')}</span></span>`;
      };
      const wfZoneHtml = sameZone ? '' : zoneEl(wfZoneName, wfZoneId);
      const stZoneHtml = zoneEl(stZoneName, stZoneId);

      const upTab = topCardHtml ? `<span class="tab"></span>` : '';
      const downTab = bottomCardHtml ? `<span class="tab"></span>` : '';

      wrapper.innerHTML = `
        <div class="wf-slot slot-top">${topCardHtml}</div>
        <div class="tab-row tab-top"${topCatColor ? ` style="--tab:${topCatColor}"` : ''}>${upTab}</div>
        <div class="rail rail-waterfront">${wfZoneHtml}</div>
        <div class="line-gap"></div>
        <div class="line-track">
          <span class="node"></span>
          ${distHtml}${addrHtml}
        </div>
        <div class="line-gap"></div>
        <div class="rail rail-street">${stZoneHtml}</div>
        <div class="tab-row tab-bottom"${bottomCatColor ? ` style="--tab:${bottomCatColor}"` : ''}>${downTab}</div>
        <div class="wf-slot slot-bottom">${bottomCardHtml}</div>
      `;

      wrapper.addEventListener('click', () => {
        centerWrapper(wrapper, false);
        // Aggiorna l'URL con /#<@id> (link condivisibile), senza far saltare la pagina.
        if (wrapper.dataset.station) history.replaceState(null, '', '#' + wrapper.dataset.station);
      });

      carousel.appendChild(wrapper);

      if (mainItemForId["@id"] && mainItemForId["@id"].includes("pontile")) {
        pontileElement = wrapper;
      }

      prevDistanceGapPx = distanceGapPx; // Salva la distanza per l'iterazione successiva
    });

    // --- Capolinea (City) agli estremi: card centrata verticalmente + freccia ---
    function buildTerminus(item, side) {
      const w = document.createElement('div');
      w.className = 'terminus-wrapper ' + (side === 'start' ? 'term-start' : 'term-end');
      w.innerHTML = `
        <div class="wf-terminus">
          <div class="terminus-head">
            <span class="t-label">Confine</span>
            <span class="t-sq"><span class="material-symbols-outlined">location_city</span></span>
          </div>
          <h3 class="t-name">${escHtml(item.name || 'Confine')}</h3>
          <span class="term-marker"><span class="material-symbols-outlined">stop</span></span>
        </div>`;
      return w;
    }
    if (termini.length) {
      // primo City = capolinea sud (sinistra), ultimo = nord (destra)
      carousel.insertBefore(buildTerminus(termini[0], 'start'), carousel.firstChild);
      carousel.appendChild(buildTerminus(termini[termini.length - 1], 'end'));
    }

    // --- Prefetch/lazy dei dati delle attività (containsPlace) ---
    // I rating compaiono nelle righe attività: precarico la finestra visibile e
    // poi, in idle, tutte le altre → nessuna attesa percepibile scorrendo.
    // Wrapper ATTIVO = quello col centro più vicino al centro del carosello.
    // Robusto anche con wrapper molto più larghi del viewport (scarti-distanza
    // grandi), dove l'IntersectionObserver a soglia non attivava mai.
    function updateActive() {
      const cx = carousel.scrollLeft + carousel.clientWidth / 2;
      let best = null, bestD = Infinity;
      for (const w of carousel.children) {
        if (!(w.classList && w.classList.contains('station-wrapper'))) continue;
        const d = Math.abs((w.offsetLeft + w.offsetWidth / 2) - cx);
        if (d < bestD) { bestD = d; best = w; }
      }
      if (!best) return;
      carousel.querySelectorAll('.station-wrapper.active').forEach(w => { if (w !== best) w.classList.remove('active'); });
      best.classList.add('active');
      loadWindow(best);
    }
    let activeRAF = null;
    carousel.addEventListener('scroll', () => {
      if (activeRAF) return;
      activeRAF = requestAnimationFrame(() => { activeRAF = null; updateActive(); });
    });

    // Observer solo per il lazy-load anticipato dei vicini che entrano in vista.
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => { if (entry.isIntersecting) loadWindow(entry.target); });
    }, { root: carousel, threshold: 0.01, rootMargin: "0px 30% 0px 30%" });

    document.querySelectorAll('.station-wrapper').forEach(station => observer.observe(station));
    document.querySelectorAll('.wf-scroll').forEach(updateScrollHint); // stato iniziale hint
    updateActive(); // stato attivo iniziale

    // Prefetch di TUTTO in background (idle): attività + containedInPlace.
    const prefetchAll = () => {
      document.querySelectorAll('.place-block[data-place-id]').forEach(fillBiz);
      document.querySelectorAll('[data-cip]').forEach(fillContainedIn);
      document.querySelectorAll('.place-media[data-media-id]').forEach(fillMedia);
    };
    if ('requestIdleCallback' in window) requestIdleCallback(prefetchAll, { timeout: 1500 });
    else setTimeout(prefetchAll, 300);

    // Centraggio da URL: /#<@id> porta quella card al centro (priorità sul pontile).
    function centerFromHash() {
      const id = decodeURIComponent((location.hash || '').slice(1));
      if (!id) return false;
      const card = document.getElementById(id);
      const w = card && card.closest('.station-wrapper');
      if (!w) return false;
      centerWrapper(w, false);
      updateActive();
      return true;
    }
    const hasHash = !!(location.hash || '').slice(1);

    if (pontileElement && !hasHash) {
      // Centra il pontile solo se l'URL non chiede già una card specifica.
      setTimeout(() => {
        centerWrapper(pontileElement, false);
        updateActive();
      }, 100);
    }
    // Più tentativi: il layout/scroll-snap si assesta dopo il primo frame.
    [150, 450, 900].forEach(d => setTimeout(centerFromHash, d));
    window.addEventListener('hashchange', centerFromHash);

    /* Linea pronta. Da qui in poi la pagina È la linea: prende tutto lo schermo e
     * scorre in orizzontale, e l'elenco delle fermate — quello che il server ha
     * scritto, e che un motore di ricerca ha già letto — si toglie di mezzo.
     *
     * L'ordine conta: la classe si mette DOPO che le fermate ci sono. Se qualcosa
     * va storto (il file non c'è, il JSON è rotto) non si arriva qui, e la pagina
     * resta quella normale con l'elenco leggibile — che è il ripiego giusto. */
    document.body.classList.add('mt-percorso');
    const loaderEl = document.getElementById('app-loader');
    if (loaderEl) setTimeout(() => loaderEl.classList.add('hide'), 250);

  } catch (error) {
    carousel.innerHTML = `<div id="loader" style="color: #e74c3c;"><span class="material-symbols-outlined" style="font-size: 40px;">error</span> Errore di caricamento. Verifica il file JSON.</div>`;
    const loaderEl = document.getElementById('app-loader');
    if (loaderEl) loaderEl.classList.add('hide');
    console.error(error);
  }
}

/* Si parte solo se in pagina c'è la linea: questo file lo carica il tema per le
 * raccolte che si guardano come un percorso, e su una raccolta qualunque non deve
 * fare niente — nemmeno un errore in console. */
if (carousel) initApp();

/* Le finestre si aprono e si chiudono da attributi `onclick` scritti dentro il
 * markup — quello del guscio e quello delle card. Un attributo `onclick` cerca la
 * funzione fra le GLOBALI, e qui dentro non lo sono più: questo file, che prima
 * viveva in fondo a una pagina, adesso sta in un involucro suo, così non semina
 * una trentina di nomi nello spazio comune. Quelle quattro che servono al markup
 * si dichiarano, una per una, invece di rinunciare all'involucro. */
window.closePlaceModal = closePlaceModal;
window.closeLegend = closeLegend;
window.closeContact = closeContact;
window.submitContact = submitContact;
window.openBeachModal = openBeachModal;
window.openPlaceModal = openPlaceModal;

} // if (carousel)
})();
