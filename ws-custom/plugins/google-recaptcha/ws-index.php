<?php
global $ws_logs;
$ws_logs[] = __('<strong>Google Recaptcha</strong> Plugin initialized <code>'.__FILE__.'</code>.');

function google_recaptcha_response_keys($g_recaptcha_response){
    //Construct the url to send your private Secret Key, token and (optionally) IP address of the form submitter to Google to get a spam rating for the submission.
    $g_recaptcha_url =  'https://www.google.com/recaptcha/api/siteverify?secret=' . urlencode(GOOGLE_RECAPTCHA_SECRET_KEY) . '&response=' . urlencode($g_recaptcha_response) . '&remoteip=' . urlencode(real_client_ip_address());
    //Get the response
    $g_recaptcha_response = file_get_contents($g_recaptcha_url);
    //Decode the response
    $g_recaptcha_response_keys = json_decode($g_recaptcha_response, true);
    return $g_recaptcha_response_keys;
}
?>