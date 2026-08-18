import { useEffect, useState } from 'react';
import { EVENTS_INDEX_URL } from './config.js';

// Fase 2 — "Apri evento dal web": incolla @id/percorso/URL, oppure scegli dall'elenco
// (indice eventi). L'indice si popola al salvataggio web (Fase 4): finché non c'è,
// il picker mostra una nota e resta usabile la parte "incolla".
export default function OpenEventModal({ open, onClose, onOpen }) {
  const [input, setInput] = useState('');
  const [q, setQ] = useState('');
  const [list, setList] = useState(null); // null=caricamento · false=indice assente · [] items

  useEffect(() => {
    if (!open) return;
    setInput('');
    setQ('');
    setList(null);
    let alive = true;
    fetch(EVENTS_INDEX_URL, { headers: { Accept: 'application/json' } })
      .then((r) => (r.ok ? r.json() : Promise.reject()))
      .then((idx) => {
        if (!alive) return;
        const items = Array.isArray(idx) ? idx : Array.isArray(idx?.events) ? idx.events : [];
        setList(items);
      })
      .catch(() => alive && setList(false));
    return () => {
      alive = false;
    };
  }, [open]);

  useEffect(() => {
    if (!open) return;
    const onKey = (e) => e.key === 'Escape' && onClose();
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [open, onClose]);

  if (!open) return null;

  const term = q.trim().toLowerCase();
  const items = Array.isArray(list) ? list : [];
  const filtered = items.filter((it) => {
    const hay = `${it.name || ''} ${it['@id'] || it.path || ''} ${it.organizer || ''} ${it.cap || ''}`.toLowerCase();
    return hay.includes(term);
  });
  const submitPaste = () => input.trim() && onOpen(input.trim());

  return (
    <div className="modal-overlay" onMouseDown={(e) => e.target === e.currentTarget && onClose()}>
      <div className="modal-box" role="dialog" aria-modal="true" aria-label="Apri evento dal web">
        <div className="modal-head">
          <h3>Apri evento dal web</h3>
          <button type="button" className="icon-btn" onClick={onClose} title="Chiudi">
            <span className="material-symbols-outlined">close</span>
          </button>
        </div>

        <div className="modal-body">
          <label className="modal-label">Incolla @id, percorso o URL</label>
          <div className="modal-row">
            <input
              className="modal-input"
              value={input}
              autoFocus
              onChange={(e) => setInput(e.target.value)}
              onKeyDown={(e) => e.key === 'Enter' && submitPaste()}
              placeholder="events/20260825T1845-IT00122  ·  https://…/index.json"
            />
            <button type="button" className="btn-save" disabled={!input.trim()} onClick={submitPaste}>
              <span className="material-symbols-outlined">open_in_new</span> Apri
            </button>
          </div>

          <div className="modal-sep">oppure scegli dall'elenco</div>
          <input
            className="modal-input"
            value={q}
            onChange={(e) => setQ(e.target.value)}
            placeholder="Cerca per nome, organizer, cap, @id…"
          />
          <div className="modal-list">
            {list === null && <div className="modal-note">Carico l'elenco…</div>}
            {list === false && (
              <div className="modal-note">
                Elenco non ancora disponibile: l'indice degli eventi si popola salvando eventi sul web (Fase 4).
                Per ora usa il campo qui sopra.
              </div>
            )}
            {Array.isArray(list) && filtered.length === 0 && <div className="modal-note">Nessun evento trovato.</div>}
            {filtered.map((it, i) => (
              <button
                type="button"
                key={it['@id'] || it.path || i}
                className="modal-item"
                onClick={() => onOpen(it['@id'] || it.path)}
              >
                <span className="modal-item-name">{it.name || it['@id'] || it.path}</span>
                <span className="modal-item-meta">
                  {[it.startDate?.slice(0, 16)?.replace('T', ' '), it.organizer, it.cap].filter(Boolean).join(' · ')}
                </span>
              </button>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}
