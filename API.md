# Site Docs API

`SiteDocs` and `ProcessSiteDocs` together manage a hierarchy of internal documentation pages. This document reflects the current implementation in the module code and is intended for developers and AI agents working directly with the modules.

## Modules

- `SiteDocs` — data/logic module. Installs fields, templates, and sample pages, and exposes the access-control + search API used by the admin UI.
- `ProcessSiteDocs` — admin `Process` module. Renders the docs index, a single-page view, the print manual, and the admin navigation.

## Module metadata

The module metadata is enforced by the `.info.php` files:

- `SiteDocs.info.php`
  - `requires` => `ProcessWire>=3.0.240,AdminThemeUikit`
  - `autoload` => `template=admin`
  - `installs` => `ProcessSiteDocs`
- `ProcessSiteDocs.info.php`
  - `requires` => `SiteDocs`
  - `permission` => `page-edit`
  - `page.name` => `docs`
  - `page.title` => `Docs`
  - `useNavJSON` => `true`

## Template and field model

Documentation pages are normal ProcessWire pages stored under a single hidden root index page.

```
sitedocs-index (hidden root page created under /)
└── sitedocs-page
    └── sitedocs-page
        └── sitedocs-page
```

Constants:

```php
SiteDocs::templateIndex // 'sitedocs-index'
SiteDocs::templatePage  // 'sitedocs-page'
SiteDocs::fieldRoles    // 'sitedocs_roles'
```

Fields created if missing:

- `body` — `FieldtypeTextarea`
- `images` — `FieldtypeImage`
- `sitedocs_roles` — `FieldtypePage` (multi-select, from Roles page tree)

Templates created if missing:

- `sitedocs-index` — fields: `title`, `body`, `images`
- `sitedocs-page` — fields: `title`, `body`, `images`, `sitedocs_roles`

Important details:

- `sitedocs-index` has `noParents = -1`
- both templates use `useRoles = 1`
- template roles are deliberately left empty so docs pages are not publicly viewable, and only editable by superuser(s) by default
- pages are intended to be rendered only via `ProcessSiteDocs`, which performs its own access checks

## URL behavior for docs pages

`SiteDocs::init()` adds a property hook for docs pages:

```php
Page(template=sitedocs-index|sitedocs-page)::urlViewSiteDoc
```

This property resolves to the admin view URL for the page:

```php
{$processPath}view/?id={$page->id}
```

This is the value used by the search results and navigation links, and it is skipped for admin edit/add/sort/clone/export processes so ProcessWire admin screens continue to use the real edit URLs.

## Access control

Access control for viewing docs is implemented by the `SiteDocs` module instead of by core template roles.

### API

```php
$siteDocs = $modules->get('SiteDocs');

$roles = $siteDocs->pageAllowedRoles($page); // PageArray|null
$canView = $siteDocs->userCanView($page, $user = null); // bool
```

Edit access control is controlled by template settings.

### Rules

- `userCanView()` immediately returns `true` for superusers
- unpublished docs pages are denied
- `pageAllowedRoles()` walks up the page's ancestors and returns the first `sitedocs_roles` values it finds
- if no ancestor has `sitedocs_roles` set, the page is unrestricted for any user allowed to use the module
- if roles are set, access is allowed only when the current user has one of those roles

```php
public function pageAllowedRoles(Page $page) {
    $p = $page;
    while($p && $p->id) {
        if($this->isSiteDocsPage($p) && $p->template->hasField(self::fieldRoles)) {
            $roles = $p->getUnformatted(self::fieldRoles);
            if($roles && count($roles)) return $roles;
        }
        $p = $p->parent;
    }
    return null;
}
```

Important: module-level access still depends on the normal ProcessWire permission model. The `ProcessSiteDocs` module requires the `page-edit` permission by configuration.

## `ProcessSiteDocs` initialization and caching

`ProcessSiteDocs::init()` performs two important setup steps before rendering any docs view:

1. It resolves the `SiteDocs` module instance and loads any configured custom CSS/JS files.
2. It builds a per-user navigation cache for the accessible docs tree.

```php
$processSiteDocs->init();
```

Custom asset behavior:

- `customCssFile` and `customJsFile` are checked on init
- paths containing `..` are rejected to prevent traversal
- only `.css` or `.js` files are accepted
- files are only added if they exist on disk under the site root
- the URL is attached to ProcessWire's script/style stacks via `$config->styles` / `$config->scripts`

Navigation cache behavior:

```php
$cacheName = ['nav'];
foreach($user->roles->sort('id') as $role) {
    $cacheName[] = $role->id;
}
if(isset($user->language)) {
    $cacheName[] = $user->language->name;
}

$this->siteDocsNav = $this->wire()->cache->getFor(
    $this,
    implode('-', $cacheName),
    'template=' . SiteDocs::templateIndex . '|' . SiteDocs::templatePage,
    function() {
        // build menu tree for this user's accessible docs pages
    }
);
```

The cached data is built from the visible children of the docs index page using `menuItem()` and is filtered with `filterItems()`, which removes pages the current user cannot view.

## `SiteDocs` public methods

```php
$siteDocs->getTemplatePageName();          // 'sitedocs-page'
$siteDocs->isSiteDocsPage(Page $page);     // bool
$siteDocs->getIndexPage();                 // Page|NullPage
$siteDocs->pageAllowedRoles(Page $page);   // PageArray|null
$siteDocs->userCanView(Page $page, ?User $user = null); // bool
$siteDocs->getContent(Page $page);         // returns $page->get('body')
$siteDocs->findCustom($query, array $contentFields = ['body']); // PageArray
```

## Search

The search API is intentionally lightweight and in-PHP filtered:

```php
$matches = $siteDocs->findCustom('getting started');
```

Behavior:

- searches `title` and each field in `$contentFields`
- default content fields: `['body']`
- runs with `check_access=0` and `include=hidden`
- removes non-viewable pages manually via `userCanView()`
- re-ranks title matches so exact and phrase matches float above broader matches

This is used by `ProcessPageSearchLive::findCustom` so admin live search can show results under a `Docs` heading for matches outside the docs section.

## `ProcessSiteDocs` process routes

The process exposes these routes:

| URL | Method | Purpose |
|---|---|---|
| `docs/` | `___execute()` | landing page / index |
| `docs/view/?id={id}` | `___executeView()` | single docs page |
| `docs/manual/` | `___executeManual()` | print-friendly manual |
| `docs/navJSON` | `___executeNavJSON()` | JSON model for admin nav |

The process also sets the admin page title and breadcrumb trail in the browser UI.

## Rendering API

The render methods are all hookable via the `___` prefix:

```php
$processSiteDocs->renderIndex();
$processSiteDocs->renderIndexImage();
$processSiteDocs->renderManual();
$processSiteDocs->renderManualPage(Page $page, $depth = 2);
$processSiteDocs->renderNav(array $items, bool $isManual = false);
$processSiteDocs->renderPage(?Page $page = null);
$processSiteDocs->renderView($content = '');
$processSiteDocs->getIndexImage();
$processSiteDocs->getSiteName();
$processSiteDocs->errorRedirect($text, $flags = 0);
```

Behavior summary:

- `renderIndex()` renders the docs landing page with the optional banner image from the index page
- `renderIndexImage()` returns the rendered image from `getIndexImage()` on the docs index page, if present
- `renderManual()` builds a splash section, intro text, TOC, and each accessible top-level docs page recursively
- `renderManualPage()` rewrites heading levels to preserve hierarchy while nesting content and appends child sections recursively
- `renderNav()` renders the docs navigation as either interactive UIkit markup or a plain anchor list for the manual TOC
- `renderPage()` renders and wraps the main content of a single docs page
- `renderView()` builds the page chrome, breadcrumbs, side nav, on-page heading nav, prev/next navigation, and edit/add controls
- `getIndexImage()` returns the first `images` item on the docs index page, if present
- `getSiteName()` falls back to `$config->httpHost` when the configured site name is empty
- `errorRedirect()` adds the error to the session and redirects back to the docs root process page

### Examples

In `/site/templates/admin.php`:

```php
// Set the site name for the SiteDocs module
$wire->addHookAfter('ProcessSiteDocs::getSiteName', function(HookEvent $event) use ($config) {
	$event->return = $event->object->siteName ?: setting('siteName') ?: $config->httpHost;
});

// Use the site logo for the SiteDocs module index image if empty
$wire->addHookAfter('ProcessSiteDocs::getIndexImage', function(HookEvent $event) use ($pages) {
	if(!$event->return) {
		$pageHome = $pages->get(1);
		if($pageHome->logo->count) {
			$event->return = $pageHome->logo->first();
		}
	}
});
```

## Navigation data structure

The docs navigation is cached per user role/language and stored as a plain array tree instead of `WireArray` objects:

```php
[
    [
        'id' => 123,
        'url' => '/docs/view/?id=123',
        'title' => 'Getting Started',
        'items' => [ ...child items... ],
        'hasContent' => true,
    ],
]
```

`menuItem()` builds this structure recursively from the visible children of each docs page, and it marks `hasContent` based on whether the page has non-empty body content.

`filterItems()` removes items the current user cannot view before they are rendered in the nav or used for prev/next links. `pageChildren()` wraps that logic for a page's direct children.

## Configuration API

The config class is `ProcessSiteDocsConfig` and exposes these field values:

```php
$processSiteDocs->siteName
$processSiteDocs->customCssFile
$processSiteDocs->customJsFile
$processSiteDocs->engineer_instructions
```

Behavior:

- `siteName` falls back to `$config->httpHost` in `getSiteName()`
- CSS/JS paths are validated before being added to ProcessWire's styles/scripts stack
- `engineer_instructions` is shown only when `AgentTools` is installed

## Install / uninstall

Install flow:

```php
$siteDocs = $modules->get('SiteDocs');
$siteDocs->install();
```

The install method ensures the following exist:

- `body` field
- `images` field
- `sitedocs_roles` field
- `sitedocs-index` and `sitedocs-page` templates
- hidden `/sitedocs/` index page
- sample docs pages for a basic starter tree

It also clears cache entries for the module before creating these items.

Uninstall flow:

```php
$siteDocs->uninstall();
```

This uninstalls `ProcessSiteDocs` and removes its admin page, but intentionally leaves the docs fields, templates, and content pages in place to avoid destroying the documentation content.

## Notes for AI agents

- Check `ProcessSiteDocs::engineer_instructions` for any special instructions intended for engineers
- Prefer `SiteDocs::getIndexPage()` and `Page::id`-based lookups over manually constructing doc URLs
- Use `siteDocs->userCanView($page)` before surfacing docs pages to users or in search results
- Read existing docs pages under the `sitedocs-page` template for tone and structure
- When adding content, keep the page under the docs index or an existing docs child page and use the `sitedocs-page` template

