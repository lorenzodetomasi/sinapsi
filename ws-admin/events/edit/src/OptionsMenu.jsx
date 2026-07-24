import { useEffect, useRef, useState } from 'react';

// Menu "Opzioni": tema chiaro/scuro e densità dell'interfaccia.
// Si chiude con click esterno o Esc.
export default function OptionsMenu({ theme, onTheme, density, onDensity }) {
  const [open, setOpen] = useState(false);
  const ref = useRef(null);

  useEffect(() => {
    if (!open) return;
    const onDoc = (e) => {
      if (ref.current && !ref.current.contains(e.target)) setOpen(false);
    };
    const onKey = (e) => e.key === 'Escape' && setOpen(false);
    document.addEventListener('mousedown', onDoc);
    document.addEventListener('keydown', onKey);
    return () => {
      document.removeEventListener('mousedown', onDoc);
      document.removeEventListener('keydown', onKey);
    };
  }, [open]);

  const Choice = ({ value, current, onPick, icon, label }) => (
    <button type="button" aria-pressed={current === value} onClick={() => onPick(value)}>
      <span className="material-symbols-outlined">{icon}</span>
      {label}
    </button>
  );

  return (
    <div className="options" ref={ref}>
      <button
        type="button"
        className="icon-btn"
        title="Opzioni"
        aria-haspopup="true"
        aria-expanded={open}
        onClick={() => setOpen((v) => !v)}
      >
        <span className="material-symbols-outlined">tune</span>
      </button>

      {open && (
        <div className="options-menu" role="menu">
          <div className="options-section">
            <div className="options-title">Tema</div>
            <div className="options-choice">
              <Choice value="light" current={theme} onPick={onTheme} icon="light_mode" label="Chiaro" />
              <Choice value="dark" current={theme} onPick={onTheme} icon="dark_mode" label="Scuro" />
            </div>
          </div>

          <div className="options-section">
            <div className="options-title">Densità</div>
            <div className="options-choice">
              <Choice value="comfortable" current={density} onPick={onDensity} icon="density_medium" label="Comoda" />
              <Choice value="compact" current={density} onPick={onDensity} icon="density_small" label="Compatta" />
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
