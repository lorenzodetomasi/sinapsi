import { rankWith, and, uiTypeIs, schemaMatches } from '@jsonforms/core';
import { withJsonFormsControlProps } from '@jsonforms/react';

// Array di stringhe come tag compatti: ogni tag è un input con una × per rimuoverlo,
// più un + per aggiungerne uno. Usato per @type e keywords.
const TagArray = ({ data, handleChange, path, label, uischema }) => {
  const items = Array.isArray(data) ? data : [];
  const icon = uischema?.options?.icon;
  const update = (arr) => handleChange(path, arr);

  return (
    <div className="control tag-array">
      <label className="field-label">
        {icon && <span className="material-symbols-outlined">{icon}</span>}
        {label}
      </label>
      <div className="tags">
        {items.map((it, i) => (
          <span className="tag" key={i}>
            <input
              value={it ?? ''}
              size={Math.max(4, (it ?? '').length)}
              onChange={(e) => {
                const a = [...items];
                a[i] = e.target.value;
                update(a);
              }}
            />
            <button type="button" title="Rimuovi" onClick={() => update(items.filter((_, j) => j !== i))}>
              <span className="material-symbols-outlined">close</span>
            </button>
          </span>
        ))}
        <button type="button" className="tag-add" title="Aggiungi" onClick={() => update([...items, ''])}>
          <span className="material-symbols-outlined">add</span>
        </button>
      </div>
    </div>
  );
};

export const tagArrayTester = rankWith(
  10,
  and(uiTypeIs('Control'), schemaMatches((s) => s?.type === 'array' && s?.items?.type === 'string'))
);

export default withJsonFormsControlProps(TagArray);
