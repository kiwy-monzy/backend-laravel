# FGE — Laravel backend *and* frontend

One Laravel application serving every part of the product:

| URL | What it is |
| --- | --- |
| `/` | The public website, resolved by hostname |
| `/s/{slug}` | A specific website by slug (`/s/fge`) |
| `/admin` | The admin, in the RoadWatch chrome |
| `/Login`, `/GetWebsite`, … | The JSON API the packaged React frontend still calls |

The Rust server (`../fge-backend`) and the React frontend (`../fge-frontend`)
are superseded. Their assets have been moved into `storage/app/public/uploads`
and their originals set aside in `../backups/pre-laravel-assets/`.

**[docs/viewing.md](docs/viewing.md) — how to see each part of it**, including
previewing a template against real content without publishing it.

## Getting started

```bash
php artisan migrate --seed
```

```bash
php artisan serve --port=50051
```

Seeded accounts (password from `BOOTSTRAP_OWNER_PASSWORD`, default
`fgetanzania.123`):

| Username | System tier | Team role | Sees |
| --- | --- | --- | --- |
| `fge_owner` | System admin | Administration | Every organization and every user |
| `admin` | Organization owner | Administration | The FGE organization only |
| `fge` | Member | Manager | FGE's modules and content |

## Two ladders, not one

`users.role` says what you are to the **installation**; your seat in
`organization_members` says what you do inside **one organization**. They
answer different questions, and a charity routinely wants someone who can
approve an invoice but not touch the public website.

| System tier | |
| --- | --- |
| `system_admin` | Runs the installation. Creates organizations, assigns owners, grants modules. |
| `owner` | Owns one organization: its team, its websites, its module hand-outs. |
| `member` | Belongs to an organization; reaches what their team role allows. |

| Team role | View | Add | Edit | Delete | Approve | Manage people |
| --- | :-: | :-: | :-: | :-: | :-: | :-: |
| Administration | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Manager | ✓ | ✓ | ✓ | — | ✓ | — |
| Salesperson | ✓ | ✓ | ✓ | — | — | — |
| Employee | ✓ | ✓ | — | — | — | — |

Every admin list funnels through `AdminController::scoped()` and every write
through `AdminController::guard()`; module pages add `ModuleController::scopedToOrg()`
and `authorizeAction()`. Scoping is one decision in one place rather than
something each controller has to remember.

See **[docs/modules.md](docs/modules.md)** for the module system and the three
gates, and **[docs/viewing.md](docs/viewing.md)** for how to see each surface.

## Templates and themes

Five public layouts, in `resources/views/templates/`. They share
`_page.blade.php`, which decides what a page is made of — so switching a site's
template changes how it looks and never what it contains.

Templates come in two collections. **Custom** templates are hand-built ports of
a specific design and carry their own stylesheet, nav, hero and footer;
**standard** ones share `site.css` and differ only by arrangement.

| Key | Collection | Character |
| --- | --- | --- |
| `template0` | Custom | **FGE Original** — a faithful port of the React frontend: floating pill navbar, masked hero grid, drifting blobs, animated tri-colour headline. **FGE runs on this.** |
| `template1` | Standard | Classic — gradient page, translucent sticky navbar |
| `template2` | Standard | Serif headings, flat page, hairline rules |
| `template3` | Standard | Dark shell, numbered sections |
| `template4` | Standard | Split hero with a pinned donate rail |
| `template5` | Standard | Single column, no cards, small payload |

`App\Support\ThemeFactory` derives every CSS custom property from three seed
colours, so a template author only ever styles against variable names. Colours
resolve weakest-first: preset → the site's `theme` content section (what the
old admin wrote) → `websites.theme_overrides`.

Five splash screens (`none`, `wordmark`, `pulse`, `bar`, `curtain`) are chosen
per website alongside the template. They are pure CSS and dismiss as soon as
the page is ready — the configured seconds are a ceiling, not a duration.

Preview any combination against your own content without publishing it:

```bash
open http://localhost:50051/admin/preview/template3?theme=royal&splash=curtain
```

## Assets

Nothing is stored as base64. `App\Services\MediaLibrary` is the one place a
file becomes a URL; uploads land in `storage/app/public/uploads` and rows keep
a `/storage/uploads/…` path.

To sweep any that got in — from an import, or an older client:

```bash
php artisan fge:consolidate-assets --dry-run
```

Drop `--dry-run` to apply, and add `--prune` to clear the adopted copies out of
the old projects (they are moved to `../backups/pre-laravel-assets/`, not
deleted). The Storage page warns whenever an inline row is left.

## Content

Each of the eleven sections has a real form — repeaters for projects, team,
posts and events, an image picker backed by storage — with a live preview of
the site in its selected template beside it. The raw JSON editor is still one
click away, because the sections are free-form by design: a template can
introduce a field without a migration, and the form must be a convenience
rather than a ceiling. Fields the form does not know about survive editing
untouched.

To start a new site from one that already exists:

```bash
php artisan fge:extract-layout https://example.org --dry-run
```

It reads the rendered page and fills `general`, `hero`, `about` and the gallery.
Everything it writes is a guess from markup and it never overwrites something a
person typed — look with `--dry-run` first, and add `--images` to pull the
pictures into storage rather than hot-linking them.
