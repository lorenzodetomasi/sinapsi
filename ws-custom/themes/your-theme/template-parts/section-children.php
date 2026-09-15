<?php
/**
 * The pages under this one, as cells of the same grid the logos use.
 *
 * Read from the site map, not from a list of their own: a cell is here because
 * the page exists, and it goes away when the page does. Nothing to keep in step.
 *
 * Same markup as grid-1_1-image_gallery.php — ul.grid-container, li.grid-cell —
 * so a section of pages and a section of logos are one thing to the eye:
 * square cells, two across, four from a thousand pixels. The cell holds the
 * name and a short description instead of an image, and the whole cell is the
 * link.
 *
 * What these pages are to this one - its `hasPart` - is declared in the head,
 * as JSON-LD, from the same map (functions.php); the grid only shows them.
 *
 * @package WS
 * @subpackage Your Theme
 */
global $ws_content, $rewrite_rule;

$here = !empty($ws_content->wspath) ? (string)$ws_content->wspath : (string)($rewrite_rule->wspath ?? '');
$children = ws_sitemap_children($here);
if(empty($children)){
	return;
}
$section_name = !empty($ws_content->name) ? trim(strip_tags($ws_content->name->innerHTML())) : '';

/* Two shapes for the same grid.
 *   'text'   - the default - makes each cell as tall as its text.
 *   'square' - keeps the logos' squares; a text that does not fit its square
 *              scrolls inside the cell instead of being cut.
 * In both the names sit at the top of their cells, on one line across the
 * row. The template that includes this part chooses by setting
 * $section_children_format before the include; one that says nothing gets
 * 'text'. */
global $section_children_format;
$grid_format = ($section_children_format === 'square') ? 'square' : 'text';
$grid_classes = 'grid-container' . ($grid_format === 'text' ? ' grid-text' : '');
?>
				<nav class="section-children"<?php if($section_name){ ?> aria-label="<?php echo htmlspecialchars($section_name); ?>"<?php } ?>>
					<ul class="<?php echo $grid_classes; ?>">
<?php
foreach($children as $child){
	$name = !empty($child->name) ? $child->name->innerHTML() : (string)$child->title;
	$description = !empty($child->description) ? trim($child->description->innerHTML()) : '';
?>
						<li class="grid-cell">
							<div class="grid-item">
								<a class="grid-link" href="<?php echo ws_href($child->wspath); ?>">
									<h3><?php echo $name; ?></h3>
<?php if($description !== ''){ ?>
									<p><?php echo $description; ?></p>
<?php } ?>
								</a>
							</div>
						</li>
<?php
}
?>
					</ul>
				</nav>
