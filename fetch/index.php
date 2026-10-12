<?php
require_once __DIR__ . '/config.php';

// Staff land on their own console.
if (is_logged_in() && user_role() !== 'customer') {
    redirect(home_for(user_role()));
}

$search = trim($_GET['q'] ?? '');
$rows = q(
    "SELECT r.*,
            (SELECT ROUND(AVG(stars),1) FROM reviews v WHERE v.restaurant_id = r.id)                      AS rating,
            (SELECT COUNT(*)            FROM reviews v WHERE v.restaurant_id = r.id)                      AS rating_count,
            (SELECT COUNT(*) FROM orders o WHERE o.restaurant_id = r.id AND o.status = 'delivered')       AS done_count,
            (SELECT COUNT(*) FROM orders o WHERE o.restaurant_id = r.id AND o.status = 'delivered'
                AND TIMESTAMPDIFF(MINUTE, o.created_at, o.delivered_at) <= o.promised_min)                AS on_time_count,
            (SELECT MIN(price) FROM menu_items i WHERE i.restaurant_id = r.id AND i.is_available = 1)      AS from_price
       FROM restaurants r
      WHERE r.is_approved = 1 AND (r.name LIKE ? OR r.cuisine LIKE ?)
      ORDER BY r.is_open DESC, rating IS NULL, rating DESC",
    ['%' . $search . '%', '%' . $search . '%']
);

$loads = kitchen_loads(array_map('intval', array_column($rows, 'id')));

page_head('Order food');
topbar('home');
?>
<main>
  <!-- ------------------------------------------------------------ hero -->
  <section class="wrap" style="padding:clamp(34px,6vw,70px) var(--gutter) 10px">
    <div class="row between gap-lg" style="align-items:flex-end">
      <div>
        <div class="mono-k">Campus food, honestly timed</div>
        <h1 class="h1" style="margin-top:10px;max-width:15ch">
          <span class="rise"><span style="--i:0">Hot</span></span>
          <span class="rise"><span style="--i:1">food,</span></span>
          <span class="rotator"><span class="is-on">tracked</span><span>timed</span><span>shared</span><span>served</span></span><br>
          <span class="rise"><span style="--i:3">over HTTP.</span></span>
        </h1>
        <p class="muted mt" style="max-width:52ch">
          Two things no other delivery app does: a <b>cart your friends can join from their own phones</b>,
          and a delivery time worked out from the <b>kitchen's real queue</b> instead of a flat guess.
        </p>
      </div>
      <form class="row gap" method="get" style="flex:1;min-width:260px;max-width:420px">
        <input class="input" name="q" placeholder="Search restaurants or cuisine…" value="<?= e($search) ?>">
        <button class="btn btn--ghost">Search</button>
      </form>
    </div>
  </section>


  <!-- a tape band, same device as the review deck -->
  <div class="tape" aria-hidden="true">
    <div class="tape__track"><span>live kitchen load</span><span>group carts</span><span>honest ETAs</span><span>no fake 30 minutes</span><span>split the bill by who ordered</span><span>GET /menu</span><span>201 Created</span></div>
    <div class="tape__track"><span>live kitchen load</span><span>group carts</span><span>honest ETAs</span><span>no fake 30 minutes</span><span>split the bill by who ordered</span><span>GET /menu</span><span>201 Created</span></div>
  </div>

  <!-- ------------------------------------------------------ restaurants -->
  <section class="wrap section">
    <?php if (!$rows): ?>
      <div class="empty"><b>Nothing matches "<?= e($search) ?>"</b>Try another cuisine.</div>
    <?php else: ?>
    <div class="r-grid">
      <?php foreach ($rows as $r):
          $load    = $loads[(int) $r['id']];
          $onTime  = $r['done_count'] ? round($r['on_time_count'] / $r['done_count'] * 100) : null;
      ?>
        <a class="card r-card" href="<?= base_url('restaurant.php?id=' . $r['id']) ?>">
          <div class="r-card__img" style="background:<?= food_gradient($r['name']) ?>">
            <?php if ($r['image']): ?><img src="<?= base_url('uploads/' . rawurlencode($r['image'])) ?>" alt=""><?php endif; ?>
            <b><?= e($r['name']) ?></b>
            <?php if (!$r['is_open']): ?><span class="closed-tag">Closed right now</span><?php endif; ?>
          </div>
          <div class="r-card__body">
            <div class="row between gap">
              <h3><?= e($r['name']) ?></h3>
              <?php if ($r['rating']): ?>
                <span class="badge badge--ok"><?= $r['rating'] ?> ★</span>
              <?php endif; ?>
            </div>
            <p class="sm muted" style="margin-top:-4px"><?= e($r['cuisine']) ?> · from <?= money((float) $r['from_price']) ?></p>

            <!-- live kitchen load, refreshed every few seconds -->
            <div class="load load--mini <?= $load['level'] ?>" data-load="<?= $r['id'] ?>">
              <span class="load__dial" style="--pct:<?= $load['load_pct'] ?>"><b></b></span>
              <span class="load__txt"><?= e($load['label']) ?> · ~<?= $load['queue_wait'] + RIDE_MINUTES + 12 ?> min</span>
            </div>

            <?php if ($onTime !== null): ?>
              <div class="xs dim mono-k" style="letter-spacing:.06em;text-transform:none">
                <?= $onTime ?>% of <?= $r['done_count'] ?> orders arrived inside the promised time
              </div>
            <?php endif; ?>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>

  <!-- -------------------------------------------------------------- usps -->
  <section class="wrap section">
    <div class="r-grid" style="grid-template-columns:repeat(auto-fit,minmax(300px,1fr))">
      <div class="card">
        <span class="badge badge--ember">USP 01</span>
        <h3 class="h2" style="margin:12px 0 8px">One cart, <span class="serif">many phones.</span></h3>
        <p class="muted sm">Share a six-letter code. Everyone adds their own food from their own phone, the cart
          updates live for all of you, and the bill splits itself by who ordered what. No more "send me your order" on WhatsApp.</p>
      </div>
      <div class="card">
        <span class="badge badge--ember">USP 02</span>
        <h3 class="h2" style="margin:12px 0 8px">A time the kitchen <span class="serif">can actually keep.</span></h3>
        <p class="muted sm">Every dish carries its own prep time. We add up what the kitchen is already cooking,
          divide by how much it can cook at once, and show you the real wait — before you order, and as a live
          promise afterwards.</p>
      </div>
    </div>
  </section>
</main>

<script>
// Keep the busy meters live without reloading the page.
poll(async () => {
  const data = await apiGet('/api/load.php');
  document.querySelectorAll('[data-load]').forEach((el) => {
    const d = data.loads[el.dataset.load];
    if (!d) return;
    el.className = 'load load--mini ' + d.level;
    el.querySelector('.load__dial').style.setProperty('--pct', d.load_pct);   // sweeps, thanks to @property
    const txt = d.label + ' · ~' + d.typical_eta + ' min';
    const node = el.querySelector('.load__txt');
    if (node.textContent !== txt) node.textContent = txt;
  });
}, 6000);
</script>
<?php page_foot(); ?>
