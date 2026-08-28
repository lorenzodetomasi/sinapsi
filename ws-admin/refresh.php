<?php
global $ws_logs;
$ws_root_url = ws_root_url();
// https://scssphp.github.io/scssphp/docs/
require_once ws_core_abspath().'/libraries/php/scssphp/scss.inc.php';
use ScssPhp\ScssPhp\Compiler;
$ws_custom_url = ws_custom_url();
?>
<h1><a href="<?php echo $ws_root_url; ?>"><?php echo $ws_root_url; ?></a></h1>
<h2><?php _e('Initializing your website'); ?></h2>
<?php
// Build main ws_sitemap (contents/ws_sitemap.wsx),
// adding /contents subfolders not starting with "-" (minus)
$ws_contents_abspath = ws_contents_abspath();
$ws_contents_subfolders = glob($ws_contents_abspath . '/*' , GLOB_ONLYDIR);
$main_ws_sitemap_abspath = $ws_contents_abspath.'/ws_sitemap.wsx';
$xml_url = abspath2url($main_ws_sitemap_abspath);
$main_ws_sitemap_DOM = new DOMDocument();
$main_ws_sitemap_DOM->loadXML('
<urlset xmlns:xi="http://www.w3.org/2001/XInclude">
  <ws_root_url>'.$ws_root_url.'</ws_root_url>
</urlset>');
foreach ($ws_contents_subfolders as $ws_contents_directory) {
	$ws_contents_directory_name = remove_start($ws_contents_directory, $ws_contents_abspath.'/');
	if(!str_starts_with($ws_contents_directory_name, '-')){
		$f = $main_ws_sitemap_DOM->createDocumentFragment();
		$f->appendXML('<xi:include xmlns:xi="http://www.w3.org/2001/XInclude" href="'.remove_start($ws_contents_directory, $ws_contents_abspath.'/').'/ws_sitemap.wsx" xpointer="xpointer(/*[1]/*)" />');
		$main_ws_sitemap_DOM->documentElement->appendChild($f);
	}
}
$f = $main_ws_sitemap_DOM->createDocumentFragment();
$f->appendXML('<xi:include xmlns:xi="http://www.w3.org/2001/XInclude" href="../../ws-admin/ws_sitemap.wsx" xpointer="xpointer(/*[1]/*)" />');
$main_ws_sitemap_DOM->documentElement->appendChild($f);
$ws_log = sprintf(__('File <a href="%1$s">%2$s</a> (%3$s bytes) saved.'),
	$xml_url,
	remove_start($xml_url, $ws_custom_url),
	ws_save_file($main_ws_sitemap_DOM, $main_ws_sitemap_abspath)
);
?>
		<li><?php echo $ws_log; ?></li>
<?php
// Refresh Contents
?>
<h2><?php _e('Refreshing your website'); ?></h2>
<?php
if((isset($_GET['debug']) and $_GET['debug'] == 'true') or WS_DEBUG == true){
?>
<p><a href="<?php echo ws_href('/admin/refresh');?>">Deactivate debug</a></p>
<?php
} else {
?>
<p><a href="<?php echo ws_href('/admin/refresh?debug=true');?>">Activate debug</a></p>
<?php
}
?>
<form action="<?php echo ws_href('/admin/refresh'); ?>" method="POST" name="refresh">
	<fieldset>
		<legend><?php _e('Contents'); ?></legend>
		<p>
			<label><?php _e('Directories'); ?>
				<select multiple name="ws_contents_directories[]">
<?php
foreach ($ws_contents_subfolders as $ws_contents_directory) {
?>
						<option value="<?php echo $ws_contents_directory; ?>" selected="selected"><?php echo remove_start($ws_contents_directory, $ws_contents_abspath.'/'); ?></option>
<?php
}
?>
				</select>
			</label>
			<fieldset>
				<legend><?php _e('Types'); ?></legend>
				<label><input type="checkbox" name="ds_store" /><?php _e('DS_Store files'); ?></label>
				<label><input type="checkbox" name="xml" checked="checked" /><?php _e('Xml'); ?></label>
				<label><input type="checkbox" name="images" /><?php _e('Images'); ?></label>
				<label><input type="checkbox" name="google_place_details" /><?php _e('Google Place Details'); ?></label>
			</fieldset>
		</fieldset>
		<fieldset>
			<legend><?php _e('Themes'); ?></legend>
			<p>
				<label><?php _e('Directories'); ?>
					<select multiple name="ws_themes_directories[]">
	<?php
	$ws_themes_abspath = realpath(ws_themes_abspath());
	$ws_themes_directories = glob($ws_themes_abspath . '/*' , GLOB_ONLYDIR);
	foreach ($ws_themes_directories as $ws_theme_directory) {
	?>
							<option value="<?php echo $ws_theme_directory; ?>"><?php echo remove_start($ws_theme_directory, $ws_themes_abspath.'/'); ?></option>
	<?php
	}
	?>
					</select>
				</label>
				<fieldset>
					<legend><?php _e('Types'); ?></legend>
					<label><input type="checkbox" name="css" checked="checked" /><?php _e('Css'); ?></label>
				</fieldset>
		</fieldset>
	</p>
	<p><button type="submit"><?php _e('Refresh'); ?></button></p>
</form>
<?php
if($_POST){
?>
<ol>
<?php
	if($_POST['ds_store'] == 'on'){
?>
	<li>
		<strong><?php _e('Deleting all .DS_Store files'); ?></strong><br />
<?php
	$dsstore_files_abspaths = get_files_abspaths(array('dir_abspath' => ws_root_abspath(), 'extension' => "DS_Store"));
	printf(__('%s .DS_Store files found.')."<br />", count($dsstore_files_abspaths));
	foreach ($dsstore_files_abspaths as $dsstore_file_abspath) {
		if(unlink($dsstore_file_abspath)){
			printf(__('Deleted file %s.')."<br />", $dsstore_file_abspath);
		}
	}
?>
	</li>
<?php
	}
	if($_POST['xml'] == 'on'){
?>
	<li>
		<strong><?php _e('Refreshing XML files'); ?></strong>
		<ol>
<?php
		// Build sitemap.xml for SEO <https://www.sitemaps.org/protocol.html>
		// Uses Xsl template <https://www.w3.org/TR/xslt/all/>
		// PHP Xsl library must be enabled on your hosting, to support XSL Transformations (XSLT) Version 1.0
		// Load sitemap.xsl as string
		$sitemapXslString = file_get_contents(ws_core_abspath() . '/templates/sitemap.xsl');
		// Convert loc relpaths to urls, adding ws_root_url
		$sitemapXslString = str_replace("<loc>", '<loc>'.rtrim($ws_root_url, '/'), $sitemapXslString);
		$sitemapXsl = new DOMDocument;
		$sitemapXsl->loadXML($sitemapXslString);
		$sitemapXSLTProcessor = new XSLTProcessor();
		$sitemapXSLTProcessor->importStyleSheet($sitemapXsl);
		foreach ($_POST['ws_contents_directories'] as $ws_content_directory) {
?>
			<li>
				<strong><?php echo $ws_content_directory; ?></strong>
				<ol>
<?php
// Search for all .wsx files.
$wsx_files_abspaths = get_files_abspaths(array('dir_abspath' => $ws_content_directory, 'extension' => "wsx"));
$ws_sitemaps = array();
foreach(array_reverse($wsx_files_abspaths) as $wsx_file_abspath){
	$wsx_file_pathinfo = pathinfo($wsx_file_abspath);
	// Save sitemap.xml
	if($wsx_file_pathinfo['filename'] == 'ws_sitemap'){
		$ws_sitemaps[] = $wsx_file_abspath;
	} else {
		// Buid xml files with Xincludes from files with .wsx get_loaded_extensions.
		// Speeds up WS queries.
		wsx_export_xml($wsx_file_abspath);
	}
?>
<?php
}
// Add main ws_sitemap (contents/ws_sitemap.wsx) as last element
$ws_sitemaps[] = $main_ws_sitemap_abspath;
foreach ($ws_sitemaps as $wsx_file_abspath) {
	// Buid xml files with Xincludes from files with .wsx get_loaded_extensions.
	// Speeds up WS queries.
	wsx_export_xml($wsx_file_abspath);
	// Build sitemap.xml files for SEO
	$wsx_file_pathinfo = pathinfo($wsx_file_abspath);
	$xml_file_abspath = $wsx_file_pathinfo['dirname'].'/sitemap.xml';
	$xml_file_pathinfo = pathinfo($xml_file_abspath);
	$xml_url = abspath2url($xml_file_abspath);
	$DOMDocument = ws_load_file($wsx_file_abspath, array('output_type' => 'dom'));
	$ws_log = sprintf(__('File <a href="%1$s">%2$s</a> (%3$s bytes) saved.'),
		$xml_url,
		remove_start($xml_url, $ws_custom_url),
		ws_save_file($sitemapXSLTProcessor->transformToDoc($DOMDocument), $xml_file_abspath)
	);
?>
				<li><?php echo $ws_log; ?></li>
<?php
}
$ws_logs = array();
?>
				</ol>
			</li>
<?php
		}
?>
		</ol>
	</li>
<?php
	}
	if(isset($_POST['json']) and $_POST['json'] == 'on'){
	?>
	<li>
		<strong><?php _e('Converting .xml files to .json'); ?></strong>
		<ol>
<?php
/*
// Convert .xml to .json
// Search for all .xml files.
$xml_files_abspaths = get_files_abspaths(array('dir_abspath' => $ws_contents_abspath, 'extension' => "xml"));
// Print ws_logs
foreach ($ws_logs as $ws_log) {
?>
			<li><?php echo $ws_log; ?></li>
<?
}
*/
?>
		</ol>
	</li>
<?php
	}
	if(isset($_POST['google_place_details']) and $_POST['google_place_details'] == 'on'){
?>
	<li>
		<strong>
<?php
		if(empty(GOOGLE_API_KEY)){
			_e('Google API key not set');
		} else {
			_e('Getting Google Place Details');
		}
?>
		</strong>
		<ol>
<?php
if(!empty(GOOGLE_API_KEY)){
	foreach ($_POST['ws_contents_directories'] as $ws_contents_directory) {
		// Search for all location.wsx files.
		$location_wsx_files_abspaths = get_files_abspaths(array('dir_abspath' => $ws_contents_directory, 'filename' => 'location', 'extension' => "wsx"));
		foreach ($location_wsx_files_abspaths as $location_wsx_file_abspath) {
			$location_relpath = remove_start($location_wsx_file_abspath, $ws_contents_abspath.'/');
			$location = ws_content($location_relpath);
			$google_place_id = $location->google_place_id;
?>
			<li>
<?php
			printf(__('Google Place ID %1$s found in %2$s'), '<code>'.$google_place_id.'</code>', '<code>'.$location_relpath.'</code>');
			if(!empty($google_place_id)){
				$google_place_details_url = google_place_details_url($google_place_id, array('language' => strtok($location->inLanguage, '-')));
				echo '<br />'.sprintf(__('Getting Google Place Details from %s'), '<a href="'.$google_place_details_url.'">'.$google_place_details_url.'</a>');
				$google_place_obj = ws_load_file( $google_place_details_url, $args = array( 'format_output' => true, 'input_type' => 'xml', 'output_type' => 'simplexml' ) );
				if($google_place_obj->error_message){
					_e('Error in $google_place_obj:');
					print_r($google_place_obj);
				} else {
					$xml_file_abspath = pathinfo($location_wsx_file_abspath)["dirname"].'/'.$google_place_id.'.xml';
					$xml_file_url = abspath2url($xml_file_abspath);
					$ws_log = sprintf(__('File <a href="%1$s">%2$s</a> (%3$s bytes) saved.'),
						$xml_file_url,
						remove_start($xml_file_url, $ws_custom_url),
						ws_save_file($google_place_obj, $xml_file_abspath, array('input_type' => 'simplexml'))
					);
				}
			}
			echo '<br />'.$ws_log;
?>
		</li>
<?php
		}
	}
}
?>
		</ol>
	</li>
<?php
	}
	if(isset($_POST['images']) and $_POST['images'] == 'on'){

	}
?>
<?php
if(isset($_POST['css']) and $_POST['css'] == 'on'){
?>
<li>
	<strong><?php _e('Refreshing CSS files'); ?></strong>
	<ol>
<?php
if(WS_PARSE_SCSS){
	try {
	    $scss = new Compiler();

	    echo $scss->compile($content);
	} catch (\Exception $e) {
	    echo '';
	    syslog(LOG_ERR, 'scssphp: Unable to compile content');
	}
	$ws_scss = new Compiler();
	if((isset($_GET['debug']) and $_GET['debug'] == 'true') or WS_DEBUG == true){
		$ws_scss->setLineNumberStyle(Compiler::LINE_COMMENTS);
?>
				<li>
					<?php echo $ws_log = '<strong class="alert">'.__('SCSS debugging is active. Remember to disable on production.').'</strong>'; ?>
				</li>
<?php
	}
	// Find SCSS Directories
	foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ws_themes_abspath)) as $result){
		$file_pathinfo = pathinfo($result);
		if ($result->getFilename() == '..' and str_ends_with($result->getPathname(), '/scss/..')){
			$scss_dir_abspath = substr_replace($result->getPathname(),"",-2);
			$scss_dir_url = abspath2url($scss_dir_abspath);
			$ws_log = sprintf(__('SCSS directory %1$s found.'),
				'<code>'.remove_start($scss_dir_url, $ws_custom_url).'</code>'
			);
?>
			<li>
				<?php echo $ws_log; ?>
<?php
			// Set scss directory as scss import path
			echo './'.remove_start($scss_dir_abspath, ws_root_abspath());
			$ws_scss->setImportPaths('./'.remove_start($scss_dir_abspath, ws_root_abspath()));
			// Find .scss files not starting with _
?>
				<ol>
<?php
			$scss_files_abspaths = array();
			foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($scss_dir_abspath)) as $result){
				$file_pathinfo = pathinfo($result);
				if ($file_pathinfo["extension"] == "scss" and !str_starts_with($file_pathinfo["filename"], '_')) {
					$scss_file_abspath = $result->getPathname();
					$scss_files_abspaths[] = $scss_file_abspath;
					$scss_file_pathinfo = pathinfo($scss_file_abspath);
					$scss_file_url = abspath2url($scss_file_abspath);
					$ws_log = sprintf(__('SCSS file %1$s found.'),
						'<code>'.remove_start($scss_file_url, $ws_custom_url).'</code>'
					);
?>
						<li><?php echo $ws_log; ?></li>
<?php
					// Compile .scss file
					$ws_scss->compile(file_get_contents($scss_file_abspath));
					$scss_url = abspath2url($scss_file_abspath);
					$ws_log = sprintf(__('SCSS file <a href="%1$s">%2$s</a> compiled.'),
						$scss_url,
						remove_start($scss_file_url, $ws_custom_url)
					);
?>
						<li><?php echo $ws_log; ?></li>
<?php
					// and save as ../css/filename.css
					$css_file_relpath = $scss_file_pathinfo['filename'].'.css';
					$css_file_abspath = dirname($scss_file_pathinfo['dirname']).'/css/'.$css_file_relpath;
					$css_url = abspath2url($css_file_abspath);
					$ws_log = sprintf(__('File <a href="%1$s">%2$s</a> (%3$s bytes) saved.'),
						$css_url,
						remove_start($css_url, $ws_custom_url),
						file_put_contents($css_file_abspath, $ws_scss->compile(file_get_contents($scss_file_abspath)))
					);
?>
						<li><?php echo $ws_log; ?></li>
<?php
				}
			}
			if(count($scss_files_abspaths) == 0){
				$ws_log = __('No SCSS files found.');
?>
					<li><?php echo $ws_log; ?></li>
<?php
			}
?>
				</ol>
			</li>
<?php
		}
	}
}
?>
		</ol>
	</li>
<?php
}
?>
	<li><strong><?php _e('All contents refreshed'); ?></strong></li>
	<li><strong><?php _e('Building Html Cache'); ?></strong></li>
<?php
/*
// Build Html Cache
// Get sitemap and, foreach url, if htmlcache is true, generate an html file in $ws_contents_directory
// Start output buffering to capture the generated HTML
ob_start();

// Include or execute your PHP script that generates the HTML content
include("your_script.php");  // Replace with the actual path to your script

// Get the captured HTML content from the buffer
$html_content = ob_get_clean();

// Specify the desired filename for the saved HTML file
$filename = "generated_html.html";

// Save the HTML content to the file
file_put_contents($filename, $html_content);

// Optionally, provide feedback to the user
_e('HTML file saved successfully as $filename');
*/
?>
</ol>
<?php
}
?>
<?php
/*
// Add <ws_root_url> Element to main ws_sitemap.wsx
$ws_sitemap_abspath = $ws_contents_abspath . 'ws_sitemap.wsx';
$ws_sitemap_url = abspath2url($ws_sitemap_abspath);
$ws_sitemap_pathinfo = pathinfo($ws_sitemap_abspath);
$DOMDocument = ws_load_file($ws_sitemap_abspath, array('output_type' => 'dom', 'xinclude' => true));
$ws_custom_urlElement = $ws_root_url;
if(isset($ws_log)){
	$ws_logs[] = $ws_log;
}

<li>
	<strong><?php echo remove_start($ws_contents_directory, $ws_contents_abspath.'/'); ?></strong>
	<ol>
<?php
$ws_content_languages = ws_content(remove_start($ws_contents_directory, $ws_contents_abspath.'/').'/ws_languages');
foreach ($ws_content_languages->item as $content_language) {
?>
<li><?php echo $content_language->locale; ?></li>
<?php
}
?>
	</ol>
</li>
*/
?>
