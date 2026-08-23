<?php
/*
 * Rigenera le liste che dichiarano una regola (`meetoo:listRule`).
 *
 * Uso:   php rebuild-lists.php            dice solo che cosa farebbe
 *        php rebuild-lists.php --apply    scrive
 *
 * Non pota: le voci che la regola non trova più restano nella lista e vengono
 * segnalate. Il lavoro redazionale (posizioni, distanze, nomi scritti a mano,
 * tappe che esistono solo qui) non si perde a una rigenerazione.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Solo da riga di comando.\n"); }

require __DIR__ . '/../lib/ws-listrule.php';

$BASE = __DIR__ . '/../../ws-custom/contents/meetoo/it_IT';
$apply = in_array('--apply', $argv, true);

// Ogni lista con una regola, ovunque stia.
$liste = [];
foreach (['places/*/*', 'places/*', 'events/*', 'organizations/*'] as $g) {
    foreach (glob("$BASE/$g/index.json") as $f) {
        $j = json_decode((string)@file_get_contents($f), true);
        if (!is_array($j)) continue;
        $e = $j['mainEntity'] ?? $j;
        if (!empty($e['meetoo:listRule'])) $liste[$f] = [$j, $e];
    }
}

if (!$liste) { echo "Nessuna lista con regola.\n"; exit; }
printf("%d list%s con regola%s\n\n", count($liste), count($liste) === 1 ? 'a' : 'e', $apply ? '' : ' — ANTEPRIMA, non scrivo');

foreach ($liste as $f => [$doc, $ent]) {
    $regola = $ent['meetoo:listRule'];
    $trovati = ws_listrule_collect($BASE, $regola);
    $r = ws_listrule_merge($regola, $ent['itemListElement'] ?? [], $trovati);

    printf("── %s\n", $ent['@id'] ?? $f);
    printf("   la regola trova %d · la lista avrà %d voci\n", count($trovati), count($r['itemListElement']));
    foreach ($r['aggiunte'] as $id)   echo "   + $id  (nuova, entrata per regola)\n";
    foreach ($r['orfane'] as $id)     echo "   ⚠ $id  (era entrata per regola, ora non la trova più: NON la tolgo)\n";
    foreach ($r['incomplete'] as $id) echo "   ⚠ $id  (manca il dato su cui si ordina)\n";
    if (!$r['aggiunte'] && !$r['orfane'] && !$r['incomplete']) echo "   già in pari\n";

    if (!$apply) continue;
    $ent['itemListElement'] = $r['itemListElement'];
    $ent['numberOfItems'] = count($r['itemListElement']);
    if (isset($doc['mainEntity'])) $doc['mainEntity'] = $ent; else $doc = $ent;
    $doc['dateModified'] = date('c');
    $ok = @file_put_contents($f, json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) !== false;
    echo $ok ? "   scritto.\n" : "   ⚠ scrittura fallita.\n";
}
