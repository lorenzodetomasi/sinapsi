import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { JsonForms } from '@jsonforms/react';
import { vanillaRenderers, vanillaCells } from '@jsonforms/vanilla-renderers';
import { schemaPer } from './paginaSchema.js';
import { daJsonLd, aJsonLd, docVuoto } from './paginaAdapter.js';
import XhtmlRichTextRenderer, { xhtmlControlTester } from './XhtmlRichTextRenderer.jsx';
import SeoDescrizioneRenderer, { seoDescrizioneTester } from './SeoDescrizioneRenderer.jsx';
import LabeledEnumRenderer, { labeledEnumTester } from './LabeledEnumRenderer.jsx';
import TagArrayRenderer, { tagArrayTester } from './TagArrayRenderer.jsx';
import MultiSelectRenderer, { multiSelectTester } from './MultiSelectRenderer.jsx';
import RepeatableObjectRenderer, { repeatableObjectTester } from './RepeatableObjectRenderer.jsx';
import FieldRowRenderer, { fieldRowTester } from './FieldRowRenderer.jsx';
import IconTextRenderer, { iconTextTester } from './IconTextRenderer.jsx';
import GroupRenderer, { groupTester } from './GroupRenderer.jsx';
import JsonValidationPane from './JsonValidationPane.jsx';
import { linguaggio } from './linguaggi.js';
import { API_BASE } from './config.js';

/*
 * L'editor delle PAGINE: la terza applicazione costruita da questa sorgente,
 * dopo gli eventi e le schede.
 *
 * Non riscrive niente di ciò che c'è: gli stessi renderer, lo stesso `form.css`
 * — quindi la stessa impaginazione dei campi — e lo stesso modo di parlare col
 * server. Quello che è suo sono lo schema, l'adattatore e un backend che sa una
 * cosa che gli altri due non devono sapere: salvare una pagina vuol dire rifare
 * le mappe, perché una pagina è dove il sito risponde e le mappe sono la sua
 * tabella di instradamento.
 *
 * Il guscio resta il terzo guscio. È la stessa scelta dichiarata in AppScheda:
 * non si estrae l'impalcatura comune prima di avere sotto mano abbastanza
 * esempi da sapere che cos'è davvero comune. Adesso ce ne sono tre, ed è
 * l'estrazione che viene dopo — non prima, e non insieme a questa.
 */

const renderers = [
  ...vanillaRenderers,
  { tester: groupTester, renderer: GroupRenderer },
  { tester: xhtmlControlTester, renderer: XhtmlRichTextRenderer },
  { tester: seoDescrizioneTester, renderer: SeoDescrizioneRenderer },
  { tester: labeledEnumTester, renderer: LabeledEnumRenderer },
  { tester: tagArrayTester, renderer: TagArrayRenderer },
  { tester: multiSelectTester, renderer: MultiSelectRenderer },
  /* Le sezioni sono un elenco di oggetti: senza questo le disegna il renderer
   * generico, che ne fa una tabella con sei colonne strette e illeggibili. */
  { tester: repeatableObjectTester, renderer: RepeatableObjectRenderer },
  { tester: fieldRowTester, renderer: FieldRowRenderer },
  { tester: iconTextTester, renderer: IconTextRenderer },
];

const RADICE = window.location.pathname.replace(/\/ws-admin\/.*/, '/');
const SALVA_URL = RADICE + 'ws-admin/pages/save-page.php';
const ELENCO_URL = RADICE + 'ws-admin/pages/';

/* Il convertitore/validatore condiviso (json-xml): la stessa risposta che vedono
 * l'editor degli eventi e quello delle schede. Un secondo validatore, con regole
 * sue, direbbe cose diverse sullo stesso documento — che è il modo più sicuro
 * per non fidarsi di nessuno dei due. */
async function apiJson(action, campi = {}) {
  const r = await fetch(API_BASE, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ action, ...campi }).toString(),
  });
  return r.json();
}

async function api(action, campi = {}) {
  const credential = window.meetooSession?.getToken?.() || '';
  const r = await fetch(SALVA_URL, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action, credential, ...campi }),
  });
  return r.json().catch(() => ({ error: 'Il server ha risposto qualcosa che non è JSON.' }));
}

export default function AppPagina() {
  const q = new URLSearchParams(window.location.search);
  const site = q.get('site') || '';
  const idIniziale = q.get('id') || '';
  const nuova = !idIniziale;

  const { schema, uischema } = useMemo(() => schemaPer(), []);

  const [doc, setDoc] = useState(() => docVuoto());
  const [data, setData] = useState(() => daJsonLd(docVuoto()));
  /* La `dateModified` di quando si è aperta la pagina: è quella che il server
   * confronta per accorgersi che qualcun altro ha salvato nel frattempo. */
  const [base, setBase] = useState('');
  const [mount, setMount] = useState('');
  const [msg, setMsg] = useState(null);
  const [occupato, setOccupato] = useState(false);
  const [caricata, setCaricata] = useState(nuova);
  const [validazione, setValidazione] = useState({ status: 'idle', errors: [] });
  const seq = useRef(0);

  /* La divisione fra le due colonne, come negli altri due editor: si trascina,
   * si azzera con un doppio clic, e si ricorda. La chiave è la stessa, perché
   * chi passa da un editor all'altro si aspetta la stessa larghezza. */
  const [split, setSplit] = useState(() => Number(localStorage.getItem('split')) || 50);
  useEffect(() => localStorage.setItem('split', String(split)), [split]);
  const layoutRef = useRef(null);

  function startDrag(e) {
    e.preventDefault();
    const el = layoutRef.current;
    const cs = getComputedStyle(el);
    const padL = parseFloat(cs.paddingLeft) || 0;
    const padR = parseFloat(cs.paddingRight) || 0;
    const move = (ev) => {
      const rect = el.getBoundingClientRect();
      const inner = rect.width - padL - padR;
      setSplit(Math.min(75, Math.max(25, ((ev.clientX - rect.left - padL) / inner) * 100)));
    };
    const stop = () => {
      window.removeEventListener('pointermove', move);
      window.removeEventListener('pointerup', stop);
      document.body.style.userSelect = '';
      document.body.style.cursor = '';
    };
    document.body.style.userSelect = 'none';
    document.body.style.cursor = 'col-resize';
    window.addEventListener('pointermove', move);
    window.addEventListener('pointerup', stop);
  }

  const avvisa = useCallback((testo, esito = 'ok') => {
    setMsg({ testo, esito });
    if (esito === 'ok') setTimeout(() => setMsg(null), 4000);
  }, []);

  /* Il documento da salvare: l'originale con sopra il modulo. Si ricalcola a
   * ogni battuta, ed è quello che il pannello di validazione mostra — così ciò
   * che si legge è ciò che verrebbe scritto, non una sua approssimazione. */
  const jsonld = useMemo(() => aJsonLd(data, doc), [data, doc]);

  /* Si rivalida mezzo secondo dopo l'ultima battuta, e le risposte vecchie si
   * scartano: senza il contatore, una validazione lenta partita prima
   * sovrascriverebbe il risultato di una più recente. */
  const payload = useMemo(() => JSON.stringify(jsonld, null, 2), [jsonld]);

  /* «Correggi XHTML»: si rimettono in regola TUTTI i campi che contengono
   * markup, non solo quello segnalato - se uno ha un attributo nudo, gli altri
   * scritti nello stesso momento ce l'hanno probabilmente anche loro.
   *
   * Si fa qui e non chiedendolo a un servizio: e' una trasformazione di testo,
   * la conosce `linguaggi.js`, e l'editor degli eventi la chiedeva invece a un
   * PHP su `localhost:8080` - che in produzione non c'e'. Un pulsante che
   * funziona solo sul portatile di chi l'ha scritto non e' un pulsante.
   *
   * Si tocca solo ciò che SEMBRA markup: una stringa con un `<` seguito da una
   * lettera. «a < b» resta «a < b». */
  function correggiXhtml() {
    const xhtml = linguaggio('xhtml');
    const pareMarkup = (t) => /<[a-zA-Z!\/?]/.test(t);
    const passa = (v) => {
      if (typeof v === 'string') return pareMarkup(v) ? xhtml.correggi(v) : v;
      if (Array.isArray(v)) return v.map(passa);
      if (v && typeof v === 'object') {
        const out = {};
        for (const k of Object.keys(v)) out[k] = passa(v[k]);
        return out;
      }
      return v;
    };
    setData(passa(data));
  }

  const rivalida = useCallback(async (corrente) => {
    const n = ++seq.current;
    try {
      const out = await apiJson('validate_json', { payload: corrente });
      if (n !== seq.current) return;
      setValidazione(out.valid ? { status: 'valid', errors: [] } : { status: 'invalid', errors: out.errors || [] });
    } catch {
      if (n === seq.current) setValidazione({ status: 'unreachable', errors: [] });
    }
  }, []);

  useEffect(() => {
    const t = setTimeout(() => rivalida(payload), 500);
    return () => clearTimeout(t);
  }, [payload, rivalida]);

  const carica = useCallback(async () => {
    if (nuova) return;
    const r = await api('load', { site, id: idIniziale });
    if (r.error) { avvisa(r.error, 'ko'); return; }
    setDoc(r.doc);
    setData(daJsonLd(r.doc));
    setBase(String(r.doc.dateModified || ''));
    setMount(String(r.mount || ''));
    setCaricata(true);
  }, [nuova, site, idIniziale, avvisa]);

  /*
   * Si carica quando c'è una sessione: il backend chiede il gettone, e senza
   * aspettarla la prima richiesta partirebbe sempre senza.
   *
   * E quando la sessione dice che NON c'è nessuno, lo si scrive. Prima non
   * succedeva niente: il modulo restava lì, vuoto, e sembrava una pagina senza
   * contenuto invece di una pagina che non è stata nemmeno chiesta.
   */
  useEffect(() => {
    let fatto = false;
    const prova = () => {
      if (fatto || !window.meetooSession) return;
      window.meetooSession.subscribe((user) => {
        if (fatto) return;
        if (!user) {
          if (!nuova) avvisa('Accedi con Google (in alto a destra) per aprire la pagina.', 'ko');
          return;
        }
        fatto = true;
        carica();
      });
    };
    prova();
    const t = setInterval(prova, 200);
    return () => clearInterval(t);
  }, [carica, nuova, avvisa]);

  const salva = useCallback(async (forza) => {
    if (!data.wspath) { avvisa('Manca l’indirizzo: senza, la pagina non sta sulla mappa.', 'ko'); return; }
    if (!data.title) { avvisa('Manca il titolo.', 'ko'); return; }
    const id = nuova ? idDaWspath(data.wspath) : idIniziale;
    if (!id) { avvisa('Dall’indirizzo non ricavo una cartella: scrivilo come /chi-siamo.', 'ko'); return; }

    setOccupato(true);
    try {
      const r = await api('save', {
        site, id, jsonld, template: data.template || 'page',
        baseModified: base,
        /* Chi crea lo dice: il server rifiuta una cartella che c'è già invece
         * di scriverci sopra. Chi crea non ha una data di partenza, quindi il
         * controllo dei conflitti da solo qui non protegge. */
        ...(nuova ? { creating: 1 } : {}),
        ...(forza ? { force: 1 } : {}),
      });
      if (r.exists) { avvisa(r.error, 'ko'); return; }
      if (r.conflict) {
        avvisa('Qualcun altro ha salvato questa pagina mentre la modificavi. Ricarica, oppure salva lo stesso e sovrascrivi il suo lavoro.', 'ko');
        return;
      }
      if (r.error) { avvisa(r.error, 'ko'); return; }
      setBase(String(r.dateModified || ''));
      /* Che cosa è stato rifatto oltre al file: è l'unica risposta che dice se
       * la pagina RISPONDE davvero, e vale la pena leggerla. */
      const extra = [r.created ? 'creata' : 'salvata', r.twin === 'ok' ? null : 'gemello non riuscito',
        r.sitemap ? `mappa ${r.sitemap}` : null].filter(Boolean).join(' · ');
      avvisa(extra);
      if (nuova) window.location.search = `?site=${encodeURIComponent(site)}&id=${encodeURIComponent(id)}`;
    } catch {
      avvisa('Il server non risponde.', 'ko');
    } finally {
      setOccupato(false);
    }
  }, [data, jsonld, site, idIniziale, nuova, base, avvisa]);

  const pubblica = data.wspath
    ? RADICE.replace(/\/$/, '') + mount + (data.wspath === '/' ? '/' : data.wspath)
    : '';

  return (
    <div className="app app-pagina">
      <div className="appbar">
        <div className="appbar-actions">
          <nav className="crumbs">
            <a href={ELENCO_URL}>Pagine</a>
            <span aria-hidden="true">›</span>
            <span>{data.name || data.title || (nuova ? 'Pagina nuova' : idIniziale)}</span>
          </nav>

          <div className="spacer" />

          {pubblica && !nuova && (
            <a className="btn" href={pubblica} target="_blank" rel="noopener" title="Vedi la pagina pubblica">
              <span className="icon material-symbols-outlined">open_in_new</span>
            </a>
          )}
          <button className="btn primary" onClick={() => salva(false)} disabled={occupato || !caricata}>
            <span className="icon material-symbols-outlined">save</span>
            {occupato ? 'Salvo…' : 'Salva'}
          </button>
        </div>
        {msg && <p className={`msg msg-${msg.esito}`}>{msg.testo}</p>}
      </div>

      {!site && (
        <p className="msg msg-ko">
          Manca il sito. Questa pagina si apre dall’<a href={ELENCO_URL}>elenco delle pagine</a>.
        </p>
      )}

      {site && (
        /*
         * `layout` e `pane` non sono nomi scelti qui: sono il contratto del
         * foglio di stile. TUTTO `form.css` è agganciato a `.pane` — le
         * etichette, i campi, le griglie — e `.pane` è anche l'unico
         * contenitore con `overflow-y: auto`, cioè l'unica cosa che scorre.
         *
         * Con un `div class="editor"` al suo posto non si applicava niente di
         * tutto questo: i campi uscivano nudi e la pagina non scorreva, perché
         * `.app` è alta quanto lo schermo e taglia quello che esce.
         */
        <div className="layout" ref={layoutRef} style={{ '--split': split + '%' }}>
          <section className="pane pane-form">
            <JsonForms
              schema={schema}
              uischema={uischema}
              data={data}
              renderers={renderers}
              cells={vanillaCells}
              onChange={({ data: d }) => setData(d)}
            />
          </section>

          <div
            className="col-divider"
            role="separator"
            aria-orientation="vertical"
            title="Trascina per ridimensionare · doppio clic per 50/50"
            onPointerDown={startDrag}
            onDoubleClick={() => setSplit(50)}
          />

          {/* Il riquadro vuole il TESTO, non l'oggetto: quello che si legge è
              esattamente quello che verrebbe scritto sul file. */}
          <section className="pane pane-validation">
            <JsonValidationPane
              payload={payload}
              validation={validazione}
              onRevalidate={() => rivalida(payload)}
              onFix={correggiXhtml}
              etichetta="La pagina (JSON-LD)"
            />
          </section>
        </div>
      )}
    </div>
  );
}

/*
 * La cartella di una pagina nuova, dal suo indirizzo.
 *
 * `/chi-siamo` → `chi-siamo`, `/servizi/brand-design` → `servizi/brand-design`,
 * `/` → `index`. È una proposta, non una regola: vale solo alla creazione, e da
 * quel momento la cartella è quella e l'indirizzo può cambiare senza portarsela
 * dietro — che è il motivo per cui `@id` e `wspath` sono due cose diverse.
 */
function idDaWspath(wspath) {
  const p = String(wspath || '').trim();
  if (p === '/' || p === '') return 'index';
  const pulito = p.replace(/^\/+|\/+$/g, '')
    .split('/')
    .map((s) => s.toLowerCase().replace(/[^a-z0-9_-]+/g, '-').replace(/^-+|-+$/g, ''))
    .filter(Boolean)
    .join('/');
  return pulito;
}
