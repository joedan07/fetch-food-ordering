<?php
/** Every order this restaurant has taken. */
require_once __DIR__ . '/../config.php';
$user   = require_role('restaurant');
$r      = my_restaurant();
$filter = $_GET['status'] ?? 'all';

$sql = 'SELECT o.*, u.name AS customer FROM orders o JOIN users u ON u.id = o.user_id WHERE o.restaurant_id = ?';
$par = [$r['id']];
if ($filter !== 'all') {
    $sql .= ' AND o.status = ?';
    $par[] = $filter;
}
$rows = q($sql . ' ORDER BY o.created_at DESC LIMIT 200', $par);

page_head('Orders — ' . $r['name']);
topbar();
?>
<div class="shell">
  <?php dash_side('restaurant', 'orders'); ?>
  <main class="main">
    <div class="page-head">
      <div><div class="mono-k"><?= count($rows) ?> shown</div><h1 class="h1" style="margin-top:6px">All <span class="serif">orders.</span></h1></div>
      <div class="cat-nav">
        <?php foreach (['all' => 'All', 'placed' => 'New', 'preparing' => 'Cooking', 'out_for_delivery' => 'On the road', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'] as $k => $label): ?>
          <a href="?status=<?= $k ?>" style="<?= $filter === $k ? 'border-color:var(--ember);color:var(--ember)' : '' ?>"><?= $label ?></a>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Order</th><th>Customer</th><th>Items</th><th>Total</th><th>Promised</th><th>Actual</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $o):
            $p     = promise_state($o);
            $items = q('SELECT name, qty FROM order_items WHERE order_id = ?', [$o['id']]);
        ?>
          <tr>
            <td class="num"><?= e($o['code']) ?><div class="xs dim"><?= date('j M, g:i a', strtotime($o['created_at'])) ?></div></td>
            <td><?= e($o['customer']) ?><?= $o['is_group'] ? ' <span class="badge">group</span>' : '' ?></td>
            <td class="sm muted"><?= e(implode(', ', array_map(fn($i) => $i['qty'] . '× ' . $i['name'], $items))) ?></td>
            <td class="num"><?= money((float) $o['total']) ?></td>
            <td class="num dim"><?= (int) $o['promised_min'] ?> min</td>
            <td class="num">
              <?php if ($p['done'] && $p['minutes'] !== null): ?>
                <span style="color:var(--<?= $p['late'] ? 'chilli' : 'basil' ?>)"><?= $p['minutes'] ?> min</span>
              <?php else: ?><span class="dim">—</span><?php endif; ?>
            </td>
            <td><span class="badge <?= $o['status'] === 'delivered' ? 'badge--ok' : ($o['status'] === 'cancelled' ? 'badge--err' : 'badge--ember') ?>"><?= e(status_label($o['status'])) ?></span></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="7"><div class="empty"><b>Nothing here</b>Try another filter.</div></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>
</div>
<?php page_foot(); ?>
