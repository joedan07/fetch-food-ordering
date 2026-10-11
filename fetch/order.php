<?php
/** Live order tracking: the promise, the timeline, and the receipt. */
require_once __DIR__ . '/config.php';
$user = require_login();

$id = (int) ($_GET['id'] ?? 0);
$o  = q1('SELECT o.*, r.name AS restaurant, r.phone AS restaurant_phone, u.name AS rider_name, u.phone AS rider_phone
            FROM orders o
            JOIN restaurants r ON r.id = o.restaurant_id
       LEFT JOIN users u       ON u.id = o.rider_id
           WHERE o.id = ?', [$id]);
if (!$o || ((int) $o['user_id'] !== (int) $user['id'] && $user['role'] !== 'admin')) {
    http_response_code(404);
    page_head('Not found');
    topbar();
    echo '<main class="wrap section"><div class="empty"><b>Order not found</b></div></main>';
    page_foot();
    exit;
}

// Leaving a rating.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $stars = max(1, min(5, (int) ($_POST['stars'] ?? 5)));
    $promise = promise_state($o);
    exec_sql(
        'INSERT INTO reviews (order_id, restaurant_id, user_id, stars, on_time, comment) VALUES (?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE stars = VALUES(stars), comment = VALUES(comment)',
        [$o['id'], $o['restaurant_id'], $user['id'], $stars, $promise['late'] ? 0 : 1, trim($_POST['comment'] ?? '') ?: null]
    );
    flash('Thanks for the rating.');
    redirect('order.php?id=' . $id);
}

$items   = q('SELECT * FROM order_items WHERE order_id = ?', [$id]);
$events  = q('SELECT * FROM order_events WHERE order_id = ? ORDER BY id', [$id]);
$review  = q1('SELECT * FROM reviews WHERE order_id = ?', [$id]);
$promise = promise_state($o);
$step    = (int) array_search($o['status'], ORDER_STATUSES, true);

// Per-person totals for a group order.
$split = [];
foreach ($items as $i) {
    $split[$i['member_name']] = ($split[$i['member_name']] ?? 0) + (float) $i['unit_price'] * (int) $i['qty'];
}

page_head('Order ' . $o['code']);
topbar('orders');
?>
<main class="wrap section" style="max-width:1000px">
  <div class="row between gap mb">
    <div>
      <div class="mono-k"><?= e($o['restaurant']) ?> · <?= date('j M, g:i a', strtotime($o['created_at'])) ?></div>
      <h1 class="h1" style="margin-top:8px">Order <span class="serif"><?= e($o['code']) ?></span></h1>
    </div>
    <span class="badge <?= $o['status'] === 'delivered' ? 'badge--ok' : 'badge--ember' ?>" id="statusBadge">
      <span class="dot <?= in_array($o['status'], ['delivered','cancelled'], true) ? '' : 'live' ?>"></span><?= e(status_label($o['status'])) ?>
    </span>
  </div>

  <div class="two-col">
    <div>
      <!-- ------------------------------------------- the promise, live -->
      <div class="card">
        <div class="row between gap">
          <div>
            <div class="mono-k" id="promiseLabel"><?= $promise['done'] ? 'Delivered in' : 'Arriving in about' ?></div>
            <div class="countdown <?= $promise['late'] ? 'late' : '' ?>" id="countdown">
              <?= $promise['minutes'] === null ? '—' : abs($promise['minutes']) ?><small>min<?= (!$promise['done'] && $promise['late']) ? ' late' : '' ?></small>
            </div>
            <p class="sm muted mt" id="promiseNote">
              We promised <b><?= (int) $o['promised_min'] ?> min</b> when you ordered —
              <?= (int) $o['queue_wait_min'] ?> min of kitchen queue, the rest cooking and riding.
            </p>
          </div>
          <?php if ($o['rider_name']): ?>
            <div class="card card--flat" style="min-width:190px">
              <div class="mono-k">Your rider</div>
              <b class="h3" style="display:block;margin:6px 0"><?= e($o['rider_name']) ?></b>
              <a class="sm" style="color:var(--ember)" href="tel:<?= e($o['rider_phone']) ?>"><?= e($o['rider_phone']) ?></a>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- ------------------------------------------------------ timeline -->
      <div class="card mt">
        <h3 class="h3 mb">Where it is</h3>
        <div class="track" id="track">
          <?php foreach (ORDER_STATUSES as $k => $s):
              $ev = null;
              foreach ($events as $x) {
                  if ($x['status'] === $s) { $ev = $x; }
              }
              $state = $k < $step ? 'done' : ($k === $step ? 'now' : '');
          ?>
            <div class="track__step <?= $state ?>">
              <span class="track__dot"><?= $k < $step ? '✓' : $k + 1 ?></span>
              <div>
                <div class="track__t"><?= e(status_label($s)) ?></div>
                <div class="track__s"><?= $ev ? date('g:i a', strtotime($ev['created_at'])) . ($ev['note'] ? ' · ' . e($ev['note']) : '') : '—' ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- -------------------------------------------------------- rating -->
      <?php if ($o['status'] === 'delivered'): ?>
        <div class="card mt">
          <h3 class="h3 mb"><?= $review ? 'Your rating' : 'How was it?' ?></h3>
          <?php if ($review): ?>
            <p><?= str_repeat('★', (int) $review['stars']) ?><span class="dim"><?= str_repeat('★', 5 - (int) $review['stars']) ?></span></p>
            <?php if ($review['comment']): ?><p class="muted sm mt"><?= e($review['comment']) ?></p><?php endif; ?>
          <?php else: ?>
            <form method="post" class="row gap" style="align-items:flex-end">
              <?= csrf_field() ?>
              <label class="field" style="width:110px;margin:0"><span>Stars</span>
                <select class="input" name="stars">
                  <?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>"><?= $i ?> ★</option><?php endfor; ?>
                </select>
              </label>
              <label class="field grow" style="margin:0"><span>Comment</span>
                <input class="input" name="comment" placeholder="Was it on time?">
              </label>
              <button class="btn btn--primary">Rate</button>
            </form>
            <p class="xs dim mt">We already know whether it was late — this order was
              <?= $promise['late'] ? '<b class="late">' . ($promise['minutes'] - (int) $o['promised_min']) . ' min over</b>' : '<b style="color:var(--basil)">inside the promise</b>' ?>.</p>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- -------------------------------------------------------- receipt -->
    <aside class="sticky">
      <div class="card">
        <h3 class="h3 mb">Receipt</h3>
        <?php foreach ($items as $i): ?>
          <div class="split-row">
            <span><?= (int) $i['qty'] ?>× <?= e($i['name']) ?>
              <?php if ($o['is_group']): ?><span class="xs dim">· <?= e($i['member_name']) ?></span><?php endif; ?>
            </span>
            <b><?= money((float) $i['unit_price'] * (int) $i['qty']) ?></b>
          </div>
        <?php endforeach; ?>
        <div class="split-row"><span>Delivery</span><b><?= money((float) $o['delivery_fee']) ?></b></div>
        <div class="split-row"><span><b>Total</b></span><b><?= money((float) $o['total']) ?></b></div>
        <p class="xs dim mt"><?= strtoupper($o['payment_mode']) ?> · <?= e($o['address']) ?></p>
      </div>

      <?php if ($o['is_group'] && count($split) > 1): ?>
        <div class="card mt">
          <h3 class="h3 mb">Who owes you</h3>
          <?php $share = (float) $o['delivery_fee'] / count($split); ?>
          <?php foreach ($split as $name => $amt): ?>
            <div class="split-row"><span><?= e($name) ?></span><b><?= money($amt + $share) ?></b></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <a class="btn btn--ghost btn--block mt" href="<?= base_url('orders.php') ?>">All my orders</a>
    </aside>
  </div>
</main>

<script>
const ORDER_ID = <?= $id ?>;
const STATUSES = <?= json_encode(ORDER_STATUSES) ?>;
let lastStatus = '<?= $o['status'] ?>';

poll(async () => {
  const d = await apiGet('/api/order.php?id=' + ORDER_ID);

  if (d.status !== lastStatus) {
    toast('Update: ' + d.status_label, d.status === 'delivered' ? 'ok' : 'warn');
    lastStatus = d.status;
    setTimeout(() => location.reload(), 1200);   // redraw the timeline from the server
  }

  const c = document.getElementById('countdown');
  c.classList.toggle('late', !!d.late);
  // roll the number, keep the unit label next to it
  let n = c.querySelector('.cd-n');
  if (!n) { c.innerHTML = '<span class="cd-n"></span><small class="cd-u"></small>'; n = c.querySelector('.cd-n'); }
  if (d.minutes === null) { n.textContent = '—'; } else { setNum(n, Math.abs(d.minutes)); }
  c.querySelector('.cd-u').textContent = 'min' + (d.late && !d.done ? ' late' : '');
  document.getElementById('promiseLabel').textContent =
    d.done ? 'Delivered in' : (d.late ? 'Past the promised time by' : 'Arriving in about');

  const b = document.getElementById('statusBadge');
  b.className = 'badge ' + (d.done ? 'badge--ok' : (d.late ? 'badge--err' : 'badge--ember'));
  b.innerHTML = '<span class="dot' + (d.done ? '' : ' live') + '"></span>' + d.status_label;
}, 5000);
</script>
<?php page_foot(); ?>
