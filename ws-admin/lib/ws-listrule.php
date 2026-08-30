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
 *   { "field": "typicalAgeRange", "ageWithin":   "6-10" }   fascia DENTRO la fascia
 *   { "field": "typicalAgeRange", "ageOverlaps": "0-13" }   fascia che la TOCCA
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
        elseif (ws_listrule_eta_quale($c) !== '') $test = ['type' => 'string', 'pattern' => ws_listrule_eta_pattern($c)];
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
        if (ws_listrule_eta_quale($c) !== '') return ws_listrule_test_eta($v, $c);
        return false;
    }

    /* ---- Fasce d'età -------------------------------------------------------
     * `typicalAgeRange` di schema.org è un testo, e le sue forme sono due: «6-13»
     * e «11-» (da undici in su). Chiedere a chi scrive una regola l'espressione che
     * dice se due intervalli si toccano vuol dire chiedergli
     * `^(?:[0-9]|1[0-3])\s*-\s*(?:[6-9]|…)$`: si scrive una volta e non si rilegge
     * più. L'aritmetica sta qui, una volta per tutte, come il `required` e il caso
     * stringa-o-array:
     *
     *   { "field":"typicalAgeRange", "ageOverlaps": "0-13" }   TOCCA     (adatto a)
     *   { "field":"typicalAgeRange", "ageTargets":  "14-18" }  RIGUARDA  (la lista di fascia)
     *   { "field":"typicalAgeRange", "ageWithin":   "6-10" }   DENTRO    (stretto)
     *
     * `ageOverlaps` è la lista larga: ci entra anche «0-», tutte le età, ed è giusto —
     * un evento aperto a tutti è adatto anche ai bambini.
     *
     * `ageTargets` è la lista di UNA fascia: tocca quegli anni E dichiara qualcosa.
     * I due pezzi servono tutti e due. Senza il primo, «14-» (dai quattordici in su)
     * non entrerebbe da nessuna parte, perché aperto verso l'alto non sta DENTRO
     * nessuna fascia: provato sui contenuti veri, tutte e otto le fasce vuote, anche
     * quelle dei bambini. Senza il secondo, «0-» entrerebbe in tutte e otto e ogni
     * fascia diventerebbe l'intero calendario: «per tutti» non è una dichiarazione di
     * pubblico, è la sua assenza.
     *
     * `ageWithin` resta per il caso stretto — chi è costruito ESATTAMENTE su quegli
     * anni — ma sui contenuti veri non lo usa nessuna lista: è troppo severo perché
     * gli intervalli che si scrivono davvero (0-13, 14-) attraversano le fasce.
     */

    /** L'ultimo anno che contiamo: oltre, «e oltre». */
    function ws_listrule_eta_max(): int { return 120; }

    /** Quale dei tre operatori porta questa clausola? '' se nessuno. */
    function ws_listrule_eta_quale(array $c): string {
        if (array_key_exists('ageWithin', $c))   return 'ageWithin';
        if (array_key_exists('ageOverlaps', $c)) return 'ageOverlaps';
        if (array_key_exists('ageTargets', $c))  return 'ageTargets';
        return '';
    }

    /** «Tutte le età» — [0,120] — non è una dichiarazione di pubblico: è la sua
     *  assenza detta a voce alta. Le liste di fascia la lasciano fuori. */
    function ws_listrule_eta_universale(array $f): bool {
        return $f[0] <= 0 && $f[1] >= ws_listrule_eta_max();
    }

    /** Il testo di una fascia → [primo, ultimo] anno; `null` se fascia non è.
     *  «6-13» → [6,13];  «11-» e «11+» → [11,120];  «All Ages» → [0,120].
     *  Una fascia scritta a parole — «da 3 a 6 anni» — resta valida per chi legge
     *  ma qui è `null`: la regola non la riconosce, e non fa finta di sì. Nemmeno
     *  «14-0», che è un errore di battitura: raddrizzarlo in silenzio vorrebbe dire
     *  metterlo in una lista che nessuno ha chiesto. */
    function ws_listrule_eta($v): ?array {
        if (!is_string($v)) return null;
        $s = trim($v);
        if ($s === '') return null;
        if (preg_match('/^(all\s*ages|tutte\s*le\s*et)/iu', $s)) return [0, ws_listrule_eta_max()];
        if (!preg_match('/^(\d{1,3})\s*[-+]\s*(\d{1,3})?$/', $s, $m)) return null;
        $primo  = (int)$m[1];
        $ultimo = (isset($m[2]) && $m[2] !== '') ? (int)$m[2] : ws_listrule_eta_max();
        return $primo <= $ultimo ? [$primo, $ultimo] : null;
    }

    /** La clausola d'età è vera per questo valore? */
    function ws_listrule_test_eta($v, array $c): bool {
        $quale = ws_listrule_eta_quale($c);
        $f = ws_listrule_eta($v);
        $r = ws_listrule_eta((string)$c[$quale]);
        if ($f === null || $r === null) return false;
        if ($quale === 'ageWithin')  return $f[0] >= $r[0] && $f[1] <= $r[1];   // dentro
        if ($quale === 'ageTargets' && ws_listrule_eta_universale($f)) return false;
        return $f[0] <= $r[1] && $f[1] >= $r[0];                                // si toccano
    }

    /** Lo stesso test scritto come `pattern`, per chi la regola la valuta con JSON
     *  Schema. Gli anni sono pochi e finiti, quindi l'elenco di quelli ammessi è
     *  esatto — e lo scrive il compilatore, non chi la regola la scrive a mano.
     *
     *  Le due cifre si enumerano ACCOPPIATE, un gruppo per ogni primo anno: gli
     *  ultimi ammessi dipendono dal primo (nessuno accetta «7-6»), e enumerarle
     *  separate faceva passare le fasce scritte al contrario. Ne esce un'espressione
     *  lunga: è generata, non si legge, e nasce già giusta. */
    function ws_listrule_eta_pattern(array $c): string {
        $quale  = ws_listrule_eta_quale($c);
        $dentro = ($quale === 'ageWithin');
        $r      = ws_listrule_eta((string)($c[$quale] ?? ''));
        if ($r === null) return '(?!)';                   // regola illeggibile: non prende nulla
        $max = ws_listrule_eta_max();
        // DENTRO:   primo e ultimo stanno tutti e due nella fascia della regola.
        // TOCCA:    il primo non supera l'ultimo anno della regola, e l'ultimo arriva
        //           almeno al primo. «11-» (ultimo assente) tocca sempre, dentro no.
        // RIGUARDA: come TOCCA, meno «0-» e «0-120» — la fascia universale, che con
        //           primo 0 si riconosce dal solo ultimo: aperto, o l'ultimo anno.
        $primi  = $dentro ? range($r[0], $r[1]) : range(0, $r[1]);
        $gruppi = [];
        foreach ($primi as $a) {
            $da     = $dentro ? $a : max($a, $r[0]);
            $fino   = $dentro ? $r[1] : $max;
            $aperto = $dentro ? ($r[1] >= $max) : true;
            if ($quale === 'ageTargets' && $a <= 0) {
                $fino   = $max - 1;   // «0-120» è universale
                $aperto = false;      // «0-» pure
            }
            if ($da > $fino) continue;
            $ultimi = '(?:' . implode('|', range($da, $fino)) . ')' . ($aperto ? '?' : '');
            $gruppi[] = $a . '\s*[-+]\s*' . $ultimi;
        }
        $p = $gruppi ? '^\s*(?:' . implode('|', $gruppi) . ')\s*$' : '(?!)';
        // «All Ages» è [0,120]: tocca qualunque fascia, ma sta dentro solo il tutto —
        // e per una lista di fascia è proprio quello che non si vuole.
        if ($quale === 'ageOverlaps' || ($dentro && $r[0] <= 0 && $r[1] >= $max)) {
            $p = '(?i:^\s*(?:all\s*ages|tutte\s*le\s*et).*$)|' . $p;
        }
        return $p;
    }

    /** Uguaglianza come `const` di JSON Schema: stesso valore E stesso tipo. */
    function ws_listrule_uguali($a, $b): bool {
        if (is_bool($a) || is_bool($b)) return $a === $b;
        if (is_numeric($a) && is_numeric($b)) return (float)$a === (float)$b;
        return is_string($a) && is_string($b) ? $a === $b : $a === $b;
    }

    /** Il nome con cui il campo sta DAVVERO nell'entità: `isChildrensEvent` oppure
     * `meetoo:isChildrensEvent`. I campi nostri nei contenuti portano il prefisso;
     * chi scrive una regola non deve doverselo ricordare, e una regola che cerca il
     * nome nudo non deve selezionare il vuoto in silenzio. */
    function ws_listrule_campo(array $e, string $campo): string {
        if ($campo === '' || array_key_exists($campo, $e)) return $campo;
        if (strpos($campo, ':') !== false) {
            $nudo = substr($campo, strrpos($campo, ':') + 1);
            return array_key_exists($nudo, $e) ? $nudo : $campo;
        }
        return array_key_exists("meetoo:$campo", $e) ? "meetoo:$campo" : $campo;
    }

    /** Una clausola è vera per questa entità? */
    function ws_listrule_clause(array $e, array $c): bool {
        $campo = ws_listrule_campo($e, (string)($c['field'] ?? ''));
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

    /* ---- Sincronizzazione ---------------------------------------------------
     * Trova TUTTE le liste che dichiarano una regola e le rimette in pari.
     * La usano tutti e tre i richiamanti — la riga di comando, il pannello di
     * manutenzione e il salvataggio — così non possono divergere.
     *
     * $apply = false → dice soltanto che cosa farebbe.
     * Ritorna ['liste'=>[{id,trovati,voci,aggiunte,orfane,incomplete,scritta}], 'cambiate'=>int].
     */
    function ws_listrule_sync(string $base, bool $apply): array {
        $out = ['liste' => [], 'cambiate' => 0];
        foreach (ws_listrule_lists($base) as $f => $doc) {
            $cambiato = false;
            // Quali righe del resoconto parlano di QUESTO file: la scrittura avviene
            // dopo averle compilate, e devono poterlo dire.
            $righe = [];
            // Un documento può contenere più liste: quella in cima e quelle delle
            // sue sezioni. Si sistemano tutte, e il file si scrive una volta sola.
            foreach (ws_listrule_posizioni($doc) as $pos) {
                $lista = ws_listrule_prendi($doc, $pos);
                $regola = $lista['meetoo:listRule'] ?? [];
                $trovati = ws_listrule_collect($base, $regola);
                $r = ws_listrule_merge($regola, $lista['itemListElement'] ?? [], $trovati);

                // Si scrive solo se qualcosa è davvero cambiato: una rigenerazione a
                // vuoto non deve toccare dateModified né sporcare il diff dei contenuti.
                $diverso = json_encode($lista['itemListElement'] ?? []) !== json_encode($r['itemListElement']);
                if ($diverso) {
                    $lista['itemListElement'] = $r['itemListElement'];
                    $lista['numberOfItems'] = count($r['itemListElement']);
                    $doc = ws_listrule_metti($doc, $pos, $lista);
                    $cambiato = true;
                    $out['cambiate']++;
                }
                $out['liste'][] = [
                    // Il nome della lista è quello del DOCUMENTO, più la sezione se
                    // è una sezione: «bambini-e-famiglie › Adatto ai bambini».
                    'id' => (($doc['mainEntity']['@id'] ?? $doc['@id'] ?? $f))
                        . ($pos === null ? '' : ' › ' . ($lista['name'] ?? "parte $pos")),
                    'trovati' => count($trovati),
                    'voci' => count($r['itemListElement']),
                    'aggiunte' => $r['aggiunte'],
                    'orfane' => $r['orfane'],
                    'incomplete' => $r['incomplete'],
                    'diversa' => $diverso,
                    'scritta' => false,
                ];
                $righe[] = count($out['liste']) - 1;
            }
            if ($apply && $cambiato) {
                $doc['dateModified'] = date('c');
                $ok = @file_put_contents($f, json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) !== false;
                foreach ($righe as $i) $out['liste'][$i]['scritta'] = $ok;
            }
        }
        return $out;
    }

    /** Le liste che dichiarano una regola, come [percorso file => documento]. */
    function ws_listrule_lists(string $base): array {
        $out = [];
        foreach (['places/*/*', 'places/*', 'events/*', 'organizations/*'] as $g) {
            foreach (glob(rtrim($base, '/') . "/$g/index.json") as $f) {
                $j = json_decode((string)@file_get_contents($f), true);
                if (!is_array($j)) continue;
                if (ws_listrule_dentro($j)) $out[$f] = $j;
            }
        }
        return $out;
    }

    /**
     * Le liste con regola dentro un documento: quella in cima e quelle nelle SEZIONI.
     *
     * Una raccolta può essere divisa in parti — «Adatto ai bambini» e «Progettato per
     * i bambini» — e ogni parte ha la sua regola. Sono liste a tutti gli effetti, e
     * l'unica differenza è che non stanno in un file per conto loro: dividere una
     * categoria in due non deve costare due indirizzi e due pagine.
     *
     * Ritorna un elenco di posizioni: `null` = l'entità in cima, un intero = quel
     * `hasPart`. Le posizioni servono a riscrivere ognuna al suo posto.
     */
    function ws_listrule_posizioni(array $doc): array {
        $e = $doc['mainEntity'] ?? $doc;
        $pos = [];
        if (!empty($e['meetoo:listRule'])) $pos[] = null;
        foreach ((array)($e['hasPart'] ?? []) as $i => $parte) {
            if (is_array($parte) && !empty($parte['meetoo:listRule'])) $pos[] = $i;
        }
        return $pos;
    }

    /** Il documento contiene almeno una lista con regola? */
    function ws_listrule_dentro(array $doc): bool {
        return !empty(ws_listrule_posizioni($doc));
    }

    /** La lista in una data posizione (null = l'entità in cima). */
    function ws_listrule_prendi(array $doc, $pos): array {
        $e = $doc['mainEntity'] ?? $doc;
        return $pos === null ? $e : (array)($e['hasPart'][$pos] ?? []);
    }

    /** Rimette la lista al suo posto dentro il documento. */
    function ws_listrule_metti(array $doc, $pos, array $lista): array {
        if ($pos === null) {
            if (isset($doc['mainEntity'])) $doc['mainEntity'] = $lista; else $doc = $lista;
            return $doc;
        }
        if (isset($doc['mainEntity'])) $doc['mainEntity']['hasPart'][$pos] = $lista;
        else $doc['hasPart'][$pos] = $lista;
        return $doc;
    }

    /** Tipo da mettere nella voce di lista: il primo, per non copiare tutto. */
    function ws_listrule_tipo(array $e): string {
        $t = $e['@type'] ?? 'Thing';
        return is_array($t) ? (string)($t[0] ?? 'Thing') : (string)$t;
    }
}
