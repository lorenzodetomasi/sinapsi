import { useEffect, useRef, useState } from 'react';
import { rankWith, and, uiTypeIs, optionIs } from '@jsonforms/core';
import { withJsonFormsControlProps } from '@jsonforms/react';

// Multi-select a opzioni FISSE: chip (con title) e ricerca tra le opzioni,
// nessun valore personalizzato. options.suggestions = [{const,title}] o stringhe.
const norm = (o) => (typeof o === 'string' ? { const: o, title: o } : o);

const MultiSelect = ({ data, handleChange, path, label, uischema, visible }) => {
  if (visible === false) return null;
  const items = Array.isArray(data) ? data : [];
  const icon = uischema?.options?.icon;
  const options = (uischema?.options?.suggestions || []).map(norm);
  const [query, setQuery] = useState('');
  const [open, setOpen] = useState(false);
  const ref = useRef(null);
  const inputRef = useRef(null);

  useEffect(() => {
    const onDoc = (e) => {
      if (ref.current && !ref.current.contains(e.target)) setOpen(false);
    };
    document.addEventListener('mousedown', onDoc);
    return () => document.removeEventListener('mousedown', onDoc);
  }, []);

  const titleOf = (val) => options.find((o) => o.const === val)?.title ?? val;
  const add = (val) => {
    if (!items.includes(val)) handleChange(path, [...items, val]);
    setQuery('');
  };
  const remove = (val) => handleChange(path, items.filter((v) => v !== val));

  const q = query.trim().toLowerCase();
  const filtered = options.filter(
    (o) => !items.includes(o.const) && (o.title.toLowerCase().includes(q) || o.const.toLowerCase().includes(q))
  );

  return (
    <div className="control tag-array multiselect" ref={ref}>
      <label className="field-label">
        {icon && <span className="material-symbols-outlined">{icon}</span>}
        {label}
      </label>
      <div className="tags" onClick={() => { setOpen(true); inputRef.current?.focus(); }}>
        {items.map((val) => (
          <span className="tag" key={val}>
            {titleOf(val)}
            <button type="button" title="Rimuovi" onClick={() => remove(val)}>
              <span className="material-symbols-outlined">close</span>
            </button>
          </span>
        ))}
        <input
          ref={inputRef}
          className="ms-input"
          value={query}
          placeholder={items.length ? '' : 'Cerca…'}
          onFocus={() => setOpen(true)}
          onChange={(e) => { setQuery(e.target.value); setOpen(true); }}
          onKeyDown={(e) => e.key === 'Escape' && setOpen(false)}
        />
        {open && filtered.length > 0 && (
          <ul className="cs-menu ms-menu" onMouseDown={(e) => e.preventDefault()}>
            {filtered.map((o) => (
              <li key={o.const} onClick={() => add(o.const)}>
                {o.title}
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  );
};

export const multiSelectTester = rankWith(15, and(uiTypeIs('Control'), optionIs('select', true)));

export default withJsonFormsControlProps(MultiSelect);
