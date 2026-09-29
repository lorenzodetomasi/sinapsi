<?php
/**
 * CARDS - the markup of one card, for every site (css/cards.css draws it).
 *
 * The twin of js/cards.js: same classes, same order of the parts, so a card
 * written by the server and one written in the browser are the same card.
 * A site builds its own cards on top (Meetoo's events, places, groups) and
 * calls ws_card() for the skeleton.
 *
 *   ws_card(array(
 *     'href'      => '…',          where the title leads; '' = not a link
 *     'external'  => false,        opens in a new tab, with its own arrow
 *     'tag'       => 'div',        li inside a list, article, div
 *     'className' => '',           more classes on the card
 *     'attrs'     => array(),      more attributes on the card
 *     'media'     => array('src' => …, 'alt' => …, 'src2' => …),
 *                                  an image; src2 takes its place on hover
 *     'head'      => '',           HTML of the lead block (a date, an icon)
 *     'title'     => '',           HTML, escaped by the caller (it may carry a
 *                                  badge)
 *     'meta'      => array(),      HTML of the short facts, one per item
 *     'text'      => '',           HTML of a description
 *     'tools'     => array(        icon buttons stacked at the far right
 *        'share'    => url | true,     true: the card's own href
 *        'interest' => array('kind' => …, 'id' => …, 'on' => false),
 *        'edit'     => url,            only for whoever may edit it
 *     ),
 *     'actions'   => array(array('href', 'icon', 'label', 'title', 'primary',
 *                                'external')),   labelled buttons (the admin)
 *     'arrow'     => true,         the arrow, when there are no tools
 *   ));
 *
 * @package WS
 * @subpackage Your Theme
 */
if(!function_exists('ws_card')){

function ws_card_esc($s){
	return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function ws_card_icon($name, $class = 'material-symbols-outlined'){
	return '<span class="'.ws_card_esc($class).'" aria-hidden="true">'.ws_card_esc($name).'</span>';
}

/** A short fact: an icon and a text, both escaped here. */
function ws_card_meta($icon, $text){
	$text = trim((string)$text);
	if($text === ''){
		return '';
	}
	return '<span>'.($icon !== '' ? ws_card_icon($icon) : '').ws_card_esc($text).'</span>';
}

/**
 * Share, interest, edit: stacked at the far right of the card.
 *
 * What they do is js/cards.js's: share opens the system sheet or copies the
 * link; interest asks the site (the event 'ws:card-interest'), because what it
 * means - a bookmark in the browser, an interest recorded on the server -
 * depends on the site and on the thing. Edit is a plain link, and it is here
 * only when the server said that whoever is looking may edit.
 */
function ws_card_tools($t, $href = ''){
	$t = is_array($t) ? $t : array();
	$share = $t['share'] ?? null;
	$interest = $t['interest'] ?? null;
	$edit = trim((string)($t['edit'] ?? ''));
	if(!$share and !$interest and $edit === ''){
		return '';
	}
	$url = ($share === true or $share === null) ? (string)$href : (string)$share;
	$kind = is_array($interest) ? (string)($interest['kind'] ?? '') : '';
	$id = is_array($interest) ? (string)($interest['id'] ?? '') : '';
	$out = '<div class="card-tools"'
		.($kind !== '' ? ' data-kind="'.ws_card_esc($kind).'"' : '')
		.($id !== '' ? ' data-id="'.ws_card_esc($id).'"' : '')
		.($url !== '' ? ' data-url="'.ws_card_esc($url).'"' : '').'>';
	if($share){
		$out .= '<button type="button" class="card-tool" data-tool="share" title="'.ws_card_esc(__('Share')).'" aria-label="'.ws_card_esc(__('Share')).'">'.ws_card_icon('share').'</button>';
	}
	if($interest){
		$on = !empty($interest['on']);
		$out .= '<button type="button" class="card-tool'.($on ? ' on' : '').'" data-tool="interest" aria-pressed="'.($on ? 'true' : 'false').'" title="'.ws_card_esc(__('I am interested')).'" aria-label="'.ws_card_esc(__('I am interested')).'">'.ws_card_icon('bookmark').'</button>';
	}
	if($edit !== ''){
		$out .= '<a class="card-tool" data-tool="edit" href="'.ws_card_esc($edit).'" title="'.ws_card_esc(__('Edit')).'" aria-label="'.ws_card_esc(__('Edit')).'">'.ws_card_icon('edit').'</a>';
	}
	return $out.'</div>';
}

/** Labelled buttons: the admin's view, edit, copy, bin. */
function ws_card_actions($actions){
	if(empty($actions)){
		return '';
	}
	$out = '<div class="card-actions">';
	foreach($actions as $a){
		$out .= '<a class="card-act'.(!empty($a['primary']) ? ' primary' : '').'" href="'.ws_card_esc($a['href'] ?? '').'"'
			.(!empty($a['external']) ? ' target="_blank" rel="noopener"' : '')
			.(!empty($a['title']) ? ' title="'.ws_card_esc($a['title']).'"' : '').'>'
			.(!empty($a['icon']) ? ws_card_icon($a['icon']) : '')
			.'<span>'.ws_card_esc($a['label'] ?? '').'</span></a>';
	}
	return $out.'</div>';
}

function ws_card($c){
	$href = trim((string)($c['href'] ?? ''));
	$external = !empty($c['external']);
	$tag = preg_match('/^(li|div|article)$/', (string)($c['tag'] ?? '')) ? $c['tag'] : 'div';
	$class = 'card'.(!empty($c['className']) ? ' '.$c['className'] : '');
	$attrs = '';
	foreach((array)($c['attrs'] ?? array()) as $k => $v){
		$attrs .= ' '.preg_replace('/[^a-z0-9_:-]/i', '', (string)$k).'="'.ws_card_esc($v).'"';
	}

	$media = '';
	if(!empty($c['media']['src'])){
		$m = $c['media'];
		$media = '<div class="card-media"><img src="'.ws_card_esc($m['src']).'" alt="'.ws_card_esc($m['alt'] ?? '').'" loading="lazy" decoding="async" />'
			.(!empty($m['src2']) ? '<img class="card-media-alt" src="'.ws_card_esc($m['src2']).'" alt="" loading="lazy" decoding="async" />' : '')
			.'</div>';
	}

	/* Without an address the card is not a link, it is a sheet: a thing that
	 * has no page of its own (a stop, a gate) is still worth showing, but not
	 * as something to click. */
	$title = (string)($c['title'] ?? '');
	if($href !== '' and $title !== ''){
		$title = '<a class="card-link" href="'.ws_card_esc($href).'"'.($external ? ' target="_blank" rel="noopener"' : '').'>'.$title.'</a>';
	}
	$meta = array_filter(array_map('strval', (array)($c['meta'] ?? array())), function($m){ return trim($m) !== ''; });
	$text = trim((string)($c['text'] ?? ''));
	$body = ($title !== '' or $meta or $text !== '')
		? '<div class="card-body">'
			.($title !== '' ? '<h3 class="card-title">'.$title.'</h3>' : '')
			.($meta ? '<div class="card-meta">'.implode('', $meta).'</div>' : '')
			.($text !== '' ? '<div class="card-text">'.$text.'</div>' : '')
			.'</div>'
		: '';

	$tools = ws_card_tools($c['tools'] ?? null, $href);
	$actions = ws_card_actions($c['actions'] ?? null);
	$arrow = ($href !== '' and $tools === '' and $actions === '' and ($c['arrow'] ?? true))
		? '<div class="card-arrow">'.ws_card_icon($external ? 'open_in_new' : 'arrow_forward').'</div>'
		: '';
	if($href === ''){
		$class .= ' card-sheet';
	}
	return '<'.$tag.' class="'.ws_card_esc($class).'"'.$attrs.'>'
		.$media.($c['head'] ?? '').$body.$tools.$actions.$arrow
		.'</'.$tag.'>';
}

}// function_exists
