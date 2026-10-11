<?php
/**
 * The cart — and, when it is shared, the live group cart that several phones
 * can edit at once. The page is drawn once by PHP and then kept in sync by
 * polling /api/cart.php.
 */
require_once __DIR__ . '/config.php';

$cart = isset($_GET['code']) ? cart_by_code($_GET['code']) : active_cart();

if ($cart && $cart['status'] === 'ordered') {
    $o = q1('SELECT id FROM orders WHERE cart_id = ?', [$cart['id']]);
    redirect($o ? 'order.php?id=' . $o['id'] : 'orders.php');
}

// A guest following a share link needs to say who they are first.
if ($cart && !cart_member($cart, false)) {
    redirect('group.php?code=' . $cart['code']);
}

page_head('Your cart');
topbar('group');

if (!$cart):
    ?>
    <main class="wrap section">
      <div class="empty">
        <b>Your cart is empty</b>
        Pick a restaurant and add something.
        <div class="mt"><a class="btn btn--primary" href="<?= base_url('index.php') ?>">Browse restaurants</a></div>
      </div>
    </main>
    <?php
    page_foot();
    exit;
endif;

$me   = cart_member($cart);
$r    = q1('SELECT * FROM restaurants WHERE id = ?', [$cart['restaurant_id']]);
$link = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . base_url('group.php?code=' . $cart['code']);
?>
<main class="wrap section">
  <div class="row between gap mb">
    <div>
      <div class="mono-k"><?= $cart['is_group'] ? 'Group cart' : 'Your cart' ?> · <?= e($r['name']) ?></div>
      <h1 class="h1" style="margin-top:8px">
        <?= $cart['is_group'] ? 'One cart, <span class="serif">many phones.</span>' : 'Ready when <span class="serif">you are.</span>' ?>
      </h1>
    </div>
    <a class="btn btn--ghost" href="<?= base_url('restaurant.php?id=' . $r['id']) ?>">+ Add more dishes</a>
  </div>

  <div class="two-col">
    <div>
      <!-- ------------------------------------------------ sharing panel -->
      <?php if ($cart['is_group']): ?>
        <div class="card mb">
          <div class="row between gap mb">
            <h3 class="h3">Everyone in this cart</h3>
            <span class="badge badge--ok"><span class="dot live"></span>live</span>
          </div>
          <div class="member-chips" id="members"></div>

          <div class="share-code mt">
            <div>
              <div class="mono-k">Share code</div>
              <b><?= e($cart['code']) ?></b>
            </div>
            <div class="row gap" style="margin-left:auto">
              <button class="btn btn--ghost btn--sm" onclick="copyText('<?= e($cart['code']) ?>','Code copied')">Copy code</button>
              <button class="btn btn--primary btn--sm" onclick="copyText(<?= json_encode($link) ?>,'Link copied')">Copy link</button>
            </div>
          </div>
          <p class="xs dim mt">Friends open the link (or type the code on the join page), add their own food, and the
            bill splits by who ordered what.</p>
        </div>
      <?php else: ?>
        <div class="card mb row between gap">
          <div>
            <h3 class="h3">Eating with friends?</h3>
            <p class="sm muted">Turn this into a shared cart — they add their own food from their own phones.</p>
          </div>
          <button class="btn btn--primary" onclick="act({action:'make_group'}).then(()=>location.reload())">Share this cart</button>
        </div>
      <?php endif; ?>

      <!-- -------------------------------------------------------- lines -->
      <div class="card">
        <div class="row between mb">
          <h3 class="h3">In the cart</h3>
          <?php if ($me['is_owner']): ?>
            <button class="btn btn--danger btn--sm" onclick="if(confirm('Empty the whole cart?'))act({action:'clear'})">Empty</button>
          <?php endif; ?>
        </div>
        <div id="lines"></div>
      </div>

      <?php if ($cart['is_group']): ?>
        <div class="card mt">
          <h3 class="h3 mb">Who owes what</h3>
          <div id="split"></div>
          <p class="xs dim mt">Food is charged to whoever added it; the <?= money(DELIVERY_FEE) ?> delivery is split equally.</p>
        </div>
      <?php endif; ?>
    </div>

    <!-- ------------------------------------------------------- sidebar -->
    <aside class="sticky">
      <div class="card">
        <div class="mono-k">Delivered in about</div>
        <div class="countdown" style="font-size:44px"><span id="etaTotal">—</span><small>min</small></div>
        <div class="eta-bar"><i class="q" id="barQ"></i><i class="c" id="barC"></i><i class="r" id="barR"></i></div>
        <div class="eta-key">
          <span><i style="background:var(--saffron)"></i><span id="keyQ">0</span> queue</span>
          <span><i style="background:var(--ember)"></i><span id="keyC">0</span> cooking</span>
          <span><i style="background:var(--basil)"></i><span id="keyR">0</span> ride</span>
        </div>
        <div class="load mt" id="loadBox"><span class="load__dial" style="--pct:0"><b></b></span><span class="load__txt"><b id="loadLabel">—</b><span id="loadNote"></span></span></div>

        <div class="mt">
          <div class="split-row"><span>Subtotal</span><b id="tSub">—</b></div>
          <div class="split-row"><span>Delivery</span><b id="tFee">—</b></div>
          <div class="split-row"><span><b>Total</b></span><b id="tTotal">—</b></div>
        </div>

        <?php if ($me['is_owner']): ?>
          <a class="btn btn--primary btn--block mt" id="checkoutBtn" href="<?= base_url('checkout.php') ?>">Checkout</a>
          <?php if ($cart['is_group']): ?>
            <p class="xs dim mt center">Only you can place the order for this group.</p>
          <?php endif; ?>
        <?php else: ?>
          <div class="alert alert--warn mt">Waiting for <b id="ownerName">the cart owner</b> to place the order. Your items are saved.</div>
          <button class="btn btn--ghost btn--block" onclick="if(confirm('Leave this cart?'))act({action:'leave'}).then(()=>location.href=FETCH.base+'/index.php')">Leave cart</button>
        <?php endif; ?>
      </div>

      <?php if ($cart['is_group']): ?>
        <div class="card mt">
          <div class="mono-k mb">Activity</div>
          <ul class="feed" id="feed"></ul>
        </div>
      <?php endif; ?>
    </aside>
  </div>
</main>

<script>
const CODE = '<?= e($cart['code']) ?>', IS_GROUP = <?= (int) $cart['is_group'] ?>;
let lastCount = -1, lastMembers = 0;

async function act(payload) {
  try {
    const out = await api('/api/cart.php', { code: CODE, ...payload });
    if (out.cart) render(out.cart);
    return out;
  } catch (e) { toast(e.message, 'err'); throw e; }
}

function render(c) {
  if (!c) { location.href = FETCH.base + '/index.php'; return; }
  if (c.status === 'ordered') { location.reload(); return; }

  /* lines, grouped so you can see who ordered what */
  const box = document.getElementById('lines');
  if (!c.lines.length) {
    setHTML(box, '<div class="empty" style="padding:30px"><b>Nothing in the cart yet</b>Add a dish from the menu.</div>');
  } else {
    setHTML(box, c.lines.map((l) => `
      <div class="dish" style="padding:12px 0">
        <div>
          <div class="dish__head"><span class="veg-dot ${l.is_veg ? '' : 'nv'}"></span><h4>${l.name}</h4></div>
          <div class="dish__meta">
            <span>${money(l.price)} each</span>
            ${IS_GROUP ? `<span class="badge ${l.mine ? 'badge--ember' : ''}">${l.member}${l.mine ? ' · you' : ''}</span>` : ''}
          </div>
        </div>
        <div class="dish__side">
          <b class="mono">${money(l.line_total)}</b>
          ${(l.mine || (c.me && c.me.is_owner)) ? `
            <span class="qty">
              <button onclick="act({action:'qty',line_id:${l.id},qty:${l.qty - 1}})">−</button>
              <span>${l.qty}</span>
              <button onclick="act({action:'qty',line_id:${l.id},qty:${l.qty + 1}})">+</button>
            </span>` : `<span class="qty"><span>${l.qty}</span></span>`}
        </div>
      </div>`).join(''));
  }

  /* members with presence dots */
  const mem = document.getElementById('members');
  if (mem) {
    setHTML(mem, c.members.map((m) => `
      <span class="member ${m.online ? 'online' : ''} ${c.me && m.id === c.me.id ? 'me' : ''}">
        <span class="avatar">${m.name.split(' ').map((x) => x[0]).slice(0, 2).join('').toUpperCase()}</span>
        ${m.name}${m.is_owner ? ' <span class="xs dim">· host</span>' : ''}
        <span class="live-dot"></span>
      </span>`).join(''));
    if (c.members.length > lastMembers && lastMembers > 0) {
      toast(c.members[c.members.length - 1].name + ' joined the cart');
      mem.lastElementChild?.classList.add('pop-in');
    }
    lastMembers = c.members.length;
  }

  /* split bill */
  const sp = document.getElementById('split');
  if (sp) {
    setHTML(sp, Object.entries(c.split).map(([name, s]) => `
      <div class="split-row"><span>${name} <span class="xs dim">· ${money(s.food)} food + ${money(s.fee)} delivery</span></span><b>${money(s.total)}</b></div>`).join('')
      || '<p class="sm dim">Nobody has added anything yet.</p>');
  }

  /* totals + eta */
  const e = c.eta, sum = Math.max(1, e.total);
  if (c.count) { setNum(document.getElementById('etaTotal'), e.total); } else { document.getElementById('etaTotal').textContent = '—'; }
  setNum(document.getElementById('keyQ'), e.queue_wait);
  setNum(document.getElementById('keyC'), e.cook);
  setNum(document.getElementById('keyR'), e.ride);
  document.getElementById('barQ').style.width = (e.queue_wait / sum * 100) + '%';
  document.getElementById('barC').style.width = (e.cook / sum * 100) + '%';
  document.getElementById('barR').style.width = (e.ride / sum * 100) + '%';
  document.getElementById('tSub').textContent = money(c.subtotal);
  document.getElementById('tFee').textContent = money(c.fee);
  document.getElementById('tTotal').textContent = money(c.total);

  const lb = document.getElementById('loadBox');
  lb.className = 'load ' + e.load.level;
  lb.querySelector('.load__dial').style.setProperty('--pct', e.load.load_pct);
  document.getElementById('loadLabel').textContent = e.load.label + ' kitchen';
  document.getElementById('loadNote').textContent = e.load.orders_cooking + ' orders cooking now';

  const btn = document.getElementById('checkoutBtn');
  if (btn) btn.classList.toggle('hide', c.count === 0);
  const owner = document.getElementById('ownerName');
  if (owner) owner.textContent = (c.members.find((m) => m.is_owner) || {}).name || 'the host';

  /* tell everyone else when something new lands in the cart */
  if (IS_GROUP && lastCount >= 0 && c.count > lastCount) {
    const last = c.lines[c.lines.length - 1];
    if (last && !last.mine) toast(last.member + ' added ' + last.name);
  }
  lastCount = c.count;

  const feed = document.getElementById('feed');
  if (feed) setHTML(feed, c.feed.map((f) => `<li>${f.text}<time>${f.ago}</time></li>`).join(''));
}

act({ action: 'state' });
poll(() => act({ action: 'state' }), IS_GROUP ? 3000 : 8000);
</script>
<?php page_foot(); ?>
