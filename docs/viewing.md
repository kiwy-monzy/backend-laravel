# How to see it

Everything is one Laravel app. Start it once and every surface is reachable.

```bash
php artisan serve --host=0.0.0.0 --port=50051
```

Or use the batch file, which does the same thing and falls back to
`C:\tools\php\php.exe` if `php` is not on PATH:

```bash
dev.bat
```

Leave that running. All URLs below assume port 50051.

---

## 1. The public FGE website

```
http://localhost:50051/s/fge
```

`/` resolves by hostname and falls back to FGE, so plain
`http://localhost:50051/` shows the same thing in development.

Pages: `/s/fge/about`, `/projects`, `/gallery`, `/events`, `/blog`, `/team`,
`/donate`, `/contact`. The nav only shows a page when the site has content for
it — an empty section is hidden rather than linked to a blank page.

The contact and volunteer forms on `/s/fge/contact` are live: submitting one
creates a row that appears under Messages / Volunteers in the admin.

## 2. The admin

```
http://localhost:50051/admin
```

| Username | Password | What they see |
| --- | --- | --- |
| `fge_owner` | `fgetanzania.123` | Every website, plus the Websites page |
| `admin` | `fgetanzania.123` | The FGE website only |
| `fge` | `fgetanzania.123` | The FGE website only |

The password comes from `BOOTSTRAP_OWNER_PASSWORD` in `.env`.

Signed in as the owner, the titlebar carries a website picker (it only appears
once there is more than one site), a global search — `Ctrl+K` focuses it — and
a light/dark toggle. The sidebar collapses with the button beside the logo and
remembers the choice.

## 3. Seeing a template without publishing it

This is the important one: you never have to switch a live site to find out
what a template looks like.

```
http://localhost:50051/admin/preview/template3
http://localhost:50051/admin/preview/template4?theme=sunset
http://localhost:50051/admin/preview/template2?theme=slate&page=gallery
```

It renders **your own content** in that template and palette and saves nothing.
The same links are on every card under **Websites → FGE → Template / Theme**,
as the *Preview* buttons.

| `template` | Looks like |
| --- | --- |
| `template1` | Classic — gradient page, translucent sticky navbar. What FGE runs on. |
| `template2` | Editorial — serif headings, flat page, hairline rules |
| `template3` | Studio — dark shell, numbered sections |
| `template4` | Campaign — split hero with a pinned donate rail |
| `template5` | Compact — single column, no cards, small payload |

| `theme` | |
| --- | --- |
| `fge` | FGE Emerald |
| `ocean` · `sunset` · `royal` · `rose` · `slate` · `gold` | |

`?page=` accepts any public page name (`home`, `about`, `gallery`, …).

**Why FGE looks red rather than emerald:** the theme preset is the weakest
input. FGE has a stored `theme` content section with `#e42f2f`/`#e9267c` in it,
written by the old admin, and that outranks the preset — so the site renders
exactly as its own data says. Edit it under **Content → Theme**, or override it
per-site under **Websites → FGE → Colour overrides**, which outranks both.

## 4. Seeing a second website

**Websites → Add website**. Pick a slug, a template and a theme; it appears at
`/s/{slug}` immediately. It starts with no content, so fill in **Content →
General** and **Hero** first — those two are what the home page is built from.

Once a second site exists, the titlebar picker appears and everything in the
admin — dashboard counts, donations, messages, search — narrows to whichever
site is selected.

## 5. Checking where the files are

**Storage** lists everything in `storage/app/public/uploads` with a thumbnail,
size and an Open link. **Gallery** is the subset the public site shows.

If the Storage page ever warns that files are still stored as base64, or the
database was rebuilt and the file list looks empty while the disk is not:

```bash
php artisan fge:consolidate-assets --dry-run
```

Drop `--dry-run` to apply. It is idempotent — safe to run any number of times.

## 6. The JSON API is still there

The packaged React frontend's endpoints are unchanged, mounted at the root:

```bash
curl -X POST http://localhost:50051/GetWebsite -H "Content-Type: application/json" -d '{}'
```

`/Login`, `/ListGallery`, `/UploadFile` and the rest all still answer, and now
write files to storage instead of base64 into the database.

---

## If something does not come up

**Everything 500s with "no such table".** The database has not been migrated:

```bash
php artisan migrate --seed --force
```

**Images 404 from `/storage/...`.** The symlink is missing:

```bash
php artisan storage:link
```

**"disk I/O error" or "database disk image is malformed".** Two `php artisan
serve` processes are fighting over the SQLite file. Stop all of them, delete
the leftover `database/database.sqlite-wal` and `-shm`, then start one server.

**A page renders but looks unstyled.** The CSS is plain files under `public/css`
with no build step, cache-busted by modification time — a hard reload
(`Ctrl+Shift+R`) is enough.
