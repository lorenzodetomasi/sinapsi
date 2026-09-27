# WS Glossary

A glossary is one JSON-LD file, `schema.org/DefinedTermSet`. Everything a
reader sees - the interactive page, the standalone HTML, the EPUB - is drawn
from it; nothing is written by hand in any of them.

| File | What it is |
|---|---|
| `glossary.js` | the app: reads the JSON-LD, filters its markup, draws, handles search/filters/paths |
| `glossary.css` | the structure; every colour and font is a `--g-*` token |
| `skins/*.css` | a look: redefines the tokens, may add ornaments (`isotype`, `sahaja`) |
| `viewer.html` | opens any glossary (`?src=` or a local file), picks a skin, exports the standalone HTML |
| `lib.php` | the PHP side: catalogue, markup whitelist, model, one entry as XHTML (twins of the JS) |
| `epub.php`, `epub.css` | the EPUB 3 books: the whole glossary and one per selection |
| `../glossary.php` | the isotype.org page template (`template=glossary`, `&skin=` to choose the look) |
| `ws-admin/glossaries/` | the list of a site's glossaries, the review of proposals, the EPUB build |
| `ws-custom/languages/glossary-<locale>.po` | every interface string (msgids in English) |

## The files of a glossary

```
projects/glossaries/sahaja-yoga/
  index.json                   the page: template=glossary, mainEntity -> #glossario
  glossary.jsonld              the glossary: the only source
  proposals/<name>.jsonld      a new version waiting for review (-<name> once applied)
  history/                     the versions an apply replaced, and log.jsonl
  sahaja-yoga.epub             derived: the whole glossary
  sahaja-yoga-essenziale.epub  derived: the selection #essenziale
```

The EPUBs are named after the folder, not the title, and are rebuilt by every
apply (and by "Build the EPUBs" in the module): made from the glossary, they
must never lag behind it. The page offers them for download by adding them
to the data it embeds, as `encoding`; the glossary file is not touched.

## Where the data comes from

`<div data-glossary …>` is the root. First found wins:

1. `data-source="#id"`: an inline `<script type="application/ld+json" id="id">`;
2. `data-src="url"`;
3. the page's `?src=url`, only when the root declares no source of its own. So
   a page that embeds its glossary can never be made to show someone else's.

Any http(s) address is accepted (the server must allow CORS). The content is
untrusted: names are text; `description`, `text` and property values may carry
only `em strong i b sup sub q cite abbr dfn small mark s u code kbd bdi br`,
plus `p ul ol li blockquote` in front and back matter, without attributes other
than `lang` (and `title` on `abbr`/`dfn`). Anything else is unwrapped, and
`script`, `style`, `iframe`, `svg`, forms… are dropped with their content.

## The model

```jsonc
{
  "@context": ["https://schema.org", {
    "skos": "http://www.w3.org/2004/02/skos/core#",
    "ws": "https://localbiz.it/ws#"
  }],
  "@type": "DefinedTermSet",
  "@id": "#glossario",
  "name": "Glossario di Sahaja Yoga",
  "alternateName": "Glossario esteso",          // the label of the whole, next to the selections
  "description": "…",                           // subtitle
  "disambiguatingDescription": "…",             // the note under it
  "inLanguage": "it", "version": "2", "datePublished": "…", "dateModified": "…",
  "isPartOf": { "@type": "CreativeWorkSeries", "name": "Quaderno di Felicia", "alternativeHeadline": "…" },
  "author": { "@type": "Person", "name": "…" },
  "copyrightHolder": { … }, "copyrightYear": 2026, "creditText": "…",
  "license": "https://creativecommons.org/licenses/by-nc-sa/4.0/",
  "encoding": [{ "@type": "MediaObject", "encodingFormat": "application/epub+zip", "contentUrl": "…epub" }],

  "hasPart": [
    // Front or back matter: any CreativeWork with a text.
    { "@type": "CreativeWork", "@id": "#premessa", "name": "Premessa", "ws:placement": "front", "text": "<p>…</p>" },

    // A selection: a reading inside the whole (the "essential" 95 of ~280).
    { "@type": "DefinedTermSet", "@id": "#essenziale", "name": "Glossario essenziale", "ws:role": "selection" },

    // A category: a filter and a colour.
    { "@type": "DefinedTermSet", "@id": "#ambito-sistema", "name": "Sistema sottile", "ws:role": "category", "ws:color": "#463C79" },

    // A reading path: an ordered list of entries.
    { "@type": "ItemList", "@id": "#percorso-meditare-meglio", "name": "Per meditare meglio",
      "itemListElement": [{ "@id": "#attenzione" }, { "@id": "#bandhan" }] },

    // A table whose rows are entries; the cells are the entries' properties
    // named like the columns (the first column is the entry's name).
    { "@type": "Table", "@id": "#colori-dei-chakra", "name": "Colori dei chakra",
      "ws:columns": ["Centro", "Colore/simbolo indicativo", "Qualità principali", "Nota sahaja"],
      "ws:rows": [{ "@id": "#mooladhara" }] }
  ],

  "hasDefinedTerm": [{
    "@type": "DefinedTerm",
    "@id": "#agnya-chakra",                      // also the anchor: page#agnya-chakra
    "name": "Agnya chakra",
    "disambiguatingDescription": "sanscrito / sistema sottile",   // the author's label
    "description": "Centro sottile del perdono…",
    "skos:related": [{ "@id": "#perdono" }, { "name": "errori più gravi in Sahaja Yoga" }],
    "inDefinedTermSet": [{ "@id": "#essenziale" }, { "@id": "#ambito-sistema" }],
    "ws:additionalProperty": [{ "@type": "PropertyValue", "name": "Colore/simbolo indicativo", "value": "Luce bianca / chiarezza" }]
  }]
}
```

Choices, and why:

- **`skos:related` for cross-references.** schema.org has no "see also" for a
  `DefinedTerm`; SKOS is the vocabulary of thesauri and has it. A reference to
  something that is not an entry (a theme, a document) stays a node with only
  a `name`: the order the author gave is kept, and the page shows it as text.
- **Membership is written on the term** (`inDefinedTermSet`), so a change of
  category or of selection shows in the diff as a change of that entry. The
  reader also accepts it the other way round (the subset's `hasDefinedTerm`).
- **`ws:additionalProperty`, not `additionalProperty`.** The schema.org
  property is not defined on `DefinedTerm` and the validator reports it; the
  `ws:` one says the same thing without breaking conformance.
- **`ws:role`, `ws:placement`, `ws:color`, `ws:columns`, `ws:rows`** are the
  CMS's own words (`ws:` is `https://localbiz.it/ws#`, the prefix every site
  uses). A glossary on another site would want them too, hence not a site prefix.

## Interface strings

`glossary.js` calls `t()`, `tn()` (plurals) and `tx()` (with a context) on
English msgids. The catalogue is `window.WSGlossaryL10n`: the viewer parses
`glossary-<locale>.po` itself, the PHP template and the standalone file embed
it. After changing the `.po`: `msgfmt -c -o glossary-it_IT.mo glossary-it_IT.po`.
