import { rankWith, and, uiTypeIs, schemaMatches } from '@jsonforms/core';
import { withJsonFormsControlProps } from '@jsonforms/react';

// Editor di ricorrenza (schema.org Schedule) modellato su Google Calendar.
// Modello dati (form): { frequency, interval, byDay[], endMode, until, count }.
// Il fuso NON sta qui: è di tutto l'evento, non della sola ricorrenza, e vive in
// cima a «Quando». Averlo in due posti significava due campi con lo stesso nome
// che potevano dire cose diverse.
// La mappatura verso Schedule (repeatFrequency P{n}{unità}, byDay, endDate/repeatCount,
// scheduleTimezone) è nell'adapter jsonld.

const FREQ = [
  { value: 'daily', label: 'giorno' },
  { value: 'weekly', label: 'settimana' },
  { value: 'monthly', label: 'mese' },
  { value: 'yearly', label: 'anno' },
];
const DAYS = [
  { code: 'MO', label: 'L' },
  { code: 'TU', label: 'M' },
  { code: 'WE', label: 'M' },
  { code: 'TH', label: 'G' },
  { code: 'FR', label: 'V' },
  { code: 'SA', label: 'S' },
  { code: 'SU', label: 'D' },
];

const DEFAULT = { presente: false, frequency: 'weekly', interval: 1, byDay: [], endMode: 'never', until: '', count: 10 };

const Recurrence = ({ data, handleChange, path, label, visible }) => {
  if (visible === false) return null; // rispetta la regola SHOW/HIDE
  const v = { ...DEFAULT, ...(data || {}) };
  const set = (patch) => handleChange(path, { ...v, ...patch });
  const toggleDay = (code) => {
    const on = v.byDay.includes(code);
    set({ byDay: on ? v.byDay.filter((d) => d !== code) : [...v.byDay, code] });
  };

  return (
    <div className="recurrence">
      {/* Una collezione può benissimo non avere una ricorrenza: le sue occorrenze
          stanno quando stanno. Senza questo interruttore i valori di comodo del
          form («ogni 1 settimana») finivano nel file al primo salvataggio, e la
          collezione si ritrovava una cadenza che nessuno le aveva dato. */}
      <label className="field-label rec-accendi">
        <input type="checkbox" checked={!!v.presente} onChange={(e) => set({ presente: e.target.checked })} />
        <span className="material-symbols-outlined">repeat</span>
        {label || 'Ricorrenza'}
      </label>

      {v.presente && (
        <>
      <div className="rec-line">
        <span>Ripeti ogni</span>
        <input
          type="number"
          min="1"
          className="rec-interval"
          value={v.interval}
          onChange={(e) => set({ interval: Math.max(1, Number(e.target.value) || 1) })}
        />
        <select value={v.frequency} onChange={(e) => set({ frequency: e.target.value })}>
          {FREQ.map((f) => (
            <option key={f.value} value={f.value}>
              {f.label}
            </option>
          ))}
        </select>
      </div>

      {v.frequency === 'weekly' && (
        <div className="rec-days">
          {DAYS.map((d) => (
            <button
              key={d.code}
              type="button"
              className={'rec-day' + (v.byDay.includes(d.code) ? ' on' : '')}
              onClick={() => toggleDay(d.code)}
              title={d.code}
            >
              {d.label}
            </button>
          ))}
        </div>
      )}

      <div className="rec-end">
        <span>Termina</span>
        <div className="rec-end-opts">
          <label>
            <input type="radio" name={path + '-end'} checked={v.endMode === 'never'} onChange={() => set({ endMode: 'never' })} />
            Mai
          </label>
          <label>
            <input type="radio" name={path + '-end'} checked={v.endMode === 'until'} onChange={() => set({ endMode: 'until' })} />
            Il
            <input
              type="date"
              className="rec-until"
              value={v.until}
              disabled={v.endMode !== 'until'}
              onChange={(e) => set({ endMode: 'until', until: e.target.value })}
            />
          </label>
          <label>
            <input type="radio" name={path + '-end'} checked={v.endMode === 'count'} onChange={() => set({ endMode: 'count' })} />
            Dopo
            <input
              type="number"
              min="1"
              className="rec-count"
              value={v.count}
              disabled={v.endMode !== 'count'}
              onChange={(e) => set({ endMode: 'count', count: Math.max(1, Number(e.target.value) || 1) })}
            />
            occorrenze
          </label>
        </div>
      </div>
        </>
      )}

    </div>
  );
};

export const recurrenceTester = rankWith(
  10,
  and(uiTypeIs('Control'), schemaMatches((s) => s && s.format === 'recurrence'))
);

export default withJsonFormsControlProps(Recurrence);
