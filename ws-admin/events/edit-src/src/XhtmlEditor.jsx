import { useEffect, useRef, useState } from 'react';

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

/* Il cursore misurato in CARATTERI dall'inizio del campo: è l'unica misura che
 * sopravvive a una riscrittura, perché i nodi su cui poggiava non esistono più. */
function posizioneCursore(el) {
  const s = document.getSelection();
  if (!s || !s.rangeCount) return null;
  const r = s.getRangeAt(0);
  if (!el.contains(r.startContainer)) return null;
  const fin_qui = r.cloneRange();
  fin_qui.selectNodeContents(el);
  fin_qui.setEnd(r.startContainer, r.startOffset);
  return fin_qui.toString().length;
}

/** Rimette il cursore a `n` caratteri dall'inizio; se il testo si è accorciato, in fondo. */
function rimettiCursore(el, n) {
  if (n == null) return;
  const passo = document.createTreeWalker(el, NodeFilter.SHOW_TEXT);
  const r = document.createRange();
  let visti = 0;
  let nodo;
  let messo = false;
  while ((nodo = passo.nextNode())) {
    const quanti = nodo.textContent.length;
    if (visti + quanti >= n) {
      r.setStart(nodo, n - visti);
      messo = true;
      break;
    }
    visti += quanti;
  }
  if (!messo) {
    r.selectNodeContents(el);
    r.collapse(false);
  } else {
    r.collapse(true);
  }
  const s = document.getSelection();
  s.removeAllRanges();
  s.addRange(r);
}

const Icon = ({ name }) => <span className="material-symbols-outlined">{name}</span>;

export default function XhtmlEditor({ value, onChange, enabled = true, compact = false }) {
  const ref = useRef(null);
  const [focused, setFocused] = useState(false);

  /* Quello che il campo emette torna indietro come `value` un istante dopo: è la
   * nostra eco, e il contenuto è GIÀ quello. Riscriverlo non servirebbe a niente e
   * butterebbe via il cursore — i nodi su cui poggiava non esistono più e il punto
   * d'inserimento torna in cima. Se succede a ogni battuta il campo è
   * inutilizzabile: si scrive un carattere e i successivi finiscono all'inizio.
   *
   * Perciò le battute emesse si tengono da parte e si riconoscono al ritorno. Una
   * MANCIATA, non solo l'ultima: scrivendo in fretta il valore può tornare con
   * qualche battuta di ritardo, e riconoscere solo l'ultima lascerebbe passare le
   * altre come se venissero da fuori.
   *
   * Il confronto sul testo resta come seconda rete (grafie diverse della stessa
   * cosa), e se una riscrittura serve davvero il cursore si rimette dov'era. */
  const echi = useRef([]);
  const sincronizza = () => {
    const el = ref.current;
    if (el && !stessoContenuto(el, value)) el.innerHTML = value ?? '';
  };
  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    const v = value ?? '';
    if (echi.current.includes(v)) return;
    if (stessoContenuto(el, v)) return;
    const dentro = document.activeElement === el;
    const dove = dentro ? posizioneCursore(el) : null;
    el.innerHTML = v;
    if (dentro) rimettiCursore(el, dove);
  }, [value]);

  // Un contenteditable "svuotato" resta con un <br>: lo trattiamo come vuoto (''),
  // così il campo non risulta compilato e il nodo sparisce dal JSON.
  const emit = () => {
    const el = ref.current;
    const html = el.innerHTML;
    const empty = !el.textContent.trim() && !/<(img|hr|iframe|video|audio)\b/i.test(html);
    const uscita = empty ? '' : toXhtml(html);
    echi.current = [...echi.current.slice(-9), uscita];
    onChange(uscita);
  };
  const cmd = (command, val = null) => {
    document.execCommand('styleWithCSS', false, false); // preferisce tag a stili inline
    document.execCommand(command, false, val);
    ref.current.focus();
    emit();
  };
  const addLink = () => {
    const url = prompt('URL del link:');
    if (url) cmd('createLink', url);
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
          quindi la toolbar non sparisce a metà interazione */}
      <div className="xhtml-toolbar" onMouseDown={(e) => e.preventDefault()}>
        <Btn title="Grassetto" name="format_bold" onClick={() => cmd('bold')} />
        <Btn title="Corsivo" name="format_italic" onClick={() => cmd('italic')} />
        <Btn title="Sottolineato" name="format_underlined" onClick={() => cmd('underline')} />
        <span className="xhtml-sep" />
        <Btn title="Elenco puntato" name="format_list_bulleted" onClick={() => cmd('insertUnorderedList')} />
        <Btn title="Elenco numerato" name="format_list_numbered" onClick={() => cmd('insertOrderedList')} />
        <span className="xhtml-sep" />
        <Btn title="Link" name="link" onClick={addLink} />
        <Btn title="Rimuovi formattazione" name="format_clear" onClick={() => cmd('removeFormat')} />
      </div>
      {value ? (
        <button
          type="button"
          className="xhtml-clear"
          title="Svuota"
          tabIndex={-1}
          onMouseDown={(e) => e.preventDefault()}
          onClick={clearAll}
        >
          <Icon name="close" />
        </button>
      ) : null}
      <div
        ref={ref}
        className={'xhtml-editor' + (compact ? ' compact' : '')}
        contentEditable={enabled}
        onInput={emit}
        onFocus={() => setFocused(true)}
        onBlur={() => {
          setFocused(false);
          sincronizza(); // qui riscrivere è innocuo: il cursore non è più qui
        }}
        suppressContentEditableWarning
      />
    </div>
  );
}
