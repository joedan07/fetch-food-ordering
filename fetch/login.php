<?php
require_once __DIR__ . '/config.php';

if (is_logged_in()) {
    redirect(home_for(user_role()));
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $user = attempt_login($_POST['email'] ?? '', $_POST['password'] ?? '');
    if ($user) {
        flash('Signed in as ' . $user['name'] . '.');
        $next = $_SESSION['after_login'] ?? null;
        unset($_SESSION['after_login']);
        redirect($next ?: home_for($user['role']));
    }
    $error = 'That email and password do not match.';
}

$demo = [
    ['Customer', 'joe@fetch.test', 'Order food, group carts'],
    ['Restaurant', 'spice@fetch.test', 'Spice Route kitchen'],
    ['Rider', 'ravi@fetch.test', 'Delivery queue'],
    ['Admin', 'admin@fetch.test', 'The whole platform'],
];

page_head('Sign in', 'plain-page');
?>
<div class="auth-wrap">
  <div class="card">
    <a class="logo" href="<?= base_url('index.php') ?>"><b>fetch</b><i>(</i>'/food'<i>)</i></a>
    <h1 class="h2" style="margin:16px 0 4px">Welcome back.</h1>
    <p class="muted sm">Sign in to order, cook or deliver.</p>

    <?php if ($error): ?><div class="alert alert--err"><?= e($error) ?></div><?php endif; ?>

    <form method="post" class="mt">
      <?= csrf_field() ?>
      <label class="field"><span>Email</span>
        <input class="input" type="email" name="email" id="email" required autofocus value="<?= e($_POST['email'] ?? '') ?>">
      </label>
      <label class="field"><span>Password</span>
        <input class="input" type="password" name="password" id="password" required>
      </label>
      <button class="btn btn--primary btn--block">Sign in</button>
    </form>

    <p class="sm muted mt center">No account? <a href="<?= base_url('register.php') ?>" style="color:var(--ember)">Create one</a></p>

    <?php if (DEMO_MODE): ?>
      <hr style="border:0;border-top:1px solid var(--line);margin:22px 0 16px">
      <div class="mono-k mb">Demo logins — password fetch123</div>
      <div class="demo-grid">
        <?php foreach ($demo as [$label, $email, $note]): ?>
          <button type="button" onclick="useDemo('<?= $email ?>')">
            <b><?= $label ?></b><span><?= e($note) ?></span>
          </button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<script>
function useDemo(email) {
  document.getElementById('email').value = email;
  document.getElementById('password').value = 'fetch123';
  document.querySelector('form').submit();
}
</script>
<?php page_foot(); ?>
