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
            // I riferimenti (@id = "{collection}/{slug}") sono relativi alla radice del
            // locale; l'entità di partenza sta due livelli sotto (es. events/{slug}/),
            // quindi l'href XInclude risale di due livelli.
            $xi = $dom->createElement('xi:include');
            $xi->setAttribute('href', '../../' . $item['@id'] . '/index.xml');
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
                        appendTextOrCdata($dom, $node, $item);
                    }
                    $parentElement->appendChild($node);
                }
            } elseif (is_array($value)) {
                // Oggetto associativo (es. location, offers)
                $node = $dom->createElement($nodeName);
                $appendNode($value, $node, $key, true);
                $parentElement->appendChild($node);
            } else {
                // Valore scalare (text node, o CDATA se contiene markup XHTML)
                $node = $dom->createElement($nodeName);
                appendTextOrCdata($dom, $node, $value);
                $parentElement->appendChild($node);
            }
        }
    };

    $bodyData = array_diff_key($data, ['@context' => '', '@type' => '', '@id' => '']);
    $buildXml($bodyData, $root, $rootName);

    return tabifyIndentation($dom->saveXML(), 2, true);
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

            if (isXhtmlContainer($child)) {
                // Contenuto XHTML inline (es. <description>testo <strong>x</strong></description>)
                // → stringa rich-text verbatim, non un oggetto annidato.
                $childData = innerXhtml($child);
            } else {
                $hasElementChildren = false;
                foreach ($child->childNodes as $grandChild) {
                    if ($grandChild instanceof DOMElement) { $hasElementChildren = true; break; }
                }
                $childData = $hasElementChildren ? $parseNode($child) : $child->textContent;
            }

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

/**
 * Converte l'indentazione a spazi (passo fisso) generata da DOMDocument/json_encode in tab.
 * Con $protectCdata=true (XML) protegge il contenuto delle sezioni <![CDATA[…]]> lasciandolo
 * verbatim, così l'HTML incorporato non viene alterato.
 */
function tabifyIndentation(string $text, int $spacesPerLevel, bool $protectCdata = false): string {
    $convert = function (string $chunk) use ($spacesPerLevel) {
        return preg_replace_callback('/^ +/m', function ($m) use ($spacesPerLevel) {
            return str_repeat("\t", intdiv(strlen($m[0]), $spacesPerLevel));
        }, $chunk);
    };

    if (!$protectCdata) return $convert($text);

    // Divide sui blocchi CDATA (catturati) e tabula solo i segmenti strutturali (indici pari).
    $parts = preg_split('/(<!\[CDATA\[.*?\]\]>)/s', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    foreach ($parts as $i => $part) {
        if ($i % 2 === 0) $parts[$i] = $convert($part);
    }
    return implode('', $parts);
}

/** True se la stringa contiene almeno un vero tag XHTML (non un semplice '<' o '&' di prosa). */
function containsXhtmlMarkup(string $value): bool {
    return (bool)preg_match('/<\/?[a-zA-Z][\w:-]*(\s[^<>]*)?\/?>/', $value);
}

/** Insieme dei nomi di tag XHTML riconosciuti come contenuto rich-text (non dati strutturati schema.org). */
function xhtmlTagSet(): array {
    static $set = null;
    if ($set === null) {
        $tags = ['a','abbr','address','b','blockquote','br','cite','code','dd','del','dfn','div','dl','dt',
            'em','figcaption','figure','h1','h2','h3','h4','h5','h6','hr','i','img','ins','kbd','li','mark',
            'ol','p','pre','q','s','samp','small','span','strong','sub','sup','table','tbody','td','tfoot',
            'th','thead','tr','u','ul','var','wbr'];
        $set = array_fill_keys($tags, true);
    }
    return $set;
}

/** True se l'elemento ha figli-elemento che sono tag XHTML (→ va trattato come stringa rich-text, non ricorso). */
function isXhtmlContainer(DOMElement $el): bool {
    foreach ($el->childNodes as $child) {
        if ($child instanceof DOMElement && isset(xhtmlTagSet()[strtolower($child->localName)])) {
            return true;
        }
    }
    return false;
}

/** Serializza verbatim il contenuto interno di un elemento (mixed content XHTML) come stringa. */
function innerXhtml(DOMElement $el): string {
    $out = '';
    foreach ($el->childNodes as $child) {
        $out .= $el->ownerDocument->saveXML($child);
    }
    return $out;
}

/**
 * Spezza una stringa in segmenti [tipo, contenuto] sicuri per CDATA: se contiene la sequenza
 * ']]>' la divide in più sezioni preservando i byte esatti (round-trip via textContent).
 */
function splitForCdata(string $str): array {
    $chunks = explode(']]>', $str);
    $last = count($chunks) - 1;
    $segments = [];
    foreach ($chunks as $i => $chunk) {
        $segments[] = ['cdata', $chunk . ($i < $last ? ']]' : '')];
        if ($i < $last) $segments[] = ['text', '>'];
    }
    return $segments;
}

/** Aggiunge a $node il valore come CDATA (se contiene markup XHTML) o come text node normale. */
function appendTextOrCdata(DOMDocument $dom, DOMElement $node, $value): void {
    $str = is_bool($value) ? ($value ? 'true' : 'false') : (string)$value;
    if (!containsXhtmlMarkup($str)) {
        $node->appendChild($dom->createTextNode($str));
        return;
    }
    foreach (splitForCdata($str) as [$kind, $content]) {
        $node->appendChild($kind === 'cdata' ? $dom->createCDATASection($content) : $dom->createTextNode($content));
    }
}

/** Mappa entità HTML nominate → carattere UTF-8, escluse le 5 predefinite in XML. Costruita una volta. */
function htmlEntityMap(): array {
    static $map = null;
    if ($map === null) {
        $table = get_html_translation_table(HTML_ENTITIES, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $flip = array_flip($table); // '&nbsp;' => "\u{00A0}", ecc.
        unset($flip['&lt;'], $flip['&gt;'], $flip['&amp;'], $flip['&quot;'], $flip['&apos;'], $flip['&#039;']);
        $map = $flip;
    }
    return $map;
}

/**
 * Valida un frammento XHTML come ben formato. Le entità HTML nominate (es. &nbsp;, &egrave;)
 * vengono risolte in caratteri solo ai fini della validazione, così restano ammesse.
 * Ritorna ['valid' => bool, 'message' => string, 'line' => ?int (relativa al frammento)].
 */
function validateXhtmlFragment(string $fragment): array {
    $resolved = strtr($fragment, htmlEntityMap());

    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $ok = $doc->loadXML("<xhtmlfragroot>" . $resolved . "</xhtmlfragroot>");
    $result = ['valid' => (bool)$ok, 'message' => '', 'line' => null];

    if (!$ok) {
        $first = libxml_get_errors()[0] ?? null;
        if ($first) {
            $result['message'] = trim($first->message);
            $result['line'] = $first->line; // relativa al frammento wrappato
        } else {
            $result['message'] = 'frammento XHTML non ben formato.';
        }
    }
    libxml_clear_errors();
    return $result;
}

/**
 * Tenta di correggere un frammento XHTML malformato con il parser HTML permissivo di libxml
 * (chiude i tag, normalizza) e lo restituisce come XHTML ben formato. Suggerimento opt-in.
 */
function fixXhtmlFragment(string $fragment): string {
    libxml_use_internal_errors(true);
    $doc = new DOMDocument('1.0', 'UTF-8');
    $doc->loadHTML(
        '<?xml encoding="UTF-8"?><xhtmlfixroot>' . $fragment . '</xhtmlfixroot>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();

    $root = $doc->getElementsByTagName('xhtmlfixroot')->item(0);
    if (!$root) return $fragment;

    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $doc->saveXML($child);
    }
    return $out;
}

/** Riga (1-based) della prima occorrenza di $needle nel testo grezzo, o null. */
function locateNeedleLine(string $raw, string $needle): ?int {
    $pos = strpos($raw, $needle);
    return $pos === false ? null : substr_count($raw, "\n", 0, $pos) + 1;
}

/** Raccoglie errori di XHTML malformato nei valori stringa di una struttura JSON decodificata. */
function collectJsonXhtmlErrors($node, string $raw, ?string $key = null): array {
    $errors = [];
    if (is_array($node)) {
        foreach ($node as $k => $v) {
            $errors = array_merge($errors, collectJsonXhtmlErrors($v, $raw, is_string($k) ? $k : $key));
        }
    } elseif (is_string($node) && containsXhtmlMarkup($node)) {
        $res = validateXhtmlFragment($node);
        if (!$res['valid']) {
            $errors[] = [
                'message' => "<strong>XHTML non valido nel campo '" . htmlspecialchars((string)$key) . "':</strong> " . $res['message']
                    . " Usa il pulsante \"Correggi XHTML\" per applicare la correzione suggerita.",
                'line' => $key !== null ? locateNeedleLine($raw, '"' . $key . '"') : null,
                'fixable' => true,
            ];
        }
    }
    return $errors;
}

/** Raccoglie errori di XHTML malformato nei nodi foglia (testo/CDATA) di un documento XML. */
function collectXmlXhtmlErrors(DOMDocument $dom, string $raw): array {
    $errors = [];
    foreach ($dom->getElementsByTagName('*') as $el) {
        $hasElementChild = false;
        foreach ($el->childNodes as $child) {
            if ($child instanceof DOMElement) { $hasElementChild = true; break; }
        }
        if ($hasElementChild) continue;

        $text = $el->textContent;
        if ($text !== '' && containsXhtmlMarkup($text)) {
            $res = validateXhtmlFragment($text);
            if (!$res['valid']) {
                $errors[] = [
                    'message' => "<strong>XHTML non valido in &lt;" . $el->localName . "&gt;:</strong> " . $res['message']
                        . " Usa il pulsante \"Correggi XHTML\" per applicare la correzione suggerita.",
                    'line' => locateNeedleLine($raw, '<' . $el->localName),
                    'fixable' => true,
                ];
            }
        }
    }
    return $errors;
}

/** Applica ricorsivamente la correzione XHTML ai valori stringa malformati di una struttura JSON. */
function fixXhtmlInData($node) {
    if (is_array($node)) {
        foreach ($node as $k => $v) $node[$k] = fixXhtmlInData($v);
        return $node;
    }
    if (is_string($node) && containsXhtmlMarkup($node)) {
        $res = validateXhtmlFragment($node);
        if (!$res['valid']) return fixXhtmlFragment($node);
    }
    return $node;
}

/** Corregge l'XHTML malformato nell'intero documento (JSON o XML) e lo riserializza. */
function fixXhtmlDocument(string $type, string $doc): string {
    if ($type === 'json') {
        $data = json_decode($doc, true);
        if (!is_array($data)) return $doc;
        $fixed = fixXhtmlInData($data);
        return tabifyIndentation(json_encode($fixed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 4);
    }

    if (trim($doc) === '') return $doc;
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $loaded = $dom->loadXML($doc);
    libxml_clear_errors();
    if (!$loaded) return $doc;

    foreach (iterator_to_array($dom->getElementsByTagName('*')) as $el) {
        $hasElementChild = false;
        foreach ($el->childNodes as $child) {
            if ($child instanceof DOMElement) { $hasElementChild = true; break; }
        }
        if ($hasElementChild) continue;

        $text = $el->textContent;
        if ($text === '' || !containsXhtmlMarkup($text)) continue;
        if (validateXhtmlFragment($text)['valid']) continue;

        $fixedFragment = fixXhtmlFragment($text);
        while ($el->firstChild) $el->removeChild($el->firstChild);
        foreach (splitForCdata($fixedFragment) as [$kind, $content]) {
            $el->appendChild($kind === 'cdata' ? $dom->createCDATASection($content) : $dom->createTextNode($content));
        }
    }
    return tabifyIndentation($dom->saveXML(), 2, true);
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

        $errors = array_merge($errors, collectJsonXhtmlErrors($parsed, $inputData));
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
        $errors = array_merge($errors, collectXmlXhtmlErrors($dom, $inputData));
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

/**
 * Upload di un'immagine nella media-sources/ e ritorna il path relativo da salvare
 * nel campo (es. "media-sources/cover.jpg").
 *
 * PoC: sandbox fissa `uploads/media-sources/` accanto al backend (nessun path
 * fornito dal client → nessun rischio di path traversal). In produzione il target
 * sarà la media-sources/ della cartella dell'entità in editing, con validazione.
 */
function handleUpload(?array $file): array {
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Nessun file valido caricato.'];
    }

    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        return ['success' => false, 'error' => "Estensione non ammessa: .{$ext} (ammesse: " . implode(', ', $allowed) . ')'];
    }
    if (($file['size'] ?? 0) > 10 * 1024 * 1024) {
        return ['success' => false, 'error' => 'File troppo grande (max 10 MB).'];
    }

    // Nome sicuro: slug del basename + estensione.
    $slug = preg_replace('/[^a-zA-Z0-9._-]+/', '-', pathinfo($file['name'], PATHINFO_FILENAME));
    $slug = trim($slug, '-.') ?: 'file';

    $targetDir = __DIR__ . '/uploads/media-sources';
    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        return ['success' => false, 'error' => 'Impossibile creare la cartella di upload.'];
    }

    // Evita sovrascritture aggiungendo un suffisso numerico.
    $name = $slug . '.' . $ext;
    for ($i = 1; file_exists($targetDir . '/' . $name); $i++) {
        $name = $slug . '-' . $i . '.' . $ext;
    }

    if (!move_uploaded_file($file['tmp_name'], $targetDir . '/' . $name)) {
        return ['success' => false, 'error' => 'Salvataggio del file fallito.'];
    }

    return ['success' => true, 'path' => 'media-sources/' . $name];
}
