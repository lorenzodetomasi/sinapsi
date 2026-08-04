<?php
// Verifica se un @id di place/localbusiness esiste già in ws-custom.
// Usato da events/edit (client) e da place-add.php. Risponde { ok, exists }.
// GET ?id=localbusinesses/IT00124/slug   (oppure places/IT00121/slug)
header('Content-Type: application/json');
// Stessa origine (www.isotype.org) in produzione: nessun CORS necessario.

$id = trim($_GET['id'] ?? '');

// Sanitizzazione severa: SOLO il formato atteso, niente "../" o path arbitrari.
if (!preg_match('#^(places|localbusinesses)/[A-Za-z0-9._-]+/[A-Za-z0-9._-]+$#', $id)) {
    echo json_encode(['ok' => false, 'error' => 'id non valido']);
    exit;
}

// Locale fisso per ora (come il resto del CMS).
$base = __DIR__ . '/../../ws-custom/contents/meetoo/it_IT/';
$dir = $base . $id;

$exists = is_dir($dir) || is_file($dir . '/index.json') || is_file($dir . '/index.xml');

echo json_encode(['ok' => true, 'id' => $id, 'exists' => $exists]);
