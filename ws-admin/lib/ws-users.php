<?php
require_once __DIR__ . '/ws-wrap.php';
// Profilo dell'utente/visitatore Google, keyed sull'UID: users/{uid}/index.json (Person +
// preferenze meetoo). Separato da users/users.xml (che controlla i RUOLI dell'editor):
// qui vivono i registranti agli eventi, per accessi futuri e memoria di scelte/preferenze.
// $base = .../contents/meetoo/it_IT. Contiene dati personali → sta in ws-custom (gitignored).

require_once __DIR__ . '/ws-private.php';

if (!function_exists('ws_user_upsert')) {
    // Crea o aggiorna il profilo. $auth = output di ws_authenticate (uid/name/email/...).
    // Ritorna il profilo aggiornato.
    function ws_user_upsert(string $base, array $auth): array {
        $uid  = (string)($auth['uid'] ?? '');
        if ($uid === '') return [];
        $dir  = rtrim($base, '/') . '/users/' . $uid;
        $file = "$dir/index.json";
        $now  = date('c');

        $documento = is_file($file) ? json_decode((string)@file_get_contents($file), true) : null;
        $doc = is_array($documento) ? ws_wrap_entity($documento) : null;
        if (!is_array($doc)) {
            $doc = [
                '@context'    => 'https://schema.org',
                '@type'       => 'Person',
                '@id'         => "users/$uid",
                'identifier'  => $uid,   // Google UID (sub)
                'dateCreated' => $now,
                'meetoo:preferences' => [],
            ];
        }
        // Nome, email e foto NON stanno qui: questo file è servito dal web.
        // Vanno nell archivio privato, e si ricompongono lato server per chi può vederli.
        ws_private_user_set($uid, [
            'name' => (string)($auth['name'] ?? ''),
            'email' => (string)($auth['email'] ?? ''),
            'picture' => (string)($auth['picture'] ?? ''),
        ]);
        unset($doc['name'], $doc['email'], $doc['image']);   // ripulisce i profili scritti prima
        if (!isset($doc['meetoo:preferences']) || !is_array($doc['meetoo:preferences'])) $doc['meetoo:preferences'] = [];
        if (!isset($doc['dateCreated'])) $doc['dateCreated'] = $now;
        $doc['dateModified'] = $now;

        @mkdir($dir, 0775, true);
        @file_put_contents($file, json_encode(
            is_array($documento) ? ws_wrap_set($documento, $doc) : ws_wrap_one($doc),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $doc;
    }
}

if (!function_exists('ws_user_set_prefs')) {
    // Aggiorna (merge) le preferenze meetoo dell'utente. Ritorna le preferenze aggiornate.
    function ws_user_set_prefs(string $base, string $uid, array $prefs): array {
        $doc = ws_user_get($base, $uid);
        $documento = is_array($doc) ? $doc : null;
        $doc = is_array($doc) ? ws_wrap_entity($doc) : null;
        if (!is_array($doc)) $doc = ['@context' => 'https://schema.org', '@type' => 'Person', '@id' => "users/$uid", 'identifier' => $uid, 'dateCreated' => date('c')];
        $cur = (isset($doc['meetoo:preferences']) && is_array($doc['meetoo:preferences'])) ? $doc['meetoo:preferences'] : [];
        // whitelist dei campi ammessi
        foreach (['language', 'notifications'] as $k) if (array_key_exists($k, $prefs)) $cur[$k] = $prefs[$k];
        $doc['meetoo:preferences'] = $cur;
        $doc['dateModified'] = date('c');
        $dir = rtrim($base, '/') . '/users/' . $uid;
        @mkdir($dir, 0775, true);
        @file_put_contents("$dir/index.json", json_encode(
            is_array($documento) ? ws_wrap_set($documento, $doc) : ws_wrap_one($doc),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $cur;
    }
}

if (!function_exists('ws_user_get')) {
    function ws_user_get(string $base, string $uid): ?array {
        $file = rtrim($base, '/') . '/users/' . $uid . '/index.json';
        $doc = is_file($file) ? json_decode((string)@file_get_contents($file), true) : null;
        return is_array($doc) ? $doc : null;
    }
}

// Eventi che interessano all utente: elenco sul suo profilo, così "i miei eventi"
// non dipende dal browser. Idempotente: aggiunge o toglie una sola volta.
if (!function_exists('ws_user_toggle_like')) {
    function ws_user_toggle_like(string $base, string $uid, string $eventRel, bool $on): bool {
        $f = "$base/users/$uid/index.json";
        $d = is_file($f) ? json_decode((string)@file_get_contents($f), true) : null;
        if (!is_array($d)) return false;
        $e = isset($d['mainEntity']) && is_array($d['mainEntity']) ? 'mainEntity' : null;
        $t = $e ? $d[$e] : $d;
        $cur = isset($t['meetoo:interestedIn']) && is_array($t['meetoo:interestedIn']) ? $t['meetoo:interestedIn'] : [];
        $cur = array_values(array_filter($cur, fn($x) => $x !== $eventRel));
        if ($on) $cur[] = $eventRel;
        $t['meetoo:interestedIn'] = $cur;
        if ($e) $d[$e] = $t; else $d = $t;
        return @file_put_contents($f, json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) !== false;
    }
}
