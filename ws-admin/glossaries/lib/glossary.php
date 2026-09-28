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
    glossary_log($dir, $user, ['action' => 'apply', 'proposal' => $proposal, 'previous' => basename($backup), 'summary' => $summary]);
    return basename($backup);
}

/** One line in history/log.jsonl: who, when, what. */
function glossary_log(string $dir, array $user, array $what): void
{
    if (!is_dir($dir . '/history')) @mkdir($dir . '/history', 0775, true);
    file_put_contents($dir . '/history/log.jsonl', json_encode(
        ['at' => date('c'), 'by' => ($user['email'] ?? '') ?: ($user['name'] ?? ''), 'uid' => $user['uid'] ?? ''] + $what,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
}

/** "2" -> "3", "1.4" -> "1.5", "" -> "1". */
function glossary_next_version(string $v): string
{
    if (preg_match('/^(.*?)(\d+)$/', trim($v), $m)) return $m[1] . ((int)$m[2] + 1);
    return trim($v) === '' ? '1' : trim($v) . '.1';
}

/* ---- A new glossary --------------------------------------------------------------------- */

/** Where new glossaries go: the folders that already hold one, or glossaries/ when there is none. */
function glossary_parents(string $root): array
{
    $parents = array_values(array_unique(array_map('dirname', array_keys(glossary_find($root)))));
    $parents = array_values(array_filter($parents, static fn($p) => $p !== '.'));
    return $parents ?: ['glossaries'];
}

/**
 * A glossary from nothing, in <root>/<parent>/<slug>/:
 *   glossary.jsonld           empty: a name, a language, who made it
 *   proposals/<date>-v1.jsonld   the first version, to open in the editor
 *   index.json                its page, as a draft, when the parent folder is
 *                             a page itself (/progetti/glossari/<slug>)
 * The one who creates it is its `creator`: it is theirs to edit (glossary_people()).
 * Returns [folder relative to the root, proposal name, whether a page was made].
 */
function glossary_create(string $root, string $parent, string $slug, string $name, string $author, array $user): array
{
    $slug = glossary_slug($slug !== '' ? $slug : $name);
    $parent = trim($parent, '/');
    if ($name === '' || !preg_match('#^[a-z0-9][a-z0-9_-]*(/[a-z0-9][a-z0-9_-]*)*$#', $parent) || !preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug)) {
        throw new InvalidArgumentException(glossary_t('Invalid request.'));
    }
    $base = realpath($root);
    $parentAbs = $base . '/' . $parent;
    $dir = $parentAbs . '/' . $slug;
    if (file_exists($dir)) throw new RuntimeException(glossary_t('A folder with this name already exists: %s', "$parent/$slug"));
    if (!is_dir($parentAbs) && !mkdir($parentAbs, 0775, true)) throw new RuntimeException(glossary_t('Cannot create %s.', $parent));
    if (!mkdir($dir . '/proposals', 0775, true)) throw new RuntimeException(glossary_t('Cannot create %s.', "$parent/$slug"));

    $locale = basename($root);
    $lang = preg_match('/^([a-z]{2})_[A-Z]{2}$/', $locale, $m) ? $m[1] : 'it';
    $today = date('Y-m-d');
    $creator = ['@type' => 'Person', '@id' => 'users/' . ($user['uid'] ?? ''), 'name' => (string)($user['name'] ?? '')];
    $g = [
        '@context' => ['https://schema.org', ['skos' => 'http://www.w3.org/2004/02/skos/core#', 'ws' => 'https://localbiz.it/ws#']],
        '@type' => 'DefinedTermSet',
        '@id' => '#glossario',
        'name' => $name,
        'inLanguage' => $lang,
        'version' => '0',
        'dateModified' => $today,
        'creator' => $creator,
    ];
    if ($author !== '') $g['author'] = ['@type' => 'Person', 'name' => $author];
    $g['hasPart'] = [];
    $g['hasDefinedTerm'] = [];
    if (file_put_contents($dir . '/' . GLOSSARY_FILE, glossary_encode($g), LOCK_EX) === false) throw new RuntimeException(glossary_t('Cannot write the glossary.'));
    $first = ['version' => '1', 'datePublished' => $today, 'dateModified' => $today] + $g;
    $proposal = $today . '-v1.jsonld';
    file_put_contents($dir . '/proposals/' . $proposal, glossary_encode($first), LOCK_EX);

    // The page, when the folder above is a page: same site, same language, same title suffix.
    $page = false;
    $above = is_file($parentAbs . '/index.json') ? json_decode((string)file_get_contents($parentAbs . '/index.json'), true) : null;
    if (is_array($above) && !empty($above['wspath'])) {
        $site = basename(dirname($base)) . '/' . $locale;
        $suffix = preg_match('/\s[–-]\s(.+)$/u', (string)($above['title'] ?? ''), $t) ? ' – ' . $t[1] : '';
        $theme = preg_match('/[?&]theme=([A-Za-z0-9_-]+)/', (string)($above['query'] ?? ''), $q) ? $q[1] : 'isotype';
        $index = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            '@id' => "$parent/$slug",
            'wspath' => rtrim($above['wspath'], '/') . '/' . $slug,
            'query' => "/?theme=$theme&template=glossary&content=$site/$parent/$slug",
            'parent' => ['wspath' => $above['wspath']],
            'inLanguage' => $above['inLanguage'] ?? str_replace('_', '-', $locale),
            'title' => $name . $suffix,
            'robots' => 'index, follow',
            'creativeWorkStatus' => 'Draft',
            'dateCreated' => $today . 'T00:00:00Z', 'datePublished' => $today . 'T00:00:00Z', 'dateModified' => $today . 'T00:00:00Z',
            'name' => $name,
            'mainEntity' => ['@type' => 'DefinedTermSet', '@id' => "$parent/$slug#glossario", 'name' => $name],
        ];
        $page = file_put_contents($dir . '/index.json', json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", LOCK_EX) !== false;
    }
    glossary_log($dir, $user, ['action' => 'create', 'proposal' => $proposal, 'page' => $page]);
    return ["$parent/$slug", $proposal, $page];
}

/**
 * A new version to work on: a proposal that is the glossary as it is, with
 * the next version number and today's date. Returns its file name.
 */
function glossary_new_proposal(string $dir, array $current, array $user): string
{
    $draft = $current;
    $draft['version'] = glossary_next_version(glossary_text($current['version'] ?? ''));
    $draft['dateModified'] = date('Y-m-d');
    if (!is_dir($dir . '/proposals') && !mkdir($dir . '/proposals', 0775, true)) throw new RuntimeException(glossary_t('Cannot create %s.', 'proposals/'));
    $base = date('Y-m-d') . '-v' . preg_replace('/[^A-Za-z0-9.-]/', '-', $draft['version']);
    $name = $base . '.jsonld';
    for ($i = 2; is_file("$dir/proposals/$name") || is_file("$dir/proposals/-$name"); $i++) $name = "$base-$i.jsonld";
    if (file_put_contents("$dir/proposals/$name", glossary_encode($draft), LOCK_EX) === false) throw new RuntimeException(glossary_t('Cannot save the proposal.'));
    glossary_log($dir, $user, ['action' => 'new', 'proposal' => $name, 'version' => $draft['version']]);
    return $name;
}

/**
 * Save the work on a proposal: what was accepted and edited becomes the
 * proposal; the previous state of the file goes to history/. Returns the
 * file's new hash, the base of the next save.
 */
function glossary_save_proposal(string $dir, string $name, array $draft, array $user): string
{
    $file = glossary_proposal_file($dir, $name);
    if (!$file) throw new RuntimeException(glossary_t('Unknown proposal.'));
    if (!is_dir($dir . '/history') && !mkdir($dir . '/history', 0775, true)) throw new RuntimeException(glossary_t('Cannot create %s.', 'history/'));
    $who = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($user['uid'] ?? 'unknown'));
    $backup = $dir . '/history/proposal-' . basename($name, '.jsonld') . '-' . date('Ymd-His') . "-$who.jsonld";
    if (!copy($file, $backup)) throw new RuntimeException(glossary_t('Cannot save the previous version.'));
    $tmp = $file . '.tmp';
    if (file_put_contents($tmp, glossary_encode($draft), LOCK_EX) === false || !rename($tmp, $file)) throw new RuntimeException(glossary_t('Cannot save the proposal.'));
    glossary_log($dir, $user, ['action' => 'save', 'proposal' => $name, 'previous' => basename($backup)]);
    return sha1_file($file);
}
