import { useEffect, useState } from 'react';
import { rankWith, and, uiTypeIs, optionIs } from '@jsonforms/core';
import { withJsonFormsControlProps } from '@jsonforms/react';
import EntityPicker, { descrizioneEntita as descrizione } from './EntityPicker.jsx';
import { loadEntities, findEntityById } from './entities.js';

/* Un campo che tiene l'@id di un'altra scheda — «Sta dentro», «Dove si trova».
 *
 * Era una casella di testo: per compilarla bisognava sapere a memoria come si
 * scrive l'indirizzo di un'altra scheda, e sbagliarlo di una lettera non dava
 * nessun errore — dava un riferimento che punta nel vuoto. Chi compila conosce
 * il NOME («il Teatro del Lido»), non `places/IT00122/teatrodellido`.
 *
 * Quindi si cerca per nome, come nel «Dove» degli eventi, e l'@id lo scrive
 * l'editor. Resta scrivibile a mano — a volte l'indirizzo si ha e basta
 * incollarlo — e sotto si legge sempre a chi si sta puntando davvero: il nome
 * risolto dall'indice, oppure l'avviso che quell'indirizzo non esiste.
 */
function RiferimentoRenderer({ data, handleChange, path, label, uischema, schema, errors, visible }) {
  const [lista, setLista] = useState([]);
  const [testo, setTesto] = useState('');
  const ambito = uischema?.options?.ambito || 'organizer';

  useEffect(() => { loadEntities().then(setLista); }, []);

  /* Il nome nella casella di ricerca segue l'@id, non il contrario: se l'@id
     arriva dal documento o da un incolla, la ricerca deve già mostrare di chi
     si tratta. */
  const scelto = data ? findEntityById(lista, data) : null;
  useEffect(() => {
    setTesto(scelto ? scelto.name : '');
  }, [data, scelto?.name]);

  if (visible === false) return null;

  return (
    <div className="rf rf-text">
      <label className="field-label">{label || schema?.title}</label>
      <EntityPicker
        value={testo}
        ambito={ambito}
        placeholder="Cerca per nome…"
        onChange={setTesto}
        onPickSite={(e) => handleChange(path, e['@id'])}
        /* Da Google non si può scegliere: qui dentro ci va una scheda che sul
           sito esiste, se no il riferimento nasce rotto. Chi cerca un luogo che
           non c'è ancora lo trova comunque nell'elenco, e sa che deve crearlo. */
      />
      <input
        type="text"
        value={data || ''}
        placeholder="places/IT00122/…"
        onChange={(e) => handleChange(path, e.target.value || undefined)}
      />
      {data && lista.length ? (
        <span className={'place-status place-' + (scelto ? 'ok' : 'warn')}>
          {scelto
            ? `✓ ${scelto.name}${descrizione(scelto) ? ' — ' + descrizione(scelto) : ''}`
            : '⚠️ Nessuna scheda con questo indirizzo: il riferimento punta nel vuoto.'}
        </span>
      ) : null}
      {errors ? <span className="place-status place-warn">{errors}</span> : null}
    </div>
  );
}

export const riferimentoTester = rankWith(16, and(uiTypeIs('Control'), optionIs('riferimento', true)));
export default withJsonFormsControlProps(RiferimentoRenderer);
