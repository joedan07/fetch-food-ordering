<?php
/** GET — the live board for one kitchen: active tickets plus the load meter. */
require_once __DIR__ . '/../config.php';
$user = require_role('restaurant');
$rid  = (int) $user['restaurant_id'];

$orders = q(
    "SELECT o.*, u.name AS customer, u.phone
       FROM orders o JOIN users u ON u.id = o.user_id
      WHERE o.restaurant_id = ? AND o.status IN ('placed','accepted','preparing','ready','out_for_delivery')
      ORDER BY FIELD(o.status,'placed','accepted','preparing','ready','out_for_delivery'), o.created_at",
    [$rid]
);

$tickets = [];
foreach ($orders as $o) {
    $p = promise_state($o);
    $tickets[] = [
        'id'       => (int) $o['id'],
        'code'     => $o['code'],
        'status'   => $o['status'],
        'label'    => status_label($o['status']),
        'customer' => $o['customer'],
        'is_group' => (int) $o['is_group'],
        'total'    => (float) $o['total'],
        'waiting'  => (int) round((time() - strtotime($o['created_at'])) / 60),
        'left'     => $p['minutes'],
        'late'     => $p['late'],
        'cook'     => (int) $o['cook_minutes'],
        'items'    => q('SELECT name, qty, member_name FROM order_items WHERE order_id = ?', [$o['id']]),
    ];
}

json_out([
    'tickets' => $tickets,
    'load'    => kitchen_load($rid),
    'today'   => q1(
        "SELECT COUNT(*) orders, COALESCE(SUM(total),0) revenue
           FROM orders WHERE restaurant_id = ? AND DATE(created_at) = CURDATE() AND status <> 'cancelled'",
        [$rid]
    ),
]);
