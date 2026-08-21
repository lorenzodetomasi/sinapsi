import { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';

// Impostazioni PROPRIE dell'editor (densità, cartella di salvataggio), montate
// dentro la modale "Impostazioni" dell'header condiviso — sotto le sue (Aspetto,
// Preferenze). Così c'è un solo posto per le opzioni, in riga 1.
// Il tema NON è qui: lo governa "Aspetto" dell'header (vedi App.jsx).
export default function PageSettings({ density, onDensity, canForgetDir, onForgetDir }) {
  const [slot, setSlot] = useState(null);

  // header.js viene iniettato a runtime: attendiamo che lo slot esista.
  useEffect(() => {
    let stop = false;
    (function find() {
      if (stop) return;
      const el = window.Meetoo?.settingsSlot?.();
      if (el) setSlot(el);
      else setTimeout(find, 150);
    })();
    return () => { stop = true; };
  }, []);

  if (!slot) return null;

  const Choice = ({ value, icon, label }) => (
    <button type="button" aria-pressed={density === value} onClick={() => onDensity(value)}>
      <span className="material-symbols-outlined">{icon}</span>
      {label}
    </button>
  );

  return createPortal(
    <>
      <div className="mt-set-group">
        <div className="mt-set-label">Densità</div>
        <div className="mt-choice">
          <Choice value="comfortable" icon="density_medium" label="Comoda" />
          <Choice value="compact" icon="density_small" label="Compatta" />
        </div>
      </div>

      {canForgetDir && (
        <div className="mt-set-group">
          <div className="mt-set-label">Salvataggio su PC</div>
          <div className="mt-choice">
            <button
              type="button"
              onClick={() => onForgetDir?.()}
              title="Ripristina la scelta della cartella base al prossimo salvataggio"
            >
              <span className="material-symbols-outlined">folder_off</span>
              Cambia cartella
            </button>
          </div>
        </div>
      )}
    </>,
    slot
  );
}
