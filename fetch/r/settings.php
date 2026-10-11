<?php
/** Restaurant profile and the two numbers that drive the ETA engine. */
require_once __DIR__ . '/../config.php';
$user = require_role('restaurant');
$r    = my_restaurant();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $img = save_upload('image');
    exec_sql(
        'UPDATE restaurants SET name=?, cuisine=?, tagline=?, address=?, phone=?, max_parallel_orders=?, prep_buffer_min=?'
        . ($img ? ', image=?' : '') . ' WHERE id=?',
        array_values(array_filter([
            trim($_POST['name'] ?? $r['name']),
            trim($_POST['cuisine'] ?? $r['cuisine']),
            trim($_POST['tagline'] ?? ''),
            trim($_POST['address'] ?? ''),
            trim($_POST['phone'] ?? ''),
            max(1, min(20, (int) ($_POST['max_parallel_orders'] ?? 3))),
            max(0, min(30, (int) ($_POST['prep_buffer_min'] ?? 2))),
            $img,
            $r['id'],
        ], fn($v) => $v !== null))
    );
    flash('Settings saved.');
    redirect('r/settings.php');
}

$load = kitchen_load((int) $r['id']);

page_head('Settings — ' . $r['name']);
topbar();
?>
<div class="shell">
  <?php dash_side('restaurant', 'settings'); ?>
  <main class="main">
    <div class="page-head"><div><div class="mono-k">Restaurant profile</div><h1 class="h1" style="margin-top:6px">Settings.</h1></div></div>

    <div class="two-col">
      <form class="card" method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <h3 class="h3 mb">Profile</h3>
        <label class="field"><span>Name</span><input class="input" name="name" value="<?= e($r['name']) ?>" required></label>
        <div class="row gap">
          <label class="field grow"><span>Cuisine</span><input class="input" name="cuisine" value="<?= e($r['cuisine']) ?>"></label>
          <label class="field grow"><span>Phone</span><input class="input" name="phone" value="<?= e($r['phone']) ?>"></label>
        </div>
        <label class="field"><span>Tagline</span><input class="input" name="tagline" value="<?= e($r['tagline']) ?>"></label>
        <label class="field"><span>Address</span><input class="input" name="address" value="<?= e($r['address']) ?>"></label>
        <label class="field"><span>Cover photo</span><input class="input" type="file" name="image" accept="image/*"></label>

        <h3 class="h3 mb mt-lg">Kitchen capacity</h3>
        <p class="sm muted mb">These two numbers are the whole ETA engine. Set them to what the kitchen can really do.</p>
        <div class="row gap">
          <label class="field grow"><span>Orders cooked at once</span>
            <input class="input" type="number" name="max_parallel_orders" min="1" max="20" value="<?= (int) $r['max_parallel_orders'] ?>">
          </label>
          <label class="field grow"><span>Plating buffer (min)</span>
            <input class="input" type="number" name="prep_buffer_min" min="0" max="30" value="<?= (int) $r['prep_buffer_min'] ?>">
          </label>
        </div>
        <button class="btn btn--primary">Save settings</button>
      </form>

      <aside class="sticky">
        <div class="card">
          <h3 class="h3 mb">Right now</h3>
          <div class="load <?= $load['level'] ?>">
            <span class="load__dial" style="--pct:<?= $load['load_pct'] ?>"><b><?= $load['load_pct'] ?>%</b></span>
            <span class="load__txt"><b><?= e($load['label']) ?></b><span><?= $load['orders_cooking'] ?> orders · <?= $load['pending_min'] ?> min of work</span></span>
          </div>
          <div class="mt">
            <div class="split-row"><span>Queue wait quoted</span><b><?= $load['queue_wait'] ?> min</b></div>
            <div class="split-row"><span>Cooked at once</span><b><?= $load['capacity'] ?></b></div>
            <div class="split-row"><span>Platform commission</span><b><?= rtrim(rtrim(number_format((float) $r['commission_pct'], 2), '0'), '.') ?>%</b></div>
          </div>
          <p class="xs dim mt">Commission is set by the platform admin.</p>
        </div>
      </aside>
    </div>
  </main>
</div>
<?php page_foot(); ?>
