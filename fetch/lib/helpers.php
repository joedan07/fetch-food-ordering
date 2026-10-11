<?php
/** Small helpers used across every page. */

/** Escape anything before it reaches the page. */
function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function money(float $n): string
{
    return '₹' . number_format($n, ($n == (int) $n) ? 0 : 2);
}

function redirect(string $path): never
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : base_url($path)));
    exit;
}

/** Send a JSON response and stop. */
function json_out(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Read a JSON request body (our fetch() calls post JSON). */
function json_in(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '[]', true);
    return is_array($data) ? $data : [];
}

// ---- CSRF ------------------------------------------------------------------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_check(): void
{
    $sent = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? '');
    if (!hash_equals($_SESSION['csrf'] ?? '', (string) $sent)) {
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'json') || !empty($_SERVER['HTTP_X_CSRF'])) {
            json_out(['error' => 'Session expired, please reload the page.'], 419);
        }
        http_response_code(419);
        exit('Session expired — go back and reload the page.');
    }
}

// ---- flash messages --------------------------------------------------------
function flash(string $msg, string $type = 'ok'): void
{
    $_SESSION['flash'][] = ['msg' => $msg, 'type' => $type];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

// ---- display ---------------------------------------------------------------
const ORDER_STATUSES = ['placed', 'accepted', 'preparing', 'ready', 'out_for_delivery', 'delivered'];

function status_label(string $s): string
{
    return [
        'placed'           => 'Placed',
        'accepted'         => 'Accepted',
        'preparing'        => 'Preparing',
        'ready'            => 'Ready',
        'out_for_delivery' => 'Out for delivery',
        'delivered'        => 'Delivered',
        'cancelled'        => 'Cancelled',
    ][$s] ?? ucfirst($s);
}

/** A stable pair of hues per dish, so every card gets its own gradient tile. */
function food_gradient(string $seed): string
{
    $h = crc32($seed);
    $a = $h % 360;
    $b = ($a + 38) % 360;
    return "linear-gradient(135deg, hsl($a 70% 46%), hsl($b 80% 34%))";
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $out .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $out ?: '?';
}

function time_ago(string $ts): string
{
    $diff = time() - strtotime($ts);
    if ($diff < 60)    return $diff . 's ago';
    if ($diff < 3600)  return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    return date('j M', strtotime($ts));
}

/** Minutes left until $ts (negative when it has already passed). */
function minutes_until(?string $ts): ?int
{
    return $ts === null ? null : (int) round((strtotime($ts) - time()) / 60);
}

/** Handle an uploaded dish photo. Returns the stored filename, or null. */
function save_upload(string $field): ?string
{
    if (empty($_FILES[$field]['name']) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    // Serverless hosts have a read-only filesystem; dishes then fall back to
    // the generated gradient tile, which is why nothing here is required.
    if (!is_writable(ROOT_PATH . '/uploads')) {
        flash('Photo uploads are turned off on this host — the dish keeps its generated tile.', 'warn');
        return null;
    }
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $mime = mime_content_type($_FILES[$field]['tmp_name']) ?: '';
    if (!isset($allowed[$mime]) || $_FILES[$field]['size'] > 3 * 1024 * 1024) {
        return null;
    }
    $name = bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    move_uploaded_file($_FILES[$field]['tmp_name'], ROOT_PATH . '/uploads/' . $name);
    return $name;
}
