import { useEffect, useMemo, useRef, useState } from 'react';
import { JsonForms } from '@jsonforms/react';
import { vanillaRenderers, vanillaCells } from '@jsonforms/vanilla-renderers';
import { schema, uischema } from './schema.js';
import { fromJsonLd, toJsonLd, sampleJsonLd } from './jsonld-adapter.js';
import XhtmlRichTextRenderer, { xhtmlControlTester } from './XhtmlRichTextRenderer.jsx';
import LabeledEnumRenderer, { labeledEnumTester } from './LabeledEnumRenderer.jsx';
import ImageUploadRenderer, { imageUploadTester } from './ImageUploadRenderer.jsx';

const renderers = [
  ...vanillaRenderers,
  { tester: xhtmlControlTester, renderer: XhtmlRichTextRenderer },
  { tester: labeledEnumTester, renderer: LabeledEnumRenderer },
  { tester: imageUploadTester, renderer: ImageUploadRenderer },
];

// Chiamata al convertitore/validatore PHP tramite il proxy /api di Vite.
async function api(action, fields = {}) {
  const body = new URLSearchParams({ action, ...fields });
  const res = await fetch('/api', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: body.toString(),
  });
  return res.json();
}

export default function App() {
  const [data, setData] = useState(() => fromJsonLd(sampleJsonLd));
  const [validation, setValidation] = useState({ status: 'idle', errors: [] });
  const [xml, setXml] = useState('');
  const [xmlError, setXmlError] = useState('');
  const seq = useRef(0);

  const jsonld = useMemo(() => toJsonLd(data), [data]);
  const payload = useMemo(() => JSON.stringify(jsonld, null, 2), [jsonld]);

  // Validazione live (debounced) sul JSON-LD generato, riusando validate_json.
  useEffect(() => {
    const id = ++seq.current;
    const t = setTimeout(async () => {
      try {
        const out = await api('validate_json', { payload });
        if (id !== seq.current) return; // scarta risposte obsolete
        setValidation(out.valid ? { status: 'valid', errors: [] } : { status: 'invalid', errors: out.errors || [] });
      } catch {
        if (id === seq.current) setValidation({ status: 'unreachable', errors: [] });
      }
    }, 500);
    return () => clearTimeout(t);
  }, [payload]);

  // Applica la correzione XHTML suggerita (fix_xhtml) e re-idrata il form.
  async function fixXhtml() {
    try {
      const out = await api('fix_xhtml', { type: 'json', payload });
      if (out.success) setData(fromJsonLd(JSON.parse(out.result)));
    } catch (e) {
      console.error(e);
    }
  }

  async function generateXml() {
    setXmlError('');
    setXml('');
    try {
      const out = await api('to_xml', { payload });
      out.success ? setXml(out.result) : setXmlError(out.error || 'Conversione fallita');
    } catch {
      setXmlError('Convertitore PHP non raggiungibile su :8080 (avvia `php -S localhost:8080` in json-xml/).');
    }
  }

  const hasFixable = validation.errors.some((e) => e.fixable);

  return (
    <div className="layout">
      <section className="pane">
        <h2>Form (JSON Forms, schema-driven)</h2>
        <JsonForms
          schema={schema}
          uischema={uischema}
          data={data}
          renderers={renderers}
          cells={vanillaCells}
          onChange={({ data }) => setData(data)}
        />
      </section>

      <section className="pane">
        <h2>Validazione live <small>(validate_json)</small></h2>
        <ValidationBanner validation={validation} onFix={fixXhtml} hasFixable={hasFixable} />

        <h2>JSON-LD generato <small>(via adapter)</small></h2>
        <pre className="output">{payload}</pre>

        <button className="primary" onClick={generateXml}>Genera XML (CDATA) →</button>
        {xmlError && <p className="err">{xmlError}</p>}
        {xml && (
          <>
            <h2>XML (dal convertitore PHP)</h2>
            <pre className="output">{xml}</pre>
          </>
        )}
      </section>
    </div>
  );
}

function ValidationBanner({ validation, onFix, hasFixable }) {
  const { status, errors } = validation;
  if (status === 'valid') return <div className="banner ok">✓ JSON-LD valido</div>;
  if (status === 'unreachable')
    return (
      <div className="banner warn">
        Validatore PHP non raggiungibile — avvia <code>php -S localhost:8080</code> in <code>json-xml/</code>.
      </div>
    );
  if (status === 'invalid')
    return (
      <div className="banner bad">
        <div className="banner-head">
          ✗ {errors.length} problema/i rilevato/i
          {hasFixable && <button className="fix" onClick={onFix}>Correggi XHTML</button>}
        </div>
        <ul>
          {errors.map((e, i) => (
            <li key={i} dangerouslySetInnerHTML={{ __html: e.message }} />
          ))}
        </ul>
      </div>
    );
  return <div className="banner">…</div>;
}
