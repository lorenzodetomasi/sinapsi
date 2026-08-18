<?php
// CLI — (ri)costruisce events/_index da tutti gli eventi già presenti su disco.
// Utile come backfill iniziale o dopo modifiche manuali. Uso:
//   php ws-admin/events/rebuild-index.php
// Riusa la stessa logica del salvataggio (lib/events-index.php).

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Solo da riga di comando.\n"); }

require __DIR__ . '/../lib/ws-auth.php';       // per ws_ref_ids()
require __DIR__ . '/../lib/events-index.php';

$base      = __DIR__ . '/../../ws-custom/contents/meetoo/it_IT';
$eventsDir = "$base/events";
if (!is_dir($eventsDir)) { fwrite(STDERR, "Cartella eventi non trovata: $eventsDir\n"); exit(1); }

// Azzera l'indice esistente per una ricostruzione pulita (rimuove voci obsolete).
$idxDir = "$eventsDir/_index";
foreach (glob("$idxDir/by-organizer/*.json") ?: [] as $f) @unlink($f);
@unlink("$idxDir/events.json");

$files = glob("$eventsDir/*/index.json") ?: [];
$ok = 0; $skip = 0;
foreach ($files as $file) {
    $doc = json_decode((string)@file_get_contents($file), true);
    if (!is_array($doc)) { fwrite(STDERR, "  ! JSON illeggibile: $file\n"); $skip++; continue; }
    $rel = 'events/' . basename(dirname($file));
    event_index_update($base, $rel, $doc);
    echo "  + $rel  ({$doc['name']})\n";
    $ok++;
}

echo "\nIndicizzati $ok eventi" . ($skip ? " ($skip saltati)" : '') . " → $idxDir/events.json\n";
