<?php
/** Join a shared cart — by link (?code=ABC123) or by typing the code. */
require_once __DIR__ . '/config.php';

$code  = strtoupper(trim($_GET['code'] ?? $_POST['code'] ?? ''));
$cart  = $code ? cart_by_code($code) : null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!$cart) {
        $error = 'No cart has that code. Check the six letters again.';
    } elseif (!$cart['is_group']) {
        $error = 'That cart has not been shared.';
    } elseif ($cart['status'] !== 'open') {
        $error = 'That cart has already been ordered.';
    } else {
        if (!is_logged_in()) {
            $name = trim($_POST['name'] ?? '');
            if (mb_strlen($name) < 2) {
                $error = 'Type the name your friends will recognise.';
            } else {
                remember_guest_name($name);
            }
        }
        if (!$error) {
            cart_member($cart);                 // creates the membership
            $_SESSION['cart_id'] = $cart['id'];
            flash('You are in. Add whatever you want.');
            redirect('cart.php?code=' . $cart['code']);
        }
    }
}

$preview = null;
if ($cart && $cart['is_group'] && $cart['status'] === 'open') {
    $preview = [
        'restaurant' => q1('SELECT name, cuisine FROM restaurants WHERE id = ?', [$cart['restaurant_id']]),
        'members'    => q('SELECT name, is_owner FROM cart_members WHERE cart_id = ? ORDER BY is_owner DESC', [$cart['id']]),
        'items'      => (int) (q1('SELECT COALESCE(SUM(qty),0) c FROM cart_items WHERE cart_id = ?', [$cart['id']])['c'] ?? 0),
    ];
}

page_head('Join a group cart', 'plain-page');
?>
<div class="auth-wrap">
  <div class="card">
    <a class="logo" href="<?= base_url('index.php') ?>"><b>fetch</b><i>(</i>'/food'<i>)</i></a>
    <h1 class="h2" style="margin:16px 0 4px">Join a <span class="serif">group cart.</span></h1>
    <p class="muted sm">Someone started a cart and shared the code. Add your own food to it — you pay for your items, the delivery splits.</p>

    <?php if ($error): ?><div class="alert alert--err"><?= e($error) ?></div><?php endif; ?>

    <?php if ($preview): ?>
      <div class="alert alert--ok">
        <b><?= e($preview['restaurant']['name']) ?></b> ·
        <?= count($preview['members']) ?> <?= count($preview['members']) === 1 ? 'person' : 'people' ?> ·
        <?= $preview['items'] ?> item<?= $preview['items'] === 1 ? '' : 's' ?> so far
        <div class="member-chips mt">
          <?php foreach ($preview['members'] as $m): ?>
            <span class="member"><span class="avatar"><?= e(initials($m['name'])) ?></span><?= e($m['name']) ?><?= $m['is_owner'] ? ' <span class="xs dim">· host</span>' : '' ?></span>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <form method="post" class="mt">
      <?= csrf_field() ?>
      <label class="field"><span>Cart code</span>
        <input class="input mono" name="code" required maxlength="6" placeholder="E.G. K7QP2M"
               style="text-transform:uppercase;letter-spacing:.3em;font-size:19px" value="<?= e($code) ?>">
      </label>
      <?php if (!is_logged_in()): ?>
        <label class="field"><span>Your name</span>
          <input class="input" name="name" required minlength="2" placeholder="Aarav" value="<?= e(guest_name() ?? '') ?>">
        </label>
        <p class="xs dim mb">No account needed to add food to someone else's cart — the host checks out for everyone.</p>
      <?php endif; ?>
      <button class="btn btn--primary btn--block">Join the cart</button>
    </form>

    <p class="sm muted mt center">Want your own cart? <a href="<?= base_url('index.php') ?>" style="color:var(--ember)">Browse restaurants</a></p>
  </div>
</div>
<?php page_foot(); ?>
