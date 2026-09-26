import { useEffect, useMemo, useState } from 'react';
import { rankWith, and, uiTypeIs, optionIs } from '@jsonforms/core';
import { withJsonFormsControlProps } from '@jsonforms/react';
import { EVENTS_INDEX_URL } from './config.js';

/* The series an event belongs to, CHOSEN from the ones that exist.
 *
 * It was a text box: whoever filled it had to know by heart how the series'
 * folder is called, and a wrong letter gave no error - it gave an occurrence
 * pointing at nothing, and a series page without it. The series are in the
 * events index (kind "series"), upcoming and archived; here they are offered
 * by name, and the reference is written by the editor.
 */

const ARCHIVIO_URL = EVENTS_INDEX_URL.replace(/\.json$/, '.archive.json');
let pending = null;
function caricaSerie() {
  if (!pending) {
    const leggi = (u) => fetch(u, { cache: 'no-store' }).then((r) => (r.ok ? r.json() : [])).catch(() => []);
    pending = Promise.all([leggi(EVENTS_INDEX_URL), leggi(ARCHIVIO_URL)]).then(([a, b]) => {
      const tutte = [...(Array.isArray(a) ? a : a?.events || []), ...(Array.isArray(b) ? b : b?.events || [])];
      const viste = new Map();
      tutte.filter((e) => e?.kind === 'series' && e?.path).forEach((e) => viste.set(e.path, e));
      return [...viste.values()].sort((x, y) => String(x.name || '').localeCompare(String(y.name || ''), 'it'));
    });
  }
  return pending;
}

const norm = (v) => {
  const s = String(v || '').replace(/^\/+|\/+$/g, '');
  return !s ? '' : s.includes('/') ? s : `events/${s}`;
};

function SerieRenderer({ data, handleChange, path, label, schema, visible }) {
  const [serie, setSerie] = useState(null);
  useEffect(() => { caricaSerie().then(setSerie); }, []);
  const valore = norm(data);
  const scelta = useMemo(() => (serie || []).find((s) => s.path === valore) || null, [serie, valore]);
  if (visible === false) return null;

  const descrivi = (s) => [s.name || s.path, s.organizer].filter(Boolean).join(' — ');
  return (
    <div className="rf rf-text serie-scelta">
      <label className="field-label">{label || schema?.title}</label>
      <select
        value={scelta ? valore : valore ? '__altro' : ''}
        onChange={(e) => {
          const v = e.target.value;
          if (v === '__altro') return;
          handleChange(path, v || undefined);
        }}
      >
        <option value="">— nessuna: è un evento a sé —</option>
        {valore && !scelta ? <option value="__altro">{valore} (non trovata)</option> : null}
        {(serie || []).map((s) => (
          <option key={s.path} value={s.path}>{descrivi(s)}</option>
        ))}
      </select>
      {serie === null ? <span className="place-status">Lettura delle collezioni…</span> : null}
      {valore && serie && !scelta ? (
        <span className="place-status place-warn">
          ⚠️ Nessuna collezione con questo indirizzo nell’indice: scegline una dall’elenco, o toglila.
        </span>
      ) : null}
      {scelta ? (
        <span className="place-status place-ok">
          ✓ Quello che questo evento non dice lo prende da «{scelta.name}».{' '}
          <a href={`?id=${encodeURIComponent(scelta.path)}`} target="_blank" rel="noopener">Apri la collezione</a>
        </span>
      ) : null}
    </div>
  );
}

export const serieTester = rankWith(17, and(uiTypeIs('Control'), optionIs('serie', true)));
export default withJsonFormsControlProps(SerieRenderer);
