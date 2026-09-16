# Site Docs

Two modules work together to provide internal site documentation:

- `SiteDocs` — the data/logic module. It creates the docs templates, fields, and access-control/search logic.
- `ProcessSiteDocs` — the admin `Process` module. It renders the docs index, single-page views, print manual, and admin navigation.

See [API.md](API.md) for the technical API reference.

## Requirements

- ProcessWire 3.0.240+
- `AdminThemeUikit`
- built-in `title` field
- `body` field (created automatically if missing)
- `images` field (created automatically if missing)
- `InputfieldTinyMCE` is recommended when the `body` field is created; it falls back to `InputfieldCKEditor` and then plain `InputfieldTextarea`
- the `dom` extension is required for heading rewrite and on-page anchor links

## Installation

1. Copy this directory to `site/modules/SiteDocs/`.
2. Refresh modules in the admin and install `SiteDocs`.
3. On install, `SiteDocs` creates the following if they do not already exist:
   - fields: `body`, `images`, `sitedocs_roles`
   - templates: `sitedocs-index`, `sitedocs-page`
   - hidden root docs page under `/`
   - sample docs pages under the hidden index
   - the `ProcessSiteDocs` admin page at `Admin > Docs`

Existing fields and templates with matching names are left unchanged.

## Usage

- Visit **Admin > Docs** to browse the docs.
- The documentation is a single nested page tree under the hidden docs index.
- Editing uses the normal ProcessWire page edit/add screens; there is no separate docs-only editor.
- `ProcessSiteDocs` is configured with the `page-edit` permission in its module metadata, so access to the admin page follows the normal ProcessWire permission model.
- Per-page visibility is controlled by the `sitedocs_roles` field. If a page has no roles set, access is inherited from the nearest ancestor that does; if no ancestor sets roles, the page is unrestricted for any user who can use the module.
- Superusers always have access.
- Docs pages appear in admin live search results under the `Docs` group when searched from outside the docs section.
- A print-friendly manual is available at **Admin > Docs > Print manual**.

## Configuration

From the module config screen you can set:

- **Site Name** — used in page titles and page chrome; falls back to the site HTTP host
- **Custom CSS File** — optional relative path to a CSS file under the ProcessWire root
- **Custom JavaScript File** — optional relative path to a JS file under the ProcessWire root
- **Notes for Agent Tools** — shown only when the `AgentTools` module is installed; freeform notes for AI agent context

## Uninstall

Uninstalling `SiteDocs` uninstalls `ProcessSiteDocs` and removes its admin page. Fields, templates, and content pages are intentionally left in place so that the documentation content is not destroyed.

## License

Mozilla Public License 2.0. See `LICENSE`.
