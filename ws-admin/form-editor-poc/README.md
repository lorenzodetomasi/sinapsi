# Form Editor PoC — JSON Forms + JSON-LD

Proof-of-concept di un editor JSON-LD **schema-driven** basato su
[JSON Forms](https://jsonforms.io) (React + vanilla renderers). Dimostra
end-to-end i requisiti difficili individuati sul nostro `index.json`:

- **Fieldset ripetibili**: `organizer[]` (aggiungi/rimuovi righe) — array di oggetti.
- **Oggetto annidato**: `offers` come gruppo.
- **Tipi vari**: testo, numero, booleano, `date-time`, enum (`select` su vocabolario schema.org).
- **Rich-text XHTML**: `description` tramite un *renderer custom* (`format: "xhtml"`)
  che produce XHTML, agganciabile alla pipeline CDATA del convertitore.
- **Adapter JSON-LD**: le chiavi `@context` / `@type` (array) / `@id` e i namespace
  (`meetoo:…`) non stanno nel form ma vengono aggiunte/rimosse in modo deterministico
  in [`src/jsonld-adapter.js`](src/jsonld-adapter.js).

## Struttura

- `src/schema.js` — JSON Schema (dati) + UI Schema (layout), entrambi JSON.
- `src/jsonld-adapter.js` — mapping form ⇆ JSON-LD (`index.json`).
- `src/XhtmlRichTextRenderer.jsx` — renderer custom per il campo XHTML.
- `src/App.jsx` — form + anteprima JSON-LD live + bottone "Genera XML".

## Avvio

```bash
npm install
npm run dev            # http://localhost:5173
```

### Ciclo completo verso XML (opzionale)

Il bottone **"Genera XML (CDATA)"** invia il JSON-LD al convertitore PHP
(`../json-xml/index.php`) tramite il proxy `/api` di Vite. Per abilitarlo:

```bash
cd ../json-xml && php -S localhost:8080
```

Si ottiene l'XML con la `description` incapsulata in `<![CDATA[…]]>`.

## Note / prossimi passi

- Il renderer XHTML del PoC usa `contenteditable` + `execCommand` (minimale); in
  produzione si può sostituire con TipTap/ProseMirror o CKEditor5 mantenendo lo
  stesso contratto (stringa XHTML in `data`).
- La validazione/correzione XHTML stretta resta delegata alla pipeline PHP
  esistente (`validate_json` + `fix_xhtml`).
- `@id` è qui fisso: in produzione andrà generato/derivato dai dati del form.
