import { useEffect, useRef, useState } from 'react';
import { rankWith, and, uiTypeIs, isStringControl, optionIs } from '@jsonforms/core';
import { withJsonFormsControlProps } from '@jsonforms/react';

// Select a valore SINGOLO con ricerca tra valori predefiniti (options.suggestions).
// Accetta anche testo libero, utile finché il vocabolario non è definitivo.
const SearchSelect = ({ data, handleChange, path, label, id, uischema, enabled }) => {
  const suggestions = uischema?.options?.suggestions || [];
  const icon = uischema?.options?.icon;
  const [query, setQuery] = useState(null); // null = mostra il valore corrente
  const [open, setOpen] = useState(false);
  const boxRef = useRef(null);

  useEffect(() => {
    const onDoc = (e) => {
      if (boxRef.current && !boxRef.current.contains(e.target)) {
        setOpen(false);
        setQuery(null);
      }
    };
    document.addEventListener('mousedown', onDoc);
    return () => document.removeEventListener('mousedown', onDoc);
  }, []);

  const shown = query === null ? (data ?? '') : query;
  const commit = (val) => {
    handleChange(path, val || undefined);
    setQuery(null);
    setOpen(false);
  };

  const q = (query ?? '').toLowerCase();
  const filtered = query === null ? suggestions : suggestions.filter((s) => s.toLowerCase().includes(q));

  return (
    <div className="control search-select" ref={boxRef}>
      <label className="field-label" htmlFor={id}>
        {icon && <span className="material-symbols-outlined">{icon}</span>}
        {label}
      </label>
      <input
        id={id}
        className="ss-input"
        type="text"
        value={shown}
        disabled={enabled === false}
        placeholder="Cerca o scrivi…"
        onFocus={() => { setOpen(true); setQuery(''); }}
        onChange={(e) => { setQuery(e.target.value); setOpen(true); }}
        onKeyDown={(e) => {
          if (e.key === 'Enter') { e.preventDefault(); commit(shown.trim()); }
          if (e.key === 'Escape') { setQuery(null); setOpen(false); }
        }}
      />
      {open && filtered.length > 0 && (
        <ul className="cs-menu">
          {filtered.map((s) => (
            <li key={s} onMouseDown={(e) => { e.preventDefault(); commit(s); }}>
              {s}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
};

export const searchSelectTester = rankWith(
  20,
  and(uiTypeIs('Control'), isStringControl, optionIs('searchable', true))
);

export default withJsonFormsControlProps(SearchSelect);
