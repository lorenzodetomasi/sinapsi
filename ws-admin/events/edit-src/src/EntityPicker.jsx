import { Fragment, useEffect, useMemo, useRef, useState } from 'react';
import { loadEntities } from './entities.js';
import { cercaLuoghi, dettaglioLuogo } from './placesSearch.js';

// Un campo solo per le due domande che il redattore si fa nello stesso momento:
// «c'è già sul sito?» e, se no, «esiste su Google?». Prima erano due campi con
// due comportamenti diversi — gli organizzatori si sceglievano solo dall'elenco
// del sito, il luogo si cercava solo su Google — e nessuno dei due faceva l'altra
// metà del lavoro: l'organizzatore nuovo andava creato altrove prima di poterlo
// nominare, e il luogo già presente sul sito veniva riproposto da Google come se
// non ci fosse.
//
// L'ordine dell'elenco è l'ordine delle domande: prima il sito (è lì che vivono
// gli @id), poi Google, e solo quando il sito non ha già la risposta. Un
// suggerimento di Google non è un'adozione: chi lo sceglie riceve il luogo grezzo
// e decide — la verifica per Google Place ID la fa il chiamante.
//
// Resta sempre possibile scrivere un nome che non è in nessuno dei due elenchi:
// un evento può avere un organizzatore che sul sito non c'è e non ci sarà.

const AMBITI = {
  // Dove: solo luoghi e attività (cartella places/).
  venue: (e) => e.kind === 'business',
  // Organizzatori: anche le organizations.
  organizer: () => true,
};

/** Etichetta di riconoscimento: distingue omonimi e dice che cosa si sta scegliendo. */
export const descrizioneEntita = (e) =>
  [e.kind === 'org' ? 'Organizzazione' : e['@type'], e.locality].filter(Boolean).join(' · ');

function filtra(lista, testo, ambito) {
  const ammesso = AMBITI[ambito] || AMBITI.organizer;
  // Le liste (ItemList: Lungomare, BookCrossing) stanno sotto places/ ma non sono
  // né un posto dove si va né qualcuno che organizza.
  const base = lista.filter((e) => ammesso(e) && e['@type'] !== 'ItemList');
  const q = (testo ?? '').trim().toLowerCase();
  if (!q) return base.slice(0, 8);
  // Chi comincia per… prima di chi contiene: cercando "bau" si vuole BauBeach in
  // cima, non un locale che ha "bau" a metà nome.
  const inizia = [];
  const contiene = [];
  for (const e of base) {
    const n = (e.name || '').toLowerCase();
    if (n.startsWith(q)) inizia.push(e);
    else if (n.includes(q)) contiene.push(e);
  }
  return [...inizia, ...contiene].slice(0, 8);
}

export default function EntityPicker({
  value,
  ambito = 'organizer',
  placeholder,
  onChange,
  onPickSite,
  onPickGoogle,
}) {
  const [lista, setLista] = useState([]);
  const [google, setGoogle] = useState([]);
  const [aperto, setAperto] = useState(false);
  const [attesa, setAttesa] = useState(false);
  const [sel, setSel] = useState(-1);
  const chiusura = useRef(null);

  useEffect(() => {
    loadEntities().then(setLista);
    return () => clearTimeout(chiusura.current);
  }, []);

  const testo = value ?? '';
  const daSito = useMemo(() => filtra(lista, testo, ambito), [lista, testo, ambito]);
  const esatto = useMemo(
    () => daSito.some((e) => (e.name || '').trim().toLowerCase() === testo.trim().toLowerCase()),
    [daSito, testo]
  );

  // Google entra solo dopo il sito: tre caratteri, nessuna corrispondenza esatta
  // sul sito, e una pausa nella digitazione — ogni battuta sarebbe una chiamata.
  useEffect(() => {
    if (!aperto || testo.trim().length < 3 || esatto) {
      setGoogle([]);
      return;
    }
    let vivo = true;
    const t = setTimeout(async () => {
      setAttesa(true);
      const r = await cercaLuoghi(testo);
      if (!vivo) return;
      setGoogle(r.slice(0, 5));
      setAttesa(false);
    }, 350);
    return () => {
      vivo = false;
      clearTimeout(t);
    };
  }, [testo, aperto, esatto]);

  const voci = [
    ...daSito.map((e) => ({ tipo: 'sito', chiave: 's:' + e['@id'], e })),
    ...google.map((p) => ({ tipo: 'google', chiave: 'g:' + p.place_id, p })),
  ];

  const scegli = async (v) => {
    setAperto(false);
    setSel(-1);
    if (v.tipo === 'sito') {
      onPickSite?.(v.e);
      return;
    }
    const d = await dettaglioLuogo(v.p.place_id);
    // Se il dettaglio non arriva resta almeno il nome scritto: meglio di un campo
    // che si svuota da solo.
    if (d) onPickGoogle?.(d);
    else onChange?.(v.p.structured_formatting?.main_text || v.p.description || testo);
  };

  const tasti = (e) => {
    if (!aperto && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) {
      setAperto(true);
      return;
    }
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      setSel((i) => (voci.length ? (i + 1) % voci.length : -1));
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      setSel((i) => (voci.length ? (i <= 0 ? voci.length - 1 : i - 1) : -1));
    } else if (e.key === 'Enter' && sel >= 0 && voci[sel]) {
      e.preventDefault(); // dentro un form l'Invio salverebbe
      scegli(voci[sel]);
    } else if (e.key === 'Escape') {
      setAperto(false);
      setSel(-1);
    }
  };

  const mostra = aperto && (voci.length > 0 || attesa);

  return (
    <div className="ent-picker">
      <input
        type="text"
        value={testo}
        placeholder={placeholder || (lista.length ? 'Scegli o scrivi un nome…' : 'Nome…')}
        autoComplete="off"
        role="combobox"
        aria-expanded={mostra}
        onChange={(e) => {
          onChange?.(e.target.value);
          setAperto(true);
          setSel(-1);
        }}
        onFocus={() => setAperto(true)}
        onBlur={() => {
          // Ritardo: il clic su una voce arriva dopo il blur. Le voci fermano già
          // il mousedown, questa è la rete di sicurezza (tasto TAB, tocco lungo).
          clearTimeout(chiusura.current);
          chiusura.current = setTimeout(() => setAperto(false), 150);
        }}
        onKeyDown={tasti}
      />

      {mostra && (
        <ul className="ent-menu" role="listbox">
          {daSito.length > 0 && <li className="ent-group">Sul sito</li>}
          {voci.map((v, i) => (
            <Fragment key={v.chiave}>
              {i === daSito.length && <li className="ent-group">Su Google</li>}
              <li
                role="option"
                aria-selected={i === sel}
                className={'ent-item' + (i === sel ? ' sel' : '')}
                onMouseDown={(e) => e.preventDefault()}
                onMouseEnter={() => setSel(i)}
                onClick={() => scegli(v)}
              >
                <span className="ent-name">
                  {v.tipo === 'sito'
                    ? v.e.name
                    : v.p.structured_formatting?.main_text || v.p.description}
                </span>
                <span className="ent-note">
                  {v.tipo === 'sito'
                    ? descrizioneEntita(v.e)
                    : v.p.structured_formatting?.secondary_text || ''}
                </span>
              </li>
            </Fragment>
          ))}
          {attesa && <li className="ent-group">Cerco su Google…</li>}
        </ul>
      )}
    </div>
  );
}
