import { useMemo, useState } from 'react';

// Replica dell'interfaccia di json-xml per il SOLO codice JSON: gutter con
// numeri di riga, righe d'errore in rosso, icona di stato, checklist degli
// errori con "Rivalida Ora"/"Correggi XHTML" e barra azioni.
//
// Normalmente il codice si legge e basta: il form è la fonte di verità e il JSON
// è il suo riflesso. Con "Modifica" si apre però una parentesi: il testo diventa
// un'area scrivibile e "Applica" lo rilegge nel form. È una porta di servizio —
// serve per incollare un JSON intero, o per aggiustare un campo che il form non
// espone — e come tale avvisa di quello che il form non sa ripetere.
export default function JsonValidationPane({
  payload,
  validation,
  onRevalidate,
  onFix,
  onGenerateXml,
  onApply,
  xml,
  xmlError,
}) {
  const [copied, setCopied] = useState(false);
  const [bozza, setBozza] = useState(null); // null = sola lettura
  const [esito, setEsito] = useState(null); // { type: 'ok' | 'warn', msg }

  const applica = () => {
    try {
      // onApply lancia se il testo non è JSON, e ritorna i campi che il form non
      // sa gestire: vanno detti, altrimenti spariscono in silenzio al salvataggio.
      const persi = onApply(bozza);
      setBozza(null);
      setEsito(
        persi && persi.length
          ? { type: 'warn', msg: `Applicato, ma il form non gestisce ${persi.join(', ')}: quei campi non sono stati ripresi.` }
          : { type: 'ok', msg: 'Applicato al form.' }
      );
    } catch (e) {
      setEsito({ type: 'warn', msg: 'JSON non valido: ' + (e?.message || e) });
    }
  };

  const lines = useMemo(() => payload.split('\n'), [payload]);
  const errorLines = useMemo(
    () => new Set((validation.errors || []).map((e) => e.line).filter(Boolean)),
    [validation.errors]
  );
  const hasFixable = (validation.errors || []).some((e) => e.fixable);

  const status =
    validation.status === 'valid' ? 'ok' : validation.status === 'invalid' ? 'bad' : 'idle';

  async function copy() {
    try {
      await navigator.clipboard.writeText(payload);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch {
      /* clipboard non disponibile */
    }
  }

  function download() {
    const blob = new Blob([payload], { type: 'application/json' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'index.json';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(a.href);
  }

  return (
    <div className="code-pane">
      <div className="code-header">
        <span>JSON-LD</span>
        <span className="head-actions">
          {bozza === null ? (
            <button type="button" onClick={() => { setEsito(null); setBozza(payload); }}>
              <span className="material-symbols-outlined">edit</span> Modifica
            </button>
          ) : (
            <>
              <button type="button" onClick={applica}>
                <span className="material-symbols-outlined">check</span> Applica
              </button>
              <button type="button" onClick={() => { setBozza(null); setEsito(null); }}>
                Annulla
              </button>
            </>
          )}
        {status === 'ok' && (
          <span className="status-indicator material-symbols-outlined" style={{ color: 'var(--success)' }}>
            check_circle
          </span>
        )}
        {status === 'bad' && (
          <span className="status-indicator material-symbols-outlined" style={{ color: 'var(--danger)' }}>
            error
          </span>
        )}
        {validation.status === 'unreachable' && (
          <span className="status-indicator material-symbols-outlined" style={{ color: 'var(--warning)' }}>
            cloud_off
          </span>
        )}
        </span>
      </div>

      {esito && (
        <p className={'code-esito place-' + esito.type}>
          {esito.type === 'ok' ? '✓' : '⚠️'} {esito.msg}
        </p>
      )}

      {bozza === null ? (
        <div className="code-area code-font">
          <div className="line-numbers">
            {lines.map((_, i) => (
              <span key={i} className={errorLines.has(i + 1) ? 'line-error' : undefined}>
                {i + 1}
              </span>
            ))}
          </div>
          <pre className="code-content">
            {lines.map((line, i) => (
              <span key={i} className={errorLines.has(i + 1) ? 'line-error' : undefined}>
                {line + '\n'}
              </span>
            ))}
          </pre>
        </div>
      ) : (
        // In scrittura il gutter sparisce: tenere i numeri di riga allineati a un
        // testo che cambia (e scorre) costa più di quanto valga.
        <textarea
          className="code-area code-edit code-font"
          value={bozza}
          spellCheck={false}
          autoComplete="off"
          onChange={(e) => setBozza(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === 'Escape') { setBozza(null); setEsito(null); }
            if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) applica();
          }}
        />
      )}

      {validation.status === 'invalid' && (
        <div className="debug-panel">
          <h4>
            ⚠️ {validation.errors.length} problema/i rilevato/i
            <span className="debug-actions">
              {hasFixable && (
                <button type="button" onClick={onFix}>
                  Correggi XHTML
                </button>
              )}
              <button type="button" onClick={onRevalidate}>
                Rivalida Ora
              </button>
            </span>
          </h4>
          <ul>
            {validation.errors.map((e, i) => (
              <li key={i}>
                <input type="checkbox" />
                <span dangerouslySetInnerHTML={{ __html: e.message }} />
              </li>
            ))}
          </ul>
        </div>
      )}

      {validation.status === 'unreachable' && (
        <div className="debug-panel" style={{ color: 'var(--warning)', borderTopColor: 'var(--warning)' }}>
          <h4>
            Validatore PHP non raggiungibile
            <span className="debug-actions">
              <button type="button" onClick={onRevalidate}>
                Riprova
              </button>
            </span>
          </h4>
          <p style={{ margin: 0 }}>
            Avvia <code>php -S localhost:8080</code> nella cartella <code>json-xml/</code>.
          </p>
        </div>
      )}

      <div className="actions-bar">
        <button type="button" onClick={copy}>
          <span className="material-symbols-outlined">{copied ? 'check' : 'content_copy'}</span>
          {copied ? 'Copiato!' : 'Copia'}
        </button>
        <button type="button" onClick={download}>
          <span className="material-symbols-outlined">download</span> Scarica
        </button>
        <button type="button" onClick={onGenerateXml}>
          <span className="material-symbols-outlined">swap_horizontal_circle</span> Genera XML
        </button>
      </div>

      {xmlError && <p className="err">{xmlError}</p>}
      {xml && <pre className="output">{xml}</pre>}
    </div>
  );
}
