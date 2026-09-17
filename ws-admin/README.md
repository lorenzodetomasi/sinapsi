# The backend: one hub, derived files, lazy refresh

How the administration of every site served by this CMS (isotype, meetoo,
your-theme's children…) is meant to work from here on. Decided on
2026-09-15; what is built is marked, what is not is the plan.

## Principles

1. **JSON is the only source.** Every content is an `index.json` in JSON-LD
   (schema.org, plus the CMS's own keys: `wspath`, `query`, `parent`,
   `title`, `robots`, `output`…). Everything else is *derived*: the XML twin
   the templates read, the site map, the public `sitemap.xml`, the HTML
   cache, the AMP page, the resized images. Hand-written `.wsx` files are a
   legacy that the migration retires; a JSON-sourced content has no `.wsx`.
2. **Derived means disposable.** A derived file can be deleted and comes
   back. Nothing is ever edited in a derived file; the `users/` XML is the one
   exception (the CMS's user record is authored there) and is never touched.
3. **Staleness by hash, not by date.** A derived file is stale when the hash
   of its source (or the generator's version) differs from what the manifest
   recorded when it was built. Dates lie: an FTP upload gives every file the
   hour of the upload.
4. **Lazy first.** Whoever asks for a page makes that page's derived files
   fresh, and only those (`ws_content_relpath()` → `ws_content_ensure_xml()`).
   The hub shows how many things are waiting and can do them all. A
   background pass (the WordPress-cron way: a request that finds work due
   spawns a non-blocking loopback request that does it while the visitor is
   served) may come later.
5. **Preview, then apply.** Every operation says what it would do before it
   writes. This is the maintenance registry Meetoo already has, made the
   CMS's own.
6. **One hub, authenticated.** `ws-admin/index.php` — Google Sign-In, roles
   from the site's `users.xml`, super-admins shared across sites. The pages
   the CMS used to route under `/admin/refresh*` without any authentication
   retire. A theme keeps its own `admin/` folder for the templates that are
   its (the user profile); the entities (persons, events, places, local
   businesses, organizations) are the hub's, some behind a plugin.
7. **Disabled by name.** A content directory, a file or a module whose name
   begins with `-` is switched off: a reminder or a work in progress. Scans
   skip it.

## Files

```
ws-admin/
  index.php               the hub: auth, the registry's UI, one site at a time
  refresh.php             (legacy, CMS-routed, unauthenticated - to retire)
  refresh-<things>.php    a MODULE: everything of one kind for one content root
                          - list, preview, apply, report. Registered in the hub.
  _refresh-<thing>.php    a UNIT: one item of that kind. A library of functions,
                          no page of its own; the front-end may require it
                          (that is how a request refreshes what it needs).
  lib/
    derived.php           the manifest of derived files, hashes, locks   [built]
    ws-maintenance.php    the registry of operations (preview / apply)
    …                     Meetoo's libraries (to be renamed in English on touch)
```

| Module | Unit | Source → target | State |
|---|---|---|---|
| `refresh-contents.php` | `_refresh-content.php` | `index.json` → `index.xml`; until migrated, a hand-written `.wsx` → its resolved `.xml` | built |
| `migrate-pages.php` | `_migrate-page.php` | a hand-written `index.wsx` → `index.json` (+ its twin); the `.wsx` is set aside as `-index.wsx` | built |
| `refresh-sitemaps.php` | `_refresh-sitemap.php` | every page of a root → `ws_sitemap.wsx`, then the public `sitemap.xml` | built, applied on isotype |
| `refresh-html.php` | `_refresh-html.php` | a page → `<cache>/<host>/<path>.html`, only when `output` lists `html` and the visitor has no session | then |
| `refresh-images.php` | `_refresh-image.php` | `<image>` sources → the declared destinations | later |
| (amp plugin) | | a page → `amp/index.html` when `output` lists `amp` | later |

## The manifest

`<root>/_index/derived.json`, one per content root (`contents/<site>/<locale>`,
or `contents/<site>` when there is no locale). Keyed by the derived file,
relative to the root:

```json
{
  "services/brand-design/index.xml": {
    "source": "services/brand-design/index.json",
    "hash": "sha1 of the source file",
    "generator": "json-to-xml 2026.09.15",
    "built": "2026-09-15T12:00:00+02:00"
  }
}
```

`stale(target)` = target missing, or no record, or `hash` ≠ sha1(source), or
`generator` ≠ the generator's current version. The manifest is a cache of
its own: delete it and every derived file is rebuilt once.

Writes are serialised with `flock` on `<root>/_index/derived.lock`, so two
requests asking for the same page do not build it twice.

## The XML twin

`index.xml` is `jsonToWsx(index.json)` — the pure conversion, XInclude
elements left in place. The CMS resolves includes at request time (as it
does for Meetoo's entities today), so an included file that changes needs
no bookkeeping: the twin is stale only when *its* source changes. The cost
of resolution is paid once per page by the HTML cache, not here.

**A `.wsx` not yet migrated** (the headings, the locations, the shared
lists, the pages still to migrate) is a source of the second kind: same
manifest, same laziness, one difference in the build - its twin is the
`.wsx` with its includes resolved, which is what the old admin refresh
produced and what its readers expect. A `.wsx` beside a JSON of the same
name is no longer a source; `ws_sitemap.wsx` never is. The headings are
loaded the same way (`ws-settings.php`): source first, twin made fresh,
twin read.

Guards, inherited from Meetoo's `xml-rebuild`:
- a JSON without `@context` and `@type` is not a content (a datalist, an RSVP list, a settings file): skipped;
- an existing XML whose root element differs from what the JSON would
  produce is not a twin (the `users/` record, a hand-written file): left
  alone and reported, unless the operation is told to adopt the new root;
- `users/`, `_index/`, `_trash/` and `-`-prefixed directories are never scanned.

## What a page is, and what it is about

`@type` is an array, general to specific: every page is a `WebPage`; a
page with pages under it adds `CollectionPage`; a page with a schema.org
role adds it (`["WebPage", "AboutPage"]`, `["WebPage", "ContactPage"]`).
The converter makes the first the twin's root element and lists them all
in `xsi:type`, so every page's twin has the same root and the specific
type sits where the CMS reads it.

What a page is *about* is its `mainEntity` (a Service, a Person, an
Event), as Meetoo's `ItemPage` pages already do. The home is the page at
`/` that is about the site: its `mainEntity` is the `WebSite` (the same
`#website` node every page names in `isPartOf`), so the map says
`WebSite` for it, as Meetoo's map already did. There is no `Index` type:
schema.org has none, and nothing looked one up. There is no `type` key:
the CMS's `type` - the one the site map carries and the templates look
up (`url[type = "ContactPage"]`, `$rewrite_rule->type`) - is derived
where the map is built: the mainEntity's most specific type when there
is one, else the page's own most specific type.

A role schema.org has no word for (PrivacyPage, CookiesPage,
DisclaimerPage: what the legal menu and the forms look up) is a type in
the CMS's own vocabulary, declared in the context beside schema.org the
way Meetoo declares `meetoo:`:

```json
"@context": ["https://schema.org", {"ws": "https://localbiz.it/ws#"}],
"@type": ["WebPage", "ws:PrivacyPage"]
```

The map strips the prefix (`<type>PrivacyPage</type>`, as always); the
head publishes the schema.org types only.

## What a content declares

Besides schema.org and the routing keys, `index.json` may say which derived
outputs it wants:

```json
"output": ["html"]
```

`xml` is implicit (every content has its twin). `html` asks for the static
page; `amp` for the AMP one. The legacy `<htmlcache>true</htmlcache>` of
some `.wsx` files means `"output": ["html"]`.

## The HTML cache (plan)

- Written after a page is rendered for a visitor without a session, when
  its content lists `html`; served by Apache before PHP via a rewrite rule
  that tries `ws-cache/<host>/<path>.html` first.
- Invalidated by a **global stamp**: any change to a content, a template or
  a stylesheet bumps it and every cached page is stale; pages are rebuilt
  lazily, on their next request. Fine-grained dependencies (a page's
  children, siblings, headings) are not tracked, on purpose.
- Pages with user state are never cached; the login widget will move to
  the client so more pages qualify.

## The site map

`<root>/ws_sitemap.wsx` is derived from the pages of the root: a page is on
the map because its `index.json` exists and says where it stands
(`wspath`, `query`, `parent`, `type`, `robots`, `title`, `description`…).
While the migration lasts, a page still written as `index.wsx` is mapped
too, from its own fields. Tree order: a page before the pages under it,
siblings by path. A sub-map in a folder without pages of its own (the
admin's) is included as a fragment; a sub-map beside pages is the old way
and is reported until it is removed. The manifest records the map against
the hash of every page's hash; a twin rebuilt by a request makes the map
stale, and the hub rebuilds it. The root `contents/ws_sitemap.wsx`
composes the sites' maps and the admin's, as today, and the public
`sitemap.xml` for the search engines follows it (Meetoo's
`ws_mappa_sitemap_pubblico`, which knows the mounts).

**A page that exists is a page that is published.** A draft, a leftover,
a page not meant to be found is switched off by its folder's name:
`-awards/`. That is the one decision the derivation cannot make.

## The migration

`migrate` turns a hand-written page into JSON: fragment includes (the
brand's name in a title, the cover) become literal values; whole-file
includes (the clients, the awards) stay references; attributes become
`@name` and `id` becomes `@xml:id`; `<grid>` becomes a section of class
`grid` with the name and xpath the template needs; `<htmlcache>` becomes
`output: ["html"]`; the old `type` becomes the page's `@type` (a schema.org
role or a `ws:` one) or its `mainEntity` (a Service, a Person), with a
minimal entity to enrich.
Comments and empty elements are dropped and counted; every such decision
is in the report. The `.wsx` is kept beside the JSON as `-index.wsx`.
