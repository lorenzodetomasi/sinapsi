# Struttura dei contenuti (meetoo / ws-custom)

Definizione dei path e delle convenzioni per i contenuti gestiti dall'editor.
I dati reali vivono in `ws-custom/contents/` (gitignored: contengono dati personali).

## Albero dei path

```
contents/{tenant}/{locale}/{collection}/{slug}/
```

- **tenant**: es. `meetoo`
- **locale**: es. `it_IT`
- **collection**: `events` | `organizations` | `places` | `lists` (per lingua); `users` | `persons` (per sito)
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

## Luoghi, organizzazioni, attività (decisione del 27 settembre 2026)

**Una cosa reale = un file solo**, e la cartella dice che cos'è:

| Cartella | Che cos'è | Esempi |
|---|---|---|
| `places/{CAP}/{slug}` | ci si può andare: un luogo pubblico, una sede, un'attività con una sede sola (LocalBusiness: è Organization e Place insieme) | spiaggia, parco, statua, teatro, libreria |
| `places/{zona}` | una zona dell'albero (City, AdministrativeArea) e i percorsi (Lungomare, Lungotevere) | `places/lido-di-ostia`, `places/lido-di-ostia/lungomare` |
| `organizations/{slug}` | un soggetto senza sede propria, o con **più sedi**, o che si è trasferito (vedi sotto) | associazione, compagnia, rete, catena |
| `lists/…` | le liste: raccolte di cose, non posti | BookCrossing, Libri e letture, fasce d'età |

**L'`@id` di una sede contiene il CAP e deve dire il vero.**
- Lo slug è il **nome**, neutro (`savethechildren`). Solo se nello stesso CAP c'è già una
  sede con quel nome si aggiunge un distintivo **stabile**: il nome della sede
  (`savethechildren-punto_luce`) o la zona (`baubeach-ostia_ponente`), **mai la via**.
- **Trasloco nello stesso CAP**: si aggiorna l'indirizzo; il precedente va nello storico
  interno con le date. L'`@id` non cambia.
- **Trasloco in un altro CAP**: si aggiorna l'indirizzo, lo storico si allunga e l'`@id` si
  **rinomina** (`places/IT00121/x` → `places/IT00122/x`); l'editor aggiorna ogni file che
  la nomina (eventi, liste, organizzazioni, sedi, gestori). Il vecchio `@id` resta nel file
  (`meetoo:formerIds`) e il suo indirizzo pubblico **rimanda** (301) al nuovo.
- Lo storico degli indirizzi (`meetoo:addressHistory`: indirizzo, dal, al, Google Place ID)
  serve alle pagine degli eventi passati, che mostrano l'indirizzo **valido in quella data**.

**Continuità: si promuove quando serve.** Finché un'attività ha una sede sola è un file solo
in `places/` (listini, contatti, gestori, logo stanno lì). Alla seconda sede nasce il
soggetto in `organizations/`: listini, contatti, gestori ed eventi organizzati passano a
lui, e ogni sede è un luogo che dice di chi è (`parentOrganization`). Un evento dice chi lo
organizza (`organizer`) e in quale sede si tiene (`location`).

**Ibridi**: se sono due cose (un'associazione che gestisce un parco pubblico) diventano
un'organizzazione e un luogo collegati; se sono una cosa sola (un bar, un teatro privato)
restano un LocalBusiness unico.

**Sede chiusa definitivamente**: la pagina resta (gli eventi passati ci rimandano) con lo
stato in evidenza; esce da elenchi, mappa e scelte dell'editor per i nuovi eventi.

**Gli eventi non si rinominano.** Quando una sede cambia CAP, i suoi eventi (anche
futuri) tengono il loro `@id`: dice quando e dove erano stati programmati, i link restano
validi, e la pagina mostra comunque l'indirizzo giusto perché lo legge dalla sede (e, per
gli eventi passati, dallo storico: l'indirizzo valido in quella data).

**Google Place ID** (`meetoo:google_place_id`, uno per indirizzo dello storico): chiave per
riconoscere una sede (niente doppioni), fonte dello stato (chiusa, trasferita) e dei dati
(orari, telefono, sito, voto). Si interroga **a mano dalla Manutenzione**, e ogni
differenza si **approva o rifiuta** in un confronto, come per le modifiche manuali.

## Utenti (decisione del 28 settembre 2026)

- **Il `sub` di Google è lo stesso ovunque**: identifica l'account, non il sito (Google
  non dà identificativi diversi per applicazione). La stessa persona arriva con lo stesso
  `sub` su isotype.org e su meetoo.it. Cambiano la **sessione** (i cookie sono di un
  dominio: si accede su ciascun sito) e i **dati**.
- **Ogni sito ha i suoi utenti, al livello del SITO, non della lingua**:
  `contents/{sito}/users/` e i loro profili pubblici `contents/{sito}/persons/` (Meetoo e
  isotype allo stesso modo). Un utente è una persona, non una traduzione: la lingua che
  preferisce sta nella sua scheda. Una persona può stare in due siti con ruoli e accessi
  diversi. Il percorso lo decide un posto solo (`ws_users_dir`, `ws_persons_dir`,
  `ws_content_users_abspath`); un server non ancora migrato li tiene ancora in
  `{sito}/{lingua}/users/`, e vengono letti lì. I riferimenti restano `users/{sub}`.
- **I dati personali** (nome, email) non sono contenuto: stanno in `ws-admin/_private/`,
  sul server, fuori da git, e non viaggiano mai.
- **Ruoli.** L'**admin** gestisce i contenuti di un sito (modifica tutto, manutenzione,
  cestino definitivo) e glielo assegna il sito, come ogni ruolo. Il **super-admin** gestisce
  il sistema (siti, ruoli, indici) e appartiene al **server**: si dichiara nel suo
  `ws-custom/ws-config.php`, per `sub` o email verificata
  (`define('WS_SUPER_ADMINS', [...])`). Dichiarata la lista, un «super-admin» scritto
  nel file utenti di un sito vale admin.
- **Copia di sviluppo.** Gli utenti di Meetoo (`users/`, `persons/`) non vanno mai sulla
  copia: ha i suoi. Le attività (`rsvp.json`, `likes.json`, `reviews.xml`) ci vanno, per
  avere gli stessi numeri; **verso meetoo.it non vanno mai** (nascono lì), e nemmeno gli
  indici (`_index/`), che ogni server si rifà da sé (`deploy/contents.sh`).

## Convenzione @id e riferimenti (NORMALIZZATA)

- **self-@id** di un'entità = **`{collection}/{slug}`**, cioè il suo percorso dalla radice
  del locale (es. `places/IT00122/lamanusa`, `organizations/clubdellibro-ostia`,
  `events/20260825T1845-IT00122-reading-party`). **Un'entità ha un nome solo**: lo stesso
  con cui la nominano i riferimenti e l'attributo `id` dell'XML.
- **riferimento** da un'entità a un'altra = **`{collection}/{slug}`**, relativo alla
  radice del *locale* (es. `places/IT00122-spiaggialamanusa`,
  `organizations/clubdellibro-ostia`).
- **XInclude** (nodo che è solo riferimento): l'href risale alla radice del locale →
  `href="../../{collection}/{slug}/index.xml"` (l'entità di partenza è due livelli sotto:
  `events/{slug}/`). *(Adeguare il convertitore: oggi usa `../{@id}`.)*

L'`@id` dell'evento è **`events/` + il percorso della cartella** (descrittivo incluso),
es. `events/20260723T1830-IT00122-reading_party`. Le cartelle esistenti prive del descrittivo
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
Riferimento e self-@id hanno la **stessa forma**: `events/{slug}`. Lo slug nudo resta accettato
in LETTURA (contenuti vecchi o scritti a mano: l'indice normalizza all'ultimo segmento), ma non
si scrive più; `check-refs` lo segnala e «Normalizza» lo ripara. L'editor emette già
questo formato (adapter `toEventRef`); lo script `ws-admin/events/migrate-refs.php` (dry-run
di default, `--apply` per scrivere) normalizza i contenuti esistenti: `@id` = `events/{percorso}`,
`superEvent`/`subEvent` → `events/{slug}`.

**Organizer come default di serie (ereditarietà).** Un'occorrenza (con `superEvent`) senza
`organizer` proprio **eredita** quelli della serie — coerente con «default di serie con
override» — così resta attribuita anche se il suo `organizer` è vuoto o **non risolto** (es.
`xi:include` non espanso nel JSON). Se ha un `organizer` proprio, quello **sostituisce**
(non si somma). *Nota:* il JSON canonico non dovrebbe contenere `xi:include` non risolti; i
riferimenti a organizer vanno salvati come `"organizations/{slug}"` o `{"@id":"…"}`. Il
convertitore `WsxToJson` è comunque **tollerante**: riconosce gli `xi:include` anche col
prefisso non legato e con href verso `…/index.xml` li risolve in `{@id}`; gli include locali
(rsvp/reviews) o vuoti li ignora — non emette più il placeholder `"xi:include": ""`.

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

## Pagine in bozza (decisione del 27 settembre 2026)

Una pagina non ancora pubblicata ma già sul sito si dichiara così nel suo `index.json`:

```json
"creativeWorkStatus": "Draft"
```

**Una bozza è sempre `noindex`**, qualunque cosa dica il suo `robots`: la mappa la
scrive così (`ws_sitemap_draft_robots()`), e da lì la leggono il `<meta name="robots">`
della pagina e `sitemap.xml`. Il `robots` della pagina può quindi dire già come sarà
una volta pubblicata (`index, follow`).

- `creativeWorkStatus` è schema.org (su ogni `CreativeWork`, quindi su ogni `WebPage`).
- La pagina **risponde** al suo indirizzo e mostra in cima l'avviso «Bozza»
  (`template-parts/draft-notice.php`, incluso da `page.php` e dal template dei glossari).
- **Compare negli elenchi** delle pagine sopra di lei (`section-children.php`), con
  l'etichetta «Bozza». Senza lo stato, `noindex` vuol dire quello che ha sempre voluto
  dire: una pagina che nessuno deve incontrare, e dagli elenchi resta fuori.
- **Non arriva ai motori**: niente `sitemap.xml` (esclude già le `noindex`), niente
  `hasPart` nel JSON-LD della pagina madre.
- La mappa (`ws_sitemap.wsx`) porta lo stato. **Per pubblicarla**: togliere
  `creativeWorkStatus` (o metterlo a `Published`) e rigenerare le mappe.

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

- `@id` di un'occorrenza (self) = `events/{occorrenzaSlug}`; **riferimento** dalla
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
