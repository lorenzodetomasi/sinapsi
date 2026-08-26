import { useEffect, useRef } from 'react';
import { rankWith, and, isStringControl, schemaMatches } from '@jsonforms/core';
import { withJsonFormsControlProps, useJsonForms } from '@jsonforms/react';
import { SEO_LIMITI, statoLunghezza, haMarcatura, riassunto } from './seo';

/**
 * La frase che finisce nei risultati di ricerca e nelle anteprime dei link.
 *
 * Tre cose che un campo di testo qualunque non fa:
 *
 * 1. SI SCRIVE DA SÉ finché nessuno la tocca. Vuota, segue il Sommario: ne prende
 *    il testo senza marcatura, chiuso su una frase intera. Appena qualcuno scrive
 *    qualcosa di suo, smette di seguirlo e non ci torna più da sola — è la stessa
 *    regola dell'@id, che qui dentro ormai è una convenzione.
 * 2. CONTA I CARATTERI, spazi inclusi, e dice se la misura è comoda. Non blocca:
 *    il taglio vero lo fa il motore, in pixel, e spesso la frase se la riscrive.
 * 3. SEGNALA LA MARCATURA. Se qui dentro finisce dell'XHTML — succede aprendo un
 *    contenuto scritto prima che i due campi si separassero — lo dice, perché nel
 *    risultato di ricerca i tag si vedrebbero come tali.
 */
const SeoDescrizione = ({ data, handleChange, path, label, id, uischema, enabled, visible }) => {
  if (visible === false) return null;
  const ctx = useJsonForms();
  const sommario = ctx?.core?.data?.abstract ?? '';
  const valore = data ?? '';
  const scritto = useRef(null);   // l'ultima proposta che abbiamo messo noi

  const proposta = riassunto(sommario);
  const automatico = valore === '' || valore === scritto.current;

  useEffect(() => {
    if (!automatico || !proposta || proposta === valore) return;
    scritto.current = proposta;
    handleChange(path, proposta);
  }, [proposta, automatico, valore]);

  const stato = statoLunghezza(valore);
  const marcata = haMarcatura(valore);
  const icona = uischema?.options?.icon;

  return (
    <div className="control seo-descrizione">
      <label className="field-label" htmlFor={id}>
        {icona && <span className="material-symbols-outlined">{icona}</span>}
        {label}
        <span className={'seo-conta ' + stato} title={`Comoda fra ${SEO_LIMITI.corta} e ${SEO_LIMITI.buona} caratteri`}>
          {valore.length}
        </span>
        {!automatico && sommario ? (
          <button
            type="button"
            className="seo-rigenera"
            title="Riscrivila dal Sommario"
            onClick={() => { scritto.current = proposta; handleChange(path, proposta); }}
          >
            <span className="material-symbols-outlined">auto_fix_high</span>
          </button>
        ) : null}
      </label>
      <textarea
        id={id}
        rows={2}
        value={valore}
        disabled={enabled === false}
        placeholder={uischema?.options?.placeholder || 'Una frase che descrive il contenuto a chi lo trova su un motore di ricerca.'}
        onChange={(e) => handleChange(path, e.target.value || undefined)}
      />
      <div className="seo-nota">
        {marcata ? (
          <span className="avviso">
            <span className="material-symbols-outlined">warning</span>
            Qui dentro c'è della marcatura: nel risultato di ricerca si vedrebbero i tag. Il testo formattato va nel Sommario.
          </span>
        ) : stato === 'troppo' ? (
          <span className="avviso">Oltre i {SEO_LIMITI.lunga} caratteri viene quasi certamente tagliata.</span>
        ) : stato === 'lunga' ? (
          <span>Un po' lunga: potrebbe essere tagliata.</span>
        ) : stato === 'corta' ? (
          <span>Ci starebbe altro: la misura comoda arriva a {SEO_LIMITI.buona} caratteri.</span>
        ) : automatico && valore ? (
          <span>Scritta dal Sommario. Cambiala pure: da quel momento resta com'è.</span>
        ) : null}
      </div>
    </div>
  );
};

/* `schemaMatches`, non una funzione qualunque: un tester scritto a mano riceve lo
 * schema RADICE, non quello del campo — e `schema.format` sarebbe sempre
 * indefinito. È lo stesso inciampo per cui l'editor mostrava qui un campo di
 * testo normale invece di questo. */
export const seoDescrizioneTester = rankWith(
  10,
  and(isStringControl, schemaMatches((s) => s && s.format === 'seo'))
);

export default withJsonFormsControlProps(SeoDescrizione);
