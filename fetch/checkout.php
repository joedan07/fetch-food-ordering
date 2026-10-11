<?php
/**
 * Checkout. Prices are read from the database again here — never from the
 * browser — and the order plus its lines are written in one transaction so a
 * half-saved order can't exist.
 */
require_once __DIR__ . '/config.php';
$user = require_login();

$cart = active_cart();
if (!$cart || $cart['status'] !== 'open') {
    flash('Your cart is empty.', 'warn');
    redirect('index.php');
}
$me = cart_member($cart, false);
if (!$me || !$me['is_owner']) {
    flash('Only the person who started the cart can place the order.', 'warn');
    redirect('cart.php');
}

$snap = cart_snapshot($cart);
if (!$snap['count']) {
    flash('Add something to the cart first.', 'warn');
    redirect('restaurant.php?id=' . $cart['restaurant_id']);
}
$r = q1('SELECT * FROM restaurants WHERE id = ?', [$cart['restaurant_id']]);

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $address = trim($_POST['address'] ?? '');
    $pay     = ($_POST['payment'] ?? 'cod') === 'upi' ? 'upi' : 'cod';

    if (mb_strlen($address) < 6) {
        $error = 'Please give an address we can actually find.';
    } elseif (!$r['is_open']) {
        $error = $r['name'] . ' has just closed. Your cart is saved.';
    } else {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            // Re-read the menu: prices or availability may have changed while
            // the cart was open.
            $lines = q(
                'SELECT ci.qty, m.name AS member_name, i.id, i.name, i.price, i.prep_minutes, i.is_available
                   FROM cart_items ci
                   JOIN cart_members m ON m.id = ci.member_id
                   JOIN menu_items i  ON i.id = ci.item_id
                  WHERE ci.cart_id = ?',
                [$cart['id']]
            );
            $gone = array_filter($lines, fn($l) => !$l['is_available']);
            if ($gone) {
                throw new RuntimeException('Sold out while you were deciding: ' . implode(', ', array_column($gone, 'name')));
            }

            $subtotal = 0;
            foreach ($lines as $l) {
                $subtotal += (float) $l['price'] * (int) $l['qty'];
            }
            $total = $subtotal + DELIVERY_FEE;

            // The promise. Worked out from the kitchen's queue right now and
            // stored, so "late" is measurable later.
            $eta  = eta_breakdown((int) $r['id'], array_map(fn($l) => ['prep_minutes' => $l['prep_minutes'], 'qty' => $l['qty']], $lines));
            $cook = cook_minutes_for(array_map(fn($l) => ['prep_minutes' => $l['prep_minutes'], 'qty' => $l['qty']], $lines));

            $code = 'FT-' . random_int(1000, 9999);
            while (q1('SELECT id FROM orders WHERE code = ?', [$code])) {
                $code = 'FT-' . random_int(1000, 9999);
            }

            $orderId = insert(
                'INSERT INTO orders (code, restaurant_id, user_id, cart_id, is_group, status, subtotal, delivery_fee, total,
                                     address, payment_mode, cook_minutes, queue_wait_min, load_pct, promised_min, promised_at)
                 VALUES (?,?,?,?,?,"placed",?,?,?,?,?,?,?,?,?, DATE_ADD(NOW(), INTERVAL ? MINUTE))',
                [$code, $r['id'], $user['id'], $cart['id'], $cart['is_group'], $subtotal, DELIVERY_FEE, $total,
                 $address, $pay, $cook, $eta['queue_wait'], $eta['load']['load_pct'], $eta['total'], $eta['total']]
            );
            foreach ($lines as $l) {
                exec_sql(
                    'INSERT INTO order_items (order_id, item_id, name, unit_price, qty, member_name) VALUES (?,?,?,?,?,?)',
                    [$orderId, $l['id'], $l['name'], $l['price'], $l['qty'], $l['member_name']]
                );
            }
            exec_sql('INSERT INTO order_events (order_id, status, note) VALUES (?, "placed", ?)',
                [$orderId, 'Promised in ' . $eta['total'] . ' min (' . $eta['queue_wait'] . ' queue + ' . $eta['cook'] . ' cook + ' . $eta['ride'] . ' ride)']);
            exec_sql("UPDATE carts SET status = 'ordered' WHERE id = ?", [$cart['id']]);

            $pdo->commit();
            unset($_SESSION['cart_id']);
            flash('Order ' . $code . ' is in. The kitchen has it.');
            redirect('order.php?id=' . $orderId);
        } catch (Throwable $e) {
            $pdo->rollBack();
            $error = $e instanceof RuntimeException ? $e->getMessage() : 'Could not place the order. Try again.';
        }
    }
}

page_head('Checkout');
topbar();
?>
<main class="wrap section" style="max-width:980px">
  <div class="mono-k">Checkout · <?= e($r['name']) ?></div>
  <h1 class="h1" style="margin:8px 0 22px">Last <span class="serif">look.</span></h1>

  <?php if ($error): ?><div class="alert alert--err"><?= e($error) ?></div><?php endif; ?>

  <form method="post" class="two-col">
    <?= csrf_field() ?>
    <div>
      <div class="card">
        <h3 class="h3 mb">Where is it going?</h3>
        <label class="field"><span>Delivery address</span>
          <textarea class="input" name="address" required minlength="6"><?= e($_POST['address'] ?? $user['address']) ?></textarea>
        </label>
        <label class="field"><span>Phone</span>
          <input class="input" value="<?= e($user['phone']) ?>" disabled>
        </label>

        <h3 class="h3 mb mt-lg">How are you paying?</h3>
        <div class="radio-row">
          <label><input type="radio" name="payment" value="cod" checked> <span><b>Cash on delivery</b><br><span class="xs dim">Pay the rider</span></span></label>
          <label><input type="radio" name="payment" value="upi"> <span><b>UPI</b><br><span class="xs dim">Demo mode — no real money moves</span></span></label>
        </div>
      </div>

      <div class="card mt">
        <h3 class="h3 mb">Your order</h3>
        <?php foreach ($snap['lines'] as $l): ?>
          <div class="split-row">
            <span><?= (int) $l['qty'] ?>× <?= e($l['name']) ?>
              <?php if ($cart['is_group']): ?><span class="xs dim">· <?= e($l['member_name']) ?></span><?php endif; ?>
            </span>
            <b><?= money((float) $l['price'] * (int) $l['qty']) ?></b>
          </div>
        <?php endforeach; ?>
      </div>

      <?php if ($cart['is_group']): ?>
        <div class="card mt">
          <h3 class="h3 mb">Split</h3>
          <?php foreach ($snap['split'] as $name => $s): ?>
            <div class="split-row"><span><?= e($name) ?></span><b><?= money($s['total']) ?></b></div>
          <?php endforeach; ?>
          <p class="xs dim mt">You pay the rider <?= money($snap['total']) ?>; this is what each person owes you.</p>
        </div>
      <?php endif; ?>
    </div>

    <aside class="sticky">
      <div class="card">
        <div class="mono-k">Promised delivery</div>
        <div class="countdown"><?= $snap['eta']['total'] ?><small>min</small></div>
        <div class="eta-bar">
          <i class="q" style="width:<?= $snap['eta']['queue_wait'] / max(1, $snap['eta']['total']) * 100 ?>%"></i>
          <i class="c" style="width:<?= $snap['eta']['cook'] / max(1, $snap['eta']['total']) * 100 ?>%"></i>
          <i class="r" style="width:<?= $snap['eta']['ride'] / max(1, $snap['eta']['total']) * 100 ?>%"></i>
        </div>
        <div class="eta-key">
          <span><i style="background:var(--saffron)"></i><?= $snap['eta']['queue_wait'] ?> queue</span>
          <span><i style="background:var(--ember)"></i><?= $snap['eta']['cook'] ?> cooking</span>
          <span><i style="background:var(--basil)"></i><?= $snap['eta']['ride'] ?> ride</span>
        </div>
        <p class="xs dim mt">Worked out from <?= $snap['eta']['load']['orders_cooking'] ?> orders already in this kitchen.
          We save this number and measure ourselves against it.</p>

        <div class="mt-lg">
          <div class="split-row"><span>Subtotal</span><b><?= money($snap['subtotal']) ?></b></div>
          <div class="split-row"><span>Delivery</span><b><?= money($snap['fee']) ?></b></div>
          <div class="split-row"><span><b>Pay now</b></span><b><?= money($snap['total']) ?></b></div>
        </div>
        <button class="btn btn--primary btn--block mt">Place order · <?= money($snap['total']) ?></button>
        <a class="btn btn--ghost btn--block mt" href="<?= base_url('cart.php') ?>">Back to cart</a>
      </div>
    </aside>
  </form>
</main>
<?php page_foot(); ?>
