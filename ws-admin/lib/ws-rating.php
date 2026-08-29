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
        $r = ['id' => $id, 'da' => null, 'a' => null, 'count' => 0, 'scritto' => false, 'motivo' => ''];
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

        /* NON SI TOCCA QUELLO CHE NON È NOSTRO.
         *
         * Sessanta luoghi hanno già una media — 4,1 · 4,5 · 3,8 — e non l'ha data
         * nessuno di qui: viene da Google, importata insieme alla scheda. È
         * un'informazione vera e utile, e un ricalcolo che la sostituisse con
         * «niente, nessuno ha ancora votato» sarebbe una perdita, non un
         * aggiornamento.
         *
         * Quindi si scrive solo dove la media è NOSTRA, e la si riconosce da un
         * marchio che ci mettiamo scrivendola. Senza marchio, la media resta di
         * chi l'ha portata e la riga lo dice. */
        $nostra = isset($ent['aggregateRating']['meetoo:source'])
            && (string)$ent['aggregateRating']['meetoo:source'] === 'reviews';
        /* Una media SENZA VALORE non è la media di nessuno: `{"ratingValue": null}`
         * è un contenitore rimasto vuoto dall'importazione, non un'informazione da
         * proteggere. Quella si può riempire. */
        $vuota = !isset($ent['aggregateRating']['ratingValue'])
            || $ent['aggregateRating']['ratingValue'] === null
            || $ent['aggregateRating']['ratingValue'] === '';
        if (isset($ent['aggregateRating']) && !$nostra && !$vuota) {
            $r['motivo'] = 'media non nostra (importata): lasciata com\'è';
            return $r;
        }

        if (!$m) {
            /* Nessun voto: si TOGLIE il campo invece di lasciarlo a zero. «0 su 5»
             * è un giudizio pessimo; «non ancora valutato» è un'altra cosa, ed è
             * quella vera. */
            if (!isset($ent['aggregateRating']) || (!$nostra && $vuota)) {
                $r['motivo'] = 'nessun voto, e non c\'era niente da togliere';
                return $r;
            }
            unset($ent['aggregateRating']);
            $r['motivo'] = 'nessun voto: aggregateRating tolto';
        } else {
            $r['a'] = $m['value'];
            $r['count'] = $m['count'];
            $ent['aggregateRating'] = [
                '@type' => 'AggregateRating',
                // Il marchio: dice che questa media l'abbiamo calcolata noi dai voti
                // di chi c'era, e che quindi si può ricalcolare. Senza, è di
                // qualcun altro e non si tocca.
                'meetoo:source' => 'reviews',
                'ratingValue' => $m['value'],
                'bestRating' => 5,
                'worstRating' => 1,
                'ratingCount' => $m['count'],
                'reviewCount' => $m['reviews'],
            ];
            if ($prima !== null && (float)$prima === (float)$m['value']
                && (int)($ent['aggregateRating']['ratingCount'] ?? 0) === $m['count']) {
                $r['motivo'] = 'già aggiornato';
            }
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
                // Solo le NOSTRE: una media importata non va rivisitata, e
                // metterla in elenco vorrebbe dire solo stampare una riga inutile.
                if (isset($ent['aggregateRating']['meetoo:source'])
                    && (string)$ent['aggregateRating']['meetoo:source'] === 'reviews') {
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
