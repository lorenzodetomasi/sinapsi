<?php
// Cestino degli eventi: spostare, ripristinare, eliminare per sempre.
//
// Cestinare = SPOSTARE la cartella da events/<slug> a _trash/events/<slug>.
// Niente viene riscritto: la cartella resta identica (media compresi) e il
// ripristino la rimette dov'era. Uscendo da events/ sparisce dagli indici e
// dalle pagine, ma è ancora sul disco finché non si svuota il cestino.
//
// Il manifest _trash/events.json ricorda cosa c'era, dove stava, quando e per
// mano di chi: è ciò che rende possibile il ripristino e la lista del cestino.
// È l'unico file scritto qui, ed è ricostruibile a mano se serve.

if (!function_exists('ws_trash_dir')) {
    function ws_trash_dir(string $base): string { return rtrim($base, '/') . '/_trash/events'; }
    function ws_trash_manifest(string $base): string { return rtrim($base, '/') . '/_trash/events.json'; }

    // Slug ammesso: un solo segmento, senza traversal. Vale sia per events/<slug>
    // sia per la cartella nel cestino.
    function ws_trash_valid_slug(string $slug): bool {
        return $slug !== '' && $slug !== '.' && $slug !== '..' && (bool)preg_match('#^[A-Za-z0-9._-]+$#', $slug);
    }

    function ws_trash_load(string $base): array {
        $f = ws_trash_manifest($base);
        if (!is_file($f)) return [];
        $j = json_decode((string)file_get_contents($f), true);
        return is_array($j) ? $j : [];
    }

    function ws_trash_save(string $base, array $list): bool {
        $dir = dirname(ws_trash_manifest($base));
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) return false;
        return @file_put_contents(
            ws_trash_manifest($base),
            json_encode(array_values($list), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        ) !== false;
    }

    // Cancellazione ricorsiva CONFINATA al cestino: se il percorso risolto non sta
    // dentro _trash non si tocca nulla (guardia contro id malformati o symlink).
    function ws_trash_rmdir(string $base, string $dir): bool {
        $trash = realpath(rtrim($base, '/') . '/_trash');
        $real = realpath($dir);
        if ($trash === false || $real === false) return false;
        if (strpos($real, $trash . DIRECTORY_SEPARATOR) !== 0) return false;   // fuori dal cestino: rifiuta
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($real, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) { $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
        return @rmdir($real);
    }

    // Sposta events/<slug> nel cestino. Ritorna ['ok'=>bool,'error'?,'entry'?].
    function ws_trash_move(string $base, string $relPath, array $user = []): array {
        $rel = trim($relPath, '/');
        if (strpos($rel, 'events/') !== 0) return ['ok' => false, 'error' => "Percorso non valido: '$relPath'."];
        $slug = substr($rel, strlen('events/'));
        if (!ws_trash_valid_slug($slug)) return ['ok' => false, 'error' => "Slug non valido: '$slug'."];

        $src = rtrim($base, '/') . '/events/' . $slug;
        if (!is_dir($src)) return ['ok' => false, 'error' => "L'evento '$rel' non esiste."];

        // Dati per la lista del cestino, letti PRIMA di spostare.
        $doc = [];
        if (is_file("$src/index.json")) {
            $j = json_decode((string)file_get_contents("$src/index.json"), true);
            if (is_array($j)) $doc = $j['mainEntity'] ?? $j;
        }

        $dir = ws_trash_dir($base);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) return ['ok' => false, 'error' => 'Cestino non creabile (permessi?).'];
        // Se un omonimo è già nel cestino, si affianca (non si sovrascrive).
        $id = $slug;
        $n = 1;
        while (is_dir("$dir/$id")) { $id = $slug . '~' . (++$n); }

        if (!@rename($src, "$dir/$id")) return ['ok' => false, 'error' => "Spostamento fallito (permessi su events/$slug?)."];

        $entry = [
            'id' => $id,
            'path' => $rel,                              // dove tornerà col ripristino
            'name' => (string)($doc['name'] ?? $slug),
            'startDate' => (string)($doc['startDate'] ?? ''),
            'kind' => in_array('EventSeries', (array)($doc['@type'] ?? []), true) ? 'series' : 'single',
            'trashedAt' => date('c'),
            'trashedBy' => isset($user['uid']) ? 'users/' . $user['uid'] : '',
            'trashedByName' => (string)($user['name'] ?? $user['email'] ?? ''),
        ];
        $list = ws_trash_load($base);
        $list[] = $entry;
        ws_trash_save($base, $list);
        return ['ok' => true, 'entry' => $entry];
    }

    // Rimette l'evento al suo posto. Non sovrascrive: se il percorso è di nuovo
    // occupato, il ripristino si ferma e lo dice.
    function ws_trash_restore(string $base, string $id): array {
        if (!ws_trash_valid_slug($id)) return ['ok' => false, 'error' => "Id non valido: '$id'."];
        $list = ws_trash_load($base);
        $idx = null;
        foreach ($list as $i => $e) if (($e['id'] ?? '') === $id) { $idx = $i; break; }
        if ($idx === null) return ['ok' => false, 'error' => "Non è nel cestino: '$id'."];

        $src = ws_trash_dir($base) . '/' . $id;
        if (!is_dir($src)) {   // cartella sparita: si ripulisce la voce orfana
            array_splice($list, $idx, 1); ws_trash_save($base, $list);
            return ['ok' => false, 'error' => "La cartella non c'è più: voce rimossa dal cestino."];
        }
        $rel = trim((string)$list[$idx]['path'], '/');
        $dest = rtrim($base, '/') . '/' . $rel;
        if (is_dir($dest)) return ['ok' => false, 'error' => "Esiste già un evento in '$rel': rinominalo prima di ripristinare."];
        if (!is_dir(dirname($dest)) && !@mkdir(dirname($dest), 0775, true)) return ['ok' => false, 'error' => 'Cartella events/ non creabile.'];
        if (!@rename($src, $dest)) return ['ok' => false, 'error' => 'Ripristino fallito (permessi?).'];

        $entry = $list[$idx];
        array_splice($list, $idx, 1);
        ws_trash_save($base, $list);
        return ['ok' => true, 'entry' => $entry];
    }

    // Elimina per sempre una voce del cestino.
    function ws_trash_delete(string $base, string $id): array {
        if (!ws_trash_valid_slug($id)) return ['ok' => false, 'error' => "Id non valido: '$id'."];
        $list = ws_trash_load($base);
        $idx = null;
        foreach ($list as $i => $e) if (($e['id'] ?? '') === $id) { $idx = $i; break; }
        if ($idx === null) return ['ok' => false, 'error' => "Non è nel cestino: '$id'."];
        $dir = ws_trash_dir($base) . '/' . $id;
        if (is_dir($dir) && !ws_trash_rmdir($base, $dir)) return ['ok' => false, 'error' => 'Eliminazione fallita (permessi?).'];
        array_splice($list, $idx, 1);
        ws_trash_save($base, $list);
        return ['ok' => true];
    }

    // Svuota il cestino. Ritorna quante voci sono state eliminate e quali no.
    function ws_trash_empty(string $base): array {
        $list = ws_trash_load($base);
        $deleted = 0; $failed = [];
        foreach ($list as $e) {
            $id = (string)($e['id'] ?? '');
            $dir = ws_trash_dir($base) . '/' . $id;
            if (!ws_trash_valid_slug($id)) { $failed[] = $id; continue; }
            if (is_dir($dir) && !ws_trash_rmdir($base, $dir)) { $failed[] = $id; continue; }
            $deleted++;
        }
        // Restano in elenco solo quelle che non si sono potute eliminare.
        ws_trash_save($base, array_values(array_filter($list, fn($e) => in_array((string)($e['id'] ?? ''), $failed, true))));
        return ['ok' => empty($failed), 'deleted' => $deleted, 'failed' => $failed];
    }
}
