import { rankWith, and, uiTypeIs, schemaMatches } from '@jsonforms/core';
import { withJsonFormsControlProps } from '@jsonforms/react';
import XhtmlEditor from './XhtmlEditor.jsx';

// Array di oggetti resi come "card" (fieldset) su più righe, invece della tabella
// stretta dei vanilla renderer. Ogni campo è reso in base a type/format dello schema.

const toLocal = (v) => (v ? String(v).slice(0, 16) : '');

function FieldControl({ name, schema, value, onChange }) {
  const label = schema.title || name;
  if (schema.format === 'xhtml') {
    return (
      <div className="rf full">
        <label className="field-label">{label}</label>
        <XhtmlEditor value={value} onChange={onChange} compact />
      </div>
    );
  }
  if (schema.format === 'date-time') {
    return (
      <div className="rf rf-date">
        <label className="field-label">{label}</label>
        <input type="datetime-local" value={toLocal(value)} onChange={(e) => onChange(e.target.value)} />
      </div>
    );
  }
  return (
    <div className="rf rf-text">
      <label className="field-label">{label}</label>
      <input type="text" value={value ?? ''} onChange={(e) => onChange(e.target.value)} />
    </div>
  );
}

const RepeatableObject = ({ data, handleChange, path, label, schema, uischema }) => {
  const items = Array.isArray(data) ? data : [];
  const props = schema.items?.properties || {};
  const icon = uischema?.options?.icon;
  const variant = uischema?.options?.variant || 'stack'; // 'row' (organizer) | 'stack' (subEvent)
  const update = (arr) => handleChange(path, arr);
  const setField = (i, key, val) => {
    const a = items.map((x) => ({ ...x }));
    a[i][key] = val;
    update(a);
  };

  return (
    <div className={'control repeat-object variant-' + variant}>
      <div className="repeat-head">
        <span className="field-label">
          {icon && <span className="material-symbols-outlined">{icon}</span>}
          {label}
        </span>
        <button type="button" className="icon-btn" title="Aggiungi" onClick={() => update([...items, {}])}>
          <span className="material-symbols-outlined">add</span>
        </button>
      </div>
      <div className="cards">
        {items.map((item, i) => (
          <fieldset className="card" key={i}>
            <div className="card-fields">
              {Object.entries(props).map(([key, sub]) => (
                <FieldControl
                  key={key}
                  name={key}
                  schema={sub}
                  value={item?.[key]}
                  onChange={(v) => setField(i, key, v)}
                />
              ))}
            </div>
            <button
              type="button"
              className="icon-btn card-remove"
              title="Rimuovi"
              onClick={() => update(items.filter((_, j) => j !== i))}
            >
              <span className="material-symbols-outlined">close</span>
            </button>
          </fieldset>
        ))}
      </div>
    </div>
  );
};

export const repeatableObjectTester = rankWith(
  10,
  and(uiTypeIs('Control'), schemaMatches((s) => s?.type === 'array' && s?.items?.type === 'object'))
);

export default withJsonFormsControlProps(RepeatableObject);
