import { useEffect, useState } from 'react';
import { rankWith, uiTypeIs } from '@jsonforms/core';
import { withJsonFormsLayoutProps, useJsonForms } from '@jsonforms/react';
import { EVENTS_INDEX_URL } from './config.js';
import { avvisiQuando } from './quando.js';

// Quello che non torna in «Quando», detto sul posto invece che al salvataggio.
// Non blocca niente: sono avvisi, e chi redige ne sa più del programma — un
// programma che sfora di dieci minuti può essere giusto così.
//
// Le date delle occorrenze si leggono dall'indice degli eventi: una richiesta
// sola invece di una per occorrenza. Se l'indice è vecchio l'avviso lo dice
// («rigenera l'indice»), invece di far finta che l'occorrenza non esista.

/* L'indice è in DUE file: i prossimi e l'archivio. Le occorrenze di una serie che
 * dura da un po' stanno quasi tutte nell'archivio, quindi leggerne uno solo
 * significherebbe dichiarare «non risulta nell'indice» proprio le date passate —
 * un allarme falso su ogni serie viva. */
const URL_ARCHIVIO = EVENTS_INDEX_URL.replace(/events\.json$/, 'events.archive.json');

async function leggi(url) {
  try {
    const r = await fetch(url, { headers: { Accept: 'application/json' } });
    if (!r.ok || !(r.headers.get('content-type') || '').includes('json')) return [];
    const idx = await r.json();
    return Array.isArray(idx) ? idx : Array.isArray(idx?.events) ? idx.events : [];
  } catch {
    return [];
  }
}

let promessaIndice = null;
function caricaIndice() {
  if (!promessaIndice) {
    promessaIndice = Promise.all([leggi(EVENTS_INDEX_URL), leggi(URL_ARCHIVIO)]).then(([prossimi, archivio]) => {
      const mappa = {};
      for (const v of [...prossimi, ...archivio]) {
        const id = v['@id'] || v.path || '';
        if (id) mappa[id] = v.startDate || '';
      }
      return mappa;
    });
  }
  return promessaIndice;
}

const Coerenza = ({ visible }) => {
  if (visible === false) return null;
  const ctx = useJsonForms();
  const d = ctx?.core?.data || {};
  const [indice, setIndice] = useState(null);
  const serie = d.primaryType === 'EventSeries';
  const quante = (d.occurrences ?? []).length;

  useEffect(() => {
    if (!serie || !quante) return;
    let vivo = true;
    caricaIndice().then((m) => vivo && setIndice(m));
    return () => {
      vivo = false;
    };
  }, [serie, quante]);

  const avvisi = avvisiQuando(d, serie ? indice : null);
  if (!avvisi.length) return null;

  return (
    <div className="control coerenza">
      <ul>
        {avvisi.map((a, i) => (
          <li key={i} className={'coerenza-' + a.tipo}>
            <span className="material-symbols-outlined">
              {a.tipo === 'errore' ? 'error' : a.tipo === 'avviso' ? 'warning' : 'info'}
            </span>
            {a.testo}
          </li>
        ))}
      </ul>
    </div>
  );
};

export const coerenzaTester = rankWith(10, uiTypeIs('Coerenza'));

export default withJsonFormsLayoutProps(Coerenza);
