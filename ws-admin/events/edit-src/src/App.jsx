import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { JsonForms } from '@jsonforms/react';
import { vanillaRenderers, vanillaCells } from '@jsonforms/vanilla-renderers';
import { schema, uischema } from './schema.js';
import { fromJsonLd, toJsonLd, blankJsonLd } from './jsonld-adapter.js';
import XhtmlRichTextRenderer, { xhtmlControlTester } from './XhtmlRichTextRenderer.jsx';
import LabeledEnumRenderer, { labeledEnumTester } from './LabeledEnumRenderer.jsx';
import ImageUploadRenderer, { imageUploadTester } from './ImageUploadRenderer.jsx';
import TagArrayRenderer, { tagArrayTester } from './TagArrayRenderer.jsx';
import RepeatableObjectRenderer, { repeatableObjectTester } from './RepeatableObjectRenderer.jsx';
import SearchSelectRenderer, { searchSelectTester } from './SearchSelectRenderer.jsx';
import RecurrenceRenderer, { recurrenceTester } from './RecurrenceRenderer.jsx';
import MultiSelectRenderer, { multiSelectTester } from './MultiSelectRenderer.jsx';
import ComputedRenderer, { computedTester } from './ComputedRenderer.jsx';
import FieldRowRenderer, { fieldRowTester } from './FieldRowRenderer.jsx';
import IconTextRenderer, { iconTextTester } from './IconTextRenderer.jsx';
import SmartDateRenderer, { smartDateTester } from './SmartDateRenderer.jsx';
import PlaceLocationRenderer, { placeLocationTester } from './PlaceLocationRenderer.jsx';
import JsonValidationPane from './JsonValidationPane.jsx';
import GroupRenderer, { groupTester } from './GroupRenderer.jsx';
import PageSettings from './PageSettings.jsx';
import OpenEventModal from './OpenEventModal.jsx';
import DiffModal from './DiffModal.jsx';
import { diffForm, mergeChoices, pathToClass } from './diff.js';
import { API_BASE, CONTENT_BASE, SAVE_EVENT_URL } from './config.js';
import { supportsFs, ensurePermission, writeInto, downloadFile, idbGet, idbSet, idbDel } from './fsSave.js';

const renderers = [
  ...vanillaRenderers,
  { tester: groupTester, renderer: GroupRenderer },
  { tester: xhtmlControlTester, renderer: XhtmlRichTextRenderer },
  { tester: labeledEnumTester, renderer: LabeledEnumRenderer },
  { tester: imageUploadTester, renderer: ImageUploadRenderer },
  { tester: tagArrayTester, renderer: TagArrayRenderer },
  { tester: repeatableObjectTester, renderer: RepeatableObjectRenderer },
  { tester: searchSelectTester, renderer: SearchSelectRenderer },
  { tester: recurrenceTester, renderer: RecurrenceRenderer },
  { tester: multiSelectTester, renderer: MultiSelectRenderer },
  { tester: computedTester, renderer: ComputedRenderer },
  { tester: fieldRowTester, renderer: FieldRowRenderer },
  { tester: iconTextTester, renderer: IconTextRenderer },
  { tester: smartDateTester, renderer: SmartDateRenderer },
  { tester: placeLocationTester, renderer: PlaceLocationRenderer },
];

// Campi derivati/gestiti da escludere dal confronto (calcolati o iniettati dal server).
const DIFF_EXCLUDE = new Set([
  'maximumAttendeeCapacity', 'remainingAttendeeCapacity',
  'creator', 'contributor', 'dateCreated', 'dateModified',
]);

// Capienze derivate: totale = presenza + remoto; rimasti = totale − prenotati.
// Sono di sola lettura nel form (ComputedRenderer) e ricalcolate a ogni modifica.
function deriveCapacities(d) {
  const total = (Number(d.maximumPhysicalAttendeeCapacity) || 0) + (Number(d.maximumVirtualAttendeeCapacity) || 0);
  const booked = Number(d.bookedAttendeeCapacity) || 0;
  return { ...d, maximumAttendeeCapacity: total, remainingAttendeeCapacity: Math.max(0, total - booked) };
}

// Chiamata al convertitore/validatore PHP tramite il proxy /api di Vite.
async function api(action, fields = {}) {
  const body = new URLSearchParams({ action, ...fields });
  const res = await fetch(API_BASE, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: body.toString(),
  });
  return res.json();
}

export default function App() {
  const [data, setData] = useState(() => deriveCapacities(fromJsonLd(blankJsonLd)));
  const [tab, setTab] = useState('form');

  // File operations (Fase 1: carica · Fase 2: apri web · Fase 3: salva su PC). Flash = messaggio transitorio.
  const fileRef = useRef(null);
  const baseDirRef = useRef(null); // FileSystemDirectoryHandle radice contenuti (ricordato)
  const [openWeb, setOpenWeb] = useState(false);
  const [flash, setFlash] = useState(null);
  // Auth Google (per il salvataggio sul web): token (credential) + utente {email, role}.
  // Login/logout avvengono nella RIGA 1 dell'header condiviso (header.js →
  // window.meetooSession); qui ci limitiamo a leggere token+utente dalla sessione.
  const [authToken, setAuthToken] = useState('');
  const [authUser, setAuthUser] = useState(null);
  useEffect(() => {
    let stop = false;
    (function attach() {
      const S = window.meetooSession;
      if (!S) { if (!stop) setTimeout(attach, 150); return; } // attende header.js
      S.subscribe((user, token) => { setAuthToken(token || ''); setAuthUser(user || null); });
    })();
    return () => { stop = true; };
  }, []);
  const onLogout = useCallback(() => { window.meetooSession?.logout?.(); }, []);
  const flashTimer = useRef(0);
  function showFlash(msg, kind = 'ok') {
    setFlash({ msg, kind });
    clearTimeout(flashTimer.current);
    flashTimer.current = setTimeout(() => setFlash(null), 4000);
  }

  // Tema: alla prima apertura segue il sistema, poi vale la scelta memorizzata.
  // Il tema è governato da "Aspetto" nelle Impostazioni dell'header (auto/chiaro/
  // scuro): qui ne seguiamo il valore RISOLTO per gli stili dell'editor.
  const [theme, setTheme] = useState(() => {
    const saved = localStorage.getItem('meetoo:theme') || localStorage.getItem('theme');
    if (saved === 'light' || saved === 'dark') return saved;
    return window.matchMedia?.('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
  });
  useEffect(() => {
    const onTheme = (e) => setTheme(e.detail?.resolved === 'light' ? 'light' : 'dark');
    document.addEventListener('meetoo:theme', onTheme);
    return () => document.removeEventListener('meetoo:theme', onTheme);
  }, []);
  const [density, setDensity] = useState(() => localStorage.getItem('density') || 'comfortable');

  // Larghezza della colonna Form (%): 50/50 di default, regolabile col divisore.
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
      const pct = ((ev.clientX - rect.left - padL) / inner) * 100;
      setSplit(Math.min(75, Math.max(25, pct)));
    };
    const stop = () => {
      window.removeEventListener('pointermove', move);
      window.removeEventListener('pointerup', stop);
      document.body.style.userSelect = '';
      document.body.style.cursor = '';
    };
    window.addEventListener('pointermove', move);
    window.addEventListener('pointerup', stop);
    document.body.style.userSelect = 'none';
    document.body.style.cursor = 'col-resize';
  }

  useEffect(() => {
    // Solo gli stili dell'editor: color-scheme e la preferenza (auto/chiaro/scuro)
    // restano dell'header, che è l'unico posto dove si sceglie il tema.
    document.documentElement.dataset.theme = theme;
  }, [theme]);
  useEffect(() => {
    document.documentElement.dataset.density = density;
    localStorage.setItem('density', density);
  }, [density]);

  // Deep-link: all'avvio, se l'URL porta ?id=… (o ?event=…), apre quell'evento dal web.
  useEffect(() => {
    const q = new URLSearchParams(window.location.search);
    const from = q.get('from');            // ?from=… → duplica (nuovo evento da un altro)
    const id = q.get('id') || q.get('event');
    if (from) loadFromWeb(from, true);
    else if (id) loadFromWeb(id);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const [validation, setValidation] = useState({ status: 'idle', errors: [] });
  const [xml, setXml] = useState('');
  const [xmlError, setXmlError] = useState('');
  const seq = useRef(0);

  const jsonld = useMemo(() => toJsonLd(data), [data]);
  const payload = useMemo(() => JSON.stringify(jsonld, null, 2), [jsonld]);

  // Validazione del JSON-LD generato, riusando validate_json del backend PHP.
  async function runValidation(current) {
    const id = ++seq.current;
    try {
      const out = await api('validate_json', { payload: current });
      if (id !== seq.current) return; // scarta risposte obsolete
      setValidation(out.valid ? { status: 'valid', errors: [] } : { status: 'invalid', errors: out.errors || [] });
    } catch {
      if (id === seq.current) setValidation({ status: 'unreachable', errors: [] });
    }
  }
  const revalidate = () => runValidation(payload);

  // Live, con debounce, a ogni modifica del form.
  useEffect(() => {
    const t = setTimeout(() => runValidation(payload), 500);
    return () => clearTimeout(t);
  }, [payload]);

  // Applica la correzione XHTML suggerita (fix_xhtml) e re-idrata il form.
  async function fixXhtml() {
    try {
      const out = await api('fix_xhtml', { type: 'json', payload });
      if (out.success) setData(fromJsonLd(JSON.parse(out.result)));
    } catch (e) {
      console.error(e);
    }
  }

  async function generateXml() {
    setXmlError('');
    setXml('');
    try {
      const out = await api('to_xml', { payload });
      out.success ? setXml(out.result) : setXmlError(out.error || 'Conversione fallita');
    } catch {
      setXmlError('Convertitore PHP non raggiungibile su :8080 (avvia `php -S localhost:8080` in json-xml/).');
    }
  }


  // Salvataggio: consentito SOLO a validazione perfetta. Per ora scarica il
  // JSON-LD; in ambiente Google Workspace diventerà un salvataggio automatico
  // con versioning (come un Google Document).
  const canSave = validation.status === 'valid';

  // Percorso relativo dell'evento (schema c). Se l'@id è già un percorso lo uso,
  // altrimenti lo metto sotto events/. La Fase 4 lo formalizzerà con un campo path.
  function eventRelPath(d) {
    const id = (d.id || '').trim().replace(/^\/+|\/+$/g, '');
    if (!id) return 'events/senza-id';
    return id.includes('/') ? id : 'events/' + id;
  }

  // Fase 3 — Salva su PC. Con File System Access API scrive index.json nella cartella
  // giusta (events/<id>/…), creando le sottocartelle; la cartella base si sceglie una
  // volta e resta ricordata. Fallback: download classico (Safari/iOS/Firefox).
  async function savePc() {
    if (!canSave) return;
    const rel = eventRelPath(data);
    if (!supportsFs()) {
      downloadFile(payload);
      showFlash('Salvato (download): index.json — sposta in ' + rel + '/', 'ok');
      return;
    }
    try {
      let base = baseDirRef.current || (await idbGet('baseDir'));
      if (base && !(await ensurePermission(base))) base = null; // permesso revocato → ri-scegli
      if (!base) {
        base = await window.showDirectoryPicker({ id: 'meetoo-contents', mode: 'readwrite' });
        if (!(await ensurePermission(base))) throw new Error('permesso negato');
        await idbSet('baseDir', base);
      }
      baseDirRef.current = base;
      await writeInto(base, rel, 'index.json', payload);
      showFlash(`Salvato in ${rel}/index.json`, 'ok');
    } catch (e) {
      if (e?.name === 'AbortError') return; // l'utente ha annullato il picker
      showFlash('Salvataggio fallito: ' + (e?.message || e), 'err');
    }
  }

  // Fase 4 — Salva/aggiorna sul web. Prima confronta col contenuto già online
  // (diff selettivo): se l'evento esiste ed è cambiato, apre il pannello scelte;
  // altrimenti salva direttamente. Il salvataggio vero avviene in doSaveWeb.
  const [savingWeb, setSavingWeb] = useState(false);
  const [diff, setDiff] = useState(null); // { changes, rel } | null
  const [changedPaths, setChangedPaths] = useState(() => new Set()); // per i marcatori inline

  async function saveWeb() {
    if (!canSave || savingWeb || diff) return;
    const rel = eventRelPath(data);
    // Confronto col web: scaricabile senza login (contenuto pubblico); il login serve al salvataggio.
    let stored = null;
    try {
      const res = await fetch(resolveEventUrl(rel), { headers: { Accept: 'application/json' } });
      if (res.ok) {
        const parsed = await res.json();
        stored = parsed?.mainEntity && typeof parsed.mainEntity === 'object' ? parsed.mainEntity : parsed;
      }
    } catch { /* offline o non esistente → trattato come nuovo */ }

    if (stored) {
      const storedData = deriveCapacities(fromJsonLd(stored));
      const changes = diffForm(storedData, data, DIFF_EXCLUDE);
      setChangedPaths(new Set(changes.map((c) => c.path)));
      if (changes.length) { setDiff({ changes, rel }); return; } // apre il pannello; salva dopo la scelta
      showFlash('Nessuna modifica rispetto al web: salvo comunque (aggiorna data)…', 'ok');
    }
    doSaveWeb(rel, payload);
  }

  // Conferma dal pannello diff: costruisce i dati uniti e salva.
  function confirmDiff(keepTheirs) {
    const merged = mergeChoices(data, diff.changes, keepTheirs);
    const mergedPayload = JSON.stringify(toJsonLd(deriveCapacities(merged)), null, 2);
    const rel = diff.rel;
    doSaveWeb(rel, mergedPayload, () => { setData(deriveCapacities(merged)); setDiff(null); });
  }

  async function doSaveWeb(rel, payloadToSave, onDone) {
    if (savingWeb) return;
    if (!authToken) { showFlash('Accedi con Google per salvare sul web', 'err'); return; }
    setSavingWeb(true);
    try {
      const body = new URLSearchParams({ payload: payloadToSave, path: rel, credential: authToken });
      const res = await fetch(SAVE_EVENT_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString(),
      });
      const out = await res.json();
      if (res.status === 401) { onLogout(); showFlash('Sessione scaduta: accedi di nuovo con Google', 'err'); return; }
      if (out.success) {
        setChangedPaths(new Set());
        onDone?.();
        showFlash(`Salvato sul web in ${out.path}/ — index.json + index.xml${out.folderCreated ? ' (cartella creata)' : ''} · da ${out.by || ''}`, 'ok');
      } else {
        const first = out.errors?.[0]?.message?.replace(/<[^>]+>/g, '') || '';
        showFlash(`Salvataggio web fallito${out.stage ? ` [${out.stage}]` : ''}: ${out.error || ''}${first ? ' — ' + first : ''}`, 'err');
      }
    } catch (e) {
      showFlash('Endpoint di salvataggio web non raggiungibile: ' + (e?.message || e), 'err');
    } finally {
      setSavingWeb(false);
    }
  }

  // Marcatori inline: evidenzia nel form i campi diversi dal web (best-effort — i
  // controlli standard espongono la classe root_properties_… col percorso dati).
  useEffect(() => {
    const form = document.querySelector('.pane-form');
    if (!form) return;
    form.querySelectorAll('.jsf-changed').forEach((el) => el.classList.remove('jsf-changed'));
    if (!changedPaths.size || tab !== 'form') return;
    const classes = [...changedPaths].map(pathToClass);
    form.querySelectorAll('.control').forEach((ctrl) => {
      const root = [...ctrl.classList].find((t) => t.startsWith('root_'));
      if (root && classes.some((c) => root === c || root.startsWith(c + '_'))) ctrl.classList.add('jsf-changed');
    });
  }, [changedPaths, tab, data]);

  // Dimentica la cartella base (per ri-sceglierla al prossimo salvataggio).
  async function forgetBaseDir() {
    baseDirRef.current = null;
    await idbDel('baseDir');
    showFlash('Cartella di salvataggio dimenticata: verrà richiesta al prossimo salvataggio', 'ok');
  }

  // Nuovo evento: svuota il form riportandolo alla configurazione base e ripulisce
  // l'URL (?id=…) e i marcatori del confronto.
  function newEvent() {
    setData(deriveCapacities(fromJsonLd(blankJsonLd)));
    setChangedPaths(new Set());
    setDiff(null);
    try { history.replaceState(null, '', window.location.pathname); } catch { /* ignora */ }
    showFlash('Nuovo evento: form vuoto (configurazione base)', 'ok');
  }

  // Fase 1 — Carica un evento da un file JSON (computer/smartphone) e idrata il form.
  function pickFile() { fileRef.current?.click(); }
  async function loadFromFile(file) {
    if (!file) return;
    try {
      const parsed = JSON.parse(await file.text());
      // Un index.json evento è "flat"; se arriva incapsulato (mainEntity) lo srotolo.
      const doc = parsed && typeof parsed === 'object' && parsed.mainEntity && typeof parsed.mainEntity === 'object'
        ? parsed.mainEntity : parsed;
      setData(deriveCapacities(fromJsonLd(doc)));
      showFlash(`Caricato «${file.name}»`, 'ok');
    } catch (e) {
      showFlash('File JSON non valido: ' + (e?.message || e), 'err');
    } finally {
      if (fileRef.current) fileRef.current.value = ''; // consenti ricaricare lo stesso file
    }
  }

  // Fase 2 — Apri un evento dal web. Accetta @id/percorso (relativo a CONTENT_BASE)
  // oppure un URL completo; se non finisce in .json aggiunge /index.json.
  function resolveEventUrl(input) {
    const s = (input || '').trim();
    if (!s) return '';
    let url = /^https?:\/\//i.test(s) ? s : CONTENT_BASE.replace(/\/$/, '') + '/' + s.replace(/^\//, '');
    if (!/\.json(\?|$)/i.test(url)) url = url.replace(/\/$/, '') + '/index.json';
    return url;
  }
  // asCopy: apre l'evento come BASE per uno nuovo (duplica). Toglie ciò che
  // identifica l'originale — @id (lo si rigenera salvando), date di sistema e
  // l'appartenenza alle occorrenze — così un salvataggio non sovrascrive la fonte.
  async function loadFromWeb(input, asCopy = false) {
    const url = resolveEventUrl(input);
    if (!url) return;
    try {
      const res = await fetch(url, { headers: { Accept: 'application/json' } });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const parsed = await res.json();
      let doc = parsed && typeof parsed === 'object' && parsed.mainEntity && typeof parsed.mainEntity === 'object'
        ? parsed.mainEntity : parsed;
      if (asCopy) {
        doc = { ...doc };
        delete doc['@id']; delete doc.dateCreated; delete doc.dateModified;
        delete doc.creator; delete doc.author; delete doc.contributor;
        delete doc.subEvent;   // le occorrenze restano dell'originale
      }
      setData(deriveCapacities(fromJsonLd(doc)));
      setOpenWeb(false);
      if (asCopy) {
        try { history.replaceState(null, '', window.location.pathname); } catch { /* ignora */ }
        showFlash('Copia di «' + input + '»: cambia data e titolo, poi salva (verrà creato un nuovo evento)', 'ok');
        return;
      }
      // Rende l'apertura condivisibile/ricaricabile: mette l'id nell'URL (?id=…).
      try {
        const q = new URLSearchParams(window.location.search);
        q.set('id', input.trim());
        history.replaceState(null, '', window.location.pathname + '?' + q.toString());
      } catch { /* URL non modificabile: ignora */ }
      showFlash(`Aperto «${doc['@id'] || input}»`, 'ok');
    } catch (e) {
      showFlash('Apertura fallita (' + (e?.message || e) + '): ' + url, 'err');
    }
  }

  // L'icona nativa del datetime è soppressa via CSS per poterla ridisegnare del
  // colore giusto, ma così perde anche il click che apriva il calendario:
  // lo riapriamo noi quando si clicca nella zona dell'icona (a destra nel campo).
  const ICON_ZONE = 40; // px riservati dal padding-right
  function openDatePicker(e) {
    const input = e.target;
    if (!(input instanceof HTMLInputElement) || input.type !== 'datetime-local') return;
    if (input.closest('.smart-date')) return; // SmartDate ha i suoi pulsanti
    if (e.clientX < input.getBoundingClientRect().right - ICON_ZONE) return;
    try {
      input.showPicker?.();
    } catch {
      /* showPicker non disponibile: resta l'input testuale */
    }
  }

  // Porta nel campo Keywords i name di organizer e luogo quando il campo che li
  // contiene perde il focus (evita di aggiungere i frammenti digitati a metà).
  // Va invocata anche dopo una compilazione automatica (es. Google Places).
  function syncKeywords() {
    setData((d) => {
      const names = [...(d.organizer ?? []).map((o) => o?.name), d.location?.name];
      const keywords = Array.isArray(d.keywords) ? [...d.keywords] : [];
      let changed = false;

      names.forEach((name) => {
        const value = (name ?? '').trim();
        if (!value) return;
        if (!keywords.some((k) => (k ?? '').trim().toLowerCase() === value.toLowerCase())) {
          keywords.push(value);
          changed = true;
        }
      });

      return changed ? { ...d, keywords } : d;
    });
  }

  return (
    <div className={'app tab-' + tab}>
      <header className="appbar appbar-row2">
        <div className="appbar-actions">
          <button type="button" className="btn-ghost" onClick={newEvent} title="Nuovo evento: svuota il form (configurazione base)">
            <span className="material-symbols-outlined">note_add</span> Nuovo
          </button>
          <button type="button" className="btn-ghost" onClick={pickFile} title="Carica un evento da un file JSON (.json)">
            <span className="material-symbols-outlined">upload_file</span> Carica
          </button>
          <input
            ref={fileRef}
            type="file"
            accept=".json,application/json,application/ld+json"
            hidden
            onChange={(e) => loadFromFile(e.target.files?.[0])}
          />
          <button type="button" className="btn-ghost" onClick={() => setOpenWeb(true)} title="Apri un evento salvato sul web">
            <span className="material-symbols-outlined">cloud_download</span> Apri
          </button>
          <button
            type="button"
            className="btn-save"
            onClick={savePc}
            disabled={!canSave}
            title={canSave ? 'Salva index.json su questo computer (nella cartella events/<id>/)' : 'Salvataggio disponibile solo a validazione perfetta'}
          >
            <span className="material-symbols-outlined">save</span> Salva su PC
          </button>
          <button
            type="button"
            className="btn-save"
            onClick={saveWeb}
            disabled={!canSave || savingWeb}
            title={canSave ? 'Salva sul web: valida JSON→XML e scrive index.json + index.xml nella cartella' : 'Disponibile solo a validazione perfetta'}
          >
            <span className="material-symbols-outlined">cloud_upload</span> {savingWeb ? 'Salvo…' : 'Salva sul web'}
          </button>
          {/* La manutenzione (Rigenera indice, Normalizza) sta in Gestione eventi:
              qui si scrive un evento, non si amministrano gli indici. */}
          {/* Tab: a destra della riga 2 (visibili quando le due colonne non stanno affiancate) */}
          <div className="tabs">
            <button className={tab === 'form' ? 'active' : ''} onClick={() => setTab('form')}>
              <span className="material-symbols-outlined">edit_document</span> Form
            </button>
            <button className={tab === 'validation' ? 'active' : ''} onClick={() => setTab('validation')}>
              <span className="material-symbols-outlined">fact_check</span> Validazione
            </button>
          </div>
        </div>
      </header>

      {/* Impostazioni proprie dell'editor: montate nella modale Impostazioni
          dell'header (riga 1), sotto Aspetto e Preferenze. */}
      <PageSettings
        density={density}
        onDensity={setDensity}
        canForgetDir={supportsFs()}
        onForgetDir={forgetBaseDir}
      />

      {flash && <div className={'flash flash-' + flash.kind} role="status">{flash.msg}</div>}

      <OpenEventModal open={openWeb} onClose={() => setOpenWeb(false)} onOpen={loadFromWeb} />

      <DiffModal
        open={!!diff}
        changes={diff?.changes || []}
        relPath={diff?.rel || ''}
        saving={savingWeb}
        onConfirm={confirmDiff}
        onCancel={() => { if (!savingWeb) { setDiff(null); setChangedPaths(new Set()); } }}
      />


      <div className="layout" ref={layoutRef} style={{ '--split': split + '%' }}>
        <section className="pane pane-form" onBlur={syncKeywords} onClick={openDatePicker}>
          <h2>Form (JSON Forms, schema-driven)</h2>
          <JsonForms
            schema={schema}
            uischema={uischema}
            data={data}
            renderers={renderers}
            cells={vanillaCells}
            onChange={({ data }) => setData(deriveCapacities(data))}
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

        <section className="pane pane-validation">
          <h2>Validazione <small>(validate_json)</small></h2>
          <JsonValidationPane
            payload={payload}
            validation={validation}
            onRevalidate={revalidate}
            onFix={fixXhtml}
            onGenerateXml={generateXml}
            xml={xml}
            xmlError={xmlError}
          />
        </section>
      </div>
    </div>
  );
}

