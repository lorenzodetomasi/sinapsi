<?php
// Alias for simplexml_import_dom()
// @return	WS_SimpleXMLElement
function ws_simplexml_import_dom($dom) {
	$args = func_get_args();

	if (isset($args[0]) && !isset($args[1]))
	{
		$args[1] = 'WS_SimpleXMLElement';
	}

	return call_user_func_array('simplexml_import_dom', $args);
}
// Alias for simplexml_load_file()
// @return	WS_SimpleXMLElement
function ws_simplexml_load_file($filename) {
	$args = func_get_args();

	if (isset($args[0]) && !isset($args[1]))
	{
		$args[1] = 'WS_SimpleXMLElement';
	}

	return call_user_func_array('simplexml_load_file', $args);
}
// Alias for simplexml_load_string()
// @return	WS_SimpleXMLElement
function ws_simplexml_load_string($string) {
	$args = func_get_args();

	if (isset($args[0]) && !isset($args[1]))
	{
		$args[1] = 'SimpleDOM';
	}

	return call_user_func_array('simplexml_load_string', $args);
}

class WS_SimpleXMLElement extends SimpleXMLElement {
	// Return the content of current node as a string
	// Roughly emulates the innerHTML property found in browsers, although it is not meant to perfectly match any specific implementation.
	// @todo Write a test for HTML entities that can't be represented in the document's encoding
	// @return	string			Content of current node
	public function innerHTML() {
		$dom = dom_import_simplexml($this);
		$doc = $dom->ownerDocument;

		$html = '';
		$childNodes = $dom->childNodes;
		if($childNodes->length > 0){
			foreach ($dom->childNodes as $child){
				$html .= ($child instanceof DOMText) ? $child->textContent : $doc->saveXML($child);
			}
		} else {
			$html = $this->textContent;
		}

		return $html;
	}
	//Return the XML representing this node and its child nodes
	// NOTE: unlike asXML() it doesn't return the XML prolog
	// @return	string			Content of current node
	public function outerXML()
	{
		$dom = dom_import_simplexml($this);
		return $dom->ownerDocument->saveXML($dom);
	}
	// Return the XML content of current node as a string
	// @return	string			Content of current node
	public function innerXML()
	{
		$xml = $this->outerXML();
		$pos = 1 + strpos($xml, '>');
		$len = strrpos($xml, '<') - $pos;
		return substr($xml, $pos, $len);
	}
/*
	public function prependChild(DOMNode $newnode)
	{
		$tmp = dom_import_simplexml($this);
	}
*/
}
?>
