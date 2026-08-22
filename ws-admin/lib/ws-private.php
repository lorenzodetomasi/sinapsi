<?php
// Dati personali (nome, email, foto) — FUORI dai contenuti pubblicati.
//
// I contenuti sotto ws-custom/ sono file statici serviti dal web: qualunque cosa
// ci finisca è leggibile da chiunque conosca l'URL. Nome ed email di una persona
// non ci devono stare. Qui vivono in ws-admin/_private/, dove:
//   • il file è .php → una richiesta diretta lo ESEGUE e non stampa nulla;
//   • un .htaccess nega comunque l'accesso, per i server che lo leggono.
// Doppia difesa apposta: se una delle due salta, l'altra regge.
//
// Nei contenuti resta solo ciò che è pseudonimo (l'uid) o pubblico per natura
// (preferenze, eventi che interessano). I nomi si ricompongono lato server, e
// solo per chi è autorizzato a vederli.

if (!function_exists('ws_private_dir')) {
    function ws_private_dir(): string { return __DIR__ . '/../_private'; }

    function ws_private_ensure(): bool {
        $dir = ws_private_dir();
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) return false;
        $ht = "$dir/.htaccess";
        if (!is_file($ht)) {
            @file_put_contents($ht, "# Dati personali: nessun accesso dal web.\nRequire all denied\n<IfModule !mod_authz_core.c>\n  Deny from all\n</IfModule>\n");
        }
        $idx = "$dir/index.php";
        if (!is_file($idx)) @file_put_contents($idx, "<?php http_response_code(404); exit;\n");
        return true;
    }

    // Un record per persona: ws-admin/_private/users/<uid>.php
    function ws_private_user_file(string $uid): ?string {
        if (!preg_match('/^[A-Za-z0-9._-]{1,64}$/', $uid)) return null;   // niente traversal
        return ws_private_dir() . '/users/' . $uid . '.php';
    }

    function ws_private_user_get(string $uid): array {
        $f = ws_private_user_file($uid);
        if ($f === null || !is_file($f)) return [];
        $d = @include $f;
        return is_array($d) ? $d : [];
    }

    // Scrive/aggiorna il record. Conserva ciò che c'era e non è stato ripassato.
    function ws_private_user_set(string $uid, array $fields): bool {
        $f = ws_private_user_file($uid);
        if ($f === null || !ws_private_ensure()) return false;
        if (!is_dir(dirname($f)) && !@mkdir(dirname($f), 0775, true)) return false;
        $data = array_merge(ws_private_user_get($uid), array_filter($fields, fn($v) => $v !== null && $v !== ''));
        $data['uid'] = $uid;
        $php = "<?php\n// Dati personali — non pubblicare, non versionare.\nreturn " . var_export($data, true) . ";\n";
        return @file_put_contents($f, $php) !== false;
    }

    // Nome da mostrare (a chi è autorizzato): il vero nome, o l'email, o l'uid.
    function ws_private_display_name(string $uid): string {
        $d = ws_private_user_get($uid);
        return (string)($d['name'] ?? $d['email'] ?? $uid);
    }
    function ws_private_email(string $uid): string {
        return (string)(ws_private_user_get($uid)['email'] ?? '');
    }
}
