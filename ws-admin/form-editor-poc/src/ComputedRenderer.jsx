import { rankWith, and, uiTypeIs, optionIs } from '@jsonforms/core';
import { withJsonFormsControlProps } from '@jsonforms/react';

// Campo di sola lettura calcolato automaticamente (es. Posti totali, Posti
// rimasti): mostra il valore come "well" spento, non modificabile dall'utente.
const Computed = ({ data, label, uischema, visible }) => {
  if (visible === false) return null;
  const icon = uischema?.options?.icon;
  return (
    <div className="control computed-control">
      <label className="field-label">
        {icon && <span className="material-symbols-outlined">{icon}</span>}
        {label}
      </label>
      <div className="computed-value" aria-readonly="true" title="Calcolato automaticamente">
        <span>{data ?? 0}</span>
        <span className="material-symbols-outlined">calculate</span>
      </div>
    </div>
  );
};

export const computedTester = rankWith(20, and(uiTypeIs('Control'), optionIs('computed', true)));

export default withJsonFormsControlProps(Computed);
