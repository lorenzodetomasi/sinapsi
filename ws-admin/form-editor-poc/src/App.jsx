import { useMemo, useState } from 'react';
import { JsonForms } from '@jsonforms/react';
import { vanillaRenderers, vanillaCells } from '@jsonforms/vanilla-renderers';
import { schema, uischema } from './schema.js';
import { fromJsonLd, toJsonLd, sampleJsonLd } from './jsonld-adapter.js';
import XhtmlRichTextRenderer, { xhtmlControlTester } from './XhtmlRichTextRenderer.jsx';

const renderers = [
  ...vanillaRenderers,
  { tester: xhtmlControlTester, renderer: XhtmlRichTextRenderer },
];

export default function App() {
  const [data, setData] = useState(() => fromJsonLd(sampleJsonLd));
  const [xml, setXml] = useState('');
  const [xmlError, setXmlError] = useState('');

  const jsonld = useMemo(() => toJsonLd(data), [data]);

  // Chiude il ciclo: manda il JSON-LD al convertitore PHP (via proxy /api) e
  // mostra l'XML con CDATA. Richiede: php -S localhost:8080 nella cartella json-xml.
  async function generateXml() {
    setXmlError('');
    setXml('');
    try {
      const body = new URLSearchParams();
      body.append('action', 'to_xml');
      body.append('payload', JSON.stringify(jsonld, null, 2));
      const res = await fetch('/api', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString(),
      });
      const out = await res.json();
      out.success ? setXml(out.result) : setXmlError(out.error || 'Conversione fallita');
    } catch (e) {
      setXmlError('Convertitore PHP non raggiungibile su :8080 (avvia `php -S localhost:8080` in json-xml/).');
    }
  }

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
        <h2>JSON-LD generato <small>(via adapter)</small></h2>
        <pre className="output">{JSON.stringify(jsonld, null, 2)}</pre>
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
