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

        // [3] user, client, admin: Sblocca Whitelist Area
        if (['user', 'client', 'admin'].includes(role)) {
            document.getElementById('whitelist-area').style.display = 'block';
            initAutocomplete(); // Inizializza Google Maps Autocomplete
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

                // Stato @id: nuovo / già inserito (stesso Google ID) + aggiornamenti /
                // collisione (Google ID diverso o assente) / regione mancante.
                const id = data.ws_cms?.mainEntity?.['@id'] || '';
                const box = document.getElementById('error-msg');
                let msg = "", color = "red";
                if (data.id_region_missing) {
                    msg = "⚠️ CAP/paese non rilevati: la regione nell'@id è vuota (" + id + "). Compila la regione a mano.";
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

    document.getElementById('btn-save').addEventListener('click', function () {
        const { text, obj } = currentJsonLd();
        if (!obj) { setSaveMsg('JSON non valido: correggi prima di salvare.', 'err'); return; }
        const id = (obj.mainEntity && obj.mainEntity['@id']) || obj['@id'] || '';
        if (!id) { setSaveMsg('@id mancante nel JSON.', 'err'); return; }
        if (!confirm('Salvare sul server in ' + id + '/index.json ?')) return;
        setSaveMsg('Salvataggio…', '');
        fetch('google_place-json.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'save', credential: userJwtToken, jsonld: text, media: lastMedia })
        })
        .then(res => res.json())
        .then(res => {
            if (res.error) { setSaveMsg('Errore: ' + res.error, 'err'); return; }
            let m = (res.overwritten ? 'Aggiornato' : 'Salvato') + ': ' + res.path;
            if (res.media_saved && res.media_saved.length) m += ' (+ ' + res.media_saved.join(', ') + ')';
            setSaveMsg(m, 'ok');
        })
        .catch(() => setSaveMsg('Errore di connessione al server.', 'err'));
    });
</script>

</body>
</html>