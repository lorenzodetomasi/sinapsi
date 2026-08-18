import { rankWith, and, isStringControl, schemaMatches } from '@jsonforms/core';
import { withJsonFormsControlProps } from '@jsonforms/react';
import XhtmlEditor from './XhtmlEditor.jsx';

const XhtmlControl = ({ data, handleChange, path, label, uischema, enabled }) => {
  const icon = uischema?.options?.icon;
  return (
    <div className="control">
      <label className="field-label">
        {icon && <span className="material-symbols-outlined">{icon}</span>}
        {label}
      </label>
      <XhtmlEditor value={data} onChange={(v) => handleChange(path, v)} enabled={enabled !== false} />
    </div>
  );
};

export const xhtmlControlTester = rankWith(
  10,
  and(isStringControl, schemaMatches((s) => s && s.format === 'xhtml'))
);

export default withJsonFormsControlProps(XhtmlControl);
