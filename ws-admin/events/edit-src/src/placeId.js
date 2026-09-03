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

/* Un @id con la regione vuota — `places//statuadinettuno` — non è un errore di
 * stampa: è il segnale che il server manda quando Google non ha dato il CAP
 * (google_place-json.php, `id_region_missing`). Il doppio taglio rende l'@id
 * invalido apposta, così il salvataggio si blocca invece di creare una scheda
 * a un indirizzo sbagliato.
 *
 * Ma il segnale non è una risposta: il CAP, in genere, chi compila ce l'ha. Se
 * nel modulo c'è, l'indirizzo si ricompone da sé; se non c'è ancora, il segnale
 * resta lì a dire che manca qualcosa. */
export function completaId(id, country, postalCode) {
  const m = String(id || '').match(/^([A-Za-z]+)\/\/(.+)$/);
  if (!m) return id;
  const paese = String(country || '').trim();
  const cap = String(postalCode || '').trim();
  return (paese && cap) ? `${m[1]}/${paese}${cap}/${m[2]}` : id;
}

/** Vero quando l'@id porta ancora il segnale del CAP mancante. */
export function idIncompleto(id) {
  return /^[A-Za-z]+\/\/.+$/.test(String(id || ''));
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

/** «Questo luogo di Google è già sul sito?» — cerca per Google Place ID nell'indice
 *  di deduplica. È la domanda giusta quando si sceglie un suggerimento di Google:
 *  il Place ID è l'identità del luogo, mentre l'@id costruito da nome e CAP cambia
 *  se il nome è scritto diversamente e mancherebbe la corrispondenza.
 *  Ritorna { id, name, type } se c'è, altrimenti null. */
export async function lookupPlaceId(placeId) {
  if (!ID_CHECK_URL || !placeId) return null;
  try {
    const res = await fetch(`${ID_CHECK_URL}?place_id=${encodeURIComponent(placeId)}`);
    const data = await res.json();
    return data && data.ok && data.found ? data : null;
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
// (sito, rating, indirizzo) si usa places/edit/index.php.
export function lightPlaceDiff(picked, stored) {
  if (!stored) return [];
  const changes = [];
  const norm = (s) => String(s ?? '').trim().toLowerCase();
  if (norm(picked.name) !== norm(stored.name)) changes.push('nome');
  const a = addr(picked.addressComponents);
  if (a.postalCode && norm(a.postalCode) !== norm(stored.postalCode)) changes.push('CAP');
  return changes;
}
