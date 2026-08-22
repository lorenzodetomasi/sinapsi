<?php
// Immagini di copertina: regole di archiviazione, ritaglio 16:9 e deduplica.
//
// DUE CARTELLE, DUE RUOLI (come per i places):
//   media-sources/  l'ORIGINALE così com'è stato caricato — non si tocca mai,
//                   serve a rigenerare la cover se cambia l'inquadratura;
//   media/          ciò che il sito SERVE: sempre 1920×1080 (16:9).
// Se il file caricato è già 1920×1080 va dritto in media/ e non c'è originale da
// conservare: la copia servita È l'originale.
//
// DEDUPLICA: l'indice _index/media.json mappa l'impronta (sha256) del file al
// percorso già presente. Ricaricare la stessa immagine — o duplicare un evento —
// non crea un secondo file: si riusa lo stesso percorso. L'indice è derivato e
// ricostruibile (ws_media_reindex).

const WS_COVER_W = 1920;
const WS_COVER_H = 1080;

if (!function_exists('ws_media_index_path')) {
    function ws_media_index_path(string $base): string { return rtrim($base, '/') . '/_index/media.json'; }

    function ws_media_index_load(string $base): array {
        $f = ws_media_index_path($base);
        if (!is_file($f)) return [];
        $j = json_decode((string)@file_get_contents($f), true);
        return is_array($j) ? $j : [];
    }

    function ws_media_index_save(string $base, array $idx): bool {
        $dir = dirname(ws_media_index_path($base));
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) return false;
        ksort($idx);
        return @file_put_contents(ws_media_index_path($base),
            json_encode($idx, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) !== false;
    }

    // Registra un file nell'indice per impronta. $rel = percorso dalla radice dei contenuti.
    function ws_media_index_add(string $base, string $hash, string $rel, array $extra = []): void {
        if ($hash === '' || $rel === '') return;
        $idx = ws_media_index_load($base);
        // Un percorso ha un solo contenuto corrente: le impronte vecchie che puntavano
        // qui (es. prima di un nuovo ritaglio) vanno tolte, altrimenti l elenco mostra
        // la stessa immagine due volte e il riuso può agganciare un file sparito.
        foreach ($idx as $h => $m) if (($m['path'] ?? '') === $rel && $h !== $hash) unset($idx[$h]);
        $idx[$hash] = array_merge(['path' => $rel], $extra);
        ws_media_index_save($base, $idx);
    }

    // Cerca un'immagine identica già presente. Ritorna il percorso o ''.
    // Verifica che il file esista ancora: un indice vecchio non deve far puntare al vuoto.
    function ws_media_index_find(string $base, string $hash): string {
        $idx = ws_media_index_load($base);
        $rel = (string)($idx[$hash]['path'] ?? '');
        if ($rel === '') return '';
        return is_file(rtrim($base, '/') . '/' . $rel) ? $rel : '';
    }

    // Ricostruisce l'indice scandendo le cartelle media/ dei contenuti.
    function ws_media_reindex(string $base): array {
        $idx = [];
        foreach (['events', 'places', 'organizations'] as $sub) {
            $dir = rtrim($base, '/') . '/' . $sub;
            if (!is_dir($dir)) continue;
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if (!$f->isFile()) continue;
                $p = $f->getPathname();
                if (!preg_match('#/media/[^/]+$#', $p)) continue;                 // solo ciò che è servito
                if (!preg_match('/\.(jpe?g|png|webp)$/i', $p)) continue;
                $rel = ltrim(str_replace(rtrim($base, '/'), '', $p), '/');
                $size = @getimagesize($p);
                $idx[hash_file('sha256', $p)] = [
                    'path' => $rel,
                    'w' => $size[0] ?? null, 'h' => $size[1] ?? null,
                    'bytes' => filesize($p),
                ];
            }
        }
        ws_media_index_save($base, $idx);
        return $idx;
    }

    // Percorso sicuro di un'entità (events/<slug>, places/<...>): niente traversal.
    function ws_media_entity_dir(string $base, string $rel): ?string {
        $rel = trim($rel, '/');
        if ($rel === '' || strpos($rel, '..') !== false) return null;
        if (!preg_match('#^(events|places|organizations)/[A-Za-z0-9._/-]+$#', $rel)) return null;
        $dir = rtrim($base, '/') . '/' . $rel;
        return is_dir($dir) ? $dir : null;
    }

    // Carica un'immagine in memoria (GD), qualunque formato ammesso.
    function ws_media_read($path) {
        $info = @getimagesize($path);
        if (!$info) return [null, null];
        switch ($info[2]) {
            case IMAGETYPE_JPEG: $im = @imagecreatefromjpeg($path); break;
            case IMAGETYPE_PNG:  $im = @imagecreatefrompng($path); break;
            case IMAGETYPE_WEBP: $im = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null; break;
            default: $im = null;
        }
        return [$im, $info];
    }

    // Genera la cover 1920×1080 da un'immagine sorgente.
    // $crop = null → ritaglio CENTRATO al 16:9; altrimenti ['x','y','w','h'] in
    // frazioni 0..1 dell'originale (l'inquadratura scelta nell'editor).
    // Ritorna true/false; l'errore torna in $err.
    function ws_media_make_cover(string $srcPath, string $destPath, ?array $crop = null, &$err = null): bool {
        $err = '';
        if (!function_exists('imagecreatetruecolor')) { $err = 'estensione GD non disponibile sul server'; return false; }
        list($src, $info) = ws_media_read($srcPath);
        if (!$src) { $err = 'immagine non leggibile (formato non supportato?)'; return false; }
        $sw = imagesx($src); $sh = imagesy($src);

        if ($crop && isset($crop['w'], $crop['h'])) {
            // Inquadratura scelta: frazioni → pixel, poi si riporta al 16:9 esatto.
            $cw = max(1, (int)round($crop['w'] * $sw));
            $ch = max(1, (int)round($crop['h'] * $sh));
            $cx = max(0, min($sw - $cw, (int)round(($crop['x'] ?? 0) * $sw)));
            $cy = max(0, min($sh - $ch, (int)round(($crop['y'] ?? 0) * $sh)));
        } else {
            // Ritaglio centrato: si toglie il di più dal lato lungo.
            $target = WS_COVER_W / WS_COVER_H;
            if ($sw / $sh > $target) { $ch = $sh; $cw = (int)round($sh * $target); }
            else                     { $cw = $sw; $ch = (int)round($sw / $target); }
            $cx = (int)round(($sw - $cw) / 2);
            $cy = (int)round(($sh - $ch) / 2);
        }

        $dst = imagecreatetruecolor(WS_COVER_W, WS_COVER_H);
        imagecopyresampled($dst, $src, 0, 0, $cx, $cy, WS_COVER_W, WS_COVER_H, $cw, $ch);
        $dir = dirname($destPath);
        // (da PHP 8 la memoria delle immagini si libera da sé: niente imagedestroy)
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) { $err = 'cartella media/ non creabile (permessi?)'; return false; }
        $ok = @imagejpeg($dst, $destPath, 86);
        if (!$ok) $err = 'scrittura della cover fallita (permessi?)';
        return (bool)$ok;
    }

    // Nome file sicuro a partire da quello caricato.
    function ws_media_safe_name(string $name, string $ext): string {
        $slug = preg_replace('/[^a-zA-Z0-9._-]+/', '-', pathinfo($name, PATHINFO_FILENAME));
        $slug = trim((string)$slug, '-.');
        return ($slug !== '' ? $slug : 'immagine') . '.' . $ext;
    }
    function ws_media_unique(string $dir, string $name): string {
        $base = pathinfo($name, PATHINFO_FILENAME);
        $ext  = pathinfo($name, PATHINFO_EXTENSION);
        $try  = $name;
        for ($i = 1; file_exists("$dir/$try"); $i++) $try = "$base-$i.$ext";
        return $try;
    }
}

// Genera in blocco le cover 1920x1080 per gli eventi che hanno gia un immagine.
// $apply=false → dice solo cosa farebbe. $adopt=true → adotta come cover un'immagine
// trovata nella cartella di un evento che non ne dichiara nessuna (solo se ce n e UNA:
// scegliere per conto dell utente fra piu immagini sarebbe indovinare).
if (!function_exists('ws_media_covers')) {
    function ws_media_covers(string $base, bool $apply, bool $adopt = false): array {
        $rep = ['done' => [], 'skipped' => [], 'broken' => [], 'external' => [], 'applied' => $apply];
        $cands = function (string $dir): array {
            $out = [];
            foreach (['media', 'media-sources'] as $sub)
                foreach (glob("$dir/$sub/*.{jpg,jpeg,png,webp}", GLOB_BRACE) ?: [] as $p)
                    if (!preg_match('#/(logo|satellite)[^/]*$#i', $p)) $out[] = $p;
            return $out;
        };
        foreach (glob("$base/events/*/index.json") as $file) {
            $dir = dirname($file); $slug = basename($dir); $rel = "events/$slug";
            $doc = json_decode((string)file_get_contents($file), true);
            if (!is_array($doc)) continue;
            $e = isset($doc['mainEntity']) && is_array($doc['mainEntity']) ? 'mainEntity' : null;
            $t = $e ? $doc[$e] : $doc;
            $img = $t['image'] ?? '';
            $img = is_array($img) ? (string)($img['url'] ?? $img['@id'] ?? '') : (string)$img;
            if ($img === '') {
                $c = $cands($dir);
                if (count($c) !== 1) continue;
                if (!$adopt) { $rep['skipped'][] = ['event' => $slug, 'why' => 'immagine orfana: ' . basename($c[0])]; continue; }
                $img = ltrim(str_replace($dir, '', $c[0]), '/');
            }
            if (preg_match('#^https?://#i', $img)) { $rep['external'][] = ['event' => $slug, 'url' => $img]; continue; }
            $srcRel = preg_match('#^(events|places|organizations)/#', $img) ? $img : "$rel/" . ltrim($img, '/');
            $srcPath = "$base/$srcRel";
            if (!is_file($srcPath)) {
                $c = $cands($dir);
                if (!($adopt && $c)) { $rep['broken'][] = ['event' => $slug, 'ref' => $img, 'found' => $c ? basename($c[0]) : '']; continue; }
                $srcPath = $c[0]; $srcRel = $rel . '/' . ltrim(str_replace($dir, '', $srcPath), '/');
            }
            $size = @getimagesize($srcPath);
            if (!$size) { $rep['broken'][] = ['event' => $slug, 'ref' => $srcRel, 'found' => 'file illeggibile']; continue; }
            $coverRel = "$rel/media/" . pathinfo($srcRel, PATHINFO_FILENAME) . '.jpg';
            $isCover = ((int)$size[0] === WS_COVER_W && (int)$size[1] === WS_COVER_H);
            if ($isCover && $img === $coverRel) continue;
            $rep['done'][] = ['event' => $slug, 'from' => $size[0] . 'x' . $size[1], 'cover' => $coverRel, 'exact' => $isCover];
            if (!$apply) continue;
            if ($isCover) {
                if ($srcRel !== $coverRel) { @mkdir("$dir/media", 0775, true); @copy($srcPath, "$base/$coverRel"); }
            } else {
                $err = '';
                if (!ws_media_make_cover($srcPath, "$base/$coverRel", null, $err)) {
                    array_pop($rep['done']); $rep['broken'][] = ['event' => $slug, 'ref' => $srcRel, 'found' => $err]; continue;
                }
                if (strpos($srcRel, '/media/') !== false && !is_file("$dir/media-sources/" . basename($srcPath))) {
                    @mkdir("$dir/media-sources", 0775, true); @copy($srcPath, "$dir/media-sources/" . basename($srcPath));
                }
            }
            $t['image'] = $coverRel;
            if ($e) $doc[$e] = $t; else $doc = $t;
            file_put_contents($file, json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
        if ($apply) ws_media_reindex($base);
        return $rep;
    }
}
