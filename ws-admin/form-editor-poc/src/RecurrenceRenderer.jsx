import { rankWith, and, uiTypeIs, schemaMatches } from '@jsonforms/core';
import { withJsonFormsControlProps } from '@jsonforms/react';

// Editor di ricorrenza (schema.org Schedule) modellato su Google Calendar.
// Modello dati (form): { frequency, interval, byDay[], endMode, until, count, timezone }.
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

const DEFAULT = { frequency: 'weekly', interval: 1, byDay: [], endMode: 'never', until: '', count: 10, timezone: '' };

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
      <label className="field-label">
        <span className="material-symbols-outlined">repeat</span>
        {label || 'Ricorrenza'}
      </label>

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

      <div className="rec-tz">
        <label className="field-label">Fuso orario</label>
        <input
          type="text"
          value={v.timezone}
          placeholder="es. Europe/Rome"
          onChange={(e) => set({ timezone: e.target.value })}
        />
      </div>
    </div>
  );
};

export const recurrenceTester = rankWith(
  10,
  and(uiTypeIs('Control'), schemaMatches((s) => s && s.format === 'recurrence'))
);

export default withJsonFormsControlProps(Recurrence);
