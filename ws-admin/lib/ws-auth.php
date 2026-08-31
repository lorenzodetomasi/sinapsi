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
        // Un'email non verificata non dà diritto a nessun ruolo: chiunque potrebbe
        // dichiarare l'indirizzo di un altro.
        $role = $emailVerified ? ws_ruolo_utente($uid, $usersXmlPath) : 'logged-visitor';

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

/**
 * Il ruolo di un utente, da `users.xml`.
 *
 * Era dentro `ws_authenticate`, e ci restava chiuso: adesso il ruolo serve anche a
 * chi arriva con una SESSIONE invece che con un token, e la regola dev'essere una
 * sola — se le due strade rispondessero diversamente, la stessa persona sarebbe
 * amministratore da una parte e visitatore dall'altra.
 *
 * Chi non è in elenco è `verified-visitor`: ha dimostrato di essere qualcuno, ma
 * qui dentro non è nessuno in particolare.
 */
if (!function_exists('ws_ruolo_utente')) {
    function ws_ruolo_utente(string $uid, ?string $usersXmlPath = null): string {
        $ruolo = 'verified-visitor';
        $usersXmlPath = $usersXmlPath ?: (__DIR__ . '/../../ws-custom/contents/meetoo/it_IT/users/users.xml');
        if (!is_file($usersXmlPath)) return $ruolo;

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        libxml_use_internal_errors(true);
        // Gli XInclude annidati (persons/) possono dare warning NON fatali: il ruolo
        // è già nel <user> incluso al primo livello, quindi non blocchiamo su errori.
        $dom->load($usersXmlPath, LIBXML_XINCLUDE | LIBXML_NOENT | LIBXML_NONET);
        $dom->xinclude(LIBXML_XINCLUDE);
        libxml_clear_errors();
        $xml = simplexml_import_dom($dom);
        if (!$xml) return $ruolo;

        // Due scritture per lo stesso identificativo: il `sub` nudo (com'è nei file
        // di Meetoo) e la forma `sub:…` che usa il plugin di accesso. Si accettano
        // tutte e due invece di far dipendere il ruolo da quale strada hai preso.
        $trovati = $xml->xpath("//user[@id='$uid' or @id='sub:$uid']");
        if (!empty($trovati) && isset($trovati[0]->role) && (string)$trovati[0]->role !== '') {
            $ruolo = (string)$trovati[0]->role;
        }
        return $ruolo;
    }
}

/**
 * Chi sei secondo la SESSIONE PHP, quella che apre il plugin `google-login`.
 *
 * È l'alternativa al token nel corpo della richiesta, e la differenza non è di
 * stile: un Google ID token è un'asserzione d'identità che dura un'ora, e usarlo
 * come sessione significa chiedere di riaccedere ogni ora. Una sessione dura
 * quanto il suo cookie, e il giro fino a Google si fa una volta sola, all'accesso.
 *
 * Qui la sessione si LEGGE soltanto: chi la apre è il plugin. E non se ne apre una
 * a chi non ce l'ha — un visitatore anonimo non deve portarsi via un cookie per
 * aver guardato una pagina.
 */
if (!function_exists('ws_autentica_sessione')) {
    function ws_autentica_sessione(?string $usersXmlPath = null): ?array {
        if (session_status() === PHP_SESSION_NONE) {
            if (empty($_COOKIE[session_name()])) return null;
            @session_start(array(
                'cookie_httponly' => true,
                'cookie_samesite' => 'Lax',
                'cookie_secure' => isset($_SERVER['HTTPS']),
            ));
        }
        if (empty($_SESSION['logged_in']) || empty($_SESSION['email_verified'])) return null;
        $uid = (string)($_SESSION['user_sub'] ?? '');
        if (!preg_match('/^\d{6,}$/', $uid)) return null;   // il sub Google è numerico

        return [
            'uid' => $uid,
            'email' => (string)($_SESSION['user_email'] ?? ''),
            'email_verified' => true,
            'name' => (string)($_SESSION['user_name'] ?? ''),
            'picture' => (string)($_SESSION['user_picture'] ?? ''),
            'role' => ws_ruolo_utente($uid, $usersXmlPath),
            'locale' => (string)($_SESSION['user_locale'] ?? 'it'),
        ];
    }
}

/**
 * Il gettone anti-CSRF della sessione.
 *
 * Con il token nel corpo, chi scriveva doveva conoscerlo: era la prova che la
 * richiesta partiva davvero da qui. Il cookie di sessione no — il browser lo manda
 * da solo anche se a chiedere è un altro sito, ed è esattamente così che si fanno
 * agire le persone a loro insaputa. Il gettone rimette la prova al suo posto.
 */
if (!function_exists('ws_gettone_sessione')) {
    function ws_gettone_sessione(): string {
        if (session_status() !== PHP_SESSION_ACTIVE) return '';
        if (empty($_SESSION['ws_csrf'])) {
            $_SESSION['ws_csrf'] = bin2hex(random_bytes(16));
        }
        return (string)$_SESSION['ws_csrf'];
    }
}
if (!function_exists('ws_gettone_valido')) {
    function ws_gettone_valido(string $dato): bool {
        // hash_equals: il confronto non deve dire, dal tempo che impiega, quanti
        // caratteri erano giusti.
        return $dato !== '' && !empty($_SESSION['ws_csrf']) && hash_equals((string)$_SESSION['ws_csrf'], $dato);
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

/* ---- Chi è di casa --------------------------------------------------------
 *
 * Un gruppo dice chi lo gestisce: `meetoo:manager` elenca le persone che ne curano
 * la scheda e pubblicano a suo nome. È il legame che mancava, e da lui dipendono
 * tre cose che sembrano lontane fra loro: chi ha il diritto di creare qualcosa,
 * chi ha il badge verificato (e riceve la fattura), e la differenza fra un evento
 * organizzato da un gruppo e un incontro proposto da chi partecipa.
 *
 *   "meetoo:manager": [ { "@type": "Person", "@id": "users/1004491…" } ]
 */

/** Dove possono stare i gruppi: le organizzazioni, e i luoghi — perché un teatro
 *  o una libreria organizzano eventi quanto un'associazione, e chi li cura deve
 *  poterne gestire la scheda. Cercarli solo sotto `organizations/` lasciava fuori
 *  metà di chi organizza davvero. */
if (!function_exists('ws_gruppi_glob')) {
    function ws_gruppi_glob(string $base): array {
        $b = rtrim($base, '/');
        return array_merge(
            glob("$b/organizations/*/index.json") ?: [],
            glob("$b/places/*/index.json") ?: [],
            glob("$b/places/*/*/index.json") ?: []
        );
    }
}

/** I gruppi che questa persona gestisce, come @id. */
if (!function_exists('ws_gruppi_gestiti')) {
    function ws_gruppi_gestiti(string $base, string $uid): array {
        static $letti = [];
        if ($uid === '') return [];
        $chiave = "$base|$uid";
        if (isset($letti[$chiave])) return $letti[$chiave];
        $me = "users/$uid";
        $out = [];
        foreach (ws_gruppi_glob($base) as $f) {
            $j = json_decode((string)@file_get_contents($f), true);
            if (!is_array($j)) continue;
            $e = $j['mainEntity'] ?? $j;
            if (!is_array($e)) continue;
            if (in_array($me, ws_ref_ids($e['meetoo:manager'] ?? null), true)) {
                $id = (string)($e['@id'] ?? '');
                if ($id !== '') $out[] = $id;
            }
        }
        return $letti[$chiave] = $out;
    }
}

/**
 * Può creare una cosa nuova di questo tipo?
 *
 * Modificare e creare sono due domande diverse, e finora ne esisteva una sola:
 * `ws_can_edit()` sa dire chi può toccare una cosa che c'è già, ma sulla creazione
 * non c'era nessuna regola — e infatti dal sito non si poteva creare niente.
 *
 * «Organizzatore verificato» qui vuol dire una cosa precisa: chi ha almeno un
 * gruppo da gestire. Crea eventi (che pubblicherà a nome del gruppo) e luoghi
 * (che poi userà per i suoi eventi). Un gruppo NON lo crea: aprire un gruppo nuovo
 * è un atto di riconoscimento, e lo fa chi amministra.
 */
if (!function_exists('ws_can_create')) {
    function ws_can_create(string $tipo, string $uid, string $role, array $gruppi = []): bool {
        if ($uid === '') return false;                        // da sloggati non si crea
        if (in_array($role, ['admin', 'super-admin'], true)) return true;
        if (!$gruppi) return false;
        return in_array($tipo, ['events', 'places'], true);
    }
}

/**
 * Può modificare questa entità?
 *
 * Amministratori sempre. Poi tre modi di essere di casa: chi gestisce il gruppo
 * modifica il gruppo; chi gestisce il gruppo modifica quello che il gruppo
 * organizza; chi ha creato una cosa (o è fra i contributor) modifica la sua.
 *
 * NON PIÙ: «se non c'è `creator`, allora sì». Era una regola di transizione scritta
 * quando gli utenti erano due e i contenuti li scrivevamo noi, e diceva in pratica
 * «chiunque abbia fatto login può modificare qualunque cosa» — perché il `creator`
 * nei contenuti non c'è quasi mai. Finché la porta d'ingresso non esisteva era
 * innocua; nel momento in cui si invitano gli organizzatori a entrare diventa una
 * casa senza pareti. Chi aveva scritto qualcosa prima che il campo esistesse lo
 * ritrova attraverso il gruppo, che è il modo giusto di dire «questo è mio».
 */
if (!function_exists('ws_can_edit')) {
    function ws_can_edit(array $entity, string $userUid, string $userRole, array $gruppi = []): bool {
        if (in_array($userRole, ['admin', 'super-admin'], true)) return true;
        if ($userUid === '') return false;
        $me = "users/$userUid";
        if (in_array($me, ws_ref_ids($entity['meetoo:manager'] ?? null), true)) return true;
        if ($gruppi && array_intersect($gruppi, ws_ref_ids($entity['organizer'] ?? null))) return true;
        $creatorId = ws_ref_id($entity['creator'] ?? null);
        return $creatorId === $me || in_array($me, ws_ref_ids($entity['contributor'] ?? null), true);
    }
}
