<?php
/*
 * Genera in blocco le copertine 1920×1080 (vedi lib/ws-media.php).
 * La logica sta in ws_media_covers(): la stessa della pagina di amministrazione.
 *
 *   php ws-admin/events/make-covers.php                    (dry-run)
 *   php ws-admin/events/make-covers.php --apply            (scrive)
 *   php ws-admin/events/make-covers.php --apply --adopt    (adotta le immagini orfane)
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Solo da riga di comando.\n"); }

require __DIR__ . '/../lib/ws-media.php';

$apply = in_array('--apply', $argv, true);
$adopt = in_array('--adopt', $argv, true);
$rep = ws_media_covers(__DIR__ . '/../../ws-custom/contents/meetoo/it_IT', $apply, $adopt);

foreach ($rep['done'] as $d)     echo "  {$d['event']} → " . ($d['exact'] ? 'già 1920×1080, aggiorno il riferimento' : "{$d['from']} → genero") . " {$d['cover']}\n";
foreach ($rep['skipped'] as $s)  echo "  {$s['event']} → {$s['why']} (usa --adopt per adottarla)\n";
foreach ($rep['external'] as $x) echo "  {$x['event']} → immagine esterna ({$x['url']}): lasciata com'è\n";
foreach ($rep['broken'] as $b)   echo "  {$b['event']} → ⚠ {$b['ref']}" . ($b['found'] ? " — nella cartella: {$b['found']}" : ' — nessuna immagine nella cartella') . "\n";

echo "\n" . ($apply ? 'APPLICATO' : 'DRY-RUN') . ': ' . count($rep['done']) . ' cover, '
   . count($rep['broken']) . " da sistemare a mano, " . count($rep['skipped']) . " in attesa di adozione.\n";
if (!$apply) echo "Rilancia con --apply (aggiungi --adopt per le immagini orfane). Poi: php ws-admin/events/rebuild-index.php\n";
