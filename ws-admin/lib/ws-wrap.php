<?php
/*
 * Il guscio di pagina: `ItemPage` + `mainEntity`.
 *
 * Un file di contenuto descrive DUE cose: la pagina (quando è stata aggiornata,
 * a che indirizzo risponde, se i motori la devono indicizzare) e l'entità di cui
 * parla (un evento, un luogo, una persona). Il guscio le tiene separate:
 *
 *   { "@type": "ItemPage", "dateModified": …, "mainEntity": { …l'entità… } }
 *
 * È la stessa struttura dei contenuti del CMS, dove `<section>` porta `wspath`,
 * `query`, `robots`, `changefreq` e poi `mainContentOfPage`. Senza guscio quei
 * dati non avrebbero un posto dove stare se non dentro l'entità — e un Event non
 * ha un `robots`.
 *
 * Luoghi e organizzazioni ce l'hanno da sempre; eventi e utenti no. Questa
 * migrazione mette d'accordo i secondi con i primi.
 */

if (!function_exists('ws_wrap_migrate')) {

    /** Il @context di Meetoo, per le entità che non ne portano uno. */
    function ws_wrap_context(): array {
        return ['https://schema.org', ['meetoo' => 'https://meetoo.eu#']];
    }

    /** Il documento ha già il guscio? */
    function ws_wrap_has(array $doc): bool {
        return isset($doc['mainEntity']) && is_array($doc['mainEntity']);
    }

    /**
     * Avvolge un'entità nuda. `dateModified` sale nel guscio — nel guscio è la data
     * della PAGINA — e resta anche nell'entità se ce l'aveva: sono due date diverse
     * (quando è cambiata la pagina, quando è cambiato il fatto descritto).
     */
    function ws_wrap_one(array $ent): array {
        $ctx = $ent['@context'] ?? ws_wrap_context();
        return [
            '@context' => $ctx,
            '@type' => 'ItemPage',
            'dateModified' => $ent['dateModified'] ?? date('c'),
            'mainEntity' => $ent,
        ];
    }

    /** L'entità, comunque sia scritto il documento. */
    function ws_wrap_entity(array $doc): array {
        return ws_wrap_has($doc) ? $doc['mainEntity'] : $doc;
    }

    /**
     * Rimette l'entità nel documento SENZA perdere il guscio: è la funzione che
     * devono usare le librerie che leggono, modificano e riscrivono. Chi scriveva
     * `$doc = $ent` cancellava il guscio in silenzio.
     */
    function ws_wrap_set(array $doc, array $ent): array {
        if (!ws_wrap_has($doc)) return ws_wrap_one($ent);
        $doc['mainEntity'] = $ent;
        $doc['dateModified'] = date('c');
        return $doc;
    }

    /**
     * Avvolge tutte le entità che ancora non hanno il guscio.
     * $apply=false → dice soltanto quali. Ritorna ['done'=>[], 'failed'=>[]].
     */
    function ws_wrap_migrate(string $base, bool $apply): array {
        $done = []; $failed = [];
        foreach (['events/*', 'places/*/*', 'places/*', 'organizations/*', 'users/*'] as $g) {
            foreach (glob(rtrim($base, '/') . "/$g/index.json") as $f) {
                $raw = (string)@file_get_contents($f);
                $doc = json_decode($raw, true);
                $rel = trim(str_replace(rtrim($base, '/'), '', dirname($f)), '/');
                if (!is_array($doc)) { $failed[] = ['path' => $rel, 'why' => 'index.json illeggibile']; continue; }
                if (ws_wrap_has($doc)) continue;
                if (empty($doc['@type'])) { $failed[] = ['path' => $rel, 'why' => 'senza @type: non so che entità sia']; continue; }

                $done[] = ['path' => $rel, 'type' => is_array($doc['@type']) ? implode('+', $doc['@type']) : $doc['@type']];
                if (!$apply) continue;
                $nuovo = ws_wrap_one($doc);
                if (@file_put_contents($f, json_encode($nuovo, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) === false) {
                    array_pop($done);
                    $failed[] = ['path' => $rel, 'why' => 'scrittura fallita'];
                }
            }
        }
        usort($done, fn($a, $b) => strcmp($a['path'], $b['path']));
        return ['done' => $done, 'failed' => $failed];
    }
}
