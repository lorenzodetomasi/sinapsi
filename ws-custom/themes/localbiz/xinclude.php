<?php
global $ws_content;
$relpath = 'ws/';
$basename = 'index.xml';
$folder_abspath = ws_contents_abspath()."/$relpath";
$file_abspath = $folder_abspath.$basename;
$fileDOMDocument = ws_load_file( $file_abspath, $args = array( 'format_output' => true, 'xinclude' => false, 'output_type' => 'dom' ) );
$fileDOMXpath = new DOMXpath($fileDOMDocument);
$fileDOMXpathQuery = $fileDOMXpath->query('//*[1]/datePublished/text()');
function xinclude_recursive(){
//	if($args['xinclude'] == 'recursive'){
		$XIncludeNamespaceURI = 'http://www.w3.org/2001/XInclude';
		$XIncludeXmlnsPrefix = $dom->documentElement->lookupPrefix($XIncludeNamespaceURI);
		$xincludeDOMElements = $dom->getElementsByTagNameNS($XIncludeNamespaceURI, '*');
		foreach($xincludeDOMElements as $xincludeDOMElement){
			$hrefValue = $xincludeDOMElement->getAttribute('href');
			$xpointerValue = $xincludeDOMElement->getAttribute('xpointer');
			$baseURI = pathinfo($xincludeDOMElement->baseURI);
			$xinclude_abspath = $baseURI['dirname'].'/'.$hrefValue;
			preg_match_all("/(\w+)\((.*?)\)(?=\w+\(|$)/", $xpointerValue, $match);
			$result = array_combine($match[1], $match[2]);
//				print_r( $result );
			$xpathString = $result['xpointer'];
			$xincludeDOMDocument = ws_load_file( $xinclude_abspath, array('output_type' => 'dom') );
//				print_r($xincludeDOMDocument); echo '<br />';
			$xincludeDOMXpath = new DOMXpath($xincludeDOMDocument);
			$xincludeQuery = $xincludeDOMXpath->query($xpathString);
			print_r($xincludeDOMElement->parentNode);
//				$xincludeDOMElement->parentNode->replaceChild($xincludeDOMElement, $xincludeQuery);
//				print_r($dom);
		}
//	} else {}
}
include_template('template-parts/header');
?>
		<main>
<?php
echo $fileDOMXpathQuery[0]->nodeValue;

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
?>
				<section>
					<h1>Log</h1>
					<ol>
<?php
foreach ($logs as $log) {
?>
						<li><?php echo $log; ?></li>
<?php
}
?>
					</ol>
				</section>
			</div>
		</main>
<?php
include_template('template-parts/footer');
?>
