<?php
/**
 * Normalizzazione di date, fusi e @id degli eventi.
 *
 * Tre cose che l'editor fa ora a ogni salvataggio, ma che i file scritti prima non
 * hanno, e che a mano sarebbero centinaia di modifiche:
 *
 *  1. le DATE portano lo scarto da UTC (`2026-08-31T19:00:00+02:00`). Un'ora senza
 *     scarto è ambigua: chi legge il JSON da fuori non sa a quale orologio si
 *     riferisce;
 *  2. il FUSO viaggia in `meetoo:timezone`, perché dallo scarto non si ricava
 *     (+02:00 d'estate ce l'ha mezza Europa). Si deduce dal paese del luogo;
 *  3. l'@id coincide con la CARTELLA. La cartella è la verità — è lì che il file
 *     sta, ed è quello che i percorsi risolvono — quindi un @id che dice altro si
 *     riallinea a lei, non viceversa.
 *
 * Le cartelle con il nome malformato (per esempio `20260716T11730-…`, cinque cifre
 * nell'ora) vengono SEGNALATE e basta: rinominarle significa spostare una cartella
 * e riscrivere ogni riferimento che la cita, ed è una decisione che va presa
 * guardando i casi, non un effetto collaterale di una normalizzazione.
 */

if (!function_exists('ws_quando_fuso_del_luogo')) {
    /** Il fuso di un luogo, dal paese scritto nel suo @id (`places/IT00122/…` → IT).
     *  Solo per i paesi con UN fuso solo: dove ce n'è più d'uno, indovinare è peggio
     *  che lasciar stare. */
    function ws_quando_fuso_del_luogo(?string $idLuogo): string {
        if (!preg_match('#^places/([A-Z]{2})\d{4,5}/#', (string)$idLuogo, $m)) return '';
        $zone = DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, $m[1]);
        return (is_array($zone) && count($zone) === 1) ? $zone[0] : '';
    }
}

if (!function_exists('ws_quando_con_offset')) {
    /** Aggiunge (o rifà) lo scarto a una data con l'ora. Le date senza ora restano
     *  nude: «2026-06-07» è un giorno, non un istante. Quello che non è una data
     *  resta com'è: un valore rotto va corretto a mano, non limato di nascosto. */
    function ws_quando_con_offset(?string $iso, string $zona): ?string {
        $s = trim((string)$iso);
        if ($s === '' || strpos($s, 'T') === false) return $iso;
        if (!preg_match('/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2})/', $s, $m)) return $iso;
        try {
            $dt = new DateTime($m[1], new DateTimeZone($zona));
        } catch (Exception $e) {
            return $iso;
        }
        return $m[1] . ':00' . $dt->format('P');
    }
}

if (!function_exists('ws_quando_id_atteso')) {
    /** L'@id che una cartella si porta dietro per il solo fatto di chiamarsi così. */
    function ws_quando_id_atteso(string $cartella): string {
        return 'events/' . trim($cartella, '/');
    }
}

if (!function_exists('ws_quando_nome_regolare')) {
    /** Il nome della cartella rispetta la regola? Ritorna '' se sì, il perché se no. */
    function ws_quando_nome_regolare(string $nome, bool $serie): string {
        if ($serie) {
            return preg_match('/^[a-z0-9][a-z0-9_-]*$/', $nome)
                ? '' : 'una collezione si scrive in minuscolo (es. clubdellibro-ostia-reading_party)';
        }
        if (!preg_match('/^\d{8}T\d{4}-/', $nome)) return 'un evento singolo comincia con data e ora (es. 20261017T1000-…)';
        return preg_match('/^\d{8}T\d{4}-([A-Z]{2}\d{4,5}|online)-[a-z0-9][a-z0-9_-]*$/', $nome)
            ? '' : 'la forma è data-CAP-nome, o data-online-nome';
    }
}

if (!function_exists('ws_quando_normalizza')) {
    /**
     * Passa tutti gli eventi. In anteprima non scrive niente.
     * Ritorna ['changes' => n, 'done' => [...], 'segnalati' => [...]].
     */
    function ws_quando_normalizza(string $base, bool $apply, string $predefinito = 'Europe/Rome'): array {
        $dir = rtrim($base, '/') . '/events';
        $done = [];
        $segnalati = [];
        if (!is_dir($dir)) return ['changes' => 0, 'done' => [], 'segnalati' => []];

        foreach (scandir($dir) as $nome) {
            if ($nome === '.' || $nome === '..' || $nome === '_index') continue;
            $file = "$dir/$nome/index.json";
            if (!is_file($file)) continue;
            $raw = (string)file_get_contents($file);
            $doc = json_decode($raw, true);
            if (!is_array($doc)) { $segnalati[] = "$nome: JSON illeggibile"; continue; }

            $guscio = isset($doc['mainEntity']) && is_array($doc['mainEntity']);
            $e = $guscio ? $doc['mainEntity'] : $doc;
            $prima = json_encode($e, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $cambi = [];

            $tipi = (array)($e['@type'] ?? []);
            $serie = in_array('EventSeries', $tipi, true);

            // 1) L'@id segue la cartella.
            $atteso = ws_quando_id_atteso($nome);
            if (($e['@id'] ?? '') !== $atteso) {
                $cambi[] = '@id: ' . ($e['@id'] ?? '(assente)') . " → $atteso";
                $e['@id'] = $atteso;
            }
            // …e il nome della cartella si guarda, ma non si tocca.
            $perche = ws_quando_nome_regolare($nome, $serie);
            if ($perche !== '') $segnalati[] = "$nome: $perche";

            // 2) Il fuso: dal luogo, o quello del sito.
            $zona = ws_quando_fuso_del_luogo($e['location']['@id'] ?? '') ?: $predefinito;
            if (($e['meetoo:timezone'] ?? '') !== $zona && (isset($e['startDate']) || isset($e['endDate']))) {
                $cambi[] = 'meetoo:timezone: ' . ($e['meetoo:timezone'] ?? '(assente)') . " → $zona";
                $e['meetoo:timezone'] = $zona;
            }

            // 3) Le date, con lo scarto.
            foreach (['startDate', 'endDate'] as $campo) {
                if (!isset($e[$campo]) || !is_string($e[$campo])) continue;
                $nuovo = ws_quando_con_offset($e[$campo], $zona);
                if ($nuovo !== $e[$campo]) {
                    $cambi[] = "$campo: {$e[$campo]} → $nuovo";
                    $e[$campo] = $nuovo;
                }
            }
            // Il programma di un evento singolo (le occorrenze di una serie sono
            // riferimenti: le loro date stanno nei loro file, non qui).
            if (!$serie && isset($e['subEvent']) && is_array($e['subEvent'])) {
                foreach ($e['subEvent'] as $i => $s) {
                    if (!is_array($s)) continue;
                    foreach (['startDate', 'endDate'] as $campo) {
                        if (!isset($s[$campo]) || !is_string($s[$campo])) continue;
                        $nuovo = ws_quando_con_offset($s[$campo], $zona);
                        if ($nuovo !== $s[$campo]) {
                            $cambi[] = "subEvent[$i].$campo: {$s[$campo]} → $nuovo";
                            $e['subEvent'][$i][$campo] = $nuovo;
                        }
                    }
                }
            }

            if (!$cambi) continue;
            $done[] = ['path' => $nome, 'cambi' => $cambi];
            if (!$apply) continue;

            if ($guscio) $doc['mainEntity'] = $e; else $doc = $e;
            $scritto = json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($scritto === false || @file_put_contents($file, $scritto . "\n") === false) {
                $segnalati[] = "$nome: scrittura fallita";
                array_pop($done);
            }
            unset($prima);
        }
        return ['changes' => count($done), 'done' => $done, 'segnalati' => $segnalati];
    }
}
