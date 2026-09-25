<?php
/**
 * PRIVATE SITES: a site that, on this server, answers only to some people.
 *
 * The development copy of Meetoo lives at isotype.org/meetoo, identical to
 * meetoo.it: it must not be seen by everyone, nor indexed by search engines as
 * a second copy of every page. Which site is private, and to whom it is open,
 * depends on the SERVER, not on the code - so it is declared in
 * ws-custom/ws-config.php, which each server keeps as its own, and the same
 * code can be uploaded to both:
 *
 *   define('WS_PRIVATE_SITES', array(
 *       'meetoo' => array('super-admin', 'someone@example.com'),
 *   ));
 *
 * An entry with an @ is an email address; any other entry is a role, as
 * users.xml assigns it. Without the constant every site is public: that is
 * meetoo.it.
 *
 * It lives in a file of its own, like mounts.php, because two readers need it:
 * the CMS, which closes the pages, and the admin, which leaves the private
 * site out of the public sitemap - and the admin does not start the CMS.
 */

if (!function_exists('ws_private_sites')) {
    /** [site folder => [roles and emails that are admitted]]. */
    function ws_private_sites(): array {
        if (!defined('WS_PRIVATE_SITES')) {
            /* The admin reads this without the CMS having loaded ws-config.php.
             * include_once: when the CMS did load it, this is a no-op. */
            $config = dirname(__DIR__) . '/ws-custom/ws-config.php';
            if (is_file($config)) { @include_once $config; }
        }
        return (defined('WS_PRIVATE_SITES') && is_array(WS_PRIVATE_SITES)) ? WS_PRIVATE_SITES : array();
    }

    function ws_site_is_private(string $site): bool {
        return $site !== '' && array_key_exists($site, ws_private_sites());
    }

    /**
     * Is this person admitted to this private site?
     *
     * $user is what ws_autentica_sessione() returns: null for nobody. Only a
     * verified email counts - it is the only one that says who you are.
     */
    function ws_private_site_admits(string $site, ?array $user): bool {
        if (!ws_site_is_private($site)) return true;
        if ($user === null || empty($user['email_verified'])) return false;
        $email = strtolower(trim((string)($user['email'] ?? '')));
        $role = (string)($user['role'] ?? '');
        foreach ((array)ws_private_sites()[$site] as $who) {
            $who = trim((string)$who);
            if ($who === '') continue;
            if (strpos($who, '@') !== false) {
                if ($email !== '' && strtolower($who) === $email) return true;
            } elseif ($role !== '' && $who === $role) {
                return true;
            }
        }
        return false;
    }

    /**
     * The gate. Called by the CMS after the plugins have loaded - the sign-in
     * plugin must be able to answer its own POST - and before the template.
     *
     * Every answer of a private site says `noindex`, the admitted ones too: a
     * search engine that got in some other way still must not keep them. Who
     * is not admitted gets a 403 and a page that lets them sign in, and not a
     * byte of the site: not the header, not the crumbs, not the title.
     */
    function ws_private_site_gate(string $site, string $usersXml = ''): void {
        if (!ws_site_is_private($site)) return;
        if (!headers_sent()) {
            header('X-Robots-Tag: noindex, nofollow', true);
            header('Cache-Control: private, no-store', true);
        }
        $user = null;
        $auth = dirname(__DIR__) . '/ws-admin/lib/ws-auth.php';
        if (is_file($auth)) {
            require_once $auth;
            $user = ws_autentica_sessione($usersXml !== '' ? $usersXml : null);
        }
        if (ws_private_site_admits($site, $user)) return;

        http_response_code(403);
        $client = '';
        if (defined('GOOGLE_API_OAUTH20_CLIENT')) {
            $conf = json_decode((string)GOOGLE_API_OAUTH20_CLIENT, true);
            $client = (string)($conf['web']['client_id'] ?? '');
        }
        require __DIR__ . '/private-site-gate.php';
        exit;
    }
}
