import { ID_CHECK_URL } from './config.js';

// Generazione @type/@id dei places — stessa logica del PHP (google_place-json.php).
// Tipo primario: LocalBusiness se è un'attività (establishment), altrimenti Place.
export function detectPrimaryType(types = []) {
  return (types || []).includes('establishment') ? 'LocalBusiness' : 'Place';
}

export function folderForType(type) {
  return type === 'LocalBusiness' ? 'localbusinesses' : 'places';
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

// Verifica sul backend se l'@id esiste già. true/false, oppure null se il
// controllo non è disponibile (URL non configurato o errore di rete).
export async function checkIdExists(id) {
  if (!ID_CHECK_URL || !id) return null;
  try {
    const res = await fetch(`${ID_CHECK_URL}?id=${encodeURIComponent(id)}`);
    const data = await res.json();
    return data && data.ok ? !!data.exists : null;
  } catch {
    return null;
  }
}
