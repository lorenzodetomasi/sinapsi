<?php
/*
 * A UNIT: the vocabulary of a content root.
 *
 * Keys outside schema.org carry a prefix that says whose word it is, and some
 * of them ended up under the wrong owner. `meetoo:icon` is the plain case: an
 * icon is something every site's menu wants, not something about Ostia, and
 * Meetoo is to become a WS site like the others. The word is the CMS's, so the
 * prefix is `ws:`.
 *
 * The test, for anything added here later: would a SECOND site want this word?
 * Yes -> `ws:`. No -> it stays the site's own. `meetoo:coastalPosition` and
 * `meetoo:m_from_border_south` describe a seafront in Ostia and are nobody
 * else's; they are not in the table and should not be.
 *
 * Before adding a rename, look for a schema.org property that already says it.
 * Several of Meetoo's keys have one - the Google place id is an `identifier`,
 * a timezone is `eventSchedule` / `Schedule.scheduleTimezone` - and those are
 * conversions, not renames: they belong in their own operation, because they
 * change the SHAPE of the value and not only its name.
 *
 * ---
 *
 * The edit is TEXTUAL, and that is the whole design of this file.
 *
 * Reading the JSON and writing it back would be shorter and would ruin every
 * file it touched: PHP's encoder does not preserve how a file was written. On
 * this archive it expands `"additionalType": ["a", "b"]` onto four lines and
 * re-indents a file written with two spaces to four. A rename of one key would
 * arrive as a diff of the whole file, and the next person to read that diff
 * would have no way to see what actually changed.
 *
 * So a key is renamed where it stands, the surrounding bytes are left alone,
 * and the result is checked by parsing it: the file must still be valid JSON,
 * and the new key must appear exactly as often as the old one did. If either
 * fails, the file is not written and is reported.
 */

if (!defined('WS_VOCABULARY_GENERATOR')) {
    define('WS_VOCABULARY_GENERATOR', 'vocabulary 2026.09.23');
}

/* The renames in force. Key: the old name. Value: the new one. */
function ws_vocabulary_renames(): array {
    return [
        'meetoo:icon' => 'ws:icon',
    ];
}

/* What each prefix stands for, so a file that gains a key also gains the
 * declaration that gives it meaning. A prefixed key whose prefix the @context
 * does not declare is not a key in another vocabulary: it is a key with a
 * colon in its name. */
function ws_vocabulary_prefixes(): array {
    return [
        'ws'     => 'https://localbiz.it/ws#',
        'meetoo' => 'https://meetoo.eu#',
    ];
}

/*
 * Runs the renames over a content root.
 *
 * Returns ['files', 'keys', 'details' => [rel => [what…]], 'problems' => […]].
 * With $apply false nothing is written and the report says what would be.
 */
function ws_vocabulary_migrate(string $root, bool $apply): array {
    $root = rtrim($root, '/');
    $rep = ['files' => 0, 'keys' => 0, 'details' => [], 'problems' => []];
    $renames = ws_vocabulary_renames();
    if (!$renames || !is_dir($root)) return $rep;

    foreach (ws_vocabulary_scan($root) as $file) {
        $rel = ltrim(str_replace($root, '', $file), '/');
        $raw = (string)@file_get_contents($file);
        if ($raw === '') continue;

        $out = $raw;
        $done = [];

        foreach ($renames as $from => $to) {
            $n = ws_vocabulary_rename_key($out, $from, $to);
            if ($n > 0) $done[] = "$from → $to ($n)";
        }
        if (!$done) continue;

        /* The prefix of every name introduced must be declared. */
        foreach ($renames as $from => $to) {
            if (strpos($raw, '"' . $from . '"') === false) continue;
            $prefix = substr($to, 0, strpos($to, ':'));
            $added = ws_vocabulary_declare_prefix($out, $prefix);
            if ($added === null) {
                $rep['problems'][] = "$rel: non trovo dove dichiarare «{$prefix}» nel @context; file non toccato";
                continue 2;
            }
            if ($added) $done[] = "@context: +$prefix";
        }

        /* The check that makes a textual edit safe to run on someone's
         * content: it must still parse, and it must say the same thing. */
        $before = json_decode($raw, true);
        $after  = json_decode($out, true);
        if (!is_array($after)) {
            $rep['problems'][] = "$rel: dopo la modifica il JSON non si legge più; file non toccato";
            continue;
        }
        if (is_array($before) && !ws_vocabulary_same_shape($before, $after, $renames)) {
            $rep['problems'][] = "$rel: il contenuto non corrisponde dopo la rinomina; file non toccato";
            continue;
        }

        $rep['files']++;
        $rep['keys'] += array_sum(array_map(fn($d) => (int)(preg_match('/\((\d+)\)$/', $d, $m) ? $m[1] : 0), $done));
        $rep['details'][$rel] = $done;

        if ($apply && @file_put_contents($file, $out) === false) {
            $rep['problems'][] = "$rel: non posso scrivere";
        }
    }

    return $rep;
}

/*
 * Renames a key where it stands. Returns how many were renamed.
 *
 * A key in JSON is a string followed by a colon, so that is what is matched -
 * the same text sitting in a VALUE is left alone. The whitespace between the
 * name and the colon is kept as it was, because this function's whole job is
 * to change nothing it was not asked to change.
 */
function ws_vocabulary_rename_key(string &$json, string $from, string $to): int {
    $count = 0;
    $json = preg_replace_callback(
        '/"' . preg_quote($from, '/') . '"(\s*):/',
        function ($m) use ($to, &$count) { $count++; return '"' . $to . '"' . $m[1] . ':'; },
        $json
    );
    return $count;
}

/*
 * Makes sure the @context declares a prefix, adding it beside one that is
 * already there so the file's own indentation is inherited.
 *
 * Returns true when it added the declaration, false when it was already there,
 * and null when it could not find a place - a file whose context is a bare
 * string, say. Null means "leave this file alone and say so": guessing how to
 * turn a string context into an object is how a migration corrupts content.
 */
function ws_vocabulary_declare_prefix(string &$json, string $prefix): ?bool {
    if (preg_match('/"' . preg_quote($prefix, '/') . '"\s*:\s*"[^"]*#"/', $json)) {
        return false;
    }
    $iri = ws_vocabulary_prefixes()[$prefix] ?? null;
    if ($iri === null) return null;

    /* Beside the last prefix already declared: same line shape, same indent. */
    if (!preg_match_all('/([ \t]*)"([A-Za-z_][\w-]*)"(\s*):(\s*)"([^"]*#)"/', $json, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
        return null;
    }
    $last = end($m);
    $whole = $last[0][0];
    $at = $last[0][1];
    $indent = $last[1][0];

    $line = ($indent !== '' ? ",\n" . $indent : ', ')
          . '"' . $prefix . '"' . $last[3][0] . ':' . $last[4][0] . '"' . $iri . '"';
    $json = substr($json, 0, $at + strlen($whole)) . $line . substr($json, $at + strlen($whole));
    return true;
}

/*
 * Is the document the same, once the renames are accounted for?
 *
 * Both sides are walked with the old names mapped to the new ones, so a
 * document that differs anywhere else - because a regex reached into a value,
 * or a context was mangled - is caught before anything is written.
 */
function ws_vocabulary_same_shape($before, $after, array $renames): bool {
    return ws_vocabulary_normalise($before, $renames) == ws_vocabulary_normalise($after, $renames);
}

function ws_vocabulary_normalise($node, array $renames) {
    if (is_array($node)) {
        $out = [];
        foreach ($node as $k => $v) {
            if (is_string($k) && isset($renames[$k])) $k = $renames[$k];
            $out[$k] = ws_vocabulary_normalise($v, $renames);
        }
        /* The @context gains a declaration, which is the point; comparing it
         * would report every migrated file as changed. */
        unset($out['@context']);
        return $out;
    }
    return $node;
}

/* Every .json of a root, minus the places the rules say never to scan:
 * `_index`, `_trash`, and anything switched off with a leading `-`. */
function ws_vocabulary_scan(string $root): array {
    $out = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            function ($current) {
                if (!$current->isDir()) return true;
                $name = $current->getFilename();
                return $name[0] !== '-' && $name[0] !== '_';
            }
        )
    );
    foreach ($it as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'json') $out[] = $file->getPathname();
    }
    sort($out);
    return $out;
}
