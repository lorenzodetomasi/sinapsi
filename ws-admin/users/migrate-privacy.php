<?php
/*
 * Toglie i dati personali dai file serviti dal web.
 *
 * Nome, email e foto stanno oggi dentro users/<uid>/index.json e (per chi si è
 * registrato) dentro events/<slug>/rsvp.json: sono file statici, quindi leggibili
 * da chiunque conosca l'URL. Questo comando li sposta nell'archivio privato
 * (ws-admin/_private/) e li cancella dai file pubblici, lasciandoci solo l'uid.
 *
 *   php ws-admin/users/migrate-privacy.php            (dry-run: dice cosa farebbe)
 *   php ws-admin/users/migrate-privacy.php --apply    (scrive)
 *
 * Idempotente: rilanciarlo non fa danni. Va eseguito ANCHE IN PRODUZIONE, dove i
 * dati sono già stati pubblicati; l'esposizione passata non si annulla da sola
 * (motori di ricerca e copie cache possono conservarne traccia).
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Solo da riga di comando.\n"); }

require __DIR__ . '/../lib/ws-private.php';

$apply = in_array('--apply', $argv, true);
$base  = __DIR__ . '/../../ws-custom/contents/meetoo/it_IT';

$PERSONALI = ['name', 'email', 'image', 'telephone', 'givenName', 'familyName'];
$profili = 0; $campi = 0; $rsvp = 0; $regs = 0;

/* ---- Profili utente ---- */
foreach (glob("$base/users/*/index.json") as $f) {
    $uid = basename(dirname($f));
    $doc = json_decode((string)file_get_contents($f), true);
    if (!is_array($doc)) continue;
    $e = isset($doc['mainEntity']) && is_array($doc['mainEntity']) ? 'mainEntity' : null;
    $t = $e ? $doc[$e] : $doc;

    $trovati = [];
    foreach ($PERSONALI as $k) if (isset($t[$k]) && $t[$k] !== '') $trovati[$k] = $t[$k];
    if (!$trovati) continue;

    $profili++; $campi += count($trovati);
    echo "  users/$uid → sposto: " . implode(', ', array_keys($trovati)) . "\n";
    if ($apply) {
        ws_private_user_set($uid, [
            'name' => (string)($trovati['name'] ?? ''),
            'email' => (string)($trovati['email'] ?? ''),
            'picture' => (string)($trovati['image'] ?? ''),
            'telephone' => (string)($trovati['telephone'] ?? ''),
        ]);
        foreach (array_keys($trovati) as $k) unset($t[$k]);
        if ($e) $doc[$e] = $t; else $doc = $t;
        file_put_contents($f, json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}

/* ---- Registrazioni agli eventi ---- */
foreach (glob("$base/events/*/rsvp.json") as $f) {
    $d = json_decode((string)file_get_contents($f), true);
    if (!is_array($d) || empty($d['registrations'])) continue;
    $tocca = false;
    foreach ($d['registrations'] as $i => $r) {
        $uid = (string)($r['uid'] ?? '');
        $ha = (isset($r['name']) && $r['name'] !== '') || (isset($r['email']) && $r['email'] !== '');
        if (!$ha) continue;
        $tocca = true; $regs++;
        if ($apply) {
            if ($uid !== '') ws_private_user_set($uid, ['name' => (string)($r['name'] ?? ''), 'email' => (string)($r['email'] ?? '')]);
            unset($d['registrations'][$i]['name'], $d['registrations'][$i]['email']);
        }
    }
    if ($tocca) {
        $rsvp++;
        echo "  " . basename(dirname($f)) . "/rsvp.json → tolgo nome/email dalle registrazioni\n";
        if ($apply) {
            $d['registrations'] = array_values($d['registrations']);
            file_put_contents($f, json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
    }
}

if ($apply) ws_private_ensure();

echo "\n" . ($profili || $rsvp
    ? ($apply ? "APPLICATO" : "DRY-RUN") . ": $profili profili ($campi campi), $rsvp file di registrazioni ($regs voci).\n"
    : "Niente da spostare: nessun dato personale nei file pubblici.\n");
if (!$apply && ($profili || $rsvp)) echo "Rilancia con --apply per scrivere.\n";
