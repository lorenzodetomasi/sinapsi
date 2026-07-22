import { useState } from 'react';
import { rankWith, and, isStringControl, schemaMatches } from '@jsonforms/core';
import { withJsonFormsControlProps } from '@jsonforms/react';

// Campo immagine: file picker + anteprima; carica in media-sources/ (endpoint PHP)
// e salva nel campo il path relativo restituito (es. "media-sources/cover.jpg").
// Il path resta editabile a mano (accetta anche un URL assoluto).
const ImageUpload = ({ data, handleChange, path, label, id, enabled }) => {
  const [preview, setPreview] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  async function onFile(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    setError('');

    // Anteprima immediata lato client (data URL).
    const reader = new FileReader();
    reader.onload = () => setPreview(reader.result);
    reader.readAsDataURL(file);

    setBusy(true);
    try {
      const fd = new FormData();
      fd.append('action', 'upload');
      fd.append('file', file);
      const res = await fetch('/api', { method: 'POST', body: fd });
      const out = await res.json();
      if (out.success) handleChange(path, out.path);
      else setError(out.error || 'Upload fallito');
    } catch {
      setError('Convertitore PHP non raggiungibile su :8080 (avvia `php -S localhost:8080` in json-xml/).');
    } finally {
      setBusy(false);
    }
  }

  const src = preview || data;

  return (
    <div className="control image-upload">
      <label htmlFor={id}>{label}</label>
      <div className="image-upload-row">
        {src && (
          <img
            className="image-thumb"
            src={src}
            alt=""
            onError={(e) => {
              e.currentTarget.style.visibility = 'hidden';
            }}
          />
        )}
        <div className="image-upload-fields">
          <input id={id} type="file" accept="image/*" disabled={enabled === false || busy} onChange={onFile} />
          <input
            className="path-field"
            type="text"
            value={data ?? ''}
            placeholder="media-sources/… oppure URL"
            onChange={(e) => handleChange(path, e.target.value)}
          />
          {busy && <span className="hint">caricamento…</span>}
          {error && <span className="err">{error}</span>}
        </div>
      </div>
    </div>
  );
};

export const imageUploadTester = rankWith(
  10,
  and(isStringControl, schemaMatches((s) => s && s.format === 'image'))
);

export default withJsonFormsControlProps(ImageUpload);
