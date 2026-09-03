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
import AgeRangeRenderer, { ageRangeTester } from './AgeRangeRenderer.jsx';
import RiferimentoRenderer, { riferimentoTester } from './RiferimentoRenderer.jsx';
import EntityPicker from './EntityPicker.jsx';
import JsonValidationPane from './JsonValidationPane.jsx';
import DiffModal from './DiffModal.jsx';
import { diffForm, mergeChoices } from './diff.js';
import { API_BASE } from './config.js';
import { completaId, idIncompleto } from './placeId.js';

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
  { tester: ageRangeTester, renderer: AgeRangeRenderer },
  { tester: riferimentoTester, renderer: RiferimentoRenderer },
];

const RADICE = window.location.pathname.replace(/\/ws-admin\/.*/, '/');
const PLACES_URL = RADICE + 'ws-admin/places/google_place-json.php';

/** Il convertitore/validatore condiviso (json-xml): la stessa risposta che vede
 *  l'editor degli eventi. Un secondo validatore, con regole sue, direbbe cose
 *  diverse sullo stesso documento — che è il modo più sicuro per non fidarsi di
 *  nessuno dei due. */
async function apiJson(action, campi = {}) {
  const r = await fetch(API_BASE, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ action, ...campi }).toString(),
  });
  return r.json();
}

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
  const [cerca, setCerca] = useState('');
  const [grezzoGoogle, setGrezzo] = useState(null);
  const [modale, setModale] = useState(false);
  const [differenze, setDifferenze] = useState([]);
  const [scelte, setScelte] = useState(new Set());
  const [validazione, setValidazione] = useState({ status: 'idle', errors: [] });
  const [confronto, setConfronto] = useState(null);   // { changes, rel } — il diff col salvato
  const seq = useRef(0);
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

  /* L'@id con la regione vuota — `places//statuadinettuno` — arriva da Google
   * quando Google non dà il CAP: il server lo lascia rotto apposta, così non si
   * salva una scheda a un indirizzo sbagliato. Ma il CAP, di solito, chi compila
   * lo sa: appena lo scrive nel modulo, l'indirizzo si ricompone da sé. Finché
   * non c'è, il segnale resta — ed è giusto che resti, perché manca davvero
   * qualcosa. */
  useEffect(() => {
    if (!idIncompleto(data.id)) return;
    const riparato = completaId(data.id, data.addressCountry, data.postalCode);
    if (riparato !== data.id) setData((d) => ({ ...d, id: riparato }));
  }, [data.id, data.addressCountry, data.postalCode]);

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
  const payload = useMemo(() => JSON.stringify(jsonld, null, 2), [jsonld]);

  /* Il validatore: la stessa chiamata dell'editor eventi, con lo stesso pannello.
   * Gira a ogni modifica, con un respiro, perché un errore trovato al salvataggio
   * è un errore trovato tardi. */
  const rivalida = useCallback(async (corrente) => {
    const n = ++seq.current;
    try {
      const out = await apiJson('validate_json', { payload: corrente });
      if (n !== seq.current) return;   // risposta vecchia: si scarta
      setValidazione(out.valid ? { status: 'valid', errors: [] } : { status: 'invalid', errors: out.errors || [] });
    } catch {
      if (n === seq.current) setValidazione({ status: 'unreachable', errors: [] });
    }
  }, []);
  useEffect(() => {
    const t = setTimeout(() => rivalida(payload), 500);
    return () => clearTimeout(t);
  }, [payload, rivalida]);

  /** Una voce scelta fra quelle del SITO: si apre quella, non se ne fa una nuova. */
  const dalSito = useCallback(async (id) => {
    setOccupato(true);
    try {
      const r = await api('load', { id });
      if (r.error) { avvisa(r.error, 'ko'); return; }
      setGrezzo(null);
      setDifferenze([]);
      adotta(r.json, true);
      avvisa(`Aperta ${id}.`);
    } catch {
      avvisa('Il server non risponde.', 'ko');
    } finally {
      setOccupato(false);
    }
  }, [adotta, avvisa]);

  /* CERCA SU GOOGLE. Il backend restituisce una scheda già in forma schema.org
   * (`ws_cms`): si adotta quella, tenendo però l'@id che c'è — cambiare indirizzo
   * a un luogo già pubblicato romperebbe ogni collegamento che gli punta. */
  const daGoogle = useCallback(async (placeIdScelto) => {
    const query = cerca.trim();
    const placeId = placeIdScelto || data.googlePlaceId;
    if (!query && !placeId) { avvisa('Scegli un luogo dall’elenco, o carica una scheda che abbia già un Google Place ID.', 'ko'); return; }
    setOccupato(true);
    try {
      const r = await api('search', placeId ? { place_id: placeId, query } : { query });
      setGrezzo(r.raw_google || null);
      /* Le differenze fra quello che dice Google e quello che è già salvato: si
       * mostrano nel modale con una casella ciascuna, perché «aggiorna da Google»
       * quasi mai vuol dire «prendi tutto» — il nome buono è spesso il nostro. */
      const diff = Array.isArray(r.updates) ? r.updates.filter((u) => u && u.path) : [];
      setDifferenze(diff);
      setScelte(new Set(diff.map((u) => u.path)));
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
  /* Prima di sovrascrivere: che cosa cambia rispetto al salvato. È la stessa
   * domanda che faceva l'importatore, con lo stesso pannello — e la si fa PRIMA
   * di scrivere, che è l'unico momento in cui serve. */
  const confronta = useCallback(async () => {
    if (!esisteSulServer || !data.id) return null;
    try {
      const r = await api('load', { id: data.id });
      if (r.error || !r.json) return null;
      const prima = daJsonLd(r.json, tipo);
      const cambi = diffForm(prima, data);
      return cambi.length ? { changes: cambi, rel: data.id } : null;
    } catch { return null; }
  }, [esisteSulServer, data, tipo]);

  const salva = useCallback(async (soloQuesti) => {
    if (!data.id) { avvisa('Manca l’indirizzo della scheda (@id).', 'ko'); return; }
    if (!data.name) { avvisa('Manca il nome.', 'ko'); return; }
    setOccupato(true);
    try {
      const parziale = Array.isArray(soloQuesti) && soloQuesti.length > 0;
      const r = await api('save', {
        jsonld: JSON.stringify(jsonld),
        mode: parziale ? 'merge' : (esisteSulServer ? 'overwrite' : ''),
        ...(parziale ? { paths: soloQuesti } : {}),
      });
      if (r.error) { avvisa(r.error, 'ko'); return; }
      if (r.needs_confirm) {
        avvisa('Esiste già una scheda a questo indirizzo. Aprila e modificala, oppure cambia l’@id.', 'ko');
        return;
      }
      setEsiste(true);
      setDifferenze([]);
      avvisa(Array.isArray(soloQuesti) && soloQuesti.length ? `Integrati ${soloQuesti.length} campi.` : 'Salvata sul server.');
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

          {/* Lo STESSO campo del «Nome» degli Organizzatori: prima quello che c'è
              già sul sito, poi — solo se il sito non ha già la risposta — quello
              che dice Google. La domanda «c'è già?» viene prima di «esiste?»,
              altrimenti si creano doppioni di cose che abbiamo già. */}
          <div className="scheda-cerca">
            <EntityPicker
              value={cerca}
              /* `organizer` e non `venue`: `venue` vuol dire «solo i luoghi», e in
                 questa pagina si modificano anche le organizzazioni — cercando
                 «Apecultura» non la trovava per costruzione. */
              ambito="organizer"
              placeholder={tipo === 'gruppo' ? 'Cerca un gruppo già sul sito…' : 'Cerca sul sito o su Google Maps…'}
              onChange={setCerca}
              onPickSite={(e) => dalSito(e['@id'])}
              onPickGoogle={({ placeId }) => daGoogle(placeId)}
            />
          </div>

          {data.googlePlaceId && tipo !== 'gruppo' && (
            <button type="button" className="btn-ghost" onClick={() => daGoogle()} disabled={occupato} title="Rilegge la scheda da Google con il Place ID salvato">
              <span className="material-symbols-outlined">travel_explore</span> Aggiorna da Google
            </button>
          )}

          {urlPubblica && (
            <a className="btn-ghost" href={urlPubblica} target="_blank" rel="noopener" title="Vedi la pagina">
              <span className="material-symbols-outlined">visibility</span>
            </a>
          )}
        </div>
      </div>

      <div className="pane">
        <JsonForms
          schema={schema}
          uischema={uischema}
          data={data}
          renderers={renderers}
          cells={vanillaCells}
          onChange={({ data: d }) => setData(d)}
        />
      </div>

      {/* I DUE JSON, uno accanto all'altro: a sinistra quello che ha detto Google,
          a destra quello che finirà nel file. È il confronto per cui finora si
          apriva l'importatore — con la differenza che qui la colonna di destra
          non è un'anteprima ma esattamente ciò che si sta per salvare. */}
      {modale && (
        <div className="modal-overlay" onMouseDown={(e) => { if (e.target === e.currentTarget) setModale(false); }}>
          <div className="modal-box modal-json" role="dialog" aria-modal="true" aria-label="I due JSON">
            <div className="modal-head">
              <strong>Che cosa dice Google, e che cosa salviamo</strong>
              <button type="button" className="icon-btn" onClick={() => setModale(false)} title="Chiudi">
                <span className="material-symbols-outlined">close</span>
              </button>
            </div>
            <div className="modal-json-corpo">
              <section>
                <h3>Da Google Maps</h3>
                {grezzoGoogle ? (
                  <pre>{JSON.stringify(grezzoGoogle, null, 2)}</pre>
                ) : (
                  <p className="modal-vuoto">
                    Niente da mostrare: questa scheda non è stata (ancora) letta da Google
                    in questa sessione. Cercala qui sopra, oppure usa «Aggiorna da Google».
                  </p>
                )}
              </section>
              <section>
                <h3>Quello che salviamo</h3>
                <pre>{payload}</pre>
              </section>
              <section className="pane-validation">
                <JsonValidationPane
                  payload={payload}
                  validation={validazione}
                  onRevalidate={() => rivalida(payload)}
                />
              </section>
            </div>
            {differenze.length > 0 && (
              <div className="modal-diff">
                <h3>Che cosa cambierebbe, e che cosa prendiamo</h3>
                <ul>
                  {differenze.map((u) => (
                    <li key={u.path}>
                      <label>
                        <input
                          type="checkbox"
                          checked={scelte.has(u.path)}
                          onChange={(e) => {
                            const n = new Set(scelte);
                            if (e.target.checked) n.add(u.path); else n.delete(u.path);
                            setScelte(n);
                          }}
                        />
                        <span className="diff-campo">{u.field}</span>
                        <span className="diff-vecchio">{u.old || '—'}</span>
                        <span className="diff-freccia" aria-hidden="true">→</span>
                        <span className="diff-nuovo">{u.new}</span>
                      </label>
                    </li>
                  ))}
                </ul>
                <div className="modal-diff-azioni">
                  <button type="button" className="btn-ghost" onClick={() => setScelte(new Set(differenze.map((u) => u.path)))}>Tutti</button>
                  <button type="button" className="btn-ghost" onClick={() => setScelte(new Set())}>Nessuno</button>
                  <button
                    type="button"
                    className="primary"
                    disabled={occupato || scelte.size === 0}
                    onClick={() => { salva([...scelte]); setModale(false); }}
                  >
                    Integra i campi scelti
                  </button>
                </div>
              </div>
            )}
          </div>
        </div>
      )}

      <DiffModal
        open={!!confronto}
        changes={confronto?.changes || []}
        relPath={confronto?.rel || ''}
        saving={occupato}
        onConfirm={(tieniIlLoro) => {
          const unito = mergeChoices(data, confronto.changes, tieniIlLoro);
          setData(unito);
          setConfronto(null);
          // Un giro di render, poi si salva: `jsonld` si ricalcola da `data`.
          setTimeout(() => salva(), 0);
        }}
        onCancel={() => { if (!occupato) setConfronto(null); }}
      />

      <div className="ed-foot">
        <div className="ed-foot-sx">
          <button
            type="button"
            className={modale ? 'ed-toggle active' : 'ed-toggle'}
            onClick={() => setModale(true)}
            title="Confronta il JSON di Google con quello che verrà salvato"
          >
            <span className="material-symbols-outlined">data_object</span> I due JSON
          </button>
          <span className={'ed-stato ed-stato-' + validazione.status} title={
            validazione.status === 'valid' ? 'JSON-LD valido'
              : validazione.status === 'invalid' ? validazione.errors.length + ' problemi'
              : validazione.status === 'unreachable' ? 'Validatore non raggiungibile' : 'Controllo…'
          }>
            <span className="material-symbols-outlined">
              {validazione.status === 'valid' ? 'check_circle'
                : validazione.status === 'invalid' ? 'error'
                : validazione.status === 'unreachable' ? 'cloud_off' : 'hourglass_empty'}
            </span>
            {validazione.status === 'invalid' ? validazione.errors.length : ''}
          </span>
          {msg && <span className={msg.genere === 'ko' ? 'flash ko' : 'flash ok'}>{msg.testo}</span>}
        </div>
        <div className="ed-foot-dx">
          <button
            type="button"
            className="primary"
            disabled={occupato}
            onClick={async () => {
              const c = await confronta();
              if (c) setConfronto(c); else salva();
            }}
          >
            <span className="material-symbols-outlined">cloud_upload</span> Salva sul web
          </button>
        </div>
      </div>
    </div>
  );
}
