<?php
/*
 * Toglie i dati personali dai file serviti dal web (vedi lib/ws-private.php).
 * La logica sta in ws_privacy_migrate(): la stessa che usa la pagina di
 * amministrazione, così riga di comando e interfaccia non possono divergere.
 *
 *   php ws-admin/users/migrate-privacy.php            (dry-run)
 *   php ws-admin/users/migrate-privacy.php --apply    (scrive)
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Solo da riga di comando.\n"); }

require __DIR__ . '/../lib/ws-private.php';

$apply = in_array('--apply', $argv, true);
$rep = ws_privacy_migrate(__DIR__ . '/../../ws-custom/contents/meetoo/it_IT', $apply);

foreach ($rep['profiles'] as $p) echo "  users/{$p['uid']} → sposto: " . implode(', ', $p['fields']) . "\n";
foreach ($rep['rsvp'] as $r)     echo "  {$r['event']}/rsvp.json → tolgo nome/email da {$r['entries']} registrazioni\n";

$n = count($rep['profiles']) + count($rep['rsvp']);
echo "\n" . ($n
    ? ($apply ? 'APPLICATO' : 'DRY-RUN') . ": " . count($rep['profiles']) . " profili ({$rep['fields']} campi), "
      . count($rep['rsvp']) . " file di registrazioni ({$rep['entries']} voci).\n"
    : "Niente da spostare: nessun dato personale nei file pubblici.\n");
if (!$apply && $n) echo "Rilancia con --apply per scrivere.\n";
