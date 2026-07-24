import { rankWith, and, isStringControl } from '@jsonforms/core';
import { withJsonFormsControlProps } from '@jsonforms/react';

// Campo di testo semplice con etichetta + icona (i controlli vanilla ignorano
// options.icon; questo lo mostra come negli altri campi custom, es. "Sito web").
const IconText = ({ data, handleChange, path, label, id, uischema, enabled, visible }) => {
  if (visible === false) return null;
  const icon = uischema?.options?.icon;
  return (
    <div className="control icon-text">
      <label className="field-label" htmlFor={id}>
        {icon && <span className="material-symbols-outlined">{icon}</span>}
        {label}
      </label>
      <input
        id={id}
        type="text"
        value={data ?? ''}
        disabled={enabled === false}
        placeholder={uischema?.options?.placeholder || ''}
        onChange={(e) => handleChange(path, e.target.value || undefined)}
      />
    </div>
  );
};

// Solo per stringhe semplici che dichiarano un'icona (niente format/oneOf/altri
// meccanismi, che hanno già i loro renderer).
export const iconTextTester = rankWith(
  4,
  and(isStringControl, (uischema, schema) => {
    const o = uischema?.options || {};
    return !!o.icon && !o.searchable && !o.select && !schema?.format && !schema?.oneOf;
  })
);

export default withJsonFormsControlProps(IconText);
