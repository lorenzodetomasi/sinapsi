import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { JsonForms } from '@jsonforms/react';
import { vanillaRenderers, vanillaCells } from '@jsonforms/vanilla-renderers';
import { schemaPer } from './schedaSchema.js';
import { daJsonLd, aJsonLd, docVuoto, entitaDi } from './schedaAdapter.js';
import XhtmlRichTextRenderer, { xhtmlControlTester } from './XhtmlRichTextRenderer.jsx';
import SeoDescrizioneRenderer, { seoDescrizioneTester } from './SeoDescrizioneRenderer.jsx';
import LabeledEnumRenderer, { labeledEnumTester } from './LabeledEnumRenderer.jsx';
import ImageUploadRenderer, { imageUploadTester } from './ImageUploadRenderer.jsx';
import TagArrayRenderer, { tagArrayTester } from './TagArrayRenderer.jsx';
import RepeatableObjectRenderer, { repeatableObjectTester } from './RepeatableObjectRenderer.jsx';
import MultiSelectRenderer, { multiSelectTester } from './MultiSelectRenderer.jsx';
import ComputedRenderer, { computedTester } from './ComputedRenderer.jsx';
import FieldRowRenderer, { fieldRowTester } from './FieldRowRenderer.jsx';
import IconTextRenderer, { iconTextTester } from './IconTextRenderer.jsx';
import GroupRenderer, { groupTester } from './GroupRenderer.jsx';

/* L'editor delle SCHEDE: un luogo, un'attività, un gruppo.
 *
 * Guscio suo, ma tutto il resto è in comune con l'editor degli eventi: gli stessi
 * renderer, lo stesso `form.css` (quindi la stessa impaginazione dei campi) e lo
 * stesso backend — `places/google_place-json.php`, che sa già caricare dal server,
 * cercare su Google, riconoscere i doppioni e scaricare le immagini. Qui non si
 * riscrive niente di tutto questo: gli si mette davanti una faccia.
 *
 * Il guscio resterà doppio finché non lo estrarremo da tutti e due; è la scelta
 * che abbiamo fatto apposta, per non mettere in cantiere l'editor che si usa ogni
 * giorno prima di avere un secondo esempio vero sotto mano.
 */

const renderers = [
  ...vanillaRenderers,
  { tester: groupTester, renderer: GroupRenderer },
  { tester: xhtmlControlTester, renderer: XhtmlRichTextRenderer },
  { tester: seoDescrizioneTester, renderer: SeoDescrizioneRenderer },
  { tester: labeledEnumTester, renderer: LabeledEnumRenderer },
  { tester: imageUploadTester, renderer: ImageUploadRenderer },
  { tester: tagArrayTester, renderer: TagArrayRenderer },
  { tester: repeatableObjectTester, renderer: RepeatableObjectRenderer },
  { tester: multiSelectTester, renderer: MultiSelectRenderer },
  { tester: computedTester, renderer: ComputedRenderer },
  { tester: fieldRowTester, renderer: FieldRowRenderer },
  { tester: iconTextTester, renderer: IconTextRenderer },
];

const RADICE = window.location.pathname.replace(/\/ws-admin\/.*/, '/');
const PLACES_URL = RADICE + 'ws-admin/places/google_place-json.php';

/** Il backend parla JSON: una funzione sola per tutte le sue azioni. */
async function api(action, campi = {}) {
  const credential = window.meetooSession?.getToken?.() || '';
  const r = await fetch(PLACES_URL, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action, credential, ...campi }),
  });
  return r.json();
}

export default function AppScheda() {
  const q = new URLSearchParams(window.location.search);
  const idIniziale = q.get('id') || '';
  const tipoRichiesto = q.get('tipo') === 'gruppo' ? 'gruppo' : '';

  const [tipo, setTipo] = useState(tipoRichiesto || (idIniziale.startsWith('organizations/') ? 'gruppo' : 'luogo'));
  const [doc, setDoc] = useState(() => docVuoto(tipoRichiesto || 'luogo'));
  const [data, setData] = useState(() => daJsonLd(docVuoto(tipoRichiesto || 'luogo'), tipoRichiesto || 'luogo'));
  const [esisteSulServer, setEsiste] = useState(false);
  const [msg, setMsg] = useState(null);
  const [occupato, setOccupato] = useState(false);
  const [mostraJson, setMostraJson] = useState(false);
  const [cerca, setCerca] = useState('');
  const [urlPubblica, setUrlPubblica] = useState('');
  const timerMsg = useRef(0);

  const { schema, uischema } = useMemo(() => schemaPer(tipo === 'gruppo' ? 'organizations/x' : 'places/x'), [tipo]);

  const avvisa = useCallback((testo, genere = 'ok') => {
    setMsg({ testo, genere });
    clearTimeout(timerMsg.current);
    timerMsg.current = setTimeout(() => setMsg(null), 5000);
  }, []);

  /** Prende un documento e ci porta dentro il modulo. */
  const adotta = useCallback((nuovoDoc, esiste) => {
    const e = entitaDi(nuovoDoc);
    const t = String(e['@id'] || '').startsWith('organizations/') ? 'gruppo' : 'luogo';
    setTipo(t);
    setDoc(nuovoDoc);
    setData(daJsonLd(nuovoDoc, t));
    setEsiste(!!esiste);
  }, []);

  // All'avvio: se l'indirizzo porta un @id, si carica quella scheda dal server.
  // Si aspetta la sessione, perché il backend vuole il token a ogni richiesta.
  useEffect(() => {
    if (!idIniziale) return;
    let fermo = false;
    (function attendi() {
      if (fermo) return;
      if (!window.meetooSession?.getToken?.()) { setTimeout(attendi, 200); return; }
      setOccupato(true);
      api('load', { id: idIniziale })
        .then((r) => {
          if (fermo) return;
          if (r.error) { avvisa(r.error, 'ko'); return; }
          adotta(r.json, true);
        })
        .catch(() => avvisa('Il server non risponde.', 'ko'))
        .finally(() => setOccupato(false));
    })();
    return () => { fermo = true; };
  }, [idIniziale, adotta, avvisa]);

  // L'indirizzo pubblico, per l'occhio. Lo sa la mappa del sito, non noi.
  useEffect(() => {
    const id = data.id;
    if (!id) { setUrlPubblica(''); return; }
    let fermo = false;
    (function attendi() {
      if (fermo) return;
      if (!window.Meetoo?.urlPagina) { setTimeout(attendi, 200); return; }
      window.Meetoo.urlPagina(id).then((u) => { if (!fermo) setUrlPubblica(u || ''); });
    })();
    return () => { fermo = true; };
  }, [data.id]);

  const jsonld = useMemo(() => aJsonLd(data, doc, tipo), [data, doc, tipo]);

  /* CERCA SU GOOGLE. Il backend restituisce una scheda già in forma schema.org
   * (`ws_cms`): si adotta quella, tenendo però l'@id che c'è — cambiare indirizzo
   * a un luogo già pubblicato romperebbe ogni collegamento che gli punta. */
  const daGoogle = useCallback(async () => {
    const query = cerca.trim();
    const placeId = data.googlePlaceId;
    if (!query && !placeId) { avvisa('Scrivi il nome del luogo, o carica una scheda che abbia già un Google Place ID.', 'ko'); return; }
    setOccupato(true);
    try {
      const r = await api('search', placeId ? { place_id: placeId, query } : { query });
      if (r.error) { avvisa(r.error, 'ko'); return; }
      const trovato = r.ws_cms;
      if (!trovato) { avvisa('Google non ha restituito una scheda.', 'ko'); return; }
      const idPrima = data.id;
      const e = entitaDi(trovato);
      if (idPrima) { e['@id'] = idPrima; trovato['@id'] = idPrima; }
      adotta(trovato, esisteSulServer || !!r.id_exists);
      avvisa(r.id_same_place ? 'Scheda aggiornata da Google (stesso luogo già in archivio).' : 'Scheda compilata da Google. Controlla prima di salvare.');
    } catch {
      avvisa('Il server non risponde.', 'ko');
    } finally {
      setOccupato(false);
    }
  }, [cerca, data.googlePlaceId, data.id, esisteSulServer, adotta, avvisa]);

  /* SALVA. Su una scheda caricata dal server si sovrascrive: l'hai vista e l'hai
   * cambiata apposta. Su una nuova si lascia decidere al backend, che sa
   * riconoscere un doppione meglio di noi (ha l'indice dei Google Place ID). */
  const salva = useCallback(async () => {
    if (!data.id) { avvisa('Manca l’indirizzo della scheda (@id).', 'ko'); return; }
    if (!data.name) { avvisa('Manca il nome.', 'ko'); return; }
    setOccupato(true);
    try {
      const r = await api('save', { jsonld: JSON.stringify(jsonld), mode: esisteSulServer ? 'overwrite' : '' });
      if (r.error) { avvisa(r.error, 'ko'); return; }
      if (r.needs_confirm) {
        avvisa('Esiste già una scheda a questo indirizzo. Aprila e modificala, oppure cambia l’@id.', 'ko');
        return;
      }
      setEsiste(true);
      avvisa('Salvata sul server.');
    } catch {
      avvisa('Il server non risponde.', 'ko');
    } finally {
      setOccupato(false);
    }
  }, [data.id, data.name, jsonld, esisteSulServer, avvisa]);

  const titolo = tipo === 'gruppo' ? 'Gruppo' : 'Luogo';
  const elenco = RADICE + 'ws-admin/';

  return (
    <div className="app app-scheda">
      <div className="appbar">
        <div className="appbar-actions">
          <nav className="crumbs">
            <a href={elenco}>Gestione</a>
            <span aria-hidden="true">›</span>
            <span>{data.name || `${titolo} senza nome`}</span>
          </nav>

          {tipo !== 'gruppo' && (
            <>
              <input
                type="text"
                className="modal-input"
                style={{ flex: '1 1 16rem', minWidth: 0 }}
                placeholder="Cerca su Google Maps: nome e città…"
                value={cerca}
                onChange={(e) => setCerca(e.target.value)}
                onKeyDown={(e) => { if (e.key === 'Enter') daGoogle(); }}
              />
              <button type="button" className="btn-ghost" onClick={daGoogle} disabled={occupato}>
                <span className="material-symbols-outlined">travel_explore</span>
                {data.googlePlaceId ? 'Aggiorna da Google' : 'Cerca su Google'}
              </button>
            </>
          )}

          {urlPubblica && (
            <a className="btn-ghost" href={urlPubblica} target="_blank" rel="noopener" title="Vedi la pagina">
              <span className="material-symbols-outlined">visibility</span>
            </a>
          )}
        </div>
      </div>

      <div className="pane">
        {mostraJson ? (
          <div className="code-pane">
            <h2>JSON-LD</h2>
            <pre style={{ whiteSpace: 'pre-wrap', overflowWrap: 'anywhere' }}>
              {JSON.stringify(jsonld, null, 2)}
            </pre>
          </div>
        ) : (
          <JsonForms
            schema={schema}
            uischema={uischema}
            data={data}
            renderers={renderers}
            cells={vanillaCells}
            onChange={({ data: d }) => setData(d)}
          />
        )}
      </div>

      <div className="ed-foot">
        <div className="ed-foot-sx">
          <button
            type="button"
            className={mostraJson ? 'ed-toggle active' : 'ed-toggle'}
            onClick={() => setMostraJson((v) => !v)}
            title="Mostra il JSON-LD che verrà salvato"
          >
            <span className="material-symbols-outlined">data_object</span> JSON-LD
          </button>
          {msg && <span className={msg.genere === 'ko' ? 'flash ko' : 'flash ok'}>{msg.testo}</span>}
        </div>
        <div className="ed-foot-dx">
          <button type="button" className="primary" onClick={salva} disabled={occupato}>
            <span className="material-symbols-outlined">cloud_upload</span> Salva sul web
          </button>
        </div>
      </div>
    </div>
  );
}
