<?php
/**
 * Router per il server di sviluppo di PHP (`php -S`), che non legge `.htaccess`.
 *
 * Rifà la stessa regola della produzione: se l'indirizzo corrisponde a un file
 * vero lo si serve com'è, altrimenti risponde il CMS. Senza questo, in locale
 * funzionano solo le pagine che per caso hanno un file con quel nome — cioè
 * nessuna, perché nel CMS gli indirizzi non sono file.
 */
$percorso = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$file = __DIR__ . $percorso;
if ($percorso !== '/' && is_file($file)) {
    return false; // lo serve il server, con il suo mime type
}
require __DIR__ . '/index.php';
