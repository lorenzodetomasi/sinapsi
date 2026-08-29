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

$display_locale = $profilo['locale'];
$display_role   = $profilo['role'];

$anon_handle     = $profilo['anon'];
$portal_name     = $profilo['name'];
$portal_email    = $profilo['email'];
$portal_image    = $profilo['image'];
$portal_org_name = $profilo['org_name'];
$portal_org_logo = $profilo['org_logo'];
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
            function ridisegna(forza) {
                var vecchio = bottone();
                var gis = window.google && google.accounts && google.accounts.id;
                if (!vecchio) { return; }
                /* Senza la libreria, o prima del suo primo disegno, l'attributo
                 * basta: al disegno ci pensera' lei, e lo leggera' di li'. Con
                 * `forza` si disegna comunque — serve al caso in cui lei non
                 * disegni affatto. */
                if (!gis || (!forza && !vecchio.firstChild)) { vecchio.setAttribute('data-theme', tema()); return; }
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

            /* IL PRIMO DISEGNO LO RIFACCIAMO NOI, sempre — non solo quando il tema
             * cambia.
             *
             * Lasciata fare da sola, la libreria sceglie da sé come disegnare, e
             * sul sito vero sceglie l'iframe: un riquadro bianco con dentro il
             * pulsante nero di Google, che nell'header scuro si vede come un
             * cerchio nero dentro una scheda bianca. In locale sceglieva il
             * pulsante nella pagina, ed è per questo che la differenza non si era
             * vista prima. Chiamandola noi su un elemento nuovo disegna nella
             * pagina — verificato in produzione — e da lì il fondo glielo diamo
             * noi, con il colore dell'header.
             *
             * Si aspetta che abbia finito il SUO disegno: rifarlo prima vorrebbe
             * dire che poi lo rifà lei sopra il nostro, e si torna all'iframe. Se
             * dopo dieci secondi non ha disegnato niente si lascia stare: meglio il
             * pulsante che c'è di nessun pulsante. */
            var attese = 0;
            (function aspetta() {
                var el = bottone();
                var gis = window.google && google.accounts && google.accounts.id;
                if (el && gis && (el.firstChild || attese > 40)) { ridisegna(true); return; }
                if (++attese > 100) { return; }
                setTimeout(aspetta, 100);
            })();

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
