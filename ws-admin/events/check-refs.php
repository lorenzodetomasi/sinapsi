<?php
// CLI — verifica i riferimenti degli eventi che puntano a cartelle inesistenti
// (organizer/location/performer/superEvent/subEvent). Utile a scovare i REFUSI nell'@id
// che fanno "sparire" collezioni/eventi dagli indici. Sola lettura.
//   php ws-admin/events/check-refs.php

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Solo da riga di comando.\n"); }
require __DIR__ . '/../lib/ws-auth.php';
require __DIR__ . '/../lib/events-check.php';

$base = __DIR__ . '/../../ws-custom/contents/meetoo/it_IT';
$broken = event_check_refs($base);

if (!$broken) { echo "OK — nessun riferimento rotto.\n"; exit(0); }

echo count($broken) . " riferimenti rotti (puntano a una cartella senza index.json — spesso refusi nell'@id):\n";
foreach ($broken as $b) echo "  - {$b['from']}  [{$b['field']}] → {$b['ref']}\n";
exit(1);
