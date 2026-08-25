<?php
/**
 * Siti INNESTATI: prefisso dell'indirizzo => cartella del sito in contents/.
 *
 * Serve a far convivere più siti in un dominio solo senza che i contenuti lo
 * sappiano: i loro wspath restano quelli del giorno in cui avranno un dominio
 * proprio, e il prefisso lo mette (e lo toglie) il CMS. Svuotarlo significa
 * «questo sito risponde alla radice».
 *
 * Sta in un file suo, e non fra le altre costanti, perché lo devono vedere in due:
 * il CMS quando instrada, e l'amministrazione quando genera il sitemap per i
 * motori — e l'amministrazione il CMS non lo avvia. Con la definizione in un posto
 * solo non possono dire cose diverse: se lo dicessero, le pagine risponderebbero a
 * un indirizzo e i motori ne cercherebbero un altro.
 *
 * Si ridefinisce in ws-custom/ws-config.php, che viene caricato prima.
 */
if (!defined('WS_MOUNTS')) {
    define('WS_MOUNTS', array('/meetoo' => 'meetoo'));
}
