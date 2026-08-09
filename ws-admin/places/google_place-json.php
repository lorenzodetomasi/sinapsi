<?php
/*
Per testare l'autenticazione (Ruolo):
tuosito.com/google_place-json.php?action=auth&token=IL_TUO_LUNGHISSIMO_TOKEN_JWT

Per testare l'importazione di un luogo:
tuosito.com/google_place-json.php?action=search&token=IL_TUO_LUNGHISSIMO_TOKEN_JWT&query=L'Amanusa+Beach+Ostia
*/
header('Content-Type: application/json');

// Chiavi da config.php (gitignored). Vedi config.sample.php.
$config = @include __DIR__ . '/config.php';
if (!is_array($config) || empty($config['places_api_key'])) {
    echo json_encode(["error" => "Configurazione mancante: crea config.php da config.sample.php."]);
    exit;
}
$googleApiKey = $config['places_api_key'];

// GET su Google che cattura ANCHE le risposte di errore (es. 403/REQUEST_DENIED),
// così possiamo leggere status/error_message. Con ignore_errors evitiamo anche il
// warning PHP di file_get_contents, che conterrebbe l'URL con la key in chiaro.
function googleGet($url) {
    $ctx = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 10]]);
    $body = @file_get_contents($url, false, $ctx);
    return $body === false ? null : json_decode($body, true);
}

// --- Generazione @type / @id dei places (stessa logica di events/edit) ---
// Tipo primario: LocalBusiness se è un'attività (establishment), altrimenti Place.
function ws_primary_type($types) {
    return in_array('establishment', (array)$types, true) ? 'LocalBusiness' : 'Place';
}
function ws_folder($type) {
    return $type === 'LocalBusiness' ? 'localbusinesses' : 'places';
}
function ws_slug($name) {
    return strtolower(preg_replace('/[^a-z0-9]/i', '', (string)$name));
}
// Verifica se la cartella dell'@id esiste già in ws-custom.
function ws_id_exists($id) {
    if (!preg_match('#^(places|localbusinesses)/[A-Za-z0-9._-]+/[A-Za-z0-9._-]+$#', $id)) return false;
    $dir = __DIR__ . '/../../ws-custom/contents/meetoo/it_IT/' . $id;
    return is_dir($dir) || is_file($dir . '/index.json') || is_file($dir . '/index.xml');
}
// Legge i dati salvati di un @id. Ritorna [google_place_id|null, stored[], parseError].
function ws_read_stored($id) {
    $path = __DIR__ . '/../../ws-custom/contents/meetoo/it_IT/' . $id . '/index.json';
    if (!is_file($path)) return [null, [], false];
    $data = json_decode(@file_get_contents($path), true);
    if (!is_array($data)) return [null, [], true]; // index.json illeggibile
    $m = $data['mainEntity'] ?? $data;
    $gid = $m['meetoo:google_place_id'] ?? $m['meetoo:googlePlaceId'] ?? null;
    $addr = is_array($m['address'] ?? null) ? $m['address'] : [];
    $stored = [
        'name' => (string)($m['name'] ?? ''),
        'url' => (string)($m['url'] ?? ''),
        'streetAddress' => (string)($addr['streetAddress'] ?? ''),
        'postalCode' => (string)($addr['postalCode'] ?? ''),
        'addressLocality' => (string)($addr['addressLocality'] ?? ''),
        'ratingValue' => $m['aggregateRating']['ratingValue'] ?? null,
        'reviewCount' => $m['aggregateRating']['reviewCount'] ?? null,
    ];
    return [$gid, $stored, false];
}

// Fascia di prezzo indicativa da price_level (0-4). Google NON dà importi reali.
function ws_price_range($level) {
    $bands = [0 => 'Gratis', 1 => '€0-15', 2 => '€15-30', 3 => '€30-60', 4 => '€60+'];
    return array_key_exists((int)$level, $bands) ? $bands[(int)$level] : '';
}

// Accessibilità dalla Places API (New): richiede "Places API (New)" abilitata
// sulla stessa key server. Ritorna le feature attive (stile schema.org).
function ws_accessibility($placeId, $key) {
    $url = "https://places.googleapis.com/v1/places/" . rawurlencode($placeId);
    $ctx = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 10,
        'header' => "X-Goog-Api-Key: $key\r\nX-Goog-FieldMask: accessibilityOptions\r\n"]]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) return [];
    $d = json_decode($body, true);
    $a = is_array($d) ? ($d['accessibilityOptions'] ?? []) : [];
    $features = [];
    foreach (['wheelchairAccessibleEntrance', 'wheelchairAccessibleRestroom',
              'wheelchairAccessibleParking', 'wheelchairAccessibleSeating'] as $k) {
        if (!empty($a[$k])) $features[] = $k;
    }
    return $features;
}

// --- Estrazione immagini dal sito del locale (niente foto Google) ---
function ws_meta_content($html, $prop) {
    $p = preg_quote($prop, '/');
    if (preg_match('/<meta[^>]+(?:property|name)=["\']' . $p . '["\'][^>]*content=["\']([^"\']+)["\']/i', $html, $m)) return html_entity_decode($m[1], ENT_QUOTES);
    if (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]*(?:property|name)=["\']' . $p . '["\']/i', $html, $m)) return html_entity_decode($m[1], ENT_QUOTES);
    return '';
}
function ws_link_href($html, $rel) {
    $r = preg_quote($rel, '/');
    if (preg_match('/<link[^>]+rel=["\'][^"\']*' . $r . '[^"\']*["\'][^>]*href=["\']([^"\']+)["\']/i', $html, $m)) return html_entity_decode($m[1], ENT_QUOTES);
    if (preg_match('/<link[^>]+href=["\']([^"\']+)["\'][^>]*rel=["\'][^"\']*' . $r . '[^"\']*["\']/i', $html, $m)) return html_entity_decode($m[1], ENT_QUOTES);
    return '';
}
function ws_abs_url($u, $base) {
    if ($u === '') return '';
    if (preg_match('#^https?://#i', $u)) return $u;
    $p = @parse_url($base);
    if (!$p || empty($p['scheme']) || empty($p['host'])) return '';
    $origin = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
    if (strpos($u, '//') === 0) return $p['scheme'] . ':' . $u;
    if ($u[0] === '/') return $origin . $u;
    $dir = isset($p['path']) ? preg_replace('#/[^/]*$#', '/', $p['path']) : '/';
    return $origin . $dir . $u;
}
// Legge og:image (cover) e og:logo/apple-touch-icon (logo) dal sito.
function ws_site_images($website) {
    $out = ['cover' => '', 'logo' => ''];
    if (!$website) return $out;
    $ctx = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 8,
        'header' => "User-Agent: Mozilla/5.0\r\n", 'follow_location' => 1, 'max_redirects' => 3]]);
    $html = @file_get_contents($website, false, $ctx);
    if ($html === false) return $out;
    $html = substr($html, 0, 500000);
    $cover = ws_meta_content($html, 'og:image');
    $logo = ws_meta_content($html, 'og:logo');
    if (!$logo) $logo = ws_link_href($html, 'apple-touch-icon');
    if (!$logo) $logo = ws_link_href($html, 'icon');
    $out['cover'] = ws_abs_url($cover, $website);
    $out['logo'] = ws_abs_url($logo, $website);
    return $out;
}
function ws_img_ext($url, $default) {
    if (preg_match('/\.(jpe?g|png|webp|gif|svg)(?:[?#]|$)/i', $url, $m)) {
        $e = strtolower($m[1]);
        return $e === 'jpeg' ? 'jpg' : $e;
    }
    return $default;
}
// Scarica un'immagine e la salva su disco (con limiti). Ritorna true/false.
function ws_download_image($url, $destPath) {
    if (!$url) return false;
    $ctx = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 15,
        'header' => "User-Agent: Mozilla/5.0\r\n", 'follow_location' => 1, 'max_redirects' => 3]]);
    $bin = @file_get_contents($url, false, $ctx);
    if ($bin === false || strlen($bin) < 100 || strlen($bin) > 8 * 1024 * 1024) return false;
    $dir = dirname($destPath);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) return false;
    return @file_put_contents($destPath, $bin) !== false;
}

// 1. Lettura dei dati (Supporto ibrido: POST JSON payload o GET Query String)
$data = json_decode(file_get_contents('php://input'), true);

// Fallback: se non c'è nel JSON, cerchiamo nell'URL (?action=...&token=...&query=...)
$action = $data['action'] ?? $_GET['action'] ?? '';
$jwtToken = $data['credential'] ?? $_GET['token'] ?? '';

if (empty($jwtToken)) {
    echo json_encode(["error" => "Token mancante. Passalo via POST o nell'URL con ?token="]);
    exit;
}

// Quando l'azione è search, assicuriamoci di poter leggere la query anche dall'URL
$searchQuery = $data['query'] ?? $_GET['query'] ?? '';

// 1. Verifica del Token Google (Autenticazione)
$tokenInfoUrl = "https://oauth2.googleapis.com/tokeninfo?id_token=" . $jwtToken;
$tokenResponse = @file_get_contents($tokenInfoUrl);
$userInfo = json_decode($tokenResponse, true);

// Verifichiamo l'esistenza del campo 'sub' (che è il Google User UID)
if (isset($userInfo['error']) || empty($userInfo['sub'])) {
    echo json_encode(["error" => "Autenticazione Google fallita o token scaduto."]);
    exit;
}

// Estraiamo il Google UID e altre info utili
$userUid = $userInfo['sub']; // Es. 100449157359400577039
$userEmail = $userInfo['email'] ?? ''; 
$isEmailVerified = $userInfo['email_verified'] ?? false;
$userLocale = $userInfo['locale'] ?? 'it';

// 2. Determinazione del Ruolo dell'utente
$userRole = 'logged-visitor'; 

if ($isEmailVerified) {
    $userRole = 'verified-visitor';

    $usersXmlPath = '../../ws-custom/contents/meetoo/it_IT/users/users.xml';

    if (file_exists($usersXmlPath)) {
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        
        libxml_use_internal_errors(true);
        
        // Flag LIBXML_XINCLUDE e LIBXML_NOENT garantiscono la risoluzione delle entità e degli include
        $dom->load($usersXmlPath, LIBXML_XINCLUDE | LIBXML_NOENT | LIBXML_NONET);

        // Risolviamo gli XInclude "best effort". Gli include ANNIDATI (es. il
        // profilo in persons/) possono fallire o generare warning NON fatali, ma
        // il RUOLO è già nel nodo <user> incluso al primo livello. Perciò NON
        // blocchiamo la lettura su libxml_get_errors(): era proprio quel guard a
        // scartare il risultato (l'include di persons/ produce il warning
        // "Namespace prefix xi on include is not defined"), lasciando il ruolo a
        // 'verified-visitor'.
        $dom->xinclude(LIBXML_XINCLUDE);
        libxml_clear_errors();

        $xml = simplexml_import_dom($dom);

        // Cerchiamo l'utente tramite il suo Google UID e leggiamo il ruolo
        $foundUser = $xml->xpath("//user[@id='$userUid']");
        if (!empty($foundUser) && isset($foundUser[0]->role) && (string)$foundUser[0]->role !== '') {
            $userRole = (string)$foundUser[0]->role;
        }
    }
}

// 3. Risposta alla richiesta di Autorizzazione (Login frontend)
if ($action === 'auth') {
    echo json_encode([
        'uid' => $userUid,
        'email' => $userEmail, // Utile da mostrare visivamente in UI
        'role' => $userRole,
        'locale' => $userLocale
    ]);
    exit;
}

// 3-bis. Salvataggio sul server: scrive il JSON-LD in <@id>/index.json.
// Operazione che MODIFICA il filesystem → solo ruoli autorizzati.
if ($action === 'save') {
    $authorizedRoles = ['user', 'client', 'admin'];
    if (!in_array($userRole, $authorizedRoles)) {
        echo json_encode(["error" => "Permessi insufficienti per salvare (Ruolo: $userRole)."]);
        exit;
    }
    $jsonld = $data['jsonld'] ?? '';
    $decoded = json_decode($jsonld, true);
    if (!is_array($decoded)) {
        echo json_encode(["error" => "JSON-LD non valido."]);
        exit;
    }
    $entity = $decoded['mainEntity'] ?? $decoded;
    $id = $entity['@id'] ?? '';
    // Solo il formato atteso: niente path traversal.
    if (!preg_match('#^(places|localbusinesses)/[A-Za-z0-9._-]+/[A-Za-z0-9._-]+$#', $id)) {
        echo json_encode(["error" => "@id non valido o mancante: '$id'."]);
        exit;
    }
    $dir = __DIR__ . '/../../ws-custom/contents/meetoo/it_IT/' . $id;
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        echo json_encode(["error" => "Impossibile creare la cartella $id."]);
        exit;
    }
    $file = $dir . '/index.json';
    $existed = is_file($file);
    $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (@file_put_contents($file, $pretty) === false) {
        echo json_encode(["error" => "Scrittura fallita: il web server ha i permessi su ws-custom?"]);
        exit;
    }

    // Immagini: scarica cover/logo dal sito nella cartella media/ (i path locali
    // sono già in entity.image / entity.logo, es. "media/cover.jpg").
    $media = is_array($data['media'] ?? null) ? $data['media'] : [];
    $saved = [];
    $coverPath = (string)($entity['image'] ?? '');
    $logoPath = (string)($entity['logo'] ?? '');
    if (!empty($media['cover_src']) && preg_match('#^media/[A-Za-z0-9._-]+$#', $coverPath)) {
        if (ws_download_image($media['cover_src'], "$dir/$coverPath")) $saved[] = $coverPath;
    }
    if (!empty($media['logo_src']) && preg_match('#^media/[A-Za-z0-9._-]+$#', $logoPath)) {
        if (ws_download_image($media['logo_src'], "$dir/$logoPath")) $saved[] = $logoPath;
    }

    echo json_encode(["success" => true, "path" => "$id/index.json", "overwritten" => $existed, "media_saved" => $saved]);
    exit;
}

// 4. Risposta alla richiesta di Ricerca (Data Ingestion)
if ($action === 'search') {
    // Sblocca l'area Whitelist solo per ruoli autorizzati
    $authorizedRoles = ['user', 'client', 'admin'];
    if (!in_array($userRole, $authorizedRoles)) {
        echo json_encode(["error" => "Accesso negato. Permessi insufficienti (Ruolo: $userRole)."]);
        exit;
    }

    $searchQuery = $data['query'] ?? '';
    if (empty($searchQuery)) {
         echo json_encode(["error" => "Query di ricerca vuota."]);
         exit;
    }

    // --- QUI INIZIA LA LOGICA DELL'API DI GOOGLE MAPS VISTA NEL PASSAGGIO PRECEDENTE ---
    $url = "https://maps.googleapis.com/maps/api/place/textsearch/json?query=" . urlencode($searchQuery) . "&key=" . $googleApiKey;
    $rawData = googleGet($url);

    if (!is_array($rawData) || ($rawData['status'] ?? '') !== 'OK' || empty($rawData['results'])) {
        // Surfacciamo lo status reale di Google (REQUEST_DENIED, OVER_QUERY_LIMIT…)
        // e l'eventuale error_message (che indica es. l'IP da autorizzare). Mai la key.
        $gStatus = $rawData['status'] ?? 'NO_RESPONSE';
        $gMsg = $rawData['error_message'] ?? '';
        echo json_encode(["error" => "Places API (textsearch): $gStatus" . ($gMsg ? " — $gMsg" : "")]);
        exit;
    }

    $place = $rawData['results'][0];

    // Chiamata Place Details (arricchimento, non bloccante)
    $detailsFields = "address_component,website,rating,user_ratings_total,editorial_summary,business_status,price_level,url";
    $detailsUrl = "https://maps.googleapis.com/maps/api/place/details/json?place_id=" . $place['place_id'] . "&fields=" . $detailsFields . "&key=" . $googleApiKey;
    $detailsData = googleGet($detailsUrl);
    if (isset($detailsData['result'])) {
        $place = array_merge($place, $detailsData['result']);
    }

    // (Ometto la parte centrale di estrazione variabili Address per brevità, è identica alla precedente iterazione)
    $street = ""; $city = ""; $postalCode = ""; $regionShort = ""; $regionLong = ""; $countryShort = "";
    if (isset($place['address_components'])) {
        foreach ($place['address_components'] as $component) {
            if (in_array("route", $component['types'])) $street = $component['long_name'];
            if (in_array("street_number", $component['types'])) $street .= ", " . $component['long_name'];
            if (in_array("locality", $component['types'])) $city = $component['long_name'];
            if (in_array("postal_code", $component['types'])) $postalCode = $component['long_name'];
            if (in_array("administrative_area_level_2", $component['types'])) $regionShort = $component['short_name'];
            if (in_array("administrative_area_level_1", $component['types'])) $regionLong = $component['long_name'];
            if (in_array("country", $component['types'])) $countryShort = $component['short_name'];
        }
    }
    
    $lat = (float) $place['geometry']['location']['lat'];
    $lng = (float) $place['geometry']['location']['lng'];
    $addressQueryString = urlencode($place['name'] . " " . $city . " " . $regionShort . ", " . $countryShort);

    // @type primario (LocalBusiness|Place) + @id: <cartella>/<IT+CAP>/<slug>
    $primaryType = ws_primary_type($place['types'] ?? []);
    $folder = ws_folder($primaryType);
    $region = $countryShort . $postalCode;            // es. IT00124
    $slug = ws_slug($place['name']);
    $regionMissing = ($countryShort === '' || $postalCode === '');
    $newId = ($region !== '' && $slug !== '') ? "$folder/$region/$slug" : "$folder//$slug";
    $idExists = (!$regionMissing) && ws_id_exists($newId);

    // Se l'@id esiste, confronta il Google ID: stesso luogo → collega + aggiornamenti;
    // diverso/assente → collisione da risolvere cambiando l'id.
    $idSamePlace = false; $idParseError = false; $idHasStoredGid = false; $updates = [];
    if ($idExists) {
        list($storedGid, $stored, $idParseError) = ws_read_stored($newId);
        $idHasStoredGid = ($storedGid !== null && $storedGid !== '');
        if (!$idParseError && $idHasStoredGid && $storedGid === $place['place_id']) {
            $idSamePlace = true;
            $freshRating = isset($place['rating']) ? (string)(float)$place['rating'] : '';
            $freshReviews = isset($place['user_ratings_total']) ? (string)(int)$place['user_ratings_total'] : '';
            $cmp = [
                ['nome', (string)($place['name'] ?? ''), $stored['name']],
                ['sito', (string)($place['website'] ?? ''), $stored['url']],
                ['indirizzo', trim($street), $stored['streetAddress']],
                ['CAP', $postalCode, $stored['postalCode']],
                ['città', $city, $stored['addressLocality']],
                ['rating', $freshRating, (string)($stored['ratingValue'] ?? '')],
                ['recensioni', $freshReviews, (string)($stored['reviewCount'] ?? '')],
            ];
            foreach ($cmp as $row) {
                $new = trim($row[1]); $old = trim($row[2]);
                if ($new !== '' && strcasecmp($new, $old) !== 0) {
                    $updates[] = ['field' => $row[0], 'old' => $old, 'new' => $new];
                }
            }
        }
    }

    // Dati extra da Google + immagini dal sito del locale (cover/logo).
    $accessibility = ws_accessibility($place['place_id'], $googleApiKey);
    $siteImg = ws_site_images($place['website'] ?? '');
    $coverExt = $siteImg['cover'] ? ws_img_ext($siteImg['cover'], 'jpg') : '';
    $logoExt = $siteImg['logo'] ? ws_img_ext($siteImg['logo'], 'png') : '';
    // URL canonico Google Maps (se disponibile) al posto di quello costruito.
    $gmapUrl = !empty($place['url'])
        ? $place['url']
        : ("https://www.google.com/maps/search/?api=1&query=" . $addressQueryString . "&query_place_id=" . urlencode($place['place_id']));

    $wsCmsJsonLd = [
        "@context" => "https://schema.org",
        "@type" => "ItemPage",
        "dateModified" => date("c"),
        "mainEntity" => [
            "@type" => $primaryType,
            "@id" => $newId,
            "meetoo:google_place_id" => $place['place_id'],
            "name" => $place['name'],
            "address" => [
                "@type" => "PostalAddress",
                "streetAddress" => trim($street),
                "postalCode" => $postalCode,
                "addressLocality" => $city,
                "addressRegion" => [$regionLong, $regionShort],
                "addressCountry" => $countryShort
            ],
            "geo" => [
                "@type" => "GeoCoordinates",
                "latitude" => $lat,
                "longitude" => $lng
            ],
            "hasMap" => [
                ["@type" => "Map", "name" => "Google Maps", "url" => $gmapUrl, "mapType" => "https://schema.org/VenueMap"],
                ["@type" => "Map", "name" => "Apple Maps", "url" => "https://maps.apple.com/?q=" . $addressQueryString, "mapType" => "https://schema.org/VenueMap"],
                ["@type" => "Map", "name" => "Bing Maps", "url" => "https://www.bing.com/maps?q=" . $addressQueryString, "mapType" => "https://schema.org/VenueMap"]
            ]
        ]
    ];

    if (!empty($place['website'])) $wsCmsJsonLd['mainEntity']['url'] = $place['website'];
    if (isset($place['rating'])) {
        $wsCmsJsonLd['mainEntity']['aggregateRating'] = [
            "@type" => "AggregateRating", "ratingValue" => (float) $place['rating'], "reviewCount" => (int) $place['user_ratings_total']
        ];
    }
    // Campi integrati aggiuntivi
    if (!empty($place['editorial_summary']['overview'])) $wsCmsJsonLd['mainEntity']['description'] = $place['editorial_summary']['overview'];
    if (isset($place['price_level'])) {
        $pr = ws_price_range($place['price_level']);
        if ($pr !== '') $wsCmsJsonLd['mainEntity']['priceRange'] = $pr;
    }
    if ($coverExt) $wsCmsJsonLd['mainEntity']['image'] = "media/cover.$coverExt";
    if ($logoExt) $wsCmsJsonLd['mainEntity']['logo'] = "media/logo.$logoExt";
    if (!empty($place['business_status'])) $wsCmsJsonLd['mainEntity']['meetoo:business_status'] = $place['business_status'];
    if (!empty($accessibility)) $wsCmsJsonLd['mainEntity']['meetoo:accessibilityFeature'] = $accessibility;

    echo json_encode([
        "raw_google" => $place,
        "ws_cms" => $wsCmsJsonLd,
        "id_exists" => $idExists,
        "id_same_place" => $idSamePlace,        // stesso Google ID → già inserito, collega
        "id_has_stored_gid" => $idHasStoredGid, // false = voce legacy senza Google ID
        "id_parse_error" => $idParseError,      // index.json esistente ma illeggibile
        "updates" => $updates,                  // differenze Google↔salvato da integrare
        "id_region_missing" => $regionMissing,
        // Sorgenti immagini dal sito, per scaricarle in media/ al salvataggio
        "media" => ["cover_src" => $siteImg['cover'], "logo_src" => $siteImg['logo']],
    ]);
    exit;
}
?>