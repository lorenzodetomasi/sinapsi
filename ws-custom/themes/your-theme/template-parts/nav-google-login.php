<?php
// Google Login Nav
// 1. Acquisizione Dati
$google_session = GoogleAuth::getSession();       
$xml_user       = GoogleAuth::getRegisteredUser(); 

$is_google_user     = ($google_session !== null);
$is_registered_user = ($xml_user !== null);

$display_locale = $xml_user->locale ?? $google_session->locale ?? 'it';
$display_role   = $xml_user->role ?? 'User';

if ($is_registered_user && isset($xml_user->person)) {
    $person = $xml_user->person;
    
    $portal_name  = get_consented_data($person->name, $anon_handle);
    $portal_email = get_consented_data($xml_user->email, null); // Email resta fuori da person in XML
    $portal_image = get_consented_data($xml_user->image, null);
    
    // Accesso a worksFor -> organization
    $portal_org_name = get_consented_data($person->worksFor->organization->name, null);
    $portal_org_logo = get_consented_data($person->worksFor->organization->logo, null);
}
?>
<?php if (!$is_google_user): ?>
    <div id="g_id_onload" data-client_id="<?= GoogleAuth::getClientId() ?>" data-context="signin" data-ux_mode="popup" data-callback="handleGoogleLogin" data-auto_prompt="false"></div>
<?php endif; ?>

<ul class="google-avatar">
    <?php if (!$is_google_user): ?>
        <li class="google-login-trigger" title="Clicca per accedere">
            <div class="g_id_signin" data-type="icon" data-shape="circle" data-theme="outline" data-size="large"></div>
        </li>
    <?php else: ?>
        <li class="avatar-wrapper">
            <img src="<?= htmlspecialchars($google_session->picture) ?>" class="avatar-circle logged-in" onclick="toggleGoogleProfileCard(event)">
            
            <div id="google-profile-card" class="profile-popup hidden">
                <div class="popup-user-info">
                    <img src="<?= htmlspecialchars($google_session->picture) ?>" class="popup-big-avatar" alt="">
                    <div class="popup-name"><?= htmlspecialchars($google_session->name) ?></div>
                    <div class="popup-email"><?= htmlspecialchars($google_session->email) ?></div>
                    
                    <div class="popup-meta">
                        <span class="meta-pill role-pill"><?= htmlspecialchars($display_role) ?></span>
                        <span class="meta-pill lang-pill" title="Lingua Profilo"><?= htmlspecialchars($display_locale) ?></span>
                    </div>
                </div>
                <div class="popup-actions">
                    <?php if ($is_registered_user): ?>
                        <a href="https://www.isotype.org/profilo-utente" class="google-action-btn edit-btn">✏️ Modifica Profilo</a>
                    <?php else: ?>
                        <a href="https://www.isotype.org/profilo-utente?init=register" class="google-action-btn register-btn">➕ Registrati</a>
                    <?php endif; ?>
                    <a href="?logout=1" class="google-logout-btn">Esci</a>
                </div>
            </div>
        </li>
    <?php endif; ?>
</ul>
