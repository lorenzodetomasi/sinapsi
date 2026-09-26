<?php
/*
 * AN OCCURRENCE INHERITS FROM ITS SERIES.
 *
 * Spritzalibro is one text, one image, one price, one organizer, and three
 * Thursdays. Each Thursday is a folder of its own - it has its own date, it
 * can have its own book, it can be cancelled - but writing the rest three
 * times means correcting it three times. So an occurrence writes only what is
 * its own (date, times, place when it differs, status, whatever it changes)
 * and takes the rest from the series named in its superEvent.
 *
 * Who reads an occurrence reads it through event_with_series(): the events
 * index, the event page, the JSON-LD. Nobody has to know which field came
 * from where.
 *
 * THE PAST DOES NOT MOVE. When a series changes, its occurrences that are
 * already over keep what they had: the archive tells what happened, not what
 * the series says today. event_series_freeze() does it, comparing each series
 * with a snapshot of the values its occurrences inherited, and writing the old
 * values into the past occurrences before the new ones would reach them.
 */

require_once __DIR__ . '/event-dates.php';

if (!function_exists('event_inherit')) {

    /** The fields an occurrence takes from its series when it does not say them. */
    function event_inherit_keys(): array {
        return ['name', 'alternateName', 'description', 'abstract', 'disambiguatingDescription',
                'image', 'logo', 'url', 'sameAs', 'keywords', 'inLanguage', 'about', 'genre',
                'location', 'organizer', 'performer', 'contributor', 'funder', 'sponsor',
                'offers', 'isAccessibleForFree', 'eventAttendanceMode', 'typicalAgeRange', 'audience',
                'maximumAttendeeCapacity', 'maximumPhysicalAttendeeCapacity', 'maximumVirtualAttendeeCapacity',
                'meetoo:timezone', 'meetoo:isChildrensEvent', 'meetoo:forSeparatedParents',
                'meetoo:childrenMustBeAccompanied', 'ws:icon'];
    }

    /** Is there nothing in this value? ('' and [] say nothing; false and 0 do.) */
    function event_inherit_empty($v): bool {
        return $v === null || $v === '' || $v === [];
    }

    /** The series an event belongs to, as a content path (events/slug), '' if none. */
    function event_series_ref(array $e): string {
        $s = $e['superEvent'] ?? null;
        if (is_array($s) && array_key_exists(0, $s)) $s = $s[0];
        $id = is_array($s) ? (string)($s['@id'] ?? '') : (is_string($s) ? $s : '');
        $id = trim($id, '/');
        if ($id === '') return '';
        return strpos($id, '/') === false ? "events/$id" : $id;
    }

    /** The entity of the document at $rel under $base (mainEntity unwrapped), null if none. */
    function event_load_doc(string $base, string $rel): ?array {
        static $cache = [];
        $f = rtrim($base, '/') . '/' . trim($rel, '/') . '/index.json';
        $k = $f . '@' . (is_file($f) ? filemtime($f) : 0);
        if (!array_key_exists($k, $cache)) {
            $j = is_file($f) ? json_decode((string)@file_get_contents($f), true) : null;
            $cache[$k] = is_array($j) ? (isset($j['mainEntity']) && is_array($j['mainEntity']) ? $j['mainEntity'] : $j) : null;
        }
        return $cache[$k];
    }

    function event_is_series(array $e): bool {
        return in_array('EventSeries', (array)($e['@type'] ?? []), true);
    }

    /**
     * A media path written by the series is relative to ITS folder
     * (media/cover.jpg): in the occurrence it must say where that folder is.
     * Paths already from the content root, and web addresses, stay as they are.
     */
    function event_inherit_media($v, string $seriesRel) {
        if ($seriesRel === '') return $v;
        $fix = function ($p) use ($seriesRel) {
            if (!is_string($p) || $p === '' || preg_match('#^(https?:)?//#i', $p)) return $p;
            if (preg_match('#^(events|places|organizations|categories|users|brand)/#', $p)) return $p;
            return trim($seriesRel, '/') . '/' . ltrim($p, '/');
        };
        if (is_string($v)) return $fix($v);
        if (is_array($v)) {
            if (array_key_exists(0, $v)) return array_map(fn($x) => event_inherit_media($x, $seriesRel), $v);
            foreach (['url', 'contentUrl', '@id'] as $k) if (isset($v[$k])) $v[$k] = $fix($v[$k]);
        }
        return $v;
    }

    /** $occ with what it does not say taken from $series (at $seriesRel). */
    function event_inherit(array $occ, array $series, string $seriesRel = ''): array {
        foreach (event_inherit_keys() as $k) {
            if (event_inherit_empty($occ[$k] ?? null) && !event_inherit_empty($series[$k] ?? null)) {
                $occ[$k] = in_array($k, ['image', 'logo'], true) ? event_inherit_media($series[$k], $seriesRel) : $series[$k];
            }
        }
        /* The kind of event: an occurrence that only says "Event" is whatever
         * its series is (a LiteraryEvent), minus being a series. */
        $own = array_values(array_diff((array)($occ['@type'] ?? []), ['Event']));
        if (!$own) {
            $from = array_values(array_diff((array)($series['@type'] ?? []), ['EventSeries']));
            if ($from) $occ['@type'] = count($from) === 1 ? $from[0] : $from;
        }
        return $occ;
    }

    /** The event as the site shows it: an occurrence completed by its series. */
    function event_with_series(string $base, array $e): array {
        if (event_is_series($e)) return $e;
        $ref = event_series_ref($e);
        if ($ref === '') return $e;
        $series = event_load_doc($base, $ref);
        return ($series && event_is_series($series)) ? event_inherit($e, $series, $ref) : $e;
    }

    /** The inherited fields of a series, the ones its occurrences may be using. */
    function event_series_heritage(array $series): array {
        $out = [];
        foreach (event_inherit_keys() as $k) {
            if (!event_inherit_empty($series[$k] ?? null)) $out[$k] = $series[$k];
        }
        $out['@type'] = $series['@type'] ?? 'EventSeries';
        return $out;
    }

    /**
     * Keeps the past as it was. For every series whose heritage changed since
     * the last snapshot, the occurrences that are already over get the OLD
     * values written into their own file, for the fields they did not say.
     * Then the snapshot is updated. The first time there is no snapshot: it is
     * only taken. Returns ['series' => n, 'frozen' => [paths]].
     */
    function event_series_freeze(string $base): array {
        $base = rtrim($base, '/');
        $snapDir = "$base/events/_index/heritage";
        $out = ['series' => 0, 'frozen' => []];
        $files = glob("$base/events/*/index.json") ?: [];
        $series = []; $members = [];
        foreach ($files as $f) {
            $rel = 'events/' . basename(dirname($f));
            $e = event_load_doc($base, $rel);
            if (!$e) continue;
            if (event_is_series($e)) { $series[$rel] = $e; continue; }
            $ref = event_series_ref($e);
            if ($ref !== '') $members[$ref][] = [$rel, $f];
        }
        if (!is_dir($snapDir) && !@mkdir($snapDir, 0775, true)) return $out;
        foreach ($series as $rel => $s) {
            $out['series']++;
            $now = event_series_heritage($s);
            $snapFile = "$snapDir/" . basename($rel) . '.json';
            $old = is_file($snapFile) ? json_decode((string)@file_get_contents($snapFile), true) : null;
            if (is_array($old) && $old != $now) {
                foreach ($members[$rel] ?? [] as [$mrel, $mfile]) {
                    $raw = json_decode((string)@file_get_contents($mfile), true);
                    if (!is_array($raw)) continue;
                    $wrapped = isset($raw['mainEntity']) && is_array($raw['mainEntity']);
                    $occ = $wrapped ? $raw['mainEntity'] : $raw;
                    $dates = event_dates_expand(event_inherit($occ, $old, $rel));
                    $last = end($dates['dates']);
                    $fine = $last ? (($last['end'] ?? '') !== '' ? $last['end'] : $last['start']) : '';
                    if ($fine === '' || strtotime(strlen($fine) === 10 ? "{$fine}T23:59:59" : $fine) >= time()) continue;
                    $frozen = event_inherit($occ, $old, $rel);
                    if ($frozen == $occ) continue;
                    if ($wrapped) $raw['mainEntity'] = $frozen; else $raw = $frozen;
                    if (@file_put_contents($mfile, json_encode($raw, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) !== false) {
                        $out['frozen'][] = $mrel;
                    }
                }
            }
            if (!is_array($old) || $old != $now) {
                @file_put_contents($snapFile, json_encode($now, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            }
        }
        return $out;
    }
}
