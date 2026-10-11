<?php
require_once __DIR__ . '/config.php';

$id = (int) ($_GET['id'] ?? 0);
$r  = q1('SELECT * FROM restaurants WHERE id = ? AND is_approved = 1', [$id]);
if (!$r) {
    http_response_code(404);
    page_head('Not found');
    topbar();
    echo '<main class="wrap section"><div class="empty"><b>No such restaurant</b>It may have been removed.</div></main>';
    page_foot();
    exit;
}

$cats  = q('SELECT * FROM categories WHERE restaurant_id = ? ORDER BY sort_order, id', [$id]);
$items = q('SELECT * FROM menu_items WHERE restaurant_id = ? ORDER BY is_available DESC, id', [$id]);
$load  = kitchen_load($id);
$stats = q1(
    "SELECT ROUND(AVG(v.stars),1) rating, COUNT(v.id) reviews FROM reviews v WHERE v.restaurant_id = ?",
    [$id]
);
$cart = active_cart();
$mine = $cart && (int) $cart['restaurant_id'] === $id ? $cart : null;

page_head($r['name']);
topbar('home');
?>
<main class="wrap section">
  <!-- ------------------------------------------------------------ header -->
  <div class="card" style="padding:0;overflow:hidden">
    <div style="height:clamp(120px,18vw,180px);background:<?= food_gradient($r['name']) ?>;position:relative">
      <?php if ($r['image']): ?><img src="<?= base_url('uploads/' . rawurlencode($r['image'])) ?>" alt="" style="width:100%;height:100%;object-fit:cover"><?php endif; ?>
      <?php if (!$r['is_open']): ?><span class="closed-tag">Closed right now</span><?php endif; ?>
    </div>
    <div style="padding:20px 22px">
      <div class="row between gap">
        <div>
          <h1 class="h1" style="font-size:clamp(24px,3.4vw,36px)"><?= e($r['name']) ?></h1>
          <p class="muted"><?= e($r['tagline']) ?></p>
          <div class="row gap mt sm dim">
            <span><?= e($r['cuisine']) ?></span>·<span><?= e($r['address']) ?></span>
            <?php if ($stats['rating']): ?>·<span><?= $stats['rating'] ?> ★ (<?= $stats['reviews'] ?>)</span><?php endif; ?>
          </div>
        </div>

        <!-- live kitchen load, the honest-ETA USP in one box -->
        <div class="load <?= $load['level'] ?>" id="loadBox" style="min-width:250px">
          <span class="load__dial" style="--pct:<?= $load['load_pct'] ?>"><b id="loadPct"><?= $load['load_pct'] ?>%</b></span>
          <span class="load__txt">
            <b id="loadLabel"><?= e($load['label']) ?></b>
            <span id="loadNote"><?= $load['orders_cooking'] ?> orders cooking · <?= $load['queue_wait'] ?> min queue</span>
          </span>
        </div>
      </div>
    </div>
  </div>

  <div class="two-col mt-lg">
    <!-- -------------------------------------------------------- the menu -->
    <div>
      <div class="cat-nav mb">
        <?php foreach ($cats as $c): ?><a href="#cat<?= $c['id'] ?>"><?= e($c['name']) ?></a><?php endforeach; ?>
      </div>

      <?php foreach ($cats as $c): ?>
        <section id="cat<?= $c['id'] ?>" class="card mb" style="scroll-margin-top:90px">
          <h2 class="h2"><?= e($c['name']) ?></h2>
          <?php foreach (array_filter($items, fn($i) => (int) $i['category_id'] === (int) $c['id']) as $i): ?>
            <article class="dish <?= $i['is_available'] ? '' : 'out' ?>">
              <div>
                <div class="dish__head">
                  <span class="veg-dot <?= $i['is_veg'] ? '' : 'nv' ?>"></span>
                  <h4><?= e($i['name']) ?></h4>
                </div>
                <p><?= e($i['description']) ?></p>
                <div class="dish__meta">
                  <span><b><?= money((float) $i['price']) ?></b></span>
                  <span>takes <b><?= (int) $i['prep_minutes'] ?> min</b> to cook</span>
                  <?php if (!$i['is_available']): ?><span class="badge badge--err">Sold out</span><?php endif; ?>
                </div>
              </div>
              <div class="dish__side">
                <div class="dish__tile" style="background:<?= food_gradient($i['name']) ?>">
                  <?php if ($i['image']): ?><img src="<?= base_url('uploads/' . rawurlencode($i['image'])) ?>" alt=""><?php endif; ?>
                  <?= e(initials($i['name'])) ?>
                </div>
                <?php if ($i['is_available'] && $r['is_open']): ?>
                  <button class="btn btn--primary btn--sm" onclick="addDish(<?= $i['id'] ?>, this)">Add</button>
                <?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
        </section>
      <?php endforeach; ?>
    </div>

    <!-- ------------------------------------------------------- the cart -->
    <aside class="sticky">
      <div class="card" id="cartBox">
        <div class="row between">
          <h3 class="h3">Your cart</h3>
          <span class="badge" id="cartCount">0 items</span>
        </div>
        <div id="cartLines" class="mt"></div>
        <div id="cartEmpty" class="sm dim mt">Nothing yet. Add a dish and the timing appears here.</div>

        <!-- where every minute comes from -->
        <div id="etaBox" class="hide mt">
          <div class="mono-k">Delivered in about</div>
          <div class="row between" style="align-items:baseline">
            <div class="countdown" style="font-size:38px"><span id="etaTotal">—</span><small>min</small></div>
            <span class="badge badge--ok" id="etaLive"><span class="dot live"></span>live</span>
          </div>
          <div class="eta-bar">
            <i class="q" id="barQ"></i><i class="c" id="barC"></i><i class="r" id="barR"></i>
          </div>
          <div class="eta-key">
            <span><i style="background:var(--saffron)"></i><span id="keyQ">0</span> queue</span>
            <span><i style="background:var(--ember)"></i><span id="keyC">0</span> cooking</span>
            <span><i style="background:var(--basil)"></i><span id="keyR">0</span> ride</span>
          </div>
        </div>

        <div id="cartTotals" class="hide mt">
          <div class="split-row"><span>Subtotal</span><b id="tSub"></b></div>
          <div class="split-row"><span>Delivery</span><b id="tFee"></b></div>
          <div class="split-row"><span><b>Total</b></span><b id="tTotal"></b></div>
          <a class="btn btn--primary btn--block mt" href="<?= base_url('cart.php') ?>">Review &amp; order</a>
          <button class="btn btn--ghost btn--block mt" id="groupBtn" onclick="makeGroup()">Share this cart with friends</button>
        </div>
      </div>

      <div class="card mt" style="padding:16px">
        <div class="mono-k mb">Why these minutes?</div>
        <p class="sm muted">The queue number is real: it is the cooking time of every order this kitchen is holding,
          divided by how many it can cook at once. When the kitchen is slammed, you see it before you pay.</p>
      </div>
    </aside>
  </div>
</main>

<script>
const RESTAURANT_ID = <?= $id ?>;

/* ---- add a dish ------------------------------------------------------- */
async function addDish(itemId, btn, replace = false) {
  btn.disabled = true;
  try {
    const out = await api('/api/cart.php', { action: 'add', item_id: itemId, replace });
    render(out.cart);
    toast('Added');
  } catch (err) {
    // the server asks before throwing away a cart from another restaurant
    const msg = err.message || '';
    if (msg.includes('already has food')) {
      if (confirm(msg)) return addDish(itemId, btn, true);
    } else if (msg.includes('sign in') || msg.includes('expired')) {
      location.href = FETCH.base + '/login.php';
    } else {
      toast(msg, 'err');
    }
  } finally {
    btn.disabled = false;
  }
}

async function makeGroup() {
  try {
    await api('/api/cart.php', { action: 'make_group' });
    location.href = FETCH.base + '/cart.php';
  } catch (e) { toast(e.message, 'err'); }
}

/* ---- draw the cart panel ---------------------------------------------- */
function render(cart) {
  const empty = !cart || cart.restaurant.id !== RESTAURANT_ID || cart.count === 0;
  document.getElementById('cartEmpty').classList.toggle('hide', !empty);
  document.getElementById('cartTotals').classList.toggle('hide', empty);
  document.getElementById('etaBox').classList.toggle('hide', empty);
  document.getElementById('cartCount').textContent = empty ? '0 items' : cart.count + ' item' + (cart.count > 1 ? 's' : '');
  const box = document.getElementById('cartLines');
  if (empty) { box.innerHTML = ''; return; }

  setHTML(box, cart.lines.map((l) => `
    <div class="split-row">
      <span>${l.qty}× ${l.name}${cart.is_group ? ` <span class="xs dim">· ${l.member}</span>` : ''}</span>
      <b>${money(l.line_total)}</b>
    </div>`).join(''));

  const e = cart.eta;
  setNum(document.getElementById('etaTotal'), e.total);
  setNum(document.getElementById('keyQ'), e.queue_wait);
  setNum(document.getElementById('keyC'), e.cook);
  setNum(document.getElementById('keyR'), e.ride);
  const sum = Math.max(1, e.total);
  document.getElementById('barQ').style.width = (e.queue_wait / sum * 100) + '%';
  document.getElementById('barC').style.width = (e.cook / sum * 100) + '%';
  document.getElementById('barR').style.width = (e.ride / sum * 100) + '%';

  document.getElementById('tSub').textContent = money(cart.subtotal);
  document.getElementById('tFee').textContent = money(cart.fee);
  document.getElementById('tTotal').textContent = money(cart.total);
  document.getElementById('groupBtn').textContent = cart.is_group ? 'Open the shared cart' : 'Share this cart with friends';
  if (cart.is_group) document.getElementById('groupBtn').setAttribute('onclick', "location.href=FETCH.base+'/cart.php'");
}

/* ---- keep the cart and the kitchen meter live ------------------------- */
async function refresh() {
  const [state, load] = await Promise.all([
    api('/api/cart.php', { action: 'state' }),
    apiGet('/api/load.php'),
  ]);
  render(state.cart);
  const d = load.loads[RESTAURANT_ID];
  if (d) {
    document.getElementById('loadBox').className = 'load ' + d.level;
    document.getElementById('loadBox').querySelector('.load__dial').style.setProperty('--pct', d.load_pct);
    setNum(document.getElementById('loadPct'), d.load_pct, { suffix: '%' });
    document.getElementById('loadLabel').textContent = d.label;
    document.getElementById('loadNote').textContent = d.cooking + ' orders cooking · ' + d.queue_wait + ' min queue';
  }
}
refresh().catch(() => {});
poll(refresh, 6000);
</script>
<?php page_foot(); ?>
