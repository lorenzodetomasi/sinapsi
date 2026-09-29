<?php
/*
 * THE GESTIONE'S HEADER AND FOOTER, dressed as the site being managed
 * (decided on 29 Sep 2026).
 *
 * The Gestione runs outside the CMS, and it used to draw a header of its own
 * with Meetoo's logo and addresses written by hand - `/meetoo/…`, which on
 * meetoo.it, where Meetoo answers at the root, led nowhere. Now each page asks
 * this file for the site it is working on, and gets its logo, its colours and
 * fonts, the address of its home on THIS server (with or without a mount), a
 * menu of the Gestione's tools with the way back to the site, and the same
 * footer as the site: who runs it, the legal menu, the credits.
 *
 * WHICH SITE:
 *   - a tool that belongs to one capability (events: Meetoo's) works on the
 *     site that has it;
 *   - otherwise the one chosen with ?site= (remembered in a cookie), and the
 *     hub's site selector sets it;
 *   - otherwise the site at the root of this server: isotype on isotype.org,
 *     Meetoo on meetoo.it.
 *
 *   $site = ws_admin_chrome_site('events');
 *   <head> … <?php echo ws_admin_chrome_head($site); ?> </head>
 *   <body> <?php echo ws_admin_chrome_header($site); ?> … page …
 *          <?php echo ws_admin_chrome_footer($site); ?> </body>
 *
 * The header is the markup header.js draws for the Gestione, so header.js
 * adopts it instead of drawing its own (login, settings, drawer stay its own).
 * The pages the server does not write - the editors, built by Vite - ask for
 * the same things as JSON: ws-admin/chrome.php.
 */
require_once __DIR__ . '/ws-sites.php';

if (!function_exists('ws_admin_chrome_site')) {

    function ws_admin_chrome_esc($s): string {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }

    /** Prefix => site: the sites mounted under an address of this server. */
    function ws_admin_chrome_mounts(): array {
        if (!defined('WS_MOUNTS')) {
            $config = dirname(__DIR__, 2) . '/ws-custom/ws-config.php';
            if (is_file($config)) { @include_once $config; }
            @include_once dirname(__DIR__, 2) . '/ws-core/mounts.php';
        }
        return defined('WS_MOUNTS') && is_array(WS_MOUNTS) ? WS_MOUNTS : [];
    }

    /** https://host of this request, without a slash at the end. */
    function ws_admin_chrome_root_url(): string {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
        $host = preg_replace('/[^a-z0-9.:-]/i', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
        return ($https ? 'https' : 'http') . '://' . $host;
    }

    /** The sites a person can choose: roots with their headings, archives out. */
    function ws_admin_chrome_sites(): array {
        $out = [];
        foreach (ws_admin_sites(ws_admin_contents_abspath()) as $id => $s) {
            if (strpos($id, 'archive-') === 0) continue;
            $dir = $s['path'];
            if (!is_file("$dir/ws_headings.wsx") && !is_file("$dir/ws_headings.xml")) continue;
            $out[$id] = $s;
        }
        return $out;
    }

    /** The site at the root of this server: one that is not mounted under a prefix. */
    function ws_admin_chrome_default(array $sites): string {
        $montati = array_values(ws_admin_chrome_mounts());
        $scelta = '';
        foreach ($sites as $id => $s) {
            list($nome, $lingua) = array_pad(explode('/', $id), 2, '');
            if (in_array($nome, $montati, true)) continue;
            if ($scelta === '' || $lingua === 'it_IT') $scelta = $id;
            if ($lingua === 'it_IT') break;
        }
        return $scelta !== '' ? $scelta : (string)array_key_first($sites);
    }

    /** The resolved headings of a site (its ws_headings, XIncludes followed). */
    function ws_admin_chrome_headings(string $dir): ?DOMXPath {
        foreach (['ws_headings.wsx', 'ws_headings.xml'] as $f) {
            if (!is_file("$dir/$f")) continue;
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $ok = $dom->load("$dir/$f", LIBXML_NONET);
            if ($ok) @$dom->xinclude(LIBXML_NONET);
            libxml_clear_errors();
            if ($ok) return new DOMXPath($dom);
        }
        return null;
    }

    function ws_admin_chrome_text(?DOMXPath $x, string $path): string {
        return $x ? trim((string)$x->evaluate("string($path)")) : '';
    }

    /**
     * Everything the chrome needs about the site being managed.
     *
     * @param string $feature a capability the page belongs to ('events'): then
     *                        the site is the one that has it.
     */
    function ws_admin_chrome_site(string $feature = ''): array {
        static $fatti = [];
        if (isset($fatti[$feature])) return $fatti[$feature];
        $sites = ws_admin_chrome_sites();
        $id = '';
        $ricorda = false;
        if ($feature !== '') {
            $r = ws_admin_request_site(null, $feature);
            $id = $r['id'] ?? '';
        }
        if ($id === '') {
            $chiesto = trim((string)($_GET['site'] ?? ''));
            if ($chiesto !== '' && isset($sites[$chiesto])) {
                $id = $chiesto;
                $ricorda = true;   // remembered for the other pages (see ws_admin_chrome_head)
            }
        }
        if ($id === '') {
            $ricordato = (string)($_COOKIE['ws_admin_site'] ?? '');
            if ($ricordato !== '' && isset($sites[$ricordato])) $id = $ricordato;
        }
        if ($id === '' || !isset($sites[$id])) $id = ws_admin_chrome_default($sites);
        $dir = $sites[$id]['path'] ?? '';
        list($nome, $lingua) = array_pad(explode('/', $id), 2, '');

        $root = ws_admin_chrome_root_url();
        $prefisso = '';
        foreach (ws_admin_chrome_mounts() as $p => $s) {
            if ($s === $nome) { $prefisso = '/' . trim((string)$p, '/'); break; }
        }
        $x = $dir !== '' ? ws_admin_chrome_headings($dir) : null;
        $tema = ws_admin_chrome_text($x, '/*/ws_theme_id') ?: $nome;
        $marchio = function (string $xmlId) use ($x, $root): string {
            $rel = ws_admin_chrome_text($x, "//*[@xml:id='$xmlId']/source/relpath");
            return $rel !== '' ? "$root/ws-custom/contents/" . ltrim($rel, '/') : '';
        };
        $logotype = $marchio('logotype');
        $logo = $logotype !== '' ? $logotype : $marchio('logo');

        return $fatti[$feature] = [
            'id' => $id,
            'site' => $nome,
            'locale' => $lingua,
            'dir' => $dir,
            'theme' => $tema,
            'root' => $root,
            'home' => $root . $prefisso . '/',
            'admin' => $root . '/ws-admin/',
            'name' => ws_admin_chrome_text($x, '/*/mainEntity/name') ?: ucfirst($nome),
            'headline' => ws_admin_chrome_text($x, '/*/mainEntity/headline'),
            'logo' => $logo,
            'logo_dark' => $logotype !== '' ? $marchio('logotype-neg') : $marchio('logo-neg'),
            // A logotype already says the name; a logo stands beside it.
            'logotype' => $logotype !== '',
            'features' => site_features($nome),
            'remember' => $ricorda,
            'x' => $x,
        ];
    }

    /**
     * The site's look: its colours and fonts over the Gestione's own sheet.
     * Meetoo's are the Gestione's already (meetoo.css); a site on the parent
     * theme brings its tokens and its fonts.
     */
    function ws_admin_chrome_styles(array $site): array {
        $root = $site['root'];
        if ($site['theme'] === 'meetoo') {
            return ['css' => [], 'fonts' => []];
        }
        return [
            'css' => ["$root/ws-custom/themes/your-theme/css/_tokens.css"],
            'fonts' => ['https://fonts.googleapis.com/css2?family=Titillium+Web:ital,wght@0,300;0,400;0,600;0,700;1,400&family=Raleway:wght@400;600;700&display=swap'],
        ];
    }

    /** What goes in <head>: where the site is, and its look. */
    function ws_admin_chrome_head(array $site): string {
        $e = 'ws_admin_chrome_esc';
        $st = ws_admin_chrome_styles($site);
        $out = "\n  <!-- The site being managed (ws-admin/lib/ws-admin-chrome.php) -->\n"
            . '  <meta name="meetoo:site-root" content="' . $e($site['root'] . '/') . "\">\n"
            . '  <meta name="ws:site-home" content="' . $e($site['home']) . "\">\n"
            . '  <meta name="ws:site-id" content="' . $e($site['id']) . "\">\n"
            . '  <meta name="ws:site-name" content="' . $e($site['name']) . "\">\n"
            . ($site['logo'] !== '' ? '  <meta name="ws:site-logo" content="' . $e($site['logo']) . "\">\n" : '');
        foreach ($st['fonts'] as $u) $out .= '  <link rel="stylesheet" href="' . $e($u) . "\">\n";
        foreach ($st['css'] as $u) $out .= '  <link rel="stylesheet" href="' . $e($u) . "\">\n";
        $out .= '  <link rel="stylesheet" href="' . $e($site['admin'] . 'chrome.css') . "\">\n";
        /* A site chosen with ?site= is remembered for the other pages of the
         * Gestione. By the browser: the page has begun before this is known,
         * and a header can no longer be sent. It is a preference, not a secret. */
        if (!empty($site['remember'])) {
            $out .= "  <script>document.cookie = 'ws_admin_site=' + encodeURIComponent(" . json_encode($site['id'])
                . ") + '; path=/ws-admin/; max-age=31536000; SameSite=Lax';</script>\n";
        }
        return $out;
    }

    /** The Gestione's tools, and the way back to the site. */
    function ws_admin_chrome_nav(array $site): array {
        $a = $site['admin'];
        $voci = [['label' => 'Vai al sito', 'icon' => 'public', 'href' => $site['home']],
                 // data 'admin': the item header.js adds for the roles that may see it, and removes for the others.
                 ['label' => 'Gestione', 'icon' => 'admin_panel_settings', 'href' => $a . 'index.php', 'data' => 'admin']];
        if (in_array('events', $site['features'], true)) {
            $voci[] = ['label' => 'Gestione eventi', 'icon' => 'event_note', 'href' => $a . 'events/index.php'];
            $voci[] = ['label' => 'Nuovo evento', 'icon' => 'note_add', 'href' => $a . 'events/add/'];
            $voci[] = ['label' => 'Luoghi e gruppi', 'icon' => 'place', 'href' => $a . 'places/edit/'];
        }
        $voci[] = ['label' => 'Pagine', 'icon' => 'description', 'href' => $a . 'pages/?site=' . rawurlencode($site['id'])];
        $voci[] = ['label' => 'Utenti e ruoli', 'icon' => 'manage_accounts', 'href' => $a . 'users/'];
        $voci[] = ['label' => 'Siti', 'icon' => 'language', 'href' => $a . 'sites.php'];
        return $voci;
    }

    function ws_admin_chrome_brand(array $site, string $class = 'mt-brand'): string {
        $e = 'ws_admin_chrome_esc';
        $segno = '';
        if ($site['logo'] !== '') {
            $segno = $site['logo_dark'] !== ''
                ? '<picture><source srcset="' . $e($site['logo_dark']) . '" media="(prefers-color-scheme: dark)"><img class="mt-logo" src="' . $e($site['logo']) . '" alt="' . ($site['logotype'] ? $e($site['name']) : '') . '"></picture>'
                : '<img class="mt-logo" src="' . $e($site['logo']) . '" alt="' . ($site['logotype'] ? $e($site['name']) : '') . '">';
        }
        $nome = (!$site['logotype'] || $segno === '')
            ? '<span class="ws-admin-brand-name">' . $e($site['name'])
                . ($site['headline'] !== '' ? '<small>' . $e($site['headline']) . '</small>' : '') . '</span>'
            : '';
        return '<a class="' . $class . ' ws-admin-brand" href="' . $e($site['home']) . '" title="' . $e('Vai al sito: ' . $site['name']) . '">' . $segno . $nome . '</a>';
    }

    /** The header, the markup header.js adopts, and its drawer. */
    function ws_admin_chrome_header(array $site): string {
        $e = 'ws_admin_chrome_esc';
        $voci = '';
        foreach (ws_admin_chrome_nav($site) as $v) {
            $voci .= '<a href="' . $e($v['href']) . '"' . (!empty($v['data']) ? ' data-nav="' . $e($v['data']) . '"' : '')
                . '><span class="material-symbols-outlined">' . $e($v['icon']) . '</span>' . $e($v['label']) . '</a>';
        }
        return '<header class="mt-header ws-admin-header" data-site="' . $e($site['site']) . '">'
            . '<div class="mt-row mt-row-1">'
            . '<div class="mt-left"><button class="mt-icon-btn" id="mt-menu" title="Menu" aria-label="Menu"><span class="material-symbols-outlined">menu</span></button>'
            . ws_admin_chrome_brand($site)
            . '<span class="ws-admin-label">Gestione</span></div>'
            . '<div class="mt-actions"><span id="mt-slot"></span>'
            . '<button class="mt-icon-btn" id="mt-settings" title="Impostazioni" aria-label="Impostazioni"><span class="material-symbols-outlined">settings</span></button>'
            . '<span id="mt-account"></span></div>'
            . '</div>'
            . '<div class="mt-row mt-row-2"><div class="mt-crumbs" id="mt-crumbs"></div><div class="mt-admin" id="mt-admin"></div></div>'
            . '</header>'
            . '<div class="mt-drawer-ov"></div>'
            . '<nav class="mt-drawer" aria-label="Menu della Gestione"><div class="mt-drawer-head">' . ws_admin_chrome_brand($site)
            . '<button class="mt-icon-btn" id="mt-drawer-close" aria-label="Chiudi"><span class="material-symbols-outlined">close</span></button></div>'
            . '<div class="mt-nav" id="mt-nav">' . $voci . '</div></nav>';
    }

    /** A content's address on the site, from the site's map: '' if it has none. */
    function ws_admin_chrome_address(array $site, string $rel): string {
        static $mappe = [];
        $contents = ws_admin_contents_abspath();
        if (!isset($mappe[$site['site']])) {
            $mappe[$site['site']] = [];
            foreach (["$contents/{$site['site']}/ws_sitemap.wsx", "{$site['dir']}/ws_sitemap.wsx"] as $f) {
                if (!is_file($f)) continue;
                $xml = @simplexml_load_file($f);
                if (!$xml) continue;
                foreach ($xml->url as $u) {
                    if (preg_match('/[?&]content=([^&]*)/', html_entity_decode((string)$u->query), $m)) {
                        $pezzi = explode('/', trim(urldecode($m[1]), '/'), 3);
                        if (count($pezzi) === 3 && !isset($mappe[$site['site']][$pezzi[2]])) {
                            $mappe[$site['site']][$pezzi[2]] = (string)$u->wspath;
                        }
                    }
                }
            }
        }
        $wspath = $mappe[$site['site']][trim($rel, '/')] ?? '';
        return $wspath !== '' ? rtrim($site['home'], '/') . '/' . ltrim($wspath, '/') : '';
    }

    /** The legal menu of the site: [label, href], from its nav-legal. */
    function ws_admin_chrome_legal(array $site): array {
        $dir = $site['dir'];
        $dom = null;
        foreach (['nav-legal.wsx', 'nav-legal.xml'] as $f) {
            if (!is_file("$dir/$f")) continue;
            $d = new DOMDocument();
            libxml_use_internal_errors(true);
            if ($d->load("$dir/$f", LIBXML_NONET)) { @$d->xinclude(LIBXML_NONET); $dom = $d; }
            libxml_clear_errors();
            if ($dom) break;
        }
        if (!$dom) return [];
        $out = [];
        foreach ((new DOMXPath($dom))->query('//item') as $item) {
            $x = new DOMXPath($dom);
            $nome = trim($x->evaluate('string(name)', $item));
            $url = trim($x->evaluate('string(url)', $item));
            $contenuto = trim($x->evaluate('string(content)', $item));
            $wspath = trim($x->evaluate('string(wspath)', $item));
            $href = preg_match('#^https?://#i', $url) ? $url
                : ($contenuto !== '' ? ws_admin_chrome_address($site, $contenuto)
                : ($wspath !== '' ? rtrim($site['home'], '/') . '/' . ltrim($wspath, '/') : ''));
            if ($nome !== '' && $href !== '') $out[] = [$nome, $href];
        }
        return $out;
    }

    /** The site's footer: the same three parts as the site's own. */
    function ws_admin_chrome_footer(array $site): string {
        $e = 'ws_admin_chrome_esc';
        $x = $site['x'];
        $t = function (string $p) use ($x) { return ws_admin_chrome_text($x, "/*/mainEntity/$p"); };
        $parti = '';

        $nome = $t('legalName');
        $indirizzo = implode(', ', array_filter([
            $t('address/streetAddress'), $t('address/district'),
            trim($t('address/postalCode') . ' ' . $t('address/addressLocality')
                . ($t('address/addressRegion') !== '' ? ' (' . $t('address/addressRegion') . ')' : '')),
            $t('address/administrativeArea'), $t('address/addressCountry'),
        ]));
        $iva = $t('vatID');
        $cf = $t('taxID');
        if ($nome !== '' || $indirizzo !== '' || $iva !== '' || $cf !== '') {
            $parti .= '<section id="fiscal-data" class="fiscal-data"><h3>Dati fiscali</h3>'
                . '<p>' . ($nome !== '' ? '<span class="legal-name">' . $e($nome) . '</span><br>' : '') . $e($indirizzo) . '</p>'
                . (($iva !== '' || $cf !== '') ? '<p>' . ($iva !== '' ? 'Partita IVA: <span class="vat-id">' . $e($iva) . '</span><br>' : '')
                    . ($cf !== '' ? 'Codice fiscale: <span class="tax-id">' . $e($cf) . '</span>' : '') . '</p>' : '')
                . '</section>';
        }

        $legali = ws_admin_chrome_legal($site);
        if ($legali) {
            $parti .= '<nav id="nav-legal" class="nav-legal nav vertical"><h3>Informazioni legali</h3><div><ul>';
            foreach ($legali as $l) $parti .= '<li><a href="' . $e($l[1]) . '">' . $e($l[0]) . '</a></li>';
            $parti .= '</ul></div></nav>';
        }

        $anno = $t('copyrightYear');
        $oggi = date('Y');
        $diritti = ($anno !== '' && (int)$oggi > (int)$anno) ? "$anno - $oggi" : ($anno !== '' ? $anno : $oggi);
        $designer = '';
        if ($x) {
            $n = $x->query('/*/mainEntity/designer')->item(0);
            if ($n) { foreach ($n->childNodes as $c) $designer .= $n->ownerDocument->saveXML($c); }
        }
        $parti .= '<section id="credits" class="credits"><h3>Crediti</h3><p>'
            . '<abbr title="Copyright">©</abbr> ' . $e($diritti) . ($nome !== '' ? ' ' . $e($nome) : '') . '<br>'
            . 'Design by ' . (trim($designer) !== '' ? $designer : '<a href="https://www.localbiz.it">Localbiz.it</a>')
            . '</p></section>';

        return '<footer id="footer" class="ws-admin-footer"><div class="mt-footer" id="footer-content">' . $parti . '</div></footer>';
    }
}
