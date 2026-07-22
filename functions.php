<?php

/**
 * Converte JSON (Schema.org) in XML (Meetoo).
 * Regola di equivalenza: un oggetto con "@id" + altri campi (es. "name")
 * diventa un attributo xlink:href sull'elemento (riferimento parziale, dati
 * inline preservati); un oggetto con il solo "@id" diventa un <xi:include>
 * (riferimento puro a file esterno).
 */
function jsonToWsx(string $jsonString): string {
    $data = json_decode($jsonString, true);
    if (!is_array($data)) return '{"error": "JSON non valido"}';

    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->formatOutput = true;

    $types = (array)($data['@type'] ?? 'Event');
    $rootName = $types[0];
    $root = $dom->createElement($rootName);

    $meetooNs = 'https://meetoo.eu';
    foreach ((array)($data['@context'] ?? []) as $contextEntry) {
        if (is_array($contextEntry) && isset($contextEntry['meetoo'])) {
            $meetooNs = $contextEntry['meetoo'];
            break;
        }
    }

    $root->setAttribute('xmlns', 'https://schema.org');
    $root->setAttribute('xmlns:meetoo', $meetooNs);
    $root->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
    $root->setAttribute('xmlns:xlink', 'http://www.w3.org/1999/xlink');
    $root->setAttribute('xmlns:xi', 'http://www.w3.org/2001/XInclude');
    $root->setAttribute('xsi:type', implode(' ', array_reverse($types)));

    if (isset($data['@id'])) $root->setAttribute('id', (string)$data['@id']);

    $dom->appendChild($root);

    // Applica a un nodo le regole di riferimento (xlink:href / xi:include) e poi ricorre sui figli.
    $appendNode = function (array $item, DOMElement $node, string $parentKey, bool $checkPlainType) use (&$buildXml, &$appendNode, $dom) {
        if ($checkPlainType && isset($item['type'])) {
            $node->setAttribute('type', (string)$item['type']);
            unset($item['type']);
        }

        $cleanKeys = array_diff(array_keys($item), ['@id', '@type', '@context']);
        $isPartialReference = isset($item['@id']) && !empty($cleanKeys);
        $isPureReference = isset($item['@id']) && empty($cleanKeys);

        if ($isPartialReference) {
            $node->setAttribute('xlink:href', (string)$item['@id']);
            unset($item['@id']);
        }

        $buildXml($item, $node, $parentKey);

        if ($isPureReference) {
            $xi = $dom->createElement('xi:include');
            $xi->setAttribute('href', '../' . $item['@id'] . '/index.xml');
            $xi->setAttribute('xpointer', 'xpointer(/*[1])');
            $node->appendChild($xi);
        }
    };

    $buildXml = function (array $dataArray, DOMElement $parentElement, string $parentKey) use (&$buildXml, &$appendNode, $dom) {
        foreach ($dataArray as $key => $value) {
            $key = (string)$key;

            if (strpos($key, '@') === 0) {
                if ($key === '@id') {
                    $parentElement->setAttribute('id', (string)$value);
                } elseif ($key === '@type') {
                    if ($parentKey === 'meetoo') {
                        $typeVal = (string)(is_array($value) ? $value[0] : $value);
                        $parentElement->setAttribute('type', preg_replace('/^meetoo:/', '', $typeVal));
                    } else {
                        $subTypes = (array)$value;
                        $parentElement->setAttribute('xsi:type', $subTypes[1] ?? $subTypes[0]);
                    }
                } elseif ($key !== '@context') {
                    $parentElement->setAttribute(substr($key, 1), is_array($value) ? implode(' ', $value) : (string)$value);
                }
                continue;
            }

            $nodeName = ($key === 'meetoo' && is_array($value)) ? 'meetoo:meetoo' : $key;

            if (is_array($value) && array_keys($value) === range(0, count($value) - 1)) {
                // Array sequenziale (es. organizer, subEvent)
                foreach ($value as $item) {
                    $node = $dom->createElement($nodeName);
                    if (is_array($item)) {
                        $appendNode($item, $node, $key, false);
                    } else {
                        $node->nodeValue = htmlspecialchars(is_bool($item) ? ($item ? 'true' : 'false') : (string)$item);
                    }
                    $parentElement->appendChild($node);
                }
            } elseif (is_array($value)) {
                // Oggetto associativo (es. location, offers)
                $node = $dom->createElement($nodeName);
                $appendNode($value, $node, $key, true);
                $parentElement->appendChild($node);
            } else {
                // Valore scalare
                $node = $dom->createElement($nodeName);
                $node->appendChild($dom->createTextNode(is_bool($value) ? ($value ? 'true' : 'false') : (string)$value));
                $parentElement->appendChild($node);
            }
        }
    };

    $bodyData = array_diff_key($data, ['@context' => '', '@type' => '', '@id' => '']);
    $buildXml($bodyData, $root, $rootName);

    return tabifyIndentation($dom->saveXML(), 2);
}

/**
 * Converte XML (Meetoo) in JSON (Schema.org) usando DOMDocument con API
 * namespace-aware (localName/namespaceURI/getAttributeNS), evitando i rischi
 * di ambiguità di SimpleXML sui nodi con prefisso (es. meetoo:meetoo).
 */
function WsxToJson(string $xmlString): string {
    if (trim($xmlString) === '') return '{"error": "XML non valido"}';

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $loaded = $dom->loadXML($xmlString);
    libxml_clear_errors();
    if (!$loaded || !$dom->documentElement) return '{"error": "XML non valido"}';

    $xsiNs = 'http://www.w3.org/2001/XMLSchema-instance';
    $xlinkNs = 'http://www.w3.org/1999/xlink';
    $xiNs = 'http://www.w3.org/2001/XInclude';

    $root = $dom->documentElement;
    $rootName = $root->localName;

    $typeArray = [$rootName];
    $xsiType = $root->getAttributeNS($xsiNs, 'type');
    if ($xsiType !== '') {
        foreach (array_reverse(explode(' ', $xsiType)) as $part) {
            if ($part !== $rootName && !in_array($part, $typeArray, true)) {
                $typeArray[] = $part;
            }
        }
    }

    $meetooNs = $root->getAttribute('xmlns:meetoo');
    $result = [
        '@context' => $meetooNs !== '' ? ['https://schema.org', ['meetoo' => $meetooNs]] : 'https://schema.org',
        '@type' => count($typeArray) === 1 ? $typeArray[0] : $typeArray,
    ];

    $rootId = $root->getAttribute('id');
    if ($rootId !== '') $result['@id'] = $rootId;

    $parseNode = function (DOMElement $element) use (&$parseNode, $xsiNs, $xlinkNs, $xiNs) {
        $data = [];
        $listNodes = ['organizer', 'subEvent', 'hasMap'];

        foreach ($element->childNodes as $child) {
            if (!($child instanceof DOMElement)) continue;

            if ($child->namespaceURI === $xiNs && $child->localName === 'include') {
                $href = $child->getAttribute('href');
                return ['@id' => str_replace(['../', '/index.xml'], '', $href)];
            }

            $cleanName = ($child->prefix) ? $child->prefix . ':' . $child->localName : $child->localName;
            if ($cleanName === 'meetoo:meetoo') $cleanName = 'meetoo';

            $hasElementChildren = false;
            foreach ($child->childNodes as $grandChild) {
                if ($grandChild instanceof DOMElement) { $hasElementChildren = true; break; }
            }
            $childData = $hasElementChildren ? $parseNode($child) : $child->textContent;

            $xsiTypeVal = $child->getAttributeNS($xsiNs, 'type');
            if ($xsiTypeVal !== '') {
                $subTypes = array_reverse(explode(' ', $xsiTypeVal));
                $typeValue = count($subTypes) === 1 ? $subTypes[0] : $subTypes;
                $childData = is_array($childData) ? array_merge(['@type' => $typeValue], $childData) : ['@type' => $typeValue];
            } elseif ($cleanName === 'meetoo') {
                $plainType = $child->getAttribute('type');
                if ($plainType !== '') {
                    $typeValue = 'meetoo:' . $plainType;
                    $childData = is_array($childData) ? array_merge(['@type' => $typeValue], $childData) : ['@type' => $typeValue];
                }
            }

            $hrefVal = $child->getAttributeNS($xlinkNs, 'href');
            if ($hrefVal !== '') {
                $childData = is_array($childData) ? array_merge(['@id' => $hrefVal], $childData) : ['@id' => $hrefVal];
            }

            if (isset($data[$cleanName])) {
                if (!is_array($data[$cleanName]) || !isset($data[$cleanName][0])) {
                    $data[$cleanName] = [$data[$cleanName]];
                }
                $data[$cleanName][] = $childData;
            } else {
                $data[$cleanName] = in_array($cleanName, $listNodes, true) ? [$childData] : $childData;
            }
        }

        return empty($data) ? null : $data;
    };

    $parsed = $parseNode($root);
    if ($parsed) $result = array_merge($result, $parsed);

    $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return tabifyIndentation($json, 4);
}

/** Converte l'indentazione a spazi (passo fisso) generata da DOMDocument/json_encode in tab. */
function tabifyIndentation(string $text, int $spacesPerLevel): string {
    return preg_replace_callback('/^ +/m', function ($m) use ($spacesPerLevel) {
        return str_repeat("\t", intdiv(strlen($m[0]), $spacesPerLevel));
    }, $text);
}

/** Cerca la riga di una virgola finale non ammessa (trailing comma) prima di } o ]. */
function locateJsonErrorLine(string $json): ?int {
    if (preg_match('/,\s*[}\]]/', $json, $m, PREG_OFFSET_CAPTURE)) {
        return substr_count($json, "\n", 0, $m[0][1]) + 1;
    }
    return scanJsonBracketMismatchLine($json);
}

/** Scansione a basso livello per individuare parentesi non bilanciate o stringhe non terminate. */
function scanJsonBracketMismatchLine(string $json): ?int {
    $line = 1;
    $stack = [];
    $inString = false;
    $escape = false;
    $len = strlen($json);

    for ($i = 0; $i < $len; $i++) {
        $ch = $json[$i];
        if ($ch === "\n") $line++;

        if ($inString) {
            if ($escape) { $escape = false; }
            elseif ($ch === '\\') { $escape = true; }
            elseif ($ch === '"') { $inString = false; }
            continue;
        }

        if ($ch === '"') { $inString = true; continue; }
        if ($ch === '{' || $ch === '[') { $stack[] = $ch; continue; }
        if ($ch === '}' || $ch === ']') {
            $expected = $ch === '}' ? '{' : '[';
            if (empty($stack) || array_pop($stack) !== $expected) return $line;
        }
    }

    return ($inString || !empty($stack)) ? $line : null;
}

/** Riga della seconda occorrenza (duplicato) di una chiave JSON, se individuabile. */
function findDuplicateKeyLine(string $json, string $keyName): ?int {
    $pattern = '/"' . preg_quote($keyName, '/') . '"\s*:/';
    if (preg_match_all($pattern, $json, $m, PREG_OFFSET_CAPTURE) && isset($m[0][1])) {
        return substr_count($json, "\n", 0, $m[0][1][1]) + 1;
    }
    return null;
}

/** Valida il JSON: sintassi + euristiche strutturali WS (chiavi duplicate). Ogni errore porta la riga, se nota. */
function validateJsonPayload(string $inputData): array {
    $errors = [];
    $detectedFormat = 'json';
    $parsed = json_decode($inputData, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $errors[] = [
            'message' => '<strong>Errore Sintassi JSON:</strong> ' . json_last_error_msg()
                . '. Controlla virgole finali (trailing comma) non ammesse, virgolette mancanti o parentesi graffe non bilanciate.',
            'line' => locateJsonErrorLine($inputData),
        ];
    } else {
        if (isset($parsed['@context'])) {
            $detectedFormat = (isset($parsed['@type']) && (is_array($parsed['@type']) || strpos(json_encode($parsed['@context']), 'meetoo') !== false))
                ? 'ws-json-ld' : 'json-ld';
        }

        if (preg_match_all('/"([^"]+)"\s*:/', $inputData, $matches)) {
            $counts = array_count_values($matches[1]);
            $repeatableKeys = ['organizer' => 'organizzatori', 'subEvent' => 'sotto-eventi'];
            foreach ($repeatableKeys as $keyName => $label) {
                $isProperArray = isset($parsed[$keyName]) && is_array($parsed[$keyName]) && isset($parsed[$keyName][0]);
                if (($counts[$keyName] ?? 0) > 1 && !$isProperArray) {
                    $errors[] = [
                        'message' => "<strong>Anomalia Strutturale WS:</strong> la chiave '{$keyName}' è ripetuta più volte. "
                            . "Nello standard JSON-LD esteso le chiavi devono essere univoche: raggruppa i {$label} in un array di oggetti `[]`.",
                        'line' => findDuplicateKeyLine($inputData, $keyName),
                    ];
                }
            }
        }
    }

    return ['valid' => empty($errors), 'errors' => $errors, 'format' => $detectedFormat];
}

/** Valida l'XML tramite DOMDocument/libxml, che fornisce già riga e colonna per ogni errore. */
function validateXmlPayload(string $inputData): array {
    if (!trim($inputData)) {
        return ['valid' => false, 'errors' => [['message' => 'Il codice XML fornito è completamente vuoto.', 'line' => null]], 'format' => 'xml'];
    }

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $isValid = $dom->loadXML($inputData);
    $errors = [];
    $detectedFormat = 'xml';

    if (!$isValid) {
        foreach (libxml_get_errors() as $error) {
            $errors[] = [
                'message' => "<strong>Errore XML (Colonna {$error->column}):</strong> " . trim($error->message),
                'line' => $error->line,
            ];
        }
        libxml_clear_errors();
    } else {
        $root = $dom->documentElement;
        if ($root && ($root->getAttribute('xmlns:meetoo') || $root->getAttribute('xmlns:xi'))) {
            $detectedFormat = 'ws-xml';
        }
    }

    return ['valid' => empty($errors), 'errors' => $errors, 'format' => $detectedFormat];
}

/** Normalizza scalari per il confronto di integrità (bool/numeri come stringa, null come stringa vuota). */
function normalizeForComparison($value) {
    if (is_bool($value)) return $value ? 'true' : 'false';
    if (is_int($value) || is_float($value)) return (string)$value;
    if (is_null($value)) return '';
    return $value;
}

/** Confronto ricorsivo tra due strutture dati; ritorna la lista dei percorsi che differiscono. */
function diffData($before, $after, string $path = ''): array {
    $before = normalizeForComparison($before);
    $after = normalizeForComparison($after);

    if (is_array($before) && is_array($after)) {
        $diffs = [];
        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $key) {
            $childPath = $path === '' ? (string)$key : "{$path}.{$key}";
            if (!array_key_exists($key, $before)) {
                $diffs[] = "Campo '{$childPath}' presente dopo la conversione ma assente nell'originale.";
            } elseif (!array_key_exists($key, $after)) {
                $diffs[] = "Campo '{$childPath}' perso durante la conversione.";
            } else {
                $diffs = array_merge($diffs, diffData($before[$key], $after[$key], $childPath));
            }
        }
        return $diffs;
    }

    if (is_array($before) !== is_array($after)) {
        return ["Struttura diversa in '{$path}'."];
    }

    return $before === $after ? [] : ["Valore diverso in '{$path}': atteso \"{$before}\", ottenuto \"{$after}\"."];
}

/**
 * Check di integrità post-conversione: riconverte il risultato nel formato di
 * partenza (passando sempre per il modello dati JSON) e confronta con l'originale.
 */
function checkIntegrity(string $direction, string $original, string $converted): array {
    try {
        if ($direction === 'json_to_xml') {
            $before = json_decode($original, true);
            $after = json_decode(WsxToJson($converted), true);
        } else {
            $before = json_decode(WsxToJson($original), true);
            $after = json_decode($converted, true);
        }

        if (!is_array($before) || !is_array($after)) {
            return ['match' => false, 'diffs' => ['Impossibile confrontare i dati: una delle due conversioni non è riuscita.']];
        }

        $diffs = diffData($before, $after);
        return ['match' => empty($diffs), 'diffs' => $diffs];
    } catch (\Throwable $e) {
        return ['match' => false, 'diffs' => [$e->getMessage()]];
    }
}
