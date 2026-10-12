# Fetch — Web Technologies PBL

Two things live in this repository:

| Path | What it is |
| --- | --- |
| [`index.html`](index.html) | The Review 1 presentation — problem, solution, stack, architecture, system flow, approach |
| [`fetch/`](fetch/) | The actual application: an online food ordering platform in PHP 8 + MySQL ([its README](fetch/README.md)) |

**Live:** <https://fetch-food-ordering-joe-daniels-projects-5d434b85.vercel.app>
(the Review 1 deck is at `/deck`). Demo logins are one click on the sign-in page,
password `fetch123`.

## The project in one line

A multi-restaurant food ordering system with four roles (customer, restaurant,
rider, admin) and two things the big delivery apps don't do: a **cart your friends
join from their own phones**, and a **delivery time computed from the kitchen's real
queue** instead of a flat guess.

## Running it locally (XAMPP)

1. Start Apache and MySQL in the XAMPP manager.
2. Copy `fetch/` into `htdocs/`.
3. Open `http://localhost/fetch/install.php` → **Install now**.
4. Open `http://localhost/fetch/` — demo logins are on the sign-in page, password `fetch123`.

Full details, demo script and the course-topic map are in [`fetch/README.md`](fetch/README.md).

## Running it on the web (Vercel)

The app is deployed from this repository. Two pieces make that work:

- **`vercel.json`** maps every `fetch/**/*.php` file to the community PHP runtime,
  and serves the presentation page at `/` with the app at `/fetch`.
- **A cloud MySQL** (TiDB Serverless) holds the data, because Vercel is serverless
  and has no database of its own.

Serverless also has no persistent disk, which changes two things, both handled in code:

| Problem | Fix |
| --- | --- |
| Each request can hit a different machine, so file-based sessions lose the login | Sessions are stored in MySQL (`lib/session.php`), switched on automatically when `VERCEL` is set |
| The filesystem is read-only, so dish photo uploads fail | `save_upload()` detects it and the dish keeps its generated gradient tile |

Configuration comes from environment variables — see [`fetch/.env.example`](fetch/.env.example).
Nothing secret is committed.

```
DB_HOST  DB_PORT  DB_NAME  DB_USER  DB_PASS  DB_SSL=1
DB_SESSIONS=1   DEMO_MODE=1   INSTALL_KEY=<secret>
```

Every push to `main` redeploys automatically. To reset the live demo data, visit
`/install.php?key=<INSTALL_KEY>` — the key is in the project's environment variables on
Vercel, and without it the installer refuses to run.

### Two deployment details worth knowing

- **One function, not thirty.** Vercel only runs PHP from `/api` and limits how many
  functions a deployment may have, so [`api/index.php`](api/index.php) is a front
  controller: it maps the requested path to the matching page inside `fetch/` and
  includes it. Apache never uses this file.
- **Region matters.** The function runs in `sin1` (Singapore) because the database does.
  With the function in Washington, every page crossed the Pacific and took six seconds;
  next to the database it is under a second.
