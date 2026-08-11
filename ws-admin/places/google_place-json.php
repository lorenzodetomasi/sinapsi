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

// Indice di deduplica google_place_id → @id (globale, ricostruibile).
require __DIR__ . '/index-lib.php';

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
// Cartella unica: LocalBusiness È un Place (schema.org), quindi entrambi stanno
// sotto places/. Il tipo preciso resta nel @type del JSON, non nel percorso.
function ws_folder($type) {
    return 'places';
}
function ws_slug($name) {
    return strtolower(preg_replace('/[^a-z0-9]/i', '', (string)$name));
}
// Verifica se la cartella dell'@id esiste già in ws-custom.
function ws_id_exists($id) {
    if (!preg_match('#^places/[A-Za-z0-9._-]+/[A-Za-z0-9._-]+$#', $id)) return false;
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

// --- Persone (creator/author/contributor) come riferimenti schema.org ---
function ws_person_ref($uid) { return ['@type' => 'Person', '@id' => "users/$uid"]; }
// Estrae "users/<uid>" da un riferimento (oggetto {@id} o stringa).
function ws_ref_id($x) {
    if (is_array($x)) return (string)($x['@id'] ?? '');
    return is_string($x) ? $x : '';
}
// Normalizza contributor (singolo o lista) in array di "users/<uid>".
function ws_ref_ids($x) {
    if (!is_array($x)) return [];
    $list = (isset($x['@id']) || isset($x['@type'])) ? [$x] : $x; // oggetto singolo → lista
    $out = [];
    foreach ($list as $r) { $id = ws_ref_id($r); if ($id !== '') $out[] = $id; }
    return $out;
}
// Chi può modificare un'entità: super-admin/admin sempre; altrimenti creator o
// contributor. Un item legacy senza creator è aperto (coerente col gate del save).
function ws_can_edit($entity, $userUid, $userRole) {
    if (in_array($userRole, ['admin', 'super-admin'], true)) return true;
    $creatorId = ws_ref_id($entity['creator'] ?? null);
    if ($creatorId === '') return true;
    $me = "users/$userUid";
    return $creatorId === $me || in_array($me, ws_ref_ids($entity['contributor'] ?? null), true);
}
// @id → percorso sicuro sotto it_IT (solo places/organizations, niente traversal).
function ws_id_to_path($id) {
    if (!is_string($id) || $id === '') return null;
    $parts = explode('/', $id);
    if (count($parts) < 2) return null;
    if (!in_array($parts[0], ['places', 'organizations'], true)) return null;
    foreach ($parts as $p) { if (!preg_match('#^[A-Za-z0-9._-]+$#', $p) || $p === '.' || $p === '..') return null; }
    return WS_MEETOO_ROOT . '/' . implode('/', $parts);
}

// --- Merge SELETTIVO per-percorso (dot-path), per i checkbox del diff ---
function ws_path_get($arr, $segs) {
    foreach ($segs as $s) {
        if (is_array($arr) && array_key_exists($s, $arr)) $arr = $arr[$s];
        else return [false, null];
    }
    return [true, $arr];
}
function ws_path_set(&$arr, $segs, $val) {
    $ref = &$arr;
    $n = count($segs);
    foreach ($segs as $i => $s) {
        if ($i === $n - 1) { $ref[$s] = $val; return; }
        if (!isset($ref[$s]) || !is_array($ref[$s])) $ref[$s] = [];
        $ref = &$ref[$s];
    }
}
function ws_path_unset(&$arr, $segs) {
    $ref = &$arr;
    $n = count($segs);
    foreach ($segs as $i => $s) {
        if ($i === $n - 1) { unset($ref[$s]); return; }
        if (!isset($ref[$s]) || !is_array($ref[$s])) return;
        $ref = &$ref[$s];
    }
}
// Applica SOLO i percorsi scelti dal nuovo sullo stored: presente nel nuovo → lo
// imposta; assente nel nuovo → lo rimuove (era una cancellazione accettata).
function ws_apply_paths($stored, $new, $paths) {
    foreach ($paths as $p) {
        $segs = explode('.', (string)$p);
        list($found, $val) = ws_path_get($new, $segs);
        if ($found) ws_path_set($stored, $segs, $val);
        else ws_path_unset($stored, $segs);
    }
    return $stored;
}

// Merge profondo: i valori di $over vincono su $base; le chiavi presenti solo in
// $base vengono conservate. Le liste (array numerici) vengono sostituite intere.
function ws_deep_merge($base, $over) {
    if (!is_array($base) || !is_array($over)) return $over;
    $isList = ($over === [] ) || array_keys($over) === range(0, count($over) - 1);
    if ($isList) return $over;
    $out = $base;
    foreach ($over as $k => $v) {
        $out[$k] = array_key_exists($k, $base) ? ws_deep_merge($base[$k], $v) : $v;
    }
    return $out;
}

// Fascia di prezzo indicativa da price_level (0-4). Google NON dà importi reali.
function ws_price_range($level) {
    $bands = [0 => 'Gratis', 1 => '€0-15', 2 => '€15-30', 3 => '€30-60', 4 => '€60+'];
    return array_key_exists((int)$level, $bands) ? $bands[(int)$level] : '';
}

// Dati dalla Places API (New): accessibilità + servizi/informazioni in UNA
// chiamata. Richiede "Places API (New)" abilitata sulla stessa key server.
// Se non disponibile ritorna [] e i campi vengono semplicemente omessi.
function ws_place_new($placeId, $key) {
    $fields = implode(',', [
        'accessibilityOptions', 'paymentOptions', 'parkingOptions',
        'dineIn', 'takeout', 'delivery', 'curbsidePickup', 'reservable',
        'servesBeer', 'servesWine', 'servesBreakfast', 'servesLunch', 'servesDinner',
        'servesBrunch', 'servesVegetarianFood', 'servesCoffee', 'servesDessert', 'servesCocktails',
        'outdoorSeating', 'liveMusic', 'menuForChildren', 'restroom',
        'goodForChildren', 'goodForGroups', 'goodForWatchingSports', 'allowsDogs',
    ]);
    $url = "https://places.googleapis.com/v1/places/" . rawurlencode($placeId);
    $ctx = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 10,
        'header' => "X-Goog-Api-Key: $key\r\nX-Goog-FieldMask: $fields\r\n"]]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) return [];
    $d = json_decode($body, true);
    return is_array($d) ? $d : [];
}

// meetoo:accessibilityFeature (voci sedia a rotelle) da accessibilityOptions.
function ws_accessibility_from($new) {
    $a = is_array($new['accessibilityOptions'] ?? null) ? $new['accessibilityOptions'] : [];
    $out = [];
    foreach (['wheelchairAccessibleEntrance', 'wheelchairAccessibleRestroom',
              'wheelchairAccessibleParking', 'wheelchairAccessibleSeating'] as $k) {
        if (!empty($a[$k])) $out[] = $k;
    }
    return $out;
}

// amenityFeature (LocationFeatureSpecification[]) dai servizi/informazioni.
// Solo quelli PRESENTI (true). Etichette IT modificabili qui.
function ws_amenities_from($new) {
    $labels = [
        'dineIn' => 'Consumo sul posto', 'takeout' => 'Asporto', 'delivery' => 'Consegna a domicilio',
        'curbsidePickup' => 'Ritiro all’esterno', 'reservable' => 'Prenotabile',
        'servesBeer' => 'Birra', 'servesWine' => 'Vino', 'servesCocktails' => 'Cocktail',
        'servesCoffee' => 'Caffè', 'servesDessert' => 'Dolci', 'servesVegetarianFood' => 'Opzioni vegetariane',
        'servesBreakfast' => 'Colazione', 'servesLunch' => 'Pranzo', 'servesDinner' => 'Cena', 'servesBrunch' => 'Brunch',
        'outdoorSeating' => 'Posti all’aperto', 'liveMusic' => 'Musica dal vivo',
        'menuForChildren' => 'Menù per bambini', 'restroom' => 'Bagni',
        'goodForChildren' => 'Adatto ai bambini', 'goodForGroups' => 'Adatto ai gruppi',
        'goodForWatchingSports' => 'Per guardare lo sport', 'allowsDogs' => 'Cani ammessi',
    ];
    $payLabels = [
        'acceptsCreditCards' => 'Carte di credito', 'acceptsDebitCards' => 'Carte di debito',
        'acceptsNfc' => 'Pagamento contactless', 'acceptsCashOnly' => 'Solo contanti',
    ];
    $parkLabels = [
        'freeParkingLot' => 'Parcheggio gratuito', 'paidParkingLot' => 'Parcheggio a pagamento',
        'freeStreetParking' => 'Parcheggio libero su strada', 'paidStreetParking' => 'Parcheggio a pagamento su strada',
        'valetParking' => 'Servizio di parcheggio', 'freeGarageParking' => 'Garage gratuito', 'paidGarageParking' => 'Garage a pagamento',
    ];
    $out = [];
    $add = function ($name) use (&$out) {
        $out[] = ['@type' => 'LocationFeatureSpecification', 'name' => $name, 'value' => true];
    };
    foreach ($labels as $k => $name) if (!empty($new[$k])) $add($name);
    $pay = is_array($new['paymentOptions'] ?? null) ? $new['paymentOptions'] : [];
    foreach ($payLabels as $k => $name) if (!empty($pay[$k])) $add($name);
    $park = is_array($new['parkingOptions'] ?? null) ? $new['parkingOptions'] : [];
    foreach ($parkLabels as $k => $name) if (!empty($park[$k])) $add($name);
    return $out;
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
// Varianti dell'URL da provare: originale, https, con/senza www, apex https.
// Serve perché Google spesso dà "http://www.dominio/" che non risponde mentre
// "https://dominio/" sì.
function ws_url_candidates($u) {
    $u = trim($u);
    $set = [];
    $add = function ($x) use (&$set) {
        $x = rtrim($x, '/');
        if ($x !== '' && !in_array($x, $set, true)) $set[] = $x;
    };
    $add($u);
    $add(preg_replace('#^http://#i', 'https://', $u));
    if (preg_match('#^(https?://)www\.(.+)$#i', $u, $m)) {
        $add($m[1] . $m[2]);
        $add('https://' . $m[2]);
    } elseif (preg_match('#^(https?://)(.+)$#i', $u, $m)) {
        $add($m[1] . 'www.' . $m[2]);
    }
    $host = @parse_url($u, PHP_URL_HOST);
    if ($host) $add('https://' . preg_replace('#^www\.#i', '', $host));
    return $set;
}
function ws_fetch_html($url) {
    $ctx = stream_context_create([
        'http' => ['ignore_errors' => true, 'timeout' => 8, 'header' => "User-Agent: Mozilla/5.0\r\n",
                   'follow_location' => 1, 'max_redirects' => 4],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $h = @file_get_contents($url, false, $ctx);
    return $h === false ? '' : substr($h, 0, 500000);
}
// Legge og:image (cover) e og:logo/apple-touch-icon (logo) dal sito, provando le
// varianti dell'URL finché una risponde.
function ws_site_images($website) {
    $out = ['cover' => '', 'logo' => ''];
    if (!$website) return $out;
    $html = '';
    $base = '';
    foreach (ws_url_candidates($website) as $u) {
        $html = ws_fetch_html($u);
        if ($html !== '') { $base = $u; break; }
    }
    if ($html === '') return $out;
    $cover = ws_meta_content($html, 'og:image');
    $logo = ws_meta_content($html, 'og:logo');
    if (!$logo) $logo = ws_link_href($html, 'apple-touch-icon');
    if (!$logo) $logo = ws_link_href($html, 'icon');
    $out['cover'] = ws_abs_url($cover, $base);
    $out['logo'] = ws_abs_url($logo, $base);
    return $out;
}
function ws_img_ext($url, $default) {
    if (preg_match('/\.(jpe?g|png|webp|gif|svg)(?:[?#]|$)/i', $url, $m)) {
        $e = strtolower($m[1]);
        return $e === 'jpeg' ? 'jpg' : $e;
    }
    return $default;
}
// URL della vista satellitare (Esri World Imagery). Nessuna API key: Google non
// serve i tipi satellite/hybrid in area SEE/EEA. Costruiamo un bbox attorno alle
// coordinate ($half = semi-lato dell'area, in metri).
function ws_satellite_url($lat, $lng) {
    $half = 300; // ~300 m attorno al luogo (ritoccabile)
    $latD = $half / 111320;
    $lngD = $half / (111320 * cos(deg2rad((float)$lat)));
    $bbox = ((float)$lng - $lngD) . ',' . ((float)$lat - $latD) . ',' . ((float)$lng + $lngD) . ',' . ((float)$lat + $latD);
    return "https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/export"
         . "?bbox=$bbox&bboxSR=4326&size=1280,1280&format=jpg&f=image";
}
// Attribuzione richiesta da Esri per World Imagery.
const WS_SATELLITE_CREDIT = 'Esri, Maxar, Earthstar Geographics';

// Riconosce un'immagine dalle magic bytes (indipendente dalla versione PHP).
function ws_is_image_bin($bin) {
    if (strlen($bin) < 12) return false;
    if (substr($bin, 0, 3) === "\xFF\xD8\xFF") return true;                        // JPEG
    if (substr($bin, 0, 8) === "\x89PNG\r\n\x1a\n") return true;                   // PNG
    if (substr($bin, 0, 6) === 'GIF87a' || substr($bin, 0, 6) === 'GIF89a') return true; // GIF
    if (substr($bin, 0, 4) === 'RIFF' && substr($bin, 8, 4) === 'WEBP') return true;     // WEBP
    $head = ltrim(substr($bin, 0, 300));
    if (stripos($head, '<svg') !== false || stripos($head, '<?xml') === 0) return true;   // SVG
    return false;
}

// Scarica un'immagine e la salva su disco (con limiti). Ritorna true/false e,
// via $err, il motivo del fallimento (per diagnosi).
function ws_download_image($url, $destPath, &$err = null) {
    $err = '';
    if (!$url) { $err = 'url mancante'; return false; }
    $ctx = stream_context_create([
        'http' => ['ignore_errors' => true, 'timeout' => 15, 'header' => "User-Agent: Mozilla/5.0\r\n",
                   'follow_location' => 1, 'max_redirects' => 3],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $bin = @file_get_contents($url, false, $ctx);
    if ($bin === false) { $err = 'irraggiungibile'; return false; }
    if (strlen($bin) < 100) { $err = 'risposta vuota/troppo piccola'; return false; }
    if (strlen($bin) > 8 * 1024 * 1024) { $err = 'immagine troppo grande'; return false; }
    if (!ws_is_image_bin($bin)) {
        // spesso è un errore testuale di Google (API non abilitata, IP…)
        $err = 'non è un\'immagine: ' . trim(preg_replace('/\s+/', ' ', strip_tags(substr($bin, 0, 200))));
        return false;
    }
    $dir = dirname($destPath);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) { $err = 'cartella media/ non creabile (permessi?)'; return false; }
    if (@file_put_contents($destPath, $bin) === false) { $err = 'scrittura fallita (permessi?)'; return false; }
    return true;
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

// 3-ter. Elenco utenti per l'autocomplete dei contributor (ricerca per nome /
// pseudonimo). Espone i nomi → solo ruoli autorizzati, come save/search.
if ($action === 'users') {
    $authorizedRoles = ['user', 'client', 'admin', 'super-admin'];
    if (!in_array($userRole, $authorizedRoles, true)) {
        echo json_encode(["error" => "Permessi insufficienti (Ruolo: $userRole).", 'users' => []]);
        exit;
    }
    $usersDir = '../../ws-custom/contents/meetoo/it_IT/users';
    $personsDir = '../../ws-custom/contents/meetoo/it_IT/persons';
    $out = [];
    if (is_dir($usersDir)) {
        foreach (scandir($usersDir) as $entry) {
            if (!preg_match('/^\d{6,}$/', $entry)) continue; // solo cartelle-UID numeriche
            $name = '';
            $pseudonym = '';
            $pf = "$personsDir/$entry/index.xml";
            if (is_file($pf)) {
                $pdom = new DOMDocument();
                $pdom->preserveWhiteSpace = false;
                libxml_use_internal_errors(true);
                if ($pdom->load($pf, LIBXML_NONET)) {
                    $px = simplexml_import_dom($pdom);
                    if ($px) {
                        $name = trim((string)$px->name);
                        if (isset($px->alternateName)) $pseudonym = trim((string)$px->alternateName);
                    }
                }
                libxml_clear_errors();
            }
            $out[] = ['uid' => $entry, 'name' => $name, 'pseudonym' => $pseudonym];
        }
    }
    echo json_encode(['users' => $out]);
    exit;
}

// 3-quater. Elenco dei JSON che l'utente può MODIFICARE (per aprirli e editarli):
// tutti per admin/super-admin, altrimenti solo dove è creator o contributor (e i
// legacy senza creator). Scansione una tantum di places/ e organizations/.
if ($action === 'editable') {
    $authorizedRoles = ['user', 'client', 'admin', 'super-admin'];
    if (!in_array($userRole, $authorizedRoles, true)) {
        echo json_encode(["error" => "Permessi insufficienti (Ruolo: $userRole).", 'items' => []]);
        exit;
    }
    $items = [];
    foreach (WS_INDEX_SCAN_DIRS as $sub) {
        $base = WS_MEETOO_ROOT . '/' . $sub;
        if (!is_dir($base)) continue;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->getFilename() !== 'index.json') continue;
            $j = json_decode((string)file_get_contents($file->getPathname()), true);
            if (!is_array($j)) continue;
            $e = $j['mainEntity'] ?? $j;
            $eid = $e['@id'] ?? '';
            // Solo @id apribili (places/organizations): esclude gli eventi annidati,
            // così ogni voce elencata è caricabile da action=load.
            if ($eid === '' || ws_id_to_path($eid) === null || !ws_can_edit($e, $userUid, $userRole)) continue;
            $items[] = ['@id' => $eid, 'name' => $e['name'] ?? '', '@type' => $e['@type'] ?? ''];
        }
    }
    usort($items, function ($a, $b) { return strcasecmp($a['name'], $b['name']); });
    echo json_encode(['items' => $items]);
    exit;
}

// 3-quinquies. Contenuto di un @id da editare. Autorizzazione come sopra.
if ($action === 'load') {
    $authorizedRoles = ['user', 'client', 'admin', 'super-admin'];
    if (!in_array($userRole, $authorizedRoles, true)) {
        echo json_encode(["error" => "Permessi insufficienti (Ruolo: $userRole)."]);
        exit;
    }
    $loadId = $data['id'] ?? $_GET['id'] ?? '';
    $path = ws_id_to_path($loadId);
    if ($path === null) { echo json_encode(["error" => "@id non valido: '$loadId'."]); exit; }
    $file = $path . '/index.json';
    if (!is_file($file)) { echo json_encode(["error" => "Nessun JSON per '$loadId'."]); exit; }
    $j = json_decode((string)file_get_contents($file), true);
    if (!is_array($j)) { echo json_encode(["error" => "index.json illeggibile per '$loadId'."]); exit; }
    $e = $j['mainEntity'] ?? $j;
    if (!ws_can_edit($e, $userUid, $userRole)) {
        echo json_encode(["error" => "Non sei autorizzato a modificare '$loadId'."]);
        exit;
    }
    echo json_encode(['id' => $loadId, 'json' => $j]);
    exit;
}

// 3-bis. Salvataggio sul server: scrive il JSON-LD in <@id>/index.json.
// Operazione che MODIFICA il filesystem → solo ruoli autorizzati.
if ($action === 'save') {
    $authorizedRoles = ['user', 'client', 'admin', 'super-admin'];
    if (!in_array($userRole, $authorizedRoles)) {
        echo json_encode(["error" => "Permessi insufficienti per salvare (Ruolo: $userRole)."]);
        exit;
    }
    $jsonld = $data['jsonld'] ?? '';
    $mode = $data['mode'] ?? ''; // '' | ignore | overwrite | merge
    $paths = is_array($data['paths'] ?? null) ? $data['paths'] : []; // merge selettivo
    $decoded = json_decode($jsonld, true);
    if (!is_array($decoded)) {
        echo json_encode(["error" => "JSON-LD non valido."]);
        exit;
    }
    $newEntity = $decoded['mainEntity'] ?? $decoded;
    $id = $newEntity['@id'] ?? '';
    // Solo il formato atteso: niente path traversal.
    if (!preg_match('#^places/[A-Za-z0-9._-]+/[A-Za-z0-9._-]+$#', $id)) {
        echo json_encode(["error" => "@id non valido o mancante: '$id'."]);
        exit;
    }
    $dir = __DIR__ . '/../../ws-custom/contents/meetoo/it_IT/' . $id;
    $file = $dir . '/index.json';
    $existed = is_file($file);
    $storedFull = $existed ? json_decode(@file_get_contents($file), true) : null;

    // Guardia anti-duplicato: se questo google_place_id è già indicizzato sotto un
    // @id DIVERSO e qui si creerebbe una cartella NUOVA, blocca. Va modificato
    // l'esistente (la ricerca adotta già l'@id: questo è il backstop lato server).
    if (!$existed) {
        $savePlaceId = ws_index_place_id($newEntity);
        $dupSave = $savePlaceId !== '' ? ws_index_lookup($savePlaceId) : null;
        if ($dupSave && ($dupSave['@id'] ?? '') !== $id) {
            echo json_encode([
                "error" => "Questo Google Place ID è già salvato come " . $dupSave['@id']
                    . " (" . ($dupSave['name'] ?? '') . "). Riapri e modifica quello invece di creare un duplicato.",
                "dup_id" => $dupSave['@id'],
            ]);
            exit;
        }
    }

    // Autorizzazione per-elemento (solo su item ESISTENTI con un proprietario):
    // può modificare chi è admin, il creatore o è negli editors. Gli item legacy
    // senza createdBy restano modificabili dai ruoli già autorizzati (sopra).
    if ($existed && is_array($storedFull)) {
        $se = $storedFull['mainEntity'] ?? $storedFull;
        $creatorId = ws_ref_id($se['creator'] ?? null);
        $contributors = ws_ref_ids($se['contributor'] ?? null);
        $meRef = "users/$userUid";
        $isAdmin = in_array($userRole, ['admin', 'super-admin'], true); // super-admin = modifica tutto Meetoo
        if ($creatorId !== '' && !$isAdmin && $creatorId !== $meRef && !in_array($meRef, $contributors, true)) {
            echo json_encode(["error" => "Non sei autorizzato a modificare questo elemento (creator: $creatorId). Chiedi di essere aggiunto ai contributor."]);
            exit;
        }
    }

    // Se esiste già e non è stata scelta un'azione, NON scriviamo: torniamo lo
    // stored così il client mostra il diff e propone ignora/sovrascrivi/integra.
    if ($existed && $mode === '') {
        echo json_encode(["needs_confirm" => true, "path" => "$id/index.json", "stored" => $storedFull]);
        exit;
    }
    if ($existed && $mode === 'ignore') {
        echo json_encode(["success" => true, "ignored" => true, "path" => "$id/index.json"]);
        exit;
    }

    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        echo json_encode(["error" => "Impossibile creare la cartella $id."]);
        exit;
    }

    // JSON finale: 'merge' integra il nuovo sullo stored. Con $paths (checkbox del
    // diff) integra SOLO i percorsi scelti; senza, l'intero nuovo (deep merge).
    // 'overwrite'/item nuovo → il nuovo così com'è.
    if ($existed && $mode === 'merge' && is_array($storedFull)) {
        $decoded = !empty($paths) ? ws_apply_paths($storedFull, $decoded, $paths) : ws_deep_merge($storedFull, $decoded);
    }

    // Date: dateCreated una volta sola (preservata se esiste), dateModified sempre.
    $now = date('c');
    $decoded['dateCreated'] = (is_array($storedFull) && !empty($storedFull['dateCreated'])) ? $storedFull['dateCreated'] : $now;
    $decoded['dateModified'] = $now;

    // Riferimento (non copia) all'entità del JSON finale.
    if (isset($decoded['mainEntity']) && is_array($decoded['mainEntity'])) {
        $entity = &$decoded['mainEntity'];
    } else {
        $entity = &$decoded;
    }

    // Persone (schema.org): creator = proprietario (immutabile), author = ultimo
    // editor (aggiornato a ogni salvataggio), contributor = editors autorizzati.
    $storedEntity = is_array($storedFull) ? ($storedFull['mainEntity'] ?? $storedFull) : [];
    if (!empty($storedEntity['creator'])) {
        $entity['creator'] = $storedEntity['creator']; // creatore immutabile
    } elseif (!$existed) {
        $entity['creator'] = ws_person_ref($userUid);
    }
    $entity['author'] = ws_person_ref($userUid); // chi ha salvato quest'ultima volta
    // Contributor: preserva la lista salvata se il JSON inviato non la specifica
    // (così non si azzera per sbaglio); se la specifica, vince (creator/admin).
    if (empty($entity['contributor']) && !empty($storedEntity['contributor'])) {
        $entity['contributor'] = $storedEntity['contributor'];
    }

    // Immagini in media/. Scaricate PRIMA di scrivere il JSON. Regola: se il
    // download fallisce (o non c'è sorgente) MA il file esiste già su disco (es.
    // salvataggio ripetuto/merge), il riferimento si conserva; altrimenti si
    // toglie per non lasciare immagini rotte. $extraKeys = chiavi da togliere
    // insieme (es. il credit della satellite).
    $media = is_array($data['media'] ?? null) ? $data['media'] : [];
    $saved = []; $failed = []; $mediaDebug = [];
    $okPath = function ($p) { return $p !== '' && preg_match('#^media/[A-Za-z0-9._-]+$#', $p); };
    $handleMedia = function ($pathKey, $srcUrl, $extraKeys = []) use (&$entity, $dir, $okPath, &$saved, &$failed, &$mediaDebug) {
        $path = (string)($entity[$pathKey] ?? '');
        if (!$okPath($path)) return;
        $dest = "$dir/$path";
        $drop = function () use (&$entity, $pathKey, $extraKeys) {
            unset($entity[$pathKey]);
            foreach ($extraKeys as $k) unset($entity[$k]);
        };
        if ($srcUrl) {
            $e = '';
            if (ws_download_image($srcUrl, $dest, $e)) { $saved[] = $path; return; }
            if (!is_file($dest)) { $drop(); $failed[] = $path; $mediaDebug[$pathKey] = $e; return; }
            $mediaDebug[$pathKey] = 'download fallito, mantenuto file esistente: ' . $e;
            return;
        }
        if (!is_file($dest)) $drop(); // nessuna sorgente e file assente → via il ref
    };

    $handleMedia('image', $media['cover_src'] ?? '');
    $handleMedia('logo', $media['logo_src'] ?? '');
    // Satellite: URL costruito qui (Esri, nessuna key) dalle coordinate del JSON.
    $geoLat = $entity['geo']['latitude'] ?? null;
    $geoLng = $entity['geo']['longitude'] ?? null;
    $satUrl = ($geoLat !== null && $geoLng !== null) ? ws_satellite_url($geoLat, $geoLng) : '';
    $handleMedia('meetoo:satelliteView', $satUrl, ['meetoo:satelliteCredit']);

    $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (@file_put_contents($file, $pretty) === false) {
        echo json_encode(["error" => "Scrittura fallita: il web server ha i permessi su ws-custom?"]);
        exit;
    }

    // Aggiorna l'indice di deduplica (se l'entità ha un google_place_id).
    $savedPlaceId = ws_index_place_id($entity);
    $indexUpdated = $savedPlaceId !== ''
        && ws_index_upsert($savedPlaceId, $id, $entity['name'] ?? '', $entity['@type'] ?? '');

    echo json_encode([
        "success" => true, "path" => "$id/index.json", "overwritten" => $existed, "mode" => ($mode ?: 'new'),
        "media_saved" => $saved, "media_failed" => $failed, "media_debug" => $mediaDebug,
        "index_updated" => $indexUpdated,
    ]);
    exit;
}

// 4. Risposta alla richiesta di Ricerca (Data Ingestion)
if ($action === 'search') {
    // Sblocca l'area Whitelist solo per ruoli autorizzati
    $authorizedRoles = ['user', 'client', 'admin', 'super-admin'];
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
    $slug = ws_slug($place['name']);
    $regionMissing = ($countryShort === '' || $postalCode === '');
    // Region SOLO se abbiamo sia paese sia CAP; altrimenti resta vuota così
    // l'@id (folder//slug) è invalido e il salvataggio lo blocca finché il CAP
    // non viene impostato a mano.
    $region = $regionMissing ? '' : ($countryShort . $postalCode);   // es. IT00124
    $newId = ($region !== '' && $slug !== '') ? "$folder/$region/$slug" : "$folder//$slug";

    // Deduplica: se questo google_place_id è GIÀ indicizzato, ADOTTA l'@id esistente
    // invece di generarne uno nuovo dal nome corrente. Google può restituire un nome
    // diverso per lo stesso luogo (es. "Spiaggia libera Bianca-L'Amanusa" vs il
    // salvato "L'Amanusa Beach"): senza questo, lo slug diverso creava un @id nuovo
    // e un duplicato. Adottando l'@id parte il normale flusso di modifica/diff.
    $dupEntry = ws_index_lookup($place['place_id'] ?? '');
    $idAdopted = false;
    if ($dupEntry && !empty($dupEntry['@id']) && $dupEntry['@id'] !== $newId) {
        $newId = $dupEntry['@id'];
        $regionMissing = false;   // l'@id adottato è già valido
        $idAdopted = true;
    }
    $idExists = (!$regionMissing) && ws_id_exists($newId);
    // Resta informativo solo nei rari casi non adottati (indice disallineato).
    $idDup = ($dupEntry && ($dupEntry['@id'] ?? '') !== $newId) ? $dupEntry : null;

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

    // Dati extra da Google (Places API New: accessibilità + servizi) + immagini
    // dal sito del locale (cover/logo).
    $newPlace = ws_place_new($place['place_id'], $googleApiKey);
    // Diagnostica: se la New API fallisce, ci dice il perché (es. API non
    // abilitata, IP non autorizzato) invece di restare muta.
    $newApiStatus = isset($newPlace['error'])
        ? (($newPlace['error']['status'] ?? '') . ': ' . ($newPlace['error']['message'] ?? ''))
        : (empty($newPlace) ? 'nessuna risposta (API non abilitata o rete?)' : 'ok');
    $accessibility = ws_accessibility_from($newPlace);
    $amenities = ws_amenities_from($newPlace);
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
    // Vista satellitare: sempre disponibile (abbiamo lat/lng). Il file verrà
    // scaricato al salvataggio da Esri World Imagery.
    if ($lat && $lng) {
        $wsCmsJsonLd['mainEntity']['meetoo:satelliteView'] = "media/satellite.jpg";
        $wsCmsJsonLd['mainEntity']['meetoo:satelliteCredit'] = WS_SATELLITE_CREDIT;
    }
    if (!empty($place['business_status'])) $wsCmsJsonLd['mainEntity']['meetoo:business_status'] = $place['business_status'];
    if (!empty($accessibility)) $wsCmsJsonLd['mainEntity']['meetoo:accessibilityFeature'] = $accessibility;
    if (!empty($amenities)) $wsCmsJsonLd['mainEntity']['amenityFeature'] = $amenities;

    echo json_encode([
        "raw_google" => $place,
        "ws_cms" => $wsCmsJsonLd,
        "id_exists" => $idExists,
        "id_same_place" => $idSamePlace,        // stesso Google ID → già inserito, collega
        "id_has_stored_gid" => $idHasStoredGid, // false = voce legacy senza Google ID
        "id_parse_error" => $idParseError,      // index.json esistente ma illeggibile
        "updates" => $updates,                  // differenze Google↔salvato da integrare
        "id_region_missing" => $regionMissing,
        "id_dup" => $idDup,                     // luogo già presente con altro @id (o null)
        "id_adopted" => $idAdopted,             // true = @id preso dall'indice (stesso place_id)
        // Sorgenti immagini dal sito, per scaricarle in media/ al salvataggio
        "media" => ["cover_src" => $siteImg['cover'], "logo_src" => $siteImg['logo']],
        // Diagnostica (visibile nel JSON di risposta, nessuna key)
        "debug" => [
            "website" => $place['website'] ?? '',
            "new_api" => $newApiStatus,          // 'ok' oppure l'errore Google
            "accessibility_count" => count($accessibility),
            "amenity_count" => count($amenities),
        ],
    ]);
    exit;
}
?>