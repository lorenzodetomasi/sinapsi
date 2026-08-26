<?php
/**
 * The Page not found (Error 404) template
 *
 * This is the template file loaded when the requested page url doesn’t have a correspondant page.
 * This file is required for a theme.
 * It is used to display a page when nothing more specific matches a query.
 * e.g., it puts together the home page when no home.php file exists.
 *
 * Learn more: {@link https://codex.wordpress.org/Template_Hierarchy}
 *
 * @package WS
 * @subpackage Localbiz
 * @since 1.0
 */

/* `$ws_content` qui è spesso FALSE: la pagina 404 si raggiunge proprio quando non
 * c'è un contenuto, e un sito può benissimo non avere un documento «404». Chiedere
 * una proprietà a `false` stampa un avviso PHP dentro la pagina — e lo stampava,
 * su ogni indirizzo sbagliato. Da qui in giù si guarda sempre con `!empty()`. */
global $ws_content;
$GLOBALS['ws_html_attributes']['html']['class'][] = 'page';
include_template('template-parts/header');
?>
            <div<?php echo ws_html_attributes('main-content'); ?>>
<?php
if(!empty($ws_content->name)){
?>
                <h1 itemprop="name"><?php echo $ws_content->name->innerHTML(); ?></h1>
<?php
} else {
?>
                <h1 itemprop="name"><?php _e('Page not found'); ?></h1>
<?php
}
?>
<?php
if(!empty($ws_content->headline)){
?>
                <h2 itemprop="headline"><?php echo $ws_content->headline->innerHTML(); ?></h2>
<?php
} else {
?>
                <h2 itemprop="headline"><?php _e('Error 404'); ?></h2>
<?php
}
?>
                <div class="content">
<?php
if(!empty($ws_content->mainContentOfPage)){
    echo $ws_content->mainContentOfPage->innerHTML();
}
if(!empty($ws_content->section)){
    foreach ($ws_content->section as $section) {
        echo $section->innerHTML();
    }
}
?>
                </div>
            </div>
<?php

?>
<?php
include_template('template-parts/footer');
?>
