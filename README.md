# Fetch — Web Technologies PBL

Two things live in this repository:

| Path | What it is |
| --- | --- |
| [`index.html`](index.html) | The Review 1 presentation — problem, solution, stack, architecture, system flow, approach |
| [`fetch/`](fetch/) | The actual application: an online food ordering platform in PHP 8 + MySQL ([its README](fetch/README.md)) |

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

After the first deploy, visit `/fetch/install.php?key=<INSTALL_KEY>` once to create the
tables and demo data.
