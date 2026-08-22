<?php
/*
 * Genera le copertine 16:9 per gli eventi che hanno già un'immagine.
 *
 * Per ogni evento con un campo `image`:
 *   • se punta a un file mancante → lo dice e cerca un candidato nella cartella;
 *   • se il file non è 1920×1080 → genera media/<nome>.jpg (ritaglio centrato)
 *     dall'originale in media-sources/ quando c'è, altrimenti dal file stesso;
 *   • riscrive `image` come percorso DALLA RADICE (events/<slug>/media/…), la
 *     forma che permette di riusare la stessa cover in un altro evento.
 *
 *   php ws-admin/events/make-covers.php             (dry-run)
 *   php ws-admin/events/make-covers.php --apply     (scrive)
 *   php ws-admin/events/make-covers.php --apply --adopt
 *       in più ADOTTA come cover un'immagine trovata nella cartella di un evento
 *       che non ne dichiara nessuna (solo se ce n'è una sola: niente indovinelli).
 *
 * Idempotente: rilanciarlo non rigenera ciò che è già a posto.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Solo da riga di comando.\n"); }

require __DIR__ . '/../lib/ws-media.php';

$apply = in_array('--apply', $argv, true);
$adopt = in_array('--adopt', $argv, true);
$base  = __DIR__ . '/../../ws-custom/contents/meetoo/it_IT';

$fatte = 0; $gia = 0; $rotte = 0; $adottate = 0;

// Immagini plausibili come cover dentro la cartella dell'evento (no loghi/satellite).
$candidati = function (string $dir): array {
    $out = [];
    foreach (['media', 'media-sources'] as $sub) {
        foreach (glob("$dir/$sub/*.{jpg,jpeg,png,webp}", GLOB_BRACE) ?: [] as $p) {
            if (preg_match('/\/(logo|satellite)[^\/]*$/i', $p)) continue;
            $out[] = $p;
        }
    }
    return $out;
};

foreach (glob("$base/events/*/index.json") as $file) {
    $dir  = dirname($file);
    $slug = basename($dir);
    $rel  = "events/$slug";
    $doc  = json_decode((string)file_get_contents($file), true);
    if (!is_array($doc)) continue;
    $e = isset($doc['mainEntity']) && is_array($doc['mainEntity']) ? 'mainEntity' : null;
    $t = $e ? $doc[$e] : $doc;

    $img = $t['image'] ?? '';
    $img = is_array($img) ? (string)($img['url'] ?? $img['@id'] ?? '') : (string)$img;

    // Nessuna immagine dichiarata: eventualmente se ne adotta una dalla cartella.
    if ($img === '') {
        $c = $candidati($dir);
        if (count($c) === 1) {
            echo "  $slug → nessuna cover dichiarata, trovata: " . basename($c[0]) . ($adopt ? '' : "  (usa --adopt per adottarla)") . "\n";
            if ($apply && $adopt) { $img = ltrim(str_replace($dir, '', $c[0]), '/'); }
            else { continue; }
            $adottate++;
        } else { continue; }
    }

    if (preg_match('#^https?://#i', $img)) { echo "  $slug → immagine esterna ($img): lasciata com'è\n"; continue; }

    // Percorso del file, accettando sia la forma relativa sia quella dalla radice.
    $srcRel  = preg_match('#^(events|places|organizations)/#', $img) ? $img : "$rel/" . ltrim($img, '/');
    $srcPath = "$base/$srcRel";

    if (!is_file($srcPath)) {
        $rotte++;
        $c = $candidati($dir);
        echo "  $slug → ⚠ image punta a un file mancante ($img)"
            . ($c ? '; nella cartella c\'è: ' . basename($c[0]) : '; nessuna immagine nella cartella') . "\n";
        if (!($apply && $adopt && $c)) continue;
        $srcPath = $c[0];
        $srcRel  = $rel . '/' . ltrim(str_replace($dir, '', $srcPath), '/');
    }

    $size = @getimagesize($srcPath);
    if (!$size) { echo "  $slug → ⚠ file illeggibile: $srcRel\n"; $rotte++; continue; }

    $coverRel = "$rel/media/" . pathinfo($srcRel, PATHINFO_FILENAME) . '.jpg';
    $isCover  = ((int)$size[0] === WS_COVER_W && (int)$size[1] === WS_COVER_H);

    if ($isCover && $img === $coverRel) { $gia++; continue; }        // già a posto

    if ($isCover) {
        echo "  $slug → già 1920×1080: aggiorno solo il riferimento → $coverRel\n";
        if ($apply) {
            if ($srcRel !== $coverRel) { @mkdir("$dir/media", 0775, true); @copy($srcPath, "$base/$coverRel"); }
            $t['image'] = $coverRel;
        }
    } else {
        echo "  $slug → {$size[0]}×{$size[1]} → genero $coverRel\n";
        if ($apply) {
            $err = '';
            if (!ws_media_make_cover($srcPath, "$base/$coverRel", null, $err)) { echo "      ✗ $err\n"; $rotte++; continue; }
            // L'originale non va perso: se stava in media/ lo si conserva in media-sources/.
            if (strpos($srcRel, '/media/') !== false && !is_file("$dir/media-sources/" . basename($srcPath))) {
                @mkdir("$dir/media-sources", 0775, true);
                @copy($srcPath, "$dir/media-sources/" . basename($srcPath));
            }
            $t['image'] = $coverRel;
        }
    }
    $fatte++;
    if ($apply) {
        if ($e) $doc[$e] = $t; else $doc = $t;
        file_put_contents($file, json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}

if ($apply) { ws_media_reindex($base); }

echo "\n" . ($apply ? 'APPLICATO' : 'DRY-RUN') . ": $fatte cover, $gia già a posto, $rotte da sistemare a mano"
   . ($adottate ? ", $adottate adottate" : '') . ".\n";
if (!$apply) echo "Rilancia con --apply (aggiungi --adopt per adottare le immagini orfane). Poi: php ws-admin/events/rebuild-index.php\n";
