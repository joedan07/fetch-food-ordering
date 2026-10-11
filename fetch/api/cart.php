<?php
/**
 * The cart endpoint. Everything the cart pages do — adding a dish, changing a
 * quantity, turning a cart into a group cart, joining one — comes through here
 * and gets the whole updated cart back, so every open phone can redraw itself.
 *
 * POST { action: "add|qty|remove|clear|make_group|join|leave|state", … }
 */
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['error' => 'POST only'], 405);
}
csrf_check();

$in     = json_in();
$action = $in['action'] ?? '';

try {
    switch ($action) {
        // ---------------------------------------------------------- joining
        case 'join': {
            $cart = cart_by_code((string) ($in['code'] ?? ''));
            if (!$cart)                       json_out(['error' => 'No cart with that code.'], 404);
            if (!$cart['is_group'])           json_out(['error' => 'That cart is not shared.'], 403);
            if ($cart['status'] !== 'open')   json_out(['error' => 'That cart is already checked out.'], 409);

            if (!is_logged_in()) {
                $name = trim((string) ($in['name'] ?? ''));
                if (mb_strlen($name) < 2) {
                    json_out(['error' => 'Type the name your friends will recognise.'], 422);
                }
                $_SESSION['guest_name'] = mb_substr($name, 0, 40);
            }
            $me = cart_member($cart);
            $_SESSION['cart_id'] = $cart['id'];
            json_out(['cart' => cart_json($cart, $me)]);
        }

        case 'state': {
            $cart = isset($in['code']) ? cart_by_code((string) $in['code']) : active_cart();
            if (!$cart) {
                json_out(['cart' => null]);
            }
            $me = cart_member($cart, false);
            json_out(['cart' => cart_json($cart, $me)]);
        }
    }

    // ------------------------------------------------- everything else needs a cart
    $cart = isset($in['code']) ? cart_by_code((string) $in['code']) : active_cart();

    if ($action === 'add' && !$cart) {
        // first dish of a brand-new cart — that needs an account
        if (!is_logged_in()) {
            json_out(['error' => 'Please sign in to start a cart.', 'login' => true], 401);
        }
        $item = q1('SELECT restaurant_id FROM menu_items WHERE id = ?', [(int) ($in['item_id'] ?? 0)]);
        if (!$item) {
            json_out(['error' => 'Unknown dish.'], 404);
        }
        $cart = get_or_create_cart((int) $item['restaurant_id'], (bool) ($in['group'] ?? false));
    }
    if (!$cart) {
        json_out(['error' => 'Your cart has expired.'], 404);
    }
    if ($cart['status'] !== 'open') {
        json_out(['error' => 'This cart has already been checked out.'], 409);
    }

    $me = cart_member($cart);
    if (!$me) {
        json_out(['error' => 'Join the cart first.'], 403);
    }

    switch ($action) {
        case 'add': {
            $itemId = (int) ($in['item_id'] ?? 0);
            $item   = q1('SELECT restaurant_id FROM menu_items WHERE id = ?', [$itemId]);
            if (!$item) {
                json_out(['error' => 'Unknown dish.'], 404);
            }
            // One restaurant per cart — ask before throwing the old one away.
            if ((int) $item['restaurant_id'] !== (int) $cart['restaurant_id']) {
                if (empty($in['replace'])) {
                    $other = q1('SELECT name FROM restaurants WHERE id = ?', [$cart['restaurant_id']]);
                    json_out(['confirm' => 'switch', 'error' => 'Your cart already has food from ' . $other['name'] . '. Start a new cart?'], 409);
                }
                exec_sql("UPDATE carts SET status = 'abandoned' WHERE id = ?", [$cart['id']]);
                unset($_SESSION['cart_id']);
                $cart = get_or_create_cart((int) $item['restaurant_id']);
                $me   = cart_member($cart);
            }
            cart_add($cart, $me, $itemId, max(1, (int) ($in['qty'] ?? 1)), $in['note'] ?? null);
            break;
        }

        case 'qty':
            cart_set_qty($cart, $me, (int) ($in['line_id'] ?? 0), (int) ($in['qty'] ?? 0));
            break;

        case 'remove':
            cart_set_qty($cart, $me, (int) ($in['line_id'] ?? 0), 0);
            break;

        case 'clear':
            if (!$me['is_owner']) {
                json_out(['error' => 'Only the person who started the cart can empty it.'], 403);
            }
            exec_sql('DELETE FROM cart_items WHERE cart_id = ?', [$cart['id']]);
            break;

        case 'make_group':
            if (!$me['is_owner']) {
                json_out(['error' => 'Only the cart owner can share it.'], 403);
            }
            exec_sql('UPDATE carts SET is_group = 1 WHERE id = ?', [$cart['id']]);
            $cart['is_group'] = 1;
            break;

        case 'leave':
            exec_sql('DELETE FROM cart_members WHERE id = ? AND is_owner = 0', [$me['id']]);
            unset($_SESSION['member_of'][$cart['id']], $_SESSION['cart_id']);
            json_out(['left' => true]);

        default:
            json_out(['error' => 'Unknown action.'], 400);
    }

    $cart = q1('SELECT * FROM carts WHERE id = ?', [$cart['id']]);
    json_out(['cart' => cart_json($cart, cart_member($cart, false))]);
} catch (RuntimeException $e) {
    json_out(['error' => $e->getMessage()], 422);
}
