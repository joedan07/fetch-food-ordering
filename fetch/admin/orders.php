<?php
/** Admin — every order on the platform, with the promise kept or missed. */
require_once __DIR__ . '/../config.php';
require_role('admin');

$filter = $_GET['status'] ?? 'all';
$search = trim($_GET['q'] ?? '');

$sql = "SELECT o.*, r.name AS restaurant, u.name AS customer, d.name AS rider
          FROM orders o
          JOIN restaurants r ON r.id = o.restaurant_id
          JOIN users u       ON u.id = o.user_id
     LEFT JOIN users d       ON d.id = o.rider_id
         WHERE 1";
$par = [];
if ($filter !== 'all') { $sql .= ' AND o.status = ?';                        $par[] = $filter; }
if ($search !== '')    { $sql .= ' AND (o.code LIKE ? OR u.name LIKE ?)';    $par[] = "%$search%"; $par[] = "%$search%"; }
$rows = q($sql . ' ORDER BY o.created_at DESC LIMIT 200', $par);

page_head('Orders — Admin');
topbar();
?>
<div class="shell">
  <?php dash_side('admin', 'orders'); ?>
  <main class="main">
    <div class="page-head">
      <div><div class="mono-k"><?= count($rows) ?> shown</div><h1 class="h1" style="margin-top:6px">Orders.</h1></div>
      <form class="row gap" method="get">
        <input class="input" name="q" placeholder="Order code or customer" value="<?= e($search) ?>" style="min-height:40px">
        <input type="hidden" name="status" value="<?= e($filter) ?>">
        <button class="btn btn--ghost btn--sm">Search</button>
      </form>
    </div>

    <div class="cat-nav mb">
      <?php foreach (['all' => 'All', 'placed' => 'New', 'preparing' => 'Cooking', 'ready' => 'Ready', 'out_for_delivery' => 'On the road', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'] as $k => $l): ?>
        <a href="?status=<?= $k ?>&q=<?= urlencode($search) ?>" style="<?= $filter === $k ? 'border-color:var(--ember);color:var(--ember)' : '' ?>"><?= $l ?></a>
      <?php endforeach; ?>
    </div>

    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Order</th><th>Restaurant</th><th>Customer</th><th>Rider</th><th>Total</th><th>Promise</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $o): $p = promise_state($o); ?>
          <tr>
            <td class="num"><?= e($o['code']) ?><div class="xs dim"><?= date('j M, g:i a', strtotime($o['created_at'])) ?></div></td>
            <td><?= e($o['restaurant']) ?></td>
            <td><?= e($o['customer']) ?><?= $o['is_group'] ? ' <span class="badge">group</span>' : '' ?></td>
            <td class="sm dim"><?= e($o['rider'] ?? '—') ?></td>
            <td class="num"><?= money((float) $o['total']) ?></td>
            <td class="num">
              <?php if ($p['done'] && $p['minutes'] !== null): ?>
                <span style="color:var(--<?= $p['late'] ? 'chilli' : 'basil' ?>)"><?= $p['minutes'] ?> / <?= $p['promised'] ?></span>
              <?php else: ?><span class="dim"><?= $p['promised'] ?> min</span><?php endif; ?>
            </td>
            <td><span class="badge <?= $o['status'] === 'delivered' ? 'badge--ok' : ($o['status'] === 'cancelled' ? 'badge--err' : 'badge--ember') ?>"><?= e(status_label($o['status'])) ?></span></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="7"><div class="empty"><b>No orders match</b></div></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>
</div>
<?php page_foot(); ?>
