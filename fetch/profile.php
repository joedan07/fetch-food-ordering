<?php
require_once __DIR__ . '/config.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name    = trim($_POST['name'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    if (mb_strlen($name) >= 2 && preg_match('/^[0-9]{10}$/', $phone)) {
        exec_sql('UPDATE users SET name = ?, phone = ?, address = ? WHERE id = ?', [$name, $phone, $address, $user['id']]);
        flash('Profile saved.');
    } else {
        flash('Check your name and phone number.', 'err');
    }
    redirect('profile.php');
}

page_head('Profile');
topbar();
?>
<main class="wrap section" style="max-width:620px">
  <h1 class="h1">Your <span class="serif">details.</span></h1>
  <p class="muted mt">Used to deliver your orders and to label your items in a group cart.</p>

  <form method="post" class="card mt-lg">
    <?= csrf_field() ?>
    <label class="field"><span>Name</span><input class="input" name="name" value="<?= e($user['name']) ?>" required></label>
    <label class="field"><span>Email</span><input class="input" value="<?= e($user['email']) ?>" disabled></label>
    <label class="field"><span>Phone</span><input class="input" name="phone" pattern="[0-9]{10}" value="<?= e($user['phone']) ?>" required></label>
    <label class="field"><span>Default delivery address</span><textarea class="input" name="address"><?= e($user['address']) ?></textarea></label>
    <button class="btn btn--primary">Save</button>
  </form>
</main>
<?php page_foot(); ?>
