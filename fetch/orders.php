<?php
/** A customer's order history, newest first. */
require_once __DIR__ . '/config.php';
$user = require_role('customer');

$rows = q(
    'SELECT o.*, r.name AS restaurant,
            (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS lines_count,
            (SELECT stars FROM reviews v WHERE v.order_id = o.id)          AS stars
       FROM orders o JOIN restaurants r ON r.id = o.restaurant_id
      WHERE o.user_id = ? ORDER BY o.created_at DESC',
    [$user['id']]
);
$live = array_filter($rows, fn($o) => !in_array($o['status'], ['delivered', 'cancelled'], true));

page_head('My orders');
topbar('orders');
?>
<main class="wrap section">
  <h1 class="h1">Your <span class="serif">orders.</span></h1>
  <p class="muted mt">Everything you've ordered, and how close we got to the time we promised.</p>

  <?php if ($live): ?>
    <div class="mono-k mt-lg mb">In progress</div>
    <div class="r-grid">
      <?php foreach ($live as $o): $p = promise_state($o); ?>
        <a class="card r-card" href="<?= base_url('order.php?id=' . $o['id']) ?>" style="padding:20px">
          <div class="row between gap">
            <b class="mono"><?= e($o['code']) ?></b>
            <span class="badge badge--ember"><span class="dot live"></span><?= e(status_label($o['status'])) ?></span>
          </div>
          <h3 class="h3 mt"><?= e($o['restaurant']) ?></h3>
          <div class="countdown <?= $p['late'] ? 'late' : '' ?>" style="font-size:34px;margin-top:10px">
            <?= $p['minutes'] === null ? '—' : abs($p['minutes']) ?><small>min<?= $p['late'] ? ' late' : ' left' ?></small>
          </div>
          <p class="sm dim mt"><?= (int) $o['lines_count'] ?> items · <?= money((float) $o['total']) ?></p>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="mono-k mt-lg mb">History</div>
  <?php if (!$rows): ?>
    <div class="empty"><b>No orders yet</b>Your first one is a click away.
      <div class="mt"><a class="btn btn--primary" href="<?= base_url('index.php') ?>">Browse restaurants</a></div>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Order</th><th>Restaurant</th><th>Items</th><th>Total</th><th>Promise</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $o):
            $p = promise_state($o);
            $kept = $p['done'] && !$p['late'];
        ?>
          <tr>
            <td class="num"><?= e($o['code']) ?><div class="xs dim"><?= time_ago($o['created_at']) ?></div></td>
            <td><?= e($o['restaurant']) ?><?= $o['is_group'] ? ' <span class="badge">group</span>' : '' ?></td>
            <td class="num"><?= (int) $o['lines_count'] ?></td>
            <td class="num"><?= money((float) $o['total']) ?></td>
            <td class="num">
              <?php if ($p['done'] && $p['minutes'] !== null): ?>
                <span style="color:var(--<?= $kept ? 'basil' : 'chilli' ?>)"><?= $p['minutes'] ?> / <?= $p['promised'] ?> min</span>
              <?php else: ?>
                <span class="dim"><?= $p['promised'] ?> min</span>
              <?php endif; ?>
            </td>
            <td><span class="badge <?= $o['status'] === 'delivered' ? 'badge--ok' : ($o['status'] === 'cancelled' ? 'badge--err' : 'badge--ember') ?>"><?= e(status_label($o['status'])) ?></span></td>
            <td class="right"><a class="btn btn--ghost btn--sm" href="<?= base_url('order.php?id=' . $o['id']) ?>">Open</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</main>
<?php page_foot(); ?>
