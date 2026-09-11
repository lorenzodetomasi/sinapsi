<?php
session_start();

/* EMBEDDED, or a full page.
 *
 * `?embed=1` is for Meetoo's profile panel: it returns ONLY the content — no
 * doctype, no head, no body — so it can go inside a modal instead of inside a
 * page. It changes three things and nothing else: the shell, the answer given
 * to someone who is not signed in (a message instead of a redirect, because
 * inside a modal a redirect cannot be seen), and where you end up after saving
 * (nowhere: you stay, with the confirmation). */
$embed = isset($_GET['embed']) && $_GET['embed'] !== '0';

/* The site root, rather than `https://www.isotype.org` written by hand: this
 * file also runs elsewhere, and a hardcoded address would take it back here. */
$site_root = function_exists('ws_root_url') ? rtrim(ws_root_url(), '/') : '';

// Basic access check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    if ($embed) {
        echo '<p class="mt-prof-vuoto">' . __('Sign in to complete your profile.') . '</p>';
        exit;
    }
    header('Location: ' . $site_root);
    exit;
}

$XML_FILE_PATH = ws_content_root_abspath() . '/users/users.xml';
$user_sub_id = 'sub:' . ($_SESSION['user_sub'] ?? '');

if ($user_sub_id === 'sub:') {
    die(__('Session error: the Sub ID is missing. Sign out and sign in again.'));
}

// ============================================================================
// FORM POST: writes the preferences into the XML, and the uploaded files
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

        // 1. Name and pseudonym
        if (isset($_POST['is_name_public'])) {
            $input_name = trim($_POST['name'] ?? '');
            $final_name = ($input_name === '') ? 'Utente Anonimo' : $input_name;
            
            if (!isset($target_user->name)) $target_user->addChild('name');
            $target_user->name[0] = $final_name;
            $target_user->name['consented_at'] = $now;
        } else { unset($target_user->name); }

        // 2. Custom email
        if (isset($_POST['is_email_public'])) {
            $input_email = trim($_POST['email'] ?? '');
            $final_email = ($input_email === '') ? ($_SESSION['user_email'] ?? '') : $input_email;
            
            if (!isset($target_user->email)) $target_user->addChild('email');
            $target_user->email[0] = $final_email;
            $target_user->email['consented_at'] = $now;
        } else { unset($target_user->email); }

        // 3. Description 
        if (isset($_POST['is_description_public'])) {
            $input_description = trim($_POST['description'] ?? '');
            if ($input_description !== '') {
                if (!isset($target_user->description)) $target_user->addChild('description');
                $target_user->description[0] = $input_description;
                $target_user->description['consented_at'] = $now;
            } else { unset($target_user->description); }
        } else { unset($target_user->description); }

        // 4. Profile picture
        if (isset($_POST['is_image_public'])) {
            $pic_path = isset($target_user->image) ? (string)$target_user->image : ($_SESSION['user_picture'] ?? '');
            
            if (isset($_POST['restore_google_photo']) && $_POST['restore_google_photo'] === '1') {
                $pic_path = $_SESSION['user_picture'] ?? '';
            } elseif (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = ws_content_root_abspath() . '/media/';
                if (!is_dir($upload_dir)) @mkdir($upload_dir, 0755, true);
                $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $filename = 'avatar_' . substr(md5($user_sub_id . time()), 0, 10) . '.' . strtolower($ext);
                if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $filename)) {
                    $pic_path = '/media/' . $filename; 
                }
            }

            if (!isset($target_user->image)) $target_user->addChild('image');
            $target_user->image[0] = $pic_path;
            $target_user->image['consented_at'] = $now;
        } else { unset($target_user->image); }

        // 5. Gender
        if (isset($_POST['is_gender_public'])) {
            $person = $target_user->person ?? $target_user->addChild('person');
            $gender_text = trim($_POST['GenderType'] ?? '');
            
            if (!empty($gender_text)) {
                if (!isset($person->gender)) $person->addChild('gender');
                $person->gender[0] = $gender_text;
                $person->gender['consented_at'] = $now;
                unset($person->gender['id']); // Non usiamo più ID JSON per il datalist nativo
            } else { unset($person->gender); }
        } else {
            if (isset($target_user->person->gender)) unset($target_user->person->gender);
        }

        // 6. Organization Role
        if (isset($_POST['is_role_public'])) {
            $person = $target_user->person ?? $target_user->addChild('person');
            $organizationRole_input = trim($_POST['organizationRole'] ?? '');
            
            if (!empty($organizationRole_input)) {
                if (!isset($person->jobTitle)) $person->addChild('jobTitle');
                $person->jobTitle[0] = $organizationRole_input; 
                $person->jobTitle['consented_at'] = $now;
            } else { unset($person->jobTitle); }
        } else {
            if (isset($target_user->person->jobTitle)) unset($target_user->person->jobTitle);
        }

        // 7. Organization Name & Logo
        $existing_logo = isset($target_user->person->worksFor->organization->logo) ? (string)$target_user->person->worksFor->organization->logo : '';

        if (isset($_POST['is_organizationName_public']) && !empty(trim($_POST['organizationName'] ?? ''))) {
            $person = $target_user->person ?? $target_user->addChild('person');
            $worksFor = $person->worksFor ?? $person->addChild('worksFor');
            $worksFor['type'] = 'Organization';
            $org = $worksFor->organization ?? $worksFor->addChild('organization');
            
            // Organization name
            if (!isset($org->name)) $org->addChild('name');
            $org->name[0] = trim($_POST['organizationName']); 
            $org->name['consented_at'] = $now;
            
            // Logo upload
            $logo_path = $existing_logo; 
            
            if (isset($_FILES['organizationLogo']) && $_FILES['organizationLogo']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = ws_content_root_abspath() . '/media/';
                if (!is_dir($upload_dir)) @mkdir($upload_dir, 0755, true);
                $ext = pathinfo($_FILES['organizationLogo']['name'], PATHINFO_EXTENSION);
                $filename = 'logo_' . substr(md5($user_sub_id . time()), 0, 10) . '.' . strtolower($ext);
                if (move_uploaded_file($_FILES['organizationLogo']['tmp_name'], $upload_dir . $filename)) {
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
        
        // Housekeeping
        if (isset($target_user->person) && $target_user->person->count() === 0) unset($target_user->person);

        $dom = new DOMDocument("1.0");
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());
        $dom->save($XML_FILE_PATH);

        if ($embed) {
            $saved = true;
        } else {
            header('Location: ' . $site_root);
            exit;
        }
    }
}

// ============================================================================
// READING THE CURRENT STATE
// ============================================================================
$current_xml_user = null;
if (file_exists($XML_FILE_PATH)) {
    $xml = simplexml_load_file($XML_FILE_PATH);
    foreach ($xml->user as $u) {
        if (trim((string)$u['id']) === $user_sub_id) { $current_xml_user = $u; break; }
    }
}

$is_registered         = ($current_xml_user !== null);
$is_name_public        = isset($current_xml_user->name['consented_at']);
$is_email_public       = isset($current_xml_user->email['consented_at']);
$is_image_public       = isset($current_xml_user->image['consented_at']);
$is_description_public = isset($current_xml_user->description['consented_at']);

$current_name_val  = $is_name_public ? (string)$current_xml_user->name : ($_SESSION['user_name'] ?? '');
if ($is_name_public && $current_name_val === 'Utente Anonimo') $current_name_val = '';
$current_email_val = $is_email_public ? (string)$current_xml_user->email : ($_SESSION['user_email'] ?? '');
$current_pic_val   = $is_image_public ? (string)$current_xml_user->image : ($_SESSION['user_picture'] ?? '');
$current_description = $is_description_public ? (string)$current_xml_user->description : '';

// Values related to the <person> node
$is_role_public             = false;
$is_gender_public           = false;
$is_organizationName_public = false;

$current_organizationRole  = '';
$current_gender_text       = '';
$current_organizationName  = '';
$current_org_logo          = '';

if ($current_xml_user !== null && isset($current_xml_user->person)) {
    $p = $current_xml_user->person;
    
    $is_role_public = isset($p->jobTitle['consented_at']);
    $current_organizationRole = (string)($p->jobTitle ?? '');
    
    $is_gender_public = isset($p->gender['consented_at']);
    $current_gender_text = (string)($p->gender ?? '');
    
    if (isset($p->worksFor->organization)) {
        $org = $p->worksFor->organization;
        $is_organizationName_public = isset($org->name['consented_at']);
        $current_organizationName = (string)($org->name ?? '');
        $current_org_logo = (string)($org->logo ?? '');
    }
}
?>
<?php if (!$embed): ?>
<?php
/* AS A FULL PAGE THIS IS A PAGE OF THE SITE, not a sheet of its own. */
$GLOBALS['ws_html_attributes']['html']['class'][] = 'page';
include_template('template-parts/header');
?>
<div<?php echo ws_html_attributes('main-content'); ?>>
  <div class="content-container">
<?php endif; ?>
    <style>        <?= $q ?>.id-box { background: var(--color-background-section2, #e8f0fe); border-radius: 12px; padding: 1.2rem; margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
        <?= $q ?>.uuid-badge { font-family: monospace; overflow-wrap: anywhere; }
        <?= $q ?>.switch-row { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 1.2rem; padding-bottom: 1rem; border-bottom: 1px solid var(--color-line, #f1f3f4); }
        <?= $q ?>.switch-row:last-of-type { border-bottom: none; }
        <?= $q ?>input[type="checkbox"] { width: 18px; height: 18px; accent-color: var(--color-link, #1a73e8); margin-top: 3px; cursor: pointer; }
        <?= $q ?>.switch-label { font-weight: 600; display: block; margin-bottom: 2px; }
        <?= $q ?>.switch-hint { font-size: 0.85rem; color: var(--color-hint, #5f6368); margin: 0; }
        <?= $q ?>.search-input { width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--color-line, #dadce0); background: var(--color-background-section2, #fff); color: var(--color-text, #202124); box-sizing: border-box; font-size: 14px; font-family: inherit; }
        <?= $q ?>.search-input:focus { border-color: var(--color-link, #1a73e8); outline: none; }
        <?= $q ?>.restore-btn { background: var(--color-background-section2, #f1f3f4); border: 1px solid var(--color-line, #dadce0); border-radius: 6px; padding: 6px 10px; cursor: pointer; color: var(--color-hint, #5f6368); display: flex; align-items: center; justify-content: center; }
        <?= $q ?>.restore-btn:hover { color: var(--color-link, #1a73e8); }
        <?= $q ?>.btn { background: var(--color-link, #1a73e8); color: var(--color-background-header, #fff); border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 14px; text-decoration: none; font-family: inherit; }
        <?= $q ?>.btn-outline { background: transparent; color: var(--color-hint, #5f6368); border: 1px solid var(--color-line, #dadce0); margin-left: 8px; }
        <?= $q ?>.input-group { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        <?= $q ?>.salvato { color: var(--color-link, #1a73e8); font-weight: 600; margin: 0 0 1rem; }
    </style>
    <script defer="defer" src="https://isotype.org/ws-custom/plugins/forms/js/fields.js"></script>
<?php if (!empty($saved)): ?>
    <p class="salvato"><?php _e('Saved.'); ?></p>
<?php endif; ?>
    <h1><?= $is_registered ? __('Edit profile') : __('Register') ?></h1>
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="save_privacy_settings" value="1">

        <!-- Edit Name -->
        <p class="question">
            <label class="field vertical width-full">
                <strong><?php _e('Name'); ?></strong><br />
                <small><?php _e('To stay anonymous you can use a pseudonym.'); ?> <br /><?php _e('Leave it empty and you will appear as “Anonymous user”.'); ?></small><br />
                <span class="input">
                    <input type="text" id="name" name="name" value="<?= htmlspecialchars($current_name_val) ?>" class="search-input" placeholder="<?php _e('Your name or pseudonym'); ?>" />
                    <button type="button" class="material-symbols-outlined restore-btn" onclick="restoreGoogleValue('name')" title="<?php _e('Restore Name from Google'); ?>">cloud_download</button>
                    <!-- Hidden real checkbox for form submission -->
                    <input type="checkbox" name="is_name_public" value="1" <?= !empty($is_name_public) ? 'checked' : '' ?> style="display: none;" />
                    <!-- Visual Material Icon Toggle -->
                    <button type="button" 
                            title="<?= !empty($is_name_public) ? 'Your Name is public' : 'Your Name is hidden' ?>" 
                            class="material-symbols-outlined icon-toggle" 
                            data-icon-toggle="'visibility', 'Your Name is public' : 'visibility_off', 'Your Name is hidden'">
                        <?= !empty($is_name_public) ? 'visibility' : 'visibility_off' ?>
                    </button>
                </span>
            </label>
            <small class="hint">
                <?php _e('Google pseudonymous ID'); ?>: <span class="uuid-badge"><?= htmlspecialchars($user_sub_id) ?></span><br />
                <?php _e('This code protects your privacy in our databases.'); ?>
            </small>
        </p>
        
        <!-- Edit Email -->
        <p class="question">
            <label class="field vertical width-full">
                <strong><?php _e('Public Email'); ?></strong><br />
                <small><?php _e('Display this only if you wish to be contacted directly.'); ?></small><br />
                <span class="input">
                    <input type="email" id="custom_email" name="email" value="<?= htmlspecialchars($current_email_val) ?>" class="search-input" placeholder="<?php _e('Your public email'); ?>" />
                    <button type="button" class="material-symbols-outlined restore-btn" onclick="restoreGoogleValue('email')" title="<?php _e('Restore Email from Google'); ?>">cloud_download</button>
                    <input type="checkbox" name="is_email_public" value="1" <?= !empty($is_email_public) ? 'checked' : '' ?> style="display: none;" />
                    <button type="button" 
                            title="<?= !empty($is_email_public) ? 'Your Email is public' : 'Your Email is hidden' ?>" 
                            class="material-symbols-outlined icon-toggle" 
                            data-icon-toggle="'visibility', 'Your Email is public' : 'visibility_off', 'Your Email is hidden'">
                        <?= !empty($is_email_public) ? 'visibility' : 'visibility_off' ?>
                    </button>
                </span>
            </label>
        </p>

        <!-- Edit Gender -->
        <p class="question">
            <label class="field vertical width-full">
                <strong><?php _e('Gender'); ?></strong><br />
                <small class="hint"><?php _e('State how you prefer to be addressed within the community.'); ?></small><br />
                <span class="input">
                    <input type="search" name="GenderType" list="genders" class="search-input" value="<?= htmlspecialchars($current_gender_text) ?>" />
                    
                    <input type="checkbox" name="is_gender_public" value="1" <?= !empty($is_gender_public) ? 'checked' : '' ?> style="display: none;" />
                    <button type="button" 
                            title="<?= !empty($is_gender_public) ? 'Your Gender is public' : 'Your Gender is hidden' ?>" 
                            class="material-symbols-outlined icon-toggle" 
                            data-icon-toggle="'visibility', 'Your Gender is public' : 'visibility_off', 'Your Gender is hidden'">
                        <?= !empty($is_gender_public) ? 'visibility' : 'visibility_off' ?>
                    </button>
                    
                    <!-- Native HTML5 Datalist -->
                    <datalist id="genders">
                      <option value="Not specified"></option>
                      <option value="Female"></option>
                      <option value="Male"></option>
                      <option value="Other"></option>
                    </datalist>
                </span>
            </label>
        </p>

        <!-- Edit Description -->
        <p class="question">
            <label class="field textarea vertical width-full">
                <strong><?php _e('Description'); ?></strong><br />
                <small><?php _e('Your short bio.'); ?></small><br />
                <span class="input">
                    <textarea name="description" class="search-input"><?= htmlspecialchars($current_description) ?></textarea>
                    
                    <input type="checkbox" name="is_description_public" value="1" <?= !empty($is_description_public) ? 'checked' : '' ?> style="display: none;" />
                    <button type="button" 
                            title="<?= !empty($is_description_public) ? 'Your Description is public' : 'Your Description is hidden' ?>" 
                            class="material-symbols-outlined icon-toggle" 
                            data-icon-toggle="'visibility', 'Your Description is public' : 'visibility_off', 'Your Description is hidden'">
                        <?= !empty($is_description_public) ? 'visibility' : 'visibility_off' ?>
                    </button>
                </span>
            </label>
        </p>

        <!-- Occupation Fieldset -->
        <fieldset class="fieldset">
            <legend><?php _e('Occupation'); ?></legend>
            <p>
                <label class="field vertical width-full">
                    <strong><?php _e('Organization'); ?></strong><br />
                    <span class="input">
                        <input type="text" name="organizationName" class="search-input" value="<?= htmlspecialchars($current_organizationName) ?>" placeholder="<?php _e('e.g. ISOTYPE.ORG'); ?>" />
                        
                        <input type="checkbox" name="is_organizationName_public" value="1" <?= !empty($is_organizationName_public) ? 'checked' : '' ?> style="display: none;" />
                        <button type="button" 
                                title="<?= !empty($is_organizationName_public) ? 'Your Organization Name is public' : 'Your Organization Name is hidden' ?>" 
                                class="material-symbols-outlined icon-toggle" 
                                data-icon-toggle="'visibility', 'Your Organization Name is public' : 'visibility_off', 'Your Organization Name is hidden'">
                            <?= !empty($is_organizationName_public) ? 'visibility' : 'visibility_off' ?>
                        </button>
                    </span>
                </label>
            </p>
            <p>
                <label class="field vertical width-full">
                    <strong><?php _e('Organization Logo'); ?></strong><br />
                    <?php if ($current_org_logo): ?>
                        <img src="<?= htmlspecialchars($current_org_logo) ?>" alt="<?php _e('Current logo'); ?>" style="height:24px; object-fit:contain; margin-bottom:8px;">
                    <?php endif; ?>
                    <input type="file" name="organizationLogo" accept="image/png, image/jpeg, image/svg+xml" />
                </label>
            </p>
            <p class="question">
                <label class="field vertical width-full">
                    <strong><?php _e('Role'); ?></strong><br />
                    <small class="hint"><?php _e('Publish your job title and your organization details.'); ?></small><br />
                    <span class="input">
                        <input type="text" name="organizationRole" class="search-input" value="<?= htmlspecialchars($current_organizationRole) ?>" placeholder="<?php _e('e.g. Designer'); ?>" />
                        
                        <input type="checkbox" name="is_role_public" value="1" <?= !empty($is_role_public) ? 'checked' : '' ?> style="display: none;" />
                        <button type="button" 
                                title="<?= !empty($is_role_public) ? 'Your Role is public' : 'Your Role is hidden' ?>" 
                                class="material-symbols-outlined icon-toggle" 
                                data-icon-toggle="'visibility', 'Your Role is public' : 'visibility_off', 'Your Role is hidden'">
                            <?= !empty($is_role_public) ? 'visibility' : 'visibility_off' ?>
                        </button>
                    </span>
                </label>
            </p>
        </fieldset>

        <!-- Edit Profile Photo -->
        <p class="question">
            <label class="field file profile-photo vertical width-full">
                <strong><?php _e('Profile photo'); ?></strong><br />
                <small class="hint"><?php _e('Show your avatar. Upload a photo or use the one from Google.'); ?></small><br />
                <span class="input">
                    <img id="current_photo_preview" src="<?= htmlspecialchars($current_pic_val) ?>" style="width:40px; height:40px; border-radius:50%; object-fit:cover; border:1px solid #ccc; margin-right:8px; vertical-align:middle;">
                    
                    <input type="file" name="image" id="image" accept="image/png, image/jpeg, image/webp" />
                    <input type="hidden" name="restore_google_photo" id="restore_google_photo" value="0" />
                    
                    <button type="button" class="material-symbols-outlined restore-btn" onclick="restoreGoogleValue('photo')" title="<?php _e('Restore Profile photo from Google'); ?>">cloud_download</button>
                    
                    <!-- Fixed specific role mismatch here -->
                    <input type="checkbox" name="is_image_public" value="1" <?= !empty($is_image_public) ? 'checked' : '' ?> style="display: none;" />
                    <button type="button" 
                            title="<?= !empty($is_image_public) ? 'Your Photo is public' : 'Your Photo is hidden' ?>" 
                            class="material-symbols-outlined icon-toggle" 
                            data-icon-toggle="'visibility', 'Your Photo is public' : 'visibility_off', 'Your Photo is hidden'">
                        <?= !empty($is_image_public) ? 'visibility' : 'visibility_off' ?>
                    </button>
                </span>
            </label>
        </p>

        <a href="https://www.isotype.org/eventi" class="btn btn-outline"><?php _e('Cancel'); ?></a>
        <button type="submit" class="btn" style="margin-left:12px;"><?php _e('Save and continue'); ?></button>
    </form>

    <script>
        // --- Restoring the data Google gave us ---
        function restoreGoogleValue(field) {
            if (field === 'name') {
                const nameInput = document.getElementById('name');
                if (nameInput) nameInput.value = <?= json_encode($_SESSION['user_name'] ?? '') ?>;
            } else if (field === 'email') {
                const emailInput = document.getElementById('custom_email');
                if (emailInput) emailInput.value = <?= json_encode($_SESSION['user_email'] ?? '') ?>;
            } else if (field === 'photo') {
                const imageInput = document.getElementById('image');
                const restoreInput = document.getElementById('restore_google_photo');
                const previewImg = document.getElementById('current_photo_preview');
                
                if (imageInput) imageInput.value = ''; 
                if (restoreInput) restoreInput.value = '1'; 
                if (previewImg) previewImg.src = <?= json_encode($_SESSION['user_picture'] ?? '') ?>;
            }
        }

        // Live preview for profile picture when a file is selected
        document.addEventListener('DOMContentLoaded', function() {
            const imageInput = document.getElementById('image');
            const restoreInput = document.getElementById('restore_google_photo');
            const previewImg = document.getElementById('current_photo_preview');

            if (imageInput && previewImg) {
                imageInput.addEventListener('change', function() {
                    if (restoreInput) restoreInput.value = '0';
                    
                    const file = this.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            previewImg.src = e.target.result;
                        }
                        reader.readAsDataURL(file);
                    }
                });
            }
        });
    </script>
<?php if (!$embed): ?>
  </div>
</div>
<?php include_template('template-parts/footer'); ?>
<?php endif; ?>