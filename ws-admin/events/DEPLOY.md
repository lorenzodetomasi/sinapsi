# Deploy — Editor eventi, backend e pagine tema

Runbook per portare in produzione (isotype.org/**sinapsi**/) l'editor eventi, il backend PHP
e le pagine tema. `ROOT` = radice `sinapsi/` sul server; `SRC` = questa working copy.

> I contenuti (`ws-custom/contents/…`) NON si toccano qui: sono dati, gestiti a parte.
> Le pagine tema (`ws-custom/themes/…`) NON sono versionate → vanno caricate a mano.

## 1) Build dell'editor

```bash
cd ws-admin/events/edit-src && npm run build   # esce in ../edit (cartella servita)
```

## 2) Cosa caricare, e dove

| Gruppo | Da (SRC) | A (ROOT sul server) |
|---|---|---|
| **Backend – lib** | `ws-admin/lib/{ws-auth,ws-users,events-index,events-migrate,events-normalize,events-check}.php` | `ws-admin/lib/` |
| **Backend – events** | `ws-admin/events/{save-event,rsvp,rebuild-index,migrate-refs,normalize-content,check-refs}.php` | `ws-admin/events/` |
| **Convertitore** | `ws-admin/json-xml/functions.php` | `ws-admin/json-xml/` |
| **Editor (dist)** | `ws-admin/events/edit/` (tutto: `index.html` + `assets/`) | `ws-admin/events/edit/` |
| **Temi** | `ws-custom/themes/meetoo/{index,organizer,collection,event,placecollection}.html` + **`header.js`** | `ws-custom/themes/meetoo/` |

**BookCrossing / collezioni di luoghi**: `placecollection.html?id=places/{slug}` mostra una
collezione di places (`@type` con `meetoo:PlaceCollection`, membri in `containsPlace`); contenuto
di esempio in `ws-custom/contents/…/places/bookcrossing/index.json` (deploy coi contenuti).
Linkata dalla home tra le "Iniziative letterarie".

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
