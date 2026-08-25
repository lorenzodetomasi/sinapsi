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
	// Il tipo si riconosce dal file: chi chiama passa un percorso, non sa (e non
	// deve sapere) se quel contenuto oggi vive in XML o in JSON.
	if(strtolower((string)pathinfo(parse_url($abspath, PHP_URL_PATH) ?: $abspath, PATHINFO_EXTENSION)) === 'json'){
		$args['input_type'] = 'json';
	}
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
		/* JSON come contenuto. Il JSON è la fonte (lo scrive l'editor); l'XML,
		 * dove c'è, è solo una copia. Qui l'albero si costruisce in memoria con
		 * LA STESSA conversione che genera i gemelli su disco, così la pagina
		 * servita e il file salvato non possono dire cose diverse.
		 *
		 * `documentURI` non è un dettaglio: senza, gli xi:include relativi non
		 * saprebbero da dove partire e non si risolverebbe niente. */
		require_once( ws_core_abspath() . '/json-to-xml.php' );
		$dom = new DOMDocument();
		$dom->preserveWhiteSpace = $args['preserve_white_space'];
		$dom->formatOutput = $args['format_output'];
		$xml_string = jsonToWsx( (string)file_get_contents($abspath) );
		$prima = libxml_use_internal_errors(true);
		$dom->loadXML($xml_string);
		$dom->documentURI = $abspath;
		if($args['xinclude'] === true){
			$dom->xinclude();
			// I riferimenti puntano a `index.xml`, che per la maggior parte delle
			// entità non esiste: il gemello si genera solo quando serve. Quelli
			// rimasti aperti si risolvono leggendo il JSON dell'entità citata.
			ws_xinclude_da_json($dom, dirname($abspath));
		}
		libxml_clear_errors();
		libxml_use_internal_errors($prima);
		if($args['output_type'] == 'dom'){
			$return = $dom;
		} else if($args['output_type'] == 'string'){
			$return = $dom->saveXML();
		} else {
			$return = ws_simplexml_import_dom($dom);
		}
	}
	return $return;
}

/**
 * Risolve gli `xi:include` che libxml ha lasciato aperti perché il file citato non
 * esiste, ma di cui esiste il JSON. È il caso normale, non l'eccezione: i gemelli
 * XML si generano solo per le entità che servono in altri formati, mentre l'@id di
 * un luogo o di un organizzatore punta sempre e comunque a qualcosa che in JSON c'è.
 *
 * Senza questo, un riferimento puro (solo @id) resterebbe un elemento vuoto: la
 * pagina di un evento perderebbe il nome del luogo, e nessuno saprebbe perché.
 */
function ws_xinclude_da_json(DOMDocument $dom, string $dir, int $profondita = 0){
	if($profondita > 3){
		// Un riferimento circolare non deve diventare un ciclo infinito.
		return;
	}
	$xpath = new DOMXPath($dom);
	$xpath->registerNamespace('xi', 'http://www.w3.org/2001/XInclude');
	$rimasti = $xpath->query('//xi:include');
	if($rimasti->length === 0){
		return;
	}
	require_once( ws_core_abspath() . '/json-to-xml.php' );
	foreach(iterator_to_array($rimasti) as $inclusione){
		$href = $inclusione->getAttribute('href');
		if($href === ''){
			continue;
		}
		$json = ws_riferimento_a_file($dir, $href);
		if($json === ''){
			continue;
		}
		$parte = new DOMDocument();
		$parte->loadXML(jsonToWsx((string)file_get_contents($json)));
		$parte->documentURI = $json;
		$parte->xinclude();
		ws_xinclude_da_json($parte, dirname($json), $profondita + 1);
		$portato = $dom->importNode($parte->documentElement, true);
		$inclusione->parentNode->replaceChild($portato, $inclusione);
	}
}

/**
 * Da un riferimento al file che lo contiene.
 *
 * Si prova prima il percorso letterale, poi — ed è il caso che conta — si cerca
 * l'antenato giusto: gli `@id` sono relativi alla radice del locale, ma chi genera
 * l'XML scrive `../../`, che risale di due livelli. Per un evento
 * (`events/{slug}/`) è esatto; per un luogo (`places/{regione}/{slug}/`) manca un
 * livello, e il riferimento cade nel vuoto senza dire niente. Invece di indovinare
 * quanti `..` servivano, si risale finché non si trova la cartella che contiene
 * davvero la collezione citata.
 */
function ws_riferimento_a_file(string $dir, string $href): string {
	$rif = preg_replace('/index\.xml$/', 'index.json', $href);
	$letterale = ws_percorso_normalizzato($dir . '/' . $rif);
	if(is_file($letterale)){
		return $letterale;
	}
	$nudo = ltrim(preg_replace('#^(\.\./)+#', '', $rif), '/');
	$collezione = explode('/', $nudo)[0];
	$risali = $dir;
	for($i = 0; $i < 6; $i++){
		if(is_dir($risali . '/' . $collezione) and is_file($risali . '/' . $nudo)){
			return $risali . '/' . $nudo;
		}
		$sopra = dirname($risali);
		if($sopra === $risali){
			break;
		}
		$risali = $sopra;
	}
	return '';
}

/** Appiattisce i `..` di un percorso senza chiedere al filesystem (realpath
 *  fallirebbe sui file che non esistono, che è proprio il caso che ci interessa). */
function ws_percorso_normalizzato(string $percorso): string {
	$fuori = [];
	foreach(explode('/', $percorso) as $pezzo){
		if($pezzo === '..'){
			array_pop($fuori);
		} else if($pezzo !== '.'){
			$fuori[] = $pezzo;
		}
	}
	return implode('/', $fuori);
}
function ws_save_file( $data, $abspath, $args = array() ){
	// $data format set in $args['input_type'], default: dom (DOMDocument)
	$default_args = array(
		'input_type' => 'dom',									// The file type to load and how: object | dom | simplexml | json | html \ simpletext
		'preserve_white_space' => false,				// false | true
		'format_output' => true,								// true | false
		'xinclude' => true,											// true | false
		'output_type' => 'xml',									// xml | json
		'file_overwrite' => true,								// false | true
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
	if($args['input_type'] == 'object'){
		if($args['output_type'] == 'json'){
			return file_put_contents($abspath, obj2json($data));
		}
	} else if($args['input_type'] == 'dom'){
		if($args['output_type'] == 'xml'){
			return $data->save($abspath);
		}
	} else if($args['input_type'] == 'simplexml'){
		if($args['output_type'] == 'xml'){
			$DOMDocument = simplexml2dom($data);
			return $DOMDocument->save($abspath);
		}
	} else if($args['input_type'] == 'json'){
		if($output_type == 'json'){
			$string = json_encode($data, $args['format_output']);
		} else if($output_type == 'string'){
			$string = $data;
		}
	}
//	$file = fopen($abspath, 'w');
//	fwrite($file, $string);
//	fclose($file);
}

// Converts a .wsx file to xml and saves it in the same directory of the .wsx file
function wsx_export_xml($wsx_file_abspath){
	$DOMDocument = ws_load_file($wsx_file_abspath, array('output_type' => 'dom'));
	$wsx_file_pathinfo = pathinfo($wsx_file_abspath);
	$xml_file_abspath = $wsx_file_pathinfo['dirname'].'/'.$wsx_file_pathinfo['filename'].'.xml';
	$xml_url = abspath2url($xml_file_abspath);
	$ws_log = sprintf(__('File <a href="%1$s">%2$s</a> (%3$s bytes) saved.'),
		$xml_url,
		remove_start($xml_url, ws_custom_url()),
		ws_save_file($DOMDocument, $xml_file_abspath)
	);
?>
		<li><?php echo $ws_log; ?></li>
<?php
}

// Recursive function to turn a DOMDocument element to an array
// @param DOMDocument $root the document (might also be a DOMElement/DOMNode?)
function ws_merge_xml( $xml1, $xml2, $args = array() ){
	// $dom1
	// $dom2
	$default_args = array(
		'input_type' => 'dom',				// The file type to load and how: dom | simplexml | json | html \ simpletext
		'preserve_white_space' => false,	// false | true
		'format_output' => true,			// true | false
		'xinclude' => true,					// true | false
		'output_type' => 'xml',				// xml | json
		'file_timestamp' => false			// false | true = WS_DATETIME_FORMAT | string Useful for archive/diff proposals, adds a timestamp to the file basename [filename]-[file_timestamp].[extension];
											// if (bool)true, by default gets constant WS_DATETIME_FORMAT = 'Ymd\THis\Z' ([yyyymmdd]T[hhmmss]Z).
	);
	$args = array_merge( $default_args, $args = array() );
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

function simplexml2dom($simplexml, $args = array()){
	$default_args = array(
		'preserve_white_space' => false,				// false | true
		'format_output' => true,								// true | false
		'xinclude' => true,											// true | false
	);
	$args = array_merge( $default_args, $args = array() );
	$DOMDocument = new DOMDocument('1.0', 'UTF-8');
	$DOMDocument->preserveWhiteSpace = $args['preserve_white_space'];
	$DOMDocument->formatOutput = $args['format_output'];
	$DOMDocument->loadXML($simplexml->asXML());
	return $DOMDocument;
}
function json2obj($json){
	return json_decode($json);
}
function obj2json($object, $args = array()){
	$default_args = array(
		'format_output' => true,	// true | false
	);
	$args = array_merge( $default_args, $args = array() );
	if($args['format_output'] == true){
		return json_encode($object, JSON_PRETTY_PRINT);
	} else {
		return json_encode($object);
	}
}
/*
function simplexml2array($simplexml){
	$array = json_decode( json_encode($simplexml), 1);
	return $array;
}
*/
function simplexml2array($simplexml, $args = array()) {
    $defaults = array(
        'namespaceSeparator' => ':',//you may want this to be something other than a colon
        'attributePrefix' => '@',   //to distinguish between attributes and nodes with the same name
        'alwaysArray' => array(),   //array of xml tag names which should always become arrays
        'autoArray' => true,        //only create arrays for tags which appear more than once
        'textContent' => '$',       //key used for the text content of elements
        'autoText' => true,         //skip textContent key if node has no attributes or child nodes
        'keySearch' => false,       //optional search and replace on tag and attribute names
        'keyReplace' => false       //replace values for above search values (as passed to str_replace())
    );
    $args = array_merge($defaults, $args);
    $namespaces = $simplexml->getDocNamespaces();
    $namespaces[''] = null; //add base (empty) namespace

    //get attributes from all namespaces
    $attributesArray = array();
    foreach ($namespaces as $prefix => $namespace) {
        foreach ($simplexml->attributes($namespace) as $attributeName => $attribute) {
            //replace characters in attribute name
            if ($args['keySearch']) $attributeName =
                    str_replace($args['keySearch'], $args['keyReplace'], $attributeName);
            $attributeKey = $args['attributePrefix']
                    . ($prefix ? $prefix . $args['namespaceSeparator'] : '')
                    . $attributeName;
            $attributesArray[$attributeKey] = (string)$attribute;
        }
    }
    //get child nodes from all namespaces
    $tagsArray = array();
    foreach ($namespaces as $prefix => $namespace) {
        foreach ($simplexml->children($namespace) as $childXml) {
            //recurse into child nodes
            $childArray = simplexml2array($childXml, $args);
						// Updated list($childTagName, $childProperties) = each($childArray);
						$childTagName = key($childArray);
						$childProperties = current($childArray);
            //replace characters in tag name
            if ($args['keySearch']) $childTagName =
                    str_replace($args['keySearch'], $args['keyReplace'], $childTagName);
            //add namespace prefix, if any
            if ($prefix) $childTagName = $prefix . $args['namespaceSeparator'] . $childTagName;

            if (!isset($tagsArray[$childTagName])) {
                //only entry with this key
                //test if tags of this type should always be arrays, no matter the element count
                $tagsArray[$childTagName] =
                        in_array($childTagName, $args['alwaysArray']) || !$args['autoArray']
                        ? array($childProperties) : $childProperties;
            } elseif (
                is_array($tagsArray[$childTagName]) && array_keys($tagsArray[$childTagName])
                === range(0, count($tagsArray[$childTagName]) - 1)
            ) {
                //key already exists and is integer indexed array
                $tagsArray[$childTagName][] = $childProperties;
            } else {
                //key exists so convert to integer indexed array with previous value in position 0
                $tagsArray[$childTagName] = array($tagsArray[$childTagName], $childProperties);
            }
        }
    }
    //get text content of node
    $textContentArray = array();
    $plainText = trim((string)$simplexml);
    if ($plainText !== '') $textContentArray[$args['textContent']] = $plainText;
    //stick it all together
    $propertiesArray = !$args['autoText'] || $attributesArray || $tagsArray || ($plainText === '')
            ? array_merge($attributesArray, $tagsArray, $textContentArray) : $plainText;
    //return node as array
    return array(
        $simplexml->getName() => $propertiesArray
    );
}
// Converts a Php Array to SimpleXMLElement
// https://stackoverflow.com/questions/1397036/how-to-convert-array-to-simplexml
// Problems with https://www.codexworld.com/convert-array-to-xml-in-php/
function array2simplexml($array, &$SimpleXMLElement = null, $numericKey = null, $numericParentSimpleXMLElement = null ) {
	foreach( $array as $key => $value ) {
		if( is_numeric($key) ){
			$key = $numericKey; //dealing with <0/>..<n/> issues
		}
		if( is_array($value) ) {
			if (isset($value[0])){
				array2simplexml($value, $SimpleXMLElement, $key );
			} else {
				$subnode = $SimpleXMLElement->addChild($key);
				array2simplexml($value, $subnode, $key, $SimpleXMLElement);
			}
		} else {
			$SimpleXMLElement->addChild("$key",htmlspecialchars("$value"));
		}
	}
}
function array_is_numeric($array){
	if (array() === $array) return true;
}
// Converts a Php Array to DOMDocument
// @param array       $array the array
// @param DOMDocument $DOMDocument
function array2dom($array, $DOMDocument = false, $parentDOMElement = false, $currentDOMElement = false) {
	if(!empty($DOMDocument) and empty($parentDOMElement)){
		$parentDOMElement = $DOMDocument;
	}
	foreach($array as $key => $value) {
		if(!$currentDOMElement){
			$currentDOMElement = $DOMDocument->createElement($key, (is_array($value) ? false : $value));
		}
		if(is_array($value)){
			echo "<p>$key is array:</p>";
			print_r($value);
			if(is_numeric($key)){
				echo "<p>$key is numeric.</p>";
			} else {
				print_r($currentDOMElement);
				echo "<p>$key is not numeric. Parent:</p>";
				print_r($parentDOMElement);
				$parentDOMElement->appendChild($currentDOMElement);
				echo "<p>$key appended.</p>";
				array2dom($value, $DOMDocument, $currentDOMElement);
			}
		} else {
			print_r($currentDOMElement);
			echo "<p>$key is not array. Parent:</p>";
			print_r($parentDOMElement);
			$parentDOMElement->appendChild($currentDOMElement);
			echo "<p>$key appended.</p>";
		}
	}
}
/*
function array2dom($array, $DOMDocument = null, $DOMElement = null, $parentKey = null) {
	foreach($array as $key => $value) {
		if(is_array($value)) {
			echo $key.' is_array<br />';
			if(!is_numeric($key)){
				echo $key.' is_not_numeric<br />';
				$child = $DOMDocument->createElement("$key", (is_array($value) ? null : $value));
				if($DOMElement){
					$DOMElement->appendChild($child);
					echo "Child element $key created.<br />";
				} else {
					$DOMDocument->appendChild($child);
					echo "Element $key created.<br />";
				}
				if(is_array($value)){
					echo "$key has children.<br />";
//					print_r($value);
					array2dom($value, $DOMDocument, $child, $key);
				}
			} else {
				echo $key.' is_numeric<br />';
				print_r($value);
				$child = $DOMDocument->createElement("$parentKey", (is_array($value) ? null : $value));
				if($DOMElement){
					$DOMElement->appendChild($child);
				} else {
					$DOMDocument->appendChild($child);
				}
				if(is_array($value)){
					echo "$key has children.<br />";
//					print_r($value);
					array2dom($value, $DOMDocument, $child, $key);
				}
			}
		} else {
			echo $key.' is_not_array<br />';
			$child = $DOMDocument->createElement("$key",htmlspecialchars("$value"));
			if($DOMElement){
				$DOMElement->appendChild($child);
				echo "Child element $key created.<br />";
			} else {
				$DOMDocument->appendChild($child);
				echo "Element $key created.<br />";
			}
		}
	}
}
*/
function dom2array(DOMDocument $dom) {
    $root = $dom->documentElement;
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
function dom2json(DOMDocument $dom) {
    $root = $dom->documentElement;
    $array = [
        $root->nodeName => dom2array($root)
    ];
    return json_encode($array, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
//function json2dom(string|array $json): DOMDocument {
function json2dom( $json): DOMDocument {
	// Converte una stringa JSON o un array associativo in un oggetto DOMDocument.
	// @param string|array $json Il JSON di origine o l'array già decodificato.
	// @return DOMDocument
	// @throws Exception Se il JSON è invalido o non ha una radice definita.
    $data = is_array($json) ? $json : json_decode($json, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("JSON non valido: " . json_last_error_msg());
    }

    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->formatOutput = true;
    $dom->preserveWhiteSpace = false;

    // Rimuove l'eventuale chiave @context (usata in JSON-LD) prima di cercare la radice
    unset($data['@context']);

    // Identifica il nodo radice (la prima chiave dell'array)
    $rootName = array_key_first($data);
    if ($rootName === null) {
        throw new Exception("Struttura dati non valida: radice mancante.");
    }

    // Avvia la conversione ricorsiva
    $rootNode = arrayToNode($data[$rootName], $rootName, $dom);
    $dom->appendChild($rootNode);

    return $dom;
}

function arrayToNode(mixed $data, string $name, DOMDocument $dom): DOMElement {
	// Funzione ricorsiva di supporto per mappare l'array sui nodi del DOM.
    $node = $dom->createElement($name);

    if (!is_array($data)) {
        // Se il valore è scalare (stringa, numero), crea il contenuto testuale
        $node->appendChild(createSmartTextNode($dom, (string)$data));
        return $node;
    }

    // 1. Gestione Attributi (chiave '@')
    if (isset($data['@']) && is_array($data['@'])) {
        foreach ($data['@'] as $attrName => $attrValue) {
            $node->setAttribute($attrName, (string)$attrValue);
        }
    }

    // 2. Gestione Nodo di Testo / CDATA (chiave '#text')
    if (isset($data['#text'])) {
        $node->appendChild(createSmartTextNode($dom, (string)$data['#text']));
    }

    // 3. Gestione Nodi Figli
    foreach ($data as $key => $value) {
        // Salta le chiavi speciali già gestite
        if ($key === '@' || $key === '#text') {
            continue;
        }

        if (is_array($value) && array_is_list($value)) {
            // Caso: Tag ripetuti (array numerico in JSON)
            foreach ($value as $item) {
                $node->appendChild(arrayToNode($item, $key, $dom));
            }
        } else {
            // Caso: Nodo singolo
            $node->appendChild(arrayToNode($value, $key, $dom));
        }
    }
    return $node;
}
//function createSmartTextNode(DOMDocument $dom, string $text): DOMText|DOMCharacterData {
function createSmartTextNode(DOMDocument $dom, string $text) {
	// Decide se creare un TextNode semplice o una sezione CDATA in base al contenuto (presenza di tag HTML/XML).
    if (str_contains($text, '<') || str_contains($text, '&')) {
        return $dom->createCDATASection($text);
    }
    return $dom->createTextNode($text);
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
function remove_attributes($simplexml){
	$result = $xml->xpath("//@xml:base");
	foreach ($result as $node) {
    unset($node[0]);
	}
	return $xml;
}
?>
