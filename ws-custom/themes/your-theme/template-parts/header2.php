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

$qui = isset($rewrite_rule->wspath) ? trim((string) $rewrite_rule->wspath, '/') : '';
if ($qui === '') {
    return;
}

/* Un pezzo di indirizzo entra in una ricerca XPath: si ammettono solo i
 * caratteri di cui un indirizzo è fatto. Non è paranoia teorica — `wspath`
 * arriva dalla richiesta, e una virgoletta in mezzo a una xpath la riscrive. */
$pezzi = array_values(array_filter(explode('/', $qui), function ($p) {
    return $p !== '' && preg_match('#^[A-Za-z0-9._~-]+$#', $p);
}));
if (!$pezzi) {
    return;
}

// Come si chiama la pagina a questo indirizzo, secondo la mappa.
$nome_di = function ($percorso) use ($ws_sitemap) {
    if (!$ws_sitemap) { return ''; }
    $voci = $ws_sitemap->xpath('url[wspath="' . $percorso . '"]');
    if (!$voci) { return ''; }
    $v = $voci[0];
    // `name` è il nome breve, fatto per i menu; `title` è quello lungo, per la
    // finestra del browser. In una riga stretta si vuole il breve.
    $n = trim((string) $v->name);
    return $n !== '' ? $n : trim((string) $v->title);
};

$scia = array();
$corso = '';
foreach ($pezzi as $p) {
    $corso .= '/' . $p;
    $scia[] = array('path' => $corso, 'nome' => $nome_di($corso), 'pezzo' => $p);
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
