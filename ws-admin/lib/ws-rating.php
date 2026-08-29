<?php
/**
 * LA VALUTAZIONE MEDIA, ricalcolata dai voti.
 *
 * I voti li danno le persone e stanno accanto agli eventi, in `ratings.json`:
 * una riga per bersaglio — l'evento, chi l'ha organizzato, il luogo — e dentro
 * chi ha votato e quanto. L'`aggregateRating` che sta nel documento di un
 * gruppo o di un locale è invece un DERIVATO: la stessa cosa detta in breve,
 * perché una card non può leggere quaranta file per scrivere «4,5».
 *
 * Un derivato ha una regola sola: si ricalcola, non si scrive a mano. Finché
 * qualcuno lo digitava nell'editor era un numero che diceva quello che voleva;
 * da qui in poi è la media dei voti veri, e se i due non coincidono ha ragione
 * il file dei voti.
 *
 * Un gruppo raccoglie i voti presi in TUTTI gli eventi che ha organizzato, un
 * luogo quelli di tutti gli eventi che ha ospitato: per questo si legge l'intero
 * archivio dei voti e non il singolo evento. È un giro di cartelle, e per questo
 * lo si fa quando lo si chiede — dalla Gestione o dall'editor — e non a ogni
 * stella toccata.
 *
 * DUE VOCI, UNA MEDIA.
 *
 * Un luogo ha spesso già una valutazione importata da Google — 4,0 su 3237
 * recensioni — e da qui arrivano quelle di chi è stato a un evento lì. Sono la
 * stessa domanda fatta a persone diverse, quindi si sommano invece di
 * escludersi; ma non si sommano alla pari: una media su 3237 voci e una su 5
 * pesano quanto le voci che le compongono.
 *
 * La formula è la MEDIA PONDERATA sul numero di voti:
 *
 *     V = (n_g · r_g + n_m · r_m) / (n_g + n_m)
 *
 * È l'unica che dà lo stesso risultato che si otterrebbe rimettendo insieme
 * tutti i voti singoli e rifacendo la media: quando due medie riassumono due
 * campioni della stessa cosa, la media del campione unito è questa. Non serve
 * altro — niente correzioni bayesiane, che servono a ORDINARE una classifica
 * (dove una media di 5,0 su un voto solo salirebbe in cima), non a raccontare
 * un giudizio accanto al numero di persone che l'hanno dato: chi legge «4,0 su
 * 3242 valutazioni» l'incertezza la vede da sé.
 *
 * Le due voci restano SEPARATE nel documento (`meetoo:ratings`), e la media
 * combinata è quello che si mostra. Tenerle separate è ciò che rende il calcolo
 * ripetibile: da una sola media mescolata non si può più tornare indietro, né
 * aggiornare Google senza perdere noi.
 *
 * Se un giorno la voce di chi c'era dovesse pesare più di quella di un passante
 * su Google, è una costante da aggiungere qui — non una formula da rifare.
 *
 * Come si usa:
 *   ws_rating_raccogli($base)              → tutti i voti, per bersaglio
 *   ws_rating_aggiorna($base, $id, $apply) → riscrive l'aggregateRating di uno
 *   ws_rating_aggiorna_tutti($base, $apply)→ ...di tutti quelli che ne hanno
 */

if (!function_exists('ws_rating_raccogli')) {

    /**
     * Tutti i voti dell'archivio, radunati per bersaglio.
     *
     * Ritorna [@id => ['value' => media, 'count' => quanti, 'reviews' => quante
     * scritte]]. Il voto può essere un numero nudo (i file di prima) o
     * `{value, text}`: si accettano tutti e due, perché un formato che cambia non
     * cancella quello che la gente aveva già detto.
     */
    function ws_rating_raccogli(string $base): array {
        $somme = [];
        foreach (glob("$base/events/*/ratings.json") as $f) {
            $d = json_decode((string)@file_get_contents($f), true);
            if (!is_array($d) || !isset($d['targets']) || !is_array($d['targets'])) continue;
            foreach ($d['targets'] as $id => $voti) {
                if (!is_array($voti)) continue;
                foreach ($voti as $v) {
                    $valore = is_array($v) ? (int)($v['value'] ?? 0) : (int)$v;
                    $testo = is_array($v) ? trim((string)($v['text'] ?? '')) : '';
                    if ($valore < 1 || $valore > 5) continue;
                    if (!isset($somme[$id])) $somme[$id] = ['somma' => 0, 'n' => 0, 'scritte' => 0];
                    $somme[$id]['somma'] += $valore;
                    $somme[$id]['n']++;
                    if ($testo !== '') $somme[$id]['scritte']++;
                }
            }
        }
        $out = [];
        foreach ($somme as $id => $x) {
            $out[$id] = [
                'value' => round($x['somma'] / $x['n'], 1),
                'count' => $x['n'],
                'reviews' => $x['scritte'],
            ];
        }
        return $out;
    }

    /** Il file di un contenuto, dal suo @id. '' se non esiste. */
    function ws_rating_file(string $base, string $id): string {
        $f = "$base/" . trim($id, '/') . '/index.json';
        return is_file($f) ? $f : '';
    }

    /**
     * Riscrive l'`aggregateRating` di UN bersaglio.
     *
     * `$apply = false` dice soltanto che cosa cambierebbe: è il modo in cui ogni
     * operazione di manutenzione di questo progetto si fa guardare prima di
     * scrivere. Ritorna [id, da, a, count, scritto, motivo].
     */
    function ws_rating_aggiorna(string $base, string $id, bool $apply, ?array $medie = null): array {
        $medie = $medie ?? ws_rating_raccogli($base);
        $m = $medie[$id] ?? null;
        $f = ws_rating_file($base, $id);
        $r = ['id' => $id, 'da' => null, 'a' => null, 'count' => 0, 'fonti' => '', 'scritto' => false, 'motivo' => ''];
        if ($f === '') {
            $r['motivo'] = 'nessun documento con questo @id';
            return $r;
        }
        $doc = json_decode((string)@file_get_contents($f), true);
        if (!is_array($doc)) {
            $r['motivo'] = 'documento illeggibile';
            return $r;
        }
        $chiave = isset($doc['mainEntity']) && is_array($doc['mainEntity']) ? 'mainEntity' : null;
        $ent = $chiave ? $doc[$chiave] : $doc;
        $prima = isset($ent['aggregateRating']['ratingValue']) ? $ent['aggregateRating']['ratingValue'] : null;
        $r['da'] = $prima;

        /* LA VOCE DI GOOGLE si mette al sicuro in un campo suo, la prima volta che
         * si passa di qui.
         *
         * Nel documento importato sta in `aggregateRating`, che è anche il posto
         * dove va a finire la media combinata: lasciandola lì, il primo calcolo la
         * cancellerebbe. Spostandola in `meetoo:ratings.google` non si perde e si
         * può ricalcolare all'infinito.
         *
         * Un `aggregateRating` SENZA il nostro marchio è, per definizione, roba
         * arrivata da fuori: alla prima passata è l'importazione originale, alle
         * successive è una reimportazione più fresca. In tutti e due i casi la
         * risposta è la stessa — è la voce di Google, e va lì. */
        $voci = isset($ent['meetoo:ratings']) && is_array($ent['meetoo:ratings']) ? $ent['meetoo:ratings'] : [];
        $agg = isset($ent['aggregateRating']) && is_array($ent['aggregateRating']) ? $ent['aggregateRating'] : [];
        $mio = isset($agg['meetoo:source']) && in_array((string)$agg['meetoo:source'], ['reviews', 'combined'], true);
        $valEsterno = isset($agg['ratingValue']) && $agg['ratingValue'] !== null && $agg['ratingValue'] !== '';
        if (!$mio && $valEsterno) {
            $voci['google'] = [
                'ratingValue' => (float)$agg['ratingValue'],
                // Google conta le recensioni; se non l'ha detto, quella voce pesa
                // come una sola — è l'ipotesi più prudente, non un numero inventato.
                'reviewCount' => (int)($agg['reviewCount'] ?? $agg['ratingCount'] ?? 1) ?: 1,
            ];
        }

        $g = isset($voci['google']['ratingValue']) ? (float)$voci['google']['ratingValue'] : null;
        $gn = max(1, (int)($voci['google']['reviewCount'] ?? 1));
        if ($g === null) unset($voci['google']);

        if ($m) {
            $voci['meetoo'] = ['ratingValue' => $m['value'], 'ratingCount' => $m['count'], 'reviewCount' => $m['reviews']];
        } else {
            unset($voci['meetoo']);
        }

        if ($g === null && !$m) {
            // Nessuna delle due voci: si toglie tutto invece di lasciare uno zero.
            // «0 su 5» è un giudizio pessimo; «non ancora valutato» è un'altra cosa.
            if (!isset($ent['aggregateRating']) && !isset($ent['meetoo:ratings'])) {
                $r['motivo'] = 'nessun voto, e non c\'era niente da togliere';
                return $r;
            }
            unset($ent['aggregateRating'], $ent['meetoo:ratings']);
            $r['motivo'] = 'nessun voto: aggregateRating tolto';
        } else {
            // La media ponderata: vedi la formula in cima al file.
            $peso = ($g !== null ? $gn : 0) + ($m ? $m['count'] : 0);
            $somma = ($g !== null ? $g * $gn : 0) + ($m ? $m['value'] * $m['count'] : 0);
            $media = round($somma / $peso, 1);
            $r['a'] = $media;
            $r['count'] = $peso;
            $r['fonti'] = ($g !== null ? "Google $g su $gn" : '')
                . ($g !== null && $m ? ' + ' : '')
                . ($m ? "Meetoo {$m['value']} su {$m['count']}" : '');
            $ent['meetoo:ratings'] = $voci;
            $ent['aggregateRating'] = [
                '@type' => 'AggregateRating',
                // Il marchio: dice che questa media l'abbiamo calcolata noi mettendo
                // insieme le voci qui sopra, e che quindi si può rifare. Senza, è di
                // qualcun altro e la prossima passata la raccoglie come voce esterna.
                'meetoo:source' => 'combined',
                'ratingValue' => $media,
                'bestRating' => 5,
                'worstRating' => 1,
                'ratingCount' => $peso,
                // Le recensioni scritte: quelle di Google più quelle di qui.
                'reviewCount' => ($g !== null ? $gn : 0) + ($m ? $m['reviews'] : 0),
            ];
        }
        // Si scrive solo se cambia davvero: un ricalcolo a vuoto non deve toccare
        // dateModified né sporcare il confronto con il server.
        $nuovo = $doc;
        if ($chiave) $nuovo[$chiave] = $ent; else $nuovo = $ent;
        if (json_encode($nuovo) === json_encode($doc)) {
            if ($r['motivo'] === '') $r['motivo'] = 'nessun cambiamento';
            return $r;
        }
        if (!$apply) {
            $r['motivo'] = $r['motivo'] ?: 'da aggiornare';
            return $r;
        }
        $nuovo['dateModified'] = date('c');
        $r['scritto'] = @file_put_contents($f, json_encode($nuovo, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) !== false;
        if (!$r['scritto']) $r['motivo'] = 'non riesco a scrivere';
        return $r;
    }

    /**
     * Riscrive l'`aggregateRating` di tutto ciò che ha ricevuto voti.
     *
     * Anche di chi non ne ha più: un gruppo che aveva una media e a cui i voti
     * sono stati ritirati deve tornare senza media, non restare con quella
     * vecchia. Per questo si guardano anche i documenti che un `aggregateRating`
     * ce l'hanno scritto ma che nei voti non compaiono.
     */
    function ws_rating_aggiorna_tutti(string $base, bool $apply): array {
        $medie = ws_rating_raccogli($base);
        $ids = array_keys($medie);
        foreach (['organizations/*', 'places/*/*', 'events/*'] as $dove) {
            foreach (glob("$base/$dove/index.json") as $f) {
                $d = json_decode((string)@file_get_contents($f), true);
                if (!is_array($d)) continue;
                $ent = (isset($d['mainEntity']) && is_array($d['mainEntity'])) ? $d['mainEntity'] : $d;
                /* Tutto ciò che una valutazione ce l'ha, da qualunque parte venga:
                 * quella di Google va messa al sicuro nel suo campo e rientra nella
                 * media combinata, la nostra va tenuta aggiornata. */
                if (isset($ent['aggregateRating']) || isset($ent['meetoo:ratings'])) {
                    $id = trim((string)($ent['@id'] ?? ''), '/');
                    if ($id !== '' && !in_array($id, $ids, true)) $ids[] = $id;
                }
            }
        }
        $righe = [];
        $cambiati = 0;
        foreach ($ids as $id) {
            $r = ws_rating_aggiorna($base, $id, $apply, $medie);
            if ($r['scritto'] || $r['motivo'] === 'da aggiornare' || $r['motivo'] === 'nessun voto: aggregateRating tolto') $cambiati++;
            $righe[] = $r;
        }
        return ['righe' => $righe, 'cambiati' => $cambiati, 'bersagli' => count($ids)];
    }
}
