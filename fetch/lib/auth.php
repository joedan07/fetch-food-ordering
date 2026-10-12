<?php
/** Sessions, roles and the guards every protected page calls first. */

function current_user(): ?array
{
    static $cached = false, $user = null;
    if ($cached) {
        return $user;
    }
    $cached = true;
    if (empty($_SESSION['uid'])) {
        return null;
    }
    $user = q1('SELECT * FROM users WHERE id = ?', [$_SESSION['uid']]);
    return $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function user_role(): string
{
    return current_user()['role'] ?? 'guest';
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = $user['id'];
    unset($_SESSION['guest_name']);
}

function logout_user(): void
{
    $_SESSION = [];
    session_destroy();
}

function attempt_login(string $email, string $password): ?array
{
    $user = q1('SELECT * FROM users WHERE email = ?', [strtolower(trim($email))]);
    if ($user && password_verify($password, $user['password_hash'])) {
        login_user($user);
        return $user;
    }
    return null;
}

/** Where each role lands after signing in. */
function home_for(string $role): string
{
    return [
        'admin'      => 'admin/index.php',
        'restaurant' => 'r/index.php',
        'rider'      => 'rider/index.php',
    ][$role] ?? 'index.php';
}

function require_login(): array
{
    if (!is_logged_in()) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? null;
        flash('Please sign in to continue.', 'warn');
        redirect('login.php');
    }
    return current_user();
}

/** Guard a page to one or more roles. */
function require_role(string ...$roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        flash('That area is for ' . implode(' / ', $roles) . ' accounts.', 'err');
        redirect(home_for($user['role']));
    }
    return $user;
}

/** The restaurant a restaurant-owner account manages. */
function my_restaurant(): array
{
    $user = require_role('restaurant');
    $r = q1('SELECT * FROM restaurants WHERE id = ?', [$user['restaurant_id']]);
    if (!$r) {
        exit('This account is not linked to a restaurant yet.');
    }
    return $r;
}

/**
 * A name for the person using a group cart — their account name, or the
 * nickname a guest typed when they joined with the share link.
 */
function actor_name(): ?string
{
    return current_user()['name'] ?? guest_name();
}
