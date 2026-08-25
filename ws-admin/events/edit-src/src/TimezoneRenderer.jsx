import { useId } from 'react';
import { rankWith, and, isStringControl, schemaMatches } from '@jsonforms/core';
import { withJsonFormsControlProps, useJsonForms } from '@jsonforms/react';
import { fusoDelBrowser, fusiDisponibili, offsetDi, fusoDelLuogo } from './quando.js';

// Il fuso viene prima delle date perché le date, senza, non vogliono dire niente:
// «17:30» è un'ora di parete, e l'istante che indica dipende da dove sta l'orologio.
// Di suo prende il fuso del browser, che per chi redige è quasi sempre quello del
// posto in cui l'evento succede; accanto si legge lo scarto che ne esce, così si
// vede subito che cosa finirà scritto nel file.

const Timezone = ({ data, handleChange, path, label, uischema, visible }) => {
  if (visible === false) return null;
  const listId = useId();
  const ctx = useJsonForms();
  const icon = uischema?.options?.icon;
  /* Campo vuoto non vuol dire «senza fuso»: vuol dire quello del browser, che per
   * chi redige è quasi sempre quello del posto dove l'evento succede. Lo si mostra
   * e lo si usa senza scriverlo nel modello finché nessuno sceglie altro: scriverlo
   * di nostra iniziativa all'apertura significherebbe segnare come «modificato» un
   * evento che nessuno ha toccato. */
  const valore = data || '';
  // Il fuso vero è quello del LUOGO: si ricava dal paese nel suo @id. Quello del
  // browser è solo il ripiego finché un luogo non c'è — redigendo dall'estero
  // timbrerebbe uno scarto che con l'evento non c'entra niente.
  const dalLuogo = fusoDelLuogo(ctx?.core?.data?.location?.id);
  const effettivo = valore || dalLuogo || fusoDelBrowser();
  const quando = ctx?.core?.data?.startDate;
  const scarto = quando ? offsetDi(effettivo, quando) : '';

  return (
    <div className="control rf rf-text">
      <label className="field-label">
        {icon && <span className="material-symbols-outlined">{icon}</span>}
        {label || 'Fuso orario'}
      </label>
      <input
        type="text"
        list={listId}
        value={valore}
        placeholder={effettivo}
        onChange={(e) => handleChange(path, e.target.value)}
      />
      <datalist id={listId}>
        {fusiDisponibili().map((z) => (
          <option key={z} value={z} />
        ))}
      </datalist>
      <span className="place-status">
        {valore ? '' : `Non scelto: vale ${effettivo}, ${dalLuogo ? 'il fuso del luogo' : 'il fuso di questo computer'}. `}
        {scarto
          ? `Le date si salvano con lo scarto ${scarto} (${String(quando).slice(0, 16)}${scarto}).`
          : 'Le date si salvano con lo scarto di questo fuso.'}
      </span>
    </div>
  );
};

export const timezoneTester = rankWith(
  10,
  and(isStringControl, schemaMatches((s) => s && s.format === 'timezone'))
);

export default withJsonFormsControlProps(Timezone);
