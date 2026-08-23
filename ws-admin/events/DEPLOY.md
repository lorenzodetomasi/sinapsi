# Deploy — Editor eventi, backend e pagine tema

Runbook per portare in produzione (isotype.org/**sinapsi**/) l'editor eventi, il backend PHP
e le pagine tema. `ROOT` = radice `sinapsi/` sul server; `SRC` = questa working copy.

> I contenuti (`ws-custom/contents/…`) NON si toccano qui: sono dati, gestiti a parte.
> Le pagine tema (`ws-custom/themes/…`) NON sono versionate → vanno caricate a mano.

## ⚠ Ordine di deploy: PRIMA le librerie, POI gli endpoint

`ws-admin/lib/*.php` va caricato **prima** (o insieme) a `ws-admin/events/*.php`: un endpoint
nuovo che chiama una funzione di una libreria vecchia muore con un fatale PHP, e il client riceve
HTML al posto del JSON ("Unexpected token '<'"). È successo con `event_index_sync`.
Gli endpoint ora si difendono (`display_errors` spento, fallback al rebuild, guardia sui require),
ma la cura è caricare le librerie insieme al resto:
`lib/{ws-auth,ws-users,events-index,events-migrate,events-normalize,events-check,events-trash}.php`.
Le pagine che leggono il **registro della manutenzione** ora si difendono da sole: se
`lib/ws-maintenance.php` manca, si perde la sezione Manutenzione (con un errore che lo dice) ma
**non l'accesso all'amministrazione** — prima il require fallito uccideva anche il login, che
rispondeva *«Il tuo account (, ruolo ?) non è abilitato»*.

## 1) Build dell'editor

```bash
cd ws-admin/events/edit-src && npm run build   # esce in ../edit (cartella servita)
```

## 2) Cosa caricare, e dove

| Gruppo | Da (SRC) | A (ROOT sul server) |
|---|---|---|
| **Backend – lib** | `ws-admin/lib/{ws-auth,ws-users,events-index,events-migrate,events-normalize,events-check}.php` | `ws-admin/lib/` |
| **Backend – lib** | `ws-admin/lib/ws-maintenance.php` (registro delle migrazioni: caricalo PRIMA delle due pagine che lo leggono) + `ws-admin/lib/ws-listrule.php` (liste con regola: la usano il salvataggio eventi E quello dei luoghi) | `ws-admin/lib/` |
| **Backend – events** | `ws-admin/events/{save-event,rsvp,rebuild-index,migrate-refs,normalize-content,check-refs}.php` | `ws-admin/events/` |
| **Gestione eventi** | `ws-admin/events/index.php` (nuova pagina) | `ws-admin/events/` |
| **Amministrazione** | `ws-admin/index.php` (hub) + `ws-admin/lib/events-trash.php` | `ws-admin/` e `ws-admin/lib/` |
| **Convertitore** | `ws-admin/json-xml/functions.php` | `ws-admin/json-xml/` |
| **Editor (dist)** | `ws-admin/events/edit/` (tutto: `index.html` + `assets/`) | `ws-admin/events/edit/` |
| **Temi** | `ws-custom/themes/meetoo/{index,organizer,collection,event,placecollection,waterfront}.html` + **`header.js`** + **`cards.js`** + **`meetoo.css`** + **`meetoo-tokens.css`** + **`places.css`** | `ws-custom/themes/meetoo/` |

**Hub «Amministrazione»** `ws-admin/index.php`: sezioni Eventi · **Luoghi e attività** · **Organizzazioni**
(ognuna con la sua card «Nuovo…») · Utenti · Strumenti; visibile solo da autenticati con ruolo redazionale.
La voce **Amministrazione** compare nel menu hamburger di TUTTE le pagine, ma solo per
**admin/super-admin** (`header.js`, `renderNav`). La card «Utenti e ruoli» è segnata *in arrivo*:
non esiste ancora uno strumento, i ruoli si cambiano a mano in `users/users.xml`.

**Cestino eventi** (`ws-admin/lib/events-trash.php` + azioni in `events/index.php`): «Cestina»
**sposta** la cartella da `events/<slug>` a **`_trash/events/<slug>`** — niente viene riscritto, i
media restano — e rigenera gli indici, così l'evento sparisce da liste e pagine pubbliche ma è
ripristinabile. Il manifest `_trash/events.json` ricorda percorso originale, data e autore.
Ripristino: rimette la cartella al suo posto (si ferma se il percorso è di nuovo occupato).
Permessi: cestinare/ripristinare come modificare (user/client/admin/super-admin); **eliminare
definitivamente e svuotare il cestino solo admin/super-admin**, con conferma esplicita; la
cancellazione ricorsiva è confinata a `_trash` (guardia su realpath).
⚠ `_trash/` sta dentro i contenuti serviti: un evento cestinato **non è più indicizzato ma resta
raggiungibile via URL diretto** finché non si svuota il cestino. Se serve che sparisca subito dal
web, va spostata la cartella `_trash` fuori dai contenuti pubblicati.
Manutenzione (admin) nella barra della pagina: **Rigenera indice** = migrate+rebuild+check (come
`save-event.php action=rebuild-index`); **Normalizza** = in più ripara la struttura (serie annidate,
occorrenze dichiarate ma assenti, subEvent↔superEvent) e dice cosa ha riparato — oggi sui contenuti
reali è un no-op, serve da rete di sicurezza.

**Pagina «Gestione eventi»** `ws-admin/events/index.php`: elenco redazionale di tutti gli eventi —
**Collezioni** (le serie, senza data), **Prossimi** (dal più vicino) e **Archivio** (dal più recente,
scaricato solo cliccando «Carica eventi passati»); **10 per volta**, altri mentre si scorre
(IntersectionObserver sugli indici statici già in memoria: nessuna paginazione lato server). Ogni card:
**Visualizza** (pagina pubblica), **Modifica** (`edit/?id=`), **Duplica** (`edit/?from=`), più ricerca,
filtri per organizzatore/collezione, data di ultima modifica e avviso **riferimenti rotti**. Il file è
anche il proprio endpoint POST (`action=auth`, `action=check-refs`): **login obbligatorio** — l'elenco
compare solo con un ruolo abilitato (user/client/admin/super-admin), altrimenti resta il messaggio di
accesso. FAB `#add-event-fab` (stile `.fab` di waterfront, ora in meetoo.css) → nuovo evento.
**Duplica** richiede la dist ricostruita: `App.jsx` gestisce `?from=` aprendo l'evento come copia
(toglie `@id`, date di sistema, creator/author/contributor e `subEvent`, così il salvataggio ne crea uno nuovo).

**Indice incrementale al salvataggio** (`event_index_sync` in `lib/events-index.php`): `save-event.php`
non rifà più tutto l'indice a ogni salvataggio, aggiorna solo l'evento salvato e **ripulisce** i
raggruppamenti che non lo riguardano più (organizzatore/collezione/CAP cambiati); salvando una SERIE
reindicizza anche le sue occorrenze (ne ereditano gli organizzatori). Verificato: su 7 scenari critici
produce un indice IDENTICO al rebuild completo. Costo con 500 eventi: **544 ms → 5 ms** per salvataggio.
Il rebuild completo resta in «Gestione eventi» per rimettere in riga modifiche fatte fuori dall'editor.
`lib/events-index.php` ora **richiede da sé `ws-auth.php`**: senza `ws_ref_id/ws_ref_ids` l'indice si
costruiva DEGRADATO (niente `by-collection`, chiavi organizzatore prese dal nome) — succedeva a chi lo
includeva da solo.

**Nuovo indice `by-cap/<CAP>.json`** (+ `.archive.json`): il CAP è già nel nome della cartella evento,
quindi indicizzarlo è gratis. È la base per le viste per zona (vedi «Territorio» in fondo).

**Organizzatori scelti dall'elenco del sito** (`entities.js` + `EntityInput.jsx` nell'editor,
`_index/entities.json` lato server): la riga Organizzatore non usa più l'autocomplete di Google
(proponeva mezzo mondo e lasciava l'@id da scrivere a memoria). Ora **nome** e **@id** si compilano
a vicenda: si sceglie il nome dall'elenco e arriva l'@id; si incolla l'@id e arriva il nome (anche
all'apertura di un evento salvato con il solo @id, cosa frequente); un @id sconosciuto lo dice
senza bloccare. `_index/entities.json` = TUTTE le organizations + i luoghi/attività, senza il
filtro `isGroup` di gruppi.json; lo rigenerano `places/rebuild-index.php`, la manutenzione dell'hub
e il salvataggio di un luogo (altrimenti un'organizzazione appena creata non comparirebbe).
Il riempimento dei nomi mancanti si fa in **App.jsx**, in un passaggio su tutte le righe: fatto
dentro la singola riga, due righe che si compilano nello stesso istante partono dalla stessa
istantanea e la seconda cancella la prima.

**Keywords: un ELENCO, non una stringa.** Si salvano come array JSON (`"keywords": ["…","…"]`)
e in XML come `<keywords>` ripetuto, esattamente come `<organizer>`. Prima erano una stringa separata
da virgole, e una keyword che contiene una virgola lì dentro non esiste: il luogo della serie si
chiama «Lido di Ostia, Roma», quindi veniva spezzato in due voci e al salvataggio dopo si
ri-aggiungeva intero, senza combaciare con nessuna delle due metà — ogni giro ne produceva un paio
in più. Con l'elenco la domanda non si pone. L'editor **legge entrambe le forme** (i file vecchi non
vanno riaperti per forza) e `dedupeKeywords` continua a spezzare sulle virgole e a deduplicare tutta
la lista, perché chi scrive incolla ancora elenchi separati da virgole.
Per i file già scritti c'è la migrazione **«Keywords come elenco»** nell'hub (anteprima + applica):
converte JSON e **rigenera l XML**, è idempotente e toglie i doppioni preesistenti.

**Luogo scelto da Google: se è già sul sito, si collega.** Scegliendo un suggerimento di Google si
chiede a `places/id-exists.php?place_id=…` se quel **Google Place ID** è già noto (indice di
deduplica `_index/google-places.json`): se sì si prendono **@id e nome DAL SITO** (il nome buono è
quello redazionale, non l'insegna su Google) e si mostra «Luogo già sul sito: collegato a …».
Altrimenti resta il comportamento di prima: @id proposto da tipo+CAP+nome e controllo delle
collisioni di slug. La domanda si fa sul Place ID e non sull'@id costruito perché il Place ID è
l'identità del luogo, mentre l'@id cambia se il nome è scritto diversamente e mancherebbe la
corrispondenza. In sviluppo serve il proxy `/id-exists` di Vite (5173→8091, altra origine); in
produzione editor ed endpoint sono sullo stesso host.

**Editor su telefono: la pagina scorre.** La media query mobile liberava `.app` ma non il
`body`, che restava `height: 100vh; overflow: hidden` (su desktop è giusto: sono le colonne a
scorrere per conto loro). Risultato: sotto i 1000px l'editor era bloccato e non si arrivava ai campi
in fondo. Ora il body si libera insieme a `.app`.

**Tre livelli di stile, non due.** `meetoo.css` = contratto comune (card, azioni, badge,
`.description`, «c'è altro sotto», condividi + «mi interessa», toast). **`places.css` (nuovo)** = il
VOCABOLARIO dei luoghi: tipologia col suo colore, voto, gestore precedente, foto con crediti,
attività ospitate — non è un formato, e serve identico a home, placecollection e al modale luogo.
`waterfront.html` = solo il FORMATO quadrato «mappa della metro», con prefisso **`wf-`**
(`.wf-card`, `.wf-slot`, `.wf-head`, `.wf-body`, `.wf-scroll`, `.wf-foot`, `.wf-name`).
Niente più nomi condivisi per caso: una regola nuova su `.card` non può più arrivare nel carosello.
Le misure comuni sono in **`em`**, così la stessa regola serve una card larga mezzo schermo e una
card quadrata che si misura in `cqw`: `.wf-card .card-act { font-size: 4.6cqw }` e basta.
Due trappole trovate e documentate: `.card-actions` nel sito prende **un terzo** della card
(`flex: 0 0 33.333%`), e in una card a colonna quel terzo diventa ALTEZZA (la riga passava da 30 a
67px); e `@media (max-width: 47.9375rem) .card { flex-wrap: wrap }` mandava il corpo in una seconda
colonna. Entrambe neutralizzate nella variante `.wf-card`.
Verificato: **57 card su 59 hanno misure identiche** a prima del rinominio (le altre due sono la
card centrata, che dipende da dove è fermo il carosello).

⚠ **Il font delle icone va caricato INTERO.** `waterfront.html` chiedeva a Google solo le icone
che gli servivano (`icon_names=account_balance,…`): un'ottimizzazione sensata finché la pagina era
sola al mondo, sbagliata da quando ospita header, menu e card condivisi, che usano icone non
prevedibili da lì (`home`, `event`, `menu_book`…). Quelle mancanti **uscivano scritte a lettere** e
sfondavano l'impaginazione del menu. Ora usa lo stesso URL completo delle altre pagine.
Stessa lezione per la CLASSE: la definizione di `.material-symbols-outlined` sta in meetoo.css e le
pagine non la ridichiarano — `waterfront.html` ne aveva una copia con `font-size: var(--icon-size)`
che si applicava anche all'header condiviso. Ora la misura di pagina è limitata alle SUE aree
(`.carousel-container`, `.modal-overlay`, `#app-loader`, `.fab`).

**Il voto è una sola pastiglia, sempre cliccabile** (`pastigliaVoto()` in waterfront.html):
stessa `.rating-pill` nella riga della card, nel blocco di un'attività e **nelle modali**, dove prima
aveva un aspetto suo (`.modal-rating`, pillola ambra con angoli diversi) ed era l'unico posto in cui
il voto non si poteva cliccare. Ora `.modal-rating` decide solo DOVE sta, non com'è fatto.
Il link va a `search.google.com/local/writereview?placeid=<google_place_id>`; senza Place ID
(6 luoghi con voto su 48) si ripiega su una ricerca per nome su Maps. Nelle modali il conteggio è
esteso («2573 recensioni»), nelle righe delle card compatto («(2573)»): stessa pastiglia, spazio
diverso.

## Liste con regola (`meetoo:listRule`) — primo passo, solo BookCrossing

Una lista (`ItemList`) può dichiarare **come si popola** e **come si ordina**. È dato, non codice:
clausole in OR su campi (`contains`, `in`, `equals`, `exists`, `prefix`) e un ordinamento per campo,
letto **prima sulla voce di lista** e poi sul membro.

```jsonc
"meetoo:listRule": {
  "from": ["places"],
  "match": { "any": [ {"field": "additionalType", "contains": "BookCrossing"} ] },
  "order": { "by": "name", "as": "text", "direction": "asc" }
}
```

**Il vocabolario è quello di JSON Schema.** Le clausole si chiamano `const`, `enum`, `pattern`,
`exists` e si comportano come là: `const` = valore ESATTO («BookCrossing»), `pattern` = espressione
(«^BookCrossing» prende anche «BookCrossingPlace»). La distinzione che mancava al primo giro, quando
c'era un `contains` ambiguo.
Si scrivono in forma COMPATTA — `{"field": "additionalType", "const": "BookCrossing"}` — e
`ws_listrule_compile()` le traduce nello JSON Schema equivalente. Perché non JSON Schema scritto a
mano: `properties` si applica solo se il campo c'è e `contains` solo sugli array, quindi la regola
ingenua accetta per verità vacua tutto ciò che quel campo non ce l'ha — **provata sui contenuti veri:
53 luoghi su 59 invece di 3**. Il `required` e il caso stringa-o-array li mette il compilatore.
Verificata l'equivalenza fra il valutatore PHP e **Ajv** (già nel bundle dell'editor, arriva con JSON
Forms) su 8 regole — esatto, prefisso, enum, booleano, esiste, non esiste, OR, numerico: **stesso
risultato su tutte**. Lato client la regola si può quindi valutare con una libreria testata da altri;
lato PHP (dove non c'è composer) il valutatore è quello della forma compatta, non di JSON Schema.

`php ws-admin/places/rebuild-lists.php` dice che cosa farebbe; con `--apply` scrive. **Non pota**:
le voci che la regola non trova più restano e vengono segnalate, e le voci senza `@id` (le 47 tappe
del lungomare che esistono solo nella lista) sopravvivono a ogni rigenerazione. Provata la fusione su
tre casi: lista vuota → popolata; lista curata → nomi e campi scritti a mano conservati, voce fuori
regola tenuta come «a mano»; voce `meetoo:auto` non più trovata → tenuta e segnalata come orfana.
L'ordine alfabetico è lettera per lettera (si ignorano interpunzione e spazi), altrimenti l'esito
dipende da dove cade un apostrofo.

**Fatto finora**: `places/lido-di-ostia/bookcrossing` è passato da `Collection` + `containsPlace`
(che in schema.org è il contenimento FISICO fra luoghi, improprio per un tema) a **`ItemList` +
`itemListElement`**, con la regola dichiarata e la lista materializzata: la regola trova **3** luoghi,
uno più di prima — «Social Pallet Park», taggato `additionalType: BookCrossing` ma mai aggiunto alla
lista a mano. `placecollection.html` non è stato toccato: leggeva già `containsPlace || hasPart ||
itemListElement` e sa scartare il `ListItem`.

**La regola gira in tre punti, con la stessa funzione** (`ws_listrule_sync`, in `lib/ws-listrule.php`):

1. **al salvataggio di un luogo o di un'organizzazione** (`places/google_place-json.php`): taggare un
   luogo come «BookCrossing» basta a farlo comparire nella sua collezione;
2. **al salvataggio di un evento** (`events/save-event.php`), per le collezioni tematiche che li
   includeranno;
3. **dal pannello di manutenzione** («Rigenera le liste con regola», con anteprima), e da
   `php ws-admin/places/rebuild-lists.php`.

Costa **~5 ms** e **non scrive se nulla è cambiato**: una rigenerazione a vuoto non tocca
`dateModified` né sporca il diff dei contenuti. Entrambi gli agganci sono difensivi come quello
dell'indice eventi — libreria mancante o errore non trasformano un salvataggio riuscito in un errore.
Verificato end-to-end su una COPIA dei contenuti: tolta una voce dalla lista, la sincronizzazione la
rimette (2 → 3), il campo scritto a mano su un'altra voce sopravvive, `numberOfItems` si aggiorna, e
il secondo giro non cambia più niente.

**Il lungomare resta curato, senza regola d'appartenenza** — e non per pigrizia: i suoi due campi
(`meetoo:coastalPosition` e `meetoo:m_from_border_south`) vivono **solo nelle voci di lista**
(59 su 59; nei file dei luoghi: 0), e 47 tappe su 61 non hanno nemmeno un file proprio. Una regola
non può generare un itinerario: può dire CHI c'è, non in che ordine né a che distanza.

**Politica di fusione** (quando il valutatore ci sarà): la rigenerazione non riscrive, fonde. Le voci
esistenti restano coi loro dati; le nuove si aggiungono; quelle che la regola non trova più **non si
cancellano**, si segnalano. `meetoo:auto: true` marca chi è entrato per regola; chi non ha il
marcatore è stato messo a mano e non si tocca.

**Ogni luogo è un BLOCCO, contenitore e ospitati allo stesso modo** (`.place-block` in places.css):
riga 1 = nome + il pulsante dei dettagli a destra; riga 2 = le caratteristiche che lo distinguono,
che scorrono in orizzontale, con il voto ancorato a destra (scorre la striscia, non il voto).
Le attività ospitate non rientrano: si distinguono con un filo sottile e una fascia di fondo a righe
alterne (`--color-background-section2`), che esce fino ai bordi della card (`--place-bleed`) con il
padding a compensare, così il testo resta allineato.
Nella riga 2 le PAROLE (tipologia del Comune, tipo, cucina) stanno nel colore dei titoli; le
dotazioni sono icone. Fuori dalla riga i **metodi di pagamento** (elenco esplicito di 5 nomi, non
un'euristica: «Parcheggio a pagamento» contiene la parola pagamento ma è una dotazione vera): carte
e contactless sono le tre voci più frequenti dell'intero lungomare e non distinguono nulla.
Una riga senza niente da dire NON si mostra — ed è il segnale di dove mancano i dati.
**A schermo vanno solo i tipi con un'etichetta italiana**: `translateTypes` restituiva il nome
schema.org grezzo quando l'etichetta mancava, e «SportsClub» è finito davvero sotto un nome. Ora i
tipi senza etichetta si tacciono, e TYPE_LABELS copre i 17 tipi che l'importazione da Google assegna.
⚠ Lacune redazionali che questa struttura rende visibili, sulle 46 attività del lungomare:
**4 hanno additionalType (9%), nessuna ha servesCuisine**, e 33 hanno dotazioni ma spesso solo
metodi di pagamento. Il codice per `servesCuisine` c'è ed è muto finché non arriva il dato.

**Le attività ospitate usano i componenti comuni.** Il «Dettagli» di un'attività non è più
`.biz-info` ma **`.card-act primary icon-only`**, e il suo voto non è più `.biz-rating` ma
**`.rating-pill`**: gli stessi dell'azione e del voto della card. `.biz-info` era per giunta 44×35px
(un `border-radius: 50%` su un rettangolo dà un'ellisse, non un cerchio) contro i 22×22 dell'azione
principale, perché la riga delle attività era rimasta a 16px fissi mentre tutto il resto della card
si misura in `cqw`. Ora `.wf-card .contained { font-size: 4.2cqw }` la rende proporzionale come il
resto e i due pulsanti coincidono. In places.css di `.biz-*` restano solo le regole di
IMPAGINAZIONE della riga (nome, allineamenti, striscia scorrevole delle dotazioni).

**Condividi + «mi interessa» in tutte le card** (`Meetoo.social` in cards.js): stesso markup e stesso
comportamento ovunque. Sui telefoni la condivisione apre il pannello di sistema, altrove copia il
link e lo dice con un toast. Il cuore va a finire in due posti diversi a seconda di cosa segna:
un EVENTO sul server (`meetoo:interestedIn`, serve essere collegati — altrimenti lo dice), un LUOGO
nel browser di chi guarda (`meetoo:favorites`, la chiave che il lungomare usava già: i preferiti
segnati restano). I pulsanti NON stanno dentro il link della card — un elemento cliccabile dentro un
altro non si può — ma accanto, in `.card-holder`. Le pagine di gestione non li mostrano: lì la card
ha già le sue azioni.

## Il guscio di pagina: `ItemPage` + `mainEntity`, ovunque

Un file di contenuto descrive DUE cose: la **pagina** (quando è cambiata, a che indirizzo risponde,
se va indicizzata) e l'**entità** di cui parla. Il guscio le separa — ed è la forma che il CMS usa
già: `<section>` porta `wspath`, `query`, `robots`, `changefreq`, e poi `mainContentOfPage`. Senza
guscio quei dati finirebbero dentro l'entità, e un `Event` non ha un `robots`.

Luoghi e organizzazioni l'avevano da sempre (62 file); eventi e utenti no (12). Ora l'hanno tutti.
Gli XML rispecchiano il JSON e hanno radice `<ItemPage>`.

**Le librerie che riscrivono sono state adeguate PRIMA dei dati**, e non è un dettaglio: chi legge,
modifica e riscrive senza sapere del guscio lo **cancella in silenzio** al primo passaggio.
In `lib/ws-wrap.php` ci sono le tre funzioni da usare sempre: `ws_wrap_entity()` per leggere,
`ws_wrap_set()` per riscrivere conservando il guscio, `ws_wrap_one()` per crearlo.
Adeguati: `save-event.php`, `ws-users.php`, `events-migrate.php`, `events-normalize.php` (anche nel
recupero di un'occorrenza dall'XML), `events-index.php` e `events-check.php`.

⚠ **Due difetti trovati dalle prove, non dalla lettura del codice.** Dopo aver avvolto i dati su una
COPIA, l'indice usciva con «11 eventi · **0 collezioni · 0 organizzatori**»: `event_index_rebuild`
indicizzava l'ItemPage invece dell'evento — nessun errore, solo un indice vuoto. E `event_check_refs`
segnalava 11 problemi inesistenti per lo stesso motivo. Entrambi corretti prima di toccare i
contenuti veri.

Verifica finale: **74 file su 74 col guscio**, **0 entità con contenuto cambiato** (il guscio non
tocca i dati), indice **identico** a prima, e le sei pagine del tema caricano come sempre
(27 · evento · 2 · 7 · 6 · 59 card).

Operazioni nuove nel pannello: **«Guscio ItemPage per tutti»** e **«Riallinea gli XML al JSON»**,
quest'ultima con l'opzione **«adotta la nuova radice»** — il consenso esplicito a cambiare forma,
necessario una volta sola. Senza quell'opzione la protezione rifiuta di riscrivere ciò che non ha
generato: è quella che ha salvato il record utente del CMS.

**La base è DICHIARATA, non indovinata.** Header e pagine ricavavano la radice del sito e la base
dei contenuti tagliando l'indirizzo su `/ws-custom/` o `/themes/` — con un ripiego su
`/sinapsi/…` in cinque pagine. Con gli URL puliti (`meetoo.it/lido-di-ostia/eventi`) quei segmenti
non esistono: la deduzione fallirebbe e il ripiego scatterebbe **proprio nel momento del trasloco**,
rompendo insieme login, menu e caricamento dei contenuti.
Ora `header.js` espone `Meetoo.siteRoot()` e `Meetoo.contentBase()`, che leggono
`<meta name="meetoo:site-root">` e `<meta name="meetoo:content-base">` — quando il CMS servirà le
pagine, li stamperà lui. Senza i meta si ricade sulla deduzione di prima: **oggi non cambia nulla**
(verificate le cinque pagine, base risolta correttamente e contenuti caricati). `?base=` continua a
scavalcare tutto, per provare una copia dei contenuti.

**Riallinea gli XML al JSON** (nuova voce di manutenzione, `lib/ws-xml.php`): l'XML è **derivato**,
ma le migrazioni riscrivevano solo il JSON — quella degli `@id` ha lasciato un evento con il vecchio
identificativo nell'XML. Ora c'è la rete di sicurezza da passare dopo ogni migrazione, con anteprima
e opzione «crea anche i mancanti» (oggi 57 entità non hanno il gemello).
⚠ **Due protezioni, e servono entrambe.** `users/` è escluso: là `index.xml` NON è un gemello ma il
**record utente del CMS** (ruolo, permessi, percorsi d'accesso) — rigenerarlo cancellerebbe il ruolo
di super-admin. E prima di riscrivere si confronta l'elemento RADICE: se non combacia, quel file non
l'abbiamo generato noi e non si tocca. La seconda protezione ha intercettato **6 casi** veri: i JSON
di luoghi e organizzazioni hanno il guscio `ItemPage`+`mainEntity` mentre i loro XML sono radicati
sull'entità (`<Place>`, `<Organization>`) — gli eventi invece non hanno guscio, e infatti combaciano.
Sono **due forme diverse nello stesso albero**: da decidere quale sia quella buona, prima di
riallinearle.

**L'accesso sopravvive alla scheda.** Il token Google stava in `sessionStorage`, che vive UNA
scheda: aprendo il sito in una scheda nuova non risultavi più collegato e il menu perdeva la voce
«Amministrazione» — la stessa pagina sembrava comportarsi in due modi, ed era l'unica cosa a non
sopravvivere (tema, preferiti e densità erano già in `localStorage`). Ora sta in `localStorage`, con
migrazione automatica per chi era collegato prima. Prezzo dichiarato: vive più a lungo ed è leggibile
da qualunque script della stessa origine — resta però un token Google a scadenza breve (~1h),
verificato dal server a ogni richiesta: una credenziale che invecchia da sola, non una sessione.

**Il tema si scrive anche come attributo** (`header.js`): oltre a `color-scheme` su `<html>`, la
scelta diventa `data-theme="light|dark"` (con «automatico» l'attributo si toglie). Serve perché
`light-dark()` **vale solo per i colori**: tutto ciò che non è un colore — l'immagine di uno sfondo,
una maschera, un'icona a due tinte — non può seguire `color-scheme`, e senza un attributo il CSS non
ha modo di sapere quale tema hai SCELTO (le `@media prefers-color-scheme` leggono il sistema, non la
scelta). È il caso dell'onda del lungomare (`--wave-url`): con sistema scuro e tema chiaro restava
l'onda scura, perché le sue regole si appoggiavano a un attributo che la vecchia intestazione di
pagina impostava e quella condivisa no. Verificate tutte e sei le combinazioni sistema × scelta.

**waterfront.html usa l'header condiviso.** Aveva un header disegnato a mano con i pulsanti inerti
(«Menu (in arrivo)», «Account (in arrivo)») e un proprio selettore del tema: ora carica `meetoo.css`
e `header.js` come le altre pagine — hamburger con la navigazione, login, Impostazioni → Aspetto,
breadcrumb via `Meetoo.setBreadcrumb` ('Lido di Ostia | Il lungomare' a sinistra, 'Roma | Municipio
10' a destra). La **legenda** delle tipologie resta un'azione di questa pagina e vive nello slot
`Meetoo.setActions`.
⚠ Attenzione al foglio condiviso su questa pagina: le sue card si chiamano `.card` come quelle del
sito ma hanno un impianto proprio (quadrate, `cqw`, modello di scatola predefinito). Perciò
`meetoo.css` va caricato PRIMA dello `<style>` di pagina, e in cima a quello stanno tre neutralizzazioni
commentate: `box-sizing`/interlinea del carosello, e su `.card` **`flex-wrap: nowrap`** — la regola
`@media (max-width: 47.9375rem) .card { flex-wrap: wrap }` (pensata per le card di gestione) mandava
il corpo in una seconda colonna e faceva uscire testo e immagine dalla card su telefono.
Verificato: tutte e 59 le card hanno misure IDENTICHE a prima della conversione, su desktop e su 375px.

**Luoghi e organizzazioni nello stesso strumento**: `places/edit` ha un selettore **«Salva come»**
(Luogo o attività / Organizzazione) che riscrive `@id` e `@type` — `places/<IT+CAP>/<slug>` con
`Place|LocalBusiness`, oppure `organizations/<slug>` con `Organization`; `?as=organization` lo
preimposta (ci arriva la card «Nuova organizzazione»). `google_place-json.php` ora **accetta anche
`organizations/<slug>`** in salvataggio: prima rifiutava tutto ciò che non fosse `places/…`, quindi
un'organizzazione si poteva aprire ma non salvare.

**Card con azioni (pagine di gestione), responsive** — regole in `meetoo.css`:
≥768px il contenuto tiene **due terzi** e le azioni **un terzo**, in griglia 2×2 (Visualizza Modifica /
Duplica Cestina) con larghezza FISSA, così restano incolonnate fra card diverse. <768px le azioni
vanno **sotto**, a tutta larghezza, due per riga: accanto al testo quattro pulsanti che non vanno a
capo riducevano il titolo a una colonna larga un carattere. Un conteggio vuoto non si mostra
(`.count:empty`): l'archivio non annuncia un numero prima di essere caricato.

**Manutenzione dall'hub** (`ws-admin/index.php`, sezione visibile ai soli admin): migrazioni e
conversioni che prima erano solo da riga di comando. L'elenco **non è scritto nella pagina**: viene
dal registro `lib/ws-maintenance.php`, unica fonte (vedi «Il registro della manutenzione» al §3),
letto anche dalla Gestione eventi. Regola invariata: prima **Anteprima** (non scrive, elenca cosa
farebbe), poi **Applica** con conferma. La logica sta nelle librerie (`ws_privacy_migrate`,
`ws_media_covers`, `event_index_rebuild`…) e sia il registro sia i comandi CLI ne sono involucri:
interfaccia e riga di comando non possono divergere.
All'apertura l'hub esegue le anteprime disponibili e scrive sotto ogni voce **quante cose sono in
attesa** e **quando è stata eseguita l'ultima volta su questa installazione** — è ciò che rende la
pagina utilizzabile per completare un aggiornamento di Meetoo senza ricordarsi la lista a memoria.

**⚠ Dati personali fuori dai contenuti pubblici (`ws-admin/lib/ws-private.php`)** — `users/<uid>/index.json`
e `events/<slug>/rsvp.json` sono file STATICI serviti dal web: contenevano **nome ed email** in chiaro
(verificato: leggibili in produzione senza autenticazione). Ora nome/email/foto vivono in
**`ws-admin/_private/users/<uid>.php`** — file `.php`, quindi una richiesta diretta lo esegue e non
stampa nulla, più un `.htaccess` che nega l accesso; nei contenuti resta solo l uid. Le registrazioni
salvano `uid/mode/date`; i nomi si ricompongono lato server per i soli amministratori dell evento.
`_private/` è in `.gitignore`: non va versionato né incluso in backup pubblici.
**Da eseguire in produzione**: `php ws-admin/users/migrate-privacy.php --apply` (dry-run senza flag)
sposta i dati già pubblicati e ripulisce i file. NB: l esposizione passata non si annulla — se le
pagine sono state indicizzate, valutare una richiesta di rimozione dalla cache dei motori.

**Copertine esistenti**: `php ws-admin/events/make-covers.php` (dry-run; `--apply`, `--adopt` per
adottare un immagine orfana trovata nella cartella) genera le cover 1920×1080 dalle immagini già
caricate e riscrive `image` in forma assoluta. Poi rigenerare l indice.

La **cover 16:9 con sfumatura e cuore** è ora in `meetoo.css` (non più dentro `event.html`), perché
la usano DUE pagine: quella dell evento e quella della **collezione** (`collection.html`, che prima
non mostrava alcuna immagine anche quando la serie ne aveva una). Anche la collezione ha il suo
«mi interessa»: l azione `like` vale per le serie quanto per le singole date.

**Copertina 16:9 e «mi interessa»** — file nuovi: `ws-admin/lib/ws-media.php`, `ws-admin/events/media.php`.
Regola: se il file caricato è **già 1920×1080** va dritto in `media/`; altrimenti l'originale resta in
`media-sources/` e in `media/` va la versione 1920×1080 generata (ritaglio centrato, GD). L'editor
permette di **scegliere l'inquadratura** (`action=recrop`) sovrascrivendo il file in `media/`.
**Riuso senza duplicati**: l'indice `_index/media.json` mappa l'impronta sha256 → percorso; ricaricare
la stessa immagine (o duplicare un evento) **non copia nulla**, restituisce il percorso esistente. I
percorsi salvati sono **dalla radice** (`events/<slug>/media/…`), così valgono anche citati da un
altro evento; le pagine accettano entrambe le forme. «Duplica» riscrive `image` in forma assoluta.
L'indice eventi porta ora **`cover`** risolta, con **ripiego sulla serie**: un'occorrenza senza
immagine — o con un riferimento che punta a un file **inesistente** — mostra la locandina della
rassegna. Il controllo di esistenza sta sia nell'indice (`event_index_cover`) sia in `event.html`
(se l'immagine non carica, si prova la serie): un riferimento rotto è peggio di nessun riferimento. **«Mi interessa»**: azione `like` in `rsvp.php`,
conteggio pubblico in `events/<slug>/likes.json` (**solo uid**, mai nomi o email) e memoria sul
profilo (`meetoo:interestedIn`). Il conteggio si legge senza login; per metterlo serve l'accesso.

**Pagina evento (`event.html`) riorganizzata**: breadcrumb **organizzatore | collezione | data**
(«Club del libro Ostia | Reading Party | 25 agosto 2026»); l'ORA si aggiunge solo se un'altra
occorrenza della stessa collezione cade lo stesso giorno (letto da `by-collection`). Il nome della
collezione si risolve dal suo documento (il `superEvent` è spesso il solo percorso). Gli
**organizzatori** non hanno più una sezione a parte: stanno fra le informazioni («Evento organizzato
da» + chip con l'icona di `Meetoo.orgIcon`). Il **luogo è un link**: apre la sua scheda in
sovrimpressione (tipo, voto, indirizzo, sito, servizi, vista satellitare, «Apri in mappa») usando il
modale del lungomare — le classi `.mt-ov/.mt-modal` già condivise, più poche righe di contenuto
(`.modal-sub/.modal-line/.modal-amen/.modal-img`). **«Partecipa» è in fondo**, dopo il Programma,
come invito ad agire. Su una SERIE la pagina degrada bene: niente data nel breadcrumb, niente RSVP.

**Token rinominati: allineare TUTTE le pagine** — nel refactor il vecchio blocco di token è stato
commentato e i nomi sono cambiati (`--accent`→`--color-link`, `--on-accent`→`--color-text-neg`,
`--line`→`--color-line`, `--hint`→`--color-hint`, `--text`→`--color-text`, `--surface(-2)`→
`--color-background-section1|2`, `--danger-*`→`--color-danger|--color-background-danger`,
`--warn-*`→`--color-warning|--color-background-warning`, `--font1|2`→`--font-family1|2`,
`--radius`→`--border-radius`, `--past|sea|sand|green`→`--color-past|sea|sand|park`). Un `var()` che
punta a un token inesistente NON eredita nulla: la proprietà diventa `unset` — così il pulsante
«Modifica» aveva sfondo trasparente e testo chiaro, cioè invisibile in tema chiaro. Rimappati **149
riferimenti** in 10 pagine (temi + admin) e aggiunti `--color-line` e `--color-success`, che
mancavano. Controllo rapido, da rifare dopo ogni rinomina: nessun `var(--x)` deve restare vuoto.

**@id degli eventi = `events/{slug}`** (era lo slug nudo): stessa forma dei riferimenti,
dell'attributo `id` dell'XML e delle altre collezioni — JSON e XML dello stesso evento prima si
contraddicevano. La regola sta in `lib/events-migrate.php`, quindi **il prossimo «Rigenera indice»
(o «Normalizza») ripara i contenuti da solo, anche in produzione**: nessun deploy di contenuti.
`lib/events-check.php` segnala come problema un `@id` che non corrisponde alla cartella, e il badge
sulla card ora dice «N problemi» (non solo riferimenti rotti). Le letture restano tolleranti alla
forma nuda (l'indice normalizza all'ultimo segmento); l'editor non cambia — `eventRelPath` e
`toEventRef` prefissano solo quando il prefisso manca.

**Icona di chi organizza** — `Meetoo.orgIcon(type, name)` in `cards.js`, regola UNICA per card
evento, collezioni e Gruppi della home: comanda il **@type** (LocalBusiness → `storefront`, con
`local_library`/`menu_book` se il nome dice biblioteca o libreria; NGO → `volunteer_activism`;
Organization e gruppi → `groups`), il nome interviene solo se il tipo manca. Per averlo, l'indice
eventi porta ora **`organizerType`** (letto dal documento dell'organizzatore, una lettura per
organizzatore, memorizzata) → **serve un rebuild dell'indice dopo il deploy**.

**Card condivise — `cards.js` (nuovo file da caricare)**: i template delle card stanno in un posto
solo, come gli stili. `Meetoo.eventCard(ev,{base,organizer})`, `Meetoo.tileCard({href,icon,title,meta,
external,accent})`, `Meetoo.placeCard(place)` + `Meetoo.cardUtils` (esc/icon/metaItem/statusBadge/
placeLabel). Va incluso **prima** dello script di pagina; `header.js` ora **fonde** `window.Meetoo`
invece di riassegnarlo, così l'ordine degli script non conta. Le card evento mostrano
**"{place.name}, {place.address.addressLocality}"** (il CAP non si mostra più: resta nell'indice).

Regola unica del luogo (indice e pagine dicono la stessa cosa): si mostra **sempre** il `name` del
luogo — un eventuale `alternateName` è un'alternativa, non un sostituto — **sempre** la località, mai
il CAP. Nella pagina evento il riferimento non ha la località, quindi `Meetoo.resolvePlace()`
(cards.js) legge il file del luogo e completa la riga.

**`_index/events.json`: il luogo è un oggetto** — la voce ha `place: {id, name, address:
{addressLocality}}` **al posto di** `location` (stringa). Poiché nell'evento la location è solo un
riferimento `{@id,name}`, `event_index_place_ref()` risolve il file del luogo per prendere la
località e il **nome canonico** (una lettura per luogo, memorizzata); se il riferimento è rotto
ripiega sui dati inline, e se non c'è luogo il campo è `null`. → **Le pagine tema e l'indice vanno
deployati INSIEME**: le pagine vecchie leggono `location` e resterebbero senza luogo.

**Stili condivisi (nuovi file — vanno caricati, altrimenti le pagine restano senza CSS)**:
- **`meetoo-tokens.css`** = SOLO i design token (palette light-dark, tipografia, misure).
- **`meetoo.css`** = importa i token + base, componenti comuni (`.wrap .sec-head .card* .badge
  .state .empty`) e l'header `.mt-*`. Gli stili dell'header NON sono più dentro `header.js`.
Ogni pagina (tema **e** admin) lo linka; `header.js` lo inietta comunque se manca. Le pagine
tengono solo le proprie specificità: i token si cambiano in un posto solo. `waterfront.html` linka
**solo i token** (ha classi omonime — `.card`, `.badge`, `.crumb` — con altro significato: i
componenti condivisi la romperebbero) e il suo header è ora ristretto a `--mt-w` (960px) come le
altre pagine. Le pagine admin (`json-xml`, `places/edit`) hanno perso i token duplicati: `json-xml`
usava una palette scura diversa, ora è quella del sito.

**BookCrossing / collezioni di luoghi**: `placecollection.html?id=places/{slug}` mostra una
collezione di places (`@type` con `meetoo:PlaceCollection`, membri in `containsPlace`); contenuto
in `ws-custom/contents/…/places/lido-di-ostia/bookcrossing/index.json` (deploy coi contenuti;
@id `places/lido-di-ostia/bookcrossing`). Linkata dalla home tra le "Iniziative letterarie".

**Editor eventi — header a 2 righe (niente triplice)**: l'editor ora usa l'header condiviso per la
**riga 1** (logo + Impostazioni + **login** via `window.meetooSession`) e la propria toolbar azioni
come **riga 2** (`.appbar-row2`; niente brand "Meetoo", niente breadcrumb). I tab **Form /
Validazione** stanno in fondo alla riga 2, **a destra**. Il menu "Opzioni" (icona `tune`) è stato
**eliminato**: Densità e "Cambia cartella" vivono ora nella modale **Impostazioni** dell'header,
sotto Aspetto/Preferenze (`PageSettings.jsx` → portal in `#mt-page-settings`, slot esposto da
`Meetoo.settingsSlot()`); `OptionsMenu.jsx` rimosso. Il **tema** si sceglie solo in "Aspetto":
header.js emette l'evento `meetoo:theme` e l'editor lo segue. `main.jsx` inietta
`header.js` con `MEETOO_HEADER = {}` (login attivo; una sola init GIS — quella dell'header, dato che
l'index.html dell'editor carica già GSI). `Auth.jsx` **rimosso**: l'auth per il salvataggio viene
dalla sessione dell'header. `header.js` ora **nasconde la riga 2 (breadcrumb) quando è vuota**. Il
tema dell'editor è sincronizzato con l'header (`color-scheme`/`meetoo:theme`). → **ricostruire la
dist** e **ridistribuire `header.js`**.

**Riferimenti eventi corretti (contenuti locali; la PRODUZIONE ha gli stessi refusi → vanno
deployati i contenuti corretti)**: nelle `location`/`subEvent` di vari eventi:
`places/IT00122-spiaggialamanusa`→`places/IT00122/lamanusa`;
`places/IT00121/sognalibristorieegiochipersguardicuriosi`→`places/IT00121/sognalibri`;
`places/IT00122/lapiccolaoasidistellapolare`→`places/IT00122/lapiccolaoasi-stellapolare`;
`places/lido-di-ostia`→`places/lido-di-ostia/lungomare`; subEvent serie reading_party
`…20260825T2130…`→`…20260825T1845…`. Scaricata da prod l'occorrenza mancante
`events/20260716T11730-IT00122-clubdellibro-ostia-junior/`. Dopo il deploy dei contenuti: `check-refs`
deve dare "nessun riferimento rotto".

**places/edit — luoghi salvati senza crediti Google**: la pagina ora ha una **ricerca
locale** dei luoghi già salvati (`action=editable` → datalist; `action=load` apre `{@id}/index.json`)
che NON usa la Google Maps API. Un bottone **«Aggiorna da Google Maps»** rinfresca il luogo caricato
via **`action=search` con `place_id`** (Details diretto sul `google_place_id` salvato — un nuovo ramo
in `google_place-json.php`): i dati Google vengono **fusi** sopra il salvato (campi custom come
`meetoo:isGroup`/creator preservati) e al Salva compare il diff esistente. L'Autocomplete Google
resta solo per i luoghi NUOVI ed è isolato in try/catch (se la chiave Maps manca, la ricerca locale
funziona lo stesso). File: `ws-admin/places/edit/index.php`, `ws-admin/places/google_place-json.php`.

**Gruppi (home)**: la sezione Gruppi legge `_index/gruppi.json`, generato da
`ws-admin/places/rebuild-index.php` (funzioni `ws_gruppi_*` in `index-lib.php`). Include **tutte le
`organizations/`** + i **`places/` LocalBusiness marcati `"meetoo:isGroup": true`** (esperienze
collettive/gratuite: Sognalibri, Feltrinelli, La Farfalla, Biblioteca Elsa Morante). Le org linkano
a `organizer.html?org={key}`, i business al loro `url`/mappa. Rilancia `rebuild-index.php` dopo aver
marcato altri place. (Nota dati: corretto un JSON invalido in `organizations/sahajayoga-ostia`.)

**Header Meetoo condiviso** (`header.js`, incluso da tutte le pagine): 2 righe (logo + hamburger +
Impostazioni + login; breadcrumb), modale Impostazioni (tema + preferenze utente), menu hamburger.
**Home** `index.html` (hub Lido di Ostia: Esplora, Prossimi eventi, Iniziative letterarie, Gruppi,
Il Lungomare). Anche le pagine **admin** lo includono in modalità `noAuth` (solo chrome, niente
login/GIS): `ws-admin/places/edit/index.php`, `ws-admin/json-xml/index.php` (già committati) e
l'**editor React** (iniettato da `main.jsx` → rientra nella **dist**, ricostruiscila).

> **`session.js` è stato sostituito da `header.js`** (header Meetoo condiviso a 2 righe + sessione +
> Impostazioni con preferenze utente): rimuovi il vecchio `session.js` dal server se presente.

**Registrazione agli eventi (RSVP)**: `rsvp.php` (login Google + capienze del fieldset "Pubblico" +
azioni `me`/`prefs`/register/participants/notify), profilo utente in `users/{uid}/index.json`
(creato al primo accesso, con `meetoo:preferences`), registrazioni in `events/{slug}/rsvp.json`.
**Header condiviso** `header.js` incluso in tutte le pagine tema (logo, Impostazioni con tema +
lingua/notifiche, login, breadcrumb). Serve che il web-server possa **scrivere** in
`ws-custom/contents/…/users/` e nelle cartelle evento.

La dist è mirror della cartella servita: caricala intera (gli asset hanno hash nel nome, i
vecchi vanno rimossi → usa `--delete`).

### Esempio rsync (adatta host/percorsi)

```bash
# Editor dist (oppure: DEPLOY_DEST=… npm run deploy, che fa build + rsync)
rsync -avz --delete ws-admin/events/edit/  USER@HOST:ROOT/ws-admin/events/edit/
# Backend PHP
rsync -avz ws-admin/lib/ws-auth.php ws-admin/lib/events-index.php ws-admin/lib/events-migrate.php \
          ws-admin/lib/events-normalize.php ws-admin/lib/events-check.php  USER@HOST:ROOT/ws-admin/lib/
rsync -avz ws-admin/events/save-event.php ws-admin/events/rebuild-index.php ws-admin/events/migrate-refs.php \
          ws-admin/events/normalize-content.php ws-admin/events/check-refs.php  USER@HOST:ROOT/ws-admin/events/
rsync -avz ws-admin/json-xml/functions.php  USER@HOST:ROOT/ws-admin/json-xml/
# Temi (non versionati)
rsync -avz ws-custom/themes/meetoo/organizer.html ws-custom/themes/meetoo/collection.html \
          ws-custom/themes/meetoo/event.html  USER@HOST:ROOT/ws-custom/themes/meetoo/
```

## 3) Una-tantum sul server (dopo il primo deploy di questo ciclo)

### Il registro della manutenzione (`ws-admin/lib/ws-maintenance.php`)

Migrazioni e conversioni sono elencate **una volta sola**, nel registro. Le pagine non le
riscrivono: le leggono.

- **`ws-admin/index.php` (l'hub) le mostra tutte** ed è il posto da cui si completa un
  aggiornamento di Meetoo. All'apertura interroga le anteprime e scrive sotto ogni voce
  *«2 in attesa · mai eseguita qui»*: dopo un deploy si vede a colpo d'occhio che cosa
  manca su **questa** installazione, senza ricordarselo.
- **`ws-admin/events/index.php` mostra solo le operazioni di ambito `events`** come
  scorciatoie nel lavoro quotidiano (Rigenera indice, Normalizza); l'endpoint rifiuta le
  altre. Non è una seconda lista: è un filtro sulla stessa.

**Per aggiungere un'operazione** basta una voce in `ws_maint_ops()`: `title`, `meta`,
`icon`, `scope`, `since` (versione che l'ha introdotta), l'eventuale `confirm`, e `run`
che ritorna `['changes'=>int,'summary'=>string,'lines'=>[]]`. Se sa dire cosa farebbe
senza scrivere, dichiara `'preview' => true` e rispetta `$apply`. Comparirà da sola
nell'hub — è così che le pagine non possono restare indietro rispetto al codice.

Chi ha già eseguito cosa sta in `contents/…/_index/maintenance.json` (file di **stato**,
non un contenuto: si può cancellare, si riparte da «mai eseguita»). Attenzione: i comandi
da shell qui sotto fanno lo stesso lavoro ma **non aggiornano quel file**, quindi l'hub
continuerà a dire «mai eseguita qui».

### I comandi equivalenti da shell

Dall'hub come **admin/super-admin**, oppure da shell:

```bash
php ws-admin/events/normalize-content.php --apply   # ripara superEvent, completa occorrenze, rimuove serie annidate
php ws-admin/events/rebuild-index.php                # rigenera events/_index (split prossimi/archivio, by-organizer, by-collection)
php ws-admin/events/check-refs.php                   # elenca i riferimenti rotti (refusi @id, cartelle mancanti)
```

> Nell'editor: **Normalizza** = normalize+rebuild+check; **Rebuild index** = migrate+rebuild+check.
> Entrambi mostrano "⚠ N riferimenti rotti" se presenti.

## 4) Da sistemare nei dati (segnalati dal check)

- **Club del libro Junior**: organizer con refuso `organizations/clubdel**i**bro-ostia` → correggi in
  `organizations/clubdel**li**bro-ostia` (ri-seleziona l'organizzatore nell'editor). Poi Rebuild.
  Il check segnala anche un `subEvent` con slug malformato (`…T11730…`) e una place inesistente.
- In generale: ogni voce di `check-refs` = un `@id` che punta a una cartella senza `index.json`.

## 5) Cron consigliato (facoltativo)

```cron
0 3 * * *  /usr/bin/php ROOT/ws-admin/events/rebuild-index.php >/dev/null 2>&1
```

## Note

- `save-event.php` richiede login Google + ruolo `user/client/admin/super-admin` (verifica `users.xml`).
- La dist porta i valori di produzione (`SAVE_EVENT_URL=../save-event.php`, `CONTENT_BASE=/sinapsi/…`).
- Le pagine tema sono robuste all'indice mancante (mostrano un messaggio con "Rebuild index").
