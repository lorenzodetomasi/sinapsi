import { useState } from 'react';
import { rankWith, and, uiTypeIs, schemaMatches, Actions } from '@jsonforms/core';
import { withJsonFormsControlProps, useJsonForms } from '@jsonforms/react';
import { dateRicorrenti, oraDi } from './quando.js';
import { proponiId } from './eventId.js';

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

/* Un tetto c'è, e non è una cautela da poco: ogni occorrenza è una cartella e un
 * file da scrivere a mano. Cinquantadue è un anno di settimanale. */
const MAX_OCCORRENZE = 52;

const DEFAULT = { presente: false, aderenza: 'fissa', frequency: 'weekly', interval: 1, byDay: [], endMode: 'never', until: '', count: 10 };

const Recurrence = ({ data, handleChange, path, label, visible }) => {
  if (visible === false) return null; // rispetta la regola SHOW/HIDE
  const v = { ...DEFAULT, ...(data || {}) };
  const set = (patch) => handleChange(path, { ...v, ...patch });
  const ctx = useJsonForms();
  const dati = ctx?.core?.data || {};
  const [quante, setQuante] = useState(12);
  const [esito, setEsito] = useState('');

  /* Dalla cadenza alle occorrenze. Non crea eventi — quelli li scrive il
   * redattore, uno per uno — ma i RIFERIMENTI: gli @id che quegli eventi avranno,
   * calcolati con la stessa regola con cui l'editor li compone. Finché i file non
   * ci sono, il controllo di coerenza li segnala come non presenti nell'indice, ed
   * è giusto così: dicono che cosa manca ancora. */
  const generabile = !!oraDi(dati.startDate);
  const genera = () => {
    const date = dateRicorrenti(dati.startDate, v, Math.min(MAX_OCCORRENZE, Math.max(1, quante)));
    const nuove = date
      .map((iso) => ({
        id: proponiId({
          primaryType: 'Event',
          name: dati.name,
          startDate: iso,
          location: dati.location,
          eventAttendanceMode: dati.eventAttendanceMode,
          superEvent: dati.id,
        }),
        name: dati.name || '',
      }))
      .filter((x) => x.id);
    const prima = dati.occurrences ?? [];
    const gia = new Set(prima.map((o) => String(o?.id || '').replace(/^events\//, '')));
    const aggiunte = nuove.filter((x) => !gia.has(x.id.replace(/^events\//, '')));
    if (aggiunte.length) ctx.dispatch(Actions.update('occurrences', () => [...prima, ...aggiunte]));
    setEsito(
      aggiunte.length
        ? `${aggiunte.length} occorrenz${aggiunte.length > 1 ? 'e aggiunte' : 'a aggiunta'} all’elenco (${date.length - aggiunte.length} c’${date.length - aggiunte.length === 1 ? 'era' : 'erano'} già). Gli eventi vanno poi creati uno per uno.`
        : 'Nessuna occorrenza nuova: quelle che la cadenza propone ci sono già.'
    );
  };
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
      <div className="rec-line rec-genera">
        <button type="button" className="btn-ghost" disabled={!generabile} onClick={genera}>
          <span className="material-symbols-outlined">playlist_add</span> Genera
        </button>
        <input
          type="number"
          min="1"
          max={MAX_OCCORRENZE}
          className="rec-count"
          value={quante}
          onChange={(e) => setQuante(Math.min(MAX_OCCORRENZE, Math.max(1, Number(e.target.value) || 1)))}
        />
        <span>occorrenze{generabile ? '' : ' — serve prima la data di inizio, con l’ora'}</span>
      </div>
      {esito ? <span className="place-status place-ok">✓ {esito}</span> : null}
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
        {/* Fissa o indicativa: una collezione può avere un ritmo dichiarato e
            qualche eccezione vera (una data spostata, una saltata). Dirlo serve a
            non far gridare al lupo il controllo di coerenza, e a non prendere per
            buona una cadenza che invece è una promessa. */}
        <select
          className="rec-aderenza"
          value={v.aderenza || 'fissa'}
          title="Quanto la cadenza va presa alla lettera"
          onChange={(e) => set({ aderenza: e.target.value })}
        >
          <option value="fissa">fissa</option>
          <option value="indicativa">indicativa</option>
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
