<?php
function get_google_api_key(){
	return GOOGLE_API_KEY;
}
function google_geocoderesponse_url($address, $args = array()){
	$default_args = array(
		'google_api_key' => get_google_api_key(),	// string
		'output_type' => 'json',	// json | xml
		'locale' => ws_locale(),		// string
	);
	$args = array_merge( $default_args, $args );
	$address = urlencode($address);
	return $url = 'https://maps.googleapis.com/maps/api/geocode/'.$args['output_type'].'?key='.$args['google_api_key'].'&address='.$address.'&language='.$args['locale'];
}
function google_geocoderesponse($address, $input_type = 'xml', $output_type = 'dom'){
	$google_geocoderesponse_url = google_geocoderesponse_url($address, array('google_api_key' => GOOGLE_API_KEY_IP, 'output_type' => $input_type));
	return ws_load_file( $google_geocoderesponse_url, $args = array( 'format_output' => true, 'input_type' => $input_type, 'output_type' => $output_type ) );
}
function google_geocoderesponse_place_id($dom){
	// google_geocoderesponse dom
	$domxpath = new DOMXpath($dom);
	$elements = $domxpath->query('/GeocodeResponse/result/place_id');
	return $elements[0]->nodeValue;
}
function google_place_details_url($place_id, $args = array()){
	global $ws_query;
	$default_args = array(
		'output_type' => 'xml',	// xml | json
		'key' => get_google_api_key(),	// string
		'language' => false,		// string
	);
	$args = array_merge( $default_args, $args );
	if($args['language'] == false and !empty($ws_query['langArray'][0])){
		$args['language'] = $ws_query['langArray'][0];
	}
	return $url = 'https://maps.googleapis.com/maps/api/place/details/'.$args['output_type'].'?key='.$args['key'].'&place_id='.$place_id.'&language='.$args['language'];
}
function google_opening_hours($opening_hours_wspath){
	// Converts WS opening hours xml format to Google My Business xml format

}
?>
