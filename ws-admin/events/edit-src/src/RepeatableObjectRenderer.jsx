import { Fragment, useId } from 'react';
import { rankWith, and, uiTypeIs, schemaMatches } from '@jsonforms/core';
import { withJsonFormsControlProps, useJsonForms } from '@jsonforms/react';
import { componi, dataDi, oraDi } from './quando.js';
import XhtmlEditor from './XhtmlEditor.jsx';
import PlaceInput from './PlaceInput.jsx';
import { EntityNameInput, EntityIdInput } from './EntityInput.jsx';
import { usePointerReorder } from './usePointerReorder.js';

// Array di oggetti resi come "card" su più righe. Ogni card ha una striscia
// verticale a sinistra (maniglia di riordino + rimozione) e si può riordinare
// con drag & drop; tra una card e l'altra compare un + per inserire in mezzo.

const toLocal = (v) => (v ? String(v).slice(0, 16) : '');

function FieldControl({ name, schema, item, value, onChange, onPickPlace, onPatch }) {
  const label = schema.title || name;
  const listId = useId();
  const ctx = useJsonForms();
  // Entità del sito (organizzatori): si sceglie dall'elenco del server, oppure si
  // incolla l'@id e il nome arriva da solo. Scrivono nome e @id INSIEME.
  if (schema.format === 'entity') {
    return <EntityNameInput label={label} value={value} onChange={onChange} onPatch={onPatch} />;
  }
  if (schema.format === 'entity-id') {
    return <EntityIdInput label={label} value={value} name={item?.name} onChange={onChange} onPatch={onPatch} />;
  }
  // Autocomplete Google Places: compila il nome e registra il Place ID
  if (schema.format === 'place') {
    return (
      <div className="rf rf-text">
        <label className="field-label">{label}</label>
        <PlaceInput value={value} placeholder="Cerca…" onChange={onChange} onPick={onPickPlace} />
      </div>
    );
  }
  // Select-search creabile: input + datalist (suggerimenti + valore personalizzato)
  if (schema.format === 'suggest') {
    return (
      <div className="rf rf-text">
        <label className="field-label">{label}</label>
        <input
          type="text"
          list={listId}
          value={value ?? ''}
          placeholder="Cerca o scrivi…"
          onChange={(e) => onChange(e.target.value)}
        />
        <datalist id={listId}>
          {(schema.examples || []).map((o) => (
            <option key={o} value={o} />
          ))}
        </datalist>
      </div>
    );
  }
  if (schema.format === 'xhtml') {
    return (
      <div className="rf full">
        <XhtmlEditor value={value} onChange={onChange} compact label={label} />
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
  /* Solo l'orario: la data la mette l'evento. Il programma di una giornata non ha
   * bisogno di ripetere il giorno dodici volte, ma nel file il valore resta un
   * datetime intero — un orario nudo non è un istante e schema.org lo rifiuta. */
  if (schema.format === 'ora') {
    const giorno = dataDi(ctx?.core?.data?.startDate);
    return (
      <div className="rf rf-date">
        <label className="field-label">{label}</label>
        <input
          type="time"
          value={oraDi(value)}
          disabled={!giorno}
          title={giorno ? undefined : 'Prima serve la data dell’evento, in Quando'}
          onChange={(e) => onChange(componi(giorno, e.target.value) || undefined)}
        />
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

const RepeatableObject = ({ data, handleChange, path, label, schema, uischema, visible }) => {
  if (visible === false) return null; // rispetta le regole SHOW/HIDE dell'uischema
  const items = Array.isArray(data) ? data : [];
  const props = schema.items?.properties || {};
  const icon = uischema?.options?.icon;
  const variant = uischema?.options?.variant || 'stack';

  const update = (arr) => handleChange(path, arr);
  const setField = (i, key, val) => {
    const next = items.map((x) => ({ ...x }));
    next[i][key] = val;
    update(next);
  };
  const setItemFields = (i, patch) => update(items.map((x, j) => (j === i ? { ...x, ...patch } : x)));
  const insertAtIndex = (at) => {
    const next = [...items];
    next.splice(at, 0, {});
    update(next);
  };
  const moveCard = (from, to) => {
    const next = [...items];
    const [moved] = next.splice(from, 1);
    next.splice(to > from ? to - 1 : to, 0, moved);
    update(next);
  };
  // Riordino con Pointer Events (mouse + touch)
  const { dragIndex, overIndex, onHandlePointerDown } = usePointerReorder(moveCard, { axis: 'y' });

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

      <div className="cards" data-reorder-root>
        {items.map((item, i) => {
          const classes = ['card'];
          if (dragIndex === i) classes.push('dragging');
          if (overIndex === i && dragIndex !== null) classes.push('insert-before');
          if (overIndex === items.length && i === items.length - 1 && dragIndex !== null)
            classes.push('insert-after');

          return (
            <Fragment key={i}>
              {i > 0 && <InsertGap onInsert={() => insertAtIndex(i)} />}
              <fieldset className={classes.join(' ')} data-reorder-index={i}>
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
                    onPointerDown={(e) => onHandlePointerDown(e, i)}
                  >
                    <span className="material-symbols-outlined">drag_indicator</span>
                  </span>
                </div>

                <div className="card-fields">
                  {Object.entries(props)
                    .filter(([, sub]) => sub.format !== 'hidden')
                    .map(([key, sub]) => (
                      <FieldControl
                        key={key}
                        name={key}
                        schema={sub}
                        item={item}
                        value={item?.[key]}
                        onChange={(v) => setField(i, key, v)}
                        onPickPlace={({ placeId, name }) => setItemFields(i, { name, googlePlaceId: placeId })}
                        onPatch={(patch) => setItemFields(i, patch)}
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
