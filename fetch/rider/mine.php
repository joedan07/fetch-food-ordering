<?php
/** Rider — the deliveries I'm holding, and what I've finished. */
require_once __DIR__ . '/../config.php';
$user = require_role('rider');

$active = q(
    "SELECT o.*, r.name AS restaurant, r.address AS pickup, u.name AS customer, u.phone
       FROM orders o JOIN restaurants r ON r.id = o.restaurant_id JOIN users u ON u.id = o.user_id
      WHERE o.rider_id = ? AND o.status IN ('ready','out_for_delivery') ORDER BY o.promised_at",
    [$user['id']]
);
$past = q(
    "SELECT o.*, r.name AS restaurant FROM orders o JOIN restaurants r ON r.id = o.restaurant_id
      WHERE o.rider_id = ? AND o.status = 'delivered' ORDER BY o.delivered_at DESC LIMIT 30",
    [$user['id']]
);

page_head('My deliveries');
topbar();
?>
<div class="shell">
  <?php dash_side('rider', 'mine'); ?>
  <main class="main">
    <div class="page-head"><div><div class="mono-k"><?= count($active) ?> in hand</div><h1 class="h1" style="margin-top:6px">My <span class="serif">deliveries.</span></h1></div></div>

    <?php if ($active): ?>
      <div class="tickets mb">
        <?php foreach ($active as $o): $p = promise_state($o); ?>
          <article class="ticket <?= $p['late'] ? 'urgent' : '' ?>">
            <div class="ticket__top">
              <span class="ticket__code"><?= e($o['code']) ?></span>
              <span class="badge <?= $p['late'] ? 'badge--err' : '' ?>"><?= $p['late'] ? abs((int) $p['minutes']) . ' min late' : (int) $p['minutes'] . ' min left' ?></span>
            </div>
            <div class="ticket__body">
              <div class="ticket__line"><span class="who">Pick up</span><b style="color:var(--ink)"><?= e($o['restaurant']) ?></b></div>
              <div class="ticket__line"><span class="who"><?= e($o['pickup']) ?></span></div>
              <div class="ticket__line" style="margin-top:8px"><span class="who">Drop at</span><b style="color:var(--ink)"><?= e($o['customer']) ?></b></div>
              <div class="ticket__line"><span><?= e($o['address']) ?></span></div>
              <div class="ticket__line"><a href="tel:<?= e($o['phone']) ?>" style="color:var(--ember)"><?= e($o['phone']) ?></a>
                <b><?= strtoupper($o['payment_mode']) ?> <?= money((float) $o['total']) ?></b></div>
            </div>
            <div class="ticket__foot">
              <?php if ($o['status'] === 'ready'): ?>
                <button class="btn btn--primary btn--sm btn--block" onclick="advance(<?= $o['id'] ?>,'out_for_delivery')">Picked up — on my way</button>
              <?php else: ?>
                <button class="btn btn--ok btn--sm btn--block" onclick="advance(<?= $o['id'] ?>,'delivered')">Delivered</button>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty mb"><b>Nothing in hand</b>Pick something up from the available list.
        <div class="mt"><a class="btn btn--primary" href="<?= base_url('rider/index.php') ?>">See available</a></div></div>
    <?php endif; ?>

    <div class="mono-k mb">Finished</div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Order</th><th>Restaurant</th><th>Delivered</th><th>Promise</th><th>Total</th></tr></thead>
        <tbody>
        <?php foreach ($past as $o): $p = promise_state($o); ?>
          <tr>
            <td class="num"><?= e($o['code']) ?></td>
            <td><?= e($o['restaurant']) ?></td>
            <td class="sm dim"><?= date('j M, g:i a', strtotime($o['delivered_at'])) ?></td>
            <td class="num" style="color:var(--<?= $p['late'] ? 'chilli' : 'basil' ?>)"><?= $p['minutes'] ?> / <?= $p['promised'] ?> min</td>
            <td class="num"><?= money((float) $o['total']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$past): ?><tr><td colspan="5"><div class="empty"><b>No deliveries yet</b></div></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>
</div>

<script>
async function advance(id, status) {
  try { await api('/api/status.php', { order_id: id, status }); toast('Updated'); setTimeout(() => location.reload(), 600); }
  catch (e) { toast(e.message, 'err'); }
}
</script>
<?php page_foot(); ?>
