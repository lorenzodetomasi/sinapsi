<?php
/*
 * THE CREDITS OF AN EVENT, in the order and words of the programme.
 *
 *   "meetoo:credits": [
 *     { "roleName": "in scena", "property": "performer",
 *       "agents": [ { "@type": "Person", "name": "Giorgia Celli" } ] },
 *     { "roleName": "interpreti", "property": "performer",
 *       "agents": [ { "@type": "Person", "name": "Micol Arpa Rock",
 *                     "description": "arpa elettroacustica ed elettrica" }, … ] },
 *     { "roleName": "presentano", "property": "workPerformed.producer",
 *       "agents": [ { "@type": "PerformingGroup", "name": "Compagnia Sunny Side" } ] }
 *   ]
 *
 * A theatre programme says "ideazione e regia", "in scena", "a cura di": words
 * schema.org does not have, in an order that matters. So the file keeps the
 * programme's list as it is, and each row DECLARES the schema.org property it
 * stands for - chosen by whoever writes it, not guessed from the words:
 *
 *   performer, director, composer, funder, sponsor      -> on the Event
 *   workPerformed.author | .creator | .producer | .isBasedOn
 *                                                       -> on the work performed
 *   workFeatured.creator                                -> the works shown (an exhibition)
 *   ""                                                  -> shown, not translated
 *                                                          ("a cura di": schema.org
 *                                                          has no curator)
 *
 * The page shows the list; the JSON-LD gets the properties. An agent is a
 * Person, a PerformingGroup or an Organization; its description is the note
 * that goes with the name (the instrument). A link to a person's page (@id)
 * will come with those pages.
 */

if (!function_exists('event_credits')) {

    /** The schema.org properties a row may declare. */
    function event_credit_properties(): array {
        return ['performer', 'director', 'composer', 'funder', 'sponsor',
                'workPerformed.author', 'workPerformed.creator', 'workPerformed.producer', 'workPerformed.isBasedOn',
                'workFeatured.creator'];
    }

    /** The rows, cleaned: [['roleName' => , 'property' => , 'agents' => [..]], ...]. */
    function event_credits(array $e): array {
        $out = [];
        foreach ((array)($e['meetoo:credits'] ?? []) as $r) {
            if (!is_array($r)) continue;
            $agents = [];
            foreach ((array)($r['agents'] ?? []) as $a) {
                if (is_string($a)) $a = ['@type' => 'Person', 'name' => $a];
                if (is_array($a) && trim((string)($a['name'] ?? '')) !== '') $agents[] = $a;
            }
            if (!$agents) continue;
            $p = (string)($r['property'] ?? '');
            $out[] = [
                'roleName' => trim((string)($r['roleName'] ?? '')),
                'property' => in_array($p, event_credit_properties(), true) ? $p : '',
                'agents' => $agents,
            ];
        }
        return $out;
    }

    /** "A, B (basso) e C": the names of a row as a sentence. */
    function event_credits_names(array $agents): string {
        $n = array_map(function ($a) {
            $d = trim((string)($a['description'] ?? ''));
            return trim((string)$a['name']) . ($d !== '' ? " ($d)" : '');
        }, $agents);
        if (count($n) < 2) return implode('', $n);
        $ultimo = array_pop($n);
        return implode(', ', $n) . ' e ' . $ultimo;
    }

    /**
     * The schema.org properties the rows declare, ready to be merged into the
     * Event: ['performer' => [...], 'workPerformed' => {...}, ...]. Properties
     * the event already states itself are left alone.
     */
    function event_credits_schema(array $e): array {
        $out = [];
        $work = [];
        $featured = [];
        $agent = function ($a) {
            $x = ['@type' => in_array($a['@type'] ?? '', ['Person', 'PerformingGroup', 'Organization'], true) ? $a['@type'] : 'Person',
                  'name' => trim((string)$a['name'])];
            if (!empty($a['@id'])) $x['@id'] = $a['@id'];
            return $x;
        };
        foreach (event_credits($e) as $r) {
            $who = array_map($agent, $r['agents']);
            switch ($r['property']) {
                case 'performer': case 'director': case 'composer': case 'funder': case 'sponsor':
                    $out[$r['property']] = array_merge($out[$r['property']] ?? [], $who);
                    break;
                case 'workPerformed.isBasedOn':
                    $work['isBasedOn'] = array_merge($work['isBasedOn'] ?? [], array_map(
                        fn($w) => ['@type' => 'CreativeWork', 'author' => $w], $who));
                    break;
                case 'workPerformed.author': case 'workPerformed.creator': case 'workPerformed.producer':
                    $k = substr($r['property'], strlen('workPerformed.'));
                    $work[$k] = array_merge($work[$k] ?? [], $who);
                    break;
                case 'workFeatured.creator':
                    $featured = array_merge($featured, $who);
                    break;
            }
        }
        if ($work) $out['workPerformed'] = ['@type' => 'CreativeWork', 'name' => (string)($e['name'] ?? '')] + $work;
        if ($featured) $out['workFeatured'] = ['@type' => 'CreativeWork', 'creator' => $featured];
        foreach (array_keys($out) as $k) {
            if (!empty($e[$k])) unset($out[$k]);   // what the event says itself wins
        }
        return $out;
    }
}
