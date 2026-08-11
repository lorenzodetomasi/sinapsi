import { ID_CHECK_URL } from './config.js';

// Generazione @type/@id dei places — stessa logica del PHP (google_place-json.php).
// Tipo primario: LocalBusiness se è un'attività (establishment), altrimenti Place.
export function detectPrimaryType(types = []) {
  return (types || []).includes('establishment') ? 'LocalBusiness' : 'Place';
}

// Cartella unica: LocalBusiness È un Place (schema.org), quindi entrambi stanno
// sotto places/. Il tipo preciso resta nel @type, non nel percorso.
export function folderForType(_type) {
  return 'places';
}

export function slugify(name) {
  return String(name || '').toLowerCase().replace(/[^a-z0-9]/g, '');
}

// Regione dell'@id = paese (short) + CAP, es. "IT" + "00124" = "IT00124".
// Ritorna '' se manca il CAP o il paese.
export function regionFromComponents(components = []) {
  let country = '';
  let postal = '';
  for (const c of components || []) {
    const t = c.types || [];
    if (t.includes('country')) country = c.short_name || '';
    if (t.includes('postal_code')) postal = c.long_name || '';
  }
  return country && postal ? country + postal : '';
}

export function buildPlaceId(type, region, slug) {
  return `${folderForType(type)}/${region}/${slug}`;
}

// Cambia solo il prefisso (localbusinesses|places) di un @id in base al tipo.
export function swapIdPrefix(id, type) {
  const m = String(id || '').match(/^(?:places|localbusinesses)\/(.*)$/);
  return m ? `${folderForType(type)}/${m[1]}` : id;
}

// Interroga il backend sull'@id. Ritorna l'oggetto { exists, google_place_id,
// stored, parse_error } oppure null se il controllo non è disponibile.
export async function lookupId(id) {
  if (!ID_CHECK_URL || !id) return null;
  try {
    const res = await fetch(`${ID_CHECK_URL}?id=${encodeURIComponent(id)}`);
    const data = await res.json();
    return data && data.ok ? data : null;
  } catch {
    return null;
  }
}

// Componenti indirizzo Google → { postalCode, country }.
function addr(components = []) {
  let postalCode = '';
  let country = '';
  for (const c of components || []) {
    const t = c.types || [];
    if (t.includes('postal_code')) postalCode = c.long_name || '';
    if (t.includes('country')) country = c.short_name || '';
  }
  return { postalCode, country };
}

// Diff LEGGERO (solo i campi che l'editor ha da Google) tra il luogo scelto e
// quello salvato: elenca i nomi dei campi cambiati. Per un confronto completo
// (sito, rating, indirizzo) si usa place-add.php.
export function lightPlaceDiff(picked, stored) {
  if (!stored) return [];
  const changes = [];
  const norm = (s) => String(s ?? '').trim().toLowerCase();
  if (norm(picked.name) !== norm(stored.name)) changes.push('nome');
  const a = addr(picked.addressComponents);
  if (a.postalCode && norm(a.postalCode) !== norm(stored.postalCode)) changes.push('CAP');
  return changes;
}
