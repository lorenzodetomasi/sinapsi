<?php
// CLI — normalizzazione strutturale dei contenuti eventi (vedi lib/events-normalize.php):
//   rimuove serie annidate mal posizionate, completa le occorrenze mancanti, rideriva subEvent.
// Dry-run di default; scrive SOLO con --apply. Ripetibile e idempotente.
//   php ws-admin/events/normalize-content.php            # anteprima
//   php ws-admin/events/normalize-content.php --apply     # applica (poi: rebuild-index)

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Solo da riga di comando.\n"); }
require __DIR__ . '/../lib/events-normalize.php';
require __DIR__ . '/../lib/ws-auth.php';
require __DIR__ . '/../lib/events-check.php';

$apply = in_array('--apply', $argv, true);
$base  = __DIR__ . '/../../ws-custom/contents/meetoo/it_IT';

$r = event_normalize($base, $apply);

echo "Serie annidate rimosse: " . count($r['removedSeries']) . "\n";
foreach ($r['removedSeries'] as $s) echo "  - $s\n";
echo "Occorrenze completate: " . count($r['completedOccurrences']) . "\n";
foreach ($r['completedOccurrences'] as $o) echo "  + {$o['path']}  ({$o['source']})\n";
echo "superEvent riparati: " . count($r['repairedSuperEvent']) . "\n";
foreach ($r['repairedSuperEvent'] as $p) echo "  ~ $p\n";
echo "subEvent di serie riderivati: " . count($r['seriesSubEventUpdated']) . "\n";
foreach ($r['seriesSubEventUpdated'] as $u) echo "  ~ {$u['series']}  ({$u['count']} occorrenze)\n";
if ($r['warns']) { echo "Avvisi:\n"; foreach ($r['warns'] as $w) echo "  ! $w\n"; }

$broken = event_check_refs($base);
if ($broken) {
    echo "\n⚠ " . count($broken) . " riferimenti rotti (cartelle inesistenti — spesso refusi nell'@id):\n";
    foreach ($broken as $b) echo "  - {$b['from']}  [{$b['field']}] → {$b['ref']}\n";
}

echo "\n" . ($apply ? 'APPLICATO.' : 'DRY-RUN (nessuna scrittura).') . "\n";
if ($apply) echo "Ora ricostruisci l'indice: php ws-admin/events/rebuild-index.php\n";
elseif ($r['removedSeries'] || $r['completedOccurrences'] || $r['seriesSubEventUpdated']) echo "Rilancia con --apply per scrivere.\n";
