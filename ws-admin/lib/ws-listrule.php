<?php
/*
 * meetoo:listRule — la regola che dice CHI sta in una lista e in CHE ORDINE.
 *
 * È dato, non codice: un elenco chiuso di clausole. I nomi sono le parole chiave
 * di JSON Schema (`const`, `enum`, `pattern`, `exists`) e la semantica è la sua,
 * così non c'è un vocabolario nuovo da imparare né da documentare due volte:
 *
 *   { "field": "additionalType", "const":   "BookCrossing"  }   valore ESATTO
 *   { "field": "additionalType", "pattern": "^BookCrossing" }   che COMINCIA per
 *   { "field": "@type",          "enum":    ["Library", "BookStore"] }
 *   { "field": "meetoo:isGroup", "const":   true }
 *   { "field": "meetoo:coastalPosition", "exists": true }
 *
 * Perché la forma compatta e non JSON Schema scritto per esteso: in JSON Schema
 * `properties` si applica solo se il campo c'è e `contains` solo se il valore è
 * un array, quindi la regola «ingenua» accetta per verità vacua tutto ciò che quel
 * campo non ce l'ha. Provata sui contenuti veri: 53 luoghi su 59 invece di 3.
 * Qui `required` e il caso stringa-o-array li mette il compilatore, una volta per
 * tutte, invece di chiederli a chi scrive la regola.
 *
 * `ws_listrule_compile()` restituisce lo JSON Schema equivalente: serve a far
 * valutare la stessa regola dal client con Ajv (già nel bundle dell'editor) e a
 * poter verificare che le due strade diano lo stesso risultato.
 */

if (!function_exists('ws_listrule_match')) {

    /** Clausola compatta → JSON Schema equivalente (robusto: required + stringa-o-array). */
    function ws_listrule_compile(array $c): array {
        $campo = (string)($c['field'] ?? '');
        if (array_key_exists('exists', $c)) {
            return $c['exists'] ? ['required' => [$campo]] : ['not' => ['required' => [$campo]]];
        }
        if (array_key_exists('const', $c))        $test = ['const' => $c['const']];
        elseif (array_key_exists('enum', $c))     $test = ['enum' => array_values((array)$c['enum'])];
        elseif (array_key_exists('pattern', $c))  $test = ['type' => 'string', 'pattern' => (string)$c['pattern']];
        else return ['not' => []];   // clausola senza operatore: non seleziona nulla
        return [
            'required' => [$campo],
            'properties' => [$campo => ['anyOf' => [['type' => 'array', 'contains' => $test], $test]]],
        ];
    }

    /** L'intera regola → JSON Schema (clausole in OR). */
    function ws_listrule_compile_all(array $regola): array {
        $any = $regola['match']['any'] ?? [];
        return ['anyOf' => array_map('ws_listrule_compile', $any)];
    }

    /** Il singolo valore soddisfa il test? (const/enum: uguaglianza tipata; pattern: solo stringhe.) */
    function ws_listrule_test($v, array $c): bool {
        if (array_key_exists('const', $c))   return ws_listrule_uguali($v, $c['const']);
        if (array_key_exists('enum', $c)) {
            foreach ((array)$c['enum'] as $x) if (ws_listrule_uguali($v, $x)) return true;
            return false;
        }
        if (array_key_exists('pattern', $c)) {
            if (!is_string($v)) return false;   // in JSON Schema `pattern` vale solo sulle stringhe
            $re = '/' . str_replace('/', '\/', (string)$c['pattern']) . '/u';
            return @preg_match($re, $v) === 1;
        }
        return false;
    }

    /** Uguaglianza come `const` di JSON Schema: stesso valore E stesso tipo. */
    function ws_listrule_uguali($a, $b): bool {
        if (is_bool($a) || is_bool($b)) return $a === $b;
        if (is_numeric($a) && is_numeric($b)) return (float)$a === (float)$b;
        return is_string($a) && is_string($b) ? $a === $b : $a === $b;
    }

    /** Una clausola è vera per questa entità? */
    function ws_listrule_clause(array $e, array $c): bool {
        $campo = (string)($c['field'] ?? '');
        $presente = array_key_exists($campo, $e);
        if (array_key_exists('exists', $c)) return $c['exists'] ? $presente : !$presente;
        if (!$presente) return false;                      // il `required` del compilato
        $v = $e[$campo];
        if (is_array($v)) {                                 // stringa-o-array: basta un elemento
            foreach ($v as $x) if (ws_listrule_test($x, $c)) return true;
            return false;
        }
        return ws_listrule_test($v, $c);
    }

    /** L'entità entra nella lista? (clausole in OR) */
    function ws_listrule_match(array $e, array $regola): bool {
        foreach (($regola['match']['any'] ?? []) as $c) {
            if (is_array($c) && ws_listrule_clause($e, $c)) return true;
        }
        return false;
    }

    /* ---- Ordinamento ------------------------------------------------------
     * Il campo si legge PRIMA sulla voce di lista e poi sull'entità: è così che
     * una lista può ordinarsi su un dato che vive nella lista stessa (le distanze
     * dal confine sud del lungomare non stanno nei file dei luoghi). */
    function ws_listrule_sortkey(array $ord, ?array $voce, array $e) {
        $campo = (string)($ord['by'] ?? 'name');
        $v = ($voce !== null && array_key_exists($campo, $voce)) ? $voce[$campo] : ($e[$campo] ?? null);
        if (is_array($v)) $v = $v[0] ?? null;
        switch ($ord['as'] ?? 'text') {
            case 'number': return (float)$v;
            case 'date':   return strtotime((string)$v) ?: 0;
            case 'random': return mt_rand();
            default:
                // Ordine da dizionario, lettera per lettera: si ignorano
                // interpunzione E spazi, come su un indice analitico. Così
                // «L'Angoletto» viene prima di «La Piccola» (langoletto <
                // lapiccola); tenendo gli spazi vincerebbe lo spazio, e l'ordine
                // dipenderebbe da dove cade un apostrofo.
                $s = mb_strtolower((string)$v);
                return preg_replace('/[^\p{L}\p{N}]+/u', '', $s);
        }
    }

    /* ---- Raccolta -----------------------------------------------------------
     * Scandisce gli ambiti dichiarati in `from` e ritorna [@id => entità] per chi
     * soddisfa la regola. Ambiti: places, organizations, events. */
    function ws_listrule_collect(string $base, array $regola): array {
        $mappa = ['places' => 'places/*/*', 'organizations' => 'organizations/*', 'events' => 'events/*'];
        $out = [];
        foreach (($regola['from'] ?? ['places']) as $ambito) {
            if (!isset($mappa[$ambito])) continue;
            foreach (glob(rtrim($base, '/') . '/' . $mappa[$ambito] . '/index.json') as $f) {
                $j = json_decode((string)@file_get_contents($f), true);
                if (!is_array($j)) continue;
                $e = $j['mainEntity'] ?? $j;
                $id = $e['@id'] ?? '';
                if ($id === '' || !ws_listrule_match($e, $regola)) continue;
                $out[$id] = $e;
            }
        }
        return $out;
    }

    /* ---- Fusione ------------------------------------------------------------
     * La rigenerazione NON riscrive la lista: la fonde.
     *   - le voci già presenti restano, con i loro dati (posizione, distanze,
     *     nomi scritti a mano): sono lavoro redazionale;
     *   - le nuove trovate dalla regola si aggiungono, marcate `meetoo:auto`;
     *   - quelle che la regola non trova più NON si cancellano: si segnalano.
     * Perché non si pota: nel lungomare 47 tappe su 61 esistono solo dentro la
     * lista, e nessuna regola potrà mai ritrovarle in un file.
     *
     * Ritorna ['itemListElement'=>[...], 'aggiunte'=>[], 'orfane'=>[], 'incomplete'=>[]].
     */
    function ws_listrule_merge(array $regola, array $listaAttuale, array $trovati): array {
        $ord = $regola['order'] ?? ['by' => 'name', 'as' => 'text', 'direction' => 'asc'];
        $voci = [];      // @id (o chiave sintetica) => ['item'=>..., 'auto'=>bool]
        $senzaId = [];   // voci senza @id: restano dove sono, in coda al loro posto

        foreach ($listaAttuale as $li) {
            $item = is_array($li) ? ($li['item'] ?? $li) : [];
            $id = $item['@id'] ?? '';
            $auto = !empty($li['meetoo:auto']);
            if ($id === '') { $senzaId[] = ['item' => $item, 'auto' => $auto]; continue; }
            $voci[$id] = ['item' => $item, 'auto' => $auto];
        }

        $aggiunte = [];
        foreach ($trovati as $id => $e) {
            if (isset($voci[$id])) { $voci[$id]['auto'] = true; continue; }   // già c'era: la regola la conferma
            $voci[$id] = ['item' => ['@id' => $id, '@type' => ws_listrule_tipo($e), 'name' => $e['name'] ?? $id], 'auto' => true];
            $aggiunte[] = $id;
        }

        // Orfane = entrate per regola ma non più trovate. Si segnalano, non si tolgono.
        $orfane = [];
        foreach ($voci as $id => $v) {
            if ($v['auto'] && !isset($trovati[$id])) $orfane[] = $id;
        }

        // Ordinamento sul campo dichiarato; le voci senza @id restano in coda.
        $chiavi = [];
        foreach ($voci as $id => $v) $chiavi[$id] = ws_listrule_sortkey($ord, $v['item'], $trovati[$id] ?? []);
        $ids = array_keys($voci);
        usort($ids, function ($a, $b) use ($chiavi, $ord) {
            $x = $chiavi[$a]; $y = $chiavi[$b];
            $c = ($x == $y) ? 0 : (($x < $y) ? -1 : 1);
            return (($ord['direction'] ?? 'asc') === 'desc') ? -$c : $c;
        });

        $incomplete = [];
        $out = []; $pos = 1;
        foreach ($ids as $id) {
            $v = $voci[$id];
            if (($ord['as'] ?? '') === 'number' && !isset($v['item'][$ord['by']]) && !isset($trovati[$id][$ord['by']])) {
                $incomplete[] = $id;   // manca il dato su cui si ordina: da compilare a mano
            }
            $voce = ['@type' => 'ListItem', 'position' => $pos++];
            if ($v['auto']) $voce['meetoo:auto'] = true;
            $voce['item'] = $v['item'];
            $out[] = $voce;
        }
        foreach ($senzaId as $v) {
            $voce = ['@type' => 'ListItem', 'position' => $pos++];
            $voce['item'] = $v['item'];
            $out[] = $voce;
        }
        return ['itemListElement' => $out, 'aggiunte' => $aggiunte, 'orfane' => $orfane, 'incomplete' => $incomplete];
    }

    /** Tipo da mettere nella voce di lista: il primo, per non copiare tutto. */
    function ws_listrule_tipo(array $e): string {
        $t = $e['@type'] ?? 'Thing';
        return is_array($t) ? (string)($t[0] ?? 'Thing') : (string)$t;
    }
}
