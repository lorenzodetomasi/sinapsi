<?php
/*
 * A UNIT: the index of the events in one content root.
 *
 * Reading three hundred `index.json` files to draw one list is the kind of
 * thing that works on the day it is written and not on the day the site has
 * three hundred events. So the events of a root are gathered, once, into a
 * derived file: `<root>/_index/events.json`. It is a cache, and it obeys the
 * rules for derived files — it can be deleted and it comes back.
 *
 * Nothing here knows about Meetoo, zones, beaches or postal areas. It scans a
 * root, reads what each event says about itself, and writes it down. Meetoo's
 * own `events/_index/events.json` — a flat record with `cap`, `organizerKey`,
 * `isChildrens` — stays where it is until Meetoo moves over; this is the one
 * every site gets, including Meetoo on the day it is a WS site like the others.
 *
 * The shape of an entry is the shape of an Event, not a record of its own.
 * That is what makes the matching below a comparison and not a translation:
 * a list page says what it collects with `about`, a pattern in schema.org's
 * words, and an event belongs to the list when that pattern is contained in
 * the event. One idea, no lookup table of field names.
 */

require_once __DIR__ . '/lib/derived.php';

if (!defined('WS_EVENTS_INDEX_GENERATOR')) {
    define('WS_EVENTS_INDEX_GENERATOR', 'events-index 2026.09.23');
}

/* ---------------------------------------------------------------------------
 * Building
 * ------------------------------------------------------------------------- */

function ws_events_index_path(string $root): string {
    return rtrim($root, '/') . '/_index/events.json';
}

/*
 * Scans `<root>/events/` and writes the index. Returns a report in the same
 * shape the other modules use: what would change, what is wrong, what was
 * done. With $apply false nothing is written.
 *
 * An event is a directory under `events/` with an `index.json` whose
 * `mainEntity` is an Event. Anything else there — the series pages, a stray
 * folder, Meetoo's own `_index` — is skipped and counted, not guessed at.
 */
function ws_events_index_build(string $root, bool $apply): array {
    $root = rtrim($root, '/');
    $dir = "$root/events";
    $rep = ['indexed' => 0, 'skipped' => 0, 'problems' => [], 'changed' => false];

    if (!is_dir($dir)) return $rep;

    $entries = [];
    foreach (ws_events_index_scan($dir) as $file) {
        $rel = 'events/' . trim(str_replace($dir, '', dirname($file)), '/');
        $data = json_decode((string)@file_get_contents($file), true);
        if (!is_array($data)) {
            $rep['problems'][] = "$rel: index.json illeggibile";
            $rep['skipped']++;
            continue;
        }
        $entry = ws_events_index_entry($data, $rel, $root);
        if ($entry === null) { $rep['skipped']++; continue; }
        $entries[] = $entry;
    }

    /* By date, soonest first; the ones without a date last, because a list
     * sorted by a missing value puts them wherever the sort happens to and
     * that changes between runs. */
    usort($entries, function ($a, $b) {
        $x = (string)($a['startDate'] ?? ''); $y = (string)($b['startDate'] ?? '');
        if ($x === '' && $y === '') return strcmp((string)$a['@id'], (string)$b['@id']);
        if ($x === '') return 1;
        if ($y === '') return -1;
        return strcmp($x, $y) ?: strcmp((string)$a['@id'], (string)$b['@id']);
    });

    $rep['indexed'] = count($entries);

    $json = json_encode([
        'generator' => WS_EVENTS_INDEX_GENERATOR,
        'built' => date('c'),
        'itemListElement' => $entries,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

    $path = ws_events_index_path($root);
    /* Compared without the timestamp: otherwise the file differs from itself
     * every time it is built, and "has anything changed" can never be answered. */
    $rep['changed'] = ws_events_index_body($json) !== ws_events_index_body((string)@file_get_contents($path));

    if ($apply && $rep['changed']) {
        $idx = dirname($path);
        if (!is_dir($idx) && !@mkdir($idx, 0775, true) && !is_dir($idx)) {
            $rep['problems'][] = "non posso creare $idx";
            return $rep;
        }
        if (@file_put_contents($path, $json) === false) {
            $rep['problems'][] = "non posso scrivere $path";
        }
    }
    return $rep;
}

/* The comparable part of the file: everything but the moment it was built. */
function ws_events_index_body(string $json): string {
    $d = json_decode($json, true);
    if (!is_array($d)) return $json;
    unset($d['built']);
    return json_encode($d);
}

/* Every `index.json` under events/, at any depth: an event may be filed in a
 * folder of its own or under a series. `_index`, `_trash` and `-`-prefixed
 * directories are never scanned — the README's rule, and the reason a draft
 * can be switched off by renaming its folder. */
function ws_events_index_scan(string $dir): array {
    $out = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            function ($current) {
                if (!$current->isDir()) return true;
                $name = $current->getFilename();
                return $name[0] !== '-' && $name[0] !== '_';
            }
        )
    );
    foreach ($it as $file) {
        if ($file->isFile() && $file->getFilename() === 'index.json') $out[] = $file->getPathname();
    }
    sort($out);
    return $out;
}

/*
 * One entry: the event as the list needs it, and nothing more.
 *
 * References stay references. `location` and `organizer` keep their `@id` and
 * gain the name and the town — which is denormalisation, on purpose and only
 * here: an index exists so a listing page does not open another file per row,
 * and a derived file may repeat what a source says. It is rebuilt when the
 * source changes, so it cannot drift for long.
 */
function ws_events_index_entry(array $data, string $rel, string $root): ?array {
    $e = $data['mainEntity'] ?? null;
    if (!is_array($e)) return null;

    $types = (array)($e['@type'] ?? []);
    if (!in_array('Event', $types, true)) return null;

    $entry = [
        '@id' => (string)($e['@id'] ?? $rel),
        '@type' => count($types) === 1 ? $types[0] : array_values($types),
        'name' => (string)($e['name'] ?? ''),
        'wspath' => (string)($data['wspath'] ?? ''),
    ];

    foreach (['startDate', 'endDate', 'eventStatus', 'eventAttendanceMode',
              'typicalAgeRange', 'isAccessibleForFree', 'image', 'superEvent'] as $k) {
        if (isset($e[$k]) && $e[$k] !== '' && $e[$k] !== []) $entry[$k] = $e[$k];
    }

    if (!empty($e['location'])) $entry['location'] = ws_events_index_ref($e['location'], $root, true);
    if (!empty($e['organizer'])) $entry['organizer'] = ws_events_index_ref($e['organizer'], $root, false);

    $entry['dateModified'] = (string)($e['dateModified'] ?? $data['dateModified'] ?? '');
    return $entry;
}

/*
 * A reference, enriched from the thing it points at.
 *
 * For a place, the chain of `containedInPlace` is copied in as well. That is
 * what lets a list say "events in this zone" without the listing code opening
 * every place to find out: the containment is already in the row. It is the
 * one piece of denormalisation that buys something real.
 */
function ws_events_index_ref($ref, string $root, bool $withContainment) {
    $list = isset($ref['@id']) || !is_array($ref) ? [$ref] : $ref;
    $out = [];

    foreach ((array)$list as $one) {
        if (!is_array($one)) { $out[] = $one; continue; }
        $id = (string)($one['@id'] ?? '');
        $r = array_filter([
            '@id' => $id,
            '@type' => $one['@type'] ?? null,
            'name' => $one['name'] ?? null,
        ], fn($v) => $v !== null && $v !== '');

        if ($id !== '') {
            $target = ws_events_index_load($root, $id);
            if ($target) {
                if (empty($r['name']) && !empty($target['name'])) $r['name'] = $target['name'];
                if (!empty($target['address']['addressLocality'])) {
                    $r['address'] = ['addressLocality' => $target['address']['addressLocality']];
                }
                if ($withContainment) {
                    $chain = ws_events_index_containment($root, $id);
                    if ($chain) $r['containedInPlace'] = $chain;
                }
            }
        }
        $out[] = $r;
    }
    return count($out) === 1 ? $out[0] : $out;
}

/* The mainEntity of a content named by @id, or null. */
function ws_events_index_load(string $root, string $id): ?array {
    if ($id === '' || strpos($id, '..') !== false) return null;
    $file = rtrim($root, '/') . '/' . $id . '/index.json';
    if (!is_file($file)) return null;
    $d = json_decode((string)@file_get_contents($file), true);
    return is_array($d) && is_array($d['mainEntity'] ?? null) ? $d['mainEntity'] : null;
}

/*
 * Everything a place is inside, outermost last: a beach inside a stretch of
 * coast inside a district. Followed by @id, with a stop after eight steps -
 * a place that contains itself would otherwise loop forever, and content can
 * always be wrong.
 */
function ws_events_index_containment(string $root, string $id, int $depth = 0): array {
    if ($depth > 8) return [];
    $place = ws_events_index_load($root, $id);
    $parent = $place['containedInPlace'] ?? null;
    if (!is_array($parent)) return [];
    $parentId = (string)($parent['@id'] ?? '');
    if ($parentId === '') return [];

    $out = [array_filter([
        '@id' => $parentId,
        '@type' => $parent['@type'] ?? null,
        'name' => $parent['name'] ?? null,
    ], fn($v) => $v !== null && $v !== '')];

    return array_merge($out, ws_events_index_containment($root, $parentId, $depth + 1));
}

/* ---------------------------------------------------------------------------
 * Reading and matching
 * ------------------------------------------------------------------------- */

/* The index, or an empty list. A listing page calls this and nothing else. */
function ws_events_index_read(string $root): array {
    $path = ws_events_index_path($root);
    if (!is_file($path)) return [];
    $d = json_decode((string)@file_get_contents($path), true);
    return is_array($d) ? (array)($d['itemListElement'] ?? []) : [];
}

/*
 * Does this event belong in a list that is `about` this pattern?
 *
 * The pattern is a partial description of the thing collected — an Event whose
 * organizer is X, an Event whose location is inside Y — and an event matches
 * when every statement in the pattern is true of it. So the test is: is the
 * pattern contained in the entry?
 *
 * Three ways a leaf compares:
 *   - `@id`               equality: this exact thing
 *   - `containedInPlace`  the chain the index carries: this, or anything inside it
 *   - anything else       equality of the value
 *
 * `@type` is special: an entry's types are a list (["Event","LiteraryEvent"]),
 * and a pattern asking for `Event` must match a LiteraryEvent. So a type in
 * the pattern matches when the entry declares it among its own.
 */
function ws_events_index_matches(array $entry, $about): bool {
    if (!is_array($about) || !$about) return true;   // a list about nothing collects everything

    foreach ($about as $key => $want) {
        if ($key === '@type') {
            $have = (array)($entry['@type'] ?? []);
            foreach ((array)$want as $t) {
                if (!in_array($t, $have, true)) return false;
            }
            continue;
        }

        $have = $entry[$key] ?? null;
        if ($have === null) return false;

        if (!ws_events_index_leaf_matches($have, $want)) return false;
    }
    return true;
}

/* One field of the pattern against one field of the entry. The entry's side
 * may be a list (an event has several organizers): one of them matching is
 * enough, which is what "organised by X" means when X is not alone. */
function ws_events_index_leaf_matches($have, $want): bool {
    if (!is_array($want)) return $have === $want || (string)$have === (string)$want;

    $candidates = (isset($have['@id']) || !is_array($have)) ? [$have] : $have;
    foreach ((array)$candidates as $one) {
        if (ws_events_index_one_matches($one, $want)) return true;
    }
    return false;
}

function ws_events_index_one_matches($one, array $want): bool {
    if (!is_array($one)) return false;

    foreach ($want as $k => $v) {
        if ($k === '@type') {
            /* The pattern's `{"@type": "Place"}` around a containment is a
             * description of the shape, not a filter: an index entry names the
             * place's own type, which may be Beach. A type here is checked
             * only when the entry states one. */
            $have = (array)($one['@type'] ?? []);
            if ($have && !array_intersect((array)$v, $have)) {
                /* Not a contradiction when the pattern only wraps a
                 * containment: Place is the general word for all of them. */
                if ((array)$v !== ['Place']) return false;
            }
            continue;
        }

        if ($k === 'containedInPlace') {
            $wantId = is_array($v) ? (string)($v['@id'] ?? '') : (string)$v;
            if ($wantId === '') return false;
            /* Inside it, or it: a list of a zone shows what happens in the
             * zone itself as well as in the places within it. */
            if ((string)($one['@id'] ?? '') === $wantId) continue;
            $chain = (array)($one['containedInPlace'] ?? []);
            $ids = array_map(fn($c) => is_array($c) ? (string)($c['@id'] ?? '') : (string)$c, $chain);
            if (!in_array($wantId, $ids, true)) return false;
            continue;
        }

        if (!isset($one[$k])) return false;
        if (is_array($v)) {
            if (!ws_events_index_one_matches($one[$k], $v)) return false;
        } elseif ((string)$one[$k] !== (string)$v) {
            return false;
        }
    }
    return true;
}

/*
 * The events of a list page, ready to print: matched, and split into the two
 * groups any listing of dated things needs.
 *
 * Past events are kept, newest first: an archive is the reason a site's events
 * page is worth linking to a year later. Upcoming first, soonest first.
 */
function ws_events_index_for(string $root, $about, ?string $now = null): array {
    $now = $now ?? date('c');
    $upcoming = $past = [];

    foreach (ws_events_index_read($root) as $entry) {
        if (!ws_events_index_matches($entry, $about)) continue;
        $end = (string)($entry['endDate'] ?? $entry['startDate'] ?? '');
        if ($end !== '' && $end < $now) $past[] = $entry; else $upcoming[] = $entry;
    }

    $past = array_reverse($past);
    return ['upcoming' => $upcoming, 'past' => $past];
}
