<?php
/**
 * Glossaries - the list of a site's glossaries and the review of a proposal.
 *
 *   ?site=isotype/it_IT                                  the glossaries of a root
 *   ?site=…&glossary=projects/glossaries/x&proposal=y    the review of proposal y
 *   POST {action: "apply", …}                            write the reviewed version
 *
 * A proposal is a whole version of the glossary (from an import, from the
 * editor to come). The review shows, entry by entry and field by field, what
 * it would change; each change is accepted or refused, and only what is
 * accepted is written. The version it replaces goes to history/.
 *
 * The first brick of the glossary editor: same place, same permissions, same
 * page style (the isotype forms).
 */

require_once __DIR__ . '/lib/glossary.php';

$sites = ws_admin_sites(ws_admin_contents_abspath());
$repo = dirname(__DIR__, 2);
// The site's URL root: where the repository sits under the web root ('' when it is the web root).
// Not from SCRIPT_NAME, which under the development router names the router.
$docRoot = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? '')) ?: $repo;
$siteBase = str_starts_with($repo, $docRoot) ? rtrim(substr($repo, strlen($docRoot)), '/') : '';
$url = static fn(string $abs) => $siteBase . '/' . ltrim(substr($abs, strlen($repo)), '/');

/* ---- Apply ------------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    ini_set('display_errors', '0');
    $fail = static function (int $code, string $message) {
        http_response_code($code);
        echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    };
    $in = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($in) || ($in['action'] ?? '') !== 'apply') $fail(400, glossary_t('Invalid request.'));
    $root = ws_admin_site_path((string)($in['site'] ?? ''));
    if (!$root) $fail(400, glossary_t('Invalid request.'));
    $user = glossary_user($root);
    if (!$user) $fail(401, glossary_t('Sign in to continue.'));
    if (empty($user['dev']) && !ws_gettone_valido((string)($in['csrf'] ?? ''))) $fail(403, glossary_t('Your session has expired: reload the page.'));
    $dir = glossary_dir($root, (string)($in['glossary'] ?? ''));
    if (!$dir) $fail(404, glossary_t('Unknown glossary.'));
    $file = $dir . '/' . GLOSSARY_FILE;
    $current = glossary_read($file);
    if (!$current || !glossary_can_review($current, $user)) $fail(403, glossary_t('You cannot review this glossary.'));
    // Somebody else applied in the meantime: this review was made against a version that is gone.
    if (!hash_equals(sha1_file($file), (string)($in['base'] ?? ''))) $fail(409, glossary_t('The glossary changed after this page was opened: reload it.'));
    $new = $in['result'] ?? null;
    if (!is_array($new)) $fail(400, glossary_t('Invalid request.'));
    if ($errors = glossary_errors($new)) $fail(422, implode(' ', $errors));
    $proposal = (string)($in['proposal'] ?? '');
    if ($proposal !== '' && !glossary_proposal_file($dir, $proposal)) $fail(404, glossary_t('Unknown proposal.'));
    try {
        $previous = glossary_apply($dir, $new, $user, $proposal ?: null, (array)($in['summary'] ?? []));
    } catch (Throwable $e) {
        $fail(500, $e->getMessage());
    }
    echo json_encode(['success' => true, 'previous' => 'history/' . $previous], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---- The page ---------------------------------------------------------------------- */
$siteId = (string)($_GET['site'] ?? '');
if (!isset($sites[$siteId])) {
    // No site asked: the first root that has a glossary, so the common case needs no choice.
    foreach ($sites as $id => $s) { if (glossary_find($s['path'])) { $siteId = $id; break; } }
    if (!isset($sites[$siteId])) $siteId = (string)array_key_first($sites);
}
$root = $sites[$siteId]['path'];
$user = glossary_user($root);
$csrf = $user && empty($user['dev']) ? ws_gettone_sessione() : '';

$mode = 'list';
$error = '';
$glossaryRel = (string)($_GET['glossary'] ?? '');
$proposalName = (string)($_GET['proposal'] ?? '');
if ($user && $glossaryRel !== '') {
    $dir = glossary_dir($root, $glossaryRel);
    $current = $dir ? glossary_read($dir . '/' . GLOSSARY_FILE) : null;
    $proposalFile = $dir ? glossary_proposal_file($dir, $proposalName) : null;
    $proposal = $proposalFile ? glossary_read($proposalFile) : null;
    if (!$current) $error = glossary_t('Unknown glossary.');
    elseif (!glossary_can_review($current, $user)) $error = glossary_t('You cannot review this glossary.');
    elseif (!$proposal) $error = glossary_t('Unknown proposal.');
    else $mode = 'review';
}

$h = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$json = static fn($v) => str_replace('<', '<', json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
$theme = $siteBase . '/ws-custom/themes';
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= $h($mode === 'review' ? glossary_t('Review of %s', $current['name'] ?? '') : glossary_t('Glossaries')) ?></title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Roboto+Slab:wght@400;600;700&display=swap">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap">
<link rel="stylesheet" href="<?= $h($theme) ?>/your-theme/css/_tokens.css">
<link rel="stylesheet" href="<?= $h($theme) ?>/your-theme/css/_forms.css">
<link rel="stylesheet" href="<?= $h($theme) ?>/isotype/glossary/glossary.css">
<link rel="stylesheet" href="<?= $h($theme) ?>/isotype/glossary/skins/isotype.css">
<link rel="stylesheet" href="review.css">
</head>
<body class="review-page">
<header class="review-top">
  <a class="review-home" href="?site=<?= $h(urlencode($siteId)) ?>"><span class="material-symbols-outlined">menu_book</span><?= $h(glossary_t('Glossaries')) ?></a>
  <?php if ($user): ?><span class="review-user"><?= $h($user['name'] ?: $user['email']) ?> · <?= $h($user['role']) ?></span><?php endif; ?>
</header>
<main class="review-main">
<?php if (!empty($user['dev'])): ?>
  <p class="review-notice"><?= $h(glossary_t("Local development: you are a super-admin because this is PHP's development server.")) ?></p>
<?php endif; ?>
<?php if (!$user): ?>
  <section class="review-gate">
    <p><?= $h(glossary_t('Sign in to continue.')) ?></p>
    <p><a class="button strong" href="<?= $h($siteBase) ?>/#signin"><?= $h(glossary_t('Sign in')) ?></a></p>
  </section>
<?php elseif ($error): ?>
  <p class="review-error" role="alert"><?= $h($error) ?></p>
  <p><a class="button" href="?site=<?= $h(urlencode($siteId)) ?>"><?= $h(glossary_t('Back to the glossaries')) ?></a></p>
<?php elseif ($mode === 'list'): ?>
  <h1><?= $h(glossary_t('Glossaries')) ?></h1>
  <form class="review-site" method="get">
    <label class="field horizontal"><strong><?= $h(glossary_t('Site')) ?></strong>
      <select name="site" onchange="this.form.submit()">
        <?php foreach ($sites as $id => $s): ?>
          <option value="<?= $h($id) ?>"<?= $id === $siteId ? ' selected' : '' ?>><?= $h($s['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </form>
  <?php $found = glossary_find($root); ?>
  <?php if (!$found): ?><p><?= $h(glossary_t('No glossary in this site yet.')) ?></p><?php endif; ?>
  <?php foreach ($found as $rel => $file):
      $g = glossary_read($file) ?? [];
      $mayReview = glossary_can_review($g, $user);
      $proposals = glossary_proposals(dirname($file)); ?>
    <section class="review-card">
      <h2><?= $h($g['name'] ?? $rel) ?></h2>
      <p class="review-meta"><?= $h(glossary_tn('%d entry', '%d entries', count((array)($g['hasDefinedTerm'] ?? [])))) ?> · <?= $h(glossary_t('Version %s', $g['version'] ?? '?')) ?> · <?= $h($g['dateModified'] ?? '') ?> · <code><?= $h($rel) ?></code></p>
      <p><a class="button" href="<?= $h($theme) ?>/isotype/glossary/viewer.html?src=<?= $h(urlencode($url($file))) ?>&amp;skin=isotype" target="_blank"><span class="material-symbols-outlined left">visibility</span><?= $h(glossary_t('View')) ?></a></p>
      <h3><?= $h(glossary_t('Proposals')) ?></h3>
      <?php if (!$proposals): ?><p class="review-meta"><?= $h(glossary_t('No proposals waiting.')) ?></p><?php endif; ?>
      <ul class="review-proposals">
        <?php foreach ($proposals as $name => $p): ?>
          <li><code><?= $h($name) ?></code>
            <?php if ($mayReview): ?>
              <a class="button strong" href="?site=<?= $h(urlencode($siteId)) ?>&amp;glossary=<?= $h(urlencode($rel)) ?>&amp;proposal=<?= $h(urlencode($name)) ?>"><?= $h(glossary_t('Review')) ?></a>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endforeach; ?>
<?php else: ?>
  <h1><?= $h(glossary_t('Review of %s', $current['name'] ?? '')) ?></h1>
  <p class="review-meta"><?= $h(glossary_t('Proposal: %s', $proposalName)) ?> · <code><?= $h($glossaryRel) ?></code></p>
  <div id="review" class="review"
       data-site="<?= $h($siteId) ?>" data-glossary="<?= $h($glossaryRel) ?>" data-proposal="<?= $h($proposalName) ?>"
       data-base="<?= $h(sha1_file($dir . '/' . GLOSSARY_FILE)) ?>" data-csrf="<?= $h($csrf) ?>"
       data-viewer="<?= $h($theme) ?>/isotype/glossary/viewer.html?src=<?= $h(urlencode($url($dir . '/' . GLOSSARY_FILE))) ?>&amp;skin=isotype"></div>
  <script type="application/json" id="review-current"><?= $json($current) ?></script>
  <script type="application/json" id="review-proposal"><?= $json($proposal) ?></script>
  <script>window.WSGlossaryL10n = <?= $json(glossary_catalogue() ?: new stdClass()) ?>; window.WSGlossaryManual = true;</script>
  <script src="<?= $h($theme) ?>/isotype/glossary/glossary.js"></script>
  <script src="review.js"></script>
<?php endif; ?>
</main>
</body>
</html>
