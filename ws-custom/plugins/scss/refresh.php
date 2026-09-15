<?php
/**
 * The compile step of the admin refresh, moved here from ws-admin/refresh.php
 * so that the refresh can run without the compiler - and does, unless this
 * plugin is on. Prints the same <li> log the refresh page prints.
 *
 * @package WS
 * @subpackage SCSS
 */
use ScssPhp\ScssPhp\Compiler;

if(!function_exists('ws_scss_refresh')){
/**
 * Compiles every theme's scss/ directory into its css/ directory.
 *
 * @param string $ws_themes_abspath  where the themes live
 * @param string $ws_custom_url      the ws-custom URL, to shorten the log's paths
 */
function ws_scss_refresh($ws_themes_abspath, $ws_custom_url){
	$ws_scss = new Compiler();
	if((isset($_GET['debug']) and $_GET['debug'] == 'true') or WS_DEBUG == true){
		$ws_scss->setLineNumberStyle(Compiler::LINE_COMMENTS);
?>
				<li>
					<?php echo '<strong class="alert">'.__('SCSS debugging is active. Remember to disable on production.').'</strong>'; ?>
				</li>
<?php
	}
	// Find SCSS Directories
	foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ws_themes_abspath)) as $result){
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
			$ws_scss->setImportPaths('./'.remove_start($scss_dir_abspath, ws_root_abspath()));
			// Find .scss files not starting with _
?>
				<ol>
<?php
			$scss_files_abspaths = array();
			foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($scss_dir_abspath)) as $result){
				$file_pathinfo = pathinfo($result);
				if (($file_pathinfo["extension"] ?? '') == "scss" and !str_starts_with($file_pathinfo["filename"], '_')) {
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
					// Compile .scss file and save as ../css/filename.css
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
?>
					<li><?php _e('No SCSS files found.'); ?></li>
<?php
			}
?>
				</ol>
			</li>
<?php
		}
	}
}
}
?>
