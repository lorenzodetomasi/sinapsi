<?php
// Google Login Nav
/* Chi sta guardando: lo dice `google_login_profilo()` (functions.php del tema),
 * che è lo stesso posto da cui lo chiede la pagina protetta. Il controllo serve
 * a un caso solo: i file del tema che arrivano sul server in momenti diversi —
 * questo nuovo e `functions.php` ancora vecchio. È già successo, e allora la
 * pagina resta senza accesso invece di non esserci proprio. */
$profilo = function_exists('google_login_profilo') ? google_login_profilo() : array(
    'sessione' => null, 'utente' => null, 'collegato' => false, 'registrato' => false,
    'locale' => 'it', 'role' => 'User', 'anon' => 'Utente', 'name' => 'Utente',
    'email' => null, 'image' => null, 'org_name' => null, 'org_logo' => null,
);

$google_session = $profilo['sessione'];
$xml_user       = $profilo['utente'];

$is_google_user     = $profilo['collegato'];
$is_registered_user = $profilo['registrato'];

$display_role   = $profilo['role'];

$anon_handle     = $profilo['anon'];
$portal_name     = $profilo['name'];
$portal_email    = $profilo['email'];
$portal_image    = $profilo['image'];
$portal_org_name = $profilo['org_name'];
$portal_org_logo = $profilo['org_logo'];
?>
<ul class="google-avatar">
    <?php if (!$is_google_user): ?>
        <li>
            <?php
            /* NON IL PULSANTE DI GOOGLE, QUI DENTRO. Un'icona nostra, che segue
             * il tema come l'ingranaggio accanto, e apre una finestra dove il
             * pulsante vero c'è per intero.
             *
             * È la fine di una rincorsa. Il disegno di quel pulsante lo fa
             * Google dentro un iframe di accounts.google.com, e il tema glielo
             * si passa davvero - si legge nei parametri dell'iframe,
             * `theme=filled_black` - ma lui lo onora solo per una delle sue
             * forme. Quella a icona è bianca e basta. Quella standard il tema lo
             * prende, finché resta anonima: appena chi guarda ha una sessione
             * Google attiva diventa «Accedi come Nome», con la foto, e torna
             * bianca - una lastra chiara nell'header scuro, larga il triplo
             * dello spazio che ha, che copriva l'ultima voce del menu.
             *
             * Non si vince: quel riquadro è di Google e cambia forma quando
             * decide lui. Quindi non sta più in una riga che deve restare
             * pulita. Nella finestra ha spazio, e ci sta su una piastra chiara
             * dichiarata - bianco su bianco, che è una scelta, non un buco. */
            ?>
            <a href="#signin" id="signin-open" title="<?php _e('Sign in'); ?>" aria-label="<?php _e('Sign in'); ?>" aria-expanded="false" aria-controls="signin">
                <span class="material-symbols-outlined" aria-hidden="true">account_circle</span>
            </a>
        </li>
    <?php else: ?>
        <li class="avatar-wrapper">
            <?php
            /* QUI C'E' SOLO IL COMANDO. La finestra la stampa il footer, da
             * `template-parts/modal-profile.php`, come ogni altra finestra del
             * sito.
             *
             * Non e' pignoleria: questo pezzo viene incluso DENTRO l'header, e
             * una finestra scritta li' si porta dietro il vestito dell'header -
             * `#header a` ha un id e vince su qualunque classe, cosi' i comandi
             * del profilo uscivano rossi su fondo blu. Sullo schermo quella
             * finestra sta al centro e sopra tutto: e' giusto che stia anche
             * alla fine del documento. */
            ?>
            <a href="#profile" title="<?php _e('Your User Profile'); ?>" aria-label="<?php _e('Your User Profile'); ?>" aria-expanded="false" aria-controls="profile">
                <img src="<?= htmlspecialchars($google_session->picture) ?>" class="avatar avatar-button" alt="" referrerpolicy="no-referrer">
            </a>
        </li>
    <?php endif; ?>
</ul>
