import { useEffect, useRef, useState } from 'react';
import { rankWith, and, uiTypeIs, optionIs } from '@jsonforms/core';
import { withJsonFormsControlProps } from '@jsonforms/react';
import { usePointerReorder } from './usePointerReorder.js';

// Multi-select a opzioni FISSE: chip (con title) e ricerca tra le opzioni,
// nessun valore personalizzato. Le chip si riordinano col drag (mouse + touch).
// options.suggestions = [{const,title}] o stringhe.
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
  const move = (from, to) => {
    const next = [...items];
    const [m] = next.splice(from, 1);
    next.splice(to > from ? to - 1 : to, 0, m);
    handleChange(path, next);
  };
  const { dragIndex, overIndex, onHandlePointerDown } = usePointerReorder(move, { axis: 'x' });

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
      <div className="tags" data-reorder-root onClick={() => { setOpen(true); inputRef.current?.focus(); }}>
        {items.map((val, i) => {
          const cls = ['tag'];
          if (dragIndex === i) cls.push('dragging');
          if (overIndex === i && dragIndex !== null) cls.push('insert-before');
          return (
            <span className={cls.join(' ')} key={val} data-reorder-index={i}>
              <span
                className="tag-handle"
                title="Trascina per riordinare"
                onPointerDown={(e) => onHandlePointerDown(e, i)}
                onClick={(e) => e.stopPropagation()}
              >
                <span className="material-symbols-outlined">drag_indicator</span>
              </span>
              {titleOf(val)}
              <button type="button" title="Rimuovi" onClick={(e) => { e.stopPropagation(); remove(val); }}>
                <span className="material-symbols-outlined">close</span>
              </button>
            </span>
          );
        })}
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
              <li key={o.const + '::' + o.title} onClick={() => add(o.const)}>
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
