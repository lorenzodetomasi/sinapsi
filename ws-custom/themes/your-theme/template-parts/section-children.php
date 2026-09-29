<?php
/**
 * The pages under this one, as cells of the same grid the logos use.
 *
 * Read from the site map, not from a list of their own: a cell is here because
 * the page exists, and it goes away when the page does. Nothing to keep in step.
 *
 * The shared cards (template-parts/cards.php): a row of text cards - the
 * name and a short description - four across on a wide screen, fewer as the
 * list narrows. The whole card is the link.
 *
 * What these pages are to this one - its `hasPart` - is declared in the head,
 * as JSON-LD, from the same map (functions.php); the grid only shows them.
 *
 * @package WS
 * @subpackage Your Theme
 */
global $ws_content, $rewrite_rule;

$here = !empty($ws_content->wspath) ? (string)$ws_content->wspath : (string)($rewrite_rule->wspath ?? '');
// Drafts too: on the site they are pages like the others, only marked (see ws_is_draft()).
$children = ws_sitemap_children($here, true);
if(empty($children)){
	return;
}
$section_name = !empty($ws_content->name) ? trim(strip_tags($ws_content->name->innerHTML())) : '';

/* Two shapes for the same list (29 Sep 2026: the shared cards, css/cards.css).
 *   'text'   - the default - a row of cards, each as tall as its text.
 *   'square' - square cards; a text that does not fit scrolls inside.
 * The template that includes this part chooses by setting
 * $section_children_format before the include; one that says nothing gets
 * 'text'. */
global $section_children_format;
$grid_format = ($section_children_format === 'square') ? 'square' : 'text';
include_template('template-parts/cards');
?>
				<nav class="section-children"<?php if($section_name){ ?> aria-label="<?php echo htmlspecialchars($section_name); ?>"<?php } ?>>
					<ul class="cards cards-row cols-4<?php echo $grid_format === 'square' ? ' ratio-1x1 cards-square' : ''; ?>">
<?php
foreach($children as $child){
	$name = !empty($child->name) ? $child->name->innerHTML() : (string)$child->title;
	$description = !empty($child->description) ? trim($child->description->innerHTML()) : '';
	echo ws_card(array(
		'tag' => 'li',
		'href' => ws_href($child->wspath),
		'title' => $name.(ws_is_draft($child) ? ' <span class="status-badge">'.__('Draft').'</span>' : ''),
		'text' => $description,
	))."\n";
}
?>
					</ul>
				</nav>
