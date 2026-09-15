<?php
/**
 * One content: its JSON → its XML twin.
 *
 * The unit behind refresh-contents.php (the module that does a whole root)
 * and behind the front-end (ws_content_relpath() asks for a page's twin to
 * be fresh before reading it). A library, not a page: it prints nothing.
 *
 * The twin is jsonToWsx(json) - the pure conversion, XInclude elements left
 * in place for the CMS to resolve at request time. So the twin depends on
 * its JSON alone, and "is it fresh?" is one hash against the manifest
 * (lib/derived.php).
 *
 * Guards, inherited from Meetoo's xml-rebuild:
 *  - a JSON without @context and @type is not a content (a datalist, an
 *    RSVP list, a settings file);
 *  - an XML whose root element differs from what the JSON would produce is
 *    not this JSON's twin (the CMS's user record, a hand-written file): it
 *    is left alone and reported, unless told to adopt the new root;
 *  - an @id that is not a string would become "Array" inside an attribute,
 *    silently: better to stop and say so.
 *
 * @package WS
 * @subpackage Admin
 */

require_once __DIR__ . '/lib/derived.php';
require_once __DIR__ . '/../ws-core/json-to-xml.php';

if (!function_exists('ws_refresh_content')) {

    /** The twin's path: the same name with .xml. */
    function ws_content_twin(string $json_abspath): string {
        return preg_replace('/\.json$/i', '.xml', $json_abspath);
    }

    /** The root element's name of an XML string ('' when unreadable). */
    function ws_content_xml_root(string $xml): string {
        return preg_match('/<\s*([A-Za-z_][\w.:-]*)/', preg_replace('/<\?xml.*?\?>/s', '', $xml), $m) ? $m[1] : '';
    }

    /** The first path at which an @id is not a string, or null. */
    function ws_content_bad_id($x, string $path = ''): ?string {
        if (!is_array($x)) return null;
        if (array_key_exists('@id', $x) && !is_string($x['@id'])) return $path ?: '(root)';
        foreach ($x as $k => $v) {
            $r = ws_content_bad_id($v, $path === '' ? (string)$k : "$path/$k");
            if ($r !== null) return $r;
        }
        return null;
    }

    /**
     * Makes (or says it would make) the twin of one JSON.
     *
     * @param string     $json_abspath
     * @param bool       $apply     false = preview: report, write nothing
     * @param array      $options   'adopt_root' => true rewrites a twin whose
     *                              root element changed shape (a deliberate
     *                              model change); 'force' => true rebuilds
     *                              even when the manifest says it is fresh.
     * @param array|null $manifest  the root's manifest, when the caller runs
     *                              many units and saves once; null = load and
     *                              save here.
     * @return array  status: fresh | stale | rebuilt | created | skipped | failed
     *                plus source, target (relative to the root), root, why.
     */
    function ws_refresh_content(string $json_abspath, bool $apply = true, array $options = [], ?array &$manifest = null): array {
        $root = ws_derived_root($json_abspath);
        $xml_abspath = ws_content_twin($json_abspath);
        $out = [
            'status' => 'failed',
            'source' => $root ? ws_derived_rel($root, $json_abspath) : $json_abspath,
            'target' => $root ? ws_derived_rel($root, $xml_abspath) : $xml_abspath,
            'root'   => $root,
            'why'    => '',
        ];
        if ($root === '') { $out['why'] = 'not under a contents tree'; return $out; }
        if (!is_file($json_abspath)) { $out['why'] = 'source missing'; return $out; }

        $own_manifest = ($manifest === null);
        if ($own_manifest) $manifest = ws_derived_manifest($root);

        $hash = ws_derived_hash($json_abspath);
        $generator = WS_JSON_TO_XML_VERSION;
        $exists = is_file($xml_abspath);
        if (empty($options['force']) && !ws_derived_is_stale($manifest, $out['target'], $xml_abspath, $hash, $generator)) {
            $out['status'] = 'fresh';
            return $out;
        }

        $raw = (string)file_get_contents($json_abspath);
        $doc = json_decode($raw, true);
        if (!is_array($doc)) { $out['status'] = 'skipped'; $out['why'] = 'unreadable JSON'; return $out; }
        // A content is a JSON-LD document: @context and @type. A list of
        // registrations, a datalist, a fragment left mid-work have neither.
        if (empty($doc['@context']) || empty($doc['@type'])) { $out['status'] = 'skipped'; $out['why'] = 'no @context/@type: not a content'; return $out; }
        $bad = ws_content_bad_id($doc);
        if ($bad !== null) { $out['why'] = "@id is not a string at $bad"; return $out; }

        try {
            $xml = jsonToWsx($raw);
        } catch (Throwable $e) {
            $out['why'] = 'conversion failed: ' . $e->getMessage();
            return $out;
        }
        if (trim((string)$xml) === '' || $xml[0] === '{') { $out['why'] = 'conversion returned nothing'; return $out; }

        if ($exists && empty($options['adopt_root'])) {
            $old_root = ws_content_xml_root((string)file_get_contents($xml_abspath));
            $new_root = ws_content_xml_root($xml);
            if ($old_root !== '' && $old_root !== $new_root) {
                $out['status'] = 'skipped';
                $out['why'] = "not a twin: the XML's root is <$old_root>, the JSON would produce <$new_root>";
                return $out;
            }
        }

        if (!$apply) { $out['status'] = 'stale'; return $out; }

        if (@file_put_contents($xml_abspath, $xml, LOCK_EX) === false) {
            $out['why'] = 'cannot write ' . $out['target'];
            return $out;
        }
        $manifest[$out['target']] = ws_derived_entry($out['source'], $hash, $generator);
        if ($own_manifest && !ws_derived_manifest_save($root, $manifest)) {
            // The twin is written; only the bookkeeping failed. Say so, but the page is served.
            $out['why'] = 'twin written, manifest not saved (is _index/ writable?)';
        }
        $out['status'] = $exists ? 'rebuilt' : 'created';
        return $out;
    }

    /**
     * The front-end's entry: the twin of this JSON, fresh, as an absolute
     * path - or '' when there is none to read (the JSON is not a content, or
     * the tree cannot be written to and the twin does not exist), in which
     * case the caller reads the JSON itself.
     *
     * Cheap when nothing changed: one sha1 of a small file and a manifest
     * that is read once per request and per root. Never throws.
     */
    function ws_content_ensure_xml(string $json_abspath): string {
        static $manifests = [];
        $root = ws_derived_root($json_abspath);
        $xml_abspath = ws_content_twin($json_abspath);
        if ($root === '') return is_file($xml_abspath) ? $xml_abspath : '';
        if (!isset($manifests[$root])) $manifests[$root] = ws_derived_manifest($root);

        $rel = ws_derived_rel($root, $xml_abspath);
        if (!ws_derived_is_stale($manifests[$root], $rel, $xml_abspath, ws_derived_hash($json_abspath), WS_JSON_TO_XML_VERSION)) {
            return $xml_abspath;
        }
        $lock = ws_derived_lock($root);
        if (!$lock) return is_file($xml_abspath) ? $xml_abspath : '';
        try {
            // Someone may have built it while we waited for the lock.
            $manifests[$root] = ws_derived_manifest($root);
            $r = ws_refresh_content($json_abspath, true, [], $manifests[$root]);
            if (in_array($r['status'], ['rebuilt', 'created'], true)) {
                ws_derived_manifest_save($root, $manifests[$root]);
            }
        } finally {
            ws_derived_unlock($lock);
        }
        if (in_array($r['status'], ['fresh', 'rebuilt', 'created'], true)) return $xml_abspath;
        // skipped (not a content, or the XML is not a twin) / failed: what exists is what is read.
        return is_file($xml_abspath) ? $xml_abspath : '';
    }
}
?>
