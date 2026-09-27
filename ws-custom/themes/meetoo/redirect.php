<?php
/**
 * An address that something HAD: a site renamed when it changed postcode
 * (meetoo:formerIds, ws-admin/lib/place-history.php). The map points its old
 * page here, with `to` = the content as it is now; the answer is a permanent
 * redirect, so links already shared keep working and search engines move on.
 */
global $ws_query;
$qui = meetoo_indirizzo((string)($ws_query['to'] ?? ''));
if($qui !== '' and !headers_sent()){
	header('Location: '.$qui, true, 301);
	exit;
}
http_response_code(404);
include_template('404');
