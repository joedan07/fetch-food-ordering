<?php
/**
 * Fetch — Online Food Ordering System
 * Central configuration. Everything else includes this file first.
 *
 * Locally (XAMPP) the defaults below are used. In the cloud, every value comes
 * from environment variables, so no credentials live in the repository.
 */

/** Read an environment variable, falling back to the local XAMPP default. */
function env(string $key, string $default = ''): string
{
    $v = getenv($key);
    return ($v === false || $v === '') ? $default : $v;
}

// ---- database -------------------------------------------------------------
define('DB_HOST', env('DB_HOST', '127.0.0.1'));
define('DB_PORT', (int) env('DB_PORT', '3306'));
define('DB_NAME', env('DB_NAME', 'fetch_db'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));
// Cloud MySQL (TiDB, Aiven, PlanetScale…) requires TLS; XAMPP does not.
define('DB_SSL', env('DB_SSL', '0') === '1');
define('DB_SSL_CA', __DIR__ . '/sql/isrgrootx1.pem');

// ---- app ------------------------------------------------------------------
const APP_NAME     = 'Fetch';
const DELIVERY_FEE = 25;        // flat, in rupees
const RIDE_MINUTES = 10;        // average time from kitchen door to the customer
define('DEMO_MODE', env('DEMO_MODE', '1') === '1');          // "log in as…" panel
define('INSTALL_KEY', env('INSTALL_KEY', ''));               // guards install.php when deployed
// Serverless hosts give each request a different machine, so PHP's file-based
// sessions don't survive. On those, sessions live in the database instead.
define('DB_SESSIONS', env('DB_SESSIONS', '0') === '1' || getenv('VERCEL') !== false);

date_default_timezone_set('Asia/Kolkata');

// ---- paths ----------------------------------------------------------------
define('ROOT_PATH', __DIR__);
// Works whether the app lives at http://localhost/fetch or at a domain root.
define('BASE_URL', rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/'));

function base_url(string $path = ''): string
{
    $base = BASE_URL;
    // pages inside /r, /admin, /rider and /api sit one level deeper
    foreach (['/r', '/admin', '/rider', '/api'] as $sub) {
        if (str_ends_with($base, $sub)) {
            $base = substr($base, 0, -strlen($sub));
        }
    }
    return $base . '/' . ltrim($path, '/');
}

require_once ROOT_PATH . '/lib/db.php';
require_once ROOT_PATH . '/lib/helpers.php';

// ---- session --------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    if (DB_SESSIONS) {
        require_once ROOT_PATH . '/lib/session.php';
        use_database_sessions();
    }
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

require_once ROOT_PATH . '/lib/auth.php';
require_once ROOT_PATH . '/lib/eta.php';
require_once ROOT_PATH . '/lib/cart.php';
require_once ROOT_PATH . '/lib/layout.php';
