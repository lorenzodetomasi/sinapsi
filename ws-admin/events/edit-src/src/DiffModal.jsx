import { useMemo, useState } from 'react';
import { pathLabel } from './diff.js';

// Pannello di confronto salvato-sul-web ↔ form, prima del salvataggio.
// Per ogni campo cambiato l'utente sceglie: usa il mio (sovrascrivi) / tieni il web (ignora).
// Conferma → onConfirm(insieme dei percorsi da tenere-dal-web).

const KIND = {
  modified: { label: 'modificato', cls: 'mod', icon: 'edit' },
  added: { label: 'aggiunto', cls: 'add', icon: 'add_circle' },
  removed: { label: 'eliminato', cls: 'del', icon: 'remove_circle' },
};

function fmt(v) {
  if (v === undefined || v === null || v === '') return '∅ (vuoto)';
  if (typeof v === 'string') return v.length > 160 ? v.slice(0, 160) + '…' : v;
  const s = JSON.stringify(v);
  return s.length > 160 ? s.slice(0, 160) + '…' : s;
}

export default function DiffModal({ open, changes, relPath, saving, onConfirm, onCancel }) {
  // Insieme dei percorsi per cui tenere il valore del web (default: vuoto = uso i miei).
  const [keepTheirs, setKeepTheirs] = useState(() => new Set());
  const counts = useMemo(() => {
    const c = { modified: 0, added: 0, removed: 0 };
    for (const ch of changes || []) c[ch.kind]++;
    return c;
  }, [changes]);

  if (!open) return null;
  const toggle = (path) =>
    setKeepTheirs((prev) => {
      const next = new Set(prev);
      next.has(path) ? next.delete(path) : next.add(path);
      return next;
    });
  const allMine = () => setKeepTheirs(new Set());
  const allTheirs = () => setKeepTheirs(new Set((changes || []).map((c) => c.path)));

  return (
    <div className="modal-overlay" onClick={saving ? undefined : onCancel}>
      <div className="modal-box diff-box" onClick={(e) => e.stopPropagation()}>
        <div className="modal-head">
          <span className="material-symbols-outlined">difference</span>
          <span>Modifiche rispetto al web</span>
          <button type="button" className="icon-btn" onClick={onCancel} disabled={saving} title="Annulla">
            <span className="material-symbols-outlined">close</span>
          </button>
        </div>

        <div className="modal-body">
          <p className="diff-sub">
            <code>{relPath}</code> · {changes.length} camp{changes.length === 1 ? 'o' : 'i'}
            {counts.modified ? <> · <b className="k-mod">{counts.modified} modif.</b></> : null}
            {counts.added ? <> · <b className="k-add">{counts.added} aggiunt{counts.added === 1 ? 'o' : 'i'}</b></> : null}
            {counts.removed ? <> · <b className="k-del">{counts.removed} elimin.</b></> : null}
          </p>
          <div className="diff-bulk">
            <button type="button" className="btn-ghost" onClick={allMine} disabled={saving}>Usa tutti i miei</button>
            <button type="button" className="btn-ghost" onClick={allTheirs} disabled={saving}>Tieni tutti dal web</button>
          </div>

          <div className="diff-list">
            {changes.map((c) => {
              const k = KIND[c.kind];
              const theirs = keepTheirs.has(c.path);
              return (
                <div key={c.path} className={'diff-row diff-' + k.cls + (theirs ? ' keep-theirs' : '')}>
                  <div className="diff-row-head">
                    <span className={'diff-kind k-' + k.cls}>
                      <span className="material-symbols-outlined">{k.icon}</span> {k.label}
                    </span>
                    <span className="diff-path">{pathLabel(c.path)}</span>
                  </div>
                  <div className="diff-values">
                    <div className={'diff-val diff-web' + (theirs ? ' chosen' : '')}>
                      <span className="diff-val-tag">web</span>
                      <span className="diff-val-txt">{fmt(c.before)}</span>
                    </div>
                    <div className={'diff-val diff-mine' + (!theirs ? ' chosen' : '')}>
                      <span className="diff-val-tag">il mio</span>
                      <span className="diff-val-txt">{fmt(c.after)}</span>
                    </div>
                  </div>
                  <div className="diff-choice">
                    <label className={!theirs ? 'on' : ''}>
                      <input type="radio" name={'ch-' + c.path} checked={!theirs} onChange={() => theirs && toggle(c.path)} disabled={saving} />
                      usa il mio
                    </label>
                    <label className={theirs ? 'on' : ''}>
                      <input type="radio" name={'ch-' + c.path} checked={theirs} onChange={() => !theirs && toggle(c.path)} disabled={saving} />
                      tieni il web
                    </label>
                  </div>
                </div>
              );
            })}
          </div>
        </div>

        <div className="modal-foot">
          <button type="button" className="btn-ghost" onClick={onCancel} disabled={saving}>Annulla</button>
          <button type="button" className="btn-save" onClick={() => onConfirm(keepTheirs)} disabled={saving}>
            <span className="material-symbols-outlined">cloud_upload</span>
            {saving ? 'Salvo…' : keepTheirs.size ? `Salva (${changes.length - keepTheirs.size} miei)` : 'Salva le mie modifiche'}
          </button>
        </div>
      </div>
    </div>
  );
}
