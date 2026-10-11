<?php
/** Kitchen console — the live ticket board. */
require_once __DIR__ . '/../config.php';
$user = require_role('restaurant');
$r    = my_restaurant();

page_head('Kitchen — ' . $r['name']);
topbar();
?>
<div class="shell">
  <?php dash_side('restaurant', 'kitchen'); ?>
  <main class="main">
    <div class="page-head">
      <div>
        <div class="mono-k"><?= e($r['name']) ?></div>
        <h1 class="h1" style="margin-top:6px">Kitchen <span class="serif">pass.</span></h1>
      </div>
      <div class="row gap">
        <div class="load" id="loadBox" style="min-width:230px">
          <span class="load__dial" style="--pct:0"><b id="loadPct">0%</b></span>
          <span class="load__txt"><b id="loadLabel">—</b><span id="loadNote"></span></span>
        </div>
        <!-- turning this off stops new orders without hiding the menu -->
        <button class="switch <?= $r['is_open'] ? 'on' : '' ?>" id="openSwitch" title="Accepting orders"><span></span></button>
      </div>
    </div>

    <div class="stats">
      <div class="stat ember"><b id="sOpen">0</b><span>Live tickets</span></div>
      <div class="stat"><b id="sToday">0</b><span>Orders today</span></div>
      <div class="stat ok"><b id="sRevenue">₹0</b><span>Revenue today</span></div>
      <div class="stat warn"><b id="sQueue">0 min</b><span>Queue wait being quoted</span></div>
    </div>

    <div id="board" class="tickets"></div>
    <div id="boardEmpty" class="empty hide"><b>No live orders</b>New tickets appear here the moment they're placed.</div>
  </main>
</div>

<script>
const seen = new Set();

/** The next step this ticket is waiting for. */
function nextStep(status) {
  return { placed: ['accepted', 'Accept'], accepted: ['preparing', 'Start cooking'], preparing: ['ready', 'Mark ready'] }[status] || null;
}

async function advance(id, status) {
  try { await api('/api/status.php', { order_id: id, status }); toast('Updated'); refresh(); }
  catch (e) { toast(e.message, 'err'); }
}

async function refresh() {
  const d = await apiGet('/api/kitchen.php');

  setNum(document.getElementById('sOpen'), d.tickets.length);
  setNum(document.getElementById('sToday'), d.today.orders);
  setNum(document.getElementById('sRevenue'), d.today.revenue, { prefix: '₹' });
  setNum(document.getElementById('sQueue'), d.load.queue_wait, { suffix: ' min' });

  const lb = document.getElementById('loadBox');
  lb.className = 'load ' + d.load.level;
  lb.querySelector('.load__dial').style.setProperty('--pct', d.load.load_pct);
  setNum(document.getElementById('loadPct'), d.load.load_pct, { suffix: '%' });
  document.getElementById('loadLabel').textContent = d.load.label;
  document.getElementById('loadNote').textContent = d.load.pending_min + ' min of food on the stoves';

  document.getElementById('boardEmpty').classList.toggle('hide', d.tickets.length > 0);
  setHTML(document.getElementById('board'), d.tickets.map((t) => {
    const step = nextStep(t.status);
    return `
    <article class="ticket ${t.late ? 'urgent' : ''}">
      <div class="ticket__top">
        <span class="ticket__code">${t.code}</span>
        <span class="badge ${t.late ? 'badge--err' : (t.status === 'placed' ? 'badge--ember' : '')}">
          ${t.late ? Math.abs(t.left) + ' min late' : (t.left + ' min left')}
        </span>
      </div>
      <div class="ticket__body">
        ${t.items.map((i) => `<div class="ticket__line"><span>${i.qty}× ${i.name}${t.is_group ? ` <span class="who">${i.member_name}</span>` : ''}</span></div>`).join('')}
        <div class="ticket__line" style="margin-top:6px">
          <span class="who">${t.customer}${t.is_group ? ' · group' : ''}</span>
          <b>${money(t.total)}</b>
        </div>
        <div class="ticket__line"><span class="who">${t.label} · waiting ${t.waiting} min · ${t.cook} min of cooking</span></div>
      </div>
      <div class="ticket__foot">
        ${step ? `<button class="btn btn--primary btn--sm" onclick="advance(${t.id},'${step[0]}')">${step[1]}</button>` : ''}
        ${t.status === 'ready' ? '<span class="badge badge--ok">Waiting for a rider</span>' : ''}
        ${t.status === 'out_for_delivery' ? '<span class="badge">On the road</span>' : ''}
        ${t.status === 'placed' ? `<button class="btn btn--danger btn--sm" onclick="if(confirm('Reject this order?'))advance(${t.id},'cancelled')">Reject</button>` : ''}
      </div>
    </article>`;
  }).join(''));

  d.tickets.forEach((t) => { if (!seen.has(t.id)) { if (seen.size) toast('New order ' + t.code); seen.add(t.id); } });
}

/* open / closed toggle */
document.getElementById('openSwitch').addEventListener('click', async (e) => {
  const on = !e.currentTarget.classList.contains('on');
  try {
    await api('/api/restaurant.php', { action: 'open', is_open: on ? 1 : 0 });
    e.currentTarget.classList.toggle('on', on);
    toast(on ? 'Taking orders again' : 'Closed — no new orders');
  } catch (err) { toast(err.message, 'err'); }
});

refresh();
poll(refresh, 4000);
</script>
<?php page_foot(); ?>
