<?php
/*
 * The Gestione's chrome as JSON, for the pages the server does not write: the
 * editors, built by Vite into static files. header.js asks here for what the
 * PHP pages get in their markup (lib/ws-admin-chrome.php): where the site's
 * home is, its name and logo, its look, the menu and the footer.
 *
 *   GET ws-admin/chrome.php?feature=events     the site that manages events
 *   GET ws-admin/chrome.php?site=isotype/it_IT a site by name
 *
 * Nothing personal in the answer: it is the same for everyone.
 */
require_once __DIR__ . '/lib/ws-admin-chrome.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, max-age=300');
ini_set('display_errors', '0');

$feature = preg_replace('/[^a-z0-9_-]/', '', (string)($_GET['feature'] ?? ''));
$site = ws_admin_chrome_site($feature);
$styles = ws_admin_chrome_styles($site);

echo json_encode([
    'id' => $site['id'],
    'name' => $site['name'],
    'home' => $site['home'],
    'logo' => $site['logo'],
    'css' => array_merge($styles['fonts'], $styles['css'], [$site['admin'] . 'chrome.css']),
    'nav' => ws_admin_chrome_nav($site),
    'brand' => ws_admin_chrome_brand($site),
    'footer' => ws_admin_chrome_footer($site),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
