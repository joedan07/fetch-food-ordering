# Fetch — Online Food Ordering System

Web Technologies PBL · Semester 3

A multi-restaurant food ordering platform built on the course stack: **HTML5, CSS3,
JavaScript (DOM + fetch), PHP 8, MySQL, XML + XSD** — no frameworks, no build step.

```
browser  ──HTTP──▶  Apache + PHP 8  ──SQL──▶  MySQL
 (HTML/CSS/JS)        (sessions,              (11 tables)
                       validation,
                       business logic)
```

## The two USPs

**1 · Live group cart.** A cart lives in the database, not in the browser, and every
line in it remembers who added it. Share a six-letter code and your friends — logged in
or not — open the same cart on their own phones, add their own food, and watch it update
live. The bill then splits itself: food goes to whoever ordered it, delivery is shared
equally. *(`lib/cart.php`, `api/cart.php`, `cart.php`, `group.php`)*

**2 · Honest ETA from kitchen load.** No flat "30–35 min". Every dish carries its own
prep time; the server adds up the cooking still on the stoves, divides by how many orders
that kitchen can cook at once, and quotes:

```
wait = queue (pending work ÷ capacity) + cook (slowest dish + extras) + ride
```

Customers see a live busy meter *before* they order, the quote is stored on the order as
a promise, and every dashboard measures the kitchen against it. *(`lib/eta.php`)*

## Four roles

| Role | Where | What they get |
| --- | --- | --- |
| Customer | `index.php` | Restaurants with live busy meters, menu, cart, group cart, checkout, live tracking, ratings |
| Restaurant | `r/` | Live ticket board, order history, menu CRUD with photo upload, XML menu import, insights, capacity settings |
| Rider | `rider/` | Unclaimed ready orders, claim, pick up, deliver |
| Admin | `admin/` | Platform stats and commission, approve/suspend restaurants, onboard restaurants and riders, all orders, all users |

## Where it runs

| | |
| --- | --- |
| Live | <https://fetch-food-ordering-joe-daniels-projects-5d434b85.vercel.app> |
| Local | XAMPP, as set up below |

Both read the same code; the live copy keeps sessions in MySQL and has photo uploads
switched off, because serverless hosts have no persistent disk.

## Setup

1. Start **Apache** and **MySQL** in the XAMPP manager.
2. Copy the `fetch` folder into `/Applications/XAMPP/xamppfiles/htdocs/`
   (Windows: `C:\xampp\htdocs\`).
3. Open <http://localhost/fetch/install.php> and press **Install now** — it creates the
   `fetch_db` database, the tables and the demo data.
4. Open <http://localhost/fetch/>.

**Without copying into htdocs** (handy while developing) you can serve the folder directly
with PHP's built-in server — MySQL still has to be running in XAMPP:

```bash
/Applications/XAMPP/xamppfiles/bin/php -S 127.0.0.1:8787 -t "/Users/joedaniel/Uni/SEM 3/WebTech/PBL/fetch"
```

then open <http://127.0.0.1:8787/>.

Database credentials live at the top of `config.php` (default XAMPP: `root`, no password).

### Demo logins — password `fetch123`

| Role | Email |
| --- | --- |
| Customer | `joe@fetch.test` |
| Restaurant | `spice@fetch.test` (also `dosa@`, `wok@`, `brew@`) |
| Rider | `ravi@fetch.test`, `sana@fetch.test` |
| Admin | `admin@fetch.test` |

The sign-in page has one-click buttons for all four.

## Demo script for the review (about 4 minutes)

1. **Landing** — point at the busy meters: different kitchens, different waits, updating live.
2. **Menu** — add two dishes; the sidebar shows *queue + cooking + ride* adding up.
3. **Group cart** — press *Share this cart*, open the link on a second laptop or phone,
   join as a friend, add a dish. Both screens update within three seconds and the split
   appears. **This is USP 1.**
4. **Checkout** — the promised time is saved with the order.
5. **Kitchen console** (second browser, `spice@fetch.test`) — the ticket appears; accept
   and cook it. Watch the customer's timeline move on its own.
6. **Rider** (`ravi@fetch.test`) — claim it, deliver it.
7. **Insights / Admin** — on-time percentage measured against the promise. **USP 2 closed.**

Tip: use two different browsers (or one normal + one private window) so the two sessions
don't share cookies.

## Course topics, and where to find them

| Topic | Where |
| --- | --- |
| HTML5 semantics, forms, client validation | every page; `register.php`, `checkout.php` |
| CSS3 Grid, Flexbox, custom properties, media queries, keyframes | `assets/css/app.css` |
| JavaScript DOM + `fetch()` + polling | `assets/js/app.js`, `cart.php`, `r/index.php` |
| `localStorage` | theme preference (`app.js`) |
| XML + XSD + DOM parsing | `r/import.php`, `sql/menu.xsd`, `sql/menu-sample.xml` |
| JSON over HTTP | everything in `api/` |
| PHP sessions, cookies, role guards | `lib/auth.php` |
| File upload | `save_upload()` in `lib/helpers.php`, used in `r/menu.php` |
| MySQL, prepared statements, transactions, joins, aggregates | `lib/db.php`, `checkout.php`, `r/insights.php` |

## Security

- Passwords stored with `password_hash()` / verified with `password_verify()`.
- Every query is a prepared statement (`lib/db.php`) — no SQL built from user input.
- Every output goes through `e()` (`htmlspecialchars`) — no raw echo of user data.
- CSRF token on every POST form and every JSON endpoint.
- Prices and availability are re-read from the database at checkout; the browser's
  numbers are never trusted.
- Role guards (`require_role()`) on every dashboard page and API endpoint.
- Uploads are limited by MIME type and size, and renamed before being stored.

## Files

```
fetch/
├── config.php            database + app settings
├── install.php           one-click installer
├── index.php             restaurant list (live busy meters)
├── restaurant.php        menu + cart sidebar + live ETA
├── cart.php group.php    the group cart and the join page
├── checkout.php          order placement (transaction)
├── order.php orders.php  live tracking, history, ratings
├── login register logout profile
├── api/                  JSON endpoints (cart, load, order, status, kitchen, rider, admin)
├── r/                    restaurant console
├── admin/                platform console
├── rider/                delivery console
├── lib/                  db, auth, helpers, cart, eta, layout
├── sql/                  schema.sql, seed.php, menu.xsd, menu-sample.xml
└── assets/               app.css, app.js
```

## Notes

- Fonts load from Google Fonts. Offline, the pages fall back to system fonts and still
  work — if the review room has no internet and you want the exact look, download the
  four families into `assets/fonts/` and swap the `@import` at the top of `app.css`.
- Live updates use AJAX polling (3–6 seconds), which pauses while a tab is hidden.
  WebSockets would be the next step, but polling keeps the whole project inside the
  PHP/Apache stack the course covers.
