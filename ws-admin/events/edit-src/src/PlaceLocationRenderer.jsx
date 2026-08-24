import { useState } from 'react';
import { rankWith, and, uiTypeIs, schemaMatches } from '@jsonforms/core';
import { withJsonFormsControlProps } from '@jsonforms/react';
import EntityPicker, { descrizioneEntita } from './EntityPicker.jsx';
import {
  detectPrimaryType,
  regionFromComponents,
  slugify,
  buildPlaceId,
  swapIdPrefix,
  lookupId,
  lookupPlaceId,
  lightPlaceDiff,
} from './placeId.js';

// Luogo: si sceglie prima dall'elenco del sito (luoghi e attività), e solo se lì
// non c'è si guarda fra i suggerimenti di Google. Scegliendo un risultato di
// Google si compila Nome, @type primario (LocalBusiness|Place), l'@id
// (<cartella>/<IT+CAP>/<slug>) e il Google Place ID. Se l'@id esiste già,
// confronta i Google ID: stesso luogo → si collega (e segnala eventuali
// aggiornamenti Google); Google ID diverso → collisione (cambia id).
const PlaceLocation = ({ data, handleChange, path, uischema, visible }) => {
  if (visible === false) return null;
  const d = data || {};
  const icon = uischema?.options?.icon || 'place';
  const [status, setStatus] = useState(null); // { type: 'ok' | 'warn', msg }
  const set = (patch) => handleChange(path, { ...d, ...patch });

  // picked = { placeId, name, addressComponents } quando arriva da Google; null se
  // è una modifica manuale dell'@id (senza un Google ID con cui confrontare).
  const evaluate = async (id, regionMissing, picked) => {
    if (regionMissing) {
      setStatus({ type: 'warn', msg: 'CAP/paese non rilevati: la regione nell’@id è vuota, compilala a mano.' });
      return;
    }
    const info = await lookupId(id);
    if (!info || !info.exists) {
      setStatus(null);
      return;
    }
    const stored = info.google_place_id || null;
    if (info.parse_error || !stored) {
      setStatus({ type: 'warn', msg: `Esiste già un @id (${id}) senza Google ID salvato: verifica se è lo stesso luogo.` });
    } else if (picked && stored === picked.placeId) {
      const changes = lightPlaceDiff(picked, info.stored);
      const extra = changes.length ? ` Aggiornamenti su Google: ${changes.join(', ')} — aggiornali in places/edit.` : '';
      setStatus({ type: 'ok', msg: `Luogo già presente: collegato al suo @id.${extra}` });
    } else if (picked) {
      setStatus({ type: 'warn', msg: `Esiste già un @id diverso con questo slug (Google ID diverso): cambia l’id.` });
    } else {
      setStatus({ type: 'warn', msg: `Esiste già un elemento con questo @id (${id}): verifica se è lo stesso luogo.` });
    }
  };

  // Scelto un suggerimento di Google, la PRIMA domanda è: quel luogo è già sul
  // sito? Si chiede per Google Place ID, che è l'identità del luogo — non per l'@id
  // costruito da nome e CAP, che cambia se il nome è scritto diversamente e
  // mancherebbe la corrispondenza. Se c'è, si prendono @id e nome DAL SITO: il nome
  // buono è quello redazionale, non quello dell'insegna su Google.
  const onPick = async ({ placeId, name, types, addressComponents }) => {
    const noto = await lookupPlaceId(placeId);
    if (noto) {
      set({ name: noto.name, type: noto.type || detectPrimaryType(types), id: noto.id, googlePlaceId: placeId });
      setStatus({ type: 'ok', msg: `Luogo già sul sito: collegato a ${noto.id}.` });
      return;
    }
    // Luogo nuovo: si propone un @id costruito da tipo, CAP e nome, e si controlla
    // che quello slug non sia già occupato da un altro luogo.
    const type = detectPrimaryType(types);
    const region = regionFromComponents(addressComponents);
    const slug = slugify(name);
    const id = region && slug ? buildPlaceId(type, region, slug) : d.id || '';
    set({ name, type, id, googlePlaceId: placeId });
    evaluate(id, !region, { placeId, name, addressComponents });
  };

  // Scelto dall'elenco del sito: @id e nome sono già quelli buoni, non c'è niente
  // da verificare. Il Google Place ID si azzera: quello dell'evento precedente
  // apparterrebbe a un altro luogo, e quello vero sta nella scheda del luogo.
  const onSito = (e) => {
    set({ name: e.name, type: e['@type'] || 'Place', id: e['@id'], googlePlaceId: '' });
    setStatus({ type: 'ok', msg: `Dal sito: ${e['@id']}${descrizioneEntita(e) ? ' — ' + descrizioneEntita(e) : ''}` });
  };

  const onType = (type) => {
    const id = swapIdPrefix(d.id, type);
    set({ type, id });
    evaluate(id, false, d.googlePlaceId ? { placeId: d.googlePlaceId, name: d.name, addressComponents: [] } : null);
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
          <EntityPicker
            value={d.name}
            ambito="venue"
            placeholder="Scegli o cerca un luogo…"
            onChange={(v) => { setStatus(null); set({ name: v }); }}
            onPickSite={onSito}
            onPickGoogle={onPick}
          />
        </div>
        <div className="rf rf-text">
          <label className="field-label">@type</label>
          {/* Un luogo preso dal sito può avere un tipo più preciso (Beach,
              Restaurant, Museum…): si mostra com'è, perché ricondurlo a
              «LocalBusiness» diceva il falso — una spiaggia è un Place — e al
              primo tocco del menu quel tipo preciso sarebbe andato perso. */}
          <select value={d.type || 'Place'} onChange={(e) => onType(e.target.value)}>
            <option value="Place">Place</option>
            <option value="LocalBusiness">LocalBusiness</option>
            {d.type && d.type !== 'Place' && d.type !== 'LocalBusiness' && (
              <option value={d.type}>{d.type}</option>
            )}
          </select>
        </div>
        <div className="rf rf-text">
          <label className="field-label">@id</label>
          <input
            type="text"
            value={d.id || ''}
            onChange={(e) => set({ id: e.target.value })}
            onBlur={(e) => evaluate(e.target.value, false, null)}
          />
        </div>
      </div>
      {d.googlePlaceId ? <span className="place-gid">Google Place ID: {d.googlePlaceId}</span> : null}
      {status ? <span className={'place-status place-' + status.type}>{status.type === 'ok' ? '✓' : '⚠️'} {status.msg}</span> : null}
    </div>
  );
};

export const placeLocationTester = rankWith(
  10,
  and(uiTypeIs('Control'), schemaMatches((s) => s?.type === 'object' && s?.format === 'place'))
);

export default withJsonFormsControlProps(PlaceLocation);
