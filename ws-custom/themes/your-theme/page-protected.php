<?php
// The Page template
// @package WS
// @subpackage Localbiz
// @since WS 1.0
global $ws_content, $ws_headings;
$GLOBALS['ws_html_attributes']['html']['class'][] = 'page';

/* CHI STA GUARDANDO, chiesto da qui.
 *
 * Questa pagina leggeva `$is_google_user`, `$portal_name` e compagnia contando
 * che glieli lasciasse per strada il partial dell'accesso, incluso dall'header.
 * Non succedeva: `load_template()` fa il `require` DENTRO una funzione, e le
 * variabili di un template non escono da lì. Erano sempre indefinite — su PHP 8
 * una fila di avvisi, e soprattutto `!$is_google_user` sempre vero, cioè
 * «effettua l'accesso» mostrato anche a chi l'accesso l'aveva fatto.
 *
 * Adesso il profilo si chiede al plugin, che è il posto che lo sa. Se il plugin
 * non c'è la pagina non esplode: si comporta come davanti a uno sconosciuto, che
 * per una pagina protetta è il modo giusto di sbagliare. */
$profilo = function_exists('google_login_profilo') ? google_login_profilo() : array(
    'collegato' => false, 'registrato' => false, 'name' => '', 'email' => null,
    'image' => null, 'org_name' => null, 'org_logo' => null,
);
$is_google_user     = $profilo['collegato'];
$is_registered_user = $profilo['registrato'];
$portal_name     = $profilo['name'];
$portal_email    = $profilo['email'];
$portal_image    = $profilo['image'];
$portal_org_name = $profilo['org_name'];
$portal_org_logo = $profilo['org_logo'];

include_template('template-parts/header');
?>
		<div<?php echo ws_html_attributes('main-content'); ?>>
<?php
if($ws_content->primaryImageOfPage){
	echo get_media($ws_content->primaryImageOfPage->figure->image, array('imgAttributes' => array('itemprop' => "primaryImageOfPage")));
}
?>
<?php
if($ws_content->parent->wspath){
// 	<span class="material-symbols-outlined">home</span>
?>
<a class="link" href="<?php echo $ws_content->parent->wspath; ?>"><span class="material-symbols-outlined">arrow_upward</span></a>
<?php
}
?>
<?php
if($ws_content->name){
?>
			<h1 itemprop="name"><?php echo $ws_content->name->innerHTML(); ?></h1>
<?php
}
?>
<?php
if($ws_content->headline){
?>
			<h2 itemprop="headline">
				<?php echo $ws_content->headline->innerHTML(); ?>
			</h2>
<?php
}
?>
			<div class="content">
<?php if($ws_headings->wip == "true"){ ?><p><?php _e("Website under construction."); ?></p><?php } ?>
<?php
if($ws_content->mainContentOfPage){
	ws_echo($ws_content->mainContentOfPage->innerHTML());
}
if($ws_content->section){
	foreach ($ws_content->section as $section) {
		ws_echo($section->innerHTML());
	}
}
?>
			</div>
        <?php if (!$is_google_user): ?>
            <p>Effettua l'accesso per visualizzare i contenuti protetti.</p>              
            <div class="g_id_signin" data-type="standard"></div>

        <?php else: ?>

            <?php if (!$is_registered_user): ?>
                <section style="background: #fff0f0; border: 1px solid #ffcccc;">
                    <h2>Utente Non Registrato</h2>
                    <p>Clicca sul tuo avatar in alto a destra e premi <strong>Registrati</strong> per generare il tuo profilo.</p>
                </section>
            <?php else: ?>
                <section id="google-user-registered">
                    <h2>Area Utenti Autorizzati</h2>
                    
                    <div class="portal-id-card">
                        <?php if ($portal_image): ?>
                            <img src="<?= htmlspecialchars($portal_image) ?>" alt="Avatar" class="portal-avatar-img">
                        <?php else: ?>
                            <?= get_google_initials_avatar($portal_name) ?>
                        <?php endif; ?>
                        
                        <div>
                            <strong><?= htmlspecialchars($portal_name) ?></strong>
                            
                            <?php if ($portal_org_name): ?>
                                <div style="display:flex; align-items:center; gap:8px; margin-top:4px;">
                                    <?php if ($portal_org_logo): ?>
                                        <img src="<?= htmlspecialchars($portal_org_logo) ?>" style="width:20px; height:20px; object-fit:contain;">
                                    <?php endif; ?>
                                    <span style="font-size:0.9rem; color:#555;">🏢 <?= htmlspecialchars($portal_org_name) ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($portal_email): ?>
                                <div style="font-size:0.9rem; color:#555; margin-top:4px;">✉️ <?= htmlspecialchars($portal_email) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                </section>
            <?php endif; ?>

        <?php endif; ?>
<?php
include_template('template-parts/locations');
?>
			</div>
<?php
include_template('template-parts/footer');
?>