<?php
/**
 * Front controller for the Vercel deployment.
 *
 * Vercel only runs PHP files that live in /api, and a plan only allows a
 * handful of serverless functions — so instead of publishing thirty lambdas,
 * every request lands here and this file hands it to the right page inside
 * ../fetch. Locally under XAMPP this file is never used: Apache serves the
 * pages directly.
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// "/" and "/r/" style URLs mean the index page of that folder.
if ($path === '/' || str_ends_with($path, '/')) {
    $path .= 'index.php';
}
if (!str_ends_with($path, '.php')) {
    $path .= '.php';
}

$appRoot = realpath(__DIR__ . '/../fetch');
$file    = realpath($appRoot . $path);

// Only real pages, and only inside the app folder: no traversal, and nothing
// from lib/ or sql/, which are includes rather than pages.
$isPage = $file
    && str_starts_with($file, $appRoot . DIRECTORY_SEPARATOR)
    && is_file($file)
    && !preg_match('#^' . preg_quote($appRoot . DIRECTORY_SEPARATOR, '#') . '(lib|sql|uploads)[/\\\\]#', $file);

if (!$isPage) {
    http_response_code(404);
    exit('<!doctype html><meta charset="utf-8"><title>404</title>'
        . '<div style="font:16px/1.6 system-ui;max-width:420px;margin:18vh auto;padding:28px;background:#1a1612;'
        . 'color:#f7efe5;border-radius:18px;border:1px solid rgba(255,90,31,.35)">'
        . '<h2 style="margin:0 0 8px;font-size:20px">Page not found</h2>'
        . '<p style="color:#c9bdae;margin:0 0 14px">Nothing lives at this address.</p>'
        . '<a href="/" style="color:#ff5a1f">← Back to Fetch</a></div>');
}

// The app works out its own links from SCRIPT_NAME, so make it look like the
// page was requested directly, which is exactly what happens under Apache.
$_SERVER['SCRIPT_NAME']     = $path;
$_SERVER['SCRIPT_FILENAME'] = $file;
$_SERVER['PHP_SELF']        = $path;

chdir(dirname($file));
require $file;
