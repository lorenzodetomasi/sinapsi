<?php
// Modello di configurazione. Copia questo file in `config.php` (gitignored) e
// inserisci le tue chiavi. `config.php` NON va committato.
return [
    // Chiave Google Maps JavaScript API (client, usata in place-add.php).
    // Esposta nell'HTML: restringila per referrer *.isotype.org su Google Cloud.
    'maps_js_key' => '',

    // Chiave per le Places API server-side (google_place-json.php).
    // Tienila separata dalla precedente e restringila per IP.
    'places_api_key' => '',
];
