import { useState } from 'react';
import { rankWith, and, isStringControl, schemaMatches } from '@jsonforms/core';
import { withJsonFormsControlProps, useJsonForms } from '@jsonforms/react';
import { componi, dataDi, oraDi } from './quando.js';

// Data con precisione variabile (schema.org accetta YYYY, YYYY-MM, YYYY-MM-DD,
// YYYY-MM-DDThh:mm) e pulsante per svuotare. La precisione è per-campo.
const PRECISIONS = [
  { key: 'datetime', label: 'Data e ora', type: 'datetime-local' },
  { key: 'date', label: 'Solo data', type: 'date' },
  { key: 'month', label: 'Mese e anno', type: 'month' },
  { key: 'year', label: 'Solo anno', type: 'number' },
];

/* «Solo ora» non è una precisione come le altre: nel file resta comunque una data
 * con l'ora, perché un orario nudo non è un istante e schema.org non lo accetta.
 * Per questo compare solo dove una data da cui ereditare c'è già — la fine di un
 * evento che comincia in un giorno noto. */
const ORA = { key: 'time', label: 'Solo ora', type: 'time' };

const parts = (v) => {
  const s = String(v ?? '');
  return { y: s.slice(0, 4), mo: s.slice(5, 7), d: s.slice(8, 10), hm: s.slice(11, 16) };
};
const detect = (v) => {
  const s = String(v ?? '');
  if (!s) return null;
  if (s.includes('T')) return 'datetime';
  if (s.length <= 4) return 'year';
  if (s.length <= 7) return 'month';
  return 'date';
};
// Adatta il valore alla precisione scelta (tronca o completa con default).
const coerce = (v, p) => {
  const { y, mo, d, hm } = parts(v);
  if (!y) return '';
  if (p === 'year') return y;
  const M = mo || '01';
  if (p === 'month') return `${y}-${M}`;
  const D = d || '01';
  if (p === 'date') return `${y}-${M}-${D}`;
  return `${y}-${M}-${D}T${hm || '00:00'}`;
};

const SmartDate = ({ data, handleChange, path, label, id, uischema, enabled, visible }) => {
  if (visible === false) return null;
  const icon = uischema?.options?.icon;
  const ctx = useJsonForms();
  // La data da cui «Solo ora» eredita il giorno: quella d'inizio dell'evento.
  const riferimento = dataDi(ctx?.core?.data?.startDate);
  const fine = /endDate$/.test(path || '');
  const scelte = riferimento && fine ? [...PRECISIONS, ORA] : PRECISIONS;

  /* La precisione si DEDUCE dal valore finché nessuno ne sceglie una: l'evento
   * arriva dal server dopo il primo disegno, e una scelta congelata al montaggio
   * resterebbe quella dei campi vuoti. Una fine nello stesso giorno dell'inizio si
   * legge meglio come orario — «dalle 19:00 alle 21:00» invece di ripetere la data. */
  const dedotta = (() => {
    const d = detect(data);
    if (fine && riferimento && d === 'datetime' && dataDi(data) === riferimento) return 'time';
    return d || 'datetime';
  })();
  const [scelta, setScelta] = useState(null);
  const precision = scelta ?? dedotta;
  const setPrecision = setScelta;
  const conf = scelte.find((p) => p.key === precision) || PRECISIONS[0];
  const val = precision === 'time' ? oraDi(data) : coerce(data, precision);

  const set = (v) => {
    const fatto = precision === 'time' ? componi(riferimento, v) : v;
    handleChange(path, fatto || undefined);
  };
  const changePrecision = (p) => {
    setPrecision(p);
    if (!data) return;
    // Passando a «Solo ora» il giorno diventa quello dell'evento: è il senso della
    // scelta, non un effetto collaterale.
    handleChange(path, (p === 'time' ? componi(riferimento, oraDi(data) || '00:00') : coerce(data, p)) || undefined);
  };
  const openPicker = (e) => {
    try {
      e.currentTarget.parentElement.querySelector('input')?.showPicker?.();
    } catch {
      /* showPicker non disponibile */
    }
  };

  return (
    <div className="control smart-date">
      <label className="field-label" htmlFor={id}>
        {icon && <span className="material-symbols-outlined">{icon}</span>}
        {label}
      </label>
      <div className="sd-row">
        <select
          className="sd-precision"
          value={precision}
          disabled={enabled === false}
          title="Precisione della data"
          onChange={(e) => changePrecision(e.target.value)}
        >
          {scelte.map((p) => (
            <option key={p.key} value={p.key}>{p.label}</option>
          ))}
        </select>

        <div className="sd-input-wrap">
          <input
            id={id}
            type={conf.type}
            value={val}
            disabled={enabled === false}
            min={precision === 'year' ? 1900 : undefined}
            max={precision === 'year' ? 2999 : undefined}
            placeholder={precision === 'year' ? 'AAAA' : undefined}
            onChange={(e) => set(e.target.value)}
          />
          {precision !== 'year' && precision !== 'time' && (
            <button type="button" className="sd-pick" title="Apri calendario" onClick={openPicker} tabIndex={-1}>
              <span className="material-symbols-outlined">calendar_today</span>
            </button>
          )}
          {data ? (
            <button type="button" className="sd-clear" title="Cancella" onClick={() => set(undefined)} tabIndex={-1}>
              <span className="material-symbols-outlined">close</span>
            </button>
          ) : null}
        </div>
      </div>
    </div>
  );
};

export const smartDateTester = rankWith(
  10,
  and(isStringControl, schemaMatches((s) => s && s.format === 'date-time'))
);

export default withJsonFormsControlProps(SmartDate);
