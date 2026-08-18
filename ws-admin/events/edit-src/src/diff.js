// Confronto tra i dati "del web" (salvato) e i dati correnti del form, con dot-path
// (supporta indici di array: a.b[0].c). Serve al diff/merge selettivo del salvataggio.

const isObj = (v) => v !== null && typeof v === 'object';
const isEmpty = (v) => v === undefined || v === null || v === '';
const eq = (a, b) => JSON.stringify(a) === JSON.stringify(b);

function parsePath(path) {
  const segs = [];
  for (const part of String(path).split('.')) {
    if (!part) continue;
    const name = part.replace(/\[\d+\]/g, '');
    if (name) segs.push(name);
    for (const m of part.matchAll(/\[(\d+)\]/g)) segs.push(Number(m[1]));
  }
  return segs;
}

export function getPath(obj, path) {
  let ref = obj;
  for (const s of parsePath(path)) {
    if (ref == null) return undefined;
    ref = ref[s];
  }
  return ref;
}

export function setPath(obj, path, value) {
  const segs = parsePath(path);
  let ref = obj;
  for (let i = 0; i < segs.length - 1; i++) {
    const s = segs[i];
    if (!isObj(ref[s])) ref[s] = typeof segs[i + 1] === 'number' ? [] : {};
    ref = ref[s];
  }
  ref[segs[segs.length - 1]] = value;
}

export function unsetPath(obj, path) {
  const segs = parsePath(path);
  let ref = obj;
  for (let i = 0; i < segs.length - 1; i++) {
    if (!isObj(ref)) return;
    ref = ref[segs[i]];
  }
  if (!isObj(ref)) return;
  const last = segs[segs.length - 1];
  if (Array.isArray(ref) && typeof last === 'number') ref[last] = undefined;
  else delete ref[last];
}

// Confronto ricorsivo. `before` = dati del web, `after` = dati del form.
// Ritorna [{ path, kind:'modified'|'added'|'removed', before, after }].
// `exclude` = insieme di chiavi di primo livello da ignorare (campi derivati/gestiti).
export function diffForm(before, after, exclude = new Set()) {
  const out = [];
  const walk = (a, b, path) => {
    const top = path.split('.')[0]?.replace(/\[\d+\]/g, '');
    if (path && exclude.has(top)) return;

    if (Array.isArray(a) || Array.isArray(b)) {
      const aa = Array.isArray(a) ? a : [];
      const bb = Array.isArray(b) ? b : [];
      for (let i = 0; i < Math.max(aa.length, bb.length); i++) walk(aa[i], bb[i], `${path}[${i}]`);
      return;
    }
    if (isObj(a) && isObj(b)) {
      for (const k of new Set([...Object.keys(a), ...Object.keys(b)])) {
        walk(a[k], b[k], path ? `${path}.${k}` : k);
      }
      return;
    }
    if (isEmpty(a) && isEmpty(b)) return;
    if (eq(a, b)) return;
    out.push({ path, kind: isEmpty(a) ? 'added' : isEmpty(b) ? 'removed' : 'modified', before: a, after: b });
  };
  walk(before, after, '');
  return out;
}

// Etichette leggibili per i percorsi del form (per il pannello diff).
const LABELS = {
  '@type': 'Tipo', id: '@id', url: 'Sito web', sameAs: 'Social/altri siti',
  name: 'Nome', description: 'Descrizione', disambiguatingDescription: 'Sottotitolo',
  image: 'Logo/immagine', startDate: 'Inizio', endDate: 'Fine', doorTime: 'Apertura porte',
  eventStatus: 'Stato evento', eventAttendanceMode: "Modalità di partecipazione",
  previousStartDate: 'Data precedente', keywords: 'Parole chiave', inLanguage: 'Lingua',
  location: 'Luogo', organizer: 'Organizzatore', performer: 'Partecipante/artista',
  offers: 'Offerta', price: 'Prezzo', priceCurrency: 'Valuta', availability: 'Disponibilità',
  maximumPhysicalAttendeeCapacity: 'Capienza in presenza', maximumVirtualAttendeeCapacity: 'Capienza da remoto',
  bookedAttendeeCapacity: 'Posti prenotati', subEvent: 'Sotto-evento', superEvent: 'Evento contenitore',
  isAccessibleForFree: 'Gratuito', typicalAgeRange: 'Età consigliata', about: 'Argomento',
};
// Percorso dati → classe wrapper di JSON Forms (es. organizer[0].name →
// root_properties_organizer_0_properties_name). Serve ai marcatori inline sui campi.
export function pathToClass(path) {
  let out = 'root';
  for (const seg of String(path).split('.')) {
    const m = seg.match(/^([^\[]*)((?:\[\d+\])*)$/);
    if (m && m[1]) out += '_properties_' + m[1];
    for (const idx of (m && m[2] ? m[2].match(/\d+/g) || [] : [])) out += '_' + idx;
  }
  return out;
}

export function pathLabel(path) {
  return String(path).split('.').map((seg) => {
    const m = seg.match(/^([^\[]*)((?:\[\d+\])*)$/);
    const key = m ? m[1] : seg;
    const idx = m && m[2] ? [...m[2].matchAll(/\[(\d+)\]/g)].map((x) => Number(x[1]) + 1).join('·') : '';
    const base = LABELS[key] || key;
    return idx ? `${base} ${idx}` : base;
  }).join(' › ');
}

// Costruisce i dati "uniti": parte dai dati del form (miei) e, per i percorsi in
// `keepTheirs`, ripristina il valore del web (o lo rimuove se il web non lo aveva).
export function mergeChoices(mine, changes, keepTheirs) {
  const merged = structuredClone(mine);
  for (const c of changes) {
    if (!keepTheirs.has(c.path)) continue; // 'usa il mio' (default): lascio il mio
    if (isEmpty(c.before)) unsetPath(merged, c.path);
    else setPath(merged, c.path, c.before);
  }
  return merged;
}
