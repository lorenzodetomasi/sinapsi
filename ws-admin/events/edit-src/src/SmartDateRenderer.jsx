import { useState } from 'react';
import { rankWith, and, isStringControl, schemaMatches } from '@jsonforms/core';
import { withJsonFormsControlProps } from '@jsonforms/react';

// Data con precisione variabile (schema.org accetta YYYY, YYYY-MM, YYYY-MM-DD,
// YYYY-MM-DDThh:mm) e pulsante per svuotare. La precisione è per-campo.
const PRECISIONS = [
  { key: 'datetime', label: 'Data e ora', type: 'datetime-local' },
  { key: 'date', label: 'Solo data', type: 'date' },
  { key: 'month', label: 'Mese e anno', type: 'month' },
  { key: 'year', label: 'Solo anno', type: 'number' },
];

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
  const [precision, setPrecision] = useState(() => detect(data) || 'datetime');
  const conf = PRECISIONS.find((p) => p.key === precision) || PRECISIONS[0];
  const val = coerce(data, precision);

  const set = (v) => handleChange(path, v || undefined);
  const changePrecision = (p) => {
    setPrecision(p);
    if (data) set(coerce(data, p));
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
          {PRECISIONS.map((p) => (
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
          {precision !== 'year' && (
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
