import { CONTENT_BASE } from './config.js';

/* DOV'È LA PAGINA PUBBLICA di un contenuto.
 *
 * La risposta la sa la mappa del sito (`ws_sitemap.wsx`), che è anche l'unica
 * autorità: gli indirizzi non si compongono a mano da questa parte, perché la
 * regola che li costruisce sta nel server e cambia — un quartiere che si sposta
 * di municipio cambia l'indirizzo di tutti i suoi eventi, e un editor che se lo
 * fosse calcolato manderebbe la gente su un 404.
 *
 * Si legge una volta per sessione: è un file solo, e l'alternativa sarebbe una
 * richiesta per ogni evento aperto.
 */
const SITEMAP_URL = CONTENT_BASE.replace(/[^/]+\/$/, '') + 'ws_sitemap.wsx';

let promessa = null;

function carica() {
  if (!promessa) {
    promessa = fetch(SITEMAP_URL, { cache: 'no-store' })
      .then((r) => (r.ok ? r.text() : ''))
      .then((testo) => {
        const mappa = new Map();
        if (!testo) return mappa;
        const doc = new DOMParser().parseFromString(testo, 'application/xml');
        for (const url of doc.getElementsByTagName('url')) {
          const wspath = url.getElementsByTagName('wspath')[0]?.textContent || '';
          const query = url.getElementsByTagName('query')[0]?.textContent || '';
          // `content=meetoo/it_IT/events/…` → l'@id, senza sito e lingua davanti.
          const m = query.match(/[?&]content=([^&]+)/);
          if (!wspath || !m) continue;
          const rel = decodeURIComponent(m[1]).replace(/^[^/]+\/[^/]+\//, '');
          // Le pagine GENERATE (i tre elenchi di una zona) condividono il contenuto
          // della zona: qui si cerca la pagina PROPRIA, e quelle non lo sono.
          if (/[?&](elenco|zona)=/.test(query)) continue;
          if (!mappa.has(rel)) mappa.set(rel, wspath);
        }
        return mappa;
      })
      .catch(() => new Map());
  }
  return promessa;
}

/** L'indirizzo pubblico di `rel`, '' se quella cosa una pagina non ce l'ha. */
export async function urlPagina(rel) {
  const v = (rel || '').replace(/^\/+|\/+$/g, '');
  if (!v) return '';
  const mappa = await carica();
  const wspath = mappa.get(v);
  if (!wspath) return '';
  const radice = location.pathname.replace(/\/ws-admin\/.*/, '');
  /* La mappa dice `/roma/…`: manca davanti il punto in cui il sito è montato.
   * Il nome del montaggio è quello della cartella dei contenuti — `contents/
   * meetoo/it_IT/` sta sotto `/meetoo/` — che è la stessa corrispondenza che fa
   * il CMS quando instrada. */
  const sito = (CONTENT_BASE.match(/contents\/([^/]+)\//) || [])[1] || '';
  return radice + (sito ? '/' + sito : '') + wspath;
}
