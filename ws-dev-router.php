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

/* Una CARTELLA con dentro un indice — `/ws-admin/events/edit/` — in produzione la
 * serve nginx da sé, e in locale non la serviva nessuno: il pannello e l'editor
 * rispondevano con la home di isotype, che è quello che fa il CMS quando non
 * riconosce un indirizzo. Sembrava un problema di instradamento del CMS, ed era
 * solo il server di sviluppo che non sa cos'è un indice di cartella. */
if ($percorso !== '/' && is_dir($file)) {
    if (substr($percorso, -1) !== '/') {
        header('Location: ' . $percorso . '/', true, 301);
        return true;
    }
    foreach (['index.php', 'index.html'] as $indice) {
        if (!is_file($file . $indice)) continue;
        if (substr($indice, -4) === '.php') { require $file . $indice; return true; }
        header('Content-Type: text/html; charset=UTF-8');
        readfile($file . $indice);
        return true;
    }
}

require __DIR__ . '/index.php';
