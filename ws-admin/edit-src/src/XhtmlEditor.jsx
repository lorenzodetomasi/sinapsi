import { useEffect, useRef, useState } from 'react';
import { applicaCaso } from './testoCaso.js';

// Editor rich-text XHTML riutilizzabile (presentazionale): value + onChange.
const VOID_ELEMENTS = 'area|base|br|col|embed|hr|img|input|link|meta|param|source|track|wbr';
export function toXhtml(html) {
  return normalizeTags(html).replace(
    new RegExp(`<(${VOID_ELEMENTS})\\b([^>]*?)\\s*/?>`, 'gi'),
    (_, tag, attrs) => `<${tag}${attrs} />`
  );
}

// execCommand produce <b>/<i>: portiamo a XHTML semantico <strong>/<em> e
// togliamo gli stili inline (l'editor ammette solo grassetto/corsivo/
// sottolineato/elenchi/link, come Google Calendar). \b evita <br>, <img>, ecc.
function normalizeTags(html) {
  return html
    .replace(/<(\/?)b\b([^>]*)>/gi, '<$1strong$2>')
    .replace(/<(\/?)i\b([^>]*)>/gi, '<$1em$2>')
    .replace(/\s+style="[^"]*"/gi, '');
}

/* «Il campo contiene già questo testo?» — confronto fra due GRAMMATICHE diverse
 * della stessa cosa. Il valore salvato scrive `<br />`, il DOM restituisce `<br>`;
 * un `<b>` salvato torna `<b>` ma noi lo scriviamo `<strong>`; gli attributi
 * possono uscire in un altro ordine. Far ripassare anche il valore dal parser del
 * browser mette le due parti nella stessa forma, e quel che resta lo appiana
 * toXhtml. */
function stessoContenuto(el, valore) {
  const prova = document.createElement('div');
  prova.innerHTML = valore ?? '';
  return toXhtml(el.innerHTML) === toXhtml(prova.innerHTML);
}

const Icon = ({ name }) => <span className="material-symbols-outlined">{name}</span>;

/* I blocchi: `formatBlock` li SOSTITUISCE (un titolo non finisce dentro un
 * paragrafo, prende il suo posto), che è quello che serve. `section` non lo
 * conosce — è un contenitore, non un formato — e infatti si avvolge a parte. */
const BLOCCHI = [
  ['p', 'Paragrafo'],
  ['h1', 'Titolo 1'],
  ['h2', 'Titolo 2'],
  ['h3', 'Titolo 3'],
  ['h4', 'Titolo 4'],
  ['h5', 'Titolo 5'],
  ['h6', 'Titolo 6'],
  ['blockquote', 'Citazione'],
  ['pre', 'Preformattato'],
];

/* I semantici in linea: si avvolgono attorno alla selezione. Alcuni chiedono un
 * attributo per avere senso (abbr senza `title` non spiega niente): quelli lo
 * chiedono, come fa già il link. */
const INLINE = [
  ['code', 'Codice'],
  ['q', 'Citazione breve'],
  ['cite', 'Titolo d’opera'],
  ['abbr', 'Abbreviazione…', 'title', 'Per esteso:'],
  ['dfn', 'Definizione'],
  ['mark', 'Evidenziato'],
  ['kbd', 'Tasto'],
  ['samp', 'Output'],
  ['var', 'Variabile'],
  ['ins', 'Inserito'],
  ['del', 'Eliminato'],
  ['sub', 'Pedice'],
  ['sup', 'Apice'],
  ['small', 'Piccolo'],
];
const TAG_INLINE = INLINE.map(([t]) => t);

const CASI = [
  ['alto', 'MAIUSCOLO'],
  ['basso', 'minuscolo'],
  ['altobasso', 'Alto e Basso'],
  ['frase', 'Iniziale maiuscola'],
];

/** I nodi di testo toccati da un intervallo, con il tratto che ne è compreso. */
function pezziDiTesto(r) {
  const dentro = r.commonAncestorContainer;
  const radice = dentro.nodeType === 1 ? dentro : dentro.parentNode;
  const passo = document.createTreeWalker(radice, NodeFilter.SHOW_TEXT);
  const out = [];
  let n;
  while ((n = passo.nextNode())) {
    if (!r.intersectsNode(n)) continue;
    const da = n === r.startContainer ? r.startOffset : 0;
    const a = n === r.endContainer ? r.endOffset : n.nodeValue.length;
    if (a > da) out.push({ nodo: n, da, a });
  }
  return out;
}

export default function XhtmlEditor({ value, onChange, enabled = true, compact = false, label = '', icona = '' }) {
  const ref = useRef(null);
  const [focused, setFocused] = useState(false);
  const [blocco, setBlocco] = useState('p');
  const [vista, setVista] = useState('anteprima'); // 'anteprima' | 'codice'

  /* Riscrivere il contenuto di un contenteditable butta via il cursore: i nodi su
   * cui poggiava non esistono più e il punto d'inserimento torna in cima. Quindi si
   * riscrive SOLO se il contenuto è davvero diverso da quello che c'è già.
   *
   * Il confronto normalizza ENTRAMBE le parti, perché la stessa cosa si scrive in
   * grafie diverse: il valore salva `<br />` e il DOM restituisce `<br>`. Confrontando
   * le grafie invece del contenuto risultavano SEMPRE diverse — così ogni tasto
   * riscriveva tutto e il cursore tornava in cima. Bastava un a capo nel testo perché
   * il campo diventasse inutilizzabile. */
  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    const v = value ?? '';
    /* Ritorno vecchio. Il form ci rimanda la sua copia con un giro di ritardo:
     * dopo una modifica arriva il valore NUOVO (giusto) e subito dopo, a volte,
     * quello di PRIMA. Riscrivere il campo con quello disferebbe la modifica
     * appena fatta — misurato: un tag messo dalla barra spariva entro 50 ms, e
     * scrivendo sarebbe il cursore a tornare in cima.
     *
     * Si riconosce con precisione, senza indovinare: è vecchio se coincide con il
     * valore che c'era PRIMA della nostra ultima emissione mentre il campo
     * contiene già quella emissione. */
    const eco = ultima.current;
    if (eco && v === eco.prima && stessoContenuto(el, eco.dopo)) {
      // Non basta non riscrivere il campo: il modello resterebbe indietro e a
      // salvare andrebbe il testo di prima. Il campo ha ragione, quindi rimanda
      // su il valore giusto e il giro si chiude lì (il valore nuovo non e' mai
      // uguale a quello di prima, quindi non si rientra qui).
      onChange(eco.dopo);
      return;
    }
    if (!stessoContenuto(el, v)) el.innerHTML = v;
  }, [value]);

  // Un contenteditable "svuotato" resta con un <br>: lo trattiamo come vuoto (''),
  // così il campo non risulta compilato e il nodo sparisce dal JSON.
  const ultima = useRef(null); // { prima, dopo } dell'ultima emissione
  const emit = () => {
    const el = ref.current;
    const html = el.innerHTML;
    const empty = !el.textContent.trim() && !/<(img|hr|iframe|video|audio)\b/i.test(html);
    const uscita = empty ? '' : toXhtml(html);
    ultima.current = { prima: value ?? '', dopo: uscita };
    onChange(uscita);
  };
  /* La selezione va ricordata. I pulsanti trattengono il fuoco (preventDefault sul
   * mousedown), ma il menu a tendina no: aprirlo sposta il fuoco e in un
   * contenteditable questo cancella la selezione. Quindi si tiene da parte
   * l'ultimo tratto selezionato dentro il campo e lo si rimette prima di agire. */
  const selezione = useRef(null);
  const ricorda = () => {
    const el = ref.current;
    const s = document.getSelection();
    if (!el || !s || !s.rangeCount) return;
    const r = s.getRangeAt(0);
    if (el.contains(r.commonAncestorContainer)) selezione.current = r.cloneRange();
  };
  const ripristina = () => {
    const el = ref.current;
    el.focus();
    const r = selezione.current;
    if (!r || !el.contains(r.commonAncestorContainer)) return;
    const s = document.getSelection();
    s.removeAllRanges();
    s.addRange(r);
  };

  /** Il blocco in cui sta il cursore, per far vedere al menu dove siamo. */
  const leggiBlocco = () => {
    const el = ref.current;
    const s = document.getSelection();
    if (!el || !s || !s.rangeCount) return;
    let n = s.getRangeAt(0).startContainer;
    if (!el.contains(n)) return;
    while (n && n !== el) {
      const t = n.nodeType === 1 ? n.tagName.toLowerCase() : '';
      if (BLOCCHI.some(([x]) => x === t)) return setBlocco(t);
      if (t === 'li' || t === 'section') return setBlocco('p');
      n = n.parentNode;
    }
    setBlocco('p');
  };
  const seguiCursore = () => {
    ricorda();
    leggiBlocco();
  };

  /* Si ascolta `selectionchange` sul documento, non i singoli eventi del campo:
   * la selezione cambia anche in modi che il campo non vede — trascinando fino
   * a rilasciare fuori dal bordo, o da tastiera con maiusc+frecce dopo un
   * clic altrove. Chi non è dentro il campo viene ignorato dai due controlli. */
  useEffect(() => {
    document.addEventListener('selectionchange', seguiCursore);
    return () => document.removeEventListener('selectionchange', seguiCursore);
  }, []);

  const cmd = (command, val = null) => {
    ripristina();
    document.execCommand('styleWithCSS', false, false); // preferisce tag a stili inline
    document.execCommand(command, false, val);
    ref.current.focus();
    emit();
    seguiCursore();
  };
  const addLink = () => {
    const url = prompt('URL del link:');
    if (url) cmd('createLink', url);
  };

  /** Avvolge la selezione in un elemento. Con il cursore fermo mette l'elemento
   *  con dentro uno spazio, gia' selezionato: ci si scrive sopra e basta.
   *
   *  Il nodo si costruisce a mano invece di passare da `insertHTML`, che sarebbe
   *  annullabile con Cmd+Z ma non e' fedele: Chrome fa passare il frammento dal suo
   *  igienizzatore e certi tag semantici li riscrive a modo suo — un <mark> torna
   *  indietro come <span style="background-color: ...">, cioe' esattamente la
   *  formattazione a stili che qui non vogliamo. */
  const avvolgi = (tag, attributi = null) => {
    ripristina();
    const el = ref.current;
    const s = document.getSelection();
    if (!s || !s.rangeCount) return;
    const r = s.getRangeAt(0);
    if (!el.contains(r.commonAncestorContainer)) return;

    const nodo = document.createElement(tag);
    if (attributi) for (const [k, v] of Object.entries(attributi)) nodo.setAttribute(k, v);
    if (r.collapsed) nodo.appendChild(document.createTextNode(' '));
    else nodo.appendChild(r.extractContents());
    r.insertNode(nodo);

    const dentro = document.createRange();
    dentro.selectNodeContents(nodo);
    s.removeAllRanges();
    s.addRange(dentro);

    el.focus();
    /* La modifica si annuncia con un evento `input`, come farebbe il browser se
     * l'avesse fatta l'utente: è la stessa strada dei pulsanti (execCommand ne
     * emette uno per conto suo) e ha un motivo preciso. Chiamando `emit()` di
     * mano propria dentro il gestore del menu, il form rimandava indietro il
     * valore di PRIMA un istante dopo, e il campo si riscriveva perdendo il tag
     * appena messo — misurato: spariva entro 50 ms. */
    el.dispatchEvent(new InputEvent('input', { bubbles: true }));
    seguiCursore();
  };

  const marca = (tag) => {
    const voce = INLINE.find(([t]) => t === tag);
    if (voce && voce[2]) {
      const v = prompt(voce[3]);
      if (!v) return;
      return avvolgi(tag, { [voce[2]]: v });
    }
    avvolgi(tag);
  };

  /** Il blocco in cui sta il cursore: serve quando non c'è niente di selezionato. */
  const bloccoDelCursore = (nodo, el) => {
    let n = nodo;
    while (n && n !== el) {
      if (n.nodeType === 1 && getComputedStyle(n).display !== 'inline') return n;
      n = n.parentNode;
    }
    return el;
  };

  /* Maiuscole e minuscole sulla selezione — o, se non c'è selezione, sul blocco in
   * cui sta il cursore (il caso vero: un titolo battuto tutto maiuscolo che si
   * vuole rimettere in ordine, senza doverlo selezionare).
   *
   * Il testo si trasforma INTERO e poi si ridistribuisce nei nodi: «Alto e Basso» e
   * «Iniziale maiuscola» hanno bisogno di vedere la frase per sapere dove comincia
   * una parola o una frase, e pezzo per pezzo non lo saprebbero. I tag restano dove
   * sono, perché si riscrive solo il testo dentro di essi. */
  const cambiaCaso = (caso) => {
    ripristina();
    const el = ref.current;
    const s = document.getSelection();
    if (!s || !s.rangeCount) return;
    let r = s.getRangeAt(0);
    if (!el.contains(r.commonAncestorContainer)) return;
    if (r.collapsed) {
      const blocco = bloccoDelCursore(r.startContainer, el);
      r = document.createRange();
      r.selectNodeContents(blocco);
    }
    const pezzi = pezziDiTesto(r);
    if (!pezzi.length) return;
    const intero = pezzi.map(({ nodo, da, a }) => nodo.nodeValue.slice(da, a)).join('');
    const nuovo = applicaCaso(intero, caso);
    // Le quattro trasformazioni non cambiano la lunghezza (in italiano): se per
    // qualche carattere esotico succedesse, si ripiega sul pezzo per pezzo invece
    // di sfasare tutto il testo.
    const stessaLunghezza = nuovo.length === intero.length;
    let pos = 0;
    for (const { nodo, da, a } of pezzi) {
      const pezzo = nodo.nodeValue.slice(da, a);
      const fatto = stessaLunghezza ? nuovo.slice(pos, pos + pezzo.length) : applicaCaso(pezzo, caso);
      nodo.nodeValue = nodo.nodeValue.slice(0, da) + fatto + nodo.nodeValue.slice(a);
      pos += pezzo.length;
    }
    s.removeAllRanges();
    s.addRange(r);
    el.focus();
    el.dispatchEvent(new InputEvent('input', { bubbles: true }));
    seguiCursore();
  };

  const applicaBlocco = (tag) => {
    if (tag === 'section') return avvolgi('section');
    cmd('formatBlock', '<' + tag + '>');
  };

  /* «Rimuovi formattazione» toglie grassetto e corsivo (removeFormat), ma non sa
   * niente dei tag semantici: senza questo, mettere un <code> sarebbe una scelta
   * senza ritorno. Si scartano quindi anche gli elementi semantici che toccano la
   * selezione, tenendone il contenuto. */
  const smarca = () => {
    ripristina();
    document.execCommand('removeFormat');
    const el = ref.current;
    const s = document.getSelection();
    if (s && s.rangeCount) {
      const r = s.getRangeAt(0);
      for (const n of [...el.querySelectorAll(TAG_INLINE.join(','))]) {
        if (r.intersectsNode(n)) n.replaceWith(...n.childNodes);
      }
    }
    el.focus();
    emit();
    seguiCursore();
  };
  const clearAll = () => {
    ref.current.innerHTML = '';
    ref.current.focus();
    onChange('');
  };

  // I pulsanti NON sono tab-stop (tabIndex -1): il Tab passa dal campo di testo
  // direttamente al successivo, senza attraversare la toolbar (anche da nascosta).
  const Btn = ({ title, name, onClick }) => (
    <button type="button" title={title} tabIndex={-1} onClick={onClick}><Icon name={name} /></button>
  );

  return (
    <div className={'xhtml-control' + (focused ? ' focused' : '')}>
      {/* preventDefault: cliccando un pulsante l'editor non perde il focus,
          quindi la toolbar non sparisce a metà interazione. I menu a tendina sono
          esclusi: con il mousedown annullato non si aprirebbero — per loro c'è la
          selezione ricordata. */}
      {vista === 'anteprima' && (
      <div
        className="xhtml-toolbar"
        onMouseDown={(e) => {
          if (e.target.tagName !== 'SELECT') e.preventDefault();
        }}
      >
        <select
          className="xhtml-blocco"
          title="Tipo di blocco"
          tabIndex={-1}
          value={blocco}
          onChange={(e) => applicaBlocco(e.target.value)}
        >
          {BLOCCHI.map(([t, etichetta]) => (
            <option key={t} value={t}>
              {etichetta}
            </option>
          ))}
          <option value="section">Sezione</option>
        </select>
        <span className="xhtml-sep" />
        <Btn title="Grassetto" name="format_bold" onClick={() => cmd('bold')} />
        <Btn title="Corsivo" name="format_italic" onClick={() => cmd('italic')} />
        <Btn title="Sottolineato" name="format_underlined" onClick={() => cmd('underline')} />
        <span className="xhtml-sep" />
        <Btn title="Elenco puntato" name="format_list_bulleted" onClick={() => cmd('insertUnorderedList')} />
        <Btn title="Elenco numerato" name="format_list_numbered" onClick={() => cmd('insertOrderedList')} />
        <span className="xhtml-sep" />
        <select
          className="xhtml-marca"
          title="Marca la selezione"
          tabIndex={-1}
          value=""
          onChange={(e) => {
            marca(e.target.value);
            e.target.value = ''; // è un menu di comandi, non uno stato
          }}
        >
          <option value="" disabled>
            Marca…
          </option>
          {INLINE.map(([t, etichetta]) => (
            <option key={t} value={t}>
              {etichetta}
            </option>
          ))}
        </select>
        <select
          className="xhtml-caso"
          title="Maiuscole e minuscole"
          tabIndex={-1}
          value=""
          onChange={(e) => {
            cambiaCaso(e.target.value);
            e.target.value = '';
          }}
        >
          <option value="" disabled>
            Aa…
          </option>
          {CASI.map(([k, etichetta]) => (
            <option key={k} value={k}>
              {etichetta}
            </option>
          ))}
        </select>
        <Btn title="Link" name="link" onClick={addLink} />
        <Btn title="Rimuovi formattazione e marcature" name="format_clear" onClick={smarca} />
      </div>
      )}
      {/* Etichetta a sinistra, le due viste all'estrema destra, sulla stessa riga:
          il campo si prende la sua intestazione invece di lasciarla a chi lo usa,
          così le due viste stanno dove si guarda per capire di che campo si tratta.
          Sempre visibili: da «Codice» il campo di scrittura non c'è, e se stessero
          nella barra (che compare col fuoco) non si potrebbe tornare indietro. */}
      <div className="xhtml-intestazione">
        <label className="field-label xhtml-etichetta">
          {icona && <Icon name={icona} />}
          {label}
        </label>
        <div className="xhtml-viste" role="tablist">
          <button
            type="button"
            role="tab"
            title="Anteprima"
            aria-label="Anteprima"
            aria-selected={vista === 'anteprima'}
            className={vista === 'anteprima' ? 'attiva' : ''}
            tabIndex={-1}
            onMouseDown={(e) => e.preventDefault()}
            onClick={() => setVista('anteprima')}
          >
            <Icon name="visibility" />
          </button>
          <button
            type="button"
            role="tab"
            title="Codice XHTML"
            aria-label="Codice XHTML"
            aria-selected={vista === 'codice'}
            className={vista === 'codice' ? 'attiva' : ''}
            tabIndex={-1}
            onMouseDown={(e) => e.preventDefault()}
            onClick={() => setVista('codice')}
          >
            <Icon name="code" />
          </button>
        </div>
        {/* La × sta dove sta il + delle sezioni ripetibili: in fondo alla riga
            dell'etichetta. Un comando che riguarda TUTTO il campo si cerca lì,
            non sovrapposto alla prima riga di testo. */}
        {value ? (
          <button
            type="button"
            className="icon-btn xhtml-clear"
            title="Svuota"
            tabIndex={-1}
            onMouseDown={(e) => e.preventDefault()}
            onClick={clearAll}
          >
            <Icon name="close" />
          </button>
        ) : null}
      </div>
      <div
        ref={ref}
        className={'xhtml-editor' + (compact ? ' compact' : '')}
        contentEditable={enabled}
        onInput={emit}
        onFocus={() => {
          setFocused(true);
          seguiCursore();
        }}
        onBlur={() => setFocused(false)}
        suppressContentEditableWarning
        hidden={vista !== 'anteprima'}
      />
      {vista === 'codice' && (
        /* Il codice si scrive com'è e si salva com'è: normalizzarlo mentre si
           digita significherebbe togliere sotto le dita il tag ancora a metà. */
        <textarea
          className={'xhtml-codice code-font' + (compact ? ' compact' : '')}
          value={value ?? ''}
          spellCheck={false}
          disabled={!enabled}
          onChange={(e) => onChange(e.target.value)}
        />
      )}
    </div>
  );
}
