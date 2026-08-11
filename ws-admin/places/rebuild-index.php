<?php
// CLI: ricostruisce _index/google-places.json scandendo tutti i JSON di Meetoo.
// Uso: php rebuild-index.php   (dalla cartella ws-admin/places)
// Sicuro da rilanciare: l'indice è derivato, non una fonte di verità primaria.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Solo da riga di comando.\n");
}

require __DIR__ . '/index-lib.php';

list($idx, $conflicts) = ws_index_rebuild();

$path = ws_index_path();
$dir = dirname($path);
if (!is_dir($dir)) mkdir($dir, 0775, true);
file_put_contents($path, json_encode($idx, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

echo "Indice ricostruito: " . count($idx) . " voci → $path\n";
if ($conflicts) {
    echo "\n⚠ Conflitti (stesso google_place_id su @id diversi — possibili duplicati):\n";
    foreach ($conflicts as $gid => $ids) {
        echo "  $gid → " . implode(', ', array_values(array_unique($ids))) . "\n";
    }
} else {
    echo "Nessun conflitto.\n";
}
