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

## Archiviazione e indice eventi

**Principio: separare *storage* da *archiviazione*.**

- **Storage** (dove vive il JSON) è **stabile**: schema c, `@id = percorso`, **nessuno
  spostamento**. Spostare un evento ne cambia l'`@id` e rompe i riferimenti
  (`superEvent`/`subEvent`, `organizer`, link condivisi, indice).
- **Archiviazione** ("passato vs prossimo") è una **vista per data**, derivata confrontando
  `endDate` (o `startDate`) con *adesso*. Lo slug canonico
  `{AAAAMMGG}T{hhmm}-{cap}-{descrittivo}` contiene la data → è auto-archiviante.

**Dove stanno gli eventi — tutti sotto `events/`:**

| Cosa | Percorso | Slug |
|---|---|---|
| Evento singolo | `events/{slug}/` | date-encoded `{AAAAMMGG}T{hhmm}-{cap}-{descrittivo}` |
| Collection (`EventSeries`) | `events/{serieSlug}/` | **descrittivo** (es. `reading-party`): una serie copre un arco di tempo |
| Occorrenza (in programma) | `events/{serieSlug}/{occSlug}/` | date-encoded |
| Occorrenza (passata, *opzionale*) | `events/{serieSlug}/archive/{occSlug}/` | invariato |

- Le collection stanno in `events/`, **non** sotto `organizations/`: `organizer` è un
  **riferimento** (multiplo), non un contenitore. `organizations/{org}/` contiene **solo
  l'anagrafica** dell'Organization; i suoi eventi/collezioni sono collegati via `organizer`
  + indice per-organizer.
- **Non** usare `organizations/{org}/archive/` (rompe il modello multi-organizer e la
  convenzione dei riferimenti) né `events/archive/` globale (perde il raggruppamento per
  serie e crea collisioni di slug).
- Lo spostamento fisico in `events/{serie}/archive/{occ}/` è **solo un'ottimizzazione** per
  serie con moltissime occorrenze; se usato, tieni il **riferimento logico**
  `events/{serie}/{occSlug}` (senza `archive/`) così i riferimenti restano stabili. Slug
  invariato: cambia solo la cartella genitore.

**Indice** (`events/_index/`, rigenerato a ogni salvataggio e da `rebuild-index.php` / dal
bottone *Rebuild index* dell'editor, admin). È splittato **prossimi/archivio** per non far
scaricare tutto l'archivio a chi mostra solo i prossimi:

```
events/_index/
  events.json / events.archive.json               # globale (serie+singoli prossimi / singoli passati)
  by-organizer/{key}.json / {key}.archive.json    # per organizzatore
  by-collection/{key}.json / {key}.archive.json   # per collection (occorrenze)
```

- **Bucket**: una **serie** sta sempre nel file principale; un **singolo** va in
  `.archive.json` se `endDate` (o `startDate`) è passata. `{key}` = ultimo segmento
  sanitizzato del riferimento (organizer @id / collection path).
- Le pagine (`organizer.html`, `collection.html`) caricano il file principale subito e
  l'archivio **solo su richiesta** ("Mostra archivio passato"); ri-splittano comunque per
  data ciò che caricano, quindi il taglio al confine è solo cosmetico e si riallinea al
  prossimo rebuild.
- Voce compatta per evento: `path, kind (series|single), collection, name, startDate,
  endDate, organizer, location, cap, status, image, dateModified`.

**Sincronizzazione.** L'indice è una **proiezione** dei JSON su disco (la verità sono i
file). Per non andare in deriva:

- **Rebuild completo a ogni salvataggio** (`save-event.php`) e con il bottone *Rebuild index*
  o `rebuild-index.php` (CLI): nessun aggiornamento incrementale parziale.
- **Rebuild schedulato (cron)** per le modifiche fatte **fuori dall'editor** (file a mano,
  git/deploy) e per ri-splittare passato/futuro con l'avanzare del tempo. Esempio (adegua i
  percorsi al server):

  ```cron
  0 3 * * *  /usr/bin/php /var/www/isotype.org/sinapsi/ws-admin/events/rebuild-index.php >/dev/null 2>&1
  ```

**Riferimenti fra entità = `{collection}/{slug}`** (es.
`"superEvent": "events/clubdellibro-ostia-reading_party"`, `"location": "places/IT00122-…"`).
Lo **slug nudo** è la forma *self-@id*, **non** un riferimento. L'indice normalizza comunque
all'ultimo segmento, ma la forma canonica da salvare è `events/{slug}`.

**Organizer come default di serie (ereditarietà).** Un'occorrenza (con `superEvent`) senza
`organizer` proprio **eredita** quelli della serie — coerente con «default di serie con
override» — così resta attribuita anche se il suo `organizer` è vuoto o **non risolto** (es.
`xi:include` non espanso nel JSON). Se ha un `organizer` proprio, quello **sostituisce**
(non si somma). *Nota:* il JSON canonico non dovrebbe contenere `xi:include` non risolti; i
riferimenti a organizer vanno salvati come `"organizations/{slug}"` o `{"@id":"…"}`.

**Membership autorevole = `superEvent`.** L'appartenenza di un evento a una collection si
ricava dalle occorrenze (`superEvent`); il `subEvent` della serie è **derivabile** dall'indice
e non va mantenuto a mano (se presente, è un elenco denormalizzato).

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

---

# Tipi di evento: EventSingle ed EventSeries

Il form edita **un documento evento alla volta**. Il tipo del documento è dato da
`meetoo:@type` e determina, con regole condizionali, quali campi mostrare.

| `meetoo:@type` | `@type` schema.org (radice) | Natura |
|----------------|-----------------------------|--------|
| `meetoo:EventSingle` | `Event` (+ sottotipo, es. `LiteraryEvent`) | Un evento con un **programma** interno |
| `meetoo:EventSeries` | `EventSeries` (+ sottotipo) | Un contenitore con **occorrenze** (eventi figli) e una **ricorrenza** |

- `meetoo:@type` è il **discriminante unico**: sceglierlo imposta anche il `@type`
  schema.org di radice (`Event` ⇄ `EventSeries`). Non si editano separatamente.
- Il modello è **ricorsivo per composizione, non per annidamento**: un'occorrenza di
  una serie è essa stessa un evento (Single o Series) con **JSON-LD proprio** in una
  cartella figlia. La serie la referenzia; non la contiene inline. Un festival è così
  una Series le cui occorrenze annuali sono a loro volta Series (con giornate) o Single.

## Campi condivisi (Single e Series)

`@id`, `@type`, `additionalType`, `keywords`, `name`, `description`, `image`, `logo`,
`typicalAgeRange`, `eventAttendanceMode`, `isAccessibleForFree`, `offers`,
`aggregateRating`, `organizer` (riferimenti), `meetoo` (`@type`, `macrocategory`).

## Specifico di EventSingle

- **`startDate` / `endDate`** = data-ora dell'evento.
- **`subEvent`** = **programma interno**: array di sotto-eventi *inline*
  (`name`, `description`, `startDate`, `endDate`) — es. Accoglienza, Lettura, Chiacchierata.
- **`location`** = luogo proprio dell'evento.

## Specifico di EventSeries

- **`startDate`** = inizio della serie (prima edizione, es. Sanremo «dal 1951»);
  **`endDate`** opzionale (serie conclusa) o assente (in corso). La sezione *Quando*
  resta ma cambia significato rispetto al singolo.
- **`eventSchedule`** (schema.org `Schedule`) = **ricorrenza**, modellata sull'editor
  di Google Calendar → mappata sulle proprietà `Schedule`:

  | UI (stile Google Calendar) | schema.org `Schedule` |
  |---|---|
  | Frequenza: Giornaliera/Settimanale/Mensile/Annuale | `repeatFrequency` (`P1D`/`P1W`/`P1M`/`P1Y`) |
  | «Ogni N …» (intervallo) | `repeatFrequency` = `P{N}{unità}` (es. ogni 2 settimane → `P2W`) |
  | Giorni della settimana (settimanale) | `byDay` (`MO,WE,FR`) |
  | Mensile: per giorno del mese / per n-esimo giorno | `byMonthDay` / `byDay` con ordinale |
  | Fine: Mai / In data / Dopo N volte | assente / `endDate` / `repeatCount` |
  | Date escluse | `exceptDate` |
  | Fuso orario | `scheduleTimezone` |

- **`subEvent`** = **occorrenze**: array di **riferimenti `@id`** (+ `name`) agli eventi
  figli. Ogni figlio ha JSON-LD proprio in una cartella. Editabile con:
  - **ricerca** tra eventi esistenti (richiede endpoint di elenco eventi);
  - **quick-create** minimale (nome + date), che genera un figlio con `superEvent`
    verso il genitore; il figlio si edita poi integralmente caricando il suo JSON.
- **`location` e capienze** = **default di serie con override**: impostati sulla serie
  valgono per tutte le occorrenze; ogni occorrenza può sovrascriverli (es. Sanremo
  sempre stesso teatro, ma un'edizione altrove). *(L'ereditarietà è applicata in fase di
  pubblicazione/rendering, non duplicata nel JSON del figlio salvo override esplicito.)*

## Relazioni genitore ↔ figlio

- Serie → occorrenze: **`subEvent`** (riferimenti `@id`).
- Occorrenza → serie: **`superEvent`** (riferimento `@id`).
- Il form mostra i **pulsanti** per aprire/editare il genitore e i figli, caricando di
  volta in volta il JSON del documento scelto (un documento alla volta).

## Path e `@id` delle occorrenze

Le occorrenze vivono **sotto** la cartella della serie:

```
events/{serieSlug}/                     ← la serie (index.json)
events/{serieSlug}/{occorrenzaSlug}/    ← occorrenza in programma
events/{serieSlug}/archive/{occorrenzaSlug}/  ← occorrenza passata
```

- `@id` di un'occorrenza (self) = slug nudo (`{occorrenzaSlug}`); **riferimento** dalla
  serie o verso la serie = path relativo alla radice del locale
  (`events/{serieSlug}/{occorrenzaSlug}`, `events/{serieSlug}`), coerente con la regola
  generale degli `@id`.
- Ne consegue che l'href XInclude **non è sempre `../../`**: dipende dalla posizione
  relativa tra sorgente e destinazione (una serie che include una sua occorrenza scende
  di un livello; un'occorrenza che punta alla serie sale di uno). *(Il convertitore deve
  calcolare l'href relativo in generale, non assumere `../../`.)*

## Regole condizionali (uischema, guidate da `meetoo:@type`)

| Sezione / campo | EventSingle | EventSeries |
|---|---|---|
| Quando (`startDate`/`endDate`) | data-ora evento | arco della serie |
| Ricorrenza (`eventSchedule`) | nascosta | **mostrata** |
| `subEvent` | Programma (inline) | **Occorrenze** (link `@id` + quick-create) |
| Luogo / capienze | proprie | default con override |

Meccanismo: **schema unico** (superset) + regole `SHOW`/`HIDE` dell'uischema su
`meetoo:@type` (stesso pattern già usato per *Offerta*); due controlli distinti sullo
stesso `subEvent`, uno per tipo. I campi nascosti da una condizione **vengono esclusi**
dal JSON-LD generato (una serie non emette il programma inline né viceversa).

## Dipendenze d'implementazione (da costruire a parte)

1. **Endpoint elenco eventi** (PHP): elenca gli eventi esistenti per la ricerca delle
   occorrenze; utile anche ai selettori `location`/`organizer`.
2. **Load/Save JSON** dal form: la navigazione genitore↔figli e la persistenza dei figli
   richiedono di caricare un `index.json` nel form e di salvarlo nella cartella.
3. **Convertitore**: href XInclude relativo generale (vedi sopra).
