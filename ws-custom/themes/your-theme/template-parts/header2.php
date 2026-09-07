<?php
/**
 * La terza riga dell'header: DOVE SEI.
 *
 * Le briciole non si scrivono a mano da nessuna parte: si ricavano
 * dall'indirizzo della pagina e dalla mappa del sito, che è l'unico posto dove
 * i nomi delle pagine stanno già scritti. `/servizi/brand-design` diventa
 * «casa › Servizi › Brand Design» chiedendo alla mappa come si chiama
 * `/servizi` e come si chiama `/servizi/brand-design`.
 *
 * Perché non un campo nel contenuto: un titolo scritto due volte prima o poi è
 * scritto in due modi. Qui il nome della tappa è LO STESSO che la pagina usa di
 * sé, perché è preso da lì.
 *
 * Una tappa che nella mappa non c'è non si inventa: si stampa il pezzo
 * dell'indirizzo così com'è, senza collegamento. Meglio una parola brutta di un
 * collegamento che porta in un posto che non esiste.
 *
 * In homepage non si stampa niente: «sei a casa» lo dice già il fatto di essere
 * a casa, e una riga vuota è una riga di schermo in meno per il contenuto.
 *
 * @package WS
 * @subpackage Your Theme
 */
global $ws_sitemap, $rewrite_rule;

$qui = isset($rewrite_rule->wspath) ? (string) $rewrite_rule->wspath : '';

/* Un indirizzo entra in una ricerca XPath: si ammettono solo i caratteri di cui
 * un indirizzo è fatto. Non è paranoia teorica — `wspath` arriva dalla
 * richiesta, e una virgoletta in mezzo a una xpath la riscrive. */
$pulito = function ($percorso) {
    $p = '/' . trim((string) $percorso, '/');
    return preg_match('#^/(?:[A-Za-z0-9._~-]+(?:/[A-Za-z0-9._~-]+)*)?$#', $p) ? $p : null;
};

// La voce della mappa per un indirizzo: è lo stesso nodo che il CMS chiama
// `$rewrite_rule` quando quella pagina è la pagina richiesta.
$voce_di = function ($percorso) use ($ws_sitemap) {
    if (!$ws_sitemap || $percorso === null) { return null; }
    $voci = $ws_sitemap->xpath('url[wspath="' . $percorso . '"]');
    return $voci ? $voci[0] : null;
};

// Come si chiama: `name` è il nome breve, fatto per i menu; `title` è quello
// lungo, per la finestra del browser. In una riga stretta si vuole il breve.
$nome_di = function ($voce) {
    if (!$voce) { return ''; }
    $n = trim((string) $voce->name);
    return $n !== '' ? $n : trim((string) $voce->title);
};

/* CHI È IL GENITORE lo dice la PAGINA, non l'indirizzo.
 *
 * Il CMS ha già la sua relazione: ogni pagina dichiara `<parent><wspath>`, e la
 * mappa può portarsela dentro — è la stessa che fa comparire il pulsante «pagina
 * genitore» in `nav-contents`. Dove c'è, comanda quella: un sito può benissimo
 * tenere una pagina a `/eventi` e dichiararla figlia di `/servizi`, e in quel
 * caso l'indirizzo direbbe una cosa e la struttura un'altra.
 *
 * Dove NON c'è — oggi su isotype non c'è in nessuna voce: le pagine lo
 * dichiarano nel loro index.xml, ma la mappa non lo riprende — si ripiega sul
 * pezzo precedente dell'indirizzo. È un'ipotesi, non un dato, e vale finché
 * l'indirizzo rispecchia la gerarchia. Il giorno che le voci della mappa
 * porteranno il `parent`, queste briciole cambiano da sole senza toccare una
 * riga: cominciano a leggere quello. */
$genitore_di = function ($percorso, $voce) use ($pulito) {
    if ($voce !== null && isset($voce->parent->wspath)) {
        $dichiarato = trim((string) $voce->parent->wspath);
        // `<wspath></wspath>` vuoto vuol dire la casa: è come lo scrive il CMS.
        return $dichiarato === '' ? '/' : $pulito($dichiarato);
    }
    $t = rtrim($percorso, '/');
    $taglio = strrpos($t, '/');
    return $taglio === false ? null : ($taglio === 0 ? '/' : substr($t, 0, $taglio));
};

/* Si risale, non si scompone: dal figlio al padre, finché si arriva a casa.
 * Il conto dei passi non è diffidenza verso il CMS: `parent` è un dato scritto a
 * mano nei contenuti, e due pagine che si dichiarano figlie a vicenda sono un
 * refuso possibile — che qui diventerebbe una pagina che non finisce di
 * caricarsi. */
$qui = $pulito($qui);
if ($qui === null || $qui === '/') {
    return;
}
$scia = array();
$visti = array();
$corrente = $qui;
for ($passi = 0; $passi < 12 && $corrente !== null && $corrente !== '/'; $passi++) {
    if (isset($visti[$corrente])) { break; }
    $visti[$corrente] = true;
    $voce = $voce_di($corrente);
    $pezzo = substr($corrente, strrpos($corrente, '/') + 1);
    array_unshift($scia, array('path' => $corrente, 'nome' => $nome_di($voce), 'pezzo' => $pezzo));
    $corrente = $genitore_di($corrente, $voce);
}
if (!$scia) {
    return;
}
$ultimo = count($scia) - 1;
?>
<nav <?php echo ws_html_attributes('header2', array('id' => 'header2', 'class' => array('crumbs'), 'aria-label' => __('Where you are'))); ?>>
	<div class="content-container crumbs-strip">
		<a class="crumb crumb-casa" href="<?php echo ws_href(''); ?>" title="<?php _e('Go to homepage'); ?>">
			<i class="material-symbols-outlined">home</i>
		</a>
<?php foreach ($scia as $i => $t): ?>
		<span class="crumb-sep" aria-hidden="true">›</span>
<?php if ($i === $ultimo): ?>
		<span class="crumb crumb-qui" aria-current="page"><?php echo htmlspecialchars($t['nome'] !== '' ? $t['nome'] : $t['pezzo']); ?></span>
<?php elseif ($t['nome'] !== ''): ?>
		<a class="crumb" href="<?php echo ws_href($t['path']); ?>"><?php echo htmlspecialchars($t['nome']); ?></a>
<?php else: ?>
		<span class="crumb"><?php echo htmlspecialchars($t['pezzo']); ?></span>
<?php endif; ?>
<?php endforeach; ?>
	</div>
</nav>
