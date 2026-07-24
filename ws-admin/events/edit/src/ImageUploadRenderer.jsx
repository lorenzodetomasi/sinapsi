import { useState } from 'react';
import { rankWith, and, isStringControl, schemaMatches } from '@jsonforms/core';
import { withJsonFormsControlProps } from '@jsonforms/react';
import { API_BASE } from './config.js';

const ACCEPT = ['image/jpeg', 'image/png', 'image/svg+xml'];
const ACCEPT_ATTR = '.jpg,.jpeg,.png,.svg';

// Campo immagine: anteprima + area drag&drop + path editabile. Carica in
// media-sources/ (endpoint PHP) e salva il path relativo restituito.
const ImageUpload = ({ data, handleChange, path, label, id, uischema, enabled }) => {
  const [preview, setPreview] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [drag, setDrag] = useState(false);
  const icon = uischema?.options?.icon || 'image';

  async function upload(file) {
    if (!file) return;
    setError('');
    if (file.type && !ACCEPT.includes(file.type)) {
      setError('Formato non supportato. Ammessi: JPG, JPEG, PNG, SVG.');
      return;
    }
    const reader = new FileReader();
    reader.onload = () => setPreview(reader.result);
    reader.readAsDataURL(file);

    setBusy(true);
    try {
      const fd = new FormData();
      fd.append('action', 'upload');
      fd.append('file', file);
      const res = await fetch(API_BASE, { method: 'POST', body: fd });
      const out = await res.json();
      if (out.success) handleChange(path, out.path);
      else setError(out.error || 'Upload fallito');
    } catch {
      setError('Convertitore PHP non raggiungibile su :8080.');
    } finally {
      setBusy(false);
    }
  }

  function onDrop(e) {
    e.preventDefault();
    setDrag(false);
    upload(e.dataTransfer.files?.[0]);
  }

  const src = preview || data;

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
          className={'dropzone' + (drag ? ' drag' : '') + (busy ? ' busy' : '')}
          onDragOver={(e) => {
            e.preventDefault();
            setDrag(true);
          }}
          onDragLeave={() => setDrag(false)}
          onDrop={onDrop}
        >
          <input
            id={id}
            type="file"
            accept={ACCEPT_ATTR}
            hidden
            disabled={enabled === false || busy}
            onChange={(e) => upload(e.target.files?.[0])}
          />
          <span className="material-symbols-outlined">upload</span>
          <span className="dz-text">{busy ? 'Caricamento…' : 'Trascina qui o clicca'}</span>
          <span className="dz-formats">JPG · JPEG · PNG · SVG</span>
        </label>
      </div>

      <input
        className="path-field"
        type="text"
        value={data ?? ''}
        placeholder="media-sources/… oppure URL"
        onChange={(e) => handleChange(path, e.target.value)}
      />
      {error && <span className="err">{error}</span>}
    </div>
  );
};

export const imageUploadTester = rankWith(
  10,
  and(isStringControl, schemaMatches((s) => s && s.format === 'image'))
);

export default withJsonFormsControlProps(ImageUpload);
