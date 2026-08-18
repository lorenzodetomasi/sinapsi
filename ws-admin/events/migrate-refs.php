<?php
// CLI — normalizza i contenuti eventi verso le convenzioni (vedi lib/events-migrate.php):
//   @id = slug cartella; superEvent/subEvent = events/{slug}.
// Dry-run di default; scrive SOLO con --apply. Idempotente (tocca solo i file che cambiano).
//   php ws-admin/events/migrate-refs.php            # anteprima
//   php ws-admin/events/migrate-refs.php --apply    # applica

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Solo da riga di comando.\n"); }
require __DIR__ . '/../lib/events-migrate.php';

$apply = in_array('--apply', $argv, true);
$base  = __DIR__ . '/../../ws-custom/contents/meetoo/it_IT';

$r = event_migrate_refs($base, $apply);
foreach ($r['details'] as $rel => $lines) { echo "• $rel\n"; foreach ($lines as $l) echo "    $l\n"; }
echo "\n" . ($apply ? 'APPLICATO' : 'DRY-RUN') . ": {$r['changedFiles']} file, {$r['changes']} modifiche.\n";
if ($r['warns']) { echo "\nAvvisi (riferimenti a cartelle non ancora presenti):\n"; foreach ($r['warns'] as $w) echo "  - $w\n"; }
if (!$apply && $r['changedFiles']) echo "\nRilancia con --apply per scrivere. Poi: php ws-admin/events/rebuild-index.php\n";
