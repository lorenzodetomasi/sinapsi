import { useEffect, useMemo, useState } from 'react';
import { rankWith, and, uiTypeIs, optionIs } from '@jsonforms/core';
import { withJsonFormsControlProps } from '@jsonforms/react';
import { caricaSerie } from './SerieRenderer.jsx';

/* The strands (rassegne) an event is in: Sonodramma, SomministrArte, the
 * Scuola d'Arte Poetica. Any number, chosen from the strands that exist; the
 * event keeps all its values - a strand gathers, it hands down nothing. */

const norm = (v) => {
  const s = String(v || '').replace(/^\/+|\/+$/g, '');
  return !s ? '' : s.includes('/') ? s : `events/${s}`;
};

function RassegneRenderer({ data, handleChange, path, label, schema, visible }) {
  const [tutte, setTutte] = useState(null);
  useEffect(() => { caricaSerie().then(setTutte); }, []);
  const scelte = (Array.isArray(data) ? data : []).map(norm).filter(Boolean);
  const rassegne = useMemo(() => (tutte || []).filter((s) => s.strand), [tutte]);
  if (visible === false) return null;
  const nome = (p) => rassegne.find((s) => s.path === p)?.name || p;
  const libere = rassegne.filter((s) => !scelte.includes(s.path));

  return (
    <div className="rf rf-text rassegne-scelta">
      <label className="field-label">{label || schema?.title}</label>
      <div className="quando-linea">
        {scelte.map((p) => (
          <span key={p} className={'quando-chip' + (tutte && !rassegne.some((s) => s.path === p) ? ' chip-warn' : '')}>
            {nome(p)}
            <button type="button" title="Togli" onClick={() => handleChange(path, scelte.filter((x) => x !== p))}>×</button>
          </span>
        ))}
        {libere.length ? (
          <select value="" onChange={(e) => e.target.value && handleChange(path, [...scelte, e.target.value])}>
            <option value="">{scelte.length ? '+ un’altra rassegna…' : '— nessuna: aggiungine una —'}</option>
            {libere.map((s) => <option key={s.path} value={s.path}>{s.name || s.path}</option>)}
          </select>
        ) : null}
      </div>
      {tutte && !rassegne.length ? (
        <span className="place-status">Nessuna rassegna ancora: si crea come una collezione, con «È una rassegna».</span>
      ) : null}
    </div>
  );
}

export const rassegneTester = rankWith(17, and(uiTypeIs('Control'), optionIs('rassegne', true)));
export default withJsonFormsControlProps(RassegneRenderer);
