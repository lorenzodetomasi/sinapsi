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

    function toggleGoogleProfileCard(event) {
        event.stopPropagation();
        document.getElementById('google-profile-card').classList.toggle('hidden');
    }

    document.addEventListener('click', function() {
        const popup = document.getElementById('google-profile-card');
        if (popup && !popup.classList.contains('hidden')) popup.classList.add('hidden');
    });
</script>";
$GLOBALS['ws_styles']['head']['google-login'] = '<style>
    #google-user-registered { background: #f0fdf4; border: 1px solid #bbf7d0; }
    
    .google-avatar { position: relative; display: inline-block; font-family: "Google Sans", Roboto, Arial, sans-serif; user-select: none; }
    .avatar-circle { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; cursor: pointer; box-sizing: border-box; border: 2px solid transparent; transition: box-shadow 0.15s; }
    .avatar-circle.logged-in:hover { box-shadow: 0 1px 2px 0 rgba(60,64,67,0.3), 0 1px 3px 1px rgba(60,64,67,0.15); }
    .google-login-trigger { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 50%; }
    .google-login-trigger:hover { transform: scale(1.05); }

    .profile-popup { position: absolute; top: 52px; right: 0; width: 280px; background: #ffffff; border-radius: 24px; box-shadow: 0 4px 12px 0 rgba(60,64,67,0.15), 0 8px 24px 0 rgba(60,64,67,0.15); padding: 20px; border: 1px solid #dadce0; z-index: 99999; text-align: center; }
    .profile-popup.hidden { display: none; }
    .popup-big-avatar { width: 72px; height: 72px; border-radius: 50%; object-fit: cover; margin-bottom: 12px; }
    .popup-name { font-size: 16px; font-weight: 500; color: #202124; line-height: 1.2; }
    .popup-email { font-size: 14px; color: #5f6368; margin-top: 4px; }
    
    .popup-meta { display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 12px; }
    .meta-pill { font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 12px; border: 1px solid #dadce0; text-transform: uppercase; }
    .meta-pill.role-pill { background: #e8f0fe; color: #1967d2; border-color: #d2e3fc; }
    .meta-pill.lang-pill { background: #e6f4ea; color: #137333; border-color: #ceead6; }

    .popup-actions { margin-top: 20px; border-top: 1px solid #e8eaed; padding-top: 16px; }
    .google-action-btn { display:inline-flex; align-items:center; gap:6px; padding:8px 16px; border-radius:8px; font-size:13px; font-weight:500; text-decoration:none; margin-bottom:8px; } 
    .register-btn { background:#1a73e8; color:#fff; } 
    .edit-btn { background:#f1f3f4; color:#3c4043; }
    .google-logout-btn { display: inline-flex; align-items: center; justify-content: center; padding: 10px 24px; border-radius: 100px; background: #f8f9fa; color: #3c4043; font-size: 14px; text-decoration: none; border: 1px solid #dadce0; }
    
    .portal-id-card { background: #fff; padding: 1.5rem; border-radius: 8px; border: 1px dashed #bbf7d0; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 20px; }
    .portal-avatar-placeholder { width: 56px; height: 56px; border-radius: 50%; background: #f1f3f4; display: flex; align-items: center; justify-content: center; color: #9aa0a6; flex-shrink: 0; }
    .portal-avatar-img { width: 56px; height: 56px; border-radius: 50%; object-fit: cover; flex-shrink: 0; border: 2px solid #e8f0fe; }
</style>';
?>