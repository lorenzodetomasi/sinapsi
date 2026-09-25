<?php
/**
 * A route: stops along a line, seen as a line.
 *
 * The lungomare is the first of these - sixty-one beaches, gates and places in
 * the order you meet them walking along the sea - and it is not a list. A list
 * says what there is; a route says what comes after what, and how far. So it
 * has a page of its own, not a branch inside `collection.php`.
 *
 * WHAT MAKES A COLLECTION A ROUTE is declared by the content, not guessed:
 * `additionalType: "TouristTrip"` on its main entity - schema.org's own type
 * for "an itinerary of visits to places of interest". The site map reads it and
 * sends the page here. It used to be inferred from the data (stops carrying a
 * distance from the start), which worked, but it was a decision nobody could
 * see without reading this theme.
 *
 * ONLY THE LINE. No list of the stops underneath: it is the same thing said
 * twice. The stops are still in the page for whoever reads it without a screen -
 * the JSON-LD in the head carries the whole ItemList, which is what a search
 * engine reads - so nothing is lost there; what is gone is only the second
 * visible copy.
 *
 * @package WS
 * @subpackage Meetoo
 */
global $ws_content, $ws_query, $rewrite_rule;

include_template('template-parts/carte');

$e = !empty($ws_content->mainEntity) ? $ws_content->mainEntity : $ws_content;
$titolo = !empty($e->name) ? (string)$e->name : (string)($rewrite_rule->title ?? '');

/* The line's own style and behaviour: five hundred lines of CSS and a thousand
 * of script, loaded only here. */
$GLOBALS['ws_links'][] = '<link rel="stylesheet" type="text/css" media="all" href="'.ws_asset('css/lungomare.css').'" />';
$GLOBALS['ws_scripts']['bodyend']['meetoo_lungomare'] = '<script defer="defer" src="'.ws_asset('js/lungomare.js').'"></script>';

/* Which collection to draw: its @id, which is also the folder of its data. In
 * the GLOBALS because templates are included inside a CMS function, and from
 * there the variables of this file are not visible. */
$GLOBALS['raccolta'] = preg_replace('#^[^/]+/[^/]+/#', '', trim((string)($ws_query['content'] ?? ''), '/'));

include_template('template-parts/header');
?>
			<article<?php echo ws_html_attributes('main-content', array('class' => array('mt-pagina', 'mt-raccolta-pagina'))); ?>>
<?php
/* The cover: its path in the document is relative to ITS folder, and this page
 * serves it from another address - `meetoo_media()` does the arithmetic. */
$cover = meetoo_media(meetoo_rel_corrente(), (string)($e->image ?? ''));
if($cover !== ''){ ?>
				<figure class="mt-copertina">
					<img src="<?php echo mt_esc($cover); ?>" alt="" loading="lazy" decoding="async" />
				</figure>
<?php } ?>
				<h1 class="mt-h1"><?php echo mt_esc($titolo); ?></h1>
<?php $testo = meetoo_testo_visibile($e); if($testo !== ''){ ?>
				<div class="mt-corpo"><?php ws_echo($testo); ?></div>
<?php } ?>
			</article>
<?php
include_template('template-parts/percorso');
include_template('template-parts/footer');
