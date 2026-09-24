<?php
//Path: ./ws-custom/plugins/google-login/ws-index.php
global $ws_logs;
$ws_logs[] = __('<strong>Google Login</strong> Plugin initialized <code>'.__FILE__.'</code>.');
// Evita di far partire la sessione due volte
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400, 'cookie_httponly' => true,
        'cookie_secure' => isset($_SERVER['HTTPS']), 'cookie_samesite' => 'Lax'
    ]);
}

// 1. HELPER GLOBALI (Protetti da re-dichiarazione)
if (!function_exists('get_consented_data')) {
    function get_consented_data($xml_node, $fallback_value = null) {
        if (!$xml_node || !isset($xml_node['consented_at'])) return $fallback_value;
        $timestamp = trim((string)$xml_node['consented_at']);
        if ($timestamp === '' || $timestamp === 'false' || $timestamp === '0') return $fallback_value;
        return trim((string)$xml_node);
    }
}

/**
 * Il profilo di chi sta guardando, in un posto solo.
 *
 * Serve a due template — la voce di accesso nell'header e la pagina protetta —
 * e ognuno se lo ricavava per conto suo. Con una conseguenza che non si vedeva:
 * i template si includono DENTRO una funzione (`load_template`), quindi le
 * variabili che il primo lasciava per strada al secondo non arrivavano mai. La
 * pagina protetta leggeva `$portal_name` e `$is_google_user` sempre indefiniti,
 * e mostrava «effettua l'accesso» anche a chi l'accesso l'aveva fatto.
 *
 * Una funzione non ha questo problema: la si chiama da dove serve e risponde
 * uguale. E sta qui, accanto a `GoogleAuth` e a `get_consented_data()`, perché
 * chi sa chi sei è questo plugin: un template non deve saperlo, deve chiederlo.
 *
 * I campi che dipendono dal CONSENSO passano da `get_consented_data()`, che li
 * dà solo se il consenso c'è — se no restano al ripiego, e per il nome il
 * ripiego è un'etichetta neutra, mai il nome vero. Se il plugin non fosse
 * attivo la risposta è «uno sconosciuto», che è il modo giusto di sbagliare.
 */
if(!function_exists('google_login_profilo')){
function google_login_profilo(){
	$anon = function_exists('__') ? __('Utente') : 'Utente';
	// `&&`, non `and`: `and` ha precedenza più bassa dell'uguale, e $vivo si
	// prenderebbe solo la prima metà della condizione.
	$vivo = class_exists('GoogleAuth') && function_exists('get_consented_data');
	$sessione = $vivo ? GoogleAuth::getSession() : null;
	$utente   = $vivo ? GoogleAuth::getRegisteredUser() : null;
	$p = array(
		'sessione'   => $sessione,
		'utente'     => $utente,
		'collegato'  => ($sessione !== null),
		'registrato' => ($utente !== null),
		'locale'     => isset($utente->locale) ? (string)$utente->locale : (isset($sessione->locale) ? $sessione->locale : 'it'),
		'role'       => isset($utente->role) ? (string)$utente->role : 'User',
		'anon'       => $anon,
		'name'       => $anon,
		'email'      => null,
		'image'      => null,
		'org_name'   => null,
		'org_logo'   => null,
	);
	if($utente !== null and isset($utente->person)){
		$persona = $utente->person;
		$p['name']  = get_consented_data($persona->name, $anon);
		// L'email sta fuori da `person`, nel documento dell'utente.
		$p['email'] = get_consented_data($utente->email, null);
		$p['image'] = get_consented_data($utente->image, null);
		$p['org_name'] = get_consented_data($persona->worksFor->organization->name, null);
		$p['org_logo'] = get_consented_data($persona->worksFor->organization->logo, null);
	}
	return $p;
}
}

if (!function_exists('get_google_initials_avatar')) {
    function get_google_initials_avatar($name) {
        $parts = explode(' ', $name);
        $initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
        $hash = md5($name);
        $color = substr($hash, 0, 6);
        return '<div style="width:56px; height:56px; border-radius:50%; background:#' . $color . '; color:#fff; display:flex; align-items:center; justify-content:center; font-size:20px; font-weight:bold; flex-shrink:0;">' . $initials . '</div>';
    }
}

// 2. CONFIGURAZIONE API
$google_api_oauth20_client = json_decode(GOOGLE_API_OAUTH20_CLIENT, true);
$CLIENT_ID = $google_api_oauth20_client['web']['client_id'];
$XML_FILE_PATH = ws_content_root_abspath() . '/users/users.xml';

// 3. ENDPOINT LOGIN/LOGOUT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['credential'])) {
    $jwt = $_POST['credential'];
    $url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . $jwt;
    $response = @file_get_contents($url);
    $userData = json_decode($response, true);

    if ($userData && isset($userData['aud']) && $userData['aud'] === $CLIENT_ID && isset($userData['email'])) {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_email'] = strtolower(trim($userData['email']));
        $_SESSION['user_name']    = $userData['name'] ?? 'Utente Google';
        $_SESSION['user_picture'] = $userData['picture'] ?? '';
        $_SESSION['email_verified'] = isset($userData['email_verified']) && filter_var($userData['email_verified'], FILTER_VALIDATE_BOOLEAN);
        $_SESSION['user_locale']    = $userData['locale'] ?? 'it'; 
        $_SESSION['user_sub']       = $userData['sub'] ?? '';
        echo json_encode(['success' => true]);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Token invalido']);
    }
    exit;
}

if (isset($_GET['logout'])) { 
    session_destroy(); 
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?')); 
    exit; 
}

// 4. LA CLASSE DI STATO (Avvolta in un guscio di sicurezza anti-crash)
if (!class_exists('GoogleAuth')) {
    class GoogleAuth {
        public static function getSession() {
            if (empty($_SESSION['logged_in']) || empty($_SESSION['email_verified'])) return null;
            return (object) [
                'email'   => $_SESSION['user_email'] ?? '',
                'name'    => $_SESSION['user_name'] ?? '',
                'picture' => $_SESSION['user_picture'] ?? '',
                'sub'     => $_SESSION['user_sub'] ?? '',
                'locale'  => $_SESSION['user_locale'] ?? 'it'
            ];
        }

        public static function getRegisteredUser() {
            $sub = 'sub:' . ($_SESSION['user_sub'] ?? '');
            $xml_path = ws_content_root_abspath() . '/users/users.xml';
            
            if ($sub === 'sub:' || !file_exists($xml_path)) return null;
            
            $xml = simplexml_load_file($xml_path);
            foreach ($xml->user as $user) {
                if (trim((string)$user['id']) === $sub) return $user;
            }
            return null;
        }

        public static function getClientId() {
            global $CLIENT_ID;
            return htmlspecialchars($CLIENT_ID);
        }
    }
}
$GLOBALS['ws_scripts']['head']['google-accounts'] = '<script src="https://accounts.google.com/gsi/client" async defer></script>';
$GLOBALS['ws_scripts']['bodyend']['google-login'] = "
<!-- Google Login -->
<script>
    function handleGoogleLogin(response) {
        const formData = new URLSearchParams();
        formData.append('credential', response.credential);
        fetch('', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => { if (data.success) window.location.reload(); else alert('Errore: ' + data.error); })
        .catch(err => alert('Impossibile contattare il server.'));
    }

</script>";
$GLOBALS['ws_styles']['head']['google-login'] = '<style>
    #google-user-registered { background: #f0fdf4; border: 1px solid #bbf7d0; }

    .google-avatar { display: flex; margin: 0; padding: 0; list-style: none; }
    .avatar-circle { width: 2.5rem; height: 2.5rem; border-radius: 50%; object-fit: cover; cursor: pointer; box-sizing: border-box; border: 2px solid transparent; transition: box-shadow .15s; display: block; }
    .avatar-circle.logged-in:hover { box-shadow: 0 1px 2px 0 rgba(60,64,67,.3), 0 1px 3px 1px rgba(60,64,67,.15); }
    .google-login-trigger { display: inline-flex; align-items: center; justify-content: center; width: 2.5rem; height: 2.5rem; border-radius: 50%; }

    /* La scheda del profilo NON si veste da sé: il guscio, il velo, il modo di
       aprirsi e di chiudersi sono quelli di ogni altra finestra del tema
       (.modal). Qui resta solo ciò che è suo, cioè chi sei. Prima era una
       tendina appesa all\'avatar, larga 280px e coi colori di Google: andava
       bene per tre righe, non per un profilo che crescerà — e su uno schermo
       stretto usciva dal bordo. */
    #profile-who { text-align: center; }
    #profile-who .profile-photo { width: 4.5rem; height: 4.5rem; border-radius: 50%; object-fit: cover; margin: 0 auto .75rem; display: block; }
    #profile-who .profile-name { margin: 0; font-weight: 600; }
    #profile-who .profile-email { margin: .25rem 0 0; color: var(--color-hint, #5f6368); font-size: .9rem; }
    #profile-who .profile-role { margin: .5rem 0 0; font-size: .7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }

    #profile-actions ul { display: flex; flex-wrap: wrap; justify-content: center; gap: .5rem; margin: 1.25rem 0 0; padding: 1rem 0 0; list-style: none; border-top: 1px solid var(--color-line, #e8eaed); }
    #profile-actions a { display: inline-flex; align-items: center; gap: .35rem; padding: .5rem .9rem; border: 1px solid var(--color-line, #dadce0); border-radius: 999px; text-decoration: none; color: inherit; }
    #profile-actions a:hover { border-color: var(--color-link, #2e3192); }

    .portal-id-card { background: var(--color-superficie, #fff); padding: 1.5rem; border-radius: 8px; border: 1px dashed #bbf7d0; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 20px; }
    .portal-avatar-placeholder { width: 56px; height: 56px; border-radius: 50%; background: #f1f3f4; display: flex; align-items: center; justify-content: center; color: #9aa0a6; flex-shrink: 0; }
    .portal-avatar-img { width: 56px; height: 56px; border-radius: 50%; object-fit: cover; flex-shrink: 0; border: 2px solid #e8f0fe; }
</style>';
?>