<?php
/** GET — what a rider sees: unclaimed ready orders and their own counts. */
require_once __DIR__ . '/../config.php';
$user = require_role('rider');

$rows = q(
    "SELECT o.*, r.name AS restaurant, u.name AS customer,
            (SELECT COALESCE(SUM(qty),0) FROM order_items oi WHERE oi.order_id = o.id) AS items
       FROM orders o JOIN restaurants r ON r.id = o.restaurant_id JOIN users u ON u.id = o.user_id
      WHERE o.status = 'ready' AND o.rider_id IS NULL
      ORDER BY o.promised_at"
);

$available = array_map(function ($o) {
    $p = promise_state($o);
    return [
        'id' => (int) $o['id'], 'code' => $o['code'], 'restaurant' => $o['restaurant'],
        'customer' => $o['customer'], 'address' => $o['address'], 'items' => (int) $o['items'],
        'total' => (float) $o['total'], 'payment' => $o['payment_mode'],
        'left' => $p['minutes'], 'late' => $p['late'],
    ];
}, $rows);

json_out([
    'available'  => $available,
    'mine'       => (int) (q1("SELECT COUNT(*) c FROM orders WHERE rider_id = ? AND status IN ('ready','out_for_delivery')", [$user['id']])['c'] ?? 0),
    'done_today' => (int) (q1("SELECT COUNT(*) c FROM orders WHERE rider_id = ? AND status = 'delivered' AND DATE(delivered_at) = CURDATE()", [$user['id']])['c'] ?? 0),
]);
