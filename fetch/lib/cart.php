<?php
/**
 * USP 1 — the group cart.
 *
 * Every cart lives in the database, not in the browser, and every line in it
 * remembers which member added it. That is what makes one cart work across
 * several phones at once and what makes the bill split itself at the end.
 *
 * A normal single-person cart is just a group cart with one member, so there
 * is only one code path to maintain.
 */

/**
 * A long-lived id for this browser.
 *
 * Guests are recognised by this cookie rather than by the session. On a
 * serverless host two requests can be served at the same moment by different
 * machines, and the one that finishes last writes the whole session back — so a
 * value written during a join could be overwritten by a poll that started
 * before it. A cookie plus a column in the database has no such race, which is
 * what keeps a shared cart from forgetting who you are.
 */
function browser_token(): string
{
    if (!empty($_COOKIE['fetch_id'])) {
        return preg_replace('/[^a-f0-9]/', '', $_COOKIE['fetch_id']);
    }
    $token = bin2hex(random_bytes(16));
    $_COOKIE['fetch_id'] = $token;
    if (!headers_sent()) {
        setcookie('fetch_id', $token, [
            'expires'  => time() + 60 * 60 * 24 * 30,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    return $token;
}

/** The name a guest typed when joining, remembered in a cookie as well. */
function guest_name(): ?string
{
    return $_SESSION['guest_name'] ?? ($_COOKIE['fetch_name'] ?? null);
}

function remember_guest_name(string $name): void
{
    $name = mb_substr(trim($name), 0, 40);
    $_SESSION['guest_name'] = $name;
    if (!headers_sent()) {
        setcookie('fetch_name', $name, [
            'expires'  => time() + 60 * 60 * 24 * 30,
            'path'     => '/',
            'samesite' => 'Lax',
        ]);
    }
}

/**
 * The cart this browser is working on.
 *
 * The session is only a shortcut: if it is empty we ask the database which open
 * cart this person — account or browser token — is already a member of.
 */
function active_cart(): ?array
{
    $id = $_SESSION['cart_id'] ?? null;
    if ($id) {
        $cart = q1("SELECT * FROM carts WHERE id = ? AND status IN ('open','locked')", [$id]);
        if ($cart) {
            return $cart;
        }
        unset($_SESSION['cart_id']);
    }

    $user = current_user();
    $cart = $user
        ? q1("SELECT c.* FROM carts c JOIN cart_members m ON m.cart_id = c.id
               WHERE m.user_id = ? AND c.status = 'open' ORDER BY c.updated_at DESC LIMIT 1", [$user['id']])
        : q1("SELECT c.* FROM carts c JOIN cart_members m ON m.cart_id = c.id
               WHERE m.token = ? AND c.status = 'open' ORDER BY c.updated_at DESC LIMIT 1", [browser_token()]);

    if ($cart) {
        $_SESSION['cart_id'] = $cart['id'];
    }
    return $cart;
}

function cart_by_code(string $code): ?array
{
    return q1('SELECT * FROM carts WHERE code = ?', [strtoupper(trim($code))]);
}

function new_cart_code(): string
{
    do {
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= substr('ABCDEFGHJKLMNPQRSTUVWXYZ23456789', random_int(0, 31), 1);
        }
    } while (q1('SELECT id FROM carts WHERE code = ?', [$code]));
    return $code;
}

/** Start (or reuse) this customer's open cart for a restaurant. */
function get_or_create_cart(int $restaurantId, bool $group = false): array
{
    $user = require_login();
    $cart = active_cart();

    if ($cart && (int) $cart['restaurant_id'] === $restaurantId && $cart['status'] === 'open') {
        if ($group && !$cart['is_group']) {
            exec_sql('UPDATE carts SET is_group = 1 WHERE id = ?', [$cart['id']]);
            $cart['is_group'] = 1;
        }
        return $cart;
    }

    $id = insert(
        'INSERT INTO carts (code, owner_user_id, restaurant_id, is_group) VALUES (?,?,?,?)',
        [new_cart_code(), $user['id'], $restaurantId, $group ? 1 : 0]
    );
    insert('INSERT INTO cart_members (cart_id, user_id, token, name, is_owner) VALUES (?,?,?,?,1)',
        [$id, $user['id'], browser_token(), $user['name']]);
    $_SESSION['cart_id'] = $id;
    return q1('SELECT * FROM carts WHERE id = ?', [$id]);
}

/** The member row for whoever is using this browser, creating it if needed. */
function cart_member(array $cart, bool $create = true): ?array
{
    $user  = current_user();
    $token = browser_token();

    if ($user) {
        $m    = q1('SELECT * FROM cart_members WHERE cart_id = ? AND user_id = ?', [$cart['id'], $user['id']]);
        $name = $user['name'];
    } else {
        $m    = q1('SELECT * FROM cart_members WHERE cart_id = ? AND token = ? AND user_id IS NULL', [$cart['id'], $token]);
        $name = guest_name();
    }

    if ($m) {
        exec_sql('UPDATE cart_members SET seen_at = NOW(), token = ? WHERE id = ?', [$token, $m['id']]);
        $_SESSION['cart_id'] = $cart['id'];
        return $m;
    }
    if (!$create || !$name) {
        return null;
    }
    $id = insert(
        'INSERT INTO cart_members (cart_id, user_id, token, name, is_owner) VALUES (?,?,?,?,?)',
        [$cart['id'], $user['id'] ?? null, $token, $name, (int) ($user && (int) $cart['owner_user_id'] === (int) $user['id'])]
    );
    $_SESSION['cart_id'] = $cart['id'];
    return q1('SELECT * FROM cart_members WHERE id = ?', [$id]);
}

/** Everything a cart page needs: members, lines, per-person split and the ETA. */
function cart_snapshot(array $cart): array
{
    $members = q(
        'SELECT m.*, (m.seen_at > DATE_SUB(NOW(), INTERVAL 45 SECOND)) AS online
           FROM cart_members m WHERE m.cart_id = ? ORDER BY m.is_owner DESC, m.joined_at',
        [$cart['id']]
    );
    $lines = q(
        'SELECT ci.id, ci.qty, ci.note, ci.added_at, ci.member_id, m.name AS member_name,
                i.id AS item_id, i.name, i.price, i.is_veg, i.prep_minutes, i.is_available, i.image
           FROM cart_items ci
           JOIN cart_members m ON m.id = ci.member_id
           JOIN menu_items   i ON i.id = ci.item_id
          WHERE ci.cart_id = ?
          ORDER BY ci.added_at',
        [$cart['id']]
    );

    $subtotal = 0;
    $split    = [];
    foreach ($lines as $l) {
        $line      = (float) $l['price'] * (int) $l['qty'];
        $subtotal += $line;
        $split[$l['member_name']] = ($split[$l['member_name']] ?? 0) + $line;
    }
    $count = array_sum(array_column($lines, 'qty'));
    $fee   = $count ? DELIVERY_FEE : 0;

    // The delivery fee is shared equally; the food is paid for by whoever ordered it.
    $share = $split ? $fee / count($split) : 0;
    foreach ($split as $name => $amt) {
        $split[$name] = ['food' => $amt, 'fee' => $share, 'total' => $amt + $share];
    }

    return [
        'cart'     => $cart,
        'members'  => $members,
        'lines'    => $lines,
        'count'    => (int) $count,
        'subtotal' => $subtotal,
        'fee'      => $fee,
        'total'    => $subtotal + $fee,
        'split'    => $split,
        'eta'      => eta_breakdown((int) $cart['restaurant_id'], array_map(
            fn($l) => ['prep_minutes' => $l['prep_minutes'], 'qty' => $l['qty']],
            $lines
        )),
    ];
}

function cart_badge_count(): int
{
    $cart = active_cart();
    if (!$cart) {
        return 0;
    }
    return (int) (q1('SELECT COALESCE(SUM(qty),0) c FROM cart_items WHERE cart_id = ?', [$cart['id']])['c'] ?? 0);
}

/** Add a dish to a cart under one member's name. */
function cart_add(array $cart, array $member, int $itemId, int $qty = 1, ?string $note = null): void
{
    $item = q1('SELECT * FROM menu_items WHERE id = ? AND restaurant_id = ?', [$itemId, $cart['restaurant_id']]);
    if (!$item || !$item['is_available']) {
        throw new RuntimeException('That dish is not available right now.');
    }
    $existing = q1(
        'SELECT * FROM cart_items WHERE cart_id = ? AND member_id = ? AND item_id = ? AND note <=> ?',
        [$cart['id'], $member['id'], $itemId, $note]
    );
    if ($existing) {
        exec_sql('UPDATE cart_items SET qty = qty + ? WHERE id = ?', [$qty, $existing['id']]);
    } else {
        insert('INSERT INTO cart_items (cart_id, member_id, item_id, qty, note) VALUES (?,?,?,?,?)',
            [$cart['id'], $member['id'], $itemId, $qty, $note]);
    }
    touch_cart($cart);
}

/** Change a quantity. Members may only touch their own lines. */
function cart_set_qty(array $cart, array $member, int $lineId, int $qty): void
{
    $line = q1('SELECT * FROM cart_items WHERE id = ? AND cart_id = ?', [$lineId, $cart['id']]);
    if (!$line) {
        return;
    }
    $isOwner = (int) $member['is_owner'] === 1;
    if ((int) $line['member_id'] !== (int) $member['id'] && !$isOwner) {
        throw new RuntimeException('Only ' . 'the person who added it can change that line.');
    }
    if ($qty <= 0) {
        exec_sql('DELETE FROM cart_items WHERE id = ?', [$lineId]);
    } else {
        exec_sql('UPDATE cart_items SET qty = ? WHERE id = ?', [min($qty, 20), $lineId]);
    }
    touch_cart($cart);
}

function touch_cart(array $cart): void
{
    exec_sql('UPDATE carts SET updated_at = NOW() WHERE id = ?', [$cart['id']]);
}

/** A tiny activity feed, built from what is in the cart right now. */
function cart_feed(array $snapshot): array
{
    $feed = [];
    foreach ($snapshot['members'] as $m) {
        $feed[] = ['t' => $m['joined_at'], 'text' => $m['name'] . ($m['is_owner'] ? ' started the cart' : ' joined')];
    }
    foreach ($snapshot['lines'] as $l) {
        $feed[] = ['t' => $l['added_at'], 'text' => $l['member_name'] . ' added ' . $l['qty'] . '× ' . $l['name']];
    }
    usort($feed, fn($a, $b) => strcmp($b['t'], $a['t']));
    return array_slice($feed, 0, 12);
}

/** The whole cart as plain data — what the live cart pages poll for. */
function cart_json(array $cart, ?array $me = null): array
{
    $s = cart_snapshot($cart);
    $r = q1('SELECT name, is_open FROM restaurants WHERE id = ?', [$cart['restaurant_id']]);

    return [
        'code'       => $cart['code'],
        'status'     => $cart['status'],
        'is_group'   => (int) $cart['is_group'],
        'restaurant' => ['id' => (int) $cart['restaurant_id'], 'name' => $r['name'], 'is_open' => (int) $r['is_open']],
        'me'         => $me ? ['id' => (int) $me['id'], 'name' => $me['name'], 'is_owner' => (int) $me['is_owner']] : null,
        'members'    => array_map(fn($m) => [
            'id' => (int) $m['id'], 'name' => $m['name'], 'is_owner' => (int) $m['is_owner'], 'online' => (int) $m['online'],
        ], $s['members']),
        'lines'      => array_map(fn($l) => [
            'id' => (int) $l['id'], 'item_id' => (int) $l['item_id'], 'name' => $l['name'], 'qty' => (int) $l['qty'],
            'price' => (float) $l['price'], 'line_total' => (float) $l['price'] * (int) $l['qty'],
            'is_veg' => (int) $l['is_veg'], 'member' => $l['member_name'], 'member_id' => (int) $l['member_id'],
            'mine' => $me ? (int) $l['member_id'] === (int) $me['id'] : false,
        ], $s['lines']),
        'count'      => $s['count'],
        'subtotal'   => $s['subtotal'],
        'fee'        => $s['fee'],
        'total'      => $s['total'],
        'split'      => $s['split'],
        'eta'        => $s['eta'],
        'feed'       => array_map(fn($f) => ['text' => $f['text'], 'ago' => time_ago($f['t'])], cart_feed($s)),
    ];
}
