import { Fragment, useRef, useState } from 'react';
import { rankWith, and, uiTypeIs, schemaMatches } from '@jsonforms/core';
import { withJsonFormsControlProps } from '@jsonforms/react';
import XhtmlEditor from './XhtmlEditor.jsx';

// Array di oggetti resi come "card" su più righe. Ogni card ha una striscia
// verticale a sinistra (maniglia di riordino + rimozione) e si può riordinare
// con drag & drop; tra una card e l'altra compare un + per inserire in mezzo.

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

/** Spazio cliccabile tra due card: rivela un + per inserire lì un elemento. */
function InsertGap({ onInsert }) {
  return (
    <div className="insert-gap">
      <button type="button" className="icon-btn" title="Inserisci qui" onClick={onInsert}>
        <span className="material-symbols-outlined">add</span>
      </button>
    </div>
  );
}

const RepeatableObject = ({ data, handleChange, path, label, schema, uischema }) => {
  const items = Array.isArray(data) ? data : [];
  const props = schema.items?.properties || {};
  const icon = uischema?.options?.icon;
  const variant = uischema?.options?.variant || 'stack';

  const [dragIndex, setDragIndex] = useState(null);
  const [insertAt, setInsertAt] = useState(null);
  const handleGrabbed = useRef(false);

  const update = (arr) => handleChange(path, arr);
  const setField = (i, key, val) => {
    const next = items.map((x) => ({ ...x }));
    next[i][key] = val;
    update(next);
  };
  const insertAtIndex = (at) => {
    const next = [...items];
    next.splice(at, 0, {});
    update(next);
  };
  const moveCard = (from, to) => {
    if (from === null || to === null) return;
    const next = [...items];
    const [moved] = next.splice(from, 1);
    next.splice(to > from ? to - 1 : to, 0, moved);
    update(next);
  };
  const endDrag = () => {
    setDragIndex(null);
    setInsertAt(null);
    handleGrabbed.current = false;
  };

  return (
    <div className={'control repeat-object variant-' + variant}>
      <div className="repeat-head">
        <span className="field-label">
          {icon && <span className="material-symbols-outlined">{icon}</span>}
          {label}
        </span>
        <button type="button" className="icon-btn" title="Aggiungi" onClick={() => insertAtIndex(items.length)}>
          <span className="material-symbols-outlined">add</span>
        </button>
      </div>

      <div className="cards">
        {items.map((item, i) => {
          const classes = ['card'];
          if (dragIndex === i) classes.push('dragging');
          if (insertAt === i) classes.push('insert-before');
          if (insertAt === items.length && i === items.length - 1) classes.push('insert-after');

          return (
            <Fragment key={i}>
              {i > 0 && <InsertGap onInsert={() => insertAtIndex(i)} />}
              <fieldset
                className={classes.join(' ')}
                draggable
                onDragStart={(e) => {
                  if (!handleGrabbed.current) {
                    e.preventDefault(); // si trascina solo dalla maniglia
                    return;
                  }
                  setDragIndex(i);
                  e.dataTransfer.effectAllowed = 'move';
                  e.dataTransfer.setData('text/plain', String(i));
                }}
                onDragOver={(e) => {
                  if (dragIndex === null) return;
                  e.preventDefault();
                  e.dataTransfer.dropEffect = 'move';
                  // metà superiore → inserisci sopra, metà inferiore → sotto
                  const box = e.currentTarget.getBoundingClientRect();
                  setInsertAt(e.clientY < box.top + box.height / 2 ? i : i + 1);
                }}
                onDrop={(e) => {
                  e.preventDefault();
                  moveCard(dragIndex, insertAt);
                  endDrag();
                }}
                onDragEnd={endDrag}
              >
                <div className="card-rail">
                  <button
                    type="button"
                    className="icon-btn card-remove"
                    title="Rimuovi"
                    onClick={() => update(items.filter((_, j) => j !== i))}
                  >
                    <span className="material-symbols-outlined">close</span>
                  </button>
                  <span
                    className="card-handle"
                    title="Trascina per riordinare"
                    onMouseDown={() => (handleGrabbed.current = true)}
                    onMouseUp={() => (handleGrabbed.current = false)}
                  >
                    <span className="material-symbols-outlined">drag_indicator</span>
                  </span>
                </div>

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
              </fieldset>
            </Fragment>
          );
        })}
      </div>
    </div>
  );
};

export const repeatableObjectTester = rankWith(
  10,
  and(uiTypeIs('Control'), schemaMatches((s) => s?.type === 'array' && s?.items?.type === 'object'))
);

export default withJsonFormsControlProps(RepeatableObject);
