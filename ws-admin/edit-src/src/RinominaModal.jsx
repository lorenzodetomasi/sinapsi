/**
 * L'@id è cambiato: che ne facciamo dell'originale?
 *
 * Salvare crea SEMPRE una cartella nuova, perché la cartella è l'@id. Finché
 * nessuno lo diceva, cambiare l'@id di un evento aperto lasciava in giro la
 * cartella di prima — due eventi identici, tutti e due negli indici e nelle
 * categorie, e nessun modo di sapere quale fosse quello buono. È successo, e la
 * normalizzazione non poteva accorgersene: per lei erano due eventi coerenti.
 *
 * Le due strade sono legittime e nessuna delle due è «quella giusta»:
 *   - RINOMINARE: l'@id era sbagliato e si corregge. L'originale va nel cestino,
 *     da dove si può sempre ripescare.
 *   - DUPLICARE: da questo evento se ne fa un altro. L'originale resta dov'è.
 *
 * Quindi si chiede, mostrando i due @id per esteso: un dialogo che dice «vuoi
 * sovrascrivere?» senza dire *che cosa* si sovrascrive non è una domanda, è un
 * indovinello.
 */
export default function RinominaModal({ open, da, a, saving, onSposta, onCopia, onCancel }) {
  if (!open) return null;
  return (
    <div className="modal-overlay" onClick={saving ? undefined : onCancel}>
      <div className="modal-box" onClick={(e) => e.stopPropagation()}>
        <div className="modal-head">
          <span className="material-symbols-outlined">drive_file_rename_outline</span>
          <span>L’identificativo è cambiato</span>
          <button type="button" className="icon-btn" onClick={onCancel} disabled={saving} title="Annulla">
            <span className="material-symbols-outlined">close</span>
          </button>
        </div>

        <div className="modal-body">
          <p className="diff-sub">
            Hai aperto <code>{da}</code> e stai per salvare <code>{a}</code>.
            Sono due cartelle diverse: l’identificativo <em>è</em> il percorso.
          </p>
          <ul className="rinomina-scelte">
            <li>
              <b>Rinomina</b> — salva il nuovo e manda <code>{da}</code> nel cestino,
              da dove si può ripristinare. Resta un evento solo.
            </li>
            <li>
              <b>Duplica</b> — salva il nuovo e lascia <code>{da}</code> dov’è.
              Diventano due eventi distinti.
            </li>
          </ul>
        </div>

        <div className="modal-foot">
          <button type="button" className="btn-ghost" onClick={onCancel} disabled={saving}>Annulla</button>
          <button type="button" className="btn-ghost" onClick={onCopia} disabled={saving}>
            <span className="material-symbols-outlined">content_copy</span> Duplica (tieni tutti e due)
          </button>
          <button type="button" className="btn-primary" onClick={onSposta} disabled={saving}>
            <span className="material-symbols-outlined">drive_file_move</span> Rinomina (cestina l’originale)
          </button>
        </div>
      </div>
    </div>
  );
}
