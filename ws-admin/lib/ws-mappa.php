<?php
/**
 * La mappa del sito Meetoo, generata dai contenuti.
 *
 * Nel CMS ogni pagina è un `<url>` del sitemap, e da lì escono tre cose: l'indirizzo
 * a cui risponde (`wspath`), che cosa caricare per rispondere (`query`: tema,
 * template, contenuto) e i metadati che finiscono nella testa dell'HTML (`title`,
 * `description`, `robots`). Nei siti scritti a mano quei valori stanno dentro i
 * `.wsx` e il sitemap li pesca con XInclude; i contenuti Meetoo però nascono in
 * JSON dall'editor e di routing non sanno niente — giustamente, perché un evento è
 * un evento, non una pagina.
 *
 * Quindi il ponte si costruisce qui: una regola sola, in un posto solo, che dice
 * quale indirizzo ha ogni tipo di entità. Cambiare gli indirizzi del sito vuol dire
 * cambiare la tabella qui sotto e rigenerare.
 *
 * Gli indirizzi si scrivono nella forma DEFINITIVA, senza il prefisso `/meetoo`:
 * il prefisso è un fatto dell'ospite (vedi WS_MOUNTS e ws_href), non del contenuto,
 * e il giorno del dominio proprio non ci sarà niente da riscrivere.
 */

// I prefissi dei siti innestati: stessa definizione che usa il CMS per instradare.
require_once __DIR__ . '/../../ws-core/mounts.php';

if (!function_exists('ws_mappa_wspath')) {

    /** Nome della cartella-foglia di un @id: `events/20261017T1000-…` → `20261017T1000-…`. */
    function ws_mappa_slug(string $rel): string {
        $p = explode('/', trim($rel, '/'));
        return end($p) ?: '';
    }

    /* =======================================================================
     * L'ALBERO DELLE ZONE
     *
     * L'indirizzo di una pagina dice dove sei: `/roma/municipio10/lido-di-ostia`.
     * Quell'albero è CONTENUTO — sta in `index/index.json`, città dentro cui
     * municipi dentro cui quartieri — e qui si legge per sapere, di ogni zona, il
     * percorso completo. Aggiungere un quartiere è aggiungere un nodo lì, non una
     * riga qui.
     *
     * I file NON si spostano: `places/lido-di-ostia` resta dov'è, con il suo @id.
     * L'indirizzo è una decisione di instradamento, e questo è il posto dove si
     * prende — che è esattamente il mestiere di questo file.
     * ===================================================================== */

    /**
     * Ogni nodo dell'albero delle zone: slug → percorso, nome, tipo, contenitore.
     *
     * L'albero di una città sta NELLA SUA CARTELLA — `places/roma/index.json` — e non
     * più nell'indice del sito: Roma è un contenuto, e le sue parti sono cosa sua.
     * L'indice si limita a dire quali città ci sono.
     *
     * Ogni nodo si identifica con `@id`, non con un `identifier`: l'@id è il modo in
     * cui in tutto Meetoo una cosa dice chi è, e un secondo modo per dirlo — valido
     * solo qui dentro — era una parola in più da ricordare e un posto in più dove
     * sbagliare. Il pezzo di indirizzo è il suo ultimo segmento.
     */
    function ws_mappa_zone(string $localeDir): array {
        $zone = [];
        $scendi = function ($nodo, string $prefisso, int $liv, string $padre) use (&$scendi, &$zone) {
            $id = trim((string)($nodo['@id'] ?? ''), '/');
            $slug = ws_mappa_slug($id);
            if ($slug === '') return;
            $percorso = ($prefisso === '' ? '' : "$prefisso/") . $slug;
            $figli = (array)($nodo['containsPlace'] ?? []);
            $zone[$slug] = [
                'id' => $id,
                'percorso' => $percorso,
                'nome' => (string)($nodo['name'] ?? $slug),
                'tipo' => (string)(is_array($nodo['@type'] ?? '') ? reset($nodo['@type']) : ($nodo['@type'] ?? 'Place')),
                'livello' => $liv,
                'padre' => $padre,
                'descrizione' => (string)($nodo['description'] ?? ''),
                // Un nodo che ne contiene altri è un CONTENITORE: una città, un
                // municipio. Non ha eventi propri — ha dentro chi li ha.
                'contenitore' => count($figli) > 0,
            ];
            foreach ($figli as $figlio) {
                if (is_array($figlio)) $scendi($figlio, $percorso, $liv + 1, $slug);
            }
        };
        foreach (ws_mappa_citta($localeDir) as $citta) $scendi($citta, '', 0, '');
        return $zone;
    }

    /**
     * Le città: quelle che l'indice del sito dichiara, o — se non ne dichiara — tutti
     * i `places/<slug>` che sono una City. La seconda strada serve perché un albero
     * che esiste sul disco non deve sparire dal sito solo perché qualcuno ha
     * dimenticato di elencarlo.
     */
    function ws_mappa_citta(string $localeDir): array {
        $out = [];
        $doc = json_decode((string)@file_get_contents("$localeDir/index/index.json"), true);
        $e = is_array($doc) ? ($doc['mainEntity'] ?? $doc) : [];
        foreach ((array)($e['hasPart']['itemListElement'] ?? []) as $voce) {
            $rif = is_array($voce) ? ($voce['item'] ?? $voce) : [];
            $id = trim((string)($rif['@id'] ?? ''), '/');
            if ($id === '') continue;
            $j = json_decode((string)@file_get_contents("$localeDir/$id/index.json"), true);
            if (is_array($j)) $out[] = $j['mainEntity'] ?? $j;
        }
        if ($out) return $out;

        foreach (glob("$localeDir/places/*/index.json") as $f) {
            $j = json_decode((string)@file_get_contents($f), true);
            if (!is_array($j)) continue;
            $e = $j['mainEntity'] ?? $j;
            if (in_array('City', (array)($e['@type'] ?? []), true)) $out[] = $e;
        }
        return $out;
    }

    /**
     * Quale zona rivendica un CAP. Lo dichiara la zona stessa, in
     * `meetoo:postalCodes`: è l'unico aggancio pulito fra una zona e le sue cose.
     * La `addressLocality` scritta a mano dice «Ostia», «Ostia Lido», «Lido di
     * Ostia» e un terzo delle volte niente — con quella non si instrada.
     */
    function ws_mappa_cap(string $localeDir, array $zone): array {
        $out = [];
        foreach (array_keys($zone) as $slug) {
            $f = "$localeDir/places/$slug/index.json";
            if (!is_file($f)) continue;
            $doc = json_decode((string)@file_get_contents($f), true);
            if (!is_array($doc)) continue;
            $e = $doc['mainEntity'] ?? $doc;
            foreach ((array)($e['meetoo:postalCodes'] ?? []) as $cap) {
                $cap = trim((string)$cap);
                if ($cap !== '') $out[$cap] = $slug;
            }
        }
        return $out;
    }

    /** Il CAP dentro un @id di luogo: `places/IT00122/lamanusa` → `IT00122`. */
    function ws_mappa_cap_di(string $idLuogo): string {
        return preg_match('#^places/([A-Z]{2}\d{4,5})/#', trim($idLuogo, '/'), $m) ? $m[1] : '';
    }

    /**
     * L'indirizzo e il template di un'entità.
     *
     * `$ctx` porta le tabelle che servono a sapere DOVE sta una cosa: l'albero
     * delle zone, i CAP che ognuna rivendica, e la zona degli organizzatori
     * (dedotta dagli eventi che organizzano). Un'entità che non si riesce ad
     * ancorare a nessuna zona non sparisce: prende l'indirizzo piatto di prima e
     * il generatore lo segnala, perché un contenuto irraggiungibile è peggio di un
     * contenuto in un posto discutibile.
     *
     * Ritorna [wspath, template, tipo] oppure null per ciò che pagina non è.
     */
    function ws_mappa_wspath(string $rel, array $tipi, array $ctx = [], array $e = []): ?array {
        $slug = ws_mappa_slug($rel);
        $lista = in_array('ItemList', $tipi, true);
        $serie = in_array('EventSeries', $tipi, true);
        $zone = $ctx['zone'] ?? [];
        $cap = $ctx['cap'] ?? [];

        // Il percorso di una zona, dal suo slug.
        $dove = function (string $zonaSlug) use ($zone): string {
            return $zone[$zonaSlug]['percorso'] ?? '';
        };

        if (strpos($rel, 'events/') === 0) {
            // La zona di un evento la sa gia' la tabella: per un evento singolo e'
            // quella del luogo, per una collezione quella dove stanno le sue
            // occorrenze — una collezione non ha un luogo suo.
            $zonaSlug = $ctx['eventi'][$slug] ?? '';
            $percorso = $zonaSlug ? $dove($zonaSlug) : '';
            $base = $percorso ? "/$percorso/eventi" : '/eventi';
            // Una collezione di eventi e un evento singolo vivono nello stesso
            // posto: per chi legge sono la stessa cosa, un appuntamento che si
            // ripete o no.
            return ["$base/$slug", $serie ? 'collection' : 'event', $serie ? 'EventSeries' : 'Event'];
        }

        if (strpos($rel, 'places/') === 0) {
            $pezzi = explode('/', trim($rel, '/'));
            // Una ZONA — `places/lido-di-ostia` — non è un posto dove si va: è il
            // territorio dentro cui si sta, e il suo indirizzo è il percorso intero.
            if (count($pezzi) === 2 && isset($zone[$pezzi[1]])) {
                /* Una città o un municipio non è un posto dove si va: è quello che
                 * contiene i posti. Ha la sua pagina — chi accorcia l'indirizzo deve
                 * trovarci qualcosa — ma è la pagina di un'AREA, che mostra le sue
                 * parti, non quella di un quartiere con eventi e luoghi propri. */
                $z = $zone[$pezzi[1]];
                return empty($z['contenitore'])
                    ? ['/' . $dove($pezzi[1]), 'zone', 'AdministrativeArea']
                    : ['/' . $dove($pezzi[1]), 'area', $z['tipo'] ?: 'AdministrativeArea'];
            }
            // Le collezioni curate di una zona (il lungomare, il bookcrossing)
            // stanno dentro la zona, perché è lì che si nominano e si condividono.
            if ($lista && count($pezzi) === 3 && isset($zone[$pezzi[1]])) {
                return ['/' . $dove($pezzi[1]) . "/$slug", 'collection', 'ItemList'];
            }
            // Un luogo sta nella zona che rivendica il suo CAP.
            $zonaSlug = $cap[$pezzi[1] ?? ''] ?? '';
            $percorso = $zonaSlug ? $dove($zonaSlug) : '';
            return [($percorso ? "/$percorso/luoghi/$slug" : "/luoghi/$slug"), 'place', 'Place'];
        }

        if (strpos($rel, 'organizations/') === 0) {
            // Un gruppo non ha un indirizzo suo: la sua zona è quella dove fa le
            // cose, dedotta dagli eventi che organizza.
            $zonaSlug = $ctx['org'][$slug] ?? '';
            /* Se di eventi non ne ha ancora, può DIRLO: `containedInPlace` con
             * l'@id di una zona. Un gruppo che non organizza niente non è un gruppo
             * che non sta da nessuna parte — e senza questo la sua pagina esiste ma
             * dagli elenchi della sua zona non ci si arriva. Chi opera solo in rete
             * non lo dichiara, e resta giustamente senza territorio. */
            if ($zonaSlug === '') {
                $dichiarata = ws_mappa_slug(ws_ref_id_semplice($e['containedInPlace'] ?? null));
                if ($dichiarata !== '' && isset($zone[$dichiarata])) $zonaSlug = $dichiarata;
            }
            $percorso = $zonaSlug ? $dove($zonaSlug) : '';
            return [($percorso ? "/$percorso/gruppi/$slug" : "/gruppi/$slug"), 'organizer', 'Organization'];
        }
        return null; // users, persons, brand, _index, _trash: non sono pagine
    }

    /** L'@id di un riferimento, che sia oggetto, stringa o elenco. */
    function ws_ref_id_semplice($x): string {
        if (is_string($x)) return $x;
        if (is_array($x)) {
            if (isset($x['@id'])) return (string)$x['@id'];
            foreach ($x as $v) { $id = ws_ref_id_semplice($v); if ($id !== '') return $id; }
        }
        return '';
    }

    /**
     * La frase che finisce nel `<meta name="description">`, presa dal posto giusto.
     *
     * Da quando i due testi sono separati, `description` È la frase per i motori:
     * testo semplice, scritto apposta, e si usa così com'è. Ma i contenuti scritti
     * prima hanno lì dentro il corpo in XHTML, e quelli nuovi possono avere solo
     * il Sommario: quindi
     *
     *   1. c'è `description`  → si usa quella (ripulita, se contiene marcatura);
     *   2. c'è solo `abstract` → si usa la sua prima parte.
     *
     * Nessun contenuto resta senza, e la migrazione a mano può procedere con calma.
     */
    function ws_mappa_meta_descrizione(array $e): string {
        $seo = trim((string)($e['description'] ?? ''));
        if ($seo !== '') return ws_mappa_descrizione($seo);
        return ws_mappa_descrizione($e['abstract'] ?? '');
    }

    /** Prima frase utile di una descrizione XHTML, per il meta description. */
    function ws_mappa_descrizione($testo, int $max = 160): string {
        /* La fine di un blocco vale uno spazio. Senza, `strip_tags` incolla le
         * parole a cavallo dei tag — «in silenziosa compagnia.Goditi un momento» —
         * e quella frase è esattamente ciò che si legge nel risultato di ricerca. */
        $testo = preg_replace('#</(p|div|li|h[1-6]|blockquote|section|tr)>#i', ' ', (string)$testo);
        $testo = preg_replace('#<(br|hr)\s*/?>#i', ' ', $testo);
        $t = trim(html_entity_decode(strip_tags($testo), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $t = preg_replace('/\s+/u', ' ', $t);
        if (mb_strlen($t) <= $max) return $t;
        $tagliato = mb_substr($t, 0, $max);
        $spazio = mb_strrpos($tagliato, ' ');
        return rtrim($spazio ? mb_substr($tagliato, 0, $spazio) : $tagliato, " ,;:.") . '…';
    }

    /** Tutte le entità con un index.json, come percorsi relativi al locale. */
    function ws_mappa_entita(string $localeDir): array {
        $out = [];
        foreach (['events/*', 'places/*', 'places/*/*', 'organizations/*'] as $g) {
            foreach (glob("$localeDir/$g/index.json") as $f) {
                $out[] = trim(str_replace($localeDir, '', dirname($f)), '/');
            }
        }
        sort($out);
        return $out;
    }

    /**
     * La zona di ogni evento.
     *
     * Un evento singolo sta dove sta il suo luogo — e il luogo può essere un posto
     * con un CAP (`places/IT00122/lamanusa`) oppure la ZONA stessa, quando si dice
     * «a Lido di Ostia» senza precisare dove: sono due scritture legittime della
     * stessa cosa, e vanno accettate tutte e due.
     *
     * Una COLLEZIONE non ha un luogo: si ripete, e ogni volta magari altrove. La
     * sua zona è quella dove stanno le sue occorrenze. Senza questa regola le
     * collezioni finivano tutte fuori dall'albero, che è il posto peggiore per una
     * cosa che invece il territorio ce l'ha eccome.
     */
    function ws_mappa_zone_eventi(string $localeDir, array $cap, array $zone): array {
        $doc = [];
        foreach (glob("$localeDir/events/*/index.json") as $f) {
            $j = json_decode((string)@file_get_contents($f), true);
            if (is_array($j)) $doc[basename(dirname($f))] = $j['mainEntity'] ?? $j;
        }
        $zonaDi = function ($e) use ($cap, $zone): string {
            $id = trim(ws_ref_id_semplice($e['location'] ?? null), '/');
            $c = ws_mappa_cap_di($id);
            if ($c !== '' && isset($cap[$c])) return $cap[$c];
            $pezzi = explode('/', $id);
            // `places/lido-di-ostia` — la zona nominata direttamente.
            if (count($pezzi) >= 2 && $pezzi[0] === 'places' && isset($zone[$pezzi[1]])) return $pezzi[1];
            return '';
        };
        $out = [];
        foreach ($doc as $slug => $e) {
            $z = $zonaDi($e);
            if ($z !== '') $out[$slug] = $z;
        }
        // Le collezioni: la zona più frequente fra le loro occorrenze.
        foreach ($doc as $slug => $e) {
            if (isset($out[$slug])) continue;
            $conta = [];
            foreach ((array)($e['subEvent'] ?? []) as $sub) {
                $occ = ws_mappa_slug(ws_ref_id_semplice($sub));
                if ($occ !== '' && isset($out[$occ])) $conta[$out[$occ]] = ($conta[$out[$occ]] ?? 0) + 1;
            }
            if ($conta) { arsort($conta); $out[$slug] = (string)array_key_first($conta); }
        }
        return $out;
    }

    /**
     * La zona di ogni organizzatore, dedotta dagli eventi che organizza.
     *
     * Un'organizzazione non ha un indirizzo suo — e quando ce l'ha è quello della
     * sede, che non è dove fa le cose. Quindi si guarda dove succedono i suoi
     * eventi: la zona più frequente vince. Chi non ha ancora organizzato niente
     * resta senza, e prende l'indirizzo piatto.
     */
    function ws_mappa_zone_organizzatori(string $localeDir, array $eventi): array {
        $conta = [];
        foreach (glob("$localeDir/events/*/index.json") as $f) {
            $doc = json_decode((string)@file_get_contents($f), true);
            if (!is_array($doc)) continue;
            $e = $doc['mainEntity'] ?? $doc;
            $zona = $eventi[basename(dirname($f))] ?? '';
            if ($zona === '') continue;
            foreach ((array)($e['organizer'] ?? []) as $org) {
                $id = ws_ref_id_semplice($org);
                if (strpos($id, 'organizations/') !== 0) continue;
                $conta[ws_mappa_slug($id)][$zona] = ($conta[ws_mappa_slug($id)][$zona] ?? 0) + 1;
            }
            // `organizer` può essere un oggetto solo: allora il foreach di sopra
            // scorre le sue CHIAVI, e non trova niente. Si riprova a leggerlo intero.
            $id = ws_ref_id_semplice($e['organizer'] ?? null);
            if (strpos($id, 'organizations/') === 0) {
                $conta[ws_mappa_slug($id)][$zona] = ($conta[ws_mappa_slug($id)][$zona] ?? 0) + 1;
            }
        }
        $out = [];
        foreach ($conta as $slug => $zone) {
            arsort($zone);
            $out[$slug] = (string)array_key_first($zone);
        }
        return $out;
    }

    /**
     * Le pagine che non nascono da una cartella.
     *
     * Due famiglie. I LIVELLI INTERMEDI dell'albero — Roma, Municipio X — che
     * esistono perché chi accorcia un indirizzo a mano deve trovarci qualcosa, e
     * perché domani ci cresceranno dentro altri quartieri. E i tre ELENCHI di ogni
     * zona attiva: eventi, gruppi, luoghi. Prima erano ancore dentro la pagina
     * della zona; sono pagine, perché sono la risposta a tre domande diverse.
     *
     * Il contenuto è quello della zona (o l'indice, per i livelli intermedi): il
     * template sa già che cosa mostrare, e glielo dice il parametro nella query.
     */
    function ws_mappa_generate(string $localeDir, array $zone): array {
        $voci = [];
        $elenchi = [
            'eventi' => ['Eventi', 'Le cose da fare nei prossimi giorni'],
            'gruppi' => ['Gruppi', 'Chi anima il territorio'],
            'luoghi' => ['Luoghi', 'Dove succedono le cose'],
        ];
        foreach ($zone as $slug => $z) {
            $attiva = is_file("$localeDir/places/$slug/index.json") && empty($z['contenitore']);
            if (!empty($z['contenitore'])) {
                // Contenitore CON un documento suo (una città): la pagina la fa la
                // mappa dalle entità, non serve generarla. Senza documento (un
                // municipio, che vive dentro l'albero della sua città) sì.
                if (is_file("$localeDir/places/$slug/index.json")) continue;
            }
            if (!$attiva) {
                // Livello senza un documento suo: la pagina la costruisce l'albero.
                // Il contenuto è l'albero della città in cima a questo ramo: è lì
                // che il nodo sta scritto.
                $radice = $slug;
                while (($zone[$radice]['padre'] ?? '') !== '') $radice = $zone[$radice]['padre'];
                $voci[] = [
                    'wspath' => '/' . $z['percorso'],
                    'rel' => is_file("$localeDir/places/$radice/index.json") ? "places/$radice" : 'index',
                    'template' => 'area',
                    'tipo' => $z['tipo'],
                    'title' => $z['nome'],
                    'description' => ws_mappa_descrizione($z['descrizione']),
                    'dateModified' => date('c'),
                    'extra' => ['zona' => $slug],   // quale nodo dell'albero mostrare
                ];
                continue;
            }
            $doc = json_decode((string)@file_get_contents("$localeDir/places/$slug/index.json"), true);
            $e = is_array($doc) ? ($doc['mainEntity'] ?? $doc) : [];
            foreach ($elenchi as $quale => $testi) {
                $voci[] = [
                    'wspath' => '/' . $z['percorso'] . '/' . $quale,
                    'rel' => "places/$slug",
                    'template' => 'elenco',
                    'tipo' => 'CollectionPage',
                    'title' => $testi[0] . ' — ' . $z['nome'],
                    'description' => $testi[1] . ' a ' . $z['nome'] . '.',
                    'dateModified' => (string)($doc['dateModified'] ?? date('c')),
                    'extra' => ['elenco' => $quale],
                ];
            }
        }
        return $voci;
    }

    /**
     * Costruisce la mappa. In anteprima non scrive: ritorna che cosa scriverebbe.
     * Ritorna ['changes'=>int, 'voci'=>[[wspath, rel, template]], 'problemi'=>[]].
     */
    function ws_mappa_costruisci(string $contentRoot, string $sito, string $locale = 'it_IT', bool $apply = false): array {
        $localeDir = rtrim($contentRoot, '/') . '/' . $locale;
        $voci = [];
        $problemi = [];
        $presi = [];

        /* La home del sito, se c'è: `index/index.json`. Oggi Meetoo non ce l'ha —
         * le sue pagine sono gli eventi e i luoghi — ma il giorno che nasce basta
         * il file, senza toccare né questo generatore né la configurazione. */
        if (is_file("$localeDir/index/index.json")) {
            $raw = (string)file_get_contents("$localeDir/index/index.json");
            $doc = json_decode($raw, true);
            $e = (is_array($doc) && isset($doc['mainEntity'])) ? $doc['mainEntity'] : (is_array($doc) ? $doc : []);
            $voci[] = [
                'wspath' => '/', 'rel' => 'index', 'template' => 'index', 'tipo' => 'WebSite',
                'title' => trim((string)($e['name'] ?? 'Meetoo')),
                'description' => ws_mappa_meta_descrizione($e),
                'dateModified' => (string)($doc['dateModified'] ?? date('c')),
            ];
            $presi['/'] = 'index';
        }

        /* Le tabelle che dicono DOVE sta ogni cosa. Si costruiscono una volta, prima
         * di cominciare: l'albero delle zone dal contenuto, i CAP che ognuna
         * rivendica, e la zona di ogni organizzatore — che non ce l'ha scritta da
         * nessuna parte e si deduce dagli eventi che organizza. È il senso di «chi
         * anima il territorio»: il territorio è quello dove fa le cose. */
        $zone = ws_mappa_zone($localeDir);
        $cap = ws_mappa_cap($localeDir, $zone);
        $eventi = ws_mappa_zone_eventi($localeDir, $cap, $zone);
        $ctx = [
            'zone' => $zone, 'cap' => $cap, 'eventi' => $eventi,
            'org' => ws_mappa_zone_organizzatori($localeDir, $eventi),
        ];

        // Le pagine che non hanno una cartella: i livelli intermedi dell'albero
        // (Roma, Municipio X) e i tre elenchi di ogni zona attiva.
        foreach (ws_mappa_generate($localeDir, $zone) as $g) {
            if (isset($presi[$g['wspath']])) { $problemi[] = "{$g['wspath']}: indirizzo già occupato"; continue; }
            $presi[$g['wspath']] = $g['rel'];
            $voci[] = $g;
        }

        foreach (ws_mappa_entita($localeDir) as $rel) {
            $raw = (string)@file_get_contents("$localeDir/$rel/index.json");
            $doc = json_decode($raw, true);
            if (!is_array($doc)) { $problemi[] = "$rel: index.json illeggibile"; continue; }
            $e = (isset($doc['mainEntity']) && is_array($doc['mainEntity'])) ? $doc['mainEntity'] : $doc;
            $tipi = array_map('strval', (array)($e['@type'] ?? []));

            $regola = ws_mappa_wspath($rel, $tipi, $ctx, $e);
            if ($regola === null) continue;
            list($wspath, $template, $tipo) = $regola;
            // Fuori da ogni zona: la pagina c'è lo stesso, con l'indirizzo piatto,
            // ma va detto — un contenuto senza territorio, qui, è un contenuto che
            // nessuno troverà navigando.
            if (strpos(ltrim($wspath, '/'), 'eventi/') === 0 || strpos(ltrim($wspath, '/'), 'luoghi/') === 0 || strpos(ltrim($wspath, '/'), 'gruppi/') === 0) {
                $problemi[] = "$rel: nessuna zona lo rivendica, resta su $wspath";
            }

            // Due entità che chiedono lo stesso indirizzo: la seconda si qualifica
            // con il segmento che le distingue (per i luoghi, il CAP). Meglio un
            // indirizzo più lungo che due pagine che si coprono a vicenda.
            if (isset($presi[$wspath])) {
                $pezzi = explode('/', $rel);
                $qualifica = count($pezzi) > 2 ? $pezzi[1] : substr(md5($rel), 0, 6);
                $wspath = preg_replace('#/([^/]+)$#', '/' . strtolower($qualifica) . '/$1', $wspath);
                if (isset($presi[$wspath])) { $problemi[] = "$rel: indirizzo già occupato ($wspath)"; continue; }
            }
            $presi[$wspath] = $rel;

            $voci[] = [
                'wspath' => $wspath,
                'rel' => $rel,
                'template' => $template,
                'tipo' => $tipo,
                'title' => trim((string)($e['name'] ?? ws_mappa_slug($rel))),
                'description' => ws_mappa_meta_descrizione($e),
                'dateModified' => (string)($doc['dateModified'] ?? $e['dateModified'] ?? date('c')),
            ];
        }

        $xml = ws_mappa_xml($voci, $sito, $locale);
        $file = rtrim($contentRoot, '/') . '/ws_sitemap.wsx';
        $scritto = false;
        if ($apply) {
            $scritto = @file_put_contents($file, $xml) !== false;
            if (!$scritto) $problemi[] = 'ws_sitemap.wsx: scrittura fallita';
        }
        return ['changes' => count($voci), 'voci' => $voci, 'problemi' => $problemi, 'file' => $file, 'scritto' => $scritto];
    }

    /** Le voci, nella forma che il CMS si aspetta. */
    function ws_mappa_xml(array $voci, string $sito, string $locale): string {
        $lang = str_replace('_', '-', $locale);
        $out = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $out .= "<!-- Generata da ws-admin (Rigenera la mappa del sito). Non si scrive a mano: si rigenera. -->\n";
        $out .= "<urlset xml:lang=\"$lang\" xmlns:xi=\"http://www.w3.org/2001/XInclude\">\n";
        foreach ($voci as $v) {
            $q = "/?theme=$sito&amp;template={$v['template']}&amp;content=$sito/$locale/{$v['rel']}";
            /* I parametri in piu' viaggiano nella query, come tutto il resto: il CMS
             * li mette in $ws_query e il template li legge da li'. E' cosi' che la
             * stessa pagina di zona serve tre elenchi diversi senza tre contenuti. */
            foreach ((array)($v['extra'] ?? []) as $k => $val) {
                $q .= '&amp;' . rawurlencode((string)$k) . '=' . rawurlencode((string)$val);
            }
            $out .= "\t<url>\n";
            $out .= "\t\t<wspath>{$v['wspath']}</wspath>\n";
            $out .= "\t\t<query>$q</query>\n";
            $out .= "\t\t<inLanguage>$lang</inLanguage>\n";
            $out .= "\t\t<type>{$v['tipo']}</type>\n";
            $out .= "\t\t<title>" . ws_mappa_esc($v['title']) . "</title>\n";
            $out .= "\t\t<description>" . ws_mappa_esc($v['description']) . "</description>\n";
            $out .= "\t\t<changefreq>weekly</changefreq>\n";
            $out .= "\t\t<priority>0.6</priority>\n";
            $out .= "\t\t<robots>index, follow</robots>\n";
            $out .= "\t\t<dateModified>" . ws_mappa_esc($v['dateModified']) . "</dateModified>\n";
            $out .= "\t</url>\n";
        }
        $out .= "</urlset>\n";
        return $out;
    }

    function ws_mappa_esc(string $s): string {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Il sitemap del sito dev'essere incluso in quello generale, altrimenti esiste
     * ma non lo cerca nessuno. Ritorna true se c'era già o se è stato aggiunto.
     */
    function ws_mappa_innesta(string $contentsDir, string $sito, bool $apply): array {
        $file = rtrim($contentsDir, '/') . '/ws_sitemap.wsx';
        if (!is_file($file)) return ['ok' => false, 'why' => 'ws_sitemap.wsx generale non trovato'];
        $raw = (string)file_get_contents($file);
        $riga = "  <xi:include href=\"$sito/ws_sitemap.wsx\" xpointer=\"xpointer(/*[1]/*)\"/>";
        if (strpos($raw, "$sito/ws_sitemap.wsx") !== false) return ['ok' => true, 'why' => 'già incluso'];
        if (!$apply) return ['ok' => true, 'why' => 'da includere'];
        $nuovo = preg_replace('#(\n\s*</urlset>)#', "\n$riga$1", $raw, 1);
        return @file_put_contents($file, $nuovo) !== false
            ? ['ok' => true, 'why' => 'incluso']
            : ['ok' => false, 'why' => 'scrittura fallita'];
    }
}

if (!function_exists('ws_mappa_sitemap_pubblico')) {
    /**
     * Il `sitemap.xml` che leggono i motori di ricerca.
     *
     * Nasce dalla mappa generale (quella che il CMS usa per instradare), ma con due
     * differenze che contano:
     *
     *  1. gli indirizzi diventano ASSOLUTI, perché un sitemap con percorsi relativi
     *     non serve a niente;
     *  2. le pagine di un sito INNESTATO prendono il loro prefisso. I contenuti
     *     scrivono `/eventi/…` perché un giorno vivranno su un dominio proprio, ma
     *     oggi stanno sotto `/meetoo/`, e un motore che seguisse l'indirizzo nudo
     *     troverebbe la pagina sbagliata. Il prefisso lo sa solo chi ospita, quindi
     *     lo aggiunge questo passaggio e non il contenuto.
     *
     * `noindex` esce dall'elenco: è l'unica cosa che il file dichiara e va rispettata.
     */
    function ws_mappa_sitemap_pubblico(string $contentsDir, array $mounts, bool $apply): array {
        $mappa = rtrim($contentsDir, '/') . '/ws_sitemap.wsx';
        if (!is_file($mappa)) return ['ok' => false, 'why' => 'ws_sitemap.wsx generale non trovato', 'urls' => 0];

        $prima = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->load($mappa);
        $dom->xinclude();
        libxml_clear_errors();
        libxml_use_internal_errors($prima);

        $x = new DOMXPath($dom);
        $radice = rtrim((string)($x->evaluate('string(/*/ws_root_url)') ?: ''), '/');
        if ($radice === '') return ['ok' => false, 'why' => 'ws_root_url non dichiarato nella mappa', 'urls' => 0];

        // sito → prefisso, letto al contrario rispetto a WS_MOUNTS.
        $prefissoDi = [];
        foreach ($mounts as $prefisso => $sito) $prefissoDi[$sito] = '/' . trim((string)$prefisso, '/');

        $out = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        $visti = [];
        $n = 0;
        foreach ($x->query('/*/url') as $url) {
            $wspath = trim($x->evaluate('string(wspath)', $url));
            $robots = $x->evaluate('string(robots)', $url);
            if ($wspath === '' || strpos($robots, 'noindex') !== false) continue;

            // A quale sito appartiene: lo dice il `content=` della query.
            $query = $x->evaluate('string(query)', $url);
            $prefisso = '';
            if (preg_match('/content=([^&\s]+)/', $query, $m)) {
                $sito = explode('/', $m[1])[0];
                $prefisso = $prefissoDi[$sito] ?? '';
            }
            $nudo = ltrim($wspath, '/');
            $loc = $radice . rtrim($prefisso, '/') . '/' . $nudo;
            // La home tiene la barra finale: `https://sito/` e `https://sito` sono
            // due indirizzi per un motore, e quello canonico è il primo. Le altre
            // pagine la perdono, per non averne due versioni.
            if ($nudo !== '') $loc = rtrim($loc, '/');
            if (isset($visti[$loc])) continue;   // la mappa generale ha dei doppioni
            $visti[$loc] = true;

            $out .= "\t<url>\n\t\t<loc>" . htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n";
            foreach (['changefreq' => 'changefreq', 'priority' => 'priority', 'dateModified' => 'lastmod'] as $da => $a) {
                $v = trim($x->evaluate("string($da)", $url));
                if ($v !== '') $out .= "\t\t<$a>" . htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</$a>\n";
            }
            $out .= "\t</url>\n";
            $n++;
        }
        $out .= "</urlset>\n";

        $file = rtrim($contentsDir, '/') . '/sitemap.xml';
        if (!$apply) return ['ok' => true, 'why' => "$n indirizzi", 'urls' => $n];
        return @file_put_contents($file, $out) !== false
            ? ['ok' => true, 'why' => "$n indirizzi scritti in sitemap.xml", 'urls' => $n]
            : ['ok' => false, 'why' => 'sitemap.xml: scrittura fallita', 'urls' => $n];
    }
}
