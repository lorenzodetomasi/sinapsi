// An occurrence inherits from its series: the editor side of
// ws-admin/lib/event-inherit.php, with the same list of fields. If the two
// lists diverge, the editor would save as "own" a value the site thinks is
// inherited, or the other way round - keep them equal.
//
// Opening an occurrence, the form shows it COMPLETE (its own values, and the
// series' where it says nothing), so whoever edits sees what the page shows.
// Saving, whatever is still equal to the series is left out: it stays
// inherited, and a later correction to the series reaches it.

export const CHIAVI_EREDITATE = [
  'name', 'alternateName', 'description', 'abstract', 'disambiguatingDescription',
  'image', 'logo', 'url', 'sameAs', 'keywords', 'inLanguage', 'about', 'genre',
  'location', 'organizer', 'performer', 'funder', 'sponsor',
  'offers', 'isAccessibleForFree', 'eventAttendanceMode', 'typicalAgeRange', 'audience',
  'maximumAttendeeCapacity', 'maximumPhysicalAttendeeCapacity', 'maximumVirtualAttendeeCapacity',
  'meetoo:timezone', 'meetoo:isChildrensEvent', 'meetoo:forSeparatedParents',
  'meetoo:childrenMustBeAccompanied', 'ws:icon',
];

const vuoto = (v) => v === undefined || v === null || v === '' || (Array.isArray(v) && v.length === 0);
const asArray = (v) => (Array.isArray(v) ? v : v === undefined || v === null || v === '' ? [] : [v]);

/** The series of an event, as a content path (events/slug), '' if none. */
export function serieDi(doc) {
  let s = doc?.superEvent;
  if (Array.isArray(s)) s = s[0];
  const id = String((s && typeof s === 'object' ? s['@id'] : s) || '').replace(/^\/+|\/+$/g, '');
  if (!id) return '';
  return id.includes('/') ? id : `events/${id}`;
}

export const eSerie = (doc) => asArray(doc?.['@type']).includes('EventSeries');

/** A media path of the series, re-rooted: `media/cover.jpg` -> `events/serie/media/cover.jpg`. */
function riradica(v, serieRel) {
  const fix = (p) => {
    if (typeof p !== 'string' || !p || /^(https?:)?\/\//i.test(p)) return p;
    if (/^(events|places|organizations|categories|users|brand)\//.test(p)) return p;
    return `${serieRel.replace(/\/+$/, '')}/${p.replace(/^\/+/, '')}`;
  };
  if (typeof v === 'string') return fix(v);
  if (Array.isArray(v)) return v.map((x) => riradica(x, serieRel));
  if (v && typeof v === 'object') {
    const o = { ...v };
    ['url', 'contentUrl', '@id'].forEach((k) => { if (o[k]) o[k] = fix(o[k]); });
    return o;
  }
  return v;
}

/** What the series hands down, media re-rooted: the terms of comparison too. */
export function eredita(serie, serieRel) {
  const out = {};
  CHIAVI_EREDITATE.forEach((k) => {
    if (!vuoto(serie?.[k])) out[k] = k === 'image' || k === 'logo' ? riradica(serie[k], serieRel) : serie[k];
  });
  out['@type'] = asArray(serie?.['@type']).filter((t) => t !== 'EventSeries');
  return out;
}

/** The occurrence complete: its values, and the series' where it says nothing. */
export function completa(occ, serie, serieRel) {
  const dote = eredita(serie, serieRel);
  const out = { ...occ };
  CHIAVI_EREDITATE.forEach((k) => {
    if (vuoto(out[k]) && !vuoto(dote[k])) out[k] = dote[k];
  });
  const propri = asArray(out['@type']).filter((t) => t !== 'Event');
  if (!propri.length && dote['@type'].length) out['@type'] = ['Event', ...dote['@type']];
  return out;
}

const uguali = (a, b) => JSON.stringify(a) === JSON.stringify(b);

/** Before saving: out goes whatever is still the series'. */
export function soloDifferenze(occ, serie, serieRel) {
  const dote = eredita(serie, serieRel);
  const out = { ...occ };
  CHIAVI_EREDITATE.forEach((k) => {
    if (vuoto(out[k]) || (k in dote && uguali(out[k], dote[k]))) delete out[k];
  });
  // Same kind as the series: "Event" is enough, the rest is inherited.
  const propri = asArray(out['@type']).filter((t) => t !== 'Event');
  if (uguali(propri, dote['@type'])) out['@type'] = 'Event';
  return out;
}
