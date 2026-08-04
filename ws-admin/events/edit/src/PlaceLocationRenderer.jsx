import { useState } from 'react';
import { rankWith, and, uiTypeIs, schemaMatches } from '@jsonforms/core';
import { withJsonFormsControlProps } from '@jsonforms/react';
import PlaceInput from './PlaceInput.jsx';
import { detectPrimaryType, regionFromComponents, slugify, buildPlaceId, swapIdPrefix, checkIdExists } from './placeId.js';

// Luogo con Google Places: scegliendo un risultato compila Nome, @type primario
// (LocalBusiness|Place), l'@id (<cartella>/<IT+CAP>/<slug>) e il Google Place ID.
// Cambiando @type l'@id cambia prefisso; se l'@id esiste già mostra un avviso.
const PlaceLocation = ({ data, handleChange, path, uischema, visible }) => {
  if (visible === false) return null;
  const d = data || {};
  const icon = uischema?.options?.icon || 'place';
  const [warn, setWarn] = useState('');
  const set = (patch) => handleChange(path, { ...d, ...patch });

  const verify = async (id, regionMissing) => {
    if (regionMissing) {
      setWarn('CAP/paese non rilevati: la regione nell’@id è vuota, compilala a mano.');
      return;
    }
    const exists = await checkIdExists(id);
    setWarn(exists ? `Esiste già un elemento con questo @id (${id}): cambia l’id.` : '');
  };

  const onPick = ({ placeId, name, types, addressComponents }) => {
    const type = detectPrimaryType(types);
    const region = regionFromComponents(addressComponents);
    const slug = slugify(name);
    const id = region && slug ? buildPlaceId(type, region, slug) : d.id || '';
    set({ name, type, id, googlePlaceId: placeId });
    verify(id, !region);
  };

  const onType = (type) => {
    const id = swapIdPrefix(d.id, type);
    set({ type, id });
    verify(id, false);
  };

  return (
    <div className="control place-field">
      <label className="field-label">
        <span className="material-symbols-outlined">{icon}</span>
        Luogo
      </label>
      <div className="field-row-grid" style={{ '--cols': 3 }}>
        <div className="rf rf-text">
          <label className="field-label">Nome</label>
          <PlaceInput value={d.name} placeholder="Cerca un luogo…" onChange={(v) => set({ name: v })} onPick={onPick} />
        </div>
        <div className="rf rf-text">
          <label className="field-label">@type</label>
          <select value={d.type === 'LocalBusiness' ? 'LocalBusiness' : 'Place'} onChange={(e) => onType(e.target.value)}>
            <option value="Place">Place</option>
            <option value="LocalBusiness">LocalBusiness</option>
          </select>
        </div>
        <div className="rf rf-text">
          <label className="field-label">@id</label>
          <input
            type="text"
            value={d.id || ''}
            onChange={(e) => set({ id: e.target.value })}
            onBlur={(e) => verify(e.target.value, false)}
          />
        </div>
      </div>
      {d.googlePlaceId ? <span className="place-gid">Google Place ID: {d.googlePlaceId}</span> : null}
      {warn ? <span className="place-warn">⚠️ {warn}</span> : null}
    </div>
  );
};

export const placeLocationTester = rankWith(
  10,
  and(uiTypeIs('Control'), schemaMatches((s) => s?.type === 'object' && s?.format === 'place'))
);

export default withJsonFormsControlProps(PlaceLocation);
