<?php
/**
 * The manifest of derived files.
 *
 * A content root (`contents/<site>/<locale>`, or `contents/<site>` when the
 * site has no locale) keeps in `_index/derived.json` one record per file
 * that was DERIVED from a source: the XML twin of a JSON, later the site
 * map, the HTML cache, the resized images. The record says which source,
 * the hash the source had when the file was built, which generator built
 * it and when.
 *
 * "Is it stale?" is answered from that: the file is missing, or nobody
 * recorded it, or the source's hash has changed, or the generator has
 * changed version. Never from the files' dates - an FTP upload gives every
 * file the hour of the upload, and a twin uploaded a second after its
 * source looks fresh whatever it contains.
 *
 * The manifest is itself disposable: delete it and every derived file is
 * rebuilt once, then recorded again.
 *
 * Writes are serialised with a lock file next to the manifest, so that two
 * requests asking for the same page do not build it twice, and a full run
 * from the hub does not race a visitor.
 *
 * No dependency on the CMS's globals: usable from the hub (standalone) and
 * from the front-end alike.
 *
 * @package WS
 * @subpackage Admin
 */

if (!defined('WS_DERIVED_MANIFEST')) {
    define('WS_DERIVED_MANIFEST', '_index/derived.json');
}

if (!function_exists('ws_derived_root')) {

    /**
     * The content root a file belongs to: the locale directory when the path
     * has one (`…/contents/isotype/it_IT`), the site directory otherwise
     * (`…/contents/isotype`). '' when the path is not under a contents tree.
     */
    function ws_derived_root(string $abspath): string {
        $abspath = str_replace('\\', '/', $abspath);
        if (!preg_match('#^(.*/contents)/([^/]+)(?:/([a-z]{2}_[A-Z]{2}))?(?:/|$)#', $abspath, $m)) {
            return '';
        }
        return $m[1] . '/' . $m[2] . (!empty($m[3]) ? '/' . $m[3] : '');
    }

    /** A directory scans never enter: switched off, or not content. */
    function ws_derived_skip_dir(string $name): bool {
        return $name === '' || $name[0] === '-' || $name[0] === '.'
            || in_array($name, ['_index', '_trash', 'users', 'media', 'media-sources', 'node_modules'], true);
    }

    function ws_derived_manifest_path(string $root): string {
        return rtrim($root, '/') . '/' . WS_DERIVED_MANIFEST;
    }

    /** The manifest as an array (empty when there is none yet). */
    function ws_derived_manifest(string $root): array {
        $f = ws_derived_manifest_path($root);
        if (!is_file($f)) return [];
        $m = json_decode((string)@file_get_contents($f), true);
        return is_array($m) ? $m : [];
    }

    /** Writes the manifest; creates `_index/` on the way. */
    function ws_derived_manifest_save(string $root, array $manifest): bool {
        $f = ws_derived_manifest_path($root);
        $dir = dirname($f);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) return false;
        ksort($manifest);
        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return @file_put_contents($f, $json . "\n", LOCK_EX) !== false;
    }

    /** The hash a source is recorded with. */
    function ws_derived_hash(string $abspath): string {
        return is_file($abspath) ? sha1_file($abspath) : '';
    }

    /** A path relative to the root, with forward slashes. */
    function ws_derived_rel(string $root, string $abspath): string {
        $root = rtrim(str_replace('\\', '/', $root), '/') . '/';
        $abspath = str_replace('\\', '/', $abspath);
        return strpos($abspath, $root) === 0 ? substr($abspath, strlen($root)) : $abspath;
    }

    /**
     * Whether a derived file needs (re)building.
     *
     * @param array  $manifest    the root's manifest (pass it in: a full run
     *                            reads it once and asks a hundred times)
     * @param string $target_rel  the derived file, relative to the root
     * @param string $target_abs  the same, absolute (to see whether it exists)
     * @param string $source_hash the source's current hash
     * @param string $generator   the generator's current version
     */
    function ws_derived_is_stale(array $manifest, string $target_rel, string $target_abs, string $source_hash, string $generator): bool {
        if (!is_file($target_abs)) return true;
        $r = $manifest[$target_rel] ?? null;
        if (!is_array($r)) return true;
        return ($r['hash'] ?? '') !== $source_hash || ($r['generator'] ?? '') !== $generator;
    }

    /** The record of a freshly built file. */
    function ws_derived_entry(string $source_rel, string $source_hash, string $generator): array {
        return [
            'source'    => $source_rel,
            'hash'      => $source_hash,
            'generator' => $generator,
            'built'     => date('c'),
        ];
    }

    /**
     * Takes the root's lock. Returns the handle to give back to
     * ws_derived_unlock(), or false when the lock cannot be had (an
     * unwritable root): the caller then does without writing.
     *
     * @param bool $wait  true blocks until the lock is free; false gives up
     *                    at once when another process holds it.
     */
    function ws_derived_lock(string $root, bool $wait = true) {
        $f = dirname(ws_derived_manifest_path($root)) . '/derived.lock';
        $dir = dirname($f);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) return false;
        $h = @fopen($f, 'c');
        if (!$h) return false;
        if (!flock($h, LOCK_EX | ($wait ? 0 : LOCK_NB))) { fclose($h); return false; }
        return $h;
    }

    function ws_derived_unlock($handle): void {
        if ($handle) { flock($handle, LOCK_UN); fclose($handle); }
    }
}
?>
