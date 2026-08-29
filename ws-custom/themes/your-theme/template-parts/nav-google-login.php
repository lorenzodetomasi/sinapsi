<?php
// Google Login Nav
// 1. Acquisizione Dati
$google_session = GoogleAuth::getSession();       
$xml_user       = GoogleAuth::getRegisteredUser(); 

$is_google_user     = ($google_session !== null);
$is_registered_user = ($xml_user !== null);

$display_locale = $xml_user->locale ?? $google_session->locale ?? 'it';
$display_role   = $xml_user->role ?? 'User';

/* Come si chiama chi non ha acconsentito a farsi chiamare per nome.
 *
 * `get_consented_data()` ritorna il valore solo se il consenso c'è, se no questo
 * ripiego — e va bene che sia un'etichetta neutra, perché è esattamente il caso
 * in cui il nome vero non si può mostrare. Non era definito da nessuna parte:
 * su PHP 8 un «Undefined variable» in cima alla pagina, a ogni accesso di un
 * utente registrato. Serve anche a `page-protected.php`, che da `$portal_name`
 * ricava le iniziali quando manca la foto: una stringa vuota darebbe un
 * dischetto senza lettere. */
$anon_handle = function_exists('__') ? __('Utente') : 'Utente';

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
            <div id="g_id_signin_btn" class="g_id_signin" data-type="icon" data-shape="circle" data-theme="outline" data-size="large"></div>
        </li>
        <script>
        /* Il pulsante di Google segue il tema della pagina.
         *
         * Il disegno lo fa Google, non noi, e l'unico modo per dirgli com'è
         * vestita la pagina è `theme`: `outline` sul chiaro, `filled_black` sullo
         * scuro — un pulsante bianco su fondo nero e' l'unica cosa che si vede,
         * e si vede male. L'attributo si mette PRIMA che la libreria arrivi, cosi'
         * il primo disegno e' gia' quello giusto; dopo, se il tema cambia, si
         * ridisegna.
         *
         * Come si sa qual e' il tema: l'attributo `data-theme` sulla radice quando
         * qualcuno ha scelto, la preferenza di sistema quando non ha scelto. Sono
         * le stesse due cose che guardano i colori. */
        (function () {
            var bottone = function () { return document.getElementById('g_id_signin_btn'); };
            var scuro = function () {
                var scelto = document.documentElement.getAttribute('data-theme');
                if (scelto === 'dark') { return true; }
                if (scelto === 'light') { return false; }
                return !!(window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
            };
            var tema = function () { return scuro() ? 'filled_black' : 'outline'; };
            var el = bottone();
            if (el) { el.setAttribute('data-theme', tema()); }

            /* Ridisegnare vuol dire RIFARE IL POSTO, non svuotarlo.
             *
             * Chiedendo a Google di disegnare una seconda volta dentro l'elemento
             * dove ha gia' disegnato, cambia modo: invece del pulsante nella
             * pagina mette un iframe di accounts.google.com, che e' un'altra
             * origine — la nostra riga di CSS sul fondo non lo raggiunge piu', e
             * quello che si vede resta vestito come dice Google. Su un elemento
             * NUOVO ricomincia da capo e il pulsante torna nella pagina. Verificato
             * dal vivo: stesso nodo → iframe, nodo nuovo → pulsante.
             *
             * Gli attributi si copiano da quello vecchio, cosi' com'e' scritto qui
             * sopra resta l'unico posto dove il pulsante e' descritto. */
            function ridisegna() {
                var vecchio = bottone();
                var gis = window.google && google.accounts && google.accounts.id;
                if (!vecchio) { return; }
                // Senza la libreria, o prima del primo disegno, l'attributo basta:
                // al disegno ci pensera' lei, e lo leggera' di li'.
                if (!gis || !vecchio.firstChild) { vecchio.setAttribute('data-theme', tema()); return; }
                var nuovo = document.createElement('div');
                nuovo.id = vecchio.id;
                nuovo.className = vecchio.className;
                for (var i = 0; i < vecchio.attributes.length; i++) {
                    var a = vecchio.attributes[i];
                    if (a.name.indexOf('data-') === 0) { nuovo.setAttribute(a.name, a.value); }
                }
                nuovo.setAttribute('data-theme', tema());
                vecchio.parentNode.replaceChild(nuovo, vecchio);
                gis.renderButton(nuovo, { type: 'icon', shape: 'circle', size: 'large', theme: tema() });
            }

            // Il tema cambia in due modi: qualcuno lo sceglie (l'header lo annuncia)
            // oppure cambia quello di sistema, mentre la pagina e' aperta.
            document.addEventListener('meetoo:theme', ridisegna);
            if (window.matchMedia) {
                var q = window.matchMedia('(prefers-color-scheme: dark)');
                if (q.addEventListener) { q.addEventListener('change', ridisegna); }
                else if (q.addListener) { q.addListener(ridisegna); }
            }
        })();
        </script>
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
