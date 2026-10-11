<?php
/** Shared page chrome: <head>, the top bar, dashboard sidebars and the footer. */

function icon(string $name, string $cls = ''): string
{
    $p = [
        'home'    => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/>',
        'grid'    => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'bag'     => '<path d="M6 7h12l1 13H5L6 7z"/><path d="M9 7V5a3 3 0 0 1 6 0v2"/>',
        'fire'    => '<path d="M12 3s5 4.5 5 9a5 5 0 0 1-10 0c0-1.6.7-3 1.6-4.2.3 1.2 1 2.2 1.9 2.2 1.2 0 1.5-1.2 1.5-3 0-1.5 0-3-1-4z"/>',
        'clock'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
        'users'   => '<circle cx="9" cy="8" r="3.2"/><path d="M3 20a6 6 0 0 1 12 0"/><path d="M16 5.5a3 3 0 0 1 0 5.6M17.5 20a5.6 5.6 0 0 0-2-4"/>',
        'store'   => '<path d="M4 9.5 5.5 4h13L20 9.5"/><path d="M4 9.5a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 4 2.2V20H4v-8.3A3 3 0 0 0 4 9.5z"/>',
        'list'    => '<path d="M8 6h13M8 12h13M8 18h13M3.5 6h.01M3.5 12h.01M3.5 18h.01"/>',
        'chart'   => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'bike'    => '<circle cx="5.5" cy="17" r="3.5"/><circle cx="18.5" cy="17" r="3.5"/><path d="M5.5 17 10 7h4l3 10M9 7h6"/>',
        'logout'  => '<path d="M14 4h4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-4"/><path d="M9 16l-4-4 4-4M5 12h10"/>',
        'cart'    => '<circle cx="9" cy="20" r="1.6"/><circle cx="18" cy="20" r="1.6"/><path d="M2 3h3l2.5 12h11L21 7H6"/>',
        'plus'    => '<path d="M12 5v14M5 12h14"/>',
        'check'   => '<path d="M4 12.5 9 17.5 20 6.5"/>',
        'x'       => '<path d="M6 6l12 12M18 6 6 18"/>',
        'settings'=> '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-2.7 1.1 2 2 0 1 1-4 0 1.6 1.6 0 0 0-2.7-1.1l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1A1.6 1.6 0 0 0 3 15a2 2 0 1 1 0-4 1.6 1.6 0 0 0 1.1-2.7l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1A1.6 1.6 0 0 0 9 4.6a2 2 0 1 1 4 0 1.6 1.6 0 0 0 2.7 1.1l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1A1.6 1.6 0 0 0 19.4 11a2 2 0 1 1 0 4z"/>',
        'star'    => '<path d="m12 3.5 2.6 5.4 5.9.8-4.3 4.1 1 5.9-5.2-2.8-5.2 2.8 1-5.9L3.5 9.7l5.9-.8z"/>',
    ][$name] ?? '';
    return '<svg class="' . $cls . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $p . '</svg>';
}

function page_head(string $title, string $bodyClass = ''): void
{
    $u = current_user();
    ?><!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> — <?= APP_NAME ?></title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%230b0907'/%3E%3Ctext x='6' y='23' font-family='monospace' font-weight='700' font-size='18' fill='%23fff'%3Ef%3C/text%3E%3Ccircle cx='23' cy='21' r='3.2' fill='%23ff5a1f'/%3E%3C/svg%3E">
<link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
<script>/* set the theme before paint so the page never flashes */
 try { var t = localStorage.getItem('fetch-theme'); if (t) document.documentElement.dataset.theme = t; } catch (e) {}
 window.FETCH = { base: '<?= rtrim(base_url(), '/') ?>', csrf: '<?= csrf_token() ?>', me: <?= $u ? (int) $u['id'] : 'null' ?> };
</script>
<!-- loaded here, not at the end: the pages' own inline scripts use these helpers -->
<script src="<?= base_url('assets/js/app.js') ?>"></script>
</head>
<body class="<?= e($bodyClass) ?>">
<div class="grid-bg"></div>
<div class="spot"></div>
<div id="progress"></div>
<div id="toasts"></div>
<?php
}

function topbar(string $active = ''): void
{
    $u    = current_user();
    $cart = cart_badge_count();
    ?>
<header class="topbar">
  <div class="topbar__in wrap">
    <a class="logo" href="<?= base_url('index.php') ?>"><b>fetch</b><i>(</i>'/food'<i>)</i></a>
    <nav class="navlinks">
      <a href="<?= base_url('index.php') ?>" class="<?= $active === 'home' ? 'on' : '' ?>">restaurants</a>
      <?php if (!$u || $u['role'] === 'customer'): ?>
        <a href="<?= base_url('orders.php') ?>" class="<?= $active === 'orders' ? 'on' : '' ?>">my orders</a>
        <a href="<?= base_url('group.php') ?>" class="<?= $active === 'group' ? 'on' : '' ?>">join a group cart</a>
      <?php else: ?>
        <a href="<?= base_url(home_for($u['role'])) ?>">dashboard</a>
      <?php endif; ?>
    </nav>
    <span class="spacer"></span>

    <?php if (!$u || $u['role'] === 'customer'): ?>
      <a class="icon-btn cart-btn" href="<?= base_url('cart.php') ?>" aria-label="Cart">
        <?= icon('cart') ?><?php if ($cart): ?><span class="count"><?= $cart ?></span><?php endif; ?>
      </a>
    <?php endif; ?>

    <button class="icon-btn theme-btn" onclick="toggleTheme()" aria-label="Switch theme">
      <svg class="moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
      <svg class="sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
    </button>

    <?php if ($u): ?>
      <div class="menu-wrap" id="userMenu">
        <button class="avatar" onclick="document.getElementById('userMenu').classList.toggle('open')"><?= e(initials($u['name'])) ?></button>
        <div class="menu-pop">
          <div style="padding:8px 12px">
            <b style="display:block"><?= e($u['name']) ?></b>
            <span class="mono-k"><?= e($u['role']) ?></span>
          </div>
          <hr>
          <?php if ($u['role'] === 'customer'): ?>
            <a href="<?= base_url('orders.php') ?>">My orders</a>
            <a href="<?= base_url('profile.php') ?>">Profile &amp; address</a>
          <?php else: ?>
            <a href="<?= base_url(home_for($u['role'])) ?>">Dashboard</a>
          <?php endif; ?>
          <hr>
          <a href="<?= base_url('logout.php') ?>">Sign out</a>
        </div>
      </div>
    <?php else: ?>
      <a class="btn btn--ghost btn--sm" href="<?= base_url('login.php') ?>">Sign in</a>
      <a class="btn btn--primary btn--sm" href="<?= base_url('register.php') ?>">Sign up</a>
    <?php endif; ?>
  </div>
</header>
<?php
    render_flashes();
}

/** Flash messages are handed to the JS toast system. */
function render_flashes(): void
{
    $f = take_flashes();
    if (!$f) {
        return;
    }
    echo '<script>window.addEventListener("DOMContentLoaded",()=>{';
    foreach ($f as $m) {
        echo 'toast(' . json_encode($m['msg']) . ',' . json_encode($m['type']) . ');';
    }
    echo '});</script>';
}

/** Sidebar for the staff dashboards. */
function dash_side(string $role, string $active): void
{
    $links = match ($role) {
        'restaurant' => [
            ['r/index.php', 'Kitchen', 'fire', 'kitchen'],
            ['r/orders.php', 'All orders', 'list', 'orders'],
            ['r/menu.php', 'Menu', 'bag', 'menu'],
            ['r/insights.php', 'Insights', 'chart', 'insights'],
            ['r/settings.php', 'Settings', 'settings', 'settings'],
        ],
        'admin' => [
            ['admin/index.php', 'Overview', 'grid', 'overview'],
            ['admin/restaurants.php', 'Restaurants', 'store', 'restaurants'],
            ['admin/orders.php', 'Orders', 'list', 'orders'],
            ['admin/users.php', 'Users', 'users', 'users'],
        ],
        'rider' => [
            ['rider/index.php', 'Available', 'bike', 'available'],
            ['rider/mine.php', 'My deliveries', 'list', 'mine'],
        ],
        default => [],
    };
    $badge = match ($role) {
        'restaurant' => (int) (q1("SELECT COUNT(*) c FROM orders WHERE restaurant_id = ? AND status = 'placed'", [current_user()['restaurant_id']])['c'] ?? 0),
        'admin'      => (int) (q1('SELECT COUNT(*) c FROM restaurants WHERE is_approved = 0')['c'] ?? 0),
        'rider'      => (int) (q1("SELECT COUNT(*) c FROM orders WHERE status = 'ready' AND rider_id IS NULL")['c'] ?? 0),
        default      => 0,
    };
    ?>
<aside class="side">
  <div class="side__brand"><?= e(ucfirst($role)) ?> console</div>
  <?php foreach ($links as [$href, $label, $ic, $key]): ?>
    <a href="<?= base_url($href) ?>" class="<?= $active === $key ? 'on' : '' ?>">
      <?= icon($ic) ?><?= e($label) ?>
      <?php if ($badge && $key === ($role === 'admin' ? 'restaurants' : ($role === 'rider' ? 'available' : 'kitchen'))): ?>
        <span class="tally"><?= $badge ?></span>
      <?php endif; ?>
    </a>
  <?php endforeach; ?>
  <a href="<?= base_url('logout.php') ?>" style="margin-top:14px"><?= icon('logout') ?>Sign out</a>
</aside>
<?php
}

function page_foot(): void
{
    ?>
</body>
</html>
<?php
}
