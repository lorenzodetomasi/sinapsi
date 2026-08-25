<?php
/**
 * JSON (schema.org) → XML, la conversione condivisa.
 *
 * Sta nel core, e non nell'amministrazione, perché ora serve a due padroni: al
 * convertitore dell'editor (che scrive il gemello `index.xml`) e al CMS, che dal
 * JSON costruisce l'albero da dare ai template senza passare da un file. Una sola
 * grammatica: se le due conversioni divergessero, la pagina servita al visitatore
 * e il file salvato direbbero cose diverse.
 *
 * Nessuna dipendenza: niente traduzioni, niente globali, niente CMS. Così lo può
 * caricare anche un endpoint che il CMS non lo avvia.
 */

if (!function_exists('jsonToWsx')) {
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
}

if (!function_exists('tabifyIndentation')) {
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
}

if (!function_exists('containsXhtmlMarkup')) {
    /** True se la stringa contiene almeno un vero tag XHTML (non un semplice '<' o '&' di prosa). */
    function containsXhtmlMarkup(string $value): bool {
        return (bool)preg_match('/<\/?[a-zA-Z][\w:-]*(\s[^<>]*)?\/?>/', $value);
    }
}

if (!function_exists('splitForCdata')) {
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
}

if (!function_exists('appendTextOrCdata')) {
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
}
