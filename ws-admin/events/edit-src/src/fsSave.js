// File System Access API: salva l'index.json direttamente nella cartella giusta,
// creando le sottocartelle (events/<id>/…). La cartella base (radice contenuti)
// si sceglie UNA volta e resta ricordata in IndexedDB tra le sessioni.

export const supportsFs = () => typeof window !== 'undefined' && 'showDirectoryPicker' in window;

// --- IndexedDB minimale per ricordare il directory handle ---
const DB = 'events-edit', STORE = 'handles';
function openDb() {
  return new Promise((res, rej) => {
    const r = indexedDB.open(DB, 1);
    r.onupgradeneeded = () => r.result.createObjectStore(STORE);
    r.onsuccess = () => res(r.result);
    r.onerror = () => rej(r.error);
  });
}
export async function idbGet(key) {
  const db = await openDb();
  return new Promise((res, rej) => {
    const t = db.transaction(STORE).objectStore(STORE).get(key);
    t.onsuccess = () => res(t.result);
    t.onerror = () => rej(t.error);
  });
}
export async function idbSet(key, val) {
  const db = await openDb();
  return new Promise((res, rej) => {
    const t = db.transaction(STORE, 'readwrite');
    t.objectStore(STORE).put(val, key);
    t.oncomplete = () => res();
    t.onerror = () => rej(t.error);
  });
}
export async function idbDel(key) {
  const db = await openDb();
  return new Promise((res, rej) => {
    const t = db.transaction(STORE, 'readwrite');
    t.objectStore(STORE).delete(key);
    t.oncomplete = () => res();
    t.onerror = () => rej(t.error);
  });
}

// Alcuni handle (es. OPFS nei test) non hanno query/requestPermission: sono già concessi.
export async function ensurePermission(handle, mode = 'readwrite') {
  if (!handle) return false;
  if (typeof handle.queryPermission !== 'function') return true;
  if ((await handle.queryPermission({ mode })) === 'granted') return true;
  return (await handle.requestPermission({ mode })) === 'granted';
}

// Naviga/crea le sottocartelle di relPath dentro baseDir e scrive `filename`.
export async function writeInto(baseDir, relPath, filename, text) {
  let dir = baseDir;
  for (const seg of String(relPath).split('/').map((s) => s.trim()).filter(Boolean)) {
    dir = await dir.getDirectoryHandle(seg, { create: true });
  }
  const fh = await dir.getFileHandle(filename, { create: true });
  const w = await fh.createWritable();
  await w.write(text);
  await w.close();
  return dir;
}

// Download classico (fallback dove manca la File System Access API).
export function downloadFile(text, filename = 'index.json', mime = 'application/ld+json') {
  const href = URL.createObjectURL(new Blob([text], { type: mime }));
  const a = document.createElement('a');
  a.href = href;
  a.download = filename;
  a.click();
  URL.revokeObjectURL(href);
}
