<?php
// WS API for media display.
// @package WS
// @subpackage Media

/**
 * L'indirizzo di un file dentro `ws-custom/contents`.
 *
 * SENZA il prefisso del mount. `ws_href()` lo antepone, ed e' giusto per un
 * indirizzo del sito - una pagina di Meetoo sta sotto `/meetoo` - ma un file di
 * contenuto e' un file: sta dove sta, e il prefisso lo manda a
 * `/meetoo/ws-custom/contents/...`, che non esiste. Non si vedeva perche'
 * l'unico sito che usa `get_media` risponde alla radice; si vede il giorno che
 * un sito innestato mostra un'immagine, e allora sono 404 tutte.
 */
function ws_media_url($relpath){
	return ws_href(WS_CONTENTS_RELPATH.'/'.ltrim((string)$relpath, '/'), array('mount' => false));
}
function get_media($SimpleXMLElement, $args = array()){
	global $ws_query;
	// $args["type"]: svg | png | jpg | gif | pdf | zip | youtube | mov | iframe
	// $args["loading"]: auto | lazy | eager
	$default_args = array(
		'type' => false,
		'fallback' => false,
		'destinationIndex' => 0,
		'output' => false,
		'pictureAttributes' => array(
			'class' => false,
			'style' => false,
		),
		'imgAttributes' => array(
			'class' => false,
			'id' => false,
			'alt' => false,
			'title' => false,
			'style' => false,
			'loading' => 'lazy',
		),
	);
	$args = array_replace_recursive( $default_args, $args );
	if(!empty($ws_query['lang'])){
		$ws_query['lang'];
	}
	if(!empty($SimpleXMLElement[0])){
		$image = $SimpleXMLElement[0];
		$args['imgAttributes']['alt'] = $image->source->alt;
		if($image->source->title){
			$args['imgAttributes']['title'] = $image->source->title;
		} else if($image->source->alt){
			$args['imgAttributes']['title'] = $image->source->alt;
		}
		$destinations = $image->destination;
		if(is_array($destinations) and count($destinations) == 1){
			$mime = explode("/", $image->destination->mime)[1];
			$src = ws_media_url($image->destination->relpath.'.'.$mime);
		} else if(is_array($destinations) and count($destinations) > 1){
			$mime = explode("/", $image->destination[$args['destinationIndex']]->mime)[1];
			$src = ws_media_url($image->destination[$args['destinationIndex']]->relpath.'.'.$mime);
		} else {
			$src = ws_media_url($image->source->relpath);
		}
		if($args['output'] == 'src'){
			return $src;
		} else {
			$pictureAttributes = '';
			foreach ($args['pictureAttributes'] as $pictureAttributeKey => $pictureAttributeValue) {
				if(!empty($pictureAttributeValue)){
					$pictureAttributes .= ' '.$pictureAttributeKey.'="'.$pictureAttributeValue.'"';
				}
			}
			$imgAttributes = '';
			foreach ($args['imgAttributes'] as $imgAttributeKey => $imgAttributeValue) {
				if(!empty($imgAttributeValue)){
					$imgAttributes .= ' '.$imgAttributeKey.'="'.$imgAttributeValue.'"';
				}
			}
			if(!empty($image->destination->relpath)){
				$webp_source = '<source type="image/webp" srcset="'.ws_media_url($image->destination->relpath.'.webp').'" />';
				$jp2_source = '<source type="image/jp2" srcset="'.ws_media_url($image->destination->relpath.'.jp2').'" />';
				$jxr_source = '<source type="image/jxr" srcset="'.ws_media_url($image->destination->relpath.'.jxr').'" />';
			}
			if(explode(".", $image->source->relpath)[1] == 'svg'){
				$svg_source = '<source type="image/svg+xml" srcset="'.ws_media_url($image->source->relpath).'" />';
				$html = '<picture'.$pictureAttributes.'>'.$svg_source.'<img src="'.$src.'"'.$imgAttributes.' /></picture>';
	//			$html = '<object height="100%" width="100%" data="'.$src.'" type="image/svg+xml"'.$pictureAttributes.'><img src="'.$fallback.'"'.$imgAttributes.' /></object>';
			} else {
				$html = '<picture'.$pictureAttributes.'>'.$webp_source.'<img src="'.$src.'"'.$imgAttributes.' /></picture>';
			}
		}
		return $html;
	}
}
function get_svg($url, $args = array()){
	$default_args = array(
		'fallback' => false,
		'output' => 'object',
		'imgAttributes' => array(
			'class' => false,
			'id' => false,
			'alt' => false,
			'style' => false,
		),
	);
	$args = array_replace_recursive( $default_args, $args );
	$attributes_html = '';
	if($args[output] == 'img' or !$fallback){
					$html = '<img src="'.$svg.'" alt="'.$alt.'"'.$microdata.' />';
	} else if($output == 'img-js'){
					$html = '<img src="'.$svg.'" onerror="this.src=\''.$fallback.'\';" alt="'.$alt.'"'.$microdata.' />';
	} else {
					$html = '<object height="100%" width="100%" data="'.$svg.'" type="image/svg+xml" alt="'.$alt.'"'.$microdata.'><img src="'.$fallback.'" alt="'.$alt.'"'.$microdata.' /></object>';
	}
	return $html;
}
