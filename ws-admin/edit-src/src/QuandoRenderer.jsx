import { useMemo, useState } from 'react';
import { rankWith, and, uiTypeIs, optionIs } from '@jsonforms/core';
import { withJsonFormsControlProps, useJsonForms } from '@jsonforms/react';
import { QUANDO_VUOTO, dateDi, giornoDi, oraDi } from './quandoModello.js';

/* «Quando» of an event, in the four shapes the programmes of a theatre use:
 * one date, the replicas of a show, a rule (every Monday from ... to ...), a
 * period (an exhibition, with its opening hours). The data model and its
 * translation to schema.org are in quandoModello.js; this is only the form.
 *
 * "Una data" leaves the usual start/end fields to do the work (they are shown
 * by the uischema only in that mode): this control then says nothing more. */

const MODI = [
  { k: 'una', label: 'Una data', icon: 'event' },
  { k: 'piu', label: 'Più date', icon: 'date_range', aiuto: 'Le repliche dello stesso spettacolo: una riga per data.' },
  { k: 'regola', label: 'Si ripete', icon: 'event_repeat', aiuto: 'Ogni lunedì, ogni due settimane… Le date le calcola il sito.' },
  { k: 'periodo', label: 'Da… a…', icon: 'calendar_view_week', aiuto: 'Una mostra, un festival, un laboratorio di una settimana.' },
];
const GIORNI = [['MO', 'L'], ['TU', 'M'], ['WE', 'M'], ['TH', 'G'], ['FR', 'V'], ['SA', 'S'], ['SU', 'D']];
const NOMI = { MO: 'lunedì', TU: 'martedì', WE: 'mercoledì', TH: 'giovedì', FR: 'venerdì', SA: 'sabato', SU: 'domenica' };
const MESI = ['gen', 'feb', 'mar', 'apr', 'mag', 'giu', 'lug', 'ago', 'set', 'ott', 'nov', 'dic'];
const breve = (g) => {
  if (!g) return '';
  const d = new Date(g + 'T12:00:00');
  return `${['dom', 'lun', 'mar', 'mer', 'gio', 'ven', 'sab'][d.getDay()]} ${d.getDate()} ${MESI[d.getMonth()]}`;
};
const giornoDopo = (g, n = 1) => {
  if (!g) return '';
  const d = new Date(g + 'T12:00:00');
  d.setDate(d.getDate() + n);
  return d.toISOString().slice(0, 10);
};

function Giorni({ valore, onChange }) {
  return (
    <div className="quando-giorni" role="group" aria-label="Giorni della settimana">
      {GIORNI.map(([c, l]) => (
        <button key={c} type="button" title={NOMI[c]} aria-pressed={valore.includes(c)}
          className={valore.includes(c) ? 'on' : ''}
          onClick={() => onChange(valore.includes(c) ? valore.filter((x) => x !== c) : [...valore, c])}>
          {l}
        </button>
      ))}
    </div>
  );
}

function Orari({ dalle, alle, onChange, etichetta = 'dalle' }) {
  return (
    <span className="quando-orari">
      {etichetta}
      <input type="time" value={dalle || ''} onChange={(e) => onChange({ dalle: e.target.value })} />
      alle
      <input type="time" value={alle || ''} onChange={(e) => onChange({ alle: e.target.value })} />
    </span>
  );
}

function QuandoRenderer({ data, handleChange, path, visible }) {
  const ctx = useJsonForms();
  const doc = ctx?.core?.data || {};
  const [aperto, setAperto] = useState(false);
  const q = { ...QUANDO_VUOTO, ...(data || {}) };
  const set = (patch) => handleChange(path, { ...q, ...patch });
  const anteprima = useMemo(() => dateDi(q), [JSON.stringify(q)]);   // eslint-disable-line react-hooks/exhaustive-deps
  if (visible === false || doc.primaryType === 'EventSeries') return null;

  /* Changing shape keeps what was already said: the start of the single date
   * becomes the first replica, the first day of the rule, the first of the period. */
  const cambiaModo = (k) => {
    if (k === q.modo) return;
    const g = giornoDi(doc.startDate), o = oraDi(doc.startDate), gf = giornoDi(doc.endDate), of = oraDi(doc.endDate);
    const primo = q.modo === 'piu' ? q.date[0] : null;
    const inizio = primo?.giorno || (q.modo === 'regola' ? q.regola.dal : q.modo === 'periodo' ? q.periodo.dal : g);
    const ora = primo?.dalle || (q.modo === 'regola' ? q.regola.dalle : o);
    const patch = { modo: k };
    if (k === 'piu' && !q.date.length && inizio) patch.date = [{ giorno: inizio, dalle: ora, alle: gf === g ? of : '' }];
    if (k === 'regola' && !q.regola.dal) patch.regola = { ...q.regola, dal: inizio, dalle: ora };
    if (k === 'periodo' && !q.periodo.dal) patch.periodo = { ...q.periodo, dal: inizio, al: gf && gf > inizio ? gf : '' };
    set(patch);
  };

  const modo = MODI.find((m) => m.k === q.modo) || MODI[0];
  const r = q.regola, p = q.periodo;
  const setR = (x) => set({ regola: { ...r, ...x } });
  const setP = (x) => set({ periodo: { ...p, ...x } });
  const setD = (i, x) => set({ date: q.date.map((d, j) => (j === i ? { ...d, ...x } : d)) });

  return (
    <div className="quando">
      <div className="quando-modi" role="tablist" aria-label="Com'è fatto nel tempo">
        {MODI.map((m) => (
          <button key={m.k} type="button" role="tab" aria-selected={q.modo === m.k}
            className={q.modo === m.k ? 'on' : ''} onClick={() => cambiaModo(m.k)}>
            <span className="material-symbols-outlined">{m.icon}</span>{m.label}
          </button>
        ))}
      </div>
      {modo.aiuto ? <p className="quando-aiuto">{modo.aiuto}</p> : null}

      {q.modo === 'piu' && (
        <div className="quando-righe">
          {q.date.map((d, i) => (
            <div className="quando-riga" key={i}>
              <input type="date" value={d.giorno} onChange={(e) => setD(i, { giorno: e.target.value })} />
              <span className="quando-giorno">{breve(d.giorno)}</span>
              <Orari dalle={d.dalle} alle={d.alle} onChange={(x) => setD(i, x)} etichetta="ore" />
              <button type="button" className="btn-icona" title="Togli questa data"
                onClick={() => set({ date: q.date.filter((_, j) => j !== i) })}>
                <span className="material-symbols-outlined">close</span>
              </button>
            </div>
          ))}
          <button type="button" className="btn-ghost" onClick={() => {
            const u = q.date[q.date.length - 1];
            set({ date: [...q.date, { giorno: u ? giornoDopo(u.giorno) : giornoDi(doc.startDate), dalle: u?.dalle || '', alle: u?.alle || '' }] });
          }}>
            <span className="material-symbols-outlined">add</span>Aggiungi una data
          </button>
        </div>
      )}

      {q.modo === 'regola' && (
        <div className="quando-regola">
          <div className="quando-linea">
            Ogni
            <input type="number" min="1" className="quando-num" value={r.interval}
              onChange={(e) => setR({ interval: Math.max(1, Number(e.target.value) || 1) })} />
            <select value={r.freq} onChange={(e) => setR({ freq: e.target.value })}>
              <option value="D">{r.interval > 1 ? 'giorni' : 'giorno'}</option>
              <option value="W">{r.interval > 1 ? 'settimane' : 'settimana'}</option>
              <option value="M">{r.interval > 1 ? 'mesi' : 'mese'}</option>
            </select>
            {r.freq === 'W' ? <Giorni valore={r.byDay || []} onChange={(v) => setR({ byDay: v })} /> : null}
          </div>
          <div className="quando-linea">
            dal <input type="date" value={r.dal} onChange={(e) => setR({ dal: e.target.value })} />
            al <input type="date" value={r.al} min={r.dal || undefined} onChange={(e) => setR({ al: e.target.value })} />
            <Orari dalle={r.dalle} alle={r.alle} onChange={setR} />
          </div>
          <div className="quando-linea quando-tranne">
            tranne
            {(r.tranne || []).map((g) => (
              <span key={g} className="quando-chip">{breve(g)}
                <button type="button" title="Togli" onClick={() => setR({ tranne: r.tranne.filter((x) => x !== g) })}>×</button>
              </span>
            ))}
            <input type="date" value="" onChange={(e) => e.target.value && !r.tranne.includes(e.target.value) && setR({ tranne: [...r.tranne, e.target.value].sort() })} />
          </div>
        </div>
      )}

      {q.modo === 'periodo' && (
        <div className="quando-periodo">
          <div className="quando-linea">
            dal <input type="date" value={p.dal} onChange={(e) => setP({ dal: e.target.value })} />
            al <input type="date" value={p.al} min={p.dal || undefined} onChange={(e) => setP({ al: e.target.value })} />
          </div>
          <label className="quando-linea">
            <input type="checkbox" checked={!!p.orari} onChange={(e) => setP({ orari: e.target.checked })} />
            Con orari di apertura
          </label>
          {p.orari ? (
            <div className="quando-linea">
              <Giorni valore={p.byDay || []} onChange={(v) => setP({ byDay: v })} />
              <Orari dalle={p.apertoDalle} alle={p.apertoAlle} onChange={(x) => setP({
                ...(x.dalle !== undefined ? { apertoDalle: x.dalle } : {}), ...(x.alle !== undefined ? { apertoAlle: x.alle } : {}),
              })} />
            </div>
          ) : (
            <div className="quando-linea">
              <Orari dalle={p.dalle} alle={p.alle} onChange={setP} etichetta="inizia alle" />
              <span className="quando-nota">(ora del primo giorno; «alle»: dell’ultimo)</span>
            </div>
          )}
          <p className="quando-aiuto">I momenti speciali — vernissage, restituzione aperta al pubblico — vanno nel Programma dell’evento.</p>
        </div>
      )}

      {(q.modo === 'regola' || q.modo === 'piu') && anteprima.length ? (
        <div className="quando-anteprima">
          <button type="button" className="btn-link" onClick={() => setAperto(!aperto)}>
            {anteprima.length} {anteprima.length === 1 ? 'data' : 'date'}: {breve(anteprima[0])}
            {anteprima.length > 1 ? ` … ${breve(anteprima[anteprima.length - 1])}` : ''}
            {q.modo === 'regola' && !r.al ? ' (senza fine: anteprima dei primi due mesi)' : ''}
            <span className="material-symbols-outlined">{aperto ? 'expand_less' : 'expand_more'}</span>
          </button>
          {aperto ? <ul>{anteprima.map((g) => <li key={g}>{breve(g)}</li>)}</ul> : null}
        </div>
      ) : null}
    </div>
  );
}

export const quandoTester = rankWith(18, and(uiTypeIs('Control'), optionIs('quando', true)));
export default withJsonFormsControlProps(QuandoRenderer);
