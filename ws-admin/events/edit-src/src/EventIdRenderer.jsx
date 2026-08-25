import { useEffect, useRef, useState } from 'react';
import { rankWith, and, isStringControl, schemaMatches } from '@jsonforms/core';
import { withJsonFormsControlProps, useJsonForms } from '@jsonforms/react';
import { CONTENT_BASE } from './config.js';
import { proponiId, pezziMancanti, difettoId, percorsoDa } from './eventId.js';

// L'@id non si scrive più a memoria: si compone da solo mentre si riempie il form
// (tipo, nome, data, luogo, organizzatore) e si ferma appena qualcuno lo tocca —
// da quel momento è roba sua e nessun automatismo glielo cambia sotto.
//
// Sotto al campo c'è sempre la stessa riga, che risponde in quest'ordine: che cosa
// manca per comporlo, che cosa non va in quello scritto, se sul sito è già
// occupato, e infine se COINCIDE CON QUELLO CHE LA REGOLA COMPORREBBE. Quest'ultima
// è la domanda che conta davvero: «chi l'ha scritto» non interessa a nessuno —
// aprendo un evento l'@id l'ha sempre scritto qualcun altro — mentre «è conforme»
// dice se quel nome è quello che il sito si aspetta.

/** C'è già un evento con questo @id? Sotto il CMS un file mancante torna la pagina
 *  HTML con stato 200: guardare `res.ok` direbbe «esiste» per qualunque @id. */
async function esisteGia(id) {
  const rel = percorsoDa(id);
  if (!rel) return false;
  try {
    const res = await fetch(CONTENT_BASE + rel + '/index.json', { headers: { Accept: 'application/json' } });
    return res.ok && (res.headers.get('content-type') || '').includes('json');
  } catch {
    return false; // offline: non è il momento di dare giudizi
  }
}

/** L'@id con cui si è aperto l'editor: quello non è una collisione, è casa sua. */
function idAperto() {
  try {
    const q = new URLSearchParams(window.location.search).get('id') || '';
    return q.trim().replace(/^\/+|\/+$/g, '').replace(/^events\//, '');
  } catch {
    return '';
  }
}

const EventId = ({ data, handleChange, path, label, visible }) => {
  if (visible === false) return null;
  const ctx = useJsonForms();
  const d = ctx?.core?.data || {};
  const valore = data ?? '';

  const proposta = proponiId(d);
  const mancano = pezziMancanti(d);
  const [occupato, setOccupato] = useState(null); // null = non chiesto
  const scritto = useRef(null); // l'ultima proposta che abbiamo scritto noi

  // Finché il campo è vuoto o contiene esattamente quello che ci abbiamo scritto
  // noi, continua a seguire la proposta. Appena il valore è altro, è stato scritto
  // a mano e non lo si tocca più.
  const automatico = valore === '' || valore === scritto.current;
  useEffect(() => {
    if (!automatico || !proposta || proposta === valore) return;
    scritto.current = proposta;
    handleChange(path, proposta);
  }, [proposta, automatico, valore]);

  // La verifica sul sito costa una richiesta: si aspetta una pausa.
  useEffect(() => {
    const v = valore.trim();
    if (!v) return setOccupato(null);
    let vivo = true;
    const t = setTimeout(async () => {
      const c = await esisteGia(v);
      if (vivo) setOccupato(c && idAperto() !== v.replace(/^events\//, ''));
    }, 500);
    return () => {
      vivo = false;
      clearTimeout(t);
    };
  }, [valore]);

  const difetto = valore ? difettoId(valore, d.primaryType) : '';
  const rel = percorsoDa(valore);
  // Conforme = identico a quello che la regola comporrebbe. Il confronto è stretto,
  // sul testo intero: un @id senza la cartella (`clubdellibro-ostia-reading_party`
  // invece di `events/clubdellibro-ostia-reading_party`) NON è conforme, ed è
  // giusto dirlo — è un riferimento che non si risolve, e il pulsante qui accanto
  // lo raddrizza in un clic.
  const conforme = !!proposta && valore === proposta;

  return (
    <div className="rf rf-text event-id">
      <label className="field-label">{label || '@id'}</label>
      <input
        type="text"
        value={valore}
        placeholder={proposta || 'si compone da solo…'}
        onChange={(e) => handleChange(path, e.target.value)}
      />
      <span className="event-id-note">
        {!valore && mancano.length ? (
          <span className="place-status place-warn">
            ⚠️ Per comporlo manca{mancano.length > 1 ? 'no' : ''} {mancano.join(', ')}.
          </span>
        ) : difetto ? (
          <span className="place-status place-warn">⚠️ @id: {difetto}.</span>
        ) : occupato ? (
          <span className="place-status place-warn">
            ⚠️ Esiste già un evento con questo @id: salvando lo aggiornerai.
          </span>
        ) : valore ? (
          <span className="place-status place-ok">
            ✓ {conforme
              ? 'Conforme alla regola'
              : proposta
                ? 'Diverso da quello che la regola comporrebbe'
                : 'Scritto a mano'}{' '}
            · finirà in {rel}/index.json
          </span>
        ) : null}
      </span>
      {proposta && proposta !== valore ? (
        <button
          type="button"
          className="btn-ghost event-id-riproponi"
          onClick={() => {
            scritto.current = proposta;
            handleChange(path, proposta);
          }}
          title="Usa l’@id composto dal form"
        >
          <span className="material-symbols-outlined">auto_fix_high</span> {proposta}
        </button>
      ) : null}
    </div>
  );
};

export const eventIdTester = rankWith(
  10,
  and(isStringControl, schemaMatches((s) => s && s.format === 'event-id'))
);

export default withJsonFormsControlProps(EventId);
