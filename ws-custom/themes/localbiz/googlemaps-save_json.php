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
//the_header();
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
$address = null;
if(!empty($address)){
	$output_type = 'xml';
	$google_geocoderesponse_xml_url = google_geocoderesponse_url($address, array('google_api_key' => GOOGLE_API_KEY_IP, 'output_type' => $output_type));
	$dom = ws_load_file( $google_geocoderesponse_xml_url, $args = array( 'format_output' => true, 'output_type' => 'dom' ) );
	$place_id = google_geocoderesponse_place_id($dom);
	$google_geocoderesponse_filename = $place_id.'-geocode';
	$google_geocoderesponse_copy_filename = $google_geocoderesponse_filename.'-'.date('Ymd\THis\Z');
	$folder_abspath = ws_content_abspath();
	$google_geocoderesponse_xml_localabspath = $folder_abspath.$google_geocoderesponse_filename.'.'.$output_type;
	$google_geocoderesponse_xml_copy_localabspath = $folder_abspath.$google_geocoderesponse_copy_filename.'.'.$output_type;
	// Save last version with [place_id]-geocode.xml as filename
	printf(__('Il file <a href="%1$s">%2$s</a> (%3$s bytes) è stato salvato.'),
		abspath2url($google_geocoderesponse_xml_localabspath),
		$google_geocoderesponse_xml_localabspath,
		ws_save_file( $dom, $google_geocoderesponse_xml_localabspath, $args = array() )
	);
	echo ' <br />';
	// Save a copy for archive proposals with [place_id]-geocode-[yyyymmdd]T[hhmmss]Z.xml as filename
	printf(__('Una copia <a href="%1$s">%2$s</a> è stata archiviata.'),
		abspath2url($google_geocoderesponse_xml_copy_localabspath),
		$google_geocoderesponse_copy_filename,
		ws_save_file( $dom, $google_geocoderesponse_xml_copy_localabspath, $args = array() )
	);
}
//$json = file_get_contents($json_url);
//print_r($json);
?>
				<form>
					<p>
						<label class="question field postal-address">
							<span class="label">Indirizzo o luogo</span>
							<span class="input postal-address flex1">
								<input name="postal-address" type="text" placeholder="ad es. localbiz" value="<?php echo $address; ?>" autocomplete="off">
								<small class="help">Inserisci il nome di un'impresa (nome sull’insegna, ragione sociale…) e l'indirizzo postale</small>
							</span>
						</label>
					</p>
					<p class="question flex1">
						<label class="field select">
							<span class="label">Formato della GeocodeResponse</span>
							<select name="google_geocoderesponse_format" class=" flex1">
								<option value="xml">xml</option>
								<option value="json">json</option>
							</select>
						</label>
					</p>
					<p>
						<label class="question field url">
							<span class="label">GeocodeResponse Url</span>
							<span class="input url flex1">
								<input name="google_geocoderesponse_xml_url" type="url" value="<?php echo $google_geocoderesponse_xml_url; ?>" />
							</span>
						</label>
					</p>
					<p>
						<label class="question field text">
							<span class="label">Place ID</span>
							<span class="input url flex1">
								<input name="place_id" type="text" value="<?php echo $place_id; ?>" />
							</span>
						</label>
					</p>
				</form>
			</div>
		</main>
<?php
//the_footer();
?>
