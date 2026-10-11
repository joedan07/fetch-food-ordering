<?php
/** Insights: sales, the promise we keep, and what people actually order. */
require_once __DIR__ . '/../config.php';
$user = require_role('restaurant');
$r    = my_restaurant();
$rid  = (int) $r['id'];

$totals = q1(
    "SELECT COUNT(*) orders, COALESCE(SUM(total),0) revenue, COALESCE(AVG(total),0) avg_order
       FROM orders WHERE restaurant_id = ? AND status <> 'cancelled'",
    [$rid]
);
$promise = q1(
    "SELECT COUNT(*) done,
            SUM(TIMESTAMPDIFF(MINUTE, created_at, delivered_at) <= promised_min) on_time,
            ROUND(AVG(TIMESTAMPDIFF(MINUTE, created_at, delivered_at))) avg_actual,
            ROUND(AVG(promised_min)) avg_promised
       FROM orders WHERE restaurant_id = ? AND status = 'delivered'",
    [$rid]
);
$onTimePct = $promise['done'] ? round($promise['on_time'] / $promise['done'] * 100) : null;

$days = q(
    "SELECT DATE(created_at) d, COUNT(*) n, COALESCE(SUM(total),0) rev
       FROM orders WHERE restaurant_id = ? AND status <> 'cancelled' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
      GROUP BY DATE(created_at) ORDER BY d",
    [$rid]
);
$max = max(1, max(array_column($days, 'rev') ?: [1]));

$top = q(
    "SELECT oi.name, SUM(oi.qty) sold, SUM(oi.qty * oi.unit_price) earned
       FROM order_items oi JOIN orders o ON o.id = oi.order_id
      WHERE o.restaurant_id = ? AND o.status <> 'cancelled'
      GROUP BY oi.name ORDER BY sold DESC LIMIT 6",
    [$rid]
);
$rating = q1('SELECT ROUND(AVG(stars),2) avg, COUNT(*) n FROM reviews WHERE restaurant_id = ?', [$rid]);
$recent = q('SELECT v.*, u.name FROM reviews v JOIN users u ON u.id = v.user_id WHERE v.restaurant_id = ? ORDER BY v.created_at DESC LIMIT 5', [$rid]);

page_head('Insights — ' . $r['name']);
topbar();
?>
<div class="shell">
  <?php dash_side('restaurant', 'insights'); ?>
  <main class="main">
    <div class="page-head"><div><div class="mono-k">Last 7 days</div><h1 class="h1" style="margin-top:6px">How it's <span class="serif">going.</span></h1></div></div>

    <div class="stats">
      <div class="stat"><b data-count="<?= (int) $totals['orders'] ?>"></b><span>Orders all time</span></div>
      <div class="stat ok"><b data-count="<?= (int) $totals['revenue'] ?>" data-prefix="₹"></b><span>Revenue</span></div>
      <div class="stat"><b data-count="<?= round((float) $totals['avg_order']) ?>" data-prefix="₹"></b><span>Average order</span></div>
      <div class="stat <?= $onTimePct !== null && $onTimePct >= 85 ? 'ok' : 'warn' ?>">
        <b<?= $onTimePct === null ? '' : ' data-count="' . $onTimePct . '" data-suffix="%"' ?>><?= $onTimePct === null ? '—' : '' ?></b><span>Delivered on time</span>
        <?php if ($promise['done']): ?><div class="delta dim"><?= (int) $promise['avg_actual'] ?> min actual vs <?= (int) $promise['avg_promised'] ?> promised</div><?php endif; ?>
      </div>
    </div>

    <div class="two-col">
      <div class="card">
        <h3 class="h3">Revenue by day</h3>
        <div class="bars-wrap">
          <div class="bars">
            <?php foreach ($days as $d): ?>
              <div style="height:<?= max(3, (int) ($d['rev'] / $max * 100)) ?>%">
                <b><?= money((float) $d['rev']) ?></b>
                <span><?= date('D', strtotime($d['d'])) ?></span>
              </div>
            <?php endforeach; ?>
            <?php if (!$days): ?><div style="height:3%"><span>no data</span></div><?php endif; ?>
          </div>
        </div>

        <h3 class="h3 mt-lg mb">Most ordered</h3>
        <table class="table">
          <thead><tr><th>Dish</th><th>Plates</th><th>Earned</th></tr></thead>
          <tbody>
          <?php foreach ($top as $t): ?>
            <tr><td><?= e($t['name']) ?></td><td class="num"><?= (int) $t['sold'] ?></td><td class="num"><?= money((float) $t['earned']) ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <aside>
        <div class="card">
          <h3 class="h3">The promise</h3>
          <p class="sm muted mt">Every order carries the time we quoted. This is how often the kitchen kept it.</p>
          <div class="countdown mt" style="color:var(--<?= $onTimePct !== null && $onTimePct >= 85 ? 'basil' : 'saffron' ?>)">
            <?= $onTimePct === null ? '—' : $onTimePct ?><small>% on time</small>
          </div>
          <p class="xs dim">Out of <?= (int) $promise['done'] ?> delivered orders.
            Raise <b>orders cooked at once</b> in Settings if the queue is quoting too long.</p>
        </div>

        <div class="card mt">
          <h3 class="h3 mb">Ratings <?= $rating['n'] ? '· ' . $rating['avg'] . ' ★' : '' ?></h3>
          <?php foreach ($recent as $v): ?>
            <div class="split-row" style="display:block">
              <div class="row between">
                <b><?= e($v['name']) ?></b>
                <span><?= str_repeat('★', (int) $v['stars']) ?><span class="dim"><?= str_repeat('★', 5 - (int) $v['stars']) ?></span></span>
              </div>
              <?php if ($v['comment']): ?><div class="sm muted"><?= e($v['comment']) ?></div><?php endif; ?>
              <div class="xs dim"><?= $v['on_time'] ? 'on time' : 'ran late' ?> · <?= time_ago($v['created_at']) ?></div>
            </div>
          <?php endforeach; ?>
          <?php if (!$recent): ?><p class="sm dim">No ratings yet.</p><?php endif; ?>
        </div>
      </aside>
    </div>
  </main>
</div>
<?php page_foot(); ?>
