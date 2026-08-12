<?php
// CLI: arricchisce con geo/indirizzo (Google Places, textsearch per nome) gli item
// di un file JSON che ne sono privi. Da eseguire SUL SERVER (la chiave in config.php
// è ristretta all'IP del server). Fa un BACKUP prima di scrivere.
//
// Uso:  php enrich-place.php <percorso/al/file.json> [--dry] [--max=N]
//   --dry : non scrive, stampa solo cosa farebbe
//   --max : tetto alle chiamate Google (default 200; sono billable)
//
// Gestisce sia le liste (itemListElement→item, anche annidato in mainEntity) sia i
// singoli place (mainEntity = l'item). Arricchisce SOLO i campi mancanti, non
// sovrascrive nulla di esistente.

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Solo da riga di comando.\n"); }

$config = @include __DIR__ . '/config.php';
if (!is_array($config) || empty($config['places_api_key'])) {
    fwrite(STDERR, "config.php mancante o senza places_api_key.\n"); exit(1);
}
$apiKey = $config['places_api_key'];
require __DIR__ . '/places-google-lib.php';

// --- argomenti ---
$file = null; $dry = false; $max = 200;
foreach (array_slice($argv, 1) as $a) {
    if ($a === '--dry') $dry = true;
    elseif (strpos($a, '--max=') === 0) $max = max(1, (int)substr($a, 6));
    elseif ($file === null) $file = $a;
}
if ($file === null) { fwrite(STDERR, "Uso: php enrich-place.php <file.json> [--dry] [--max=N]\n"); exit(1); }
if (!is_file($file)) { fwrite(STDERR, "File non trovato: $file\n"); exit(1); }

$j = json_decode((string)file_get_contents($file), true);
if (!is_array($j)) { fwrite(STDERR, "JSON non valido: $file\n"); exit(1); }

// Raccoglie i riferimenti agli item da arricchire (in una lista o come singolo place).
$root = (isset($j['mainEntity']) && is_array($j['mainEntity'])) ? $j['mainEntity'] : $j;
$items = [];
if (isset($root['itemListElement']) && is_array($root['itemListElement'])) {
    foreach ($root['itemListElement'] as $ki => $_) {
        if (isset($root['itemListElement'][$ki]['item']) && is_array($root['itemListElement'][$ki]['item'])) {
            $items[] = &$root['itemListElement'][$ki]['item'];
        }
    }
} else {
    $items[] = &$root; // singolo place
}

$tried = 0; $enriched = 0; $skipped = 0; $capped = false;
foreach ($items as &$it) {
    if (isset($it['geo']) && isset($it['address'])) { $skipped++; continue; }
    if ($tried >= $max) { $capped = true; break; }
    $tried++;
    $name = $it['name'] ?? '';
    $ok = enrichPlaceWithGoogleAPI($name, $it, $apiKey);
    if ($ok) $enriched++;
    printf("  %-38s %s\n", mb_substr((string)$name, 0, 38), $ok ? 'OK' : '— nessun match');
}
unset($it);

// Se abbiamo lavorato su un singolo place, riscrivi la radice giusta.
if (isset($j['mainEntity']) && is_array($j['mainEntity'])) $j['mainEntity'] = $root; else $j = $root;

echo "\nProvati: $tried | arricchiti: $enriched | già completi: $skipped" . ($capped ? " | TETTO $max raggiunto" : "") . "\n";

if ($dry) { echo "(dry-run: nessuna scrittura)\n"; exit(0); }
if ($enriched === 0) { echo "Nessuna modifica: file non riscritto.\n"; exit(0); }

$backup = $file . '.bak-' . date('YmdHis');
copy($file, $backup);
file_put_contents($file, json_encode($j, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
echo "Scritto $file (backup: $backup). Rivedi i risultati prima di usarli.\n";
