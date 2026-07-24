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

const Icon = ({ name }) => <span className="material-symbols-outlined">{name}</span>;

export default function XhtmlEditor({ value, onChange, enabled = true, compact = false }) {
  const ref = useRef(null);
  const [focused, setFocused] = useState(false);

  useEffect(() => {
    const el = ref.current;
    if (el && el.innerHTML !== (value ?? '')) el.innerHTML = value ?? '';
  }, [value]);

  const emit = () => onChange(toXhtml(ref.current.innerHTML));
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

  return (
    <div className={'xhtml-control' + (focused ? ' focused' : '')}>
      {/* preventDefault: cliccando un pulsante l'editor non perde il focus,
          quindi la toolbar non sparisce a metà interazione */}
      <div className="xhtml-toolbar" onMouseDown={(e) => e.preventDefault()}>
        <button type="button" title="Grassetto" onClick={() => cmd('bold')}><Icon name="format_bold" /></button>
        <button type="button" title="Corsivo" onClick={() => cmd('italic')}><Icon name="format_italic" /></button>
        <button type="button" title="Sottolineato" onClick={() => cmd('underline')}><Icon name="format_underlined" /></button>
        <span className="xhtml-sep" />
        <button type="button" title="Elenco puntato" onClick={() => cmd('insertUnorderedList')}><Icon name="format_list_bulleted" /></button>
        <button type="button" title="Elenco numerato" onClick={() => cmd('insertOrderedList')}><Icon name="format_list_numbered" /></button>
        <span className="xhtml-sep" />
        <button type="button" title="Link" onClick={addLink}><Icon name="link" /></button>
        <button type="button" title="Rimuovi formattazione" onClick={() => cmd('removeFormat')}><Icon name="format_clear" /></button>
      </div>
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
