<?php
// CLI — (ri)costruisce events/_index da tutti gli eventi già presenti su disco
// (serie + occorrenze annidate). Utile come backfill iniziale o dopo modifiche manuali.
//   php ws-admin/events/rebuild-index.php
// Riusa event_index_rebuild() (stessa logica dell'endpoint web action=rebuild-index).

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Solo da riga di comando.\n"); }

require __DIR__ . '/../lib/ws-auth.php';       // per ws_ref_ids()
require __DIR__ . '/../lib/events-index.php';

$base = __DIR__ . '/../../ws-custom/contents/meetoo/it_IT';
if (!is_dir("$base/events")) { fwrite(STDERR, "Cartella eventi non trovata: $base/events\n"); exit(1); }

$r = event_index_rebuild($base);
echo "Indicizzati {$r['indexed']} eventi" . ($r['skipped'] ? " ({$r['skipped']} saltati)" : '') .
     " · {$r['series']} collection · {$r['organizers']} organizzatori → $base/events/_index/\n";
