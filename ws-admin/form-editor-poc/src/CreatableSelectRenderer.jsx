import { useEffect, useRef, useState } from 'react';
import { rankWith, and, uiTypeIs, schemaMatches, optionIs } from '@jsonforms/core';
import { withJsonFormsControlProps } from '@jsonforms/react';

// Selettore multi-valore con RICERCA tra valori predefiniti (options.suggestions)
// e possibilità di inserire testo libero (creatable). Usato per @type.
const CreatableSelect = ({ data, handleChange, path, label, uischema }) => {
  const items = Array.isArray(data) ? data : [];
  const suggestions = uischema?.options?.suggestions || [];
  const icon = uischema?.options?.icon;
  const [query, setQuery] = useState('');
  const [open, setOpen] = useState(false);
  const boxRef = useRef(null);
  const inputRef = useRef(null);

  useEffect(() => {
    const onDoc = (e) => {
      if (boxRef.current && !boxRef.current.contains(e.target)) setOpen(false);
    };
    document.addEventListener('mousedown', onDoc);
    return () => document.removeEventListener('mousedown', onDoc);
  }, []);

  const add = (val) => {
    const v = (val ?? '').trim();
    setQuery('');
    if (!v || items.includes(v)) return;
    handleChange(path, [...items, v]);
  };
  const remove = (i) => handleChange(path, items.filter((_, j) => j !== i));

  const q = query.toLowerCase();
  const filtered = suggestions.filter((s) => !items.includes(s) && s.toLowerCase().includes(q));
  const canCreate = query.trim() && !suggestions.includes(query.trim()) && !items.includes(query.trim());

  return (
    <div className="control creatable-select" ref={boxRef}>
      <label className="field-label">
        {icon && <span className="material-symbols-outlined">{icon}</span>}
        {label}
      </label>
      <div className="cs-box" onClick={() => { setOpen(true); inputRef.current?.focus(); }}>
        {items.map((it, i) => (
          <span className="tag" key={i}>
            {it}
            <button type="button" title="Rimuovi" onClick={() => remove(i)}>
              <span className="material-symbols-outlined">close</span>
            </button>
          </span>
        ))}
        <input
          ref={inputRef}
          className="cs-input"
          value={query}
          placeholder={items.length ? '' : 'Cerca o scrivi…'}
          onFocus={() => setOpen(true)}
          onChange={(e) => {
            setQuery(e.target.value);
            setOpen(true);
          }}
          onKeyDown={(e) => {
            if (e.key === 'Enter') {
              e.preventDefault();
              add(query);
            } else if (e.key === 'Backspace' && !query && items.length) {
              remove(items.length - 1);
            }
          }}
        />
      </div>
      {open && (filtered.length > 0 || canCreate) && (
        <ul className="cs-menu">
          {filtered.map((s) => (
            <li key={s} onMouseDown={(e) => { e.preventDefault(); add(s); }}>
              {s}
            </li>
          ))}
          {canCreate && (
            <li className="cs-create" onMouseDown={(e) => { e.preventDefault(); add(query); }}>
              <span className="material-symbols-outlined">add</span> Aggiungi “{query.trim()}”
            </li>
          )}
        </ul>
      )}
    </div>
  );
};

export const creatableSelectTester = rankWith(
  20,
  and(
    uiTypeIs('Control'),
    schemaMatches((s) => s?.type === 'array' && s?.items?.type === 'string'),
    optionIs('creatable', true)
  )
);

export default withJsonFormsControlProps(CreatableSelect);
