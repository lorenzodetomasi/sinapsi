<?php
// Helper Google Places condivisi tra il backend (google_place-json.php) e gli
// script CLI (es. enrich-place.php). La chiave va passata dal chiamante (config.php).

if (!function_exists('googleGet')) {
    // GET su Google che cattura ANCHE le risposte di errore (es. 403/REQUEST_DENIED),
    // così possiamo leggere status/error_message. Con ignore_errors evitiamo anche il
    // warning PHP di file_get_contents, che conterrebbe l'URL con la key in chiaro.
    function googleGet($url) {
        $ctx = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 10]]);
        $body = @file_get_contents($url, false, $ctx);
        return $body === false ? null : json_decode($body, true);
    }
}

if (!function_exists('enrichPlaceWithGoogleAPI')) {
    // Arricchisce un item (fermata/place) con geo e indirizzo da Google Places
    // (textsearch per nome), SOLO se mancanti. Adattamento del suggerimento di Gemini
    // al codebase: usa googleGet() e la chiave da config.php (niente 'TUA_API_KEY' né
    // curl). Ritorna true se ha trovato un match. NB: formatted_address è l'indirizzo
    // COMPLETO (via + città + paese); per un streetAddress "pulito" servirebbe una
    // chiamata Place Details con address_components — qui teniamo la versione semplice.
    function enrichPlaceWithGoogleAPI($placeName, &$item, $apiKey) {
        $placeName = trim((string)$placeName);
        if ($placeName === '' || (isset($item['geo']) && isset($item['address']))) return false;
        $url = "https://maps.googleapis.com/maps/api/place/textsearch/json?query="
             . urlencode($placeName . " Ostia") . "&language=it&region=IT&key=" . $apiKey;
        $data = googleGet($url);
        if (($data['status'] ?? '') !== 'OK' || empty($data['results'])) return false;
        $best = $data['results'][0];
        $loc = $best['geometry']['location'] ?? null;
        if (!isset($item['geo']) && $loc) {
            $item['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => $loc['lat'], 'longitude' => $loc['lng']];
        }
        if (!isset($item['address']) && !empty($best['formatted_address'])) {
            $item['address'] = ['@type' => 'PostalAddress', 'streetAddress' => $best['formatted_address'],
                                'addressLocality' => 'Lido di Ostia', 'addressCountry' => 'IT'];
        }
        return true;
    }
}
