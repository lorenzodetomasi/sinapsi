<?php
// Autenticazione condivisa (Google Identity): verifica il Google ID token via
// oauth2.googleapis.com/tokeninfo e risolve il RUOLO dell'utente da users.xml
// (XInclude). Stessa logica del backend places, estratta per riuso.
//
// Ritorna ['uid','email','email_verified','role','locale'] oppure null se il token
// è mancante/non valido/scaduto.

if (!function_exists('ws_authenticate')) {
    function ws_authenticate(string $jwtToken, ?string $usersXmlPath = null): ?array {
        $jwtToken = trim($jwtToken);
        if ($jwtToken === '') return null;

        $resp = @file_get_contents('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($jwtToken));
        $info = $resp ? json_decode($resp, true) : null;
        if (!is_array($info) || isset($info['error']) || empty($info['sub'])) return null;

        $uid = (string)$info['sub'];
        if (!preg_match('/^\d{6,}$/', $uid)) return null; // il sub Google è numerico

        $emailVerified = !empty($info['email_verified']) && $info['email_verified'] !== 'false';
        $role = 'logged-visitor';

        if ($emailVerified) {
            $role = 'verified-visitor';
            $usersXmlPath = $usersXmlPath ?: (__DIR__ . '/../../ws-custom/contents/meetoo/it_IT/users/users.xml');
            if (is_file($usersXmlPath)) {
                $dom = new DOMDocument();
                $dom->preserveWhiteSpace = false;
                libxml_use_internal_errors(true);
                // Gli XInclude annidati (persons/) possono dare warning NON fatali: il ruolo
                // è già nel <user> incluso al primo livello, quindi non blocchiamo su errori.
                $dom->load($usersXmlPath, LIBXML_XINCLUDE | LIBXML_NOENT | LIBXML_NONET);
                $dom->xinclude(LIBXML_XINCLUDE);
                libxml_clear_errors();
                $xml = simplexml_import_dom($dom);
                $found = $xml ? $xml->xpath("//user[@id='$uid']") : [];
                if (!empty($found) && isset($found[0]->role) && (string)$found[0]->role !== '') {
                    $role = (string)$found[0]->role;
                }
            }
        }

        return [
            'uid' => $uid,
            'email' => $info['email'] ?? '',
            'email_verified' => $emailVerified,
            'name' => $info['name'] ?? '',
            'picture' => $info['picture'] ?? '',
            'role' => $role,
            'locale' => $info['locale'] ?? 'it',
        ];
    }
}

// Riferimento persona schema.org da UID (per creator/contributor).
if (!function_exists('ws_person_ref')) {
    function ws_person_ref(string $uid): array { return ['@type' => 'Person', '@id' => "users/$uid"]; }
}

// @id di un riferimento (oggetto {@id} o stringa).
if (!function_exists('ws_ref_id')) {
    function ws_ref_id($x): string {
        if (is_array($x)) return (string)($x['@id'] ?? '');
        return is_string($x) ? $x : '';
    }
}
if (!function_exists('ws_ref_ids')) {
    function ws_ref_ids($x): array {
        if ($x === null) return [];
        $list = (isset($x['@id']) || isset($x['@type'])) ? [$x] : (is_array($x) ? $x : [$x]);
        return array_values(array_filter(array_map('ws_ref_id', $list), fn($s) => $s !== ''));
    }
}

// Può l'utente modificare questa entità? admin/super-admin sempre; altrimenti solo
// se è creator o tra i contributor; se non c'è creator (legacy) → sì.
if (!function_exists('ws_can_edit')) {
    function ws_can_edit(array $entity, string $userUid, string $userRole): bool {
        if (in_array($userRole, ['admin', 'super-admin'], true)) return true;
        $creatorId = ws_ref_id($entity['creator'] ?? null);
        if ($creatorId === '') return true;
        $me = "users/$userUid";
        return $creatorId === $me || in_array($me, ws_ref_ids($entity['contributor'] ?? null), true);
    }
}
