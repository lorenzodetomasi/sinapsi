import { CONTENT_BASE } from './config.js';

// Elenco di chi può organizzare un evento (organizations + luoghi/attività), letto
// da _index/entities.json — l'indice che rigenerano places/rebuild-index.php e la
// manutenzione dell'hub. Serve a scegliere l'organizzatore da una lista invece che
// digitarne l'@id a memoria, e a risalire al nome partendo dall'@id.
//
// Si scarica UNA volta per sessione dell'editor: è un file piccolo e statico, e
// l'alternativa (una richiesta per riga di organizzatore) sarebbe solo più lenta.

export const ENTITIES_URL = CONTENT_BASE + '_index/entities.json';

let promessa = null;

/** Ritorna la lista (array, eventualmente vuoto). Non lancia: l'editor deve
 *  restare usabile anche se l'indice non c'è ancora sul server. */
export function loadEntities() {
  if (!promessa) {
    promessa = fetch(ENTITIES_URL)
      .then((r) => (r.ok ? r.json() : []))
      .then((j) => (Array.isArray(j) ? j : []))
      .catch(() => []);
  }
  return promessa;
}

/** Cerca per @id, confronto esatto e poi tollerante (spazi, maiuscole, slash
 *  finale): chi incolla un @id non deve essere punito per un dettaglio. */
export function findEntityById(list, id) {
  const v = (id ?? '').trim();
  if (!v) return null;
  const exact = list.find((e) => e['@id'] === v);
  if (exact) return exact;
  const norm = (s) => (s ?? '').trim().toLowerCase().replace(/\/+$/, '');
  return list.find((e) => norm(e['@id']) === norm(v)) || null;
}

/** Cerca per nome esatto (case-insensitive): serve quando si sceglie dalla lista,
 *  perché un <datalist> restituisce il testo dell'opzione, non un identificativo. */
export function findEntityByName(list, name) {
  const v = (name ?? '').trim().toLowerCase();
  if (!v) return null;
  return list.find((e) => (e.name ?? '').trim().toLowerCase() === v) || null;
}
