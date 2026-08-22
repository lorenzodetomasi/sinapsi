import { useEffect, useId, useState } from 'react';
import { loadEntities, findEntityById, findEntityByName } from './entities.js';

// Campo per un'entità del sito (oggi: gli organizzatori). Due modi, entrambi
// necessari perché il redattore arriva da due strade diverse:
//
//   • dal NOME  → si sceglie dall'elenco del server (datalist: filtra scrivendo,
//     ma accetta anche un nome che sul server non c'è ancora);
//   • dall'@id  → incollandolo, il nome si compila da solo.
//
// In entrambi i casi si scrivono ENTRAMBI i campi insieme (`onPatch`): un
// organizzatore con il nome giusto e l'@id di un altro è il modo più semplice per
// rompere gli indici, e prima era la cosa più facile da fare.

/** Etichetta di riconoscimento: distingue omonimi e dice che cosa si sta scegliendo. */
const descrizione = (e) =>
  [e.kind === 'org' ? 'Organizzazione' : e['@type'], e.locality].filter(Boolean).join(' · ');

export function EntityNameInput({ label, value, onChange, onPatch }) {
  const listId = useId();
  const [lista, setLista] = useState([]);
  useEffect(() => {
    loadEntities().then(setLista);
  }, []);

  // Il datalist restituisce il testo scelto: se coincide con un'entità nota,
  // si porta dietro il suo @id; altrimenti resta un nome libero.
  const scrivi = (testo) => {
    const e = findEntityByName(lista, testo);
    if (e) onPatch({ name: e.name, id: e['@id'] });
    else onChange(testo);
  };

  return (
    <div className="rf rf-text">
      <label className="field-label">{label}</label>
      <input
        type="text"
        list={listId}
        value={value ?? ''}
        placeholder={lista.length ? 'Scegli o scrivi un nome…' : 'Nome…'}
        onChange={(e) => scrivi(e.target.value)}
      />
      <datalist id={listId}>
        {/* La chiave include l'indice: due voci con lo stesso @id sono un difetto
            dei contenuti (è successo: due cartelle per la stessa attività), ma non
            deve essere il campo a rompersi. */}
        {lista.map((e, i) => (
          <option key={e['@id'] + '#' + i} value={e.name}>
            {descrizione(e)}
          </option>
        ))}
      </datalist>
    </div>
  );
}

export function EntityIdInput({ label, value, name, onChange, onPatch }) {
  const [lista, setLista] = useState([]);
  const [nota, setNota] = useState(null); // { type: 'ok' | 'warn', msg }
  useEffect(() => {
    loadEntities().then(setLista);
  }, []);

  // Nota: il recupero dei nomi mancanti all'APERTURA non si fa qui ma in App.jsx,
  // in un passaggio solo su tutti gli organizzatori. Fatto per riga, due righe che
  // si compilano nello stesso istante partono dalla stessa istantanea e la seconda
  // cancella la prima (visto: si compilava solo l'ultima).

  // Al termine della digitazione: @id noto → compila il nome (se manca o se era
  // quello di un'altra entità); @id sconosciuto → lo dice, senza impedire nulla:
  // può essere un'entità che si sta per creare.
  const verifica = (id) => {
    const v = (id ?? '').trim();
    if (!v) return setNota(null);
    if (!lista.length) return setNota(null); // indice non disponibile: niente giudizi
    const e = findEntityById(lista, v);
    if (!e) {
      setNota({ type: 'warn', msg: 'Nessuna entità con questo @id: verifica, o creala in Luoghi e organizzazioni.' });
      return;
    }
    setNota({ type: 'ok', msg: `${e.name}${descrizione(e) ? ' — ' + descrizione(e) : ''}` });
    const attuale = (name ?? '').trim();
    if (attuale.toLowerCase() !== e.name.trim().toLowerCase()) onPatch({ id: e['@id'], name: e.name });
  };

  return (
    <div className="rf rf-text">
      <label className="field-label">{label}</label>
      <input
        type="text"
        value={value ?? ''}
        placeholder="organizations/…"
        onChange={(e) => {
          setNota(null);
          onChange(e.target.value);
        }}
        onBlur={(e) => verifica(e.target.value)}
      />
      {nota ? (
        <span className={'place-status place-' + nota.type}>
          {nota.type === 'ok' ? '✓' : '⚠️'} {nota.msg}
        </span>
      ) : null}
    </div>
  );
}
