import { useEffect, useRef } from 'react';
import { rankWith, and, isStringControl, schemaMatches } from '@jsonforms/core';
import { withJsonFormsControlProps } from '@jsonforms/react';

// Normalizzazione leggera verso XHTML: auto-chiude tutti gli elementi "void"
// (img, br, hr, input, …) che i browser serializzano senza slash. La validazione/
// correzione XHTML stretta resta delegata alla pipeline PHP (validate_json + fix_xhtml).
const VOID_ELEMENTS = 'area|base|br|col|embed|hr|img|input|link|meta|param|source|track|wbr';
function toXhtml(html) {
  return html.replace(
    new RegExp(`<(${VOID_ELEMENTS})\\b([^>]*?)\\s*/?>`, 'gi'),
    (_, tag, attrs) => `<${tag}${attrs} />`
  );
}

const XhtmlControl = ({ data, handleChange, path, label, id, enabled }) => {
  const ref = useRef(null);

  // Imposta l'HTML iniziale e sincronizza solo su modifiche ESTERNE (es. reset),
  // per non far saltare il cursore durante la digitazione.
  useEffect(() => {
    const el = ref.current;
    if (el && el.innerHTML !== (data ?? '')) {
      el.innerHTML = data ?? '';
    }
  }, [data]);

  const onInput = () => handleChange(path, toXhtml(ref.current.innerHTML));
  const cmd = (command, value = null) => {
    document.execCommand(command, false, value);
    ref.current.focus();
    onInput();
  };

  return (
    <div className="xhtml-control">
      <label htmlFor={id}>{label}</label>
      <div className="xhtml-toolbar">
        <button type="button" onClick={() => cmd('bold')}><b>B</b></button>
        <button type="button" onClick={() => cmd('italic')}><i>I</i></button>
        <button type="button" onClick={() => cmd('insertUnorderedList')}>• Lista</button>
        <button type="button" onClick={() => cmd('createLink', prompt('URL del link:') || '')}>🔗 Link</button>
        <button type="button" onClick={() => cmd('insertHTML', '<br />')}>↵ A capo</button>
      </div>
      <div
        id={id}
        ref={ref}
        className="xhtml-editor"
        contentEditable={enabled !== false}
        onInput={onInput}
        suppressContentEditableWarning
      />
    </div>
  );
};

export const xhtmlControlTester = rankWith(
  10,
  and(isStringControl, schemaMatches((s) => s && s.format === 'xhtml'))
);

export default withJsonFormsControlProps(XhtmlControl);
