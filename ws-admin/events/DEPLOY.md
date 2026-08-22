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

## 1) Build dell'editor

```bash
cd ws-admin/events/edit-src && npm run build   # esce in ../edit (cartella servita)
```

## 2) Cosa caricare, e dove

| Gruppo | Da (SRC) | A (ROOT sul server) |
|---|---|---|
| **Backend – lib** | `ws-admin/lib/{ws-auth,ws-users,events-index,events-migrate,events-normalize,events-check}.php` | `ws-admin/lib/` |
| **Backend – events** | `ws-admin/events/{save-event,rsvp,rebuild-index,migrate-refs,normalize-content,check-refs}.php` | `ws-admin/events/` |
| **Gestione eventi** | `ws-admin/events/index.php` (nuova pagina) | `ws-admin/events/` |
| **Amministrazione** | `ws-admin/index.php` (hub) + `ws-admin/lib/events-trash.php` | `ws-admin/` e `ws-admin/lib/` |
| **Convertitore** | `ws-admin/json-xml/functions.php` | `ws-admin/json-xml/` |
| **Editor (dist)** | `ws-admin/events/edit/` (tutto: `index.html` + `assets/`) | `ws-admin/events/edit/` |
| **Temi** | `ws-custom/themes/meetoo/{index,organizer,collection,event,placecollection,waterfront}.html` + **`header.js`** + **`meetoo.css`** + **`meetoo-tokens.css`** | `ws-custom/themes/meetoo/` |

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
conversioni che prima erano solo da riga di comando — **Dati personali fuori dai file pubblici**,
**Genera le copertine 1920×1080** (con «adotta orfane»), **Rigenera l'indice delle immagini**,
**Rigenera indice luoghi e Gruppi**. Regola: prima **Anteprima** (non scrive, elenca cosa farebbe),
poi **Applica** con conferma. La logica sta nelle librerie (`ws_privacy_migrate`, `ws_media_covers`)
e i comandi CLI ne sono involucri: interfaccia e riga di comando non possono divergere.

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

**Copertina 16:9 e «mi interessa»** — file nuovi: `ws-admin/lib/ws-media.php`, `ws-admin/events/media.php`.
Regola: se il file caricato è **già 1920×1080** va dritto in `media/`; altrimenti l'originale resta in
`media-sources/` e in `media/` va la versione 1920×1080 generata (ritaglio centrato, GD). L'editor
permette di **scegliere l'inquadratura** (`action=recrop`) sovrascrivendo il file in `media/`.
**Riuso senza duplicati**: l'indice `_index/media.json` mappa l'impronta sha256 → percorso; ricaricare
la stessa immagine (o duplicare un evento) **non copia nulla**, restituisce il percorso esistente. I
percorsi salvati sono **dalla radice** (`events/<slug>/media/…`), così valgono anche citati da un
altro evento; le pagine accettano entrambe le forme. «Duplica» riscrive `image` in forma assoluta.
L'indice eventi porta ora **`cover`** risolta, con **ripiego sulla serie**: un'occorrenza senza
immagine mostra la locandina della rassegna. **«Mi interessa»**: azione `like` in `rsvp.php`,
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

Dall'editor come **admin/super-admin** (bottoni in appbar), oppure da shell:

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
