import { useEffect, useState } from 'react';
import { rankWith, and, uiTypeIs, optionIs } from '@jsonforms/core';
import { withJsonFormsControlProps } from '@jsonforms/react';

/* Fascia d'età: una o più fasce CONTIGUE, oppure due numeri.
 *
 * `typicalAgeRange` di schema.org è un testo solo — «6-13», «11-» (da undici in su)
 * — e va benissimo così. Finché le fasce non si sovrappongono e non lasciano buchi,
 * e finché quelle spuntate sono attaccate, l'unione È la scelta: «6-13» non lo può
 * dare nessun altro insieme di fasce. Quindi non serve un secondo campo che ricordi
 * le caselle: si rileggono dal valore. (Verificato su tutte e 36 le scelte possibili
 * — vedi la prova in ws-listrule.php.)
 *
 * Per questo il click ATTACCA invece di spuntare: chi clicca una fascia lontana
 * tira la selezione fino a lì. Un buco in mezzo sarebbe una scelta che il campo non
 * saprebbe scrivere, e la scelta impossibile è meglio non farla fare.
 *
 * Le fasce le decide chi scrive il contenuto (schema.js), non questo file: le stesse
 * che si leggono nelle categorie, con la scuola accanto — «11-13» da solo non dice
 * niente, «le medie» sì.
 */
const MAX = 120;

/** «6-13» → [6,13]; «11-» e «11+» → [11,120]; il resto → null. Le stesse regole di
 *  ws_listrule_eta() in PHP: se le due strade non leggessero uguale, la fascia
 *  scelta qui e la lista che la raccoglie direbbero due cose diverse. */
const leggi = (s) => {
  if (typeof s !== 'string') return null;
  const t = s.trim();
  if (/^(all\s*ages|tutte\s*le\s*et)/i.test(t)) return [0, MAX];
  const m = /^(\d{1,3})\s*[-+]\s*(\d{1,3})?$/.exec(t);
  if (!m) return null;
  const primo = Number(m[1]);
  const ultimo = m[2] === undefined || m[2] === '' ? MAX : Number(m[2]);
  return primo <= ultimo ? [primo, ultimo] : null;
};

/** [6,13] → «6-13»; [65,120] → «65-»: l'ultima fascia non ha un ultimo anno. */
const scrivi = ([primo, ultimo]) => (ultimo >= MAX ? `${primo}-` : `${primo}-${ultimo}`);

/** I due numeri scritti a mano → il valore, oppure '' se non è ancora una fascia. */
const componi = (da, a) => {
  const d = String(da).trim();
  const u = String(a).trim();
  if (!/^\d{1,3}$/.test(d)) return '';
  if (u === '') return `${d}-`;
  if (!/^\d{1,3}$/.test(u)) return '';
  return Number(u) < Number(d) ? '' : `${d}-${u}`;
};

const AgeRange = ({ data, handleChange, path, label, uischema, visible }) => {
  if (visible === false) return null;
  const fasce = uischema?.options?.bands || [];
  const icon = uischema?.options?.icon;
  const valore = typeof data === 'string' ? data : '';
  const r = leggi(valore);

  // I due campi liberi hanno uno stato loro: mentre si digita «6-» e poi «6-1» il
  // valore passa per fasce che non stanno in piedi, e riscriverli a ogni tasto
  // vorrebbe dire cancellare la cifra appena battuta.
  const [libero, setLibero] = useState(() => (r ? [String(r[0]), r[1] >= MAX ? '' : String(r[1])] : ['', '']));
  useEffect(() => {
    if (componi(libero[0], libero[1]) !== valore) {
      setLibero(r ? [String(r[0]), r[1] >= MAX ? '' : String(r[1])] : ['', '']);
    }
  }, [valore]);

  const dentro = (b) => !!r && b.min >= r[0] && b.max <= r[1];
  const tocca = (b) => !!r && b.min <= r[1] && b.max >= r[0];
  const spuntate = fasce.map(dentro);
  const primo = spuntate.indexOf(true);
  const ultimo = spuntate.lastIndexOf(true);

  const metti = (i, j) => handleChange(path, scrivi([fasce[i].min, fasce[j].max]));
  const clic = (i) => {
    if (primo < 0) return metti(i, i);                 // niente di spuntato: solo questa
    if (i < primo) return metti(i, ultimo);            // tira la selezione all'indietro
    if (i > ultimo) return metti(primo, i);            // e in avanti
    if (i === primo && i === ultimo) return handleChange(path, '');   // l'unica: si spegne
    if (i === primo) return metti(i + 1, ultimo);      // dai bordi si toglie
    if (i === ultimo) return metti(primo, i - 1);
    return metti(i, i);                                 // in mezzo: resta solo questa
  };

  /* Una coppia storta — «da 3 a 1» — non arriva MAI al file: il modello tiene
   * l'ultimo valore buono e il campo dice che cosa non va. Non si può però
   * rifiutare mentre si scrive: per arrivare a 12 si passa da 1, e 1 con «da 3»
   * è storto. Quindi si avvisa qui e si rimette a posto quando il campo si
   * lascia — chi ha battuto una cosa che non sta in piedi ritrova quella di
   * prima, non il vuoto. */
  const scriviLibero = (da, a) => {
    setLibero([da, a]);
    const v = componi(da, a);
    const svuotato = String(da).trim() === '' && String(a).trim() === '';
    if (v !== '' || svuotato) handleChange(path, v);
  };
  const chiudiLibero = () => {
    const b = leggi(valore);
    setLibero(b ? [String(b[0]), b[1] >= MAX ? '' : String(b[1])] : ['', '']);
  };
  const storto = libero[0] !== '' && libero[1] !== '' && componi(libero[0], libero[1]) === '';

  return (
    <div className="control age-range">
      <label className="field-label">
        {icon && <span className="material-symbols-outlined">{icon}</span>}
        {label}
      </label>
      <div className="age-bands">
        {fasce.map((b, i) => {
          const cls = ['age-band'];
          if (spuntate[i]) cls.push('on');
          else if (tocca(b)) cls.push('part');
          return (
            <button
              type="button"
              key={b.id}
              className={cls.join(' ')}
              aria-pressed={spuntate[i]}
              onClick={() => clic(i)}
              title={b.school ? `${b.name}: ${b.school}` : b.name}
            >
              <span className="age-band-name">{b.name}</span>
              <span className="age-band-note">{b.max >= MAX ? `${b.min}+` : `${b.min}–${b.max}`}{b.school ? ` · ${b.school}` : ''}</span>
            </button>
          );
        })}
      </div>
      <div className="age-libero">
        <span className="age-oppure">oppure</span>
        <label>
          da
          <input
            type="number" min="0" max={MAX} inputMode="numeric"
            value={libero[0]}
            onChange={(e) => scriviLibero(e.target.value, libero[1])}
            onBlur={chiudiLibero}
          />
        </label>
        <label>
          a
          <input
            type="number" min={libero[0] || 0} max={MAX} inputMode="numeric" placeholder="in su"
            value={libero[1]}
            onChange={(e) => scriviLibero(libero[0], e.target.value)}
            onBlur={chiudiLibero}
          />
        </label>
        {/* I due gradini che mette la legge, non la scuola: in Italia i film si
            classificano VM14 e VM18, e quattordici anni è anche l'età da cui si
            risponde di sé. Stanno qui e non fra le fasce perché fasce non sono —
            e i diciotto cadono in mezzo alle superiori, quindi con le sole caselle
            non si potrebbero nemmeno scrivere. */}
        <button type="button" className="age-scorciatoia" title="Da 14 anni in su" onClick={() => scriviLibero('14', '')}>
          14+
        </button>
        <button type="button" className="age-scorciatoia" title="Da 18 anni in su: solo maggiorenni" onClick={() => scriviLibero('18', '')}>
          18+
        </button>
        <span className={storto ? 'age-esito age-storto' : 'age-esito'}>
          {storto
            ? 'L’ultimo anno viene prima del primo: non lo scrivo.'
            : valore === ''
              ? 'Vuoto: nessuna età dichiarata.'
              : `Nel file: ${valore}`}
        </span>
        {valore !== '' && (
          <button type="button" className="age-pulisci" title="Nessuna età dichiarata" onClick={() => scriviLibero('', '')}>
            <span className="material-symbols-outlined">close</span>
          </button>
        )}
      </div>
    </div>
  );
};

export const ageRangeTester = rankWith(16, and(uiTypeIs('Control'), optionIs('ageRange', true)));

export default withJsonFormsControlProps(AgeRange);
