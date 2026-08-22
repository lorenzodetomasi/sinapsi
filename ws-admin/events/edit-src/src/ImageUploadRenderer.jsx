import { useEffect, useRef, useState } from 'react';
import { rankWith, and, isStringControl, schemaMatches } from '@jsonforms/core';
import { withJsonFormsControlProps, useJsonForms } from '@jsonforms/react';
import { CONTENT_BASE, MEDIA_URL } from './config.js';

const ACCEPT = ['image/jpeg', 'image/png', 'image/webp'];
const ACCEPT_ATTR = '.jpg,.jpeg,.png,.webp';

// Campo immagine (cover 16:9).
//
// Il file va nella cartella DELL'EVENTO, non in un'area di appoggio: se è già
// 1920×1080 finisce in media/, altrimenti l'originale resta in media-sources/ e
// in media/ va la versione 1920×1080 generata dal server. Il campo salva il
// percorso DALLA RADICE dei contenuti (events/<slug>/media/…): così la stessa
// immagine può essere usata da un altro evento senza copiarla.
const ImageUpload = ({ data, handleChange, path, label, id, uischema, enabled }) => {
  const ctx = useJsonForms();
  const [preview, setPreview] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [note, setNote] = useState('');
  const [source, setSource] = useState('');      // originale in media-sources (se c'è)
  const [drag, setDrag] = useState(false);
  const [picker, setPicker] = useState(null);    // elenco cover riusabili
  const [crop, setCrop] = useState(null);        // {src, y} inquadratura in corso
  const [isMobile, setIsMobile] = useState(() => !!window.matchMedia?.('(max-width: 62.5rem)').matches);
  useEffect(() => {
    const mq = window.matchMedia('(max-width: 62.5rem)');
    const on = (e) => setIsMobile(e.matches);
    mq.addEventListener('change', on);
    return () => mq.removeEventListener('change', on);
  }, []);
  const icon = uischema?.options?.icon || 'image';

  // Cartella dell'evento, ricavata dal suo @id: il server ci deve scrivere dentro.
  function eventPath() {
    const eid = String(ctx?.core?.data?.id || '').trim().replace(/^\/+|\/+$/g, '');
    if (!eid) return '';
    return eid.includes('/') ? eid : 'events/' + eid;
  }
  const token = () => (window.meetooSession && window.meetooSession.getToken()) || '';

  async function call(fields, file) {
    const fd = new FormData();
    Object.entries(fields).forEach(([k, v]) => fd.append(k, v));
    fd.append('credential', token());
    if (file) fd.append('file', file);
    const res = await fetch(MEDIA_URL, { method: 'POST', body: fd });
    return { status: res.status, body: await res.json().catch(() => ({})) };
  }

  async function upload(file) {
    if (!file) return;
    setError(''); setNote('');
    if (file.type && !ACCEPT.includes(file.type)) { setError('Formato non supportato. Ammessi: JPG, PNG, WEBP.'); return; }
    const rel = eventPath();
    if (!rel) { setError("Manca l'@id: compila l'identità dell'evento prima di caricare la copertina."); return; }
    if (!token()) { setError('Accedi con Google (in alto a destra) per caricare la copertina.'); return; }

    const reader = new FileReader();
    reader.onload = () => setPreview(reader.result);
    reader.readAsDataURL(file);

    setBusy(true);
    try {
      const { status, body } = await call({ action: 'upload', path: rel }, file);
      if (status === 200 && body.success) {
        handleChange(path, body.path);
        setPreview('');
        setSource(body.source || '');
        setNote(body.note || '');
      } else {
        setError(body.error || 'Caricamento fallito.');
      }
    } catch (e) {
      setError('Endpoint immagini non raggiungibile: ' + (e?.message || e));
    } finally {
      setBusy(false);
    }
  }

  // Riuso: elenco delle cover già presenti (anche di eventi passati).
  async function openPicker() {
    setError('');
    try {
      const { status, body } = await call({ action: 'library' });
      if (status === 200) setPicker(body.items || []);
      else setError(body.error || 'Elenco non disponibile.');
    } catch { setError('Endpoint immagini non raggiungibile.'); }
  }

  // Nuova inquadratura: si sceglie quanto scorrere il riquadro 16:9 sull'originale.
  async function applyCrop() {
    if (!crop) return;
    setBusy(true); setError('');
    try {
      const { status, body } = await call({
        action: 'recrop', path: eventPath(), source,
        x: 0, y: crop.y, w: 1, h: crop.h,
      });
      if (status === 200 && body.success) {
        handleChange(path, body.path);
        setNote('Inquadratura aggiornata.');
        setCrop(null);
        setPreview(''); // forza il ricaricamento dell'anteprima
      } else setError(body.error || 'Ritaglio fallito.');
    } finally { setBusy(false); }
  }

  const asUrl = (p) => (!p ? '' : (/^https?:\/\//i.test(p) ? p
    : (/^(events|places|organizations)\//.test(p) ? CONTENT_BASE + p : CONTENT_BASE + eventPath() + '/' + p)));
  const src = preview || asUrl(data);

  return (
    <div className="control image-upload">
      <label className="field-label" htmlFor={id}>
        <span className="material-symbols-outlined">{icon}</span>
        {label}
      </label>
      <div className="image-upload-row">
        <div className="image-thumb-box">
          {src ? (
            <img className="image-thumb" src={src} alt="" onError={(e) => (e.currentTarget.style.visibility = 'hidden')} />
          ) : (
            <span className="material-symbols-outlined image-thumb-empty">add_photo_alternate</span>
          )}
        </div>

        <label
          className={'dropzone' + (drag ? ' drag' : '') + (busy ? ' busy' : '') + (isMobile ? ' as-button' : '')}
          onDragOver={isMobile ? undefined : (e) => { e.preventDefault(); setDrag(true); }}
          onDragLeave={isMobile ? undefined : () => setDrag(false)}
          onDrop={isMobile ? undefined : (e) => { e.preventDefault(); setDrag(false); upload(e.dataTransfer.files?.[0]); }}
        >
          <input
            id={id}
            type="file"
            accept={ACCEPT_ATTR}
            hidden
            disabled={enabled === false || busy}
            onChange={(e) => upload(e.target.files?.[0])}
          />
          <span className="material-symbols-outlined">{isMobile ? 'photo_camera' : 'upload'}</span>
          <span className="dz-text">{busy ? 'Caricamento…' : isMobile ? 'Scegli file' : 'Trascina qui o clicca'}</span>
          <span className="dz-formats">16:9 · 1920×1080 · JPG · PNG · WEBP</span>
        </label>
      </div>

      <div className="image-actions">
        <button type="button" className="btn-ghost" onClick={openPicker}>
          <span className="material-symbols-outlined">photo_library</span> Riusa un'immagine
        </button>
        {source && (
          <button type="button" className="btn-ghost" onClick={() => setCrop({ src: asUrl(source), y: 0, h: 0.5 })}>
            <span className="material-symbols-outlined">crop_16_9</span> Inquadratura
          </button>
        )}
      </div>

      {crop && (
        <div className="crop-box">
          <div className="crop-hint">Trascina il cursore per scegliere quale fascia dell'immagine diventa la copertina.</div>
          <div className="crop-frame">
            <img src={crop.src} alt="" style={{ transform: `translateY(-${crop.y * 100}%)` }} />
          </div>
          <input type="range" min="0" max="100" value={Math.round(crop.y * 100)}
                 onChange={(e) => setCrop({ ...crop, y: Number(e.target.value) / 100 })} />
          <div className="image-actions">
            <button type="button" className="btn-save" onClick={applyCrop} disabled={busy}>Applica</button>
            <button type="button" className="btn-ghost" onClick={() => setCrop(null)}>Annulla</button>
          </div>
        </div>
      )}

      {picker && (
        <div className="picker">
          <div className="crop-hint">
            {picker.length ? "Scegli un'immagine già presente: verrà usata senza crearne una copia." : 'Nessuna immagine disponibile.'}
            <button type="button" className="btn-ghost" onClick={() => setPicker(null)}>Chiudi</button>
          </div>
          <div className="picker-grid">
            {picker.map((it) => (
              <button type="button" key={it.path} className="picker-item"
                      title={it.path}
                      onClick={() => { handleChange(path, it.path); setPicker(null); setSource(''); setNote('Immagine riusata: nessun file duplicato.'); }}>
                <img src={asUrl(it.path)} alt="" loading="lazy" />
              </button>
            ))}
          </div>
        </div>
      )}

      <input
        className="path-field"
        type="text"
        value={data ?? ''}
        placeholder="events/<evento>/media/cover.jpg oppure URL"
        onChange={(e) => handleChange(path, e.target.value)}
      />
      {note && <span className="hint-line">{note}</span>}
      {error && <span className="err">{error}</span>}
    </div>
  );
};

export const imageUploadTester = rankWith(
  10,
  and(isStringControl, schemaMatches((s) => s && s.format === 'image'))
);

export default withJsonFormsControlProps(ImageUpload);
