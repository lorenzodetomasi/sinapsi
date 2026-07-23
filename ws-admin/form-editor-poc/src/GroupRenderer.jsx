import { rankWith, uiTypeIs } from '@jsonforms/core';
import { withJsonFormsLayoutProps, JsonFormsDispatch } from '@jsonforms/react';

// Sezione senza riquadro: icona nel gutter a sinistra, etichetta di testo alla
// sua destra e campi allineati nella colonna del contenuto.
// L'icona si dichiara nell'uischema: options: { icon: 'place' }.
const GroupLayout = ({ uischema, schema, path, renderers, cells, enabled, visible }) => {
  if (visible === false) return null;

  const icon = uischema?.options?.icon;
  const elements = uischema?.elements ?? [];

  return (
    <section className="group">
      <div className="group-icon" aria-hidden="true">
        {icon && <span className="material-symbols-outlined">{icon}</span>}
      </div>
      <div className="group-content">
        {uischema.label && <h3 className="group-title">{uischema.label}</h3>}
        <div className="group-body">
          {elements.map((child, i) => (
            <JsonFormsDispatch
              key={i}
              uischema={child}
              schema={schema}
              path={path}
              renderers={renderers}
              cells={cells}
              enabled={enabled}
            />
          ))}
        </div>
      </div>
    </section>
  );
};

export const groupTester = rankWith(10, uiTypeIs('Group'));

export default withJsonFormsLayoutProps(GroupLayout);
