<?php
function validate_ws_json($jsonString) {
    $data = json_decode($jsonString);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['valid' => false, 'errors' => [json_last_error_msg()]];
    }
    // Qui puoi aggiungere validazioni di logica (es. check campi duplicati, chiavi specifiche)
    return ['valid' => true];
}

function validate_ws_xml($xmlString) {
    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $isValid = $doc->loadXML($xmlString);
    if (!$isValid) {
        $errors = libxml_get_errors();
        libxml_clear_errors();
        return ['valid' => false, 'errors' => array_map(fn($e) => $e->message, $errors)];
    }
    return ['valid' => true];
}
?>
<?php
/**
 * Converte JSON (Schema.org) in XML (Meetoo)
 * - FIX XInclude sugli array e Namespace Custom -
 */
function jsonToWsx(string $jsonString): string {
    $data = json_decode($jsonString, true);
    if (!$data) return '{"error": "JSON non valido"}';

    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->formatOutput = true;

    // Gestione Tipo Radice: invertiamo l'array dei tipi
    $types = (array)($data['@type'] ?? 'Event');
    $rootName = $types[0]; // Il primo rimane il nome del tag (es. Place)
    $root = $dom->createElement($rootName);
    
    // Invertiamo l'ordine: dal più specifico al più generale
    $reversedTypes = array_reverse($types);
    $xsiTypeString = implode(' ', $reversedTypes);
    
    // Assegnazione Namespace
    $root->setAttribute('xmlns', 'https://schema.org');
    $root->setAttribute('xmlns:meetoo', 'https://meetoo.eu');
    $root->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
    $root->setAttribute('xmlns:xi', 'http://www.w3.org/2001/XInclude');
    
    // Assegnazione xsi:type concatenato e invertito
    $root->setAttribute('xsi:type', $xsiTypeString);
    
    if (isset($data['@id'])) $root->setAttribute('id', $data['@id']);

    $dom->appendChild($root);

    $buildXml = function($dataArray, $parentElement, $parentKey = '') use (&$buildXml, $dom) {
        foreach ($dataArray as $key => $value) {
            
            // Regola Metadati '@'
            if (strpos($key, '@') === 0) {
                if ($key === '@id') {
                    $parentElement->setAttribute('id', (string)$value);
                } elseif ($key === '@type') {
                    // Se stiamo processando un nodo meetoo, usa 'type' normale, altrimenti xsi:type
                    if ($parentKey === 'meetoo' || $parentKey === 'meetoo:meetoo') {
                        $parentElement->setAttribute('type', (string)(is_array($value) ? $value[0] : $value));
                    } else {
                        $subTypes = (array)$value;
                        $parentElement->setAttribute('xsi:type', $subTypes[1] ?? $subTypes[0]);
                    }
                } elseif ($key !== '@context') {
                    $attrName = substr($key, 1);
                    $parentElement->setAttribute($attrName, is_array($value) ? implode(' ', $value) : (string)$value);
                }
                continue; 
            }

            // Fix per gestire eventuale chiave "meetoo" che deve diventare "meetoo:meetoo"
            $nodeName = ($key === 'meetoo' && is_array($value)) ? 'meetoo:meetoo' : $key;

            // Array Sequenziale (es. organizers)
            if (is_array($value) && array_keys($value) === range(0, count($value) - 1)) {
                foreach ($value as $item) {
                    $node = $dom->createElement($nodeName);
                    if (is_array($item)) {
                        $buildXml($item, $node, $key); 
                        
                        // FIX: Controllo XInclude basato sulle chiavi originarie, non sugli attributi del DOM
                        $cleanKeys = array_diff(array_keys($item), ['@id', '@type', '@context']);
                        if (isset($item['@id']) && empty($cleanKeys)) {
                            $idVal = $item['@id'];
                            $xi = $dom->createElement('xi:include');
                            $xi->setAttribute('href', '../' . $idVal . '/index.xml');
                            $xi->setAttribute('xpointer', 'xpointer(/*[1])');
                            $node->appendChild($xi);
                        }
                    } else {
                        $strItem = is_bool($item) ? ($item ? 'true' : 'false') : (string)$item;
                        $node->nodeValue = htmlspecialchars($strItem);
                    }
                    $parentElement->appendChild($node);
                }
            } 
            // Oggetto Associativo (es. location)
            else if (is_array($value)) {
                $node = $dom->createElement($nodeName);
                
                if (isset($value['type'])) {
                    $node->setAttribute('type', $value['type']);
                    unset($value['type']);
                }

                $buildXml($value, $node, $key); 

                // FIX: Controllo XInclude basato sulle chiavi
                $cleanKeys = array_diff(array_keys($value), ['@id', '@type', '@context']);
                if (isset($value['@id']) && empty($cleanKeys)) {
                    $idVal = $value['@id'];
                    $xi = $dom->createElement('xi:include');
                    $xi->setAttribute('href', '../' . $idVal . '/index.xml');
                    $xi->setAttribute('xpointer', 'xpointer(/*[1])');
                    $node->appendChild($xi);
                }
                
                $parentElement->appendChild($node);
            } 
            // Valori Scalari
            else {
                $node = $dom->createElement($nodeName);
                $strValue = is_bool($value) ? ($value ? 'true' : 'false') : (string)$value;
                $node->appendChild($dom->createTextNode($strValue));
                $parentElement->appendChild($node);
            }
        }
    };

    $bodyData = array_diff_key($data, ['@context' => '', '@type' => '', '@id' => '']);
    $buildXml($bodyData, $root, $rootName);
    
    return $dom->saveXML();
}
?>
<?php
/**
 * Converte XML (Meetoo) in JSON (Schema.org)
 * - IMPLEMENTAZIONE REGOLA INVERSA TIPI -
 */
function WsxToJson(string $xmlString): string {
    $xmlString = str_replace(['xi:include', 'xsi:type'], ['xi_include', 'xsi_type'], $xmlString);
    $xml = simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NOCDATA);
    if (!$xml) return '{"error": "XML non valido"}';

    $rootName = $xml->getName();
    $attributes = $xml->attributes();
    
    // REGOLA INVERSA: Ricostruzione array tipi
    // Se xsi:type contiene più parole, le esplodiamo e invertiamo l'ordine
    $typeArray = [$rootName];
    if (isset($attributes['xsi_type'])) {
        $parts = explode(' ', (string)$attributes['xsi_type']);
        // Se l'ultima parte dell'xsi:type è uguale al nome del nodo, la ignoriamo (è ridondante)
        foreach (array_reverse($parts) as $part) {
            if ($part !== $rootName && !in_array($part, $typeArray)) {
                $typeArray[] = $part;
            }
        }
    }

    $result = [
        '@context' => 'https://schema.org',
        '@type' => count($typeArray) === 1 ? $typeArray[0] : $typeArray
    ];

    if (isset($attributes['id'])) $result['@id'] = (string)$attributes['id'];

    $parseNode = function($element) use (&$parseNode) {
        $data = [];
        foreach ($element->children() as $name => $child) {
            // Gestione Namespace custom (es meetoo:meetoo -> meetoo)
            $cleanName = str_replace('meetoo_', '', $name);
            
            // XInclude check
            if ($name === 'xi_include') {
                $href = (string)$child->attributes()['href'];
                $id = str_replace(['../', '/index.xml'], '', $href);
                return ['@id' => $id];
            }

            $childData = $child->count() > 0 ? $parseNode($child) : (string)$child;
            
            // Gestione attributi xsi_type nei sottonodi
            if ($child->attributes() && isset($child->attributes()['xsi_type'])) {
                $subParts = explode(' ', (string)$child->attributes()['xsi_type']);
                $subTypes = array_reverse($subParts);
                if (is_array($childData)) {
                    $childData = array_merge(['@type' => count($subTypes) === 1 ? $subTypes[0] : $subTypes], $childData);
                }
            }

            // Normalizzazione array
            if (isset($data[$cleanName])) {
                if (!is_array($data[$cleanName]) || !isset($data[$cleanName][0])) {
                    $data[$cleanName] = [$data[$cleanName]];
                }
                $data[$cleanName][] = $childData;
            } else {
                // Forza in array solo i nodi noti per essere liste
                $listNodes = ['organizer', 'subEvent', 'hasMap'];
                $data[$cleanName] = in_array($cleanName, $listNodes) ? [$childData] : $childData;
            }
        }
        return empty($data) ? null : $data;
    };

    $parsed = $parseNode($xml);
    if ($parsed) $result = array_merge($result, $parsed);

    return json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
?>