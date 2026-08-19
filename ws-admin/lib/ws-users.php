<?php
// Profilo dell'utente/visitatore Google, keyed sull'UID: users/{uid}/index.json (Person +
// preferenze meetoo). Separato da users/users.xml (che controlla i RUOLI dell'editor):
// qui vivono i registranti agli eventi, per accessi futuri e memoria di scelte/preferenze.
// $base = .../contents/meetoo/it_IT. Contiene dati personali → sta in ws-custom (gitignored).

if (!function_exists('ws_user_upsert')) {
    // Crea o aggiorna il profilo. $auth = output di ws_authenticate (uid/name/email/...).
    // Ritorna il profilo aggiornato.
    function ws_user_upsert(string $base, array $auth): array {
        $uid  = (string)($auth['uid'] ?? '');
        if ($uid === '') return [];
        $dir  = rtrim($base, '/') . '/users/' . $uid;
        $file = "$dir/index.json";
        $now  = date('c');

        $doc = is_file($file) ? json_decode((string)@file_get_contents($file), true) : null;
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
        if (!empty($auth['name']))    $doc['name']  = (string)$auth['name'];
        if (!empty($auth['email']))   $doc['email'] = (string)$auth['email'];
        if (!empty($auth['picture'])) $doc['image'] = (string)$auth['picture'];
        if (!isset($doc['dateCreated'])) $doc['dateCreated'] = $now;
        $doc['dateModified'] = $now;

        @mkdir($dir, 0775, true);
        @file_put_contents($file, json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $doc;
    }
}

if (!function_exists('ws_user_get')) {
    function ws_user_get(string $base, string $uid): ?array {
        $file = rtrim($base, '/') . '/users/' . $uid . '/index.json';
        $doc = is_file($file) ? json_decode((string)@file_get_contents($file), true) : null;
        return is_array($doc) ? $doc : null;
    }
}
