<?php
/*
 * «Contenuti da migrare»: chi ha ancora il testo nel campo sbagliato.
 *
 * Da quando i due testi sono separati — SOMMARIO (`abstract`) per chi legge la
 * pagina, DESCRIZIONE (`description`) per il risultato di ricerca — i contenuti
 * scritti prima hanno il corpo formattato dentro `description`. Continuano a
 * funzionare, perché le pagine mostrano il Sommario se c'è e la descrizione
 * altrimenti; ma il loro `<meta description>` è una frase lunga ripulita dai tag,
 * e non una frase scritta per quel mestiere.
 *
 * La migrazione è a mano, ed è giusto così: spostare il testo da un campo
 * all'altro è facile, scrivere la frase per i motori è un lavoro editoriale che
 * non si automatizza. Questa operazione NON scrive niente: dice quanti sono, dove
 * stanno e in che ordine conviene prenderli.
 *
 * L'ordine: prima i contenuti che hanno una PAGINA e che qualcuno può trovare —
 * eventi e collezioni, poi luoghi e organizzazioni — e dentro ognuno prima i più
 * lunghi, perché sono quelli il cui meta description viene tagliato peggio.
 */

if (!function_exists('ws_migrazione_stato')) {

/** Il testo senza marcatura, per contare i caratteri veri. */
function ws_migrazione_piano_testo(string $xhtml): string {
    $s = preg_replace('#</(p|div|li|h[1-6]|blockquote|section|tr)>#i', ' ', $xhtml);
    $s = preg_replace('#<(br|hr)\s*/?>#i', ' ', (string)$s);
    $s = trim(html_entity_decode(strip_tags((string)$s), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    return preg_replace('/\s+/u', ' ', $s);
}

/** C'è marcatura qui dentro? */
function ws_migrazione_ha_marcatura(string $testo): bool {
    return (bool)preg_match('/<[a-z][^>]*>/i', $testo);
}

/**
 * Chi va migrato. Non scrive niente.
 *
 * Ritorna ['changes'=>n, 'voci'=>[['rel','tipo','motivo','caratteri']], 'ok'=>n].
 */
function ws_migrazione_stato(string $base): array {
    $base = rtrim($base, '/');
    $voci = [];
    $ok = 0;
    $ordine = ['events' => 1, 'places' => 2, 'organizations' => 3];

    foreach (['events/*', 'places/*', 'places/*/*', 'organizations/*'] as $gruppo) {
        foreach (glob("$base/$gruppo/index.json") as $file) {
            $doc = json_decode((string)@file_get_contents($file), true);
            if (!is_array($doc)) continue;
            $e = (isset($doc['mainEntity']) && is_array($doc['mainEntity'])) ? $doc['mainEntity'] : $doc;
            $rel = trim(str_replace($base, '', dirname($file)), '/');
            $desc = trim((string)($e['description'] ?? ''));
            $somm = trim((string)($e['abstract'] ?? ''));

            if ($desc === '' && $somm === '') continue;   // niente testo: niente da migrare

            $marcata = ws_migrazione_ha_marcatura($desc);
            $lunga = mb_strlen(ws_migrazione_piano_testo($desc)) > 200;

            if (!$marcata && !$lunga) { $ok++; continue; }

            $voci[] = [
                'rel' => $rel,
                'tipo' => explode('/', $rel)[0],
                'nome' => (string)($e['name'] ?? basename($rel)),
                'motivo' => $marcata
                    ? ($somm === '' ? 'il testo formattato è ancora nella descrizione' : 'descrizione con marcatura, ma il Sommario c\'è già')
                    : 'descrizione lunga: nel risultato di ricerca verrebbe tagliata',
                'caratteri' => mb_strlen(ws_migrazione_piano_testo($desc)),
                'sommario' => $somm !== '',
            ];
        }
    }

    // Prima chi ha più da perdere: per tipo di contenuto, poi per lunghezza.
    usort($voci, function ($a, $b) use ($ordine) {
        $pa = $ordine[$a['tipo']] ?? 9;
        $pb = $ordine[$b['tipo']] ?? 9;
        if ($pa !== $pb) return $pa <=> $pb;
        return $b['caratteri'] <=> $a['caratteri'];
    });

    return ['changes' => count($voci), 'voci' => $voci, 'ok' => $ok];
}

}// function_exists
