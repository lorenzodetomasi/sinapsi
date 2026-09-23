<?php
/*
 * A UNIT: one place, as Google Maps knows it.
 *
 * Two jobs, kept apart on purpose:
 *   - FETCH    ask the Places API for a place (google_places_search / _details)
 *   - MAP      turn that answer into the CMS's own place (google_place_to_json)
 *
 * They are separate because only the first needs the network and a key, and
 * only the second is a decision about what a place IS in this CMS. Keeping the
 * mapping pure means it can be read, reviewed and tested without spending a
 * request — and a change in Google's field names does not reach the shape of
 * the content.
 *
 * This speaks the Places API (New) — `places.googleapis.com/v1` — not the
 * legacy `maps/api/place`. The old one is the one `places/google_place-json.php`
 * still calls; it cannot return opening hours in a form worth storing, it is
 * closed to new projects, and its field names are a different language. Both
 * exist for now: this file is what the new work uses, and the importer moves
 * over when someone touches it.
 */

if (!defined('WS_PLACE_GOOGLE_GENERATOR')) {
    define('WS_PLACE_GOOGLE_GENERATOR', 'place-google 2026.09.23');
}

/* ---------------------------------------------------------------------------
 * The key
 * ------------------------------------------------------------------------- */

/* The server-side key lives in ws-admin/places/config.php, gitignored, and is
 * restricted by IP — a different key from the browser one, which is restricted
 * by referrer. Reading it from there and not from ws-config.php keeps that
 * separation, which is the only thing stopping a leaked page key from being
 * usable for billing. */
function google_places_key(): string {
    $config = @include __DIR__ . '/places/config.php';
    return is_array($config) ? (string)($config['places_api_key'] ?? '') : '';
}

/* ---------------------------------------------------------------------------
 * Fetching
 * ------------------------------------------------------------------------- */

/*
 * The fields asked for. A field mask is not optional on this API and it is not
 * a detail: Google bills by what is asked, so the mask IS the cost. These are
 * the fields that become content — nothing is requested "in case".
 *
 * `photos` is deliberately absent. Place photos may be displayed through
 * Google's own URLs but not stored on another server, so fetching them would
 * buy a thing this CMS is not allowed to keep.
 */
function google_places_fields(): string {
    return implode(',', [
        'id', 'displayName', 'formattedAddress', 'addressComponents', 'location',
        'types', 'primaryType', 'primaryTypeDisplayName',
        'websiteUri', 'nationalPhoneNumber', 'internationalPhoneNumber',
        'rating', 'userRatingCount', 'businessStatus', 'googleMapsUri',
        'editorialSummary', 'priceLevel', 'regularOpeningHours',
        'accessibilityOptions', 'paymentOptions',
    ]);
}

/* One request. Returns [data, error]: an error is a string a person can read,
 * not an exception, because every caller here has something to say about it. */
function google_places_request(string $url, ?array $body, string $mask): array {
    $key = google_places_key();
    if ($key === '') {
        return [null, 'Chiave mancante: crea ws-admin/places/config.php da config.sample.php.'];
    }

    $ch = curl_init($url);
    $headers = ['X-Goog-Api-Key: ' . $key, 'X-Goog-FieldMask: ' . $mask];
    $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20];
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = json_encode($body);
    }
    $opts[CURLOPT_HTTPHEADER] = $headers;
    curl_setopt_array($ch, $opts);

    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    /* No curl_close: since PHP 8.0 the handle is an object freed with the
     * variable, and calling it is deprecated from 8.5 — which prints a notice
     * straight into the JSON this endpoint returns. */

    if ($raw === false) return [null, "Rete: $curlErr"];
    $data = json_decode((string)$raw, true);
    if (!is_array($data)) return [null, "Risposta illeggibile da Google (HTTP $code)."];

    if ($code !== 200) {
        $msg = (string)($data['error']['message'] ?? "HTTP $code");
        $reason = (string)($data['error']['details'][0]['reason'] ?? '');
        /* The two refusals that are about configuration, not about the query,
         * are named: they are what a person has to go and fix, and the raw
         * message does not say where. */
        if ($reason === 'API_KEY_IP_ADDRESS_BLOCKED') {
            $ip = (string)($data['error']['details'][0]['metadata']['callerIp'] ?? '?');
            return [null, "La chiave Places è ristretta per IP e questa macchina ($ip) non è fra quelli ammessi. "
                        . "Aggiungi l'IP alla chiave su Google Cloud, oppure esegui l'operazione dal server."];
        }
        if ($reason === 'API_KEY_INVALID') {
            return [null, "La chiave in places/config.php non è valida per la Places API (New). "
                        . "Controlla che l'API sia abilitata sul progetto Google Cloud."];
        }
        return [null, "Places API: $msg"];
    }
    return [$data, null];
}

/* Text search: what the editor typed. Returns [places[], error]. */
function google_places_search(string $query, string $locale = 'it_IT'): array {
    [$lang, $region] = google_places_locale($locale);
    [$data, $err] = google_places_request(
        'https://places.googleapis.com/v1/places:searchText',
        ['textQuery' => $query, 'languageCode' => $lang, 'regionCode' => $region],
        'places.' . str_replace(',', ',places.', google_places_fields())
    );
    if ($err !== null) return [[], $err];
    return [$data['places'] ?? [], null];
}

/* One place by its Google id — what "update from Google Maps" uses, and what
 * the second step of a search uses, so that the place saved is the place the
 * editor picked and not whatever a repeated search returns tomorrow. */
function google_place_details(string $placeId, string $locale = 'it_IT'): array {
    [$lang, $region] = google_places_locale($locale);
    $url = 'https://places.googleapis.com/v1/places/' . rawurlencode($placeId)
         . '?languageCode=' . urlencode($lang) . '&regionCode=' . urlencode($region);
    [$data, $err] = google_places_request($url, null, google_places_fields());
    if ($err !== null) return [null, $err];
    return [$data, null];
}

/* The CMS speaks locales (it_IT); this API speaks a language and a region
 * separately. One place to convert, so no caller hardcodes "it" again — which
 * is what the legacy importer does, on every one of its requests. */
function google_places_locale(string $locale): array {
    $parts = explode('_', $locale);
    return [strtolower($parts[0] ?? 'en'), strtoupper($parts[1] ?? 'US')];
}

/* ---------------------------------------------------------------------------
 * Mapping: Google's answer -> the CMS's place
 * ------------------------------------------------------------------------- */

/*
 * The whole conversion, and no network. Give it what the API returned and it
 * gives back the `index.json` of a place, in the shape the places archive
 * already uses (see meetoo's places/IT00122/lamanusa).
 *
 * $extraTypes are the schema.org types the editor chose — RealEstateAgent for
 * an estate agency. Google's own `types` are not schema.org and are not
 * treated as if they were: they go in `additionalType` as the words Google
 * uses, which is what they are.
 */
function google_place_to_json(array $g, string $id, array $extraTypes = []): array {
    $addr = google_place_address($g['addressComponents'] ?? []);
    $name = (string)($g['displayName']['text'] ?? '');

    $types = array_values(array_unique(array_merge(
        [google_place_primary_type($g)], $extraTypes
    )));

    $entity = [
        '@type' => count($types) === 1 ? $types[0] : $types,
        '@id'   => $id,
        /*
         * The Google Place ID as a schema.org `identifier`, not as a key of
         * our own. An identifier from an external system is exactly what
         * PropertyValue is for, and it costs nothing: one schema.org key
         * instead of a word only this CMS understands.
         *
         * The archive already shows what the alternative leads to. Meetoo
         * writes this same id under `meetoo:google_place_id` in 54 files and
         * `meetoo:googlePlaceId` in 17 - two spellings of one fact, because
         * nothing outside the code says which is right. A third spelling was
         * not worth adding.
         */
        'identifier' => google_place_identifiers($g),
        'name'  => $name,
    ];

    /* What Google calls this kind of thing, in the editor's language. Data
     * about the source, kept as the source's own words. */
    $additional = [];
    if (!empty($g['primaryTypeDisplayName']['text'])) $additional[] = (string)$g['primaryTypeDisplayName']['text'];
    if ($additional) $entity['additionalType'] = $additional;

    if (!empty($g['editorialSummary']['text'])) $entity['description'] = (string)$g['editorialSummary']['text'];
    if (!empty($g['websiteUri'])) $entity['url'] = (string)$g['websiteUri'];

    $entity['address'] = $addr['postal'];

    if (isset($g['location']['latitude'])) {
        $entity['geo'] = [
            '@type' => 'GeoCoordinates',
            'latitude' => $g['location']['latitude'],
            'longitude' => $g['location']['longitude'] ?? null,
        ];
    }

    $phone = (string)($g['internationalPhoneNumber'] ?? $g['nationalPhoneNumber'] ?? '');
    if ($phone !== '') $entity['telephone'] = $phone;

    if (!empty($g['googleMapsUri'])) {
        $entity['hasMap'] = [[
            '@type' => 'Map', 'name' => 'Google Maps',
            'url' => google_place_map_url((string)$g['googleMapsUri']),
            'mapType' => 'https://schema.org/VenueMap',
        ]];
    }

    /* `ratingCount` is how many people voted (Google: userRatingCount). The
     * archive's older files say `reviewCount`, which meant something else and
     * is read but not written. */
    if (isset($g['rating'])) {
        $entity['aggregateRating'] = [
            '@type' => 'AggregateRating',
            'ratingValue' => $g['rating'],
            'ratingCount' => $g['userRatingCount'] ?? 0,
        ];
    }

    $hours = google_place_opening_hours($g['regularOpeningHours'] ?? []);
    if ($hours) $entity['openingHoursSpecification'] = $hours;

    $features = google_place_features($g);
    if ($features) $entity['amenityFeature'] = $features;

    if (isset($g['priceLevel'])) $entity['priceRange'] = (string)$g['priceLevel'];

    /* Whether the business is trading, temporarily shut or gone for good.
     * schema.org has no property for it and it is worth keeping: a place closed
     * for good should not be shown as if it were open. One key of ours, named
     * for what it is. */
    if (!empty($g['businessStatus'])) $entity['ws:businessStatus'] = (string)$g['businessStatus'];

    return [
        '@context' => ['https://schema.org', ['ws' => 'https://localbiz.it/ws#']],
        '@type' => 'ItemPage',
        'dateCreated' => date('c'),
        'dateModified' => date('c'),
        'mainEntity' => $entity,
    ];
}

/*
 * The map link, with Google's own tracking parameter taken off.
 *
 * `googleMapsUri` comes back as `?cid=<id>&g_mp=<a blob>`, and the blob is
 * different on every call. Stored as it arrives, the place's file would differ
 * from itself after each refresh: the manifest would call the twin stale, the
 * site map would be rebuilt, and a diff would show a change where nothing
 * about the place had changed. The `cid` is the place; the rest is Google
 * watching itself.
 */
function google_place_map_url(string $uri): string {
    $parts = parse_url($uri);
    parse_str($parts['query'] ?? '', $q);
    if (!empty($q['cid'])) return 'https://maps.google.com/?cid=' . rawurlencode((string)$q['cid']);
    return $uri;
}

/*
 * What Google knows about this place that schema.org has no property for,
 * written the way schema.org says to write facts from another system: a list
 * of PropertyValue.
 *
 * Always a list, even with one entry. A field that is an object on some places
 * and an array on others makes every reader handle both, and the first reader
 * that forgets breaks on the day a second identifier appears.
 *
 * Only things that IDENTIFY the place go here. `businessStatus` was in this
 * list for a while and should not have been: whether a business is trading is
 * not a name for it, and putting it here bought the claim that nothing in the
 * file was ours at the price of saying something untrue. It is `ws:businessStatus`
 * now - one honest key of our own beats a schema.org key used wrongly.
 *
 * This is the exception the archive's own rule allows: a thing is identified
 * by its `@id`, and `identifier` is for an id from ANOTHER system - the Google
 * `sub` on a user profile is already written this way.
 */
function google_place_identifiers(array $g): array {
    $out = [];
    if (!empty($g['id'])) {
        $out[] = ['@type' => 'PropertyValue', 'propertyID' => 'googlePlaceId', 'value' => (string)$g['id']];
    }
    return $out;
}

/* LocalBusiness when Google says it is a business one can visit, Place
 * otherwise — the same rule the legacy importer settled on, kept because it is
 * right: a LocalBusiness IS a Place, so both live under places/, and the
 * precise type stays in the JSON rather than in the path. */
function google_place_primary_type(array $g): string {
    $types = (array)($g['types'] ?? []);
    return in_array('establishment', $types, true) ? 'LocalBusiness' : 'Place';
}

/*
 * The address, from Google's components rather than from the one-line string:
 * the line is for reading, the components are the data, and a postal code
 * parsed back out of a formatted line is a guess.
 *
 * Returns the schema.org PostalAddress and the postal code on its own, which
 * is what the archive uses to decide the folder (places/IT00121/...).
 */
function google_place_address(array $components): array {
    $get = function (string $type, string $form = 'longText') use ($components) {
        foreach ($components as $c) {
            if (in_array($type, (array)($c['types'] ?? []), true)) return (string)($c[$form] ?? '');
        }
        return '';
    };

    /* "Via Diego Simonetti, 10": the comma is how the archive already writes a
     * street (`Lungomare Amerigo Vespucci, 144`), and how Italian addresses
     * read. Google hands the two parts separately and says nothing about how
     * to join them. */
    $route = $get('route');
    $number = $get('street_number');
    $street = $route !== '' && $number !== '' ? "$route, $number" : trim("$route $number");
    $postal = $get('postal_code');
    $country = $get('country', 'shortText');

    return [
        'postal' => array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => $street,
            'postalCode' => $postal,
            'addressLocality' => $get('locality') ?: $get('postal_town'),
            'addressRegion' => $get('administrative_area_level_2', 'shortText') ?: $get('administrative_area_level_1', 'shortText'),
            'addressCountry' => $country,
        ], fn($v) => $v !== ''),
        'postalCode' => $postal,
        'country' => $country,
    ];
}

/*
 * The opening hours.
 *
 * Google gives periods: an open point (day 0-6, Sunday first, hour, minute)
 * and usually a close point. schema.org wants one OpeningHoursSpecification
 * per stretch, with the day named. A place open twice a day gets two entries
 * for that day, which is exactly how the CMS's own legacy hours file wrote it.
 *
 * A period with no `close` means open around the clock; it is written as
 * 00:00-23:59 rather than left half-empty, because a specification with an
 * `opens` and no `closes` reads as broken data to everything downstream.
 */
function google_place_opening_hours(array $regular): array {
    $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $out = [];

    foreach ((array)($regular['periods'] ?? []) as $p) {
        if (!isset($p['open']['day'])) continue;
        $day = $days[(int)$p['open']['day']] ?? null;
        if ($day === null) continue;

        $opens = google_place_time($p['open']);
        $closes = isset($p['close']) ? google_place_time($p['close']) : '23:59';

        $out[] = [
            '@type' => 'OpeningHoursSpecification',
            'dayOfWeek' => $day,
            'opens' => $opens,
            'closes' => $closes,
        ];
    }
    return $out;
}

function google_place_time(array $point): string {
    return sprintf('%02d:%02d', (int)($point['hour'] ?? 0), (int)($point['minute'] ?? 0));
}

/*
 * The features worth keeping, as schema.org states them.
 *
 * Only the ones that are facts about the place and that someone would look
 * for: getting in with a wheelchair, and how one may pay. Each is written even
 * when false, because "no step-free entrance" is an answer and a missing key
 * is not — for accessibility that difference is the whole point.
 */
function google_place_features(array $g): array {
    $map = [
        'wheelchairAccessibleEntrance' => ['accessibilityOptions', 'wheelchairAccessibleEntrance'],
        'wheelchairAccessibleParking'  => ['accessibilityOptions', 'wheelchairAccessibleParking'],
        'wheelchairAccessibleRestroom' => ['accessibilityOptions', 'wheelchairAccessibleRestroom'],
        'acceptsCreditCards'           => ['paymentOptions', 'acceptsCreditCards'],
        'acceptsDebitCards'            => ['paymentOptions', 'acceptsDebitCards'],
        'acceptsCashOnly'              => ['paymentOptions', 'acceptsCashOnly'],
    ];

    $out = [];
    foreach ($map as $name => [$group, $key]) {
        if (!isset($g[$group][$key])) continue;
        $out[] = [
            '@type' => 'LocationFeatureSpecification',
            'name' => $name,
            'value' => (bool)$g[$group][$key],
        ];
    }
    return $out;
}

/* ---------------------------------------------------------------------------
 * Where the place goes
 * ------------------------------------------------------------------------- */

/*
 * The @id of a place: `places/<area>/<slug>`.
 *
 * The area is Meetoo's arrangement, kept as asked — every site has places, and
 * grouping them by postal area is what keeps an archive of hundreds readable.
 * It is the country code and the postal code (IT00121), which is stable, comes
 * with every Google answer, and sorts the way a person expects.
 *
 * Without a postal code the place still needs a home: `places/_` holds the
 * ones Google could not place, visibly, rather than scattering them at the
 * root where nobody would notice they are unfiled.
 */
function google_place_id_for(array $g, string $nameOverride = ''): string {
    $addr = google_place_address($g['addressComponents'] ?? []);
    $area = $addr['country'] !== '' && $addr['postalCode'] !== ''
          ? $addr['country'] . $addr['postalCode']
          : '_';
    $name = $nameOverride !== '' ? $nameOverride : (string)($g['displayName']['text'] ?? '');
    return 'places/' . $area . '/' . google_place_slug($name);
}

/* The slug: lowercase letters and digits, accents folded, everything else
 * dropped. The archive's existing folders are written this way
 * (`angolettodipampuelo`, `lapiccolaoasi-stellapolare`), so a place imported
 * today lands beside the ones imported last year. */
function google_place_slug(string $name): string {
    $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
    $s = $s === false ? $name : $s;
    $s = strtolower(preg_replace('/[^a-z0-9]/i', '', $s));
    return $s !== '' ? $s : 'place';
}
