<?php
/** Admin — approve, suspend and onboard restaurants. */
require_once __DIR__ . '/../config.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $do = $_POST['do'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($do === 'approve') {
        exec_sql('UPDATE restaurants SET is_approved = 1 WHERE id = ?', [$id]);
        flash('Restaurant approved — it is now live.');
    } elseif ($do === 'suspend') {
        exec_sql('UPDATE restaurants SET is_approved = 0 WHERE id = ?', [$id]);
        flash('Restaurant suspended.', 'warn');
    } elseif ($do === 'commission') {
        exec_sql('UPDATE restaurants SET commission_pct = ? WHERE id = ?', [max(0, min(40, (float) $_POST['pct'])), $id]);
        flash('Commission updated.');
    } elseif ($do === 'create') {
        $name  = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('Need a restaurant name and a valid owner email.', 'err');
        } elseif (q1('SELECT id FROM users WHERE email = ?', [$email])) {
            flash('That owner email already exists.', 'err');
        } else {
            $rid = insert('INSERT INTO restaurants (name, cuisine, tagline, address, phone, is_approved) VALUES (?,?,?,?,?,1)',
                [$name, trim($_POST['cuisine'] ?? ''), trim($_POST['tagline'] ?? ''), trim($_POST['address'] ?? ''), trim($_POST['phone'] ?? '')]);
            insert('INSERT INTO users (name, email, password_hash, role, restaurant_id) VALUES (?,?,?,"restaurant",?)',
                [trim($_POST['owner'] ?? $name), $email, password_hash($_POST['password'] ?: 'fetch123', PASSWORD_DEFAULT), $rid]);
            flash('Restaurant and owner account created.');
        }
    }
    redirect('admin/restaurants.php');
}

$rows = q(
    "SELECT r.*,
            (SELECT COUNT(*) FROM orders o WHERE o.restaurant_id = r.id) orders,
            (SELECT COALESCE(SUM(total),0) FROM orders o WHERE o.restaurant_id = r.id AND o.status <> 'cancelled') rev,
            (SELECT COUNT(*) FROM menu_items i WHERE i.restaurant_id = r.id) dishes,
            (SELECT u.name FROM users u WHERE u.restaurant_id = r.id LIMIT 1) owner,
            (SELECT u.email FROM users u WHERE u.restaurant_id = r.id LIMIT 1) owner_email
       FROM restaurants r ORDER BY r.is_approved, r.name"
);

page_head('Restaurants — Admin');
topbar();
?>
<div class="shell">
  <?php dash_side('admin', 'restaurants'); ?>
  <main class="main">
    <div class="page-head"><div><div class="mono-k"><?= count($rows) ?> total</div><h1 class="h1" style="margin-top:6px">Restaurants.</h1></div></div>

    <div class="two-col">
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Restaurant</th><th>Owner</th><th>Menu</th><th>Orders</th><th>Revenue</th><th>Commission</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td>
                <b><?= e($r['name']) ?></b>
                <div class="xs dim"><?= e($r['cuisine']) ?></div>
                <?php if (!$r['is_approved']): ?><span class="badge badge--warn">Awaiting approval</span>
                <?php elseif (!$r['is_open']): ?><span class="badge">Closed</span>
                <?php else: ?><span class="badge badge--ok">Live</span><?php endif; ?>
              </td>
              <td class="sm"><?= e($r['owner'] ?? '—') ?><div class="xs dim"><?= e($r['owner_email'] ?? '') ?></div></td>
              <td class="num"><?= (int) $r['dishes'] ?></td>
              <td class="num"><?= (int) $r['orders'] ?></td>
              <td class="num"><?= money((float) $r['rev']) ?></td>
              <td>
                <form method="post" class="row gap" style="flex-wrap:nowrap">
                  <?= csrf_field() ?>
                  <input type="hidden" name="do" value="commission"><input type="hidden" name="id" value="<?= $r['id'] ?>">
                  <input class="input" style="width:74px;min-height:34px;padding:4px 8px" type="number" step="0.5" min="0" max="40" name="pct" value="<?= rtrim(rtrim(number_format((float) $r['commission_pct'], 2), '0'), '.') ?>">
                  <button class="btn btn--ghost btn--sm">Set</button>
                </form>
              </td>
              <td class="right">
                <form method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= $r['id'] ?>">
                  <?php if ($r['is_approved']): ?>
                    <input type="hidden" name="do" value="suspend">
                    <button class="btn btn--danger btn--sm" data-confirm="Suspend <?= e($r['name']) ?>?">Suspend</button>
                  <?php else: ?>
                    <input type="hidden" name="do" value="approve">
                    <button class="btn btn--primary btn--sm">Approve</button>
                  <?php endif; ?>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <aside class="sticky">
        <form class="card" method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="do" value="create">
          <h3 class="h3 mb">Onboard a restaurant</h3>
          <label class="field"><span>Restaurant name</span><input class="input" name="name" required></label>
          <label class="field"><span>Cuisine</span><input class="input" name="cuisine" placeholder="South Indian"></label>
          <label class="field"><span>Tagline</span><input class="input" name="tagline"></label>
          <label class="field"><span>Address</span><input class="input" name="address"></label>
          <label class="field"><span>Phone</span><input class="input" name="phone"></label>
          <hr style="border:0;border-top:1px solid var(--line);margin:16px 0">
          <label class="field"><span>Owner name</span><input class="input" name="owner"></label>
          <label class="field"><span>Owner login email</span><input class="input" type="email" name="email" required></label>
          <label class="field"><span>Temporary password</span><input class="input" name="password" placeholder="fetch123"></label>
          <button class="btn btn--primary btn--block">Create</button>
        </form>
      </aside>
    </div>
  </main>
</div>
<?php page_foot(); ?>
