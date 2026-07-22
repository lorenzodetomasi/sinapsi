import { rankWith, and, isStringControl, schemaMatches } from '@jsonforms/core';
import { withJsonFormsControlProps } from '@jsonforms/react';

// Select con etichetta (title) distinta dal valore salvato (const).
// Gestisce gli schema con `oneOf: [{ const, title }, ...]`.
const LabeledEnum = ({ data, handleChange, path, label, id, schema, enabled, required }) => {
  const options = schema.oneOf ?? [];
  return (
    <div className="control labeled-enum">
      <label htmlFor={id}>
        {label}
        {required ? ' *' : ''}
      </label>
      <select
        id={id}
        value={data ?? ''}
        disabled={enabled === false}
        onChange={(e) => handleChange(path, e.target.value || undefined)}
      >
        <option value="">—</option>
        {options.map((o) => (
          <option key={o.const} value={o.const}>
            {o.title ?? o.const}
          </option>
        ))}
      </select>
    </div>
  );
};

export const labeledEnumTester = rankWith(
  10,
  and(
    isStringControl,
    schemaMatches((s) => Array.isArray(s?.oneOf) && s.oneOf.every((o) => o && o.const !== undefined))
  )
);

export default withJsonFormsControlProps(LabeledEnum);
