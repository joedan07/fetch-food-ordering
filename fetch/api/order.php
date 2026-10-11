<?php
/** GET ?id= — live state of one order, for the tracking page. */
require_once __DIR__ . '/../config.php';
$user = require_login();

$id = (int) ($_GET['id'] ?? 0);
$o  = q1('SELECT o.*, r.name AS restaurant, u.name AS rider_name, u.phone AS rider_phone
            FROM orders o
            JOIN restaurants r ON r.id = o.restaurant_id
       LEFT JOIN users u       ON u.id = o.rider_id
           WHERE o.id = ?', [$id]);

if (!$o) {
    json_out(['error' => 'No such order.'], 404);
}
// Customers see their own; staff involved with the order can see it too.
$allowed = (int) $o['user_id'] === (int) $user['id']
    || $user['role'] === 'admin'
    || ($user['role'] === 'restaurant' && (int) $user['restaurant_id'] === (int) $o['restaurant_id'])
    || ($user['role'] === 'rider' && (int) $o['rider_id'] === (int) $user['id']);
if (!$allowed) {
    json_out(['error' => 'Not your order.'], 403);
}

$promise = promise_state($o);

json_out([
    'status'       => $o['status'],
    'status_label' => status_label($o['status']),
    'step'         => array_search($o['status'], ORDER_STATUSES, true),
    'minutes'      => $promise['minutes'],
    'late'         => $promise['late'],
    'done'         => $promise['done'],
    'promised_min' => (int) $o['promised_min'],
    'rider'        => $o['rider_name'] ? ['name' => $o['rider_name'], 'phone' => $o['rider_phone']] : null,
    'events'       => array_map(
        fn($ev) => ['status' => $ev['status'], 'label' => status_label($ev['status']), 'note' => $ev['note'], 'at' => date('g:i a', strtotime($ev['created_at']))],
        q('SELECT * FROM order_events WHERE order_id = ? ORDER BY id', [$id])
    ),
]);
