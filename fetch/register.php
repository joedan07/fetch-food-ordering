<?php
require_once __DIR__ . '/config.php';

if (is_logged_in()) {
    redirect(home_for(user_role()));
}

$errors = [];
$old    = ['name' => '', 'email' => '', 'phone' => '', 'address' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($old as $k => $_) {
        $old[$k] = trim($_POST[$k] ?? '');
    }
    $pass  = $_POST['password'] ?? '';
    $pass2 = $_POST['password2'] ?? '';

    // Checked in the browser too, but never trusted from there.
    if (mb_strlen($old['name']) < 2)                                   $errors[] = 'Tell us your name.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL))             $errors[] = 'That email does not look right.';
    if (!preg_match('/^[0-9]{10}$/', $old['phone']))                   $errors[] = 'Phone should be 10 digits.';
    if (strlen($pass) < 6)                                             $errors[] = 'Password needs at least 6 characters.';
    if ($pass !== $pass2)                                              $errors[] = 'The two passwords are different.';
    if (!$errors && q1('SELECT id FROM users WHERE email = ?', [strtolower($old['email'])])) {
        $errors[] = 'That email is already registered.';
    }

    if (!$errors) {
        $id = insert(
            'INSERT INTO users (name, email, phone, address, password_hash, role) VALUES (?,?,?,?,?,"customer")',
            [$old['name'], strtolower($old['email']), $old['phone'], $old['address'], password_hash($pass, PASSWORD_DEFAULT)]
        );
        login_user(['id' => $id]);
        flash('Welcome to Fetch, ' . $old['name'] . '.');
        redirect('index.php');
    }
}

page_head('Create an account', 'plain-page');
?>
<div class="auth-wrap">
  <div class="card">
    <a class="logo" href="<?= base_url('index.php') ?>"><b>fetch</b><i>(</i>'/food'<i>)</i></a>
    <h1 class="h2" style="margin:16px 0 4px">Create your account.</h1>
    <p class="muted sm">Customer accounts order food. Restaurants and riders are set up by the admin.</p>

    <?php if ($errors): ?>
      <div class="alert alert--err"><?= implode('<br>', array_map('e', $errors)) ?></div>
    <?php endif; ?>

    <form method="post" class="mt" novalidate>
      <?= csrf_field() ?>
      <label class="field"><span>Full name</span>
        <input class="input" name="name" required minlength="2" value="<?= e($old['name']) ?>">
      </label>
      <label class="field"><span>Email</span>
        <input class="input" type="email" name="email" required value="<?= e($old['email']) ?>">
      </label>
      <label class="field"><span>Phone</span>
        <input class="input" name="phone" required pattern="[0-9]{10}" inputmode="numeric" placeholder="10 digits" value="<?= e($old['phone']) ?>">
      </label>
      <label class="field"><span>Delivery address</span>
        <input class="input" name="address" placeholder="Hostel B, Room 214" value="<?= e($old['address']) ?>">
      </label>
      <div class="row gap">
        <label class="field grow"><span>Password</span>
          <input class="input" type="password" name="password" required minlength="6">
        </label>
        <label class="field grow"><span>Repeat</span>
          <input class="input" type="password" name="password2" required minlength="6">
        </label>
      </div>
      <button class="btn btn--primary btn--block">Create account</button>
    </form>

    <p class="sm muted mt center">Already have one? <a href="<?= base_url('login.php') ?>" style="color:var(--ember)">Sign in</a></p>
  </div>
</div>
<?php page_foot(); ?>
