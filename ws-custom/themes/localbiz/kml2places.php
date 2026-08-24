<?php
/**
 * The main template file
 *
 * This is the most generic template file in a WordPress theme
 * and one of the two required files for a theme (the other being style.css).
 * It is used to display a page when nothing more specific matches a query.
 * e.g., it puts together the home page when no home.php file exists.
 *
 * Learn more: {@link https://codex.wordpress.org/Template_Hierarchy}
 *
 * @package WordPress
 * @subpackage Isotype_Theme
 * @since Isotype 1.0
 */
global $ws_content;
include_template('template-parts/header');
$kml_url = 'https://localbiz.it/localbiz/ws-custom/contents/localbiz.it/data/ristoranti-for-kids/ristoranti-family.kml';
$relpath = 'data/ristoranti-for-kids/';
$basename = 'ristoranti-family.xml';
$folder_abspath = ws_content_abspath().$relpath;
$file_abspath = $folder_abspath.$basename;
if(file_exists($file_abspath)){
	$placesDOMDocument = ws_load_file( $file_abspath, $args = array( 'format_output' => true, 'xinclude' => false, 'output_type' => 'dom' ) );
	$places_domxpath = new DOMXpath($placesDOMDocument);
	$places_without_place_id = $places_domxpath->query('//place[not(./place_id)]');
	// Create place_idElement in [filename] in placeElements without it
	foreach ($places_without_place_id as $place_without_place_id) {
		$place_name = $places_domxpath->query('.//name', $place_without_place_id);
		$address = $place_name[0]->nodeValue;
		$google_geocoderesponse_dom = google_geocoderesponse($address);
		$geocoderesponse_place_id_value = google_geocoderesponse_place_id($google_geocoderesponse_dom);
		$google_geocoderesponse_pathinfo = array(
			'dirname' => $folder_abspath,
			'filename' => $geocoderesponse_place_id_value.'-geocode',
			'extension' => 'xml',
		);
		$google_geocoderesponse_copy_filename = $google_geocoderesponse_pathinfo['filename'].'-'.date(WS_DATETIME_FORMAT);
		$google_geocoderesponse_xml_localabspath = $google_geocoderesponse_pathinfo['dirname'].$google_geocoderesponse_pathinfo['filename'].'.'.$google_geocoderesponse_pathinfo['extension'];
		// Save last version with [place_id]-geocode.xml as filename
		$ws_logs[] = sprintf(__('Il file <a href="%1$s">%2$s</a> (%3$s bytes) è stato salvato.'),
			abspath2url($google_geocoderesponse_xml_localabspath),
			$google_geocoderesponse_xml_localabspath,
			ws_save_file( $google_geocoderesponse_dom, $google_geocoderesponse_xml_localabspath, $args = array() )
		);
		// Save a copy for archive proposals with [place_id]-geocode-[yyyymmdd]T[hhmmss]Z.xml as filename
		$ws_logs[] = sprintf(__('Una copia <a href="%1$s">%2$s</a> è stata archiviata.'),
			abspath2url($google_geocoderesponse_xml_localabspath),
			$google_geocoderesponse_copy_filename,
			ws_save_file( $google_geocoderesponse_dom, $google_geocoderesponse_xml_localabspath, array( 'file_timestamp' => true) )
		);
		$place_id = $placesDOMDocument->createDocumentFragment();
		$place_id_string = '<place_id>'.$geocoderesponse_place_id_value.'</place_id>';
		$place_id->appendXML($place_id_string);
//		<details><xi:include href="'.$geocoderesponse_place_id_value.'-details.xml"/></details>
		$place_without_place_id->appendChild($place_id);
		$ws_logs[] = sprintf(__('<code>%s</code> appended.'), htmlentities($place_id_string));
	}
	$places = $places_domxpath->query('./place');
	foreach ($places as $place) {
		$place_id = $places_domxpath->query('./place_id', $place);
		if ($place->getElementsByTagName("details")->length == 0) {
		    $detailsElement = $placesDOMDocument->createElement('details');
		    $place->appendChild($detailsElement);
				$ws_logs[] = sprintf(__('Element <code>%s</code> appended.'), '&lt;details /&gt;');
		}
	}
	foreach ($places as $place) {
		$place_id = $places_domxpath->query('./place_id', $place);
		$place_id_value = $place_id[0]->nodeValue;
		$google_place_details_pathinfo = array(
			'dirname' => $folder_abspath,
			'filename' => $place_id_value.'-details',
			'extension' => 'xml',
		);
		$google_place_details_copy_filename = $google_place_details_pathinfo['filename'].'-'.date(WS_DATETIME_FORMAT);
		$google_place_details_xml_localabspath = $google_place_details_pathinfo['dirname'].$google_place_details_pathinfo['filename'].'.'.$google_place_details_pathinfo['extension'];
		if(!file_exists($google_place_details_xml_localabspath)){
			$google_place_details_dom = google_place_details($place_id_value);
			$ws_logs[] = sprintf(__('Il file <a href="%1$s">%2$s</a> (%3$s bytes) è stato salvato.'),
				abspath2url($google_place_details_xml_localabspath),
				$google_place_details_xml_localabspath,
				ws_save_file( $google_place_details_dom, $google_place_details_xml_localabspath, $args = array() )
			);
			// Save a copy for archive proposals with [place_id]-geocode-[yyyymmdd]T[hhmmss]Z.xml as filename
			$ws_logs[] = sprintf(__('Una copia <a href="%1$s">%2$s</a> è stata archiviata.'),
				abspath2url($google_place_details_xml_localabspath),
				$google_place_details_copy_filename,
				ws_save_file( $google_place_details_dom, $google_place_details_xml_localabspath, array( 'file_timestamp' => true) )
			);
		}
		if($place->getElementsByTagName("details")->length == 1) {
			$detailsElements = $place->getElementsByTagName("details");
			foreach ($detailsElements as $detailsElement) {
				if ($detailsElement->getElementsByTagName("include")->length == 0) {
					$xincludeElement = $placesDOMDocument->createDocumentFragment();
					$xincludeString = '<xi:include href="'.$place_id_value.'-details.xml"/>';
					$xincludeElement->appendXML($xincludeString);
					$xincludeElement = $placesDOMDocument->createElement('xi:include');
					$xinclude_href = $placesDOMDocument->createAttribute('href');
					$xinclude_href->value = $place_id_value.'-details.xml';
					$xincludeElement->appendChild($xinclude_href);
					$detailsElement->appendChild($xincludeElement);
					$ws_logs[] = sprintf(__('Element <code>%s</code> appended.'), htmlentities($xincludeString));
				}
			}
		}
	}
	ws_save_file( $placesDOMDocument, $file_abspath, array() );
	ws_save_file( $placesDOMDocument, $file_abspath, $args = array( 'file_timestamp' => true) );
} else {
	kml2places($kml_url, $basename, $file_abspath);
}
function kml2places($kml_url, $basename, $file_abspath){
	if(is_url($kml_url) and !empty($basename)){
		// Before using php DOMXpath
		// https://groups.google.com/forum/#!topic/google-maps-api/L3RLprNcBzc
		// $kml_string = str_replace("xmlns=", "ns=", $kml_string);
		$kml_dom = ws_load_file( $kml_url, array( 'output_type' => 'dom' ) );
		$kml_domxpath = new DOMXpath($kml_dom);
		$place_names = $kml_domxpath->query('//Placemark/name');
		// Create xml Place name index
		$placesDOMDocument = new DomDocument("1.0", "UTF-8");
		$placesDOMDocument_root = $placesDOMDocument->createElement("places");
		$placesDOMDocument->appendChild($placesDOMDocument_root);
		$placesDOMDocument->createAttributeNS('http://www.w3.org/2001/XInclude', 'xi:attr');
		$place_dom = $placesDOMDocument->createDocumentFragment();
		foreach ($place_names as $place_name) {
			$place_dom->appendXML('
<place>
	<name>'.$place_name->nodeValue.'</name>
</place>');
			$placesDOMDocument->documentElement->appendChild($place_dom);
		}
		ws_save_file( $placesDOMDocument, $file_abspath, array() );
	}
}
?>
		<main>
<?php
if($ws_content->name){
?>
<h1 itemprop="name"><?php echo $ws_content->name; ?></h1>
<?php
}
?>
<?php
if($ws_content->description){
?>
			<div itemprop="description">
				<?php ?>
			</div>
<?php
}
?>
			<div class="content">
<?php
if($ws_content->main){
	echo $ws_content->main->innerHTML();
}
?>
				<form>
					<p>
						<label class="question field url">
							<span class="label">Kml Url</span>
							<span class="input url flex1">
								<input name="kml_url" type="url" value="<?php echo $kml_url; ?>" />
							</span>
						</label>
					</p>
					<p>
						<label class="question field text">
							<span class="label"><?php printf(__('Save file as <code>%s</code>', 'isotype'), ws_content_relpath()); ?></span>
							<span class="input flex1">
								<input name="relpath" type="text" value="<?php echo $basename; ?>" />
							</span>
						</label>
					</p>
					<p class="submit">
						<button type="submit" class="button h48" disabled="disabled">
							<span class="button-text">Crea file xml</span>
							<i class="material-icons right">send</i>
						</button>
					</p>
				</form>
			</div>
		</main>
<?php
include_template('template-parts/footer');
?>
