<?php
global $ws_logs, $ws_query, $locations;
$ws_custom_url = ws_custom_url();
$ws_contents_abspath = ws_contents_abspath();

// Refresh Content
?>
<h1><?php _e('Refreshing your contents'); ?></h1>
<p><?php printf(__('Updating contents for %s'), '<code>'.ws_content_root_relpath().'</code>'); ?></p>
<ol>
<?php
if(GOOGLE_API_KEY){
	foreach ($locations as $locationIndex => $location) {
		$google_place_id = $location->google_place_id;
		if(!empty($google_place_id)){
			$google_place_details_url = google_place_details_url($google_place_id);
?>
	<li>
		<strong><?php printf(__('Getting Google Place Details from %s'), '<a href="'.$google_place_details_url.'">'.$google_place_details_url.'</a>'); ?></strong>
	</li>
<?php
			$google_place_obj = ws_load_file( $google_place_details_url, $args = array( 'format_output' => true, 'input_type' => 'xml', 'output_type' => 'simplexml' ) );
			if($google_place_obj->error_message){
?>
	<li>
<?php
				echo "Error in \$google_place_obj: \n";
				print_r($google_place_obj);
?>
	</li>
<?php
			} else {
				$xml_file_abspath = ws_content_root_abspath().'/locations/'.$location->id.'/'.$google_place_id.'.xml';
				$xml_file_url = abspath2url($xml_file_abspath);
				$ws_log = sprintf(__('File <a href="%1$s">%2$s</a> (%3$s bytes) saved.'),
					$xml_file_url,
					remove_start($xml_file_url, $ws_custom_url),
					ws_save_file($google_place_obj, $xml_file_abspath, array('input_type' => 'simplexml'))
				);
			}
?>
	<li><?php echo $ws_log; ?></li>
<?php
		}
	}
}
?>
	<li>
		<strong><?php _e('Refreshing media'); ?></strong>
		<ol>
<?php
$media_xml_abspath = ws_content_root_abspath().'/media.xml';
$media_xml_url = ws_content_root_url().'/media.xml';
$ws_log = sprintf(__('Processing images in <a href="%1$s">%2$s</a>.'),
	$media_xml_url,
	'<code>contents/'.ws_content_root_relpath().'/media.xml'.'</code>'
);
if (!extension_loaded('imagick')){
	$ws_log .= '<br />'.__('Imagick not installed.');
}
?>
			<li><?php echo $ws_log; ?>
<?php
$DOMDocument = ws_load_file($media_xml_abspath, array('output_type' => 'simplexml', 'xinclude' => true));
?>
				<ol>
<?php
foreach($DOMDocument->image as $image){
	$source_relpath = $image->source->relpath;
	$source_abspath = $ws_contents_abspath.'/'.$source_relpath;
	$source_url = abspath2url($source_abspath);
	$ws_log = sprintf(__('Processing <strong>source image</strong> file <a href="%1$s">%2$s</a>.'),
		$source_url,
		'<code>contents/'.$image->source->relpath.'</code>'
	);
?>
					<li><?php echo $ws_log; ?><br />
<?php
	$source_size = getimagesize($source_abspath);
	$source_mime = $source_size['mime'];
	list($source_width, $source_height) = $source_size;
	if($source_mime == false){
		$ws_log = __('File skipped.');
	} else {
		$ws_log = sprintf(__('mime: %3$s; width: %1$spx; height: %2$spx.'),
			$source_width,
			$source_height,
			$source_mime
		);
	}
?>
			<?php echo $ws_log; ?><br />
<?php
	if(!$image->destination){
		$ws_log = __('<strong>No destination images</strong> set.');
	} else {
		foreach($image->destination as $destination){
			if($destination->height == 'auto' and $destination->width > 0){
				$destination_width = intval($destination->width);
				$destination_height = intval(($destination_width / $source_width) * $source_height);
			}	else if($destination->width == 'auto' and $destination->height > 0){
				$destination_height = intval($destination->height);
				$destination_width = intval(($destination_height / $source_height) * $source_width);
			} else {
				$destination_width = intval($destination->width);
				$destination_height = intval($destination->height);
			}
			$ws_log = sprintf(__('<strong>Creating w:%1$spx x h:%2$spx destination image</strong> from %3$s.'),
				$destination_width,
				$destination_height,
				$source_mime
			);
?>
			<?php echo $ws_log; ?>
<?php
			//$image = new Imagick($source_abspath);
			if($source_mime == 'image/jpeg'){
				$source_image = imagecreatefromjpeg($source_abspath);
			} else if($source_mime == 'image/png'){
				$source_image = imagecreatefrompng($source_abspath);
			} else if($source_mime == 'image/webp'){
				$source_image = imagecreatefromwebp($source_abspath);
			}
			$destination_relpath = $destination->relpath;
			$destination_abspath = $ws_contents_abspath.'/'.$destination_relpath;
			$destination_url = abspath2url($destination_abspath);
			$destination_image = imagecreatetruecolor($destination_width, $destination_height);
			// Resample
			if($destination->alpha == 'true'){
				imagealphablending($destination_image, true);
				$transparent = imagecolorallocatealpha( $destination_image, 0, 0, 0, 127 );
				imagefill( $destination_image, 0, 0, $transparent );
			}
			imagecopyresampled($destination_image, $source_image, 0, 0, 0, 0, $destination_width, $destination_height, $source_width, $source_height);
			// Save file
			if($destination->mime == 'image/jpeg'){
				$destination_extension = 'jpeg';
				$has_saved = imagejpeg($destination_image, $destination_abspath.'.'.$destination_extension, intval($destination->quality));
			} else if($destination->mime == 'image/gif'){
				$destination_extension = 'gif';
				$has_saved = imagegif($destination_image, $destination_abspath.'.'.$destination_extension);
			} else if($destination->mime == 'image/png'){
				$destination_extension = 'png';
				if($destination->alpha == 'true'){
					imagealphablending($destination_image, false);
					imagesavealpha($destination_image, true);
				}
				$has_saved = imagepng($destination_image, $destination_abspath.'.'.$destination_extension, intval((100 - $destination->quality)/10));
			}
			if($has_saved == true){
				$ws_log = sprintf(__('File <a href="%1$s">%2$s</a> saved.'),
					$destination_url.'.'.$destination_extension,
					remove_start($destination_url.'.'.$destination_extension, $ws_custom_url)
				);
?>
						<li><?php echo $ws_log; ?></li>
<?php
			}
			// Save next generation image formats
			// JPEG 2000 (Safari 5+) - supports alpha, RGB/CMYK, 32-bit color space, lossy or lossless
			// JPEG-XR (IE9-11, Edge) - supports alpha, RGB/CMYK, n-channel, lossy or lossless, progressive decoding, 32-bit color spacing
			// Webp (Chrome, Opera, Android Browser, Edge 18+, Firefox 65+) - supports alpha, lossy or lossless.
			$destination_abspath = $destination_abspath.'.webp';
			imagewebp($destination_image, $destination_abspath, intval($destination->quality));
			// Free up memory
			imagedestroy($source_image);
			imagedestroy($destination_image);
		}
	}
}
?>
		</ol>
	</li>
</ol>
