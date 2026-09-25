<?php
/**
 * Condividere questa pagina.
 *
 * Non serve nessun dato che il sito non abbia già: l'indirizzo e il titolo
 * bastano, e sono quelli della pagina che si sta guardando. È la ragione per
 * cui questa finestra si può scrivere e le altre due della stessa fila —
 * «Seguici», «Cerca» — no: quelle chiedono cose che non esistono ancora.
 *
 * NIENTE PULSANTI DEI SOCIAL con il loro codice dentro. Un bottone «condividi
 * su Facebook» disegnato da Facebook porta con sé il suo tracciamento, e lo
 * porta a chi la pagina la sta solo LEGGENDO, che non ha chiesto niente. Qui
 * sono collegamenti normali: portano dove dicono, e finché non si toccano non
 * succede niente.
 *
 * Su un telefono c'è di meglio: `navigator.share` apre il foglio di sistema,
 * con dentro tutto quello che la persona ha davvero installato. Se c'è, la
 * prima voce diventa quello; se non c'è, resta la fila qui sotto.
 *
 * @package WS
 * @subpackage Your Theme
 */
global $rewrite_rule, $ws_headings;

/* Se il sito non ha chiesto «Condividi», questa finestra non si stampa.
 *
 * Il guardiano sta QUI e non in chi include: due footer la includono - quello
 * del tema e quello di Meetoo - e una regola che ogni chiamante deve ricordarsi
 * e' una regola che prima o poi uno dei due dimentica. Senza, su Meetoo restava
 * una finestra nel documento che nessun comando apriva: l'inverso esatto del
 * difetto che questa riga di lavoro e' andata a togliere. */
if(empty($ws_headings) or (string)$ws_headings->share !== 'true'){
	return;
}

$titolo = trim((string)$rewrite_rule->title);
if($titolo === '' and !empty($ws_headings->mainEntity->name)){
	$titolo = trim((string)$ws_headings->mainEntity->name);
}

/* L'indirizzo CANONICO, non quello con cui si è arrivati: un link condiviso
 * con `?utm_source=` appiccicato dietro se lo porta in giro per sempre. */
$wspath = isset($rewrite_rule->wspath) ? ltrim((string)$rewrite_rule->wspath, '/') : '';
$url = ws_href($wspath);

$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$u = rawurlencode($url);
$t = rawurlencode($titolo);
?>
<aside id="share" class="modal" hidden>
	<div>
		<header>
			<h3><?php _e('Share'); ?></h3>
			<nav>
				<ul>
					<li><a class="close link h48" href="#" data-close="#share"><i class="material-symbols-outlined">close</i><span class="button-text"><?php _e('Close'); ?></span></a></li>
				</ul>
			</nav>
		</header>
		<section id="share-what">
			<p class="share-title"><?php echo $e($titolo); ?></p>
			<p class="share-url"><?php echo $e($url); ?></p>
		</section>
		<nav id="share-where" data-url="<?php echo $e($url); ?>" data-title="<?php echo $e($titolo); ?>">
			<ul class="pills">
				<li hidden id="share-native"><button type="button" class="pill"><span class="material-symbols-outlined" aria-hidden="true">ios_share</span><span class="share-label"><?php _e('Share'); ?></span></button></li>
				<li><button type="button" class="pill" id="share-copy" data-done="<?php _e('Copied'); ?>"><span class="material-symbols-outlined" aria-hidden="true">link</span><span class="share-label"><?php _e('Copy link'); ?></span></button></li>
				<li><a class="pill" href="mailto:?subject=<?php echo $t; ?>&amp;body=<?php echo $u; ?>"><span class="material-symbols-outlined" aria-hidden="true">mail</span><?php _e('Email'); ?></a></li>
				<li><a class="pill" href="https://wa.me/?text=<?php echo $t; ?>%20<?php echo $u; ?>" target="_blank" rel="noopener noreferrer"><span class="material-symbols-outlined" aria-hidden="true">chat</span>WhatsApp</a></li>
				<li><a class="pill" href="https://t.me/share/url?url=<?php echo $u; ?>&amp;text=<?php echo $t; ?>" target="_blank" rel="noopener noreferrer"><span class="material-symbols-outlined" aria-hidden="true">send</span>Telegram</a></li>
			</ul>
		</nav>
	</div>
</aside>
