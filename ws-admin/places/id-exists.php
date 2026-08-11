<?php
// Verifica se un @id di place/localbusiness esiste già in ws-custom e, se sì,
// restituisce il Google Place ID salvato e alcuni campi confrontabili, così il
// chiamante può capire se è lo STESSO luogo (→ collega) o una collisione di slug.
// Usato da events/edit (client) e da places/edit/index.php. GET ?id=<prefix>/<region>/<slug>
header('Content-Type: application/json');

$id = trim($_GET['id'] ?? '');

// Sanitizzazione severa: SOLO il formato atteso, niente "../" o path arbitrari.
if (!preg_match('#^places/[A-Za-z0-9._-]+/[A-Za-z0-9._-]+$#', $id)) {
    echo json_encode(['ok' => false, 'error' => 'id non valido']);
    exit;
}

$base = __DIR__ . '/../../ws-custom/contents/meetoo/it_IT/'; // locale fisso, come il resto del CMS
$dir = $base . $id;
$jsonPath = $dir . '/index.json';

$out = ['ok' => true, 'id' => $id, 'exists' => false];

if (is_dir($dir) || is_file($jsonPath) || is_file($dir . '/index.xml')) {
    $out['exists'] = true;
    $out['google_place_id'] = null;
    if (is_file($jsonPath)) {
        $data = json_decode(@file_get_contents($jsonPath), true);
        if (!is_array($data)) {
            $out['parse_error'] = true; // index.json illeggibile (es. virgola finale)
        } else {
            $m = $data['mainEntity'] ?? $data;
            $out['google_place_id'] = $m['meetoo:google_place_id'] ?? $m['meetoo:googlePlaceId'] ?? null;
            $addr = is_array($m['address'] ?? null) ? $m['address'] : [];
            $out['stored'] = [
                'name' => $m['name'] ?? '',
                'url' => $m['url'] ?? '',
                'streetAddress' => $addr['streetAddress'] ?? '',
                'postalCode' => $addr['postalCode'] ?? '',
                'addressLocality' => $addr['addressLocality'] ?? '',
                'addressCountry' => is_array($addr['addressCountry'] ?? null) ? implode('', $addr['addressCountry']) : ($addr['addressCountry'] ?? ''),
                'ratingValue' => $m['aggregateRating']['ratingValue'] ?? null,
                'reviewCount' => $m['aggregateRating']['reviewCount'] ?? null,
            ];
        }
    }
}

echo json_encode($out);
