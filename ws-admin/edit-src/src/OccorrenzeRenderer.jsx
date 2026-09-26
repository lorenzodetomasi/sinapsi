import { useContext, useEffect, useState } from 'react';
import { rankWith, and, uiTypeIs, optionIs } from '@jsonforms/core';
import { withJsonFormsControlProps, useJsonForms } from '@jsonforms/react';
import { EVENTS_INDEX_URL } from './config.js';
import { EditorContext } from './editorContext.js';
import { proponiId, percorsoDa } from './eventId.js';
import EntityPicker from './EntityPicker.jsx';

/* The occurrences of a series, each one a date that becomes a folder.
 *
 * A row says what is the occurrence's own - day, times, a place when it is
 * not the series' - and «Crea bozza» writes its folder with a draft index.json
 * holding only that, plus the link to the series: text, image, prices,
 * organizers come from the series (ws-admin/lib/event-inherit.php). The draft
 * is then opened in a new tab, where it can be changed like any event.
 *
 * With no occurrence yet, «Crea la prima occorrenza» does the same from the
 * series' own start: the quickest way from a series to its first evening. */

const ARCHIVIO_URL = EVENTS_INDEX_URL.replace(/\.json$/, '.archive.json');
async function percorsiEsistenti() {
  const leggi = (u) => fetch(u, { cache: 'no-store' }).then((r) => (r.ok ? r.json() : [])).catch(() => []);
  const [a, b] = await Promise.all([leggi(EVENTS_INDEX_URL), leggi(ARCHIVIO_URL)]);
  const tutte = [...(Array.isArray(a) ? a : a?.events || []), ...(Array.isArray(b) ? b : b?.events || [])];
  return new Set(tutte.map((e) => e?.path).filter(Boolean));
}

const MESI = ['gen', 'feb', 'mar', 'apr', 'mag', 'giu', 'lug', 'ago', 'set', 'ott', 'nov', 'dic'];
const breve = (g) => {
  if (!g) return '';
  const d = new Date(g + 'T12:00:00');
  return `${['dom', 'lun', 'mar', 'mer', 'gio', 'ven', 'sab'][d.getDay()]} ${d.getDate()} ${MESI[d.getMonth()]}`;
};
/** Rows written before they had a day: the day is in their @id (20261022T1800-…). */
const dalId = (id) => {
  const m = /(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})/.exec(String(id || ''));
  return m ? { giorno: `${m[1]}-${m[2]}-${m[3]}`, dalle: `${m[4]}:${m[5]}` } : {};
};
const riga = (o) => {
  const r = { id: '', name: '', giorno: '', dalle: '', alle: '', luogo: { id: '', name: '' }, ...o };
  if (!r.giorno) Object.assign(r, dalId(r.id), r.dalle ? { dalle: r.dalle } : {});
  if (!r.luogo) r.luogo = { id: '', name: '' };
  return r;
};

function OccorrenzeRenderer({ data, handleChange, path, visible, label }) {
  const ctx = useJsonForms();
  const editor = useContext(EditorContext);
  const serie = ctx?.core?.data || {};
  const [esistono, setEsistono] = useState(null);
  const [lavoro, setLavoro] = useState({});   // index -> 'creo' | 'ok' | error text
  useEffect(() => { percorsiEsistenti().then(setEsistono); }, []);
  if (visible === false || serie.primaryType !== 'EventSeries') return null;

  const righe = (Array.isArray(data) ? data : []).map(riga);
  const scrivi = (r) => handleChange(path, r);
  const setRiga = (i, x) => scrivi(righe.map((o, j) => (j === i ? { ...o, ...x } : o)));
  const serieId = percorsoDa(serie.id);
  const idPer = (o) => (o.id ? percorsoDa(o.id) : proponiId({
    primaryType: 'Event', name: serie.name,
    startDate: o.giorno ? `${o.giorno}T${o.dalle || '00:00'}` : '',
    location: o.luogo?.id ? o.luogo : serie.location, superEvent: serieId,
  }));
  const esiste = (o) => !!(esistono && o.id && esistono.has(percorsoDa(o.id)));

  async function crea(i, lista = righe) {
    const o = lista[i];
    if (!editor?.creaBozza) return;
    if (!serieId) { setLavoro((l) => ({ ...l, [i]: 'Salva prima la collezione: le serve un @id.' })); return; }
    const id = idPer(o);
    if (!id) { setLavoro((l) => ({ ...l, [i]: 'Servono il giorno e un luogo (della collezione o della riga).' })); return; }
    setLavoro((l) => ({ ...l, [i]: 'creo' }));
    const fuso = serie.timezone || Intl.DateTimeFormat().resolvedOptions().timeZone;
    const bozza = {
      '@context': ['https://schema.org', { meetoo: 'https://meetoo.eu#' }],
      '@id': id,
      '@type': 'Event',
      startDate: o.dalle ? `${o.giorno}T${o.dalle}` : o.giorno,
      ...(o.alle ? { endDate: `${o.giorno}T${o.alle}` } : {}),
      'meetoo:timezone': fuso,
      ...(o.luogo?.id ? { location: { '@id': o.luogo.id, '@type': 'Place', ...(o.luogo.name ? { name: o.luogo.name } : {}) } } : {}),
      superEvent: { '@id': serieId, '@type': 'EventSeries' },
      eventStatus: 'https://schema.org/EventScheduled',
    };
    const esito = await editor.creaBozza(id, bozza);
    if (esito.ok) {
      const nuove = lista.map((x, j) => (j === i ? { ...x, id } : x));
      scrivi(nuove);
      setEsistono((s) => new Set([...(s || []), id]));
      setLavoro((l) => ({ ...l, [i]: 'ok' }));
      window.open(`?id=${encodeURIComponent(id)}${editor.site ? '&site=' + encodeURIComponent(editor.site) : ''}`, '_blank', 'noopener');
    } else {
      setLavoro((l) => ({ ...l, [i]: esito.error }));
    }
  }

  const prima = () => {
    const g = /^(\d{4}-\d{2}-\d{2})/.exec(serie.startDate || '')?.[1] || new Date().toISOString().slice(0, 10);
    const h = /T(\d{2}:\d{2})/.exec(serie.startDate || '')?.[1] || '';
    const nuove = [riga({ giorno: g, dalle: h })];
    scrivi(nuove);
    crea(0, nuove);
  };
  const mancanti = righe.map((o, i) => [o, i]).filter(([o]) => o.giorno && !esiste(o));

  return (
    <div className="occorrenze">
      <div className="occ-testa">
        <span className="material-symbols-outlined">event_repeat</span>
        <span className="field-label">{label || 'Occorrenze'}</span>
        {righe.length ? <span className="quando-nota">{righe.length} {righe.length === 1 ? 'data' : 'date'}</span> : null}
      </div>
      {!righe.length ? (
        <div className="occ-vuoto">
          <p>Nessuna occorrenza. La prima nasce dalla data d’inizio della collezione e ne eredita testo, immagine, prezzi e organizzatori: poi la modifichi come vuoi.</p>
          <button type="button" className="btn-ghost" onClick={prima} disabled={!editor?.loggato}>
            <span className="material-symbols-outlined">add_circle</span>Crea la prima occorrenza
          </button>
          {!editor?.loggato ? <span className="place-status place-warn">Accedi con Google per creare le cartelle.</span> : null}
        </div>
      ) : (
        <div className="occ-righe">
          {righe.map((o, i) => {
            const stato = lavoro[i];
            const c = esiste(o);
            return (
              <div className="occ-riga" key={i}>
                <input type="date" value={o.giorno} disabled={c} onChange={(e) => setRiga(i, { giorno: e.target.value })} />
                <span className="quando-giorno">{breve(o.giorno)}</span>
                <span className="quando-orari">
                  ore <input type="time" value={o.dalle} disabled={c} onChange={(e) => setRiga(i, { dalle: e.target.value })} />
                  – <input type="time" value={o.alle} disabled={c} onChange={(e) => setRiga(i, { alle: e.target.value })} />
                </span>
                <span className="occ-luogo">
                  <EntityPicker
                    value={o.luogo?.name || ''}
                    ambito="venue"
                    placeholder={serie.location?.name ? `come la collezione (${serie.location.name})` : 'Luogo…'}
                    onChange={(v) => setRiga(i, { luogo: v ? { ...o.luogo, name: v } : { id: '', name: '' } })}
                    onPickSite={(e) => setRiga(i, { luogo: { id: e['@id'], name: e.name } })}
                  />
                </span>
                <span className="occ-stato">
                  {c ? (
                    <a className="btn-ghost" href={`?id=${encodeURIComponent(percorsoDa(o.id))}${editor?.site ? '&site=' + encodeURIComponent(editor.site) : ''}`} target="_blank" rel="noopener">
                      <span className="material-symbols-outlined">open_in_new</span>Apri
                    </a>
                  ) : (
                    <button type="button" className="btn-ghost" disabled={stato === 'creo' || !o.giorno || !editor?.loggato} onClick={() => crea(i)}>
                      <span className="material-symbols-outlined">create_new_folder</span>{stato === 'creo' ? 'Creo…' : 'Crea bozza'}
                    </button>
                  )}
                  <button type="button" className="btn-icona" title="Togli dall’elenco (la cartella, se c’è, resta)"
                    onClick={() => scrivi(righe.filter((_, j) => j !== i))}>
                    <span className="material-symbols-outlined">close</span>
                  </button>
                </span>
                {stato && stato !== 'creo' && stato !== 'ok' ? <span className="place-status place-warn occ-errore">⚠️ {stato}</span> : null}
              </div>
            );
          })}
          <div className="occ-azioni">
            <button type="button" className="btn-ghost" onClick={() => {
              const u = righe[righe.length - 1];
              scrivi([...righe, riga({ dalle: u?.dalle || '', alle: u?.alle || '' })]);
            }}>
              <span className="material-symbols-outlined">add</span>Aggiungi una data
            </button>
            {mancanti.length > 1 ? (
              <button type="button" className="btn-ghost" disabled={!editor?.loggato}
                onClick={async () => { for (const [, i] of mancanti) await crea(i); }}>
                <span className="material-symbols-outlined">create_new_folder</span>Crea le {mancanti.length} bozze mancanti
              </button>
            ) : null}
          </div>
          <p className="quando-aiuto">Ogni occorrenza scrive solo quello che è suo (data, orari, luogo se diverso); il resto lo eredita dalla collezione. Quando una data è passata, conserva i valori che aveva.</p>
        </div>
      )}
    </div>
  );
}

export const occorrenzeTester = rankWith(18, and(uiTypeIs('Control'), optionIs('occorrenze', true)));
export default withJsonFormsControlProps(OccorrenzeRenderer);
