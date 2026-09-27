import { rankWith, and, uiTypeIs, optionIs } from '@jsonforms/core';
import { withJsonFormsControlProps } from '@jsonforms/react';
import { PROPRIETA, RUOLI, proprietaDa } from './crediti.js';

/* Credits in the programme's order and words: «in scena», «ideazione e regia»,
 * «interpreti», «a cura di». Each row names its role as the programme does and
 * says which schema.org property it stands for; the property is PROPOSED from
 * the words and can be changed. The model is in ws-admin/lib/event-credits.php. */

const TIPI = [['Person', 'persona'], ['PerformingGroup', 'gruppo, compagnia'], ['Organization', 'organizzazione']];
const vuota = () => ({ ruolo: '', proprieta: '', persone: [{ nome: '', nota: '', tipo: 'Person' }] });

function CreditiRenderer({ data, handleChange, path, visible }) {
  if (visible === false) return null;
  const righe = Array.isArray(data) ? data : [];
  const scrivi = (r) => handleChange(path, r);
  const setR = (i, x) => scrivi(righe.map((r, j) => (j === i ? { ...r, ...x } : r)));
  const setP = (i, k, x) => setR(i, { persone: righe[i].persone.map((p, j) => (j === k ? { ...p, ...x } : p)) });
  const sposta = (i, d) => {
    const j = i + d;
    if (j < 0 || j >= righe.length) return;
    const n = [...righe];
    [n[i], n[j]] = [n[j], n[i]];
    scrivi(n);
  };

  return (
    <div className="crediti">
      <div className="occ-testa">
        <span className="material-symbols-outlined">theater_comedy</span>
        <span className="field-label">Crediti</span>
      </div>
      <datalist id="crediti-ruoli">{RUOLI.map((r) => <option key={r} value={r} />)}</datalist>
      {righe.map((r, i) => (
        <div className="offerta" key={i}>
          <div className="quando-linea">
            <input type="text" list="crediti-ruoli" placeholder="ruolo, come nel programma" value={r.ruolo}
              onChange={(e) => {
                const ruolo = e.target.value;
                // The property follows the words until someone changes it by hand.
                const seguiva = !r.proprieta || r.proprieta === proprietaDa(r.ruolo);
                setR(i, { ruolo, ...(seguiva ? { proprieta: proprietaDa(ruolo) } : {}) });
              }} />
            <select value={r.proprieta || ''} onChange={(e) => setR(i, { proprieta: e.target.value })}>
              {PROPRIETA.map((p) => <option key={p.v} value={p.v}>{p.l}</option>)}
            </select>
            <button type="button" className="btn-icona" title="Su" onClick={() => sposta(i, -1)}><span className="material-symbols-outlined">arrow_upward</span></button>
            <button type="button" className="btn-icona" title="Giù" onClick={() => sposta(i, 1)}><span className="material-symbols-outlined">arrow_downward</span></button>
            <button type="button" className="btn-icona" title="Togli la riga" onClick={() => scrivi(righe.filter((_, j) => j !== i))}><span className="material-symbols-outlined">close</span></button>
          </div>
          {(r.persone || []).map((p, k) => (
            <div className="quando-linea offerta-dettagli" key={k}>
              <input type="text" placeholder="nome" value={p.nome} onChange={(e) => setP(i, k, { nome: e.target.value })} />
              <input type="text" className="offerta-url" placeholder="nota (es. basso, arpa elettrica)" value={p.nota} onChange={(e) => setP(i, k, { nota: e.target.value })} />
              <select value={p.tipo || 'Person'} onChange={(e) => setP(i, k, { tipo: e.target.value })}>
                {TIPI.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
              </select>
              <button type="button" className="btn-icona" title="Togli il nome"
                onClick={() => setR(i, { persone: r.persone.filter((_, j) => j !== k) })}><span className="material-symbols-outlined">person_remove</span></button>
            </div>
          ))}
          <div className="quando-linea offerta-dettagli">
            <button type="button" className="btn-link" onClick={() => setR(i, { persone: [...(r.persone || []), { nome: '', nota: '', tipo: 'Person' }] })}>
              <span className="material-symbols-outlined">person_add</span>un altro nome
            </button>
          </div>
        </div>
      ))}
      <div className="occ-azioni">
        <button type="button" className="btn-ghost" onClick={() => scrivi([...righe, vuota()])}>
          <span className="material-symbols-outlined">add</span>Un ruolo
        </button>
      </div>
    </div>
  );
}

export const creditiTester = rankWith(18, and(uiTypeIs('Control'), optionIs('crediti', true)));
export default withJsonFormsControlProps(CreditiRenderer);
