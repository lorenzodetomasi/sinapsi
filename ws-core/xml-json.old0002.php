<?php
function ws_load_file( $abspath, $args = array() ){
	global $ws_logs;
	// @return
	if (filter_var($abspath, FILTER_VALIDATE_URL) === FALSE) {
		if(!file_exists($abspath)){
			return false;
			die();
		}
	}
	$default_args = array(
		'input_type' => 'xml',						// The file type to load and how: xml | json |
		'preserve_white_space' => false,	// false | true
		'format_output' => true,					// false | true
		'xinclude' => true,								// true | false
		'output_type' => 'simplexml',			// dom | simplexml
	);
	$args = array_intersect_key( $args, $default_args );
	$args = array_merge( $default_args, $args );
	if($args['input_type'] == 'xml'){
		$dom = new DOMDocument();
		$dom->preserveWhiteSpace = $args['preserve_white_space'];
		$dom->formatOutput = $args['format_output'];
		$dom->load($abspath) or die(sprintf(__('Unable to load file %1$s.', 'ws').' '.__('<a href="%2$s">Debug</a>', 'ws'), $abspath, abspath2url($abspath)));
		if($args['xinclude'] === true){
			$dom->xinclude();
		}
		if($args['output_type'] == 'simplexml'){
			$simplexml = ws_simplexml_import_dom($dom);
			$return = $simplexml;
		} else if($args['output_type'] == 'dom'){
			$return = $dom;
		}
	} else if($args['input_type'] == 'json'){
		$json = file_get_contents($abspath);
		if($output_type == 'string'){
			$return = $json;
		}	elseif($output_type = 'obj'){
			$return = json2obj($json);
		}
	}
	return $return;
}
function ws_save_file( $data, $abspath, $args = array() ){
	// $data format set in $args['input_type'], default: dom (DOMDocument)
	$default_args = array(
		'input_type' => 'dom',									// The file type to load and how: dom | simplexml | json | html \ simpletext
		'preserve_white_space' => false,				// false | true
		'format_output' => true,								// true | false
		'xinclude' => true,											// true | false
		'output_type' => 'xml',									// xml | json
		'file_timestamp' => false								// false | true = WS_DATETIME_FORMAT | string Useful for archive/diff proposals, adds a timestamp to the file basename [filename]-[file_timestamp].[extension];
																						// if (bool)true, by default gets constant WS_DATETIME_FORMAT = 'Ymd\THis\Z' ([yyyymmdd]T[hhmmss]Z).
	);
	$args = array_merge( $default_args, $args );
	if($args['file_timestamp'] == true){
		if($args['file_timestamp'] === true){
			$args['file_timestamp'] = WS_DATETIME_FORMAT;
		}
		// http://php.net/manual/en/function.pathinfo.php
		$abspathinfo = pathinfo($abspath);
		$abspath = $abspathinfo['dirname'].'/'.$abspathinfo['filename'].'-'.date(WS_DATETIME_FORMAT).'.'.$abspathinfo['extension'];
	}
	if($args['input_type'] == 'dom'){
		if($args['output_type'] == 'xml'){
			return $data->save($abspath);
		}
	} else if($args['input_type'] == 'simplexml'){
		if($args['output_type'] == 'xml'){
			return $data->asXML($abspath);
		}
	} else if($args['input_type'] == 'json'){
		if($output_type == 'json'){
			$string = json_encode($data);
		} else if($output_type == 'string'){
			$string = $data;
		}
	}
//	$file = fopen($abspath, 'w');
//	fwrite($file, $string);
//	fclose($file);
}
// Recursive function to turn a DOMDocument element to an array
// @param DOMDocument $root the document (might also be a DOMElement/DOMNode?)
function ws_merge_xml( $xml1, $xml2, $args = array() ){
	// $dom1
	// $dom2
	$default_args = array(
		'input_type' => 'dom',									// The file type to load and how: dom | simplexml | json | html \ simpletext
		'preserve_white_space' => false,				// false | true
		'format_output' => true,								// true | false
		'xinclude' => true,											// true | false
		'output_type' => 'xml',									// xml | json
		'file_timestamp' => false								// false | true = WS_DATETIME_FORMAT | string Useful for archive/diff proposals, adds a timestamp to the file basename [filename]-[file_timestamp].[extension];
																						// if (bool)true, by default gets constant WS_DATETIME_FORMAT = 'Ymd\THis\Z' ([yyyymmdd]T[hhmmss]Z).
	);
	$args = array_merge( $default_args, $args );
	if($args['input_type'] == 'dom'){
		// Get DOMDocuments Roots
		$DOMDocument1Root = '';
		$DOMDocument2Root = '';
		// Iterate over children elements of $DOMDocument2Root
		$DOMDocument2RootChildren = $DOMDocument2Root->getElementsByTagName('item');
		for ($i = 0; $i < $items2->length; $i ++) {
				$item2 = $items2->item($i);

				// import/copy item from document 2 to document 1
				$item1 = $doc1->importNode($item2, true);

				// append imported item to document 1 'res' element
				$res1->appendChild($item1);

		}
	}
}
function dom2array($root) {
    $array = array();
    //list attributes
    if($root->hasAttributes()) {
        foreach($root->attributes as $attribute) {
            $array['_attributes'][$attribute->name] = $attribute->value;
        }
    }
    //handle classic node
    if($root->nodeType == XML_ELEMENT_NODE) {
        $array['_type'] = $root->nodeName;
        if($root->hasChildNodes()) {
            $children = $root->childNodes;
            for($i = 0; $i < $children->length; $i++) {
                $child = Dom2Array( $children->item($i) );
                //don't keep textnode with only spaces and newline
                if(!empty($child)) {
                    $array['_children'][] = $child;
                }
            }
        }
    //handle text node
    } elseif($root->nodeType == XML_TEXT_NODE || $root->nodeType == XML_CDATA_SECTION_NODE) {
        $value = $root->nodeValue;
        if(!empty($value)) {
            $array['_type'] = '_text';
            $array['_content'] = $value;
        }
    }
    return $array;
}
// Recursive function to turn an array to a DOMDocument
// @param array       $array the array
// @param DOMDocument $doc   only used by recursion
function array2dom($array, $doc = null, $args = array()) {
    if($doc == null) {
        $doc = new DOMDocument();
        $doc->formatOutput = true;
        $currentNode = $doc;
    } else {
        if(isset($array['_type']) and $array['_type'] == '_text')
            $currentNode = $doc->createTextNode($array['_content']);
        else
            $currentNode = $doc->createElement($array['_type']);
    }
    if(isset($array['_type']) and $array['_type'] != '_text') {
        if(isset($array['_attributes'])) {
            foreach ($array['_attributes'] as $name => $value) {
                $currentNode->setAttribute($name, $value);
            }
        }
        if(isset($array['_children'])) {
            foreach($array['_children'] as $child) {
                $childNode = Array2Dom($child, $doc);
                $childNode = $currentNode->appendChild($childNode);
            }
        }
    }
    return $currentNode;
}
function xml2json($xml){
	return $json;
}
function simplexml2array($simplexml){
	$array = json_decode( json_encode($simplexml), 1);
	return $array;
}
function array2xml($array){
	return $xml;
}
function json2xml($json){
	return $xml;
}
function json2obj($json){
	return json_decode($json);
}
function obj2json($object){
	return json_encode($object);
}
function xinclude_file2xml_file($xinclude_file_abspath){
	// Load an xml file with xincludes and save it in xml format, in the same dir and with the same filename, changing only the extension
	$DOMDocument = ws_load_file($xinclude_file_abspath, array('output_type' => 'dom'));
	$xinclude_file_pathinfo = pathinfo($xinclude_file_abspath);
	$xml_file_relpath = $xinclude_file_pathinfo['filename'].'.xml';
	$xml_file_abspath = $xinclude_file_pathinfo['dirname'].'/'.$xml_file_relpath;
	$ws_log = sprintf(__('File <a href="%1$s">%2$s</a> (%3$s bytes) saved.'),
		abspath2url($xml_file_abspath),
		basename($xml_file_abspath),
		ws_save_file($DOMDocument, $xml_file_abspath)
	);
	return $ws_log;
}
?>
