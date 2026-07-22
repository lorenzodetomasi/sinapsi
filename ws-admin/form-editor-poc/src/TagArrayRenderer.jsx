import { useEffect, useRef, useState } from 'react';
import { rankWith, and, uiTypeIs, schemaMatches } from '@jsonforms/core';
import { withJsonFormsControlProps } from '@jsonforms/react';

// Array di stringhe come tag editabili: si scrive direttamente dentro al tag,
// una × per rimuoverlo e un + per aggiungerne uno.
// Se l'uischema fornisce `options.suggestions`, digitando nel tag compare
// l'autocomplete con i valori proposti; resta sempre possibile scrivere un
// valore custom (basta non selezionare nulla dall'elenco).
const TagArray = ({ data, handleChange, path, label, uischema }) => {
  const items = Array.isArray(data) ? data : [];
  const icon = uischema?.options?.icon;
  const suggestions = uischema?.options?.suggestions || [];

  const [openIndex, setOpenIndex] = useState(null);
  const rootRef = useRef(null);
  const inputsRef = useRef([]);
  const focusLast = useRef(false);

  useEffect(() => {
    const onDoc = (e) => {
      if (rootRef.current && !rootRef.current.contains(e.target)) setOpenIndex(null);
    };
    document.addEventListener('mousedown', onDoc);
    return () => document.removeEventListener('mousedown', onDoc);
  }, []);

  // Dopo il +, porta il focus sul nuovo tag vuoto e apre i suggerimenti.
  useEffect(() => {
    if (!focusLast.current) return;
    focusLast.current = false;
    inputsRef.current[items.length - 1]?.focus();
    if (suggestions.length) setOpenIndex(items.length - 1);
  }, [items.length, suggestions.length]);

  const update = (arr) => handleChange(path, arr);
  const setAt = (i, value) => {
    const next = [...items];
    next[i] = value;
    update(next);
  };
  const removeAt = (i) => {
    update(items.filter((_, j) => j !== i));
    setOpenIndex(null);
  };
  const add = () => {
    focusLast.current = true;
    update([...items, '']);
  };

  // Suggerimenti filtrati sul testo del tag, escludendo quelli già usati altrove.
  const optionsFor = (i) => {
    const query = (items[i] ?? '').trim().toLowerCase();
    return suggestions.filter(
      (s) =>
        !items.some((it, j) => j !== i && (it ?? '').trim().toLowerCase() === s.toLowerCase()) &&
        (!query || s.toLowerCase().includes(query))
    );
  };

  return (
    <div className="control tag-array" ref={rootRef}>
      <label className="field-label">
        {icon && <span className="material-symbols-outlined">{icon}</span>}
        {label}
      </label>
      <div className="tags">
        {items.map((it, i) => {
          const options = openIndex === i ? optionsFor(i) : [];
          return (
            <span className="tag" key={i}>
              <input
                ref={(el) => (inputsRef.current[i] = el)}
                value={it ?? ''}
                /* larghezza aderente al testo, più compatta su mobile */
                style={{ width: `${Math.max(3, (it ?? '').length + 1)}ch` }}
                onFocus={() => suggestions.length && setOpenIndex(i)}
                onChange={(e) => {
                  setAt(i, e.target.value);
                  if (suggestions.length) setOpenIndex(i);
                }}
                onKeyDown={(e) => {
                  if (e.key === 'Enter') {
                    e.preventDefault();
                    setOpenIndex(null);
                  } else if (e.key === 'Escape') {
                    setOpenIndex(null);
                  }
                }}
              />
              <button type="button" title="Rimuovi" onClick={() => removeAt(i)}>
                <span className="material-symbols-outlined">close</span>
              </button>
              {options.length > 0 && (
                /* preventDefault sul mousedown evita che l'input perda il focus;
                   la selezione avviene sul click. */
                <ul className="cs-menu tag-menu" onMouseDown={(e) => e.preventDefault()}>
                  {options.map((s) => (
                    <li
                      key={s}
                      onClick={() => {
                        setAt(i, s);
                        setOpenIndex(null);
                      }}
                    >
                      {s}
                    </li>
                  ))}
                </ul>
              )}
            </span>
          );
        })}
        <button type="button" className="icon-btn tag-add" title="Aggiungi" onClick={add}>
          <span className="material-symbols-outlined">add</span>
        </button>
      </div>
    </div>
  );
};

// Match su `format: "tags"` (dichiarativo, come format: "xhtml"), con fallback
// su qualsiasi array di stringhe.
export const tagArrayTester = rankWith(
  10,
  and(
    uiTypeIs('Control'),
    schemaMatches((s) => s?.type === 'array' && (s?.format === 'tags' || s?.items?.type === 'string'))
  )
);

export default withJsonFormsControlProps(TagArray);
