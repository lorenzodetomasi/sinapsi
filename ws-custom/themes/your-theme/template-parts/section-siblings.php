<?php
/**
 * The pages that stand next to this one — its sisters under the same parent.
 *
 * The essential version of the section's cards, for the pages inside the
 * section: names only, the current one marked and not linked, under the
 * parent's name. It says "you are here, and these are the others" — which is
 * the one thing a reader deep in a section wants to know without going back up.
 *
 * Same source as the cards: the site map. Nothing to keep in step.
 *
 * @package WS
 * @subpackage Your Theme
 */
global $ws_content, $rewrite_rule;

$here = !empty($ws_content->wspath) ? (string)$ws_content->wspath : (string)($rewrite_rule->wspath ?? '');
$parent_path = !empty($ws_content->parent->wspath) ? (string)$ws_content->parent->wspath : '';
if($parent_path === ''){
	$entry = ws_sitemap_entry($here);
	$parent_path = ($entry and !empty($entry->parent->wspath)) ? (string)$entry->parent->wspath : '';
}
if($parent_path === ''){
	return;
}
$siblings = ws_sitemap_children($parent_path);
if(count($siblings) < 2){
	return;   // alone under its parent: nothing to choose from
}
$parent = ws_sitemap_entry($parent_path);
$parent_name = ($parent and !empty($parent->name)) ? $parent->name->innerHTML() : '';
$here_norm = ws_sitemap_normalize_path($here);
?>
				<aside class="section-siblings" aria-labelledby="section-siblings-title">
<?php if($parent_name !== ''){ ?>
					<h2 id="section-siblings-title" class="section-siblings-title"><a href="<?php echo ws_href(ws_sitemap_normalize_path($parent_path)); ?>"><?php echo $parent_name; ?></a></h2>
<?php } ?>
					<ul>
<?php
foreach($siblings as $sibling){
	$name = !empty($sibling->name) ? $sibling->name->innerHTML() : (string)$sibling->title;
	if(ws_sitemap_normalize_path($sibling->wspath) === $here_norm){
?>
						<li aria-current="page"><?php echo $name; ?></li>
<?php
	} else {
?>
						<li><a href="<?php echo ws_href($sibling->wspath); ?>"><?php echo $name; ?></a></li>
<?php
	}
}
?>
					</ul>
				</aside>
