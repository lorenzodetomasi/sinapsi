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

const Icon = ({ name }) => <span className="material-symbols-outlined">{name}</span>;

export default function XhtmlEditor({ value, onChange, enabled = true, compact = false }) {
  const ref = useRef(null);
  const [focused, setFocused] = useState(false);

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
    if (el && !stessoContenuto(el, value)) el.innerHTML = value ?? '';
  }, [value]);

  // Un contenteditable "svuotato" resta con un <br>: lo trattiamo come vuoto (''),
  // così il campo non risulta compilato e il nodo sparisce dal JSON.
  const emit = () => {
    const el = ref.current;
    const html = el.innerHTML;
    const empty = !el.textContent.trim() && !/<(img|hr|iframe|video|audio)\b/i.test(html);
    onChange(empty ? '' : toXhtml(html));
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
        onBlur={() => setFocused(false)}
        suppressContentEditableWarning
      />
    </div>
  );
}
