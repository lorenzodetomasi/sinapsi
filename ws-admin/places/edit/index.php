<?php
// Chiave Maps JS da config.php (gitignored, nella cartella places/). Vedi config.sample.php.
$config = @include __DIR__ . '/../config.php';
$mapsJsKey = is_array($config) ? ($config['maps_js_key'] ?? '') : '';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>WS CMS - Data Ingestion Places</title>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <script src="https://maps.googleapis.com/maps/api/js?key=<?php echo htmlspecialchars($mapsJsKey, ENT_QUOTES); ?>&libraries=places&language=it&region=IT"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Roboto+Slab:wght@500;600;700&family=Source+Code+Pro:wght@400;600&display=swap" rel="stylesheet">
    <!-- Stili condivisi Meetoo: token, base e header (unica fonte di verità). -->
    <link rel="stylesheet" href="../../../ws-custom/themes/meetoo/meetoo.css">
    <style>
        /* Solo ciò che è proprio di questa pagina: i token stanno in meetoo.css. */
        :root { --red: var(--color1); }
        .material-symbols-outlined { font-size: 18px; vertical-align: -4px; }
        .container { max-width: 1100px; margin: auto; padding: 20px 20px 60px; }
        .header { margin-bottom: 20px; }
        .header h2 { font-family: 'Roboto Slab', Georgia, serif; color: var(--red); margin: 0 0 12px; }

        #banner-unverified, #google-user, #whitelist-area { display: none; }
        #banner-unverified { background: var(--color-background-warning); color: var(--color-warning); padding: 15px; border-left: 5px solid var(--color-warning); border-radius: var(--border-radius); font-weight: 600; margin-bottom: 20px; }
        #google-user { background: var(--color-background-section1); border: 1px solid var(--color-line); padding: 15px; border-radius: var(--border-radius); margin-bottom: 20px; }
        #google-user code { background: var(--color-background-section2) !important; color: var(--red) !important; }

        .search-box { width: 100%; padding: 14px 16px; font-size: 1rem; margin-bottom: 16px; border: 1px solid var(--color-line); border-radius: var(--border-radius); background: var(--color-background-section1); color: var(--color-text); }
        .search-box:focus { outline: none; border-color: var(--color-link); }
        #debug-msg, #save-msg { color: var(--color-hint); }

        .grid { display: flex; gap: 20px; flex-wrap: wrap; }
        .col { flex: 1 1 340px; display: flex; flex-direction: column; min-width: 0; }
        label { font-weight: 600; margin-bottom: 6px; color: var(--color-hint); }
        textarea { width: 100%; height: 480px; padding: 12px; font-family: 'Source Code Pro', monospace; font-size: 13px; line-height: 1.5; background: var(--color-background-section1); color: var(--color-text); border: 1px solid var(--color-line); border-radius: var(--border-radius); resize: vertical; }
        textarea:focus { outline: none; border-color: var(--color-link); }
        input[type=text], select { background: var(--color-background-section1); color: var(--color-text); border: 1px solid var(--color-line); border-radius: 10px; padding: 6px 10px; font: inherit; }
        input[type=text]:focus, select:focus { outline: none; border-color: var(--color-link); }

        #error-msg { color: var(--red); font-weight: 700; margin-bottom: 15px; }
        #cap-fix { background: var(--color-background-warning) !important; color: var(--color-warning); border-left: 5px solid var(--color-warning) !important; border-radius: var(--border-radius); }

        .save-bar { display: flex; align-items: center; gap: 10px; margin-top: 12px; flex-wrap: wrap; }
        button { font-family: inherit; }
        .save-bar button, .diff-btns button, #admin-tools button, #editors-apply {
            display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; font-size: 14px; font-weight: 600;
            cursor: pointer; border: 1px solid var(--color-line); border-radius: 999px; background: var(--color-background-section2); color: var(--color-text);
        }
        .save-bar button:hover, .diff-btns button:hover, #admin-tools button:hover, #editors-apply:hover { border-color: var(--color-link); }
        #btn-save, #diff-merge { background: var(--color-link); color: var(--color-text-neg); border-color: transparent; }
        #diff-overwrite { background: var(--red); color: #fff; border-color: transparent; }
        #save-msg { font-weight: 600; }

        #diff-panel { display: none; margin-top: 20px; padding: 16px; background: var(--color-background-section1); border: 1px solid var(--color-line); border-radius: var(--border-radius); }
        #diff-panel h3 { margin: 0 0 4px; } #diff-panel p, #diff-panel a { color: var(--color-hint); }
        #diff-content { max-height: 340px; overflow: auto; font-family: 'Source Code Pro', monospace; font-size: 13px; border: 1px solid var(--color-line); padding: 10px; border-radius: 8px; background: var(--color-background-section2); }
        .diff-row { display: block; padding: 2px 0; white-space: pre-wrap; word-break: break-word; cursor: pointer; }
        .diff-row input { vertical-align: middle; margin-right: 4px; }
        .diff-add { color: light-dark(#2e7d32, #a6e3a1); }
        .diff-del { color: var(--red); }
        .diff-chg { color: light-dark(#e65100, #ffcf8a); }
        .diff-btns { margin-top: 12px; display: flex; gap: 10px; flex-wrap: wrap; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h2>WS CMS - Google Places Importer</h2>
        <div id="login-buttons">
            <div id="g_id_onload"
                 data-client_id="947742864411-rs99t8lkv5qcv4f5afb3pnhi0lkegbk3.apps.googleusercontent.com"
                 data-callback="handleCredentialResponse">
            </div>
            <div class="g_id_signin" data-type="standard"></div>
        </div>
    </div>

    <div id="banner-unverified">
        ⚠️ Attenzione: La tua email Google non risulta verificata. Accesso al sistema bloccato.
    </div>

    <div id="google-user">
        👤 Benvenuto, <strong id="user-email-display"></strong> <br>
        🔑 Google UID: <code id="user-uid-display" style="background:#fff; padding:2px 5px; border-radius:3px; color:#d32f2f;"></code> <br>
        🌍 Locale Google acquisito: <strong id="user-locale-display"></strong> <br>
        🛡️ Ruolo: <strong id="user-role-display"></strong>
    </div>

    <div id="error-msg"></div>

    <div id="whitelist-area">
        <div id="admin-tools" style="display:none; margin:0 0 12px;">
            <button type="button" id="rebuild-index" style="padding:6px 10px; cursor:pointer;">🔄 Rigenera indice</button>
            <span id="rebuild-msg" style="margin-left:8px; font-size:13px;"></span>
        </div>
        <div id="saved-area">
            <label style="display:block; margin-bottom:6px;">Luoghi già salvati
                <span style="font-weight:400; color:var(--color-hint);">— ricerca locale, nessun credito Google</span>
            </label>
            <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-bottom:6px;">
                <input type="text" id="saved-search" list="places-datalist" placeholder="Cerca un luogo salvato per nome…" style="flex:1 1 320px;">
                <datalist id="places-datalist"></datalist>
                <button type="button" id="saved-open"><span class="material-symbols-outlined">folder_open</span> Apri salvato</button>
                <button type="button" id="saved-copy" title="Apri come base per una scheda nuova: senza @id e senza Google Place ID"><span class="material-symbols-outlined">content_copy</span> Duplica</button>
                <button type="button" id="btn-refresh-google" style="display:none;"><span class="material-symbols-outlined">refresh</span> Aggiorna da Google Maps</button>
            </div>
            <div id="saved-msg" style="font-size:13px; color:var(--color-hint);"></div>
        </div>

        <label style="display:block; margin:18px 0 6px;">Nuovo da Google Maps
            <span style="font-weight:400; color:var(--color-hint);">— usa crediti API</span>
        </label>
        <!-- Stessa ricerca, due destinazioni: un LUOGO/attività va in places/<IT+CAP>/<slug>,
             un'ORGANIZZAZIONE in organizations/<slug>. Il selettore riscrive @id e @type. -->
        <div style="display:flex; gap:8px; align-items:center; margin-bottom:8px; flex-wrap:wrap;">
            <span style="color:var(--color-hint);">Salva come:</span>
            <label><input type="radio" name="kind" value="place" checked> Luogo o attività</label>
            <label><input type="radio" name="kind" value="organization"> Organizzazione</label>
            <span id="kind-msg" style="font-size:13px; color:var(--color-hint);"></span>
        </div>
        <input type="text" id="place-search" class="search-box" placeholder="Cerca un NUOVO luogo su Google Maps…">
        <div id="debug-msg" style="font-size:13px;color:var(--color-hint);margin:-10px 0 16px;"></div>
        <div id="cap-fix" style="display:none; margin:0 0 16px; padding:12px; background:#fff3e0; border-left:5px solid #ff9800;">
            ⚠️ CAP non rilevato da Google (es. punto di confine). Impostalo per un @id valido:
            <input type="text" id="cap-input" maxlength="5" inputmode="numeric" placeholder="es. 00122"
                   style="padding:6px; font-size:15px; width:110px; margin-left:8px;">
        </div>

        <div class="grid">
            <div class="col">
                <label>JSON Originale (Google Maps)</label>
                <textarea id="json-google" readonly></textarea>
            </div>
            <div class="col">
                <label>JSON-LD Generato (WS CMS)</label>
                <textarea id="json-wscms"></textarea>
                <div class="save-bar">
                    <button type="button" id="btn-download" title="Scarica index.json in locale">
                        <span class="material-symbols-outlined">download</span> Scarica
                    </button>
                    <button type="button" id="btn-save" title="Salva sul server nella cartella dell'@id">
                        <span class="material-symbols-outlined">cloud_upload</span> Salva sul web
                    </button>
                    <span id="save-msg"></span>
                </div>
                <div style="margin-top:8px; font-size:13px; color:#555;">
                    Contributor autorizzati a modificare (Google UID, virgola-separati):
                    <input type="text" id="editors-input" list="users-datalist" placeholder="cerca per nome, pseudonimo o UID…" style="padding:6px; width:320px; margin:0 6px;">
                    <datalist id="users-datalist"></datalist>
                    <button type="button" id="editors-apply" style="padding:6px 10px; cursor:pointer;">Imposta contributor</button>
                </div>
            </div>
        </div>

        <div id="diff-panel">
            <h3 style="margin:0 0 4px;">⚠️ Esiste già: <span id="diff-id"></span></h3>
            <p style="margin:0 0 8px; color:#555;">Differenze tra la versione <b>salvata</b> e quella <b>nuova</b> (date escluse). Spunta le modifiche da integrare, poi scegli l'azione. Le spunte valgono solo per <b>Integra aggiornamenti</b>.</p>
            <div style="margin:0 0 6px; font-size:13px;">Seleziona: <a href="#" id="diff-all">tutte</a> · <a href="#" id="diff-none">nessuna</a></div>
            <div id="diff-content"></div>
            <div class="diff-btns">
                <button type="button" id="diff-ignore">Ignora</button>
                <button type="button" id="diff-overwrite">Sovrascrivi integralmente</button>
                <button type="button" id="diff-merge">Integra aggiornamenti</button>
            </div>
        </div>
    </div>
</div>

<script>
    let userJwtToken = "";
    let lastMedia = {}; // sorgenti cover/logo dal sito, dall'ultima ricerca

    // 1. Risposta Google Identity
    function handleCredentialResponse(response) {
        userJwtToken = response.credential;
        document.getElementById('login-buttons').style.display = 'none';
        
        // Invio Token al backend per verifica ruolo
        fetch('../google_place-json.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'auth', credential: userJwtToken })
        })
        .then(res => res.json())
        .then(data => applyUserState(data))
        .catch(err => alert("Errore di connessione al server."));
    }

    // 2. Applicazione Stati Visivi (0, 1, 2, 3)
    function applyUserState(userData) {
        if (userData.error) {
            document.getElementById('error-msg').innerText = userData.error;
            return;
        }

        const role = userData.role;

        // [1] logged-visitor
        if (role === 'logged-visitor') {
            document.getElementById('banner-unverified').style.display = 'block';
            return;
        }

        // [2] verified-visitor (o superiori): Stampa Info
        document.getElementById('google-user').style.display = 'block';
        document.getElementById('user-email-display').innerText = userData.email;
        
        // --- NUOVA RIGA INSERITA PER L'UID ---
        document.getElementById('user-uid-display').innerText = userData.uid; 
        
        document.getElementById('user-locale-display').innerText = userData.locale;
        document.getElementById('user-role-display').innerText = role;

        // [3] user, client, admin, super-admin: Sblocca Whitelist Area
        if (['user', 'client', 'admin', 'super-admin'].includes(role)) {
            document.getElementById('whitelist-area').style.display = 'block';
            // Prima le funzioni SENZA crediti Google (devono funzionare anche se la
            // chiave Maps manca): elenco salvati + contributor. L'autocomplete Google
            // è isolato in try/catch così un errore Maps non blocca la ricerca locale.
            loadSavedList();     // Elenco luoghi già salvati (ricerca locale, no crediti Google)
            loadUsersDatalist(); // Popola la ricerca contributor per nome/pseudonimo
            try { initAutocomplete(); } catch (e) { console.warn('Google Maps non disponibile:', e); }
            if (role === 'super-admin') document.getElementById('admin-tools').style.display = 'block';
        } else {
            // Se è solo 'verified-visitor', mostriamo un messaggio di divieto
            document.getElementById('error-msg').innerText = "Non sei presente in users.xml. Non hai accesso al tool di importazione.";
        }
    }

    // 3. Autocomplete e Chiamata API Search
    function initAutocomplete() {
        if (!(window.google && google.maps && google.maps.places)) {
            console.warn('Google Maps JS non caricato: ricerca NUOVI luoghi disabilitata (la ricerca locale resta attiva).');
            return;
        }
        const input = document.getElementById('place-search');
        const autocomplete = new google.maps.places.Autocomplete(input);

        autocomplete.addListener('place_changed', function() {
            const place = autocomplete.getPlace();
            if (!place.name) return;

            document.getElementById('error-msg').innerText = "Acquisizione dati in corso...";

            // Si manda il PLACE ID del luogo scelto, non il testo digitato: il
            // suggerimento che hai selezionato È già l'identità del luogo. Prima
            // partiva solo `query` e il server rifaceva una ricerca a testo libero
            // prendendo il primo risultato — che poteva essere un altro luogo, o
            // nessuno per i punti che la vecchia ricerca non indicizza.
            // Il server sa già gestirlo: è lo stesso percorso di «Aggiorna da
            // Google Maps», e costa una chiamata in meno.
            fetch('../google_place-json.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'search',
                    credential: userJwtToken,
                    place_id: place.place_id || '',
                    query: input.value
                })
            })
            .then(res => res.json())
            .then(data => applySearchResult(data));
        });
    }

    // Applica una risposta di 'search' (Google) all'editor. Con opts.mergeInto il
    // JSON fresco di Google viene FUSO sopra il salvato passato (i campi custom —
    // meetoo:isGroup, creator, contributor… — non presenti in Google restano); così
    // "Aggiorna da Google Maps" non azzera il lavoro fatto a mano, e il diff al
    // salvataggio mostra solo le vere differenze.
    function applySearchResult(data, opts) {
                opts = opts || {};
                if (data.error) {
                    document.getElementById('error-msg').innerText = data.error;
                    return;
                }
                const finalObj = opts.mergeInto ? deepMerge(opts.mergeInto, data.ws_cms) : data.ws_cms;
                document.getElementById('json-google').value = JSON.stringify(data.raw_google, null, 4);
                document.getElementById('json-wscms').value = JSON.stringify(finalObj, null, 4);
                lastMedia = data.media || {};
                document.getElementById('diff-panel').style.display = 'none';
                if (data.debug) {
                    console.log('[debug]', data.debug, 'media:', data.media);
                    const dbg = data.debug;
                    const idxInfo = dbg.index_exists
                        ? ('indice ' + dbg.index_count + ' voci, ' + (dbg.index_hit ? ('aggancio → ' + dbg.index_hit) : 'nessun aggancio'))
                        : '⚠️ INDICE ASSENTE (usa «Rigenera indice»)';
                    document.getElementById('debug-msg').innerText =
                        'Places API (New): ' + dbg.new_api +
                        ' · accessibilità: ' + dbg.accessibility_count +
                        ' · servizi: ' + dbg.amenity_count +
                        ' · sito: ' + (dbg.website || '(nessuno)') +
                        ' · cover: ' + (lastMedia.cover_src ? 'sì' : 'no') +
                        ' · logo: ' + (lastMedia.logo_src ? 'sì' : 'no') +
                        ' · place_id: ' + (dbg.place_id || '?') +
                        ' · ' + idxInfo;
                }

                // Stato @id: nuovo / già inserito (stesso Google ID) + aggiornamenti /
                // collisione (Google ID diverso o assente) / regione mancante.
                const id = data.ws_cms?.mainEntity?.['@id'] || '';
                const box = document.getElementById('error-msg');
                document.getElementById('cap-fix').style.display = data.id_region_missing ? 'block' : 'none';
                if (data.id_region_missing) document.getElementById('cap-input').value = '';
                let msg = "", color = "red";
                if (data.id_adopted) {
                    // Stesso Google Place ID già salvato: usato l'@id esistente.
                    msg = "✓ Stesso luogo già salvato: uso l'@id esistente " + id
                        + ". Salva per vedere il diff e scegliere cosa integrare (i campi corretti a mano restano se non li spunti).";
                    color = "#2e7d32";
                    if (data.updates && data.updates.length) {
                        msg += " Differenze da Google: " + data.updates
                            .map(u => u.field + ': "' + u.old + '" → "' + u.new + '"').join("; ") + ".";
                    }
                } else if (data.id_region_missing) {
                    msg = "⚠️ CAP non rilevato: imposta il CAP qui sopra per un @id valido (" + id + ").";
                    color = "#e65100";
                } else if (!data.id_exists) {
                    msg = ""; // nuovo, nessun problema
                } else if (data.id_same_place) {
                    msg = "✓ Luogo già inserito (stesso Google ID): collegato a " + id + ".";
                    if (data.updates && data.updates.length) {
                        msg += " Aggiornamenti da Google: " + data.updates
                            .map(u => u.field + ': "' + u.old + '" → "' + u.new + '"').join("; ") + ".";
                    }
                    color = "#2e7d32";
                } else if (data.id_parse_error) {
                    msg = "⚠️ Esiste già (" + id + ") ma l'index.json è illeggibile: verifica a mano.";
                    color = "#e65100";
                } else if (!data.id_has_stored_gid) {
                    msg = "⚠️ Esiste già (" + id + ") senza Google ID salvato: verifica se è lo stesso luogo.";
                    color = "#e65100";
                } else {
                    msg = "⚠️ Esiste già un @id diverso con questo slug (Google ID diverso): cambia l'id.";
                }
                // Deduplica via indice: stesso Google Place ID già salvato altrove.
                if (data.id_dup && data.id_dup['@id']) {
                    msg = "⚠️ Questo luogo è già salvato come " + data.id_dup['@id']
                        + (data.id_dup.name ? " (" + data.id_dup.name + ")" : "")
                        + ". Stesso Google Place ID: modifica quello esistente invece di creare un duplicato."
                        + (msg ? " — " + msg : "");
                    color = "#e65100";
                }
                box.style.color = color;
                box.innerText = msg;
                // Se la scheda è impostata su "Organizzazione", adegua @id/@type al volo.
                if (currentKind() === 'organization') applyKind();
    }

    // 4. Salvataggio: locale (download) e sul web (scrive <@id>/index.json).
    function setSaveMsg(text, kind) {
        const el = document.getElementById('save-msg');
        el.innerText = text;
        el.style.color = kind === 'ok' ? '#2e7d32' : (kind === 'err' ? '#c62828' : '#555');
    }

    function currentJsonLd() {
        const text = document.getElementById('json-wscms').value;
        try { return { text, obj: JSON.parse(text) }; }
        catch (e) { return { text, obj: null }; }
    }

    document.getElementById('btn-download').addEventListener('click', function () {
        const { text, obj } = currentJsonLd();
        if (!text.trim()) { setSaveMsg('Niente da scaricare.', 'err'); return; }
        if (!obj) { setSaveMsg('JSON non valido: correggi prima di scaricare.', 'err'); return; }
        const blob = new Blob([text], { type: 'application/json' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'index.json';
        document.body.appendChild(a); a.click(); document.body.removeChild(a);
        URL.revokeObjectURL(a.href);
        setSaveMsg('Scaricato index.json', 'ok');
    });

    // Salvataggio. mode: '' (prima volta) | ignore | overwrite | merge.
    // Se l'item esiste, il server risponde needs_confirm e mostriamo il diff.
    function doSave(mode, paths) {
        const { text, obj } = currentJsonLd();
        if (!obj) { setSaveMsg('JSON non valido: correggi prima di salvare.', 'err'); return; }
        const id = (obj.mainEntity && obj.mainEntity['@id']) || obj['@id'] || '';
        if (!id) { setSaveMsg('@id mancante nel JSON.', 'err'); return; }
        setSaveMsg('Salvataggio…', '');
        fetch('../google_place-json.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'save', credential: userJwtToken, jsonld: text, media: lastMedia, mode: mode || '', paths: paths || [] })
        })
        .then(res => res.json())
        .then(res => {
            if (res.error) { setSaveMsg('Errore: ' + res.error, 'err'); return; }
            if (res.needs_confirm) { showDiff(res.path, res.stored, obj); return; }
            hideDiff();
            if (res.ignored) { setSaveMsg('Ignorato: la versione salvata resta invariata.', 'ok'); return; }
            let m = (res.overwritten ? 'Aggiornato' : 'Salvato') + ' [' + (res.mode || 'new') + ']: ' + res.path;
            if (res.media_saved && res.media_saved.length) m += ' (+ ' + res.media_saved.join(', ') + ')';
            if (res.media_failed && res.media_failed.length) m += ' — non salvate: ' + res.media_failed.join(', ');
            if (res.media_debug) console.log('[media_debug]', res.media_debug);
            const md = res.media_debug || {};
            const reasons = Object.keys(md).map(k => k + ': ' + md[k]);
            if (reasons.length) m += ' — motivo → ' + reasons.join(' · ');
            setSaveMsg(m, res.media_failed && res.media_failed.length ? 'err' : 'ok');
        })
        .catch(() => setSaveMsg('Errore di connessione al server.', 'err'));
    }
    document.getElementById('btn-save').addEventListener('click', function () { doSave(''); });
    document.getElementById('diff-ignore').addEventListener('click', function () { doSave('ignore'); });
    document.getElementById('diff-overwrite').addEventListener('click', function () { doSave('overwrite'); });
    document.getElementById('diff-merge').addEventListener('click', function () {
        const cbs = document.querySelectorAll('#diff-content .diff-cb');
        const paths = Array.from(cbs).filter(c => c.checked).map(c => c.getAttribute('data-path'));
        if (cbs.length && !paths.length) { setSaveMsg('Nessuna modifica selezionata da integrare.', 'err'); return; }
        doSave('merge', paths);
    });
    document.getElementById('diff-all').addEventListener('click', function (e) {
        e.preventDefault();
        document.querySelectorAll('#diff-content .diff-cb').forEach(c => c.checked = true);
    });
    document.getElementById('diff-none').addEventListener('click', function (e) {
        e.preventDefault();
        document.querySelectorAll('#diff-content .diff-cb').forEach(c => c.checked = false);
    });

    function hideDiff() { document.getElementById('diff-panel').style.display = 'none'; }
    function showDiff(path, stored, next) {
        document.getElementById('diff-id').innerText = path;
        document.getElementById('diff-content').innerHTML = renderDiff(stored, next);
        document.getElementById('diff-panel').style.display = 'block';
        setSaveMsg('Esiste già: scegli come procedere (vedi diff sotto).', '');
        document.getElementById('diff-panel').scrollIntoView({ behavior: 'smooth' });
    }
    // Diff a livello di campo tra due JSON (percorsi puntati). Le date sono escluse.
    function flattenJson(obj, prefix, out) {
        out = out || {};
        if (obj && typeof obj === 'object' && !Array.isArray(obj)) {
            Object.keys(obj).forEach(k => flattenJson(obj[k], prefix ? prefix + '.' + k : k, out));
        } else if (Array.isArray(obj)) {
            out[prefix] = JSON.stringify(obj);
        } else {
            out[prefix] = obj;
        }
        return out;
    }
    function renderDiff(stored, next) {
        const skip = /(^|\.)(dateCreated|dateModified|creator|author|contributor)(\.|$)/;
        const a = flattenJson(stored || {}), b = flattenJson(next || {});
        const keys = Array.from(new Set(Object.keys(a).concat(Object.keys(b)))).sort();
        const esc = s => String(s).replace(/[&<>]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]));
        const rows = [];
        keys.forEach(k => {
            if (skip.test(k)) return;
            const va = a[k], vb = b[k];
            if (JSON.stringify(va) === JSON.stringify(vb)) return;
            let cls, body;
            if (va === undefined) { cls = 'diff-add'; body = '+ ' + esc(k) + ': ' + esc(vb); }
            else if (vb === undefined) { cls = 'diff-del'; body = '− ' + esc(k) + ': ' + esc(va); }
            else { cls = 'diff-chg'; body = '~ ' + esc(k) + ': ' + esc(va) + ' → ' + esc(vb); }
            rows.push('<label class="diff-row ' + cls + '"><input type="checkbox" class="diff-cb" data-path="' + esc(k) + '" checked> ' + body + '</label>');
        });
        return rows.length ? rows.join('') : '<div style="color:#2e7d32;">Nessuna differenza sostanziale (a parte le date).</div>';
    }

    // 5. CAP manuale: aggiorna postalCode e ricostruisce l'@id (<paese><CAP>).
    function applyCap(cap) {
        cap = (cap || '').trim();
        const ta = document.getElementById('json-wscms');
        let obj;
        try { obj = JSON.parse(ta.value); } catch (e) { return; }
        const ent = obj.mainEntity || obj;
        ent.address = ent.address || {};
        const country = ent.address.addressCountry || 'IT';
        ent.address.postalCode = cap;
        const parts = String(ent['@id'] || '').split('/'); // [folder, region, slug]
        if (parts.length === 3) {
            parts[1] = cap ? (country + cap) : '';
            ent['@id'] = parts.join('/');
        }
        ta.value = JSON.stringify(obj, null, 4);
        const box = document.getElementById('error-msg');
        if (cap && /^\d{5}$/.test(cap)) { box.style.color = '#2e7d32'; box.innerText = '✓ CAP impostato: @id ' + ent['@id']; }
        else { box.style.color = '#e65100'; box.innerText = '⚠️ CAP mancante o non valido (5 cifre): imposta il CAP per un @id valido.'; }
    }
    document.getElementById('cap-input').addEventListener('input', function () { applyCap(this.value); });

    // 6bis. Popola la datalist per cercare i contributor per nome/pseudonimo.
    // Ogni option ha value "Nome · Pseudonimo (UID)"; l'UID viene poi estratto.
    function loadUsersDatalist() {
        fetch('../google_place-json.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'users', credential: userJwtToken })
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            const dl = document.getElementById('users-datalist');
            if (!dl || !Array.isArray(d.users)) return;
            dl.innerHTML = '';
            d.users.forEach(function (u) {
                const label = [u.name, u.pseudonym].filter(Boolean).join(' · ') || u.uid;
                const opt = document.createElement('option');
                opt.value = label + ' (' + u.uid + ')';
                dl.appendChild(opt);
            });
        })
        .catch(function () { /* datalist opzionale: si può sempre digitare l'UID */ });
    }

    // 6ter. Rigenera l'indice di deduplica dal browser (solo super-admin).
    document.getElementById('rebuild-index').addEventListener('click', function () {
        const m = document.getElementById('rebuild-msg');
        m.style.color = '#555'; m.innerText = 'Rigenerazione…';
        fetch('../google_place-json.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'rebuild_index', credential: userJwtToken })
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.error) { m.style.color = '#c62828'; m.innerText = d.error; return; }
            let t = '✓ Indice rigenerato: ' + d.count + ' voci.';
            if (d.conflicts && d.conflicts.length) {
                m.style.color = '#e65100';
                t += ' ⚠️ ' + d.conflicts.length + ' conflitti (stesso place_id, @id diversi): '
                    + d.conflicts.map(function (c) { return c.ids.join(' ↔ '); }).join('; ');
            } else {
                m.style.color = '#2e7d32';
            }
            m.innerText = t;
        })
        .catch(function () { m.style.color = '#c62828'; m.innerText = 'Errore di rete.'; });
    });

    // 6. Contributor: imposta contributor (Person link users/<uid>) nel JSON. Il
    // gate del server consente la modifica solo a creator/contributor/super-admin.
    // Accetta "Nome (UID)" dalla datalist, "users/<uid>" o l'UID nudo.
    document.getElementById('editors-apply').addEventListener('click', function () {
        const raw = document.getElementById('editors-input').value || '';
        const list = [];
        raw.split(',').forEach(function (tok) {
            tok = tok.trim();
            if (!tok) return;
            const m = tok.match(/\((\d{6,})\)\s*$/) || tok.match(/(\d{6,})\s*$/);
            if (m) list.push('users/' + m[1]);
        });
        const ta = document.getElementById('json-wscms');
        let obj;
        try { obj = JSON.parse(ta.value); } catch (e) { setSaveMsg('JSON non valido: correggi prima.', 'err'); return; }
        const ent = obj.mainEntity || obj;
        if (list.length) ent['contributor'] = list.map(function (id) { return { '@type': 'Person', '@id': id }; });
        else delete ent['contributor'];
        ta.value = JSON.stringify(obj, null, 4);
        setSaveMsg('Contributor impostati: ' + (list.join(', ') || '(nessuno)') + '. Salva per applicare.', 'ok');
    });

    // 6-quater. Luogo o organizzazione: stessa scheda, destinazione diversa.
    //   luogo        → places/<IT+CAP>/<slug>, @type Place|LocalBusiness (da Google)
    //   organizzazione → organizations/<slug>, @type Organization
    // Riscrive @id e @type nel JSON già caricato: il resto dei dati (indirizzo,
    // contatti, sito, immagini) vale per entrambi.
    function currentKind() {
        const r = document.querySelector('input[name="kind"]:checked');
        return r ? r.value : 'place';
    }
    function applyKind() {
        const kind = currentKind();
        const ta = document.getElementById('json-wscms');
        const msg = document.getElementById('kind-msg');
        if (!ta.value.trim()) { msg.textContent = kind === 'organization' ? 'La prossima ricerca creerà un\'organizzazione.' : ''; return; }
        let obj;
        try { obj = JSON.parse(ta.value); } catch (e) { msg.textContent = 'JSON non valido: correggi prima.'; return; }
        const ent = obj.mainEntity || obj;
        const parts = String(ent['@id'] || '').split('/');
        const slug = parts[parts.length - 1] || '';
        if (!slug) { msg.textContent = 'Manca l\'@id: fai prima una ricerca.'; return; }

        if (kind === 'organization') {
            ent['@id'] = 'organizations/' + slug;
            ent['@type'] = 'Organization';
        } else {
            // Torna a luogo: serve la regione (IT+CAP) dall'indirizzo.
            const a = ent.address || {};
            const region = (a.addressCountry || 'IT') + (a.postalCode || '');
            ent['@id'] = 'places/' + region + '/' + slug;
            if (ent['@type'] === 'Organization') ent['@type'] = 'LocalBusiness';
        }
        ta.value = JSON.stringify(obj, null, 4);
        msg.textContent = '@id → ' + ent['@id'];
        msg.style.color = /\/\//.test(ent['@id']) ? 'var(--color-warning)' : 'var(--color-hint)';
    }
    document.querySelectorAll('input[name="kind"]').forEach((r) => r.addEventListener('change', applyKind));
    // Arrivando da «Nuova organizzazione» (hub admin) la scheda parte già impostata.
    if (new URLSearchParams(location.search).get('as') === 'organization') {
        const r = document.querySelector('input[name="kind"][value="organization"]');
        if (r) { r.checked = true; applyKind(); }
    }

    // 7. Luoghi già salvati: ricerca LOCALE (nessun credito Google) + apertura +
    //    aggiornamento on-demand da Google Maps sul google_place_id salvato.
    let savedItems = [];      // {@id, name, @type} da action=editable (solo places/)
    let loadedId = '';        // @id del luogo attualmente caricato dal salvato
    let loadedPlaceId = '';   // google_place_id salvato (per "Aggiorna da Google Maps")
    let loadedObj = null;     // JSON salvato caricato (base del merge in aggiornamento)

    // Merge profondo: i valori di $over vincono; le chiavi solo in $base restano; le
    // liste vengono sostituite intere. Speculare a ws_deep_merge lato server.
    function deepMerge(base, over) {
        if (over === null || typeof over !== 'object' || Array.isArray(over)) return over;
        if (base === null || typeof base !== 'object' || Array.isArray(base)) return over;
        const out = Object.assign({}, base);
        Object.keys(over).forEach(function (k) { out[k] = (k in base) ? deepMerge(base[k], over[k]) : over[k]; });
        return out;
    }
    function savedMsg(text, kind) {
        const el = document.getElementById('saved-msg');
        el.innerText = text;
        el.style.color = kind === 'ok' ? '#2e7d32' : (kind === 'err' ? '#c62828' : 'var(--color-hint)');
    }

    // Elenco dei luoghi editabili (action=editable): nessun credito Google. Tiene
    // solo places/ (il tool salva sotto places/); popola la datalist per nome.
    function loadSavedList() {
        fetch('../google_place-json.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'editable', credential: userJwtToken })
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            const dl = document.getElementById('places-datalist');
            if (d.error) { savedMsg(d.error, 'err'); return; }
            savedItems = (Array.isArray(d.items) ? d.items : []).filter(function (i) { return /^places\//.test(i['@id'] || ''); });
            if (dl) {
                dl.innerHTML = '';
                savedItems.forEach(function (i) {
                    const opt = document.createElement('option');
                    opt.value = (i.name || i['@id']) + ' — ' + i['@id']; // @id nel value = risoluzione univoca
                    dl.appendChild(opt);
                });
            }
            savedMsg(savedItems.length + ' luoghi salvati. Cerca per nome e «Apri salvato» (nessun credito Google).');
        })
        .catch(function () { savedMsg('Impossibile caricare l\'elenco dei luoghi salvati.', 'err'); });
    }
    // Dal valore scelto ("Nome — places/…") ricava l'@id; fallback: nome esatto.
    function resolveSavedId(value) {
        value = (value || '').trim();
        if (!value) return '';
        const m = value.match(/—\s*(places\/[^\s]+)\s*$/);
        if (m) return m[1];
        const byName = savedItems.find(function (i) { return (i.name || '').toLowerCase() === value.toLowerCase(); });
        return byName ? byName['@id'] : '';
    }
    // Apre un luogo salvato (action=load): riempie l'editor SENZA toccare Google.
    /* `comeCopia`: apre il documento come BASE per uno nuovo.
     *
     * Che cosa si toglie, e perché: l'@id (è il percorso — tenerlo vorrebbe dire
     * riscrivere l'originale) e il Google Place ID (identifica UN posto sulla mappa:
     * due schede con lo stesso finirebbero per essere lo stesso luogo salvato due
     * volte, ed è la ragione per cui il salvataggio le rifiuta). Resta tutto il
     * resto: è quello che si voleva riusare. */
    function openSaved(comeCopia) {
        const id = resolveSavedId(document.getElementById('saved-search').value);
        if (!id) { savedMsg('Scegli un luogo dall\'elenco (o scrivi il nome esatto).', 'err'); return; }
        savedMsg(comeCopia ? 'Duplicazione…' : 'Apertura…');
        fetch('../google_place-json.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'load', credential: userJwtToken, id: id })
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.error) { savedMsg(d.error, 'err'); return; }
            loadedObj = d.json;
            loadedId = d.id || id;
            const ent = (d.json && (d.json.mainEntity || d.json)) || {};
            loadedPlaceId = ent['meetoo:google_place_id'] || ent['meetoo:googlePlaceId'] || '';
            if (comeCopia) {
                delete ent['@id'];
                delete ent['meetoo:google_place_id'];
                delete ent['meetoo:googlePlaceId'];
                delete ent['dateCreated'];
                delete ent['dateModified'];
                loadedId = '';
                loadedPlaceId = '';
            }
            document.getElementById('json-wscms').value = JSON.stringify(d.json, null, 4);
            document.getElementById('json-google').value = '';
            lastMedia = {};
            document.getElementById('diff-panel').style.display = 'none';
            document.getElementById('error-msg').innerText = '';
            document.getElementById('btn-refresh-google').style.display = loadedPlaceId ? 'inline-flex' : 'none';
            // Il selettore riflette ciò che si è aperto (luogo o organizzazione).
            const kindNow = /^organizations\//.test(loadedId) ? 'organization' : 'place';
            const rad = document.querySelector('input[name="kind"][value="' + kindNow + '"]');
            if (rad) rad.checked = true;
            if (comeCopia) {
                savedMsg('Copia di «' + id + '»: dài un @id nuovo (e, se serve, cercalo su Google per riagganciare la mappa), poi salva.', 'ok');
                return;
            }
            savedMsg('✓ Caricato: ' + loadedId + (loadedPlaceId
                ? '. Modifica e Salva, oppure «Aggiorna da Google Maps».'
                : ' (nessun Google Place ID salvato: per aggiornarlo cercalo su Google per nome).'), 'ok');
        })
        .catch(function () { savedMsg('Errore di rete nell\'apertura.', 'err'); });
    }
    // Aggiorna il luogo caricato con i dati freschi di Google (Details sul place_id
    // salvato): fonde Google SOPRA il salvato (campi custom preservati); poi Salva
    // mostra il diff col salvato per integrare selettivamente.
    function refreshFromGoogle() {
        if (!loadedPlaceId) { savedMsg('Nessun Google Place ID salvato per questo luogo.', 'err'); return; }
        savedMsg('Aggiornamento da Google Maps…');
        const base = currentJsonLd().obj || loadedObj;
        fetch('../google_place-json.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'search', credential: userJwtToken, place_id: loadedPlaceId })
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.error) { savedMsg(d.error, 'err'); return; }
            applySearchResult(d, { mergeInto: base });
            const n = (d.updates && d.updates.length) ? d.updates.length : 0;
            savedMsg('✓ Dati Google aggiornati nell\'editor' + (n ? ' (' + n + ' differenze)' : '')
                + '. Salva per rivedere il diff col salvato e integrare selettivamente.', 'ok');
        })
        .catch(function () { savedMsg('Errore di rete nell\'aggiornamento.', 'err'); });
    }
    document.getElementById('saved-open').addEventListener('click', function () { openSaved(false); });
    document.getElementById('saved-copy').addEventListener('click', function () { openSaved(true); });
    document.getElementById('btn-refresh-google').addEventListener('click', refreshFromGoogle);
    document.getElementById('saved-search').addEventListener('change', function () {
        if (resolveSavedId(this.value)) openSaved(false); // scelta dalla datalist → apri
    });
</script>

<script>window.MEETOO_HEADER = { noAuth: true };</script>
<script src="../../../ws-custom/themes/meetoo/header.js"></script>
<script>
// Breadcrumb dell admin: "Gestione" risale all hub, la voce corrente dice dove sei.
(function crumb() {
  if (!window.Meetoo) { setTimeout(crumb, 100); return; }
  var root = location.pathname.replace(/\/ws-admin\/.*/, "/");
  Meetoo.setBreadcrumb([
    { label: "Gestione", href: root + "ws-admin/index.php", title: "Amministrazione" },
    { label: "Luoghi", current: true },
  ]);
})();
</script>
</body>
</html>