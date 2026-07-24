import { rankWith, and, uiTypeIs } from '@jsonforms/core';
import { withJsonFormsLayoutProps, JsonFormsDispatch } from '@jsonforms/react';

// HorizontalLayout "arricchito" via options:
//  - separator: 'x'  → due campi affiancati con un separatore (es. "Dal – al")
//  - cols: N         → i campi disposti in una griglia di N colonne (le righe
//                      restano allineate anche se l'ultima è incompleta)
const FieldRow = ({ uischema, schema, path, renderers, cells, enabled, visible }) => {
  if (visible === false) return null;
  const els = uischema.elements || [];
  const { separator, cols } = uischema.options || {};

  const dispatch = (el, i) => (
    <JsonFormsDispatch
      key={i}
      uischema={el}
      schema={schema}
      path={path}
      renderers={renderers}
      cells={cells}
      enabled={enabled}
    />
  );

  if (separator) {
    return (
      <div className="field-row field-row-sep">
        {dispatch(els[0], 0)}
        {/* segnaposto etichetta (vuoto) così il separatore si allinea all'input */}
        <div className="control pair-sep-control" aria-hidden="true">
          <label className="field-label">{' '}</label>
          <div className="pair-sep">{separator}</div>
        </div>
        {dispatch(els[1], 1)}
      </div>
    );
  }

  return (
    <div className="field-row field-row-grid" style={{ '--cols': cols || els.length }}>
      {els.map(dispatch)}
    </div>
  );
};

export const fieldRowTester = rankWith(
  5,
  and(uiTypeIs('HorizontalLayout'), (uischema) => {
    const o = uischema?.options;
    return !!(o && (o.separator || o.cols));
  })
);

export default withJsonFormsLayoutProps(FieldRow);
