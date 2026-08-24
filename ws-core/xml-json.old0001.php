<?php
function innerHTML($xml, $type = 'simplexml'){
	//$xml: SimpleXMLElement
	$innerXML= '';
	if($type == 'simplexml'){
		foreach (dom_import_simplexml($xml)->childNodes as $child){
			$innerXML .= $child->ownerDocument->saveXML( $child );
		}
	} else {
		return false;
	}
	return $innerXML;
};
function innerXML($xml){
	$innerXML= '';
	if(!empty($xml)){
		if(get_class($xml) == 'SimpleXMLElement'){
			$childNodes = dom_import_simplexml($xml)->childNodes;
			if($childNodes->length > 0){
				foreach ($childNodes as $childNode){
					$innerXML .= $childNode->ownerDocument->saveXML( $childNode );
				}
			}
		} else {
			return false;
		}
		return $innerXML;
	}
};
?>
