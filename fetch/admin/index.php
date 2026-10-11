<?php
/** Admin — the platform at a glance. */
require_once __DIR__ . '/../config.php';
$user = require_role('admin');

$t = q1("SELECT COUNT(*) orders, COALESCE(SUM(total),0) gmv,
                COALESCE(SUM(total * (SELECT commission_pct FROM restaurants r WHERE r.id = o.restaurant_id) / 100),0) commission
           FROM orders o WHERE status <> 'cancelled'");
$p = q1("SELECT COUNT(*) done, SUM(TIMESTAMPDIFF(MINUTE, created_at, delivered_at) <= promised_min) on_time
           FROM orders WHERE status = 'delivered'");
$onTime = $p['done'] ? round($p['on_time'] / $p['done'] * 100) : null;

$counts = [
    'restaurants' => (int) q1('SELECT COUNT(*) c FROM restaurants WHERE is_approved = 1')['c'],
    'pending'     => (int) q1('SELECT COUNT(*) c FROM restaurants WHERE is_approved = 0')['c'],
    'customers'   => (int) q1("SELECT COUNT(*) c FROM users WHERE role = 'customer'")['c'],
    'riders'      => (int) q1("SELECT COUNT(*) c FROM users WHERE role = 'rider'")['c'],
    'live'        => (int) q1("SELECT COUNT(*) c FROM orders WHERE status IN ('placed','accepted','preparing','ready','out_for_delivery')")['c'],
    'groups'      => (int) q1('SELECT COUNT(*) c FROM orders WHERE is_group = 1')['c'],
];

$days = q("SELECT DATE(created_at) d, COUNT(*) n, COALESCE(SUM(total),0) rev FROM orders
            WHERE status <> 'cancelled' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            GROUP BY DATE(created_at) ORDER BY d");
$max = max(1, max(array_column($days, 'rev') ?: [1]));

$board = q(
    "SELECT r.id, r.name, r.is_open,
            (SELECT COUNT(*) FROM orders o WHERE o.restaurant_id = r.id AND o.status <> 'cancelled') orders,
            (SELECT COALESCE(SUM(total),0) FROM orders o WHERE o.restaurant_id = r.id AND o.status <> 'cancelled') rev,
            (SELECT ROUND(AVG(stars),1) FROM reviews v WHERE v.restaurant_id = r.id) rating,
            (SELECT COUNT(*) FROM orders o WHERE o.restaurant_id = r.id AND o.status = 'delivered') done,
            (SELECT COUNT(*) FROM orders o WHERE o.restaurant_id = r.id AND o.status = 'delivered'
                AND TIMESTAMPDIFF(MINUTE, o.created_at, o.delivered_at) <= o.promised_min) kept
       FROM restaurants r WHERE r.is_approved = 1 ORDER BY rev DESC"
);

page_head('Admin overview');
topbar();
?>
<div class="shell">
  <?php dash_side('admin', 'overview'); ?>
  <main class="main">
    <div class="page-head">
      <div><div class="mono-k">Platform</div><h1 class="h1" style="margin-top:6px">Everything, <span class="serif">at once.</span></h1></div>
      <?php if ($counts['pending']): ?>
        <a class="btn btn--primary" href="<?= base_url('admin/restaurants.php') ?>"><?= $counts['pending'] ?> waiting for approval</a>
      <?php endif; ?>
    </div>

    <div class="stats">
      <div class="stat"><b data-count="<?= (int) $t['orders'] ?>"></b><span>Orders</span></div>
      <div class="stat ok"><b data-count="<?= (int) $t['gmv'] ?>" data-prefix="₹"></b><span>Gross value</span></div>
      <div class="stat ember"><b data-count="<?= round((float) $t['commission']) ?>" data-prefix="₹"></b><span>Platform commission</span></div>
      <div class="stat <?= $onTime !== null && $onTime >= 85 ? 'ok' : 'warn' ?>"><b<?= $onTime === null ? '' : ' data-count="' . $onTime . '" data-suffix="%"' ?>><?= $onTime === null ? '—' : '' ?></b><span>Delivered on time</span></div>
    </div>
    <div class="stats">
      <div class="stat"><b data-count="<?= $counts['live'] ?>"></b><span>Orders in flight</span></div>
      <div class="stat"><b data-count="<?= $counts['restaurants'] ?>"></b><span>Live restaurants</span></div>
      <div class="stat"><b data-count="<?= $counts['customers'] ?>"></b><span>Customers</span></div>
      <div class="stat"><b data-count="<?= $counts['groups'] ?>"></b><span>Group orders</span></div>
    </div>

    <div class="two-col">
      <div class="card">
        <h3 class="h3">Platform revenue, last 7 days</h3>
        <div class="bars-wrap">
          <div class="bars">
            <?php foreach ($days as $d): ?>
              <div style="height:<?= max(3, (int) ($d['rev'] / $max * 100)) ?>%"><b><?= money((float) $d['rev']) ?></b><span><?= date('D', strtotime($d['d'])) ?></span></div>
            <?php endforeach; ?>
          </div>
        </div>

        <h3 class="h3 mt-lg mb">Restaurants by revenue</h3>
        <div class="table-wrap" style="border:0">
          <table class="table">
            <thead><tr><th>Restaurant</th><th>Orders</th><th>Revenue</th><th>On time</th><th>Rating</th><th>State</th></tr></thead>
            <tbody>
            <?php foreach ($board as $b): $pct = $b['done'] ? round($b['kept'] / $b['done'] * 100) : null; ?>
              <tr>
                <td><b><?= e($b['name']) ?></b></td>
                <td class="num"><?= (int) $b['orders'] ?></td>
                <td class="num"><?= money((float) $b['rev']) ?></td>
                <td class="num" style="color:var(--<?= $pct !== null && $pct >= 85 ? 'basil' : 'saffron' ?>)"><?= $pct === null ? '—' : $pct . '%' ?></td>
                <td class="num"><?= $b['rating'] ?: '—' ?></td>
                <td><span class="badge <?= $b['is_open'] ? 'badge--ok' : '' ?>"><?= $b['is_open'] ? 'Open' : 'Closed' ?></span></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <aside class="sticky">
        <div class="card">
          <div class="row between mb"><h3 class="h3">Kitchens right now</h3><span class="badge badge--ok"><span class="dot live"></span>live</span></div>
          <div id="loads"></div>
        </div>
        <div class="card mt">
          <h3 class="h3 mb">In flight</h3>
          <div id="live"></div>
        </div>
      </aside>
    </div>
  </main>
</div>

<script>
async function refresh() {
  const d = await apiGet('/api/admin_feed.php');
  setHTML(document.getElementById('loads'), d.kitchens.map((k) => `
    <div class="load load--mini ${k.level}" style="margin-bottom:8px">
      <span class="load__dial" style="--pct:${k.load_pct}"><b></b></span>
      <span class="load__txt">${k.name} · ${k.label} · ${k.queue_wait} min queue</span>
    </div>`).join(''));
  setHTML(document.getElementById('live'), d.live.length ? d.live.map((o) => `
    <div class="split-row">
      <span><b class="mono">${o.code}</b> <span class="xs dim">${o.restaurant} · ${o.status}</span></span>
      <b class="${o.late ? 'late' : ''}">${o.late ? Math.abs(o.left) + 'm late' : o.left + 'm'}</b>
    </div>`).join('') : '<p class="sm dim">Nothing cooking.</p>');
}
refresh();
poll(refresh, 5000);
</script>
<?php page_foot(); ?>
