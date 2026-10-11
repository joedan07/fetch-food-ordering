<?php
/** Rider — orders waiting for someone to take them. */
require_once __DIR__ . '/../config.php';
$user = require_role('rider');

page_head('Available deliveries');
topbar();
?>
<div class="shell">
  <?php dash_side('rider', 'available'); ?>
  <main class="main">
    <div class="page-head">
      <div><div class="mono-k">Updated live</div><h1 class="h1" style="margin-top:6px">Ready for <span class="serif">pickup.</span></h1></div>
      <span class="badge badge--ok"><span class="dot live"></span>watching</span>
    </div>

    <div class="stats">
      <div class="stat ember"><b id="sWaiting">0</b><span>Waiting for a rider</span></div>
      <div class="stat"><b id="sMine">0</b><span>On my bike</span></div>
      <div class="stat ok"><b id="sDone">0</b><span>Delivered today</span></div>
    </div>

    <div id="board" class="tickets"></div>
    <div id="empty" class="empty hide"><b>Nothing ready yet</b>Kitchens are still cooking. This page updates on its own.</div>
  </main>
</div>

<script>
async function claim(id) {
  try { await api('/api/rider.php', { action: 'claim', order_id: id }); toast('It\'s yours — head to the kitchen'); refresh(); }
  catch (e) { toast(e.message, 'err'); refresh(); }
}

async function refresh() {
  const d = await apiGet('/api/rider_feed.php');
  setNum(document.getElementById('sWaiting'), d.available.length);
  setNum(document.getElementById('sMine'), d.mine);
  setNum(document.getElementById('sDone'), d.done_today);
  document.getElementById('empty').classList.toggle('hide', d.available.length > 0);
  setHTML(document.getElementById('board'), d.available.map((o) => `
    <article class="ticket ${o.late ? 'urgent' : ''}">
      <div class="ticket__top">
        <span class="ticket__code">${o.code}</span>
        <span class="badge ${o.late ? 'badge--err' : ''}">${o.late ? Math.abs(o.left) + ' min late' : o.left + ' min left'}</span>
      </div>
      <div class="ticket__body">
        <div class="ticket__line"><b>${o.restaurant}</b></div>
        <div class="ticket__line"><span>${o.address}</span></div>
        <div class="ticket__line"><span class="who">${o.customer} · ${o.items} items · ${o.payment.toUpperCase()}</span><b>${money(o.total)}</b></div>
      </div>
      <div class="ticket__foot"><button class="btn btn--primary btn--sm btn--block" onclick="claim(${o.id})">Take this delivery</button></div>
    </article>`).join(''));
}
refresh();
poll(refresh, 4000);
</script>
<?php page_foot(); ?>
