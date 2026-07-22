# Struttura dei contenuti (meetoo / ws-custom)

Definizione dei path e delle convenzioni per i contenuti gestiti dall'editor.
I dati reali vivono in `ws-custom/contents/` (gitignored: contengono dati personali).

## Albero dei path

```
contents/{tenant}/{locale}/{collection}/{slug}/
```

- **tenant**: es. `meetoo`
- **locale**: es. `it_IT`
- **collection**: `events` | `organizations` | `places` | `persons` | `users`
- **slug**: identificatore dell'entità (nome cartella)

### File per entità

| File | Ruolo |
|------|-------|
| `index.json` | Dato **canonico** (sorgente di verità, editato dal form) |
| `index.xml` | Conversione XML (generata dal convertitore json-xml) |
| `index.wsx.xml` | Variante WSX (opzionale) |
| `media-sources/` | File **originali** caricati (foto grezze) |
| `media/` | Derivati **elaborati/pubblicati** (es. `savethedate.jpg`, `logo.jpg`) |

### Eventi: singoli e serie

- Evento singolo: `events/{eventSlug}/`
- Serie/ricorrenza: `events/{seriesSlug}/` con:
  - `index.xml` della serie
  - `archive/{istanzaSlug}/` per le occorrenze passate
  - `reviews.xml` + `reviews/{reviewSlug}.xml` per le recensioni di un'istanza

Slug istanza evento: `{yyyymmdd}T{hhmm}-{codiceLuogo}-{descrittivo}`
(es. `20260723T1830-IT00122-reading_party`). Il descrittivo finale evita collisioni
tra eventi con stessa data/luogo.

## Convenzione @id e riferimenti (NORMALIZZATA)

- **self-@id** di un'entità = **slug nudo** (es. Place → `IT00122-spiaggialamanusa`,
  Organization → `clubdellibro-ostia`).
- **riferimento** da un'entità a un'altra = **`{collection}/{slug}`**, relativo alla
  radice del *locale* (es. `places/IT00122-spiaggialamanusa`,
  `organizations/clubdellibro-ostia`).
- **XInclude** (nodo che è solo riferimento): l'href risale alla radice del locale →
  `href="../../{collection}/{slug}/index.xml"` (l'entità di partenza è due livelli sotto:
  `events/{slug}/`). *(Adeguare il convertitore: oggi usa `../{@id}`.)*

L'`@id` dell'evento **coincide con lo slug della cartella** (descrittivo incluso),
es. `20260723T1830-IT00122-reading_party`. Le cartelle esistenti prive del descrittivo
vanno rinominate per allinearsi all'`@id`.

## image / logo

- Path **relativo** alla cartella dell'entità, che punta a `media-sources/{file}`
  per gli originali (es. `media-sources/cover.jpg`), oppure a `media/{file}` per i
  derivati pubblicati.
- In alternativa un **URL assoluto** (usato da alcune organizzazioni).
- **Upload** (form): il file va nella `media-sources/` dell'entità in editing; il
  campo salva `media-sources/{file}`.

## Tipi di dato

- Il JSON canonico usa **tipi reali** (numeri, booleani).
- L'XML è per natura **string-based**: `json → xml → json` restituisce stringhe, ma il
  check d'integrità normalizza (`100` ≡ `"100"`, `true` ≡ `"true"`), quindi i dati sono
  equivalenti. Nessuna perdita a livello di modello dati.

## @context meetoo

Valore: **`https://meetoo.eu#`** (con `#`). In JSON-LD l'espansione di un prefisso è
concatenazione: serve un separatore finale (`#` o `/`), altrimenti `meetoo:macrocategory`
si espande in `https://meetoo.eumacrocategory` (IRI malformato). Con `#` →
`https://meetoo.eu#macrocategory` (corretto).

> I contenuti reali in `ws-custom` usano `https://meetoo.eu` (senza `#`): da correggere
> aggiungendo il `#`.

## Editor: ambito attuale

- **Solo Eventi** per ora (form Place/Organization in una fase successiva).
- `location` = selettore di **un** Place esistente; `organizer` = selettore
  ripetibile di Organization esistenti. Si salva `@id` + `name` (riferimento).
- Google Places assiste la ricerca/compilazione del `name`; l'`@id` è generato come
  slug dal nome ed è **editabile**. La chiave Maps JS/Places sta in `.env.local`
  (non committata).
