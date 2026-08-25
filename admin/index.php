<?php
/**
 * `/admin/` porta all'amministrazione.
 *
 * Un file e non una regola di riscrittura: il server è nginx, quindi `.htaccess`
 * non lo legge nessuno e una `RewriteRule` resterebbe lettera morta. Una cartella
 * con dentro un redirect funziona ovunque e non chiede accesso al pannello.
 *
 * 302 e non 301: l'indirizzo definitivo dell'amministrazione non è ancora deciso
 * (oggi `/ws-admin/`, domani forse altrove), e un 301 lo inchioderebbe nella
 * cache dei browser di tutti.
 */
$radice = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
$radice = ($radice === '/admin') ? '' : preg_replace('#/admin$#', '', $radice);
header('Location: ' . $radice . '/ws-admin/', true, 302);
exit;
