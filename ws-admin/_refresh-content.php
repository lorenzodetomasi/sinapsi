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
 * Until the migration is over a content may still be a hand-written .wsx
 * (the headings, the locations, the shared lists). It gets the same
 * treatment from the same functions - source .wsx, twin .xml, hash in the
 * manifest - with one difference in the build: the twin is the .wsx with
 * its includes RESOLVED, which is what the old admin refresh produced and
 * what these files' readers expect. A .wsx beside a JSON of the same name
 * is not a source any more (the JSON is); ws_sitemap.wsx is never one (the
 * map is derived by _refresh-sitemap.php).
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

/* The version of the .wsx → .xml export, recorded with every twin made
 * from a .wsx (see WS_JSON_TO_XML_VERSION for the JSON side). */
if (!defined('WS_WSX_EXPORT_VERSION')) {
    define('WS_WSX_EXPORT_VERSION', 'wsx-export 2026.09.17');
}

if (!function_exists('ws_refresh_content')) {

    /** The twin's path: the same name with .xml. */
    function ws_content_twin(string $source_abspath): string {
        return preg_replace('/\.(json|wsx)$/i', '.xml', $source_abspath);
    }

    /** Whether a .wsx is still a source: not switched off, not the map, not shadowed by a JSON of the same name. */
    function ws_content_wsx_is_source(string $wsx_abspath): bool {
        $name = basename($wsx_abspath);
        if ($name[0] === '-' || $name[0] === '.' || $name === 'ws_sitemap.wsx') return false;
        return !is_file(preg_replace('/\.wsx$/i', '.json', $wsx_abspath));
    }

    /** The generator a source's twin is recorded with. */
    function ws_content_generator(string $source_abspath): string {
        return preg_match('/\.wsx$/i', $source_abspath) ? WS_WSX_EXPORT_VERSION : WS_JSON_TO_XML_VERSION;
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
     * A hand-written .wsx as its readers want it: includes resolved, one
     * document. What the old admin refresh did with wsx_export_xml(), minus
     * the page it printed. '' and a reason when the file cannot be read.
     */
    function ws_content_export_wsx(string $wsx_abspath, ?string &$why = null): string {
        $prev = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $ok = $dom->load($wsx_abspath);
        if ($ok) $dom->xinclude();
        $errors = libxml_get_errors();
        libxml_clear_errors(); libxml_use_internal_errors($prev);
        if (!$ok || !$dom->documentElement) { $why = 'unreadable XML' . ($errors ? ': ' . trim($errors[0]->message) : ''); return ''; }
        // xinclude() leaves xml:base on what it brought in; not content.
        $x = new DOMXPath($dom);
        foreach (iterator_to_array($x->query('//@xml:base')) as $a) $a->ownerElement->removeAttributeNode($a);
        $xml = $dom->saveXML();
        return $xml === false ? '' : $xml;
    }

    /**
     * Makes (or says it would make) the twin of one source - a JSON, or a
     * .wsx not yet migrated.
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
    function ws_refresh_content(string $source_abspath, bool $apply = true, array $options = [], ?array &$manifest = null): array {
        $root = ws_derived_root($source_abspath);
        $xml_abspath = ws_content_twin($source_abspath);
        $is_wsx = (bool)preg_match('/\.wsx$/i', $source_abspath);
        $out = [
            'status' => 'failed',
            'source' => $root ? ws_derived_rel($root, $source_abspath) : $source_abspath,
            'target' => $root ? ws_derived_rel($root, $xml_abspath) : $xml_abspath,
            'root'   => $root,
            'why'    => '',
        ];
        if ($root === '') { $out['why'] = 'not under a contents tree'; return $out; }
        if (!is_file($source_abspath)) { $out['why'] = 'source missing'; return $out; }
        if ($is_wsx && !ws_content_wsx_is_source($source_abspath)) { $out['status'] = 'skipped'; $out['why'] = 'not a source: switched off, the map, or a JSON stands beside it'; return $out; }

        $own_manifest = ($manifest === null);
        if ($own_manifest) $manifest = ws_derived_manifest($root);

        $hash = ws_derived_hash($source_abspath);
        $generator = ws_content_generator($source_abspath);
        $exists = is_file($xml_abspath);
        if (empty($options['force']) && !ws_derived_is_stale($manifest, $out['target'], $xml_abspath, $hash, $generator)) {
            $out['status'] = 'fresh';
            return $out;
        }

        if ($is_wsx) {
            $xml = ws_content_export_wsx($source_abspath, $why);
            if ($xml === '') { $out['why'] = $why; return $out; }
        } else {
            $raw = (string)file_get_contents($source_abspath);
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
        }

        // The root guard is for an XML of unknown provenance. One the manifest
        // records as derived from THIS source is a twin whatever its root -
        // the source's @type may have changed since, and that is exactly what
        // a rebuild is for. Without this, a page whose types were reordered
        // stayed stuck behind "not a twin" until someone ticked adopt_root.
        $recorded = (($manifest[$out['target']]['source'] ?? null) === $out['source']);
        if ($exists && !$recorded && empty($options['adopt_root'])) {
            $old_root = ws_content_xml_root((string)file_get_contents($xml_abspath));
            $new_root = ws_content_xml_root($xml);
            if ($old_root !== '' && $old_root !== $new_root) {
                $out['status'] = 'skipped';
                $out['why'] = "not a twin: the XML's root is <$old_root>, the source would produce <$new_root>";
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
     * The front-end's entry: the twin of this source (a JSON or a .wsx),
     * fresh, as an absolute path - or '' when there is none to read (the
     * file is not a content, or the tree cannot be written to and the twin
     * does not exist), in which case the caller reads the source itself.
     *
     * Cheap when nothing changed: one sha1 of a small file and a manifest
     * that is read once per request and per root. Never throws.
     */
    function ws_content_ensure_xml(string $source_abspath): string {
        static $manifests = [];
        $root = ws_derived_root($source_abspath);
        $xml_abspath = ws_content_twin($source_abspath);
        if ($root === '') return is_file($xml_abspath) ? $xml_abspath : '';
        if (!isset($manifests[$root])) $manifests[$root] = ws_derived_manifest($root);

        $rel = ws_derived_rel($root, $xml_abspath);
        if (!ws_derived_is_stale($manifests[$root], $rel, $xml_abspath, ws_derived_hash($source_abspath), ws_content_generator($source_abspath))) {
            return $xml_abspath;
        }
        $lock = ws_derived_lock($root);
        if (!$lock) return is_file($xml_abspath) ? $xml_abspath : '';
        try {
            // Someone may have built it while we waited for the lock.
            $manifests[$root] = ws_derived_manifest($root);
            $r = ws_refresh_content($source_abspath, true, [], $manifests[$root]);
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
