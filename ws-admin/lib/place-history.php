<?php
/*
 * A SITE MOVES, CLOSES, AND KEEPS ITS PAST (CONTENT-STRUCTURE.md, 27 Sep 2026).
 *
 * The @id of a site carries its postcode - places/IT00121/teatrodellido - and
 * must say the truth:
 *
 *   - a new address in the SAME postcode: the address changes, the previous one
 *     goes into meetoo:addressHistory (with the day it ended and its Google
 *     Place ID), the @id stays;
 *   - a new address in ANOTHER postcode: the same, and the @id is RENAMED -
 *     folder moved, every file that names it updated, the old id kept in
 *     meetoo:formerIds, whose public address then redirects to the new one.
 *
 * The history serves past events: their page shows the address that was valid
 * on their date (place_at_date).
 *
 * A closed site keeps its page - past events point to it - and says so; it
 * leaves the lists and the choices for new events. Its status is Google's
 * business_status (OPERATIONAL, CLOSED_TEMPORARILY, CLOSED_PERMANENTLY), in
 * meetoo:business_status; meetoo:legalStatus, the name the Google import used
 * to write, is still read.
 */

require_once __DIR__ . '/ws-content-folders.php';

if (!function_exists('place_status')) {

    /** OPERATIONAL | CLOSED_TEMPORARILY | CLOSED_PERMANENTLY | '' (not said). */
    function place_status(array $e): string {
        $s = strtoupper(trim((string)($e['meetoo:business_status'] ?? $e['meetoo:legalStatus'] ?? '')));
        return in_array($s, ['OPERATIONAL', 'CLOSED_TEMPORARILY', 'CLOSED_PERMANENTLY'], true) ? $s : '';
    }

    function place_is_closed(array $e): bool {
        return place_status($e) === 'CLOSED_PERMANENTLY';
    }

    /** The country-and-postcode code of an address: IT + 00121 = IT00121, '' if incomplete. */
    function place_cap_from_address($a): string {
        if (!is_array($a)) return '';
        $pc = preg_replace('/\s+/', '', (string)($a['postalCode'] ?? ''));
        $c = $a['addressCountry'] ?? '';
        if (is_array($c)) $c = $c['identifier'] ?? $c['name'] ?? ($c[0] ?? '');
        $c = strtoupper(trim((string)$c));
        if (!preg_match('/^[A-Z]{2}$/', $c) || $pc === '') return '';
        return $c . $pc;
    }

    /** The code in an @id of the form places/<CODE>/<slug>, '' for a zone or a route. */
    function place_cap_from_id(string $id): string {
        return preg_match('#^places/([A-Z]{2}[0-9A-Z]{3,})/[^/]+$#', $id, $m) ? $m[1] : '';
    }

    /** Two addresses are the same place on the map? (street, number, postcode, town) */
    function place_same_address($a, $b): bool {
        $n = function ($x) {
            if (!is_array($x)) return '';
            $s = [];
            foreach (['streetAddress', 'postalCode', 'addressLocality'] as $k) {
                $s[] = mb_strtolower(preg_replace('/[\s,.]+/u', ' ', trim((string)($x[$k] ?? ''))));
            }
            return implode('|', $s);
        };
        return $n($a) === $n($b);
    }

    /**
     * Before saving $new over $stored: if the address changed, the old one goes
     * into the history. Returns true when it did.
     */
    function place_record_address_change(array $stored, array &$new, string $day = ''): bool {
        $old = $stored['address'] ?? null;
        if (!is_array($old) || !is_array($new['address'] ?? null) || place_same_address($old, $new['address'])) return false;
        $hist = is_array($new['meetoo:addressHistory'] ?? null) ? $new['meetoo:addressHistory']
              : (is_array($stored['meetoo:addressHistory'] ?? null) ? $stored['meetoo:addressHistory'] : []);
        $entry = ['address' => $old, 'validThrough' => $day !== '' ? $day : date('Y-m-d')];
        $pid = (string)($stored['meetoo:google_place_id'] ?? '');
        if ($pid !== '') $entry['meetoo:google_place_id'] = $pid;
        if (!empty($stored['meetoo:addressSince'])) $entry['validFrom'] = $stored['meetoo:addressSince'];
        $hist[] = $entry;
        $new['meetoo:addressHistory'] = $hist;
        $new['meetoo:addressSince'] = $entry['validThrough'];
        return true;
    }

    /** The place as it was on $iso (a date): with the address valid then. */
    function place_at_date(array $e, string $iso): array {
        $day = substr($iso, 0, 10);
        if ($day === '' || empty($e['meetoo:addressHistory'])) return $e;
        $hist = array_values(array_filter((array)$e['meetoo:addressHistory'], 'is_array'));
        usort($hist, fn($a, $b) => strcmp((string)($a['validThrough'] ?? ''), (string)($b['validThrough'] ?? '')));
        foreach ($hist as $h) {
            $fino = substr((string)($h['validThrough'] ?? ''), 0, 10);
            if ($fino !== '' && $day < $fino && is_array($h['address'] ?? null)) {
                $e['address'] = $h['address'];
                return $e;
            }
        }
        return $e;
    }

    /**
     * Renames $old to $new in every content file under $base (a locale folder),
     * whole ids only - except $skip, the renamed file itself, whose
     * meetoo:formerIds must keep the old one. Returns the files changed,
     * relative to $base.
     */
    function place_rename_refs(string $base, string $old, string $new, string $skip = ''): array {
        $out = [];
        foreach (ws_content_files($base, true) as $f) {
            if ($skip !== '' && realpath($f) === realpath($skip)) continue;
            $s = (string)@file_get_contents($f);
            if (strpos($s, "\"$old\"") === false) continue;
            $t = str_replace("\"$old\"", "\"$new\"", $s);
            if (json_decode($t) === null) continue;   // never write what does not parse
            if (@file_put_contents($f, $t) !== false) $out[] = trim(str_replace(rtrim($base, '/'), '', $f), '/');
        }
        return $out;
    }
}
