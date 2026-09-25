<?php
/**
 * The page a private site shows to who is not admitted (see private-sites.php).
 *
 * Standalone on purpose: no theme, no header, no crumbs. The theme would print
 * the site's name, its menu and the title of the page asked for, and that is
 * exactly what this page is here not to give away.
 *
 * In scope: $user (null or what ws_autentica_sessione returns), $client (the
 * Google client id, '' if sign-in is not configured).
 */
$t = function ($s) { return function_exists('__') ? __($s) : $s; };
$e = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
$signed = ($user !== null);
?><!DOCTYPE html>
<html lang="<?= $e(function_exists('ws_locale') ? str_replace('_', '-', ws_locale()) : 'it') ?>">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="robots" content="noindex, nofollow" />
<title><?= $e($t('Private site')) ?></title>
<style>
:root { color-scheme: light dark; --bg: #f5f5f4; --card: #fff; --text: #1c1917; --muted: #57534e; --line: #e7e5e4; }
@media (prefers-color-scheme: dark) { :root { --bg: #1c1917; --card: #292524; --text: #f5f5f4; --muted: #a8a29e; --line: #44403c; } }
* { box-sizing: border-box; }
body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 1rem; background: var(--bg); color: var(--text); font: 1rem/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
main { width: 100%; max-width: 26rem; background: var(--card); border: 1px solid var(--line); border-radius: 1rem; padding: 2rem 1.5rem; text-align: center; }
h1 { font-size: 1.25rem; margin: 0 0 .5rem; }
p { margin: 0 0 1rem; color: var(--muted); }
/* Google's button is drawn by Google, light: it sits on a light plate in the
   dark too, as in the sign-in window of the site. */
.plate { display: inline-block; background: #fff; border-radius: 999px; padding: .25rem; }
a { color: inherit; }
</style>
</head>
<body>
<main>
	<h1><?= $e($t('Private site')) ?></h1>
<?php if (!$signed) { ?>
	<p><?= $e($t('This copy of the site is open only to the people who work on it.')) ?></p>
<?php if ($client !== '') { ?>
	<p><?= $e($t('Sign in with Google to continue.')) ?></p>
	<div id="g_id_onload" data-client_id="<?= $e($client) ?>" data-context="signin" data-ux_mode="popup" data-callback="wsGateSignIn" data-auto_prompt="false"></div>
	<div class="plate"><div class="g_id_signin" data-type="standard" data-shape="pill" data-text="signin_with" data-size="large" data-theme="outline"></div></div>
	<script>
	/* The sign-in plugin answers a POST with the credential at any address:
	   the session opens, and the same page is asked again. */
	function wsGateSignIn(response) {
		var body = new URLSearchParams();
		body.append('credential', response.credential);
		fetch(location.href, { method: 'POST', body: body, credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (d) { if (d && d.success) { location.reload(); } })
			.catch(function () {});
	}
	</script>
	<script src="https://accounts.google.com/gsi/client" async defer></script>
<?php } ?>
<?php } else { ?>
	<p><?= $e(sprintf($t('You are signed in as %s, but this copy is not open to you.'), $user['email'] ?? '')) ?></p>
	<p><?= $e($t('Ask a super-admin to add your address.')) ?></p>
	<p><a href="?logout=1"><?= $e($t('Sign out')) ?></a></p>
<?php } ?>
</main>
</body>
</html>
