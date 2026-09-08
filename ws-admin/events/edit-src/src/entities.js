import { CONTENT_BASE } from './config.js';

// Everyone who can organise an event (organizations plus places/businesses),
// read from _index/entities.json — the index rebuilt by
// places/rebuild-index.php and by the hub's maintenance page. It exists so the
// organiser can be picked from a list instead of typing an @id from memory, and
// so a name can be resolved back from an @id.
//
// Fetched ONCE per editor session: it is a small static file, and the
// alternative — one request per organiser row — would only be slower.

export const ENTITIES_URL = CONTENT_BASE + '_index/entities.json';

let pending = null;

/** Returns the list (an array, possibly empty). Never throws: the editor has to
 *  stay usable even when the index is not on the server yet. */
export function loadEntities() {
  if (!pending) {
    pending = fetch(ENTITIES_URL)
      .then((r) => (r.ok ? r.json() : []))
      .then((j) => (Array.isArray(j) ? j : []))
      .catch(() => []);
  }
  return pending;
}

/** Look up by @id: exact match first, then a forgiving one (whitespace, case,
 *  trailing slash). Pasting an @id should not be punished for a detail. */
export function findEntityById(list, id) {
  const v = (id ?? '').trim();
  if (!v) return null;
  const exact = list.find((e) => e['@id'] === v);
  if (exact) return exact;
  const norm = (s) => (s ?? '').trim().toLowerCase().replace(/\/+$/, '');
  return list.find((e) => norm(e['@id']) === norm(v)) || null;
}

/** Look up by exact name (case-insensitive): needed when picking from the list,
 *  because a <datalist> hands back the option's text, not an identifier. */
export function findEntityByName(list, name) {
  const v = (name ?? '').trim().toLowerCase();
  if (!v) return null;
  return list.find((e) => (e.name ?? '').trim().toLowerCase() === v) || null;
}
