import { useEffect, useState } from 'react';
import { loadEntities, findEntityById } from './entities.js';
import EntityPicker, { descrizioneEntita as descrizione } from './EntityPicker.jsx';
import { detectPrimaryType, regionFromComponents, slugify, buildPlaceId, lookupPlaceId } from './placeId.js';

// Campo per un'entità del sito (oggi: gli organizzatori). Tre strade, perché il
// redattore ci arriva da tre situazioni diverse:
//
//   • dal NOME  → si sceglie dall'elenco del server (filtra scrivendo, ma accetta
//     anche un nome che sul server non c'è ancora);
//   • da GOOGLE → se sul sito non c'è, si cerca fra i luoghi di Google: la
//     verifica per Google Place ID dice se in realtà c'è già (con un altro nome);
//   • dall'@id  → incollandolo, il nome si compila da solo.
//
// In tutti i casi si scrivono ENTRAMBI i campi insieme (`onPatch`): un
// organizzatore con il nome giusto e l'@id di un altro è il modo più semplice per
// rompere gli indici, e prima era la cosa più facile da fare.

export function EntityNameInput({ label, value, onChange, onPatch }) {
  const [nota, setNota] = useState(null); // { type: 'ok' | 'warn', msg }

  // Scelto un suggerimento di Google, la prima domanda è: quel soggetto è già sul
  // sito? Si chiede per Google Place ID, che è l'identità del luogo — l'@id
  // costruito da nome e CAP cambia se il nome è scritto diversamente. Se c'è, si
  // prendono @id e nome DAL SITO: il nome buono è quello redazionale.
  const daGoogle = async ({ placeId, name, types, addressComponents }) => {
    const noto = await lookupPlaceId(placeId);
    if (noto) {
      onPatch({ name: noto.name, id: noto.id, googlePlaceId: placeId });
      setNota({ type: 'ok', msg: `Già sul sito: collegato a ${noto.id}.` });
      return;
    }
    // Non c'è: si propone l'@id che avrà quando verrà creato (stesso algoritmo di
    // places/edit, così i due combaciano) e lo si dice a chiare lettere — finché
    // quel file non esiste, il riferimento punta nel vuoto.
    const region = regionFromComponents(addressComponents);
    const slug = slugify(name);
    const id = region && slug ? buildPlaceId(detectPrimaryType(types), region, slug) : '';
    onPatch({ name, id, googlePlaceId: placeId });
    setNota({
      type: 'warn',
      msg: id
        ? `Non è ancora sul sito: creane la scheda in Luoghi e organizzazioni con l'@id ${id}.`
        : 'Non è ancora sul sito e il CAP non è noto: scrivi l’@id a mano, poi crea la scheda.',
    });
  };

  return (
    <div className="rf rf-text">
      <label className="field-label">{label}</label>
      <EntityPicker
        value={value}
        ambito="organizer"
        onChange={(t) => {
          setNota(null);
          onChange(t);
        }}
        onPickSite={(e) => {
          onPatch({ name: e.name, id: e['@id'] });
          setNota({ type: 'ok', msg: `Dal sito: ${e['@id']}${descrizione(e) ? ' — ' + descrizione(e) : ''}` });
        }}
        onPickGoogle={daGoogle}
      />
      {nota ? (
        <span className={'place-status place-' + nota.type}>
          {nota.type === 'ok' ? '✓' : '⚠️'} {nota.msg}
        </span>
      ) : null}
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
