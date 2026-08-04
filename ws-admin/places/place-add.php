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
    <style>
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
                <textarea id="json-wscms" readonly></textarea>
            </div>
        </div>
    </div>
</div>

<script>
    let userJwtToken = "";

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

                // Avviso su @id: regione mancante o cartella già esistente.
                const id = data.ws_cms?.mainEntity?.['@id'] || '';
                let warn = "";
                if (data.id_region_missing) {
                    warn = "⚠️ CAP/paese non rilevati: la regione nell'@id è vuota (" + id + "). Compila la regione a mano.";
                } else if (data.id_exists) {
                    warn = "⚠️ Esiste già un elemento con questo @id (" + id + "): cambia l'id prima di salvare.";
                }
                document.getElementById('error-msg').innerText = warn;
            });
        });
    }
</script>

</body>
</html>