<?php
// Chiave Maps JS da config.php (gitignored). Vedi config.sample.php.
$config = @include __DIR__ . '/config.php';
$mapsJsKey = is_array($config) ? ($config['maps_js_key'] ?? '') : '';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>WS CMS - Data Ingestion Places</title>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <script src="https://maps.googleapis.com/maps/api/js?key=<?php echo htmlspecialchars($mapsJsKey, ENT_QUOTES); ?>&libraries=places"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <style>
        .material-symbols-outlined { font-size: 18px; vertical-align: -4px; }
        body { font-family: Arial, sans-serif; padding: 20px; background: #f4f4f9; }
        .container { max-width: 1200px; margin: auto; }
        .header { margin-bottom: 20px; }
        
        /* Gestione Visibilità Stati */
        #banner-unverified, #google-user, #whitelist-area { display: none; }
        
        /* Stili Elementi */
        #banner-unverified { background: #ffeb3b; padding: 15px; border-left: 5px solid #ff9800; font-weight: bold; margin-bottom: 20px; }
        #google-user { background: #e3f2fd; padding: 15px; border-left: 5px solid #2196f3; margin-bottom: 20px; }
        .search-box { width: 100%; padding: 15px; font-size: 18px; margin-bottom: 20px; box-sizing: border-box; }
        .grid { display: flex; gap: 20px; }
        .col { flex: 1; display: flex; flex-direction: column; }
        textarea { width: 100%; height: 500px; padding: 10px; font-family: monospace; background: #2d2d2d; color: #fff; border: none; border-radius: 5px; box-sizing: border-box; resize: none; }
        label { font-weight: bold; margin-bottom: 5px; }
        #error-msg { color: red; font-weight: bold; margin-bottom: 15px; }
        .save-bar { display: flex; align-items: center; gap: 10px; margin-top: 10px; }
        .save-bar button {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 14px; font-size: 14px; font-weight: bold; cursor: pointer;
            border: none; border-radius: 5px; background: #e0e0e0; color: #222;
        }
        .save-bar button:hover { background: #d5d5d5; }
        #btn-save { background: #2196f3; color: #fff; }
        #btn-save:hover { background: #1976d2; }
        #save-msg { font-weight: bold; }
        #diff-panel { display:none; margin-top:20px; padding:16px; background:#fff; border:1px solid #ddd; border-radius:6px; }
        #diff-content { max-height:340px; overflow:auto; font-family:monospace; font-size:13px; border:1px solid #eee; padding:10px; border-radius:4px; background:#fafafa; }
        .diff-row { display:block; padding:2px 0; white-space:pre-wrap; word-break:break-word; cursor:pointer; }
        .diff-row input { vertical-align:middle; margin-right:4px; }
        .diff-add { color:#2e7d32; }
        .diff-del { color:#c62828; }
        .diff-chg { color:#e65100; }
        .diff-btns { margin-top:12px; display:flex; gap:10px; flex-wrap:wrap; }
        .diff-btns button { padding:8px 14px; font-size:14px; font-weight:bold; cursor:pointer; border:none; border-radius:5px; background:#e0e0e0; color:#222; }
        #diff-overwrite { background:#ef6c00; color:#fff; }
        #diff-merge { background:#2196f3; color:#fff; }
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
        <input type="text" id="place-search" class="search-box" placeholder="Cerca il luogo da importare (es. L'Amanusa Beach Ostia)...">
        <div id="debug-msg" style="font-size:13px;color:#555;margin:-10px 0 16px;"></div>
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
        fetch('google_place-json.php', {
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
            initAutocomplete(); // Inizializza Google Maps Autocomplete
            loadUsersDatalist(); // Popola la ricerca contributor per nome/pseudonimo
        } else {
            // Se è solo 'verified-visitor', mostriamo un messaggio di divieto
            document.getElementById('error-msg').innerText = "Non sei presente in users.xml. Non hai accesso al tool di importazione.";
        }
    }

    // 3. Autocomplete e Chiamata API Search
    function initAutocomplete() {
        const input = document.getElementById('place-search');
        const autocomplete = new google.maps.places.Autocomplete(input);

        autocomplete.addListener('place_changed', function() {
            const place = autocomplete.getPlace();
            if (!place.name) return;

            document.getElementById('error-msg').innerText = "Acquisizione dati in corso...";
            
            fetch('google_place-json.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'search',
                    credential: userJwtToken,
                    query: input.value
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.error) {
                    document.getElementById('error-msg').innerText = data.error;
                    return;
                }
                document.getElementById('json-google').value = JSON.stringify(data.raw_google, null, 4);
                document.getElementById('json-wscms').value = JSON.stringify(data.ws_cms, null, 4);
                lastMedia = data.media || {};
                document.getElementById('diff-panel').style.display = 'none';
                if (data.debug) {
                    console.log('[debug]', data.debug, 'media:', data.media);
                    const dbg = data.debug;
                    document.getElementById('debug-msg').innerText =
                        'Places API (New): ' + dbg.new_api +
                        ' · accessibilità: ' + dbg.accessibility_count +
                        ' · servizi: ' + dbg.amenity_count +
                        ' · sito: ' + (dbg.website || '(nessuno)') +
                        ' · cover: ' + (lastMedia.cover_src ? 'sì' : 'no') +
                        ' · logo: ' + (lastMedia.logo_src ? 'sì' : 'no');
                }

                // Stato @id: nuovo / già inserito (stesso Google ID) + aggiornamenti /
                // collisione (Google ID diverso o assente) / regione mancante.
                const id = data.ws_cms?.mainEntity?.['@id'] || '';
                const box = document.getElementById('error-msg');
                document.getElementById('cap-fix').style.display = data.id_region_missing ? 'block' : 'none';
                if (data.id_region_missing) document.getElementById('cap-input').value = '';
                let msg = "", color = "red";
                if (data.id_region_missing) {
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
            });
        });
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
        fetch('google_place-json.php', {
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
        fetch('google_place-json.php', {
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
</script>

</body>
</html>