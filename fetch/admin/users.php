<?php
/** Admin — people on the platform, and adding riders. */
require_once __DIR__ . '/../config.php';
$admin = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $do = $_POST['do'] ?? '';

    if ($do === 'rider') {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $name  = trim($_POST['name'] ?? '');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('A rider needs a name and a valid email.', 'err');
        } elseif (q1('SELECT id FROM users WHERE email = ?', [$email])) {
            flash('That email is taken.', 'err');
        } else {
            insert('INSERT INTO users (name, email, phone, password_hash, role) VALUES (?,?,?,?,"rider")',
                [$name, $email, trim($_POST['phone'] ?? ''), password_hash($_POST['password'] ?: 'fetch123', PASSWORD_DEFAULT)]);
            flash('Rider account created.');
        }
    } elseif ($do === 'delete') {
        $id = (int) $_POST['id'];
        if ($id === (int) $admin['id']) {
            flash('You cannot delete your own account.', 'err');
        } else {
            exec_sql("DELETE FROM users WHERE id = ? AND role IN ('customer','rider')", [$id]);
            flash('Account removed.', 'warn');
        }
    }
    redirect('admin/users.php');
}

$role = $_GET['role'] ?? 'all';
$sql  = "SELECT u.*, r.name AS restaurant,
                (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) orders,
                (SELECT COUNT(*) FROM orders o WHERE o.rider_id = u.id AND o.status = 'delivered') deliveries
           FROM users u LEFT JOIN restaurants r ON r.id = u.restaurant_id WHERE 1";
$par = [];
if ($role !== 'all') { $sql .= ' AND u.role = ?'; $par[] = $role; }
$rows = q($sql . ' ORDER BY u.role, u.name', $par);

page_head('Users — Admin');
topbar();
?>
<div class="shell">
  <?php dash_side('admin', 'users'); ?>
  <main class="main">
    <div class="page-head">
      <div><div class="mono-k"><?= count($rows) ?> accounts</div><h1 class="h1" style="margin-top:6px">People.</h1></div>
      <div class="cat-nav">
        <?php foreach (['all' => 'Everyone', 'customer' => 'Customers', 'restaurant' => 'Restaurants', 'rider' => 'Riders', 'admin' => 'Admins'] as $k => $l): ?>
          <a href="?role=<?= $k ?>" style="<?= $role === $k ? 'border-color:var(--ember);color:var(--ember)' : '' ?>"><?= $l ?></a>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="two-col">
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Name</th><th>Role</th><th>Contact</th><th>Activity</th><th>Joined</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($rows as $u): ?>
            <tr>
              <td>
                <div class="row gap"><span class="avatar"><?= e(initials($u['name'])) ?></span>
                  <div><b><?= e($u['name']) ?></b><?php if ($u['restaurant']): ?><div class="xs dim"><?= e($u['restaurant']) ?></div><?php endif; ?></div>
                </div>
              </td>
              <td><span class="badge <?= $u['role'] === 'admin' ? 'badge--ember' : '' ?>"><?= e($u['role']) ?></span></td>
              <td class="sm dim"><?= e($u['email']) ?><div class="xs"><?= e($u['phone']) ?></div></td>
              <td class="num sm">
                <?= $u['role'] === 'rider' ? (int) $u['deliveries'] . ' delivered' : ((int) $u['orders'] . ' orders') ?>
              </td>
              <td class="sm dim"><?= date('j M y', strtotime($u['created_at'])) ?></td>
              <td class="right">
                <?php if (in_array($u['role'], ['customer', 'rider'], true)): ?>
                  <form method="post" data-confirm="Delete <?= e($u['name']) ?>? Their orders go too.">
                    <?= csrf_field() ?>
                    <input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= $u['id'] ?>">
                    <button class="btn btn--danger btn--sm">Delete</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <aside class="sticky">
        <form class="card" method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="do" value="rider">
          <h3 class="h3 mb">Add a rider</h3>
          <label class="field"><span>Name</span><input class="input" name="name" required></label>
          <label class="field"><span>Email</span><input class="input" type="email" name="email" required></label>
          <label class="field"><span>Phone</span><input class="input" name="phone"></label>
          <label class="field"><span>Temporary password</span><input class="input" name="password" placeholder="fetch123"></label>
          <button class="btn btn--primary btn--block">Create rider</button>
          <p class="xs dim mt">Restaurant accounts are created on the Restaurants page, together with the restaurant.</p>
        </form>
      </aside>
    </div>
  </main>
</div>
<?php page_foot(); ?>
