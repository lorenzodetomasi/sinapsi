<?php
/**
 * Glossaries: where they are, who may change them, how a change is written.
 *
 * A glossary is a folder of a content root holding `glossary.jsonld` (a
 * schema.org DefinedTermSet, see ws-custom/themes/isotype/glossary/README.md).
 * Beside it:
 *   proposals/<name>.jsonld   a whole new version waiting for review; once
 *                             applied it is renamed `-<name>.jsonld` (off, kept)
 *   history/<stamp>-<uid>.jsonld   the version that an apply replaced
 *   history/log.jsonl              one line per apply: who, when, what
 *
 * Nothing here trusts a path from the request: a glossary is named by its
 * folder relative to a root that ws_admin_sites() knows, and must resolve
 * inside that root; a proposal is a bare file name.
 */

require_once __DIR__ . '/../../lib/ws-auth.php';
require_once __DIR__ . '/../../lib/ws-sites.php';
// The app's own library - the catalogue, the markup whitelist, the model - and its EPUB.
require_once __DIR__ . '/../../../ws-custom/themes/isotype/glossary/epub.php';

/* ---- Who ---------------------------------------------------------------------- */

/** The users.xml of a root: the locale's own, or the site's. */
function glossary_users_xml(string $root): ?string
{
    foreach ([$root . '/users/users.xml', dirname($root) . '/users/users.xml'] as $f) {
        if (is_file($f)) return $f;
    }
    return null;
}

/**
 * Who is asking, from the session the google-login plugin opens.
 *
 * On PHP's own development server, and only from this machine, an unsigned
 * visitor is a local super-admin: Google Sign-In cannot run on localhost:8091,
 * and without this the module could not be tried at all. `cli-server` is never
 * what serves the site in production.
 */
function glossary_user(string $root): ?array
{
    $user = ws_autentica_sessione(glossary_users_xml($root));
    if ($user) {
        // "Super-admins shared across sites" (ws-admin/README.md): whoever is one
        // on any site is one here, even where this site's users.xml says less.
        if ($user['role'] !== 'super-admin' && glossary_is_super_admin_anywhere($user['uid'])) $user['role'] = 'super-admin';
        return $user;
    }
    $local = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
    if (PHP_SAPI === 'cli-server' && $local) {
        return ['uid' => 'local', 'email' => '', 'name' => 'localhost', 'role' => 'super-admin', 'dev' => true];
    }
    return null;
}

function glossary_is_super_admin_anywhere(string $uid): bool
{
    $seen = [];
    foreach (ws_admin_sites(ws_admin_contents_abspath()) as $s) {
        $xml = glossary_users_xml($s['path']);
        if (!$xml || isset($seen[$xml])) continue;
        $seen[$xml] = true;
        if (ws_ruolo_utente($uid, $xml) === 'super-admin') return true;
    }
    return false;
}

/** The @id of every Person the glossary names as author, editor or contributor. */
function glossary_people(array $g): array
{
    $ids = [];
    foreach (['author', 'editor', 'contributor', 'creator'] as $k) {
        foreach (ws_ref_ids($g[$k] ?? null) as $id) $ids[] = $id;
    }
    return array_values(array_unique($ids));
}

/** Admins always; otherwise whoever the glossary itself names (users/<uid>). */
function glossary_can_review(array $g, array $user): bool
{
    if (in_array($user['role'] ?? '', ['admin', 'super-admin'], true)) return true;
    $uid = (string)($user['uid'] ?? '');
    return $uid !== '' && in_array("users/$uid", glossary_people($g), true);
}

/** May create a new glossary in this root: an admin, or a user of the site. */
function glossary_can_create(array $user): bool
{
    return in_array($user['role'] ?? '', ['user', 'client', 'admin', 'super-admin'], true);
}

/* ---- Where ------------------------------------------------------------------------ */

/** Every glossary of a root, as folder (relative) => its file's absolute path. */
function glossary_find(string $root): array
{
    $out = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            // Switched off (-), private (_), media: nothing to find there.
            static fn($f) => !$f->isDir() || !preg_match('/^[-_.]|^media(-sources)?$|^(history|proposals)$/', $f->getFilename())
        ),
        RecursiveIteratorIterator::SELF_FIRST
    );
    $it->setMaxDepth(5);
    foreach ($it as $f) {
        if ($f->isFile() && $f->getFilename() === GLOSSARY_FILE) {
            $rel = ltrim(substr($f->getPath(), strlen($root)), '/');
            $out[$rel] = $f->getPathname();
        }
    }
    ksort($out);
    return $out;
}

/** A glossary folder named in a request, or null if it is not one. */
function glossary_dir(string $root, string $rel): ?string
{
    if ($rel === '' || str_contains($rel, '..') || !preg_match('#^[A-Za-z0-9._/-]+$#', $rel)) return null;
    $dir = realpath($root . '/' . $rel);
    $base = realpath($root);
    if (!$dir || !$base || !str_starts_with($dir . '/', $base . '/') || !is_file($dir . '/' . GLOSSARY_FILE)) return null;
    return $dir;
}

function glossary_read(string $file): ?array
{
    if (!is_file($file) || filesize($file) > GLOSSARY_MAX_BYTES) return null;
    $g = json_decode((string)file_get_contents($file), true);
    return is_array($g) ? $g : null;
}

/** Proposals waiting in a glossary folder: name => [file, modified]. Applied ones start with `-`. */
function glossary_proposals(string $dir): array
{
    $out = [];
    foreach (glob($dir . '/proposals/*.jsonld') ?: [] as $f) {
        $name = basename($f);
        if ($name[0] === '-') continue;
        $out[$name] = ['file' => $f, 'modified' => filemtime($f)];
    }
    ksort($out);
    return $out;
}

function glossary_proposal_file(string $dir, string $name): ?string
{
    if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*\.jsonld$/', $name)) return null;
    $f = $dir . '/proposals/' . $name;
    return is_file($f) ? $f : null;
}

/* ---- Checking and writing --------------------------------------------------------------- */

/** What is wrong with a glossary so badly that it must not be saved. */
function glossary_errors(array $g): array
{
    $errors = [];
    $types = (array)($g['@type'] ?? []);
    if (!in_array('DefinedTermSet', $types, true)) $errors[] = glossary_t('The file is not a DefinedTermSet.');
    if (!is_array($g['hasDefinedTerm'] ?? null)) $errors[] = glossary_t('The glossary has no entries.');
    $seen = [];
    foreach ((array)($g['hasDefinedTerm'] ?? []) as $t) {
        $id = is_array($t) ? (string)($t['@id'] ?? '') : '';
        if ($id === '' || trim((string)($t['name'] ?? '')) === '') { $errors[] = glossary_t('An entry has no @id or no name.'); continue; }
        if (isset($seen[$id])) $errors[] = glossary_t('Duplicate id: %s', $id);
        $seen[$id] = true;
    }
    return $errors;
}

function glossary_encode(array $g): string
{
    return json_encode($g, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
}

/**
 * Write a new version: the old one goes to history/, the proposal (if any) is
 * switched off, a line is added to the log. Returns the history file.
 */
function glossary_apply(string $dir, array $new, array $user, ?string $proposal, array $summary): string
{
    $file = $dir . '/' . GLOSSARY_FILE;
    $history = $dir . '/history';
    if (!is_dir($history) && !mkdir($history, 0775, true)) throw new RuntimeException(glossary_t('Cannot create %s.', 'history/'));
    $stamp = date('Ymd-His');
    $who = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($user['uid'] ?? 'unknown'));
    $backup = "$history/$stamp-$who.jsonld";
    if (!copy($file, $backup)) throw new RuntimeException(glossary_t('Cannot save the previous version.'));

    $tmp = $file . '.tmp';
    if (file_put_contents($tmp, glossary_encode($new), LOCK_EX) === false || !rename($tmp, $file)) {
        throw new RuntimeException(glossary_t('Cannot write the glossary.'));
    }
    if ($proposal) {
        $p = glossary_proposal_file($dir, $proposal);
        if ($p) rename($p, dirname($p) . '/-' . basename($p));
    }
    file_put_contents("$history/log.jsonl", json_encode([
        'at' => date('c'), 'by' => $user['email'] ?: ($user['name'] ?? ''), 'uid' => $user['uid'] ?? '',
        'proposal' => $proposal, 'previous' => basename($backup), 'summary' => $summary,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
    return basename($backup);
}
