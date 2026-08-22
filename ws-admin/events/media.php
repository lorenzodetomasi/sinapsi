<?php
/*
 * Immagini di copertina — caricamento, ritaglio 16:9, riuso.
 *
 *   action=upload   (multipart: file, path=events/<slug>)
 *       già 1920×1080 → media/<nome>            (la copia servita È l'originale)
 *       altrimenti    → media-sources/<nome>    (originale, conservato)
 *                     + media/<nome>.jpg        (cover 1920×1080, ritaglio centrato)
 *       Se un'immagine IDENTICA esiste già (impronta sha256), non se ne scrive una
 *       seconda: si restituisce il percorso esistente. È così che un evento
 *       duplicato riusa la cover senza copiarla.
 *
 *   action=recrop   (source=<percorso originale>, path=events/<slug>, x,y,w,h in 0..1)
 *       rigenera media/<nome> con l'inquadratura scelta, sovrascrivendola.
 *
 *   action=library  elenco delle cover già in uso, per riusarne una.
 *
 * I percorsi restituiti sono RELATIVI ALLA RADICE dei contenuti (events/<slug>/media/…):
 * così valgono anche citati da un altro evento. Le pagine accettano entrambe le
 * forme (relativa alla cartella o dalla radice).
 */

ini_set('display_errors', '0');   // endpoint JSON: nessun errore PHP nel corpo

require_once __DIR__ . '/../lib/ws-auth.php';
require_once __DIR__ . '/../lib/ws-media.php';

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Solo POST']); exit; }

$user = ws_authenticate((string)($_POST['credential'] ?? ''));
if ($user === null) { http_response_code(401); echo json_encode(['error' => 'Autenticazione Google fallita o token scaduto. Accedi di nuovo.']); exit; }
if (!in_array($user['role'], ['user', 'client', 'admin', 'super-admin'], true)) {
    http_response_code(403); echo json_encode(['error' => "Permessi insufficienti (ruolo: {$user['role']})."]); exit;
}

$base   = __DIR__ . '/../../ws-custom/contents/meetoo/it_IT';
$action = (string)($_POST['action'] ?? 'upload');

/* ---------- Elenco delle cover disponibili (per riusarne una) ---------- */
if ($action === 'library') {
    $idx = ws_media_index_load($base);
    $items = [];
    foreach ($idx as $hash => $m) {
        $rel = (string)($m['path'] ?? '');
        if ($rel === '' || !is_file("$base/$rel")) continue;
        $items[] = [
            'path' => $rel, 'w' => $m['w'] ?? null, 'h' => $m['h'] ?? null,
            'owner' => preg_replace('#/media(-sources)?/.*$#', '', $rel),
        ];
    }
    usort($items, fn($a, $b) => strcmp($b['owner'], $a['owner']));   // i più recenti (slug con data) per primi
    echo json_encode(['items' => $items]);
    exit;
}

/* ---------- Nuova inquadratura su un'immagine già caricata ---------- */
if ($action === 'recrop') {
    $rel = trim((string)($_POST['path'] ?? ''), '/');
    $dir = ws_media_entity_dir($base, $rel);
    if ($dir === null) { http_response_code(400); echo json_encode(['error' => "Percorso non valido: '$rel'."]); exit; }

    $source = trim((string)($_POST['source'] ?? ''), '/');          // originale, dalla radice
    $srcPath = "$base/$source";
    if ($source === '' || strpos($source, '..') !== false || !is_file($srcPath)) {
        http_response_code(400); echo json_encode(['error' => 'Immagine originale non trovata: serve il file in media-sources/.']); exit;
    }
    $crop = [
        'x' => (float)($_POST['x'] ?? 0), 'y' => (float)($_POST['y'] ?? 0),
        'w' => (float)($_POST['w'] ?? 1), 'h' => (float)($_POST['h'] ?? 1),
    ];
    $name = pathinfo($source, PATHINFO_FILENAME) . '.jpg';
    $dest = "$dir/media/$name";
    $err = '';
    if (!ws_media_make_cover($srcPath, $dest, $crop, $err)) { http_response_code(500); echo json_encode(['error' => "Ritaglio fallito: $err"]); exit; }

    $coverRel = "$rel/media/$name";
    ws_media_index_add($base, hash_file('sha256', $dest), $coverRel, ['w' => WS_COVER_W, 'h' => WS_COVER_H, 'bytes' => filesize($dest), 'source' => $source]);
    echo json_encode(['success' => true, 'path' => $coverRel, 'source' => $source, 'w' => WS_COVER_W, 'h' => WS_COVER_H]);
    exit;
}

/* ---------- Caricamento ---------- */
$rel = trim((string)($_POST['path'] ?? ''), '/');
$dir = ws_media_entity_dir($base, $rel);
if ($dir === null) {
    http_response_code(400);
    echo json_encode(['error' => "Salva prima l'evento: la cartella '$rel' non esiste ancora."]);
    exit;
}

$file = $_FILES['file'] ?? null;
if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(400); echo json_encode(['error' => 'Nessun file valido caricato.']); exit;
}
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
    http_response_code(400); echo json_encode(['error' => "Formato non ammesso: .$ext (ammessi: jpg, png, webp)."]); exit;
}
if (($file['size'] ?? 0) > 12 * 1024 * 1024) {
    http_response_code(400); echo json_encode(['error' => 'Immagine troppo grande (max 12 MB).']); exit;
}
$tmp = $file['tmp_name'];
$size = @getimagesize($tmp);
if (!$size) { http_response_code(400); echo json_encode(['error' => 'Il file non è un\'immagine leggibile.']); exit; }

// Già caricata (qui o altrove)? Si riusa: nessun duplicato sul disco.
$hash = hash_file('sha256', $tmp);
$already = ws_media_index_find($base, $hash);
if ($already !== '') {
    echo json_encode(['success' => true, 'path' => $already, 'reused' => true,
        'w' => $size[0], 'h' => $size[1],
        'note' => 'Immagine identica già presente: riusata senza crearne una copia.']);
    exit;
}

$name = ws_media_safe_name($file['name'], $ext);

// Regola: già 1920×1080 → dritta in media/. Altrimenti l'originale resta in
// media-sources/ e in media/ va la cover generata.
if ((int)$size[0] === WS_COVER_W && (int)$size[1] === WS_COVER_H) {
    if (!is_dir("$dir/media") && !@mkdir("$dir/media", 0775, true)) { http_response_code(500); echo json_encode(['error' => 'Cartella media/ non creabile (permessi?)']); exit; }
    $name = ws_media_unique("$dir/media", $name);
    if (!@move_uploaded_file($tmp, "$dir/media/$name")) { http_response_code(500); echo json_encode(['error' => 'Salvataggio fallito (permessi?)']); exit; }
    $coverRel = "$rel/media/$name";
    ws_media_index_add($base, $hash, $coverRel, ['w' => WS_COVER_W, 'h' => WS_COVER_H, 'bytes' => filesize("$dir/media/$name")]);
    echo json_encode(['success' => true, 'path' => $coverRel, 'exact' => true, 'w' => WS_COVER_W, 'h' => WS_COVER_H,
        'note' => 'Già 1920×1080: salvata così com\'è.']);
    exit;
}

if (!is_dir("$dir/media-sources") && !@mkdir("$dir/media-sources", 0775, true)) { http_response_code(500); echo json_encode(['error' => 'Cartella media-sources/ non creabile (permessi?)']); exit; }
$srcName = ws_media_unique("$dir/media-sources", $name);
if (!@move_uploaded_file($tmp, "$dir/media-sources/$srcName")) { http_response_code(500); echo json_encode(['error' => 'Salvataggio dell\'originale fallito (permessi?)']); exit; }

$coverName = pathinfo($srcName, PATHINFO_FILENAME) . '.jpg';
$err = '';
if (!ws_media_make_cover("$dir/media-sources/$srcName", "$dir/media/$coverName", null, $err)) {
    http_response_code(500);
    echo json_encode(['error' => "Originale salvato, ma la cover 1920×1080 non è stata generata: $err",
        'source' => "$rel/media-sources/$srcName"]);
    exit;
}
$coverRel = "$rel/media/$coverName";
ws_media_index_add($base, hash_file('sha256', "$dir/media/$coverName"), $coverRel,
    ['w' => WS_COVER_W, 'h' => WS_COVER_H, 'bytes' => filesize("$dir/media/$coverName"), 'source' => "$rel/media-sources/$srcName"]);
ws_media_index_add($base, $hash, "$rel/media-sources/$srcName", ['w' => $size[0], 'h' => $size[1], 'original' => true]);

echo json_encode(['success' => true, 'path' => $coverRel, 'source' => "$rel/media-sources/$srcName",
    'w' => WS_COVER_W, 'h' => WS_COVER_H, 'from' => ['w' => $size[0], 'h' => $size[1]],
    'note' => "Ritagliata al centro da {$size[0]}×{$size[1]}: puoi scegliere un'altra inquadratura."]);
