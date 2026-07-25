import { rankWith, and, uiTypeIs, schemaMatches } from '@jsonforms/core';
import { withJsonFormsControlProps } from '@jsonforms/react';
import PlaceInput from './PlaceInput.jsx';
import { schemaTypeForPlace } from './googleMaps.js';

// Luogo con Google Places: il campo Nome è un autocomplete che, alla selezione,
// compila Nome, @type (dedotto dai tipi Google) e registra il Google Place ID.
const PlaceLocation = ({ data, handleChange, path, uischema, visible }) => {
  if (visible === false) return null;
  const d = data || {};
  const icon = uischema?.options?.icon || 'place';
  const set = (patch) => handleChange(path, { ...d, ...patch });

  return (
    <div className="control place-field">
      <label className="field-label">
        <span className="material-symbols-outlined">{icon}</span>
        Luogo
      </label>
      <div className="field-row-grid" style={{ '--cols': 3 }}>
        <div className="rf rf-text">
          <label className="field-label">Nome</label>
          <PlaceInput
            value={d.name}
            placeholder="Cerca un luogo…"
            onChange={(v) => set({ name: v })}
            onPick={({ placeId, name, types }) =>
              set({ name, type: schemaTypeForPlace(types), googlePlaceId: placeId })
            }
          />
        </div>
        <div className="rf rf-text">
          <label className="field-label">@type</label>
          <input type="text" value={d.type || 'Place'} onChange={(e) => set({ type: e.target.value })} />
        </div>
        <div className="rf rf-text">
          <label className="field-label">@id</label>
          <input type="text" value={d.id || ''} onChange={(e) => set({ id: e.target.value })} />
        </div>
      </div>
      {d.googlePlaceId ? <span className="place-gid">Google Place ID: {d.googlePlaceId}</span> : null}
    </div>
  );
};

export const placeLocationTester = rankWith(
  10,
  and(uiTypeIs('Control'), schemaMatches((s) => s?.type === 'object' && s?.format === 'place'))
);

export default withJsonFormsControlProps(PlaceLocation);
