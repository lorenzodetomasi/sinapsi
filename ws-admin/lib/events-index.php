<?php
// Manutenzione dell'indice eventi, aggiornato a ogni salvataggio web.
//   • Globale:       events/_index/events.json            (elenco compatto di tutti gli eventi, per il picker "Apri")
//   • Per-organizer: events/_index/by-organizer/<key>.json (per le pagine-organizer: Prossimi eventi / Archivio)
// Voce compatta per evento; ordinamento per startDate. Best-effort: gli errori NON bloccano il salvataggio.

// Voce compatta dell'indice a partire dal documento evento e dal percorso (schema c).
if (!function_exists('event_index_item')) {
    function event_index_item(array $doc, string $relPath): array {
        // cap dal nome cartella: <AAAAMMGGThhmm>-<cap>[-slug]
        $cap = '';
        if (preg_match('/^\d{8}T\d{4}-([A-Za-z0-9]+)/', basename($relPath), $m)) $cap = $m[1];

        // organizer: name del primo se presente (inline), altrimenti l'@id di riferimento
        $orgName = '';
        $orgId = '';
        $o = $doc['organizer'] ?? null;
        if (is_array($o)) {
            $first = array_key_exists(0, $o) ? $o[0] : $o;
            if (is_array($first)) { $orgName = (string)($first['name'] ?? ''); $orgId = (string)($first['@id'] ?? ''); }
        } elseif (is_string($o)) {
            $orgId = $o;
        }

        // immagine di anteprima: prima url utile tra image/logo (stringa o oggetto/array)
        $img = '';
        foreach (['image', 'logo'] as $k) {
            $v = $doc[$k] ?? null;
            if (is_string($v) && $v !== '') { $img = $v; break; }
            if (is_array($v)) {
                $img = (string)($v['url'] ?? $v['@id'] ?? (isset($v[0]) && is_array($v[0]) ? ($v[0]['url'] ?? '') : (is_string($v[0] ?? null) ? $v[0] : '')));
                if ($img !== '') break;
            }
        }

        return [
            'path'         => $relPath,
            'name'         => (string)($doc['name'] ?? ''),
            'startDate'    => (string)($doc['startDate'] ?? ''),
            'endDate'      => (string)($doc['endDate'] ?? ''),
            'organizer'    => $orgName !== '' ? $orgName : $orgId,
            'location'     => (string)($doc['location']['name'] ?? ''),
            'cap'          => $cap,
            'status'       => (string)($doc['eventStatus'] ?? ''),
            'image'        => $img,
            'dateModified' => (string)($doc['dateModified'] ?? ''),
        ];
    }
}

// Chiave file-safe per un organizer (ultimo segmento del path/URL, sanitizzato).
if (!function_exists('event_index_key')) {
    function event_index_key(string $refId): string {
        $s = trim($refId);
        if ($s === '') return 'senza-organizer';
        $p = strrpos($s, '/');
        if ($p !== false) $s = substr($s, $p + 1);
        $s = trim((string)preg_replace('/[^A-Za-z0-9._-]+/', '-', $s), '-');
        return $s !== '' ? $s : 'senza-organizer';
    }
}

// Upsert atomico-ish di una voce in un file-indice (elenco JSON): rimuove la voce con
// lo stesso path, aggiunge quella nuova, riordina per startDate e riscrive.
if (!function_exists('event_index_upsert_file')) {
    function event_index_upsert_file(string $file, array $item): bool {
        $dir = dirname($file);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) return false;
        $list = [];
        if (is_file($file)) {
            $j = json_decode((string)@file_get_contents($file), true);
            if (is_array($j)) $list = (isset($j['events']) && is_array($j['events'])) ? $j['events'] : $j;
        }
        $list = array_values(array_filter(
            is_array($list) ? $list : [],
            fn($e) => is_array($e) && ($e['path'] ?? null) !== $item['path']
        ));
        $list[] = $item;
        usort($list, fn($a, $b) => strcmp((string)($a['startDate'] ?? ''), (string)($b['startDate'] ?? '')));
        $json = json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return $json !== false && @file_put_contents($file, $json) !== false;
    }
}

// Aggiorna l'indice globale e quelli per-organizer. $base = .../contents/meetoo/it_IT.
// Ritorna ['global'=>bool, 'organizers'=>[key=>bool]] per la diagnostica.
if (!function_exists('event_index_update')) {
    function event_index_update(string $base, string $relPath, array $doc): array {
        $item = event_index_item($doc, $relPath);
        $idxDir = rtrim($base, '/') . '/events/_index';
        $res = ['global' => event_index_upsert_file("$idxDir/events.json", $item), 'organizers' => []];

        $orgIds = function_exists('ws_ref_ids') ? ws_ref_ids($doc['organizer'] ?? null) : [];
        if (!$orgIds && $item['organizer'] !== '') $orgIds = [$item['organizer']]; // organizer inline senza @id
        foreach (array_unique($orgIds) as $oid) {
            $key = event_index_key($oid);
            $res['organizers'][$key] = event_index_upsert_file("$idxDir/by-organizer/$key.json", $item);
        }
        return $res;
    }
}
