<?php
/**
 * The pages under this one, as cards.
 *
 * Read from the site map, not from a list of their own: a card is here because
 * the page exists, and it goes away when the page does. Nothing to keep in step.
 *
 * In schema.org this is the page's `mainEntity`: an ItemList whose ListItems
 * hold the pages themselves, each typed as its content declares (a Service, a
 * Product, an Article…). Microdata, like the rest of the theme. The whole card
 * is the link, so it reads as one thing and is one thing to click.
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
?>
				<nav class="section-children" itemprop="mainEntity" itemscope itemtype="https://schema.org/ItemList"<?php if($section_name){ ?> aria-label="<?php echo htmlspecialchars($section_name); ?>"<?php } ?>>
					<ol class="section-cards">
<?php
$position = 0;
foreach($children as $child){
	$position++;
	// The type is what the page declares for itself; a page without one is a page.
	$type = trim((string)$child->type);
	if($type === ''){ $type = 'WebPage'; }
	$name = !empty($child->name) ? $child->name->innerHTML() : (string)$child->title;
	$description = !empty($child->description) ? trim($child->description->innerHTML()) : '';
?>
						<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
							<meta itemprop="position" content="<?php echo $position; ?>">
							<div itemprop="item" itemscope itemtype="https://schema.org/<?php echo htmlspecialchars($type); ?>">
								<a itemprop="url" href="<?php echo ws_href($child->wspath); ?>">
									<h3 itemprop="name"><?php echo $name; ?></h3>
<?php if($description !== ''){ ?>
									<p itemprop="description"><?php echo $description; ?></p>
<?php } ?>
								</a>
							</div>
						</li>
<?php
}
?>
					</ol>
				</nav>
