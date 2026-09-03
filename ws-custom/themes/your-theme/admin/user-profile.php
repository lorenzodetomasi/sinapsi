<?php
session_start();

/* INCORPORATA o a pagina intera.
 *
 * `?embed=1` serve al riquadro del profilo di Meetoo: restituisce SOLO il
 * contenuto — niente doctype, niente head, niente body — così si può mettere
 * dentro un modale invece che dentro una pagina. Cambia tre cose e nient'altro:
 * il guscio, la risposta a chi non è collegato (un messaggio invece di un
 * rimando, perché dentro un modale un redirect non si vede) e dove si va dopo
 * aver salvato (in nessun posto: si resta lì, con la conferma).
 *
 * La pagina intera continua a funzionare esattamente come prima. */
$embed = isset($_GET['embed']) && $_GET['embed'] !== '0';

/* La radice del sito, invece di `https://www.isotype.org` scritto a mano: questo
 * file gira anche altrove, e un indirizzo assoluto ce lo porta comunque lì. */
$ws_radice = function_exists('ws_root_url') ? rtrim(ws_root_url(), '/') : '';

// Controllo d'accesso base
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    if ($embed) {
        echo '<p class="mt-prof-vuoto">Accedi per completare il tuo profilo.</p>';
        exit;
    }
    header('Location: ' . $ws_radice . '/eventi');
    exit;
}

$XML_FILE_PATH = ws_content_root_abspath() . '/users/users.xml';
$user_sub_id = 'sub:' . ($_SESSION['user_sub'] ?? '');

if ($user_sub_id === 'sub:') {
    die("Errore di sessione: Sub ID mancante. Effettua il logout e riaccedi.");
}

// ============================================================================
// GESTIONE POST DEL FORM: Salvataggio preferenze nell'XML e File Upload
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_privacy_settings'])) {
    
    if (file_exists($XML_FILE_PATH)) {
        $xml = simplexml_load_file($XML_FILE_PATH);
        $target_user = null;
        
        foreach ($xml->user as $u) {
            if (trim((string)$u['id']) === $user_sub_id) { 
                $target_user = $u; 
                break; 
            }
        }

        if (!$target_user) {
            $target_user = $xml->addChild('user');
            $target_user->addAttribute('id', $user_sub_id);
            $target_user->addChild('role', 'user');
            $ap = $target_user->addChild('access_paths');
            $ap->addChild('path', '/progetti/guest/');
        }

        $locale_val = $_SESSION['user_locale'] ?? 'it';
        if (!isset($target_user->locale)) {
            $target_user->addChild('locale', $locale_val);
        } else {
            $target_user->locale = $locale_val;
        }

        $now = date('c');

        // GESTIONE NOME E PSEUDONIMO
        if (isset($_POST['show_name'])) {
            $input_name = trim($_POST['custom_name'] ?? '');
            $final_name = ($input_name === '') ? 'Utente Anonimo' : $input_name;
            
            if (!isset($target_user->name)) $target_user->addChild('name');
            $target_user->name[0] = $final_name;
            $target_user->name['consented_at'] = $now;
        } else { 
            unset($target_user->name); 
        }

        // GESTIONE EMAIL PERSONALIZZATA
        if (isset($_POST['show_email'])) {
            $input_email = trim($_POST['custom_email'] ?? '');
            $final_email = ($input_email === '') ? $_SESSION['user_email'] : $input_email;
            
            if (!isset($target_user->email)) $target_user->addChild('email');
            $target_user->email[0] = $final_email;
            $target_user->email['consented_at'] = $now;
        } else { unset($target_user->email); }

        // GESTIONE IMMAGINE PROFILO
        if (isset($_POST['show_picture'])) {
            $pic_path = isset($target_user->image) ? (string)$target_user->image : $_SESSION['user_picture'];
            
            if (isset($_POST['restore_google_photo']) && $_POST['restore_google_photo'] === '1') {
                $pic_path = $_SESSION['user_picture'];
            } elseif (isset($_FILES['custom_picture']) && $_FILES['custom_picture']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = ws_content_root_abspath() . '/media/';
                if (!is_dir($upload_dir)) @mkdir($upload_dir, 0755, true);
                $ext = pathinfo($_FILES['custom_picture']['name'], PATHINFO_EXTENSION);
                $filename = 'avatar_' . substr(md5($user_sub_id . time()), 0, 10) . '.' . strtolower($ext);
                if (move_uploaded_file($_FILES['custom_picture']['tmp_name'], $upload_dir . $filename)) {
                    $pic_path = '/media/' . $filename; 
                }
            }

            if (!isset($target_user->image)) $target_user->addChild('image');
            $target_user->image[0] = $pic_path;
            $target_user->image['consented_at'] = $now;
        } else { unset($target_user->image); }

        // GESTIONE JOB TITLE
        if (isset($_POST['show_person'])) {
            $person = $target_user->person ?? $target_user->addChild('person');
            $job_title_input = trim($_POST['job_title'] ?? '');
            
            if (!empty($job_title_input)) {
                if (!isset($person->jobTitle)) $person->addChild('jobTitle');
                $person->jobTitle[0] = $job_title_input; 
                $person->jobTitle['consented_at'] = $now;
            } else { unset($person->jobTitle); }
        } else {
            if (isset($target_user->person->jobTitle)) unset($target_user->person->jobTitle);
        }

        // GESTIONE GENERE (Mostra il tuo Genere)
        if (isset($_POST['show_gender'])) {
            $person = $target_user->person ?? $target_user->addChild('person');
            $gender_text = trim($_POST['gender_text'] ?? '');
            $gender_id   = trim($_POST['gender_id'] ?? '');
            
            if (!empty($gender_text)) {
                if (!isset($person->gender)) $person->addChild('gender');
                $person->gender[0] = $gender_text;
                $person->gender['consented_at'] = $now;
                
                // Associazione ID univoco
                if ($gender_id !== '') $person->gender['id'] = $gender_id;
                else unset($person->gender['id']);
            } else { unset($person->gender); }
        } else {
            if (isset($target_user->person->gender)) unset($target_user->person->gender);
        }

        // GESTIONE ORGANIZZAZIONE
        $existing_logo = isset($target_user->person->worksFor->organization->logo) ? (string)$target_user->person->worksFor->organization->logo : '';

        if (isset($_POST['show_org']) && !empty(trim($_POST['org_name']))) {
            $person = $target_user->person ?? $target_user->addChild('person');
            $worksFor = $person->worksFor ?? $person->addChild('worksFor');
            $worksFor['type'] = 'Organization';
            $org = $worksFor->organization ?? $worksFor->addChild('organization');
            
            // Nome Organizzazione
            if (!isset($org->name)) $org->addChild('name');
            $org->name[0] = trim($_POST['org_name']); 
            $org->name['consented_at'] = $now;
            
            // Elaborazione Upload Logo
            $logo_path = $existing_logo; 
            
            if (isset($_FILES['org_logo']) && $_FILES['org_logo']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = ws_content_root_abspath() . '/media/';
                if (!is_dir($upload_dir)) @mkdir($upload_dir, 0755, true);
                $ext = pathinfo($_FILES['org_logo']['name'], PATHINFO_EXTENSION);
                $filename = 'logo_' . substr(md5($user_sub_id . time()), 0, 10) . '.' . strtolower($ext);
                if (move_uploaded_file($_FILES['org_logo']['tmp_name'], $upload_dir . $filename)) {
                    $logo_path = '/media/' . $filename; 
                }
            }
            
            if ($logo_path !== '') {
                if (!isset($org->logo)) $org->addChild('logo');
                $org->logo[0] = $logo_path; 
                $org->logo['consented_at'] = $now;
            } else { unset($org->logo); }

        } else {
            if (isset($target_user->person->worksFor->organization)) unset($target_user->person->worksFor->organization);
            if (isset($target_user->person->worksFor) && $target_user->person->worksFor->count() === 0) unset($target_user->person->worksFor);
        }
        
        // Pulizia igienica
        if (isset($target_user->person) && $target_user->person->count() === 0) unset($target_user->person);

        $dom = new DOMDocument("1.0");
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());
        $dom->save($XML_FILE_PATH);

        /* Dopo aver salvato: a pagina intera si esce (come prima), dentro un
         * riquadro si RESTA — un modale che si svuota e ti manda agli eventi non
         * dice «salvato», dice «è successo qualcosa». */
        if ($embed) {
            $salvato = true;
        } else {
            header('Location: ' . $ws_radice . '/eventi');
            exit;
        }
    }
}

// ============================================================================
// LETTURA DELLO STATO ATTUALE
// ============================================================================
$current_xml_user = null;
if (file_exists($XML_FILE_PATH)) {
    $xml = simplexml_load_file($XML_FILE_PATH);
    foreach ($xml->user as $u) {
        if (trim((string)$u['id']) === $user_sub_id) { $current_xml_user = $u; break; }
    }
}

$is_registered         = ($current_xml_user !== null);
$has_consented_name    = isset($current_xml_user->name['consented_at']);
$has_consented_email   = isset($current_xml_user->email['consented_at']);
$has_consented_picture = isset($current_xml_user->image['consented_at']);

$current_name_val = $has_consented_name ? (string)$current_xml_user->name : ($_SESSION['user_name'] ?? '');
if ($has_consented_name && $current_name_val === 'Utente Anonimo') $current_name_val = '';

$current_email_val = $has_consented_email ? (string)$current_xml_user->email : ($_SESSION['user_email'] ?? '');
$current_pic_val = $has_consented_picture ? (string)$current_xml_user->image : ($_SESSION['user_picture'] ?? '');

$has_consented_person  = false;
$has_consented_gender  = false;
$has_consented_org     = false;

$current_job_title     = '';
$current_gender_text   = '';
$current_gender_id     = '';
$current_org_name      = '';
$current_org_logo      = '';

if ($current_xml_user !== null && isset($current_xml_user->person)) {
    $p = $current_xml_user->person;
    $has_consented_person = isset($p->jobTitle['consented_at']);
    $current_job_title = (string)($p->jobTitle ?? '');
    
    $has_consented_gender = isset($p->gender['consented_at']);
    $current_gender_text = (string)($p->gender ?? '');
    $current_gender_id   = (string)($p->gender['id'] ?? '');
    
    if (isset($p->worksFor->organization)) {
        $org = $p->worksFor->organization;
        $has_consented_org = isset($org->name['consented_at']);
        $current_org_name = (string)($org->name ?? '');
        $current_org_logo = (string)($org->logo ?? '');
    }
}

$current_locale = (string)($current_xml_user->locale ?? $_SESSION['user_locale'] ?? 'it');

// Caricamento JSON dei generi
$genders_json_path = ws_content_root_abspath() . '/users/genders.json';
if (!file_exists($genders_json_path)) {
    $genders_json_path = __DIR__ . '/genders.json'; 
}
$genders_json_content = file_exists($genders_json_path) ? file_get_contents($genders_json_path) : '{"categories":[]}';
?>
<?php if (!$embed): ?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(substr($current_locale, 0, 2)) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione Profilo e Privacy</title>
<?php endif; ?>
<?php
/* GLI STILI NON DEVONO USCIRE DAL RIQUADRO.
 *
 * Incorporata, questa pagina viene inserita con `innerHTML` — e un `<style>`
 * messo così SI APPLICA A TUTTA LA PAGINA che lo ospita. Le regole qui sotto si
 * chiamano `.card`, `.btn`, `body`, `:root`: nomi che nel tema di Meetoo
 * esistono già e vogliono dire altro. Il risultato era il modulo illeggibile e
 * la pagina dietro spaginata — le schede del sito ridisegnate dai bottoni di un
 * modulo di privacy.
 *
 * Quindi: incorporata, ogni regola vive sotto `#mt-profilo-corpo`, e `:root` e
 * `body` non si scrivono affatto (non ci sono, lì dentro: c'è il riquadro).
 * A pagina intera resta tutto com'era. */
$q = $embed ? '#mt-profilo-corpo ' : '';

/* E NON DEVE CHIAMARSI COME UNA COSA CHE C'È GIÀ.
 *
 * Scrivere le regole sotto `#mt-profilo-corpo` impedisce a questa pagina di
 * uscire; non impedisce a Meetoo di entrare. Là `.card` è la scheda di un
 * evento — `display:flex`, `align-items:stretch`, `overflow:hidden` — e il
 * modulo ci finiva dentro di traverso: titolo in una colonna, riquadro dell'ID
 * in un'altra, il resto tagliato via. Nel riquadro la scheda ha un nome suo.
 * A pagina intera resta `card`, che è il nome giusto a casa sua. */
$scheda = $embed ? 'scheda-profilo' : 'card';
?>
    <style>
<?php if (!$embed): ?>
        :root { font-family: system-ui, -apple-system, sans-serif; line-height: 1.5; color: var(--color-text, #202124); }
        body { max-width: 680px; margin: 3rem auto; padding: 0 1.5rem; background: var(--color-background, #f8f9fa); }
        <?= $q ?>.<?= $scheda ?> { background: var(--color-background-section1, #fff); border-radius: var(--border-radius, 16px); padding: 2rem; border: 1px solid var(--color-line, #dadce0); }
<?php else: ?>
        /* Nel riquadro la scheda non ha bisogno di un secondo bordo: ce l'ha già
           il modale, e il testo eredita da lì tipo, dimensione e colore. */
        <?= $q ?>.<?= $scheda ?> { display: block; background: transparent; border: none; padding: 0; }
<?php endif; ?>
        <?= $q ?>h1 { font-size: <?= $embed ? '1.125rem' : '1.5rem' ?>; margin-top: 0; color: var(--color-link, #1a73e8); }
        <?= $q ?>.id-box { background: var(--color-background-section2, #e8f0fe); border-radius: 12px; padding: 1.2rem; margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
        <?= $q ?>.uuid-badge { font-family: monospace; background: var(--color-background-section1, #fff); padding: 4px 8px; border-radius: 6px; border: 1px solid var(--color-line, #cce3ff); font-size: 13px; color: var(--color-link, #1967d2); overflow-wrap: anywhere; }
        <?= $q ?>.switch-row { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 1.2rem; padding-bottom: 1rem; border-bottom: 1px solid var(--color-line, #f1f3f4); }
        <?= $q ?>.switch-row:last-of-type { border-bottom: none; }
        <?= $q ?>input[type="checkbox"] { width: 18px; height: 18px; accent-color: var(--color-link, #1a73e8); margin-top: 3px; cursor: pointer; }
        <?= $q ?>.switch-label { font-weight: 600; display: block; margin-bottom: 2px; }
        <?= $q ?>.switch-hint { font-size: 0.85rem; color: var(--color-hint, #5f6368); margin: 0; }
        <?= $q ?>.search-input { width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--color-line, #dadce0); background: var(--color-background-section2, #fff); color: var(--color-text, #202124); box-sizing: border-box; font-size: 14px; font-family: inherit; }
        <?= $q ?>.search-input:focus { border-color: var(--color-link, #1a73e8); outline: none; }
        <?= $q ?>.restore-btn { background: var(--color-background-section2, #f1f3f4); border: 1px solid var(--color-line, #dadce0); border-radius: 6px; padding: 6px 10px; cursor: pointer; color: var(--color-hint, #5f6368); display: flex; align-items: center; justify-content: center; }
        <?= $q ?>.restore-btn:hover { color: var(--color-link, #1a73e8); }
        <?= $q ?>.autocomplete-container { position: relative; }
        <?= $q ?>.suggestions-list {
            position: absolute; top: 100%; left: 0; right: 0;
            background: var(--color-background-section1, #fff); border: 1px solid var(--color-line, #ccc); border-radius: 8px;
            max-height: 180px; overflow-y: auto; list-style: none; padding: 4px; margin: 4px 0 0 0;
            z-index: 1000; box-shadow: 0 4px 12px rgba(0,0,0,0.2); display: none;
        }
        <?= $q ?>.suggestions-list li { padding: 8px 12px; cursor: pointer; border-radius: 6px; font-size: 14px; }
        <?= $q ?>.suggestions-list li:hover, <?= $q ?>.autocomplete-active { background: var(--color-background-section2, #e8f0fe); }
        <?= $q ?>.btn { background: var(--color-link, #1a73e8); color: var(--color-background-header, #fff); border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 14px; text-decoration: none; font-family: inherit; }
        <?= $q ?>.btn-outline { background: transparent; color: var(--color-hint, #5f6368); border: 1px solid var(--color-line, #dadce0); margin-left: 8px; }
        <?= $q ?>.input-group { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        <?= $q ?>.salvato { color: var(--color-link, #1a73e8); font-weight: 600; margin: 0 0 1rem; }
    </style>
<?php if (!$embed): ?>
</head>
<body>
<?php endif; ?>
    <div class="<?= $scheda ?>">
<?php if (!empty($salvato)): ?>
        <p class="salvato">Salvato.</p>
<?php endif; ?>
        <h1><?= $is_registered ? 'Modifica Profilo' : 'Registrazione al Portale' ?></h1>
        
        <div class="id-box">
            <div>
                <div style="font-size:12px; font-weight:700; text-transform:uppercase; color:#1967d2;">ID Pseudonimo di Google</div>
                <div style="font-size:13px; color:#5f6368;">Questo codice protegge la tua anagrafica nei database. Lingua rilevata: <strong style="text-transform:uppercase;"><?= htmlspecialchars($current_locale) ?></strong></div>
            </div>
            <div class="uuid-badge"><?= htmlspecialchars($user_sub_id) ?></div>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="save_privacy_settings" value="1">

            <!-- MODIFICA NOME -->
            <div class="switch-row" style="flex-direction:column; gap:8px;">
                <div style="display:flex; gap:12px; align-items:flex-start;">
                    <input type="checkbox" id="cb_name" name="show_name" value="1" <?= $has_consented_name ? 'checked' : '' ?>>
                    <div>
                        <label for="cb_name" class="switch-label">Mostra il tuo <strong>Nome</strong>:</label>
                        <p class="switch-hint">Per mantenere l'anonimato, puoi inserire uno pseudonimo.<br>Se lasci il campo vuoto, risulterai "Utente Anonimo".</p>
                    </div>
                </div>
                <div style="margin-left:30px; width:calc(100% - 30px);">
                    <div class="input-group">
                        <input type="text" id="custom_name" name="custom_name" value="<?= htmlspecialchars($current_name_val) ?>" class="search-input" placeholder="Il tuo nome o pseudonimo">
                        <button type="button" class="restore-btn" onclick="restoreGoogleValue('name')" title="Ripristina nome Google">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6 0 1.01-.25 1.97-.7 2.8l1.46 1.46C19.54 15.03 20 13.57 20 12c0-4.42-3.58-8-8-8zm0 14c-3.31 0-6-2.69-6-6 0-1.01.25-1.97.7-2.8L5.24 7.74C4.46 8.97 4 10.43 4 12c0 4.42 3.58 8 8 8v3l4-4-4-4v3z"/></svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- MODIFICA FOTO -->
            <div class="switch-row" style="flex-direction:column; gap:8px;">
                <div style="display:flex; gap:12px; align-items:flex-start;">
                    <input type="checkbox" id="cb_picture" name="show_picture" value="1" <?= $has_consented_picture ? 'checked' : '' ?>>
                    <div>
                        <label for="cb_picture" class="switch-label">Mostra la tua <strong>Foto profilo</strong></label>
                        <p class="switch-hint">Rendi visibile il tuo avatar. Carica una foto o usa quella di Google.</p>
                    </div>
                </div>
                <div style="margin-left:30px; width:calc(100% - 30px);">
                    <div class="input-group" style="background:#f8f9fa; padding:8px; border:1px solid #dadce0; border-radius:6px;">
                        <img id="current_photo_preview" src="<?= htmlspecialchars($current_pic_val) ?>" style="width:40px; height:40px; border-radius:50%; object-fit:cover; border:1px solid #ccc;">
                        <input type="file" id="custom_picture" name="custom_picture" accept="image/png, image/jpeg, image/webp" style="flex:1; font-size:12px;">
                        <input type="hidden" id="restore_google_photo" name="restore_google_photo" value="0">
                        <button type="button" class="restore-btn" onclick="restoreGoogleValue('photo')" title="Ripristina foto Google">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6 0 1.01-.25 1.97-.7 2.8l1.46 1.46C19.54 15.03 20 13.57 20 12c0-4.42-3.58-8-8-8zm0 14c-3.31 0-6-2.69-6-6 0-1.01.25-1.97.7-2.8L5.24 7.74C4.46 8.97 4 10.43 4 12c0 4.42 3.58 8 8 8v3l4-4-4-4v3z"/></svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- MODIFICA EMAIL -->
            <div class="switch-row" style="flex-direction:column; gap:8px;">
                <div style="display:flex; gap:12px; align-items:flex-start;">
                    <input type="checkbox" id="cb_email" name="show_email" value="1" <?= $has_consented_email ? 'checked' : '' ?>>
                    <div>
                        <label for="cb_email" class="switch-label">Rendi pubblica la tua <strong>Email</strong>:</label>
                        <p class="switch-hint">Consigliato solo se desideri essere contattato per i progetti.</p>
                    </div>
                </div>
                <div style="margin-left:30px; width:calc(100% - 30px);">
                    <div class="input-group">
                        <input type="text" id="custom_email" name="custom_email" value="<?= htmlspecialchars($current_email_val) ?>" class="search-input" placeholder="La tua email pubblica">
                        <button type="button" class="restore-btn" onclick="restoreGoogleValue('email')" title="Ripristina email Google">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6 0 1.01-.25 1.97-.7 2.8l1.46 1.46C19.54 15.03 20 13.57 20 12c0-4.42-3.58-8-8-8zm0 14c-3.31 0-6-2.69-6-6 0-1.01.25-1.97.7-2.8L5.24 7.74C4.46 8.97 4 10.43 4 12c0 4.42 3.58 8 8 8v3l4-4-4-4v3z"/></svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- MODIFICA GENERE -->
            <div class="switch-row" style="flex-direction:column; gap:8px;">
                <div style="display:flex; gap:12px; align-items:flex-start;">
                    <input type="checkbox" id="cb_gender" name="show_gender" value="1" <?= $has_consented_gender ? 'checked' : '' ?>>
                    <div>
                        <label for="cb_gender" class="switch-label">Mostra il tuo <strong>Genere</strong></label>
                        <p class="switch-hint">Dichiara come preferisci che ci si rivolga a te all'interno della community.</p>
                    </div>
                </div>
                <div style="margin-left:30px; width:calc(100% - 30px);" class="autocomplete-container">
                    <input type="text" id="gender_input" name="gender_text" class="search-input" value="<?= htmlspecialchars($current_gender_text) ?>" placeholder="Inizia a digitare..." autocomplete="off">
                    <input type="hidden" id="gender_id" name="gender_id" value="<?= htmlspecialchars($current_gender_id) ?>">
                    <ul id="suggestions_list" class="suggestions-list"></ul>
                </div>
            </div>

            <!-- DATI PROFESSIONALI & ORGANIZZAZIONE -->
            <div class="switch-row" style="flex-direction:column; gap:8px;">
                <div style="display:flex; gap:12px; align-items:flex-start;">
                    <input type="checkbox" id="cb_person" name="show_person" value="1" <?= $has_consented_person ? 'checked' : '' ?>>
                    <div>
                        <label for="cb_person" class="switch-label">Mostra la tua <strong>Professione</strong></label>
                        <p class="switch-hint">Pubblica il tuo ruolo lavorativo e i riferimenti dell'organizzazione.</p>
                    </div>
                </div>
                
                <div style="margin-left:30px; display:flex; flex-direction:column; gap:12px; width:calc(100% - 30px); margin-top:8px;">
                    <div>
                        <label style="font-size: 12px; font-weight: 600; color: #5f6368; display:block; margin-bottom:4px;">Ruolo lavorativo:</label>
                        <input type="text" name="job_title" value="<?= htmlspecialchars($current_job_title) ?>" placeholder="Es. Designer" class="search-input">
                    </div>
                    
                    <!-- Box Organizzazione Inclusa -->
                    <div style="background:#f8f9fa; border:1px solid #dadce0; border-radius:8px; padding:12px;">
                        <h4 style="margin:0 0 10px 0; font-size:13px; color:#1a73e8; text-transform:uppercase;">Organizzazione</h4>
                        
                        <div style="margin-bottom:12px;">
                            <label style="font-size: 12px; font-weight: 600; color: #5f6368; display:block; margin-bottom:4px;">Nome dell'organizzazione:</label>
                            <input type="text" name="org_name" value="<?= htmlspecialchars($current_org_name) ?>" placeholder="Es. ISOTYPE.ORG" class="search-input">
                        </div>
                        
                        <div>
                            <label style="font-size: 12px; font-weight: 600; color: #5f6368; display:block; margin-bottom:4px;">Logo dell'organizzazione:</label>
                            <?php if ($current_org_logo): ?>
                                <div style="display:flex; align-items:center; gap: 10px; margin-bottom:8px; padding: 4px 8px; background: #fff; border: 1px dashed #ccc; border-radius: 6px;">
                                    <img src="<?= htmlspecialchars($current_org_logo) ?>" alt="Current Logo" style="height:24px; object-fit:contain;">
                                    <span style="font-size:11px; color:#666;">Logo in uso. Caricane uno nuovo per sostituirlo.</span>
                                </div>
                            <?php endif; ?>
                            <input type="file" name="org_logo" accept="image/png, image/jpeg, image/svg+xml" style="font-size:13px; color:#5f6368; width: 100%;">
                        </div>
                    </div>
                </div>
            </div>

            <div style="margin-top:2rem; padding-top:1.5rem; border-top:1px solid #dadce0; display:flex; justify-content:flex-end;">
                <a href="https://www.isotype.org/eventi" class="btn btn-outline">Annulla</a>
                <button type="submit" class="btn" style="margin-left:12px;">Salva e Accedi</button>
            </div>
        </form>
    </div>

    <script>
        // --- SCRIPT PER RIPRISTINARE I DATI GOOGLE ---
        function restoreGoogleValue(field) {
            if (field === 'name') {
                document.getElementById('custom_name').value = <?= json_encode($_SESSION['user_name'] ?? '') ?>;
            } else if (field === 'email') {
                document.getElementById('custom_email').value = <?= json_encode($_SESSION['user_email'] ?? '') ?>;
            } else if (field === 'photo') {
                document.getElementById('custom_picture').value = ''; 
                document.getElementById('restore_google_photo').value = '1'; 
                document.getElementById('current_photo_preview').src = <?= json_encode($_SESSION['user_picture'] ?? '') ?>;
            }
        }

        document.getElementById('custom_picture').addEventListener('change', function() {
            document.getElementById('restore_google_photo').value = '0';
        });

        // --- SCRIPT AUTOCOMPLETE PER IL GENERE ---
        document.addEventListener("DOMContentLoaded", function() {
            const input = document.getElementById("gender_input");
            const hiddenId = document.getElementById("gender_id");
            const suggestionsList = document.getElementById("suggestions_list");
            let currentFocusIdx = -1;
            
            // Lettura della lingua del portale dal PHP
            const userLocale = <?= json_encode(substr($current_locale, 0, 2)) ?>;
            
            const data = <?= $genders_json_content ?>;
            const options = [];
            
            if (data.categories) {
                data.categories.forEach(cat => {
                    cat.values.forEach(val => {
                        // Matching testuale in base al locale
                        const text = (val.translations && val.translations[userLocale]) 
                            ? val.translations[userLocale] 
                            : val.english_text;
                            
                        options.push({ id: val.id, text: text });
                    });
                });
            }

            input.addEventListener("input", function() {
                const val = this.value;
                hiddenId.value = ''; // Resetta l'ID nascosto se l'utente digita liberamente
                closeDropdown();
                if (!val) return;
                
                currentFocusIdx = -1;
                const filteredData = options.filter(item => item.text.toLowerCase().includes(val.toLowerCase()));
                
                if (filteredData.length > 0) {
                    suggestionsList.style.display = 'block';
                    filteredData.forEach(item => {
                        const li = document.createElement("li");
                        const regex = new RegExp("(" + val + ")", "gi");
                        li.innerHTML = item.text.replace(regex, "<strong>$1</strong>");
                        
                        li.addEventListener("click", function() {
                            input.value = item.text;
                            hiddenId.value = item.id; // Assegnazione ID
                            closeDropdown();
                        });
                        suggestionsList.appendChild(li);
                    });
                }
            });

            input.addEventListener("keydown", function(e) {
                const items = suggestionsList.getElementsByTagName("li");
                if (e.key === "ArrowDown") {
                    e.preventDefault(); currentFocusIdx++; addActive(items);
                } else if (e.key === "ArrowUp") {
                    e.preventDefault(); currentFocusIdx--; addActive(items);
                } else if (e.key === "Enter") {
                    if (currentFocusIdx > -1 && items.length > 0) { e.preventDefault(); items[currentFocusIdx].click(); }
                } else if (e.key === "Escape") { closeDropdown(); }
            });
            
            function addActive(items) {
                if (!items) return;
                removeActive(items);
                if (currentFocusIdx >= items.length) currentFocusIdx = 0;
                if (currentFocusIdx < 0) currentFocusIdx = items.length - 1;
                items[currentFocusIdx].classList.add("autocomplete-active");
                items[currentFocusIdx].scrollIntoView({ block: "nearest" }); 
            }
            
            function removeActive(items) {
                for (let i = 0; i < items.length; i++) items[i].classList.remove("autocomplete-active");
            }
            
            function closeDropdown() {
                suggestionsList.innerHTML = "";
                suggestionsList.style.display = 'none';
            }
            
            document.addEventListener("click", function(e) {
                if (e.target !== input) closeDropdown();
            });
        });
    </script>
<?php if (!$embed): ?>
</body>
</html>
<?php endif; ?>