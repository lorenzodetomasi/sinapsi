<?php
/*
 * Rigenera le liste che dichiarano una regola (`meetoo:listRule`).
 *
 * Uso:   php rebuild-lists.php            dice solo che cosa farebbe
 *        php rebuild-lists.php --apply    scrive
 *
 * È un involucro su `ws_listrule_sync()`: la stessa funzione che usano il
 * pannello di manutenzione e il salvataggio, così le tre strade non divergono.
 *
 * Non pota: le voci che la regola non trova più restano nella lista e vengono
 * segnalate. Il lavoro redazionale (posizioni, distanze, nomi scritti a mano,
 * tappe che esistono solo qui) non si perde a una rigenerazione.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Solo da riga di comando.\n"); }

require __DIR__ . '/../lib/ws-listrule.php';

$BASE = __DIR__ . '/../../ws-custom/contents/meetoo/it_IT';
$apply = in_array('--apply', $argv, true);

$r = ws_listrule_sync($BASE, $apply);

if (!$r['liste']) { echo "Nessuna lista con regola.\n"; exit; }
printf("%d list%s con regola%s\n\n", count($r['liste']), count($r['liste']) === 1 ? 'a' : 'e',
    $apply ? '' : ' — ANTEPRIMA, non scrivo');

foreach ($r['liste'] as $l) {
    printf("── %s\n", $l['id']);
    printf("   la regola trova %d · la lista avrà %d voci\n", $l['trovati'], $l['voci']);
    foreach ($l['aggiunte'] as $id)   echo "   + $id  (nuova, entrata per regola)\n";
    foreach ($l['orfane'] as $id)     echo "   ⚠ $id  (era entrata per regola, ora non la trova più: NON la tolgo)\n";
    foreach ($l['incomplete'] as $id) echo "   ⚠ $id  (manca il dato su cui si ordina)\n";
    if (!$l['diversa']) echo "   già in pari\n";
    elseif ($apply)     echo $l['scritta'] ? "   scritta.\n" : "   ⚠ scrittura fallita.\n";
}
