<?php
/*
 * The events of a list page.
 *
 * The page says WHAT it collects, in its own JSON, with schema.org's `about`:
 *
 *   "mainEntity": {
 *     "@type": ["ItemList", "CreativeWork"],
 *     "about": { "@type": "Event", "organizer": { "@id": "places/…" } }
 *   }
 *
 * and this reads the root's index and keeps the events that answer to that
 * description. Nothing here decides the scope, so the same template serves a
 * page of one business's events and, the day Meetoo's zones move onto the same
 * index, a page of everything happening in a neighbourhood: there the `about`
 * says `location.containedInPlace`, and only the pattern changes.
 *
 * Upcoming first, then the archive. A past event is kept and shown, because a
 * year later it is the reason someone can tell what this place actually does.
 *
 * The helpers are declared before they are used, and not at the foot of the
 * file where a template's helpers usually end up: they are wrapped in
 * `function_exists` (a template part may be included twice on one page), and
 * PHP only hoists an UNCONDITIONAL declaration. Inside an `if`, a function
 * exists from the moment execution passes it and not before.
 *
 * @package WS
 * @subpackage Your Theme
 */

global $ws_content, $ws_query;

require_once ws_admin_abspath() . '/_refresh-events-index.php';

if (!function_exists('ws_event_list_about')) {
    /**
     * The pattern this page collects by, from its own index.json.
     *
     * Read from the JSON and not from the XML twin on purpose: `about` is a
     * nested description, and the twin turns nesting into elements whose shape
     * the matcher would have to undo. The JSON is the source; here it is also
     * the easiest thing to read.
     */
    function ws_event_list_about(): array {
        global $ws_query;
        $rel = (string)($ws_query['content'] ?? '');
        if ($rel === '') return [];
        $file = ws_root_abspath() . '/' . WS_CONTENTS_RELPATH . '/' . $rel . '/index.json';
        if (!is_file($file)) return [];
        $d = json_decode((string)@file_get_contents($file), true);
        return is_array($d['mainEntity']['about'] ?? null) ? $d['mainEntity']['about'] : [];
    }
}

if (!function_exists('ws_event_when')) {
    /**
     * The day and time of an event, shown in the event's OWN timezone.
     *
     * An event at 17:30 in Rome is at 17:30 on the poster, whatever the
     * server's clock is set to — so the offset the date carries is what the
     * time is rendered in, and never the server default.
     */
    function ws_event_when(array $e): string {
        $iso = (string)($e['startDate'] ?? '');
        if ($iso === '') return '';
        try {
            $d = new DateTime($iso);
        } catch (Exception $ex) {
            return $iso;
        }
        return $d->format('d/m/Y H:i');
    }
}

if (!function_exists('ws_event_href')) {
    /**
     * The event's address, from the page's own `wspath` when it has one and
     * from the site map otherwise — the same rule as the menus: a list that
     * writes its own links is a list that goes on offering yesterday's.
     * An event with no page of its own gets no link, rather than a broken one.
     */
    function ws_event_href(array $e): string {
        global $ws_contentmap;
        if (!empty($e['wspath'])) return ws_href((string)$e['wspath']);

        $id = (string)($e['@id'] ?? '');
        if ($id === '' || empty($ws_contentmap)) return '';
        $found = $ws_contentmap->xpath('url[contains(query, "/' . $id . '")]');
        return $found && !empty($found[0]->wspath) ? ws_href((string)$found[0]->wspath) : '';
    }
}

if (!function_exists('ws_event_place')) {
    /** The event's place: one reference, whether the entry holds one or a list. */
    function ws_event_place(array $e): ?array {
        $loc = $e['location'] ?? null;
        if (!is_array($loc)) return null;
        if (isset($loc['@id'])) return $loc;
        $first = reset($loc);
        return is_array($first) ? $first : null;
    }
}

/* The content root this page belongs to: `<site>/<locale>`, taken from the
 * page's own content path, which is where the CMS already put it. Not built
 * from the locale: the folder is named by the content, and the two agree only
 * as long as nobody renames one. */
$ws_event_parts = explode('/', trim((string)($ws_query['content'] ?? ''), '/'));
$ws_event_root = ws_root_abspath() . '/' . WS_CONTENTS_RELPATH . '/'
               . implode('/', array_slice($ws_event_parts, 0, 2));
$ws_event_groups = ws_events_index_for($ws_event_root, ws_event_list_about());
?>
<?php if (!$ws_event_groups['upcoming'] && !$ws_event_groups['past']): ?>
				<p class="event-list-empty"><?php _e('No events yet.'); ?></p>
<?php else: ?>
<?php foreach (array('upcoming' => __('Coming up'), 'past' => __('Past events')) as $ws_group => $ws_group_title): ?>
<?php if (!$ws_event_groups[$ws_group]) continue; ?>
				<section class="event-list event-list-<?php echo $ws_group; ?>">
					<h3><?php echo $ws_group_title; ?></h3>
					<ul class="event-list-items">
<?php foreach ($ws_event_groups[$ws_group] as $ws_event): ?>
<?php $ws_event_href = ws_event_href($ws_event); $ws_event_place = ws_event_place($ws_event); ?>
						<li class="event-list-item" itemscope itemtype="https://schema.org/Event">
<?php if ($ws_event_href): ?>
							<a href="<?php echo $ws_event_href; ?>" itemprop="url"><span class="event-name" itemprop="name"><?php echo htmlspecialchars((string)$ws_event['name'], ENT_QUOTES, 'UTF-8'); ?></span></a>
<?php else: ?>
							<span class="event-name" itemprop="name"><?php echo htmlspecialchars((string)$ws_event['name'], ENT_QUOTES, 'UTF-8'); ?></span>
<?php endif; ?>
<?php if (!empty($ws_event['startDate'])): ?>
							<time class="event-when" itemprop="startDate" datetime="<?php echo htmlspecialchars((string)$ws_event['startDate'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo ws_event_when($ws_event); ?></time>
<?php endif; ?>
<?php if (!empty($ws_event_place['name'])): ?>
							<span class="event-where" itemprop="location" itemscope itemtype="https://schema.org/Place"><span itemprop="name"><?php echo htmlspecialchars((string)$ws_event_place['name'], ENT_QUOTES, 'UTF-8'); ?></span></span>
<?php endif; ?>
						</li>
<?php endforeach; ?>
					</ul>
				</section>
<?php endforeach; ?>
<?php endif; ?>
