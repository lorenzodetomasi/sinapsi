import { ID_CHECK_URL } from './config.js';

// Builds the @type/@id of a place — same logic as the PHP side
// (google_place-json.php). Primary type: LocalBusiness when it is an
// establishment, Place otherwise.
export function detectPrimaryType(types = []) {
  return (types || []).includes('establishment') ? 'LocalBusiness' : 'Place';
}

// One folder for both: a LocalBusiness IS a Place (schema.org), so both live
// under places/. The precise type stays in @type, not in the path.
export function folderForType(_type) {
  return 'places';
}

export function slugify(name) {
  return String(name || '').toLowerCase().replace(/[^a-z0-9]/g, '');
}

// The region part of an @id is country (short) + postcode, e.g. "IT" + "00124"
// = "IT00124". Returns '' when either is missing.
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

/* An @id with an empty region — `places//statuadinettuno` — is not a typo: it is
 * the signal the server sends when Google gave no postcode
 * (google_place-json.php, `id_region_missing`). The double slash makes the @id
 * invalid on purpose, so that saving is blocked instead of filing a place under
 * a wrong address.
 *
 * But a signal is not an answer: whoever is filling the form usually knows the
 * postcode. Once it is in the form the address puts itself back together; until
 * then the signal stays, because something really is missing.
 */
export function completeId(id, country, postalCode) {
  const m = String(id || '').match(/^([A-Za-z]+)\/\/(.+)$/);
  if (!m) return id;
  const land = String(country || '').trim();
  const post = String(postalCode || '').trim();
  return (land && post) ? `${m[1]}/${land}${post}/${m[2]}` : id;
}

/** True while the @id still carries the missing-postcode signal. */
export function isIdIncomplete(id) {
  return /^[A-Za-z]+\/\/.+$/.test(String(id || ''));
}

// Changes only the prefix (localbusinesses|places) of an @id, by type.
export function swapIdPrefix(id, type) {
  const m = String(id || '').match(/^(?:places|localbusinesses)\/(.*)$/);
  return m ? `${folderForType(type)}/${m[1]}` : id;
}

// Asks the backend about an @id. Returns { exists, google_place_id, stored,
// parse_error }, or null when the check is unavailable.
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

/** «Is this Google place already on the site?» — looks it up by Google Place ID
 *  in the deduplication index. That is the right question when picking one of
 *  Google's suggestions: the Place ID is the place's identity, while an @id
 *  built from name and postcode changes if the name is spelled differently, and
 *  the match would be missed.
 *  Returns { id, name, type } when found, otherwise null. */
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

// Google address components → { postalCode, country }.
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

// A LIGHT diff (only the fields the editor holds from Google) between the picked
// place and the stored one: lists the names of the fields that changed. For a
// full comparison (website, rating, address) see places/edit/index.php.
export function lightPlaceDiff(picked, stored) {
  if (!stored) return [];
  const changes = [];
  const norm = (s) => String(s ?? '').trim().toLowerCase();
  if (norm(picked.name) !== norm(stored.name)) changes.push('nome');
  const a = addr(picked.addressComponents);
  if (a.postalCode && norm(a.postalCode) !== norm(stored.postalCode)) changes.push('CAP');
  return changes;
}
