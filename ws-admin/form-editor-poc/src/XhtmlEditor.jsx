import { useEffect, useRef, useState } from 'react';

// Editor rich-text XHTML riutilizzabile (presentazionale): value + onChange.
const VOID_ELEMENTS = 'area|base|br|col|embed|hr|img|input|link|meta|param|source|track|wbr';
export function toXhtml(html) {
  return html.replace(
    new RegExp(`<(${VOID_ELEMENTS})\\b([^>]*?)\\s*/?>`, 'gi'),
    (_, tag, attrs) => `<${tag}${attrs} />`
  );
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
    document.execCommand(command, false, val);
    ref.current.focus();
    emit();
  };

  return (
    <div className={'xhtml-control' + (focused ? ' focused' : '')}>
      {/* preventDefault: cliccando un pulsante l'editor non perde il focus,
          quindi la toolbar non sparisce a metà interazione */}
      <div className="xhtml-toolbar" onMouseDown={(e) => e.preventDefault()}>
        <button type="button" title="Grassetto" onClick={() => cmd('bold')}><Icon name="format_bold" /></button>
        <button type="button" title="Corsivo" onClick={() => cmd('italic')}><Icon name="format_italic" /></button>
        <button type="button" title="Elenco" onClick={() => cmd('insertUnorderedList')}><Icon name="format_list_bulleted" /></button>
        <button type="button" title="Link" onClick={() => cmd('createLink', prompt('URL del link:') || '')}><Icon name="link" /></button>
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
