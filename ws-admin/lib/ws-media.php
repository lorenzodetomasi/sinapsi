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
