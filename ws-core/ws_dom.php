<?php
class WS_DOMDocument extends DOMDocument {
 public function createElement($name, $value=null) {
  $orphan = new WS_DOMElement($name, $value); // new  sub-class object
  $docFragment = $this->createDocumentFragment(); // lightweight container maintains "ownerDocument"
  $docFragment->appendChild($orphan); // attach
  $ret = $docFragment->removeChild($orphan); // remove
  return $ret; // ownerDocument set; won't be destroyed on  method exit
 }
 // .. more class definition
}

class WS_DOMElement extends DOMElement {
 function __construct($name, $value='', $namespaceURI=null) {
  parent::__construct($name, $value, $namespaceURI);
 }
  //  ... more class definition here
}

class WS_DOMNode extends DOMNode {
	public function prependChild(DOMNode $newnode)
	{
		$firstChild = $this->firstChild;
		$this->insertBefore($newnode, $firstChild);
	}
}
//$DOMDocument = $this->ownerDocument;
?>
