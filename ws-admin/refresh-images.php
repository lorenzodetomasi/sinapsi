<?php
// Refresh all images in single .wsx or .xml files.
global $ws_logs;
$ws_contents_abspath = ws_contents_abspath();

function ws_save_xml($wsx_file_abspath){
	$wsx_file_pathinfo = pathinfo($wsx_file_abspath);
	$xml_file_relpath = $wsx_file_pathinfo['filename'].'.xml';
	$xml_file_abspath = $wsx_file_pathinfo['dirname'].'/'.$xml_file_relpath;
	$xml_url = abspath2url($xml_file_abspath);
	$DOMDocument = ws_load_file($wsx_file_abspath, array('output_type' => 'dom'));
	$ws_log = sprintf(__('File <a href="%1$s">%2$s</a> (%3$s bytes) saved.'),
		$xml_url,
		remove_start($xml_url, ws_custom_url()),
		ws_save_file($DOMDocument, $xml_file_abspath)
	);
?>
		<li><?php echo $ws_log; ?></li>
<?php
}
// Refresh images
if((isset($_GET['content']) and !empty($_GET['content']))){
?>
<h1><?php printf(__('Refreshing images in %s.'), '<code>'.$_GET['content'].'</code>'); ?></h1>
<ol>
<?php
	if (!extension_loaded('imagick')){
		$ws_log = __('Imagick not installed.');
?>
	<li><?php echo $ws_log; ?></li>
<?php
	}
	$simplexml = ws_content($_GET['content']);
	$images = $simplexml->xpath('//image');
	foreach($images as $image){
		$source_relpath = $image->source->relpath;
		$source_abspath = $ws_contents_abspath.'/'.$source_relpath;
		$source_url = abspath2url($source_abspath);
		if(!file_exists($source_abspath)){
			$ws_log = sprintf(__('<strong>Source image not found</strong> at <a href="%1$s">%2$s</a>.'),
				$source_url,
				'<code>contents/'.$image->source->relpath.'</code>'
			);
?>
	<li><?php echo $ws_log; ?></li>
<?php
		} else {
			$ws_log = sprintf(__('Processing <strong>source image</strong> file <a href="%1$s">%2$s</a>.'),
				$source_url,
				'<code>contents/'.$image->source->relpath.'</code>'
			);
?>
		<?php echo $ws_log; ?><br />
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
		<?php echo $ws_log; ?><br />
	</li>
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
		<?php echo $ws_log; ?>
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
	}
} else {
	$ws_log .= __('Add <code>/?content=[path]</code> to this url. <br />Eg. <code>/?content=localbiz/en/privacy</code>');
?>
		<li><?php echo $ws_log; ?></li>
<?php
}
?>
</ol>
