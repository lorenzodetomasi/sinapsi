<?php
/**
 * Le azioni dell'header che sono di Meetoo e di nessun altro.
 *
 * Stavano in `top-header.php`, che l'header di Meetoo includeva. Da quando
 * l'header e' quello del tema genitore quel file non lo apre piu' nessuno, e
 * queste due cose sarebbero sparite: sono l'unico modo, dal sito, di aggiungere
 * un evento o di mettere le mani su quello che si sta guardando.
 *
 * Il genitore cerca questo file per nome e lo include se c'e'. Il resto di
 * `top-header.php` non serve piu': l'ingranaggio e' `#preferences-open`,
 * l'accesso lo mette il plugin, e `mt-slot` era un appiglio di header.js.
 */
?>
<?php
/* Il «+»: lo vede chi ha il diritto di creare - un amministratore, o chi
 * gestisce un gruppo. */
$mt_crea = (function_exists('meetoo_puo_creare') and meetoo_puo_creare('events')) ? meetoo_url_crea('events') : '';
if($mt_crea !== ''){
?>
							<a id="create" href="<?php echo mt_esc($mt_crea); ?>" title="<?php _e('Aggiungi un evento'); ?>" aria-label="<?php _e('Aggiungi un evento'); ?>"><span class="material-symbols-outlined" aria-hidden="true">add</span></a>
<?php } ?>
<?php
/* La penna: c'e' solo per chi puo' davvero modificare QUESTA cosa, e solo se
 * esiste un editor che sappia aprirla. Il permesso lo decide la stessa funzione
 * che risponde al salvataggio, cosi' la penna non e' mai una porta su un muro. */
$mt_modifica = function_exists('meetoo_url_modifica') ? meetoo_url_modifica() : '';
if($mt_modifica !== '' and function_exists('meetoo_puo_modificare') and meetoo_puo_modificare()){
?>
							<a id="edit" href="<?php echo mt_esc($mt_modifica); ?>" title="<?php _e('Modifica'); ?>" aria-label="<?php _e('Modifica'); ?>"><span class="material-symbols-outlined" aria-hidden="true">edit</span></a>
<?php } ?>
