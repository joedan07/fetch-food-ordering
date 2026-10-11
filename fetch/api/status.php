<?php
/**
 * POST { order_id, status } — move an order along the pipeline.
 * The kitchen owns placed → ready, the rider owns the road, and nobody can
 * skip backwards. Every change is written to order_events as well, which is
 * what the customer's timeline reads.
 */
require_once __DIR__ . '/../config.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['error' => 'POST only'], 405);
}
csrf_check();

$in     = json_in();
$id     = (int) ($in['order_id'] ?? 0);
$next   = (string) ($in['status'] ?? '');
$order  = q1('SELECT * FROM orders WHERE id = ?', [$id]);

if (!$order) {
    json_out(['error' => 'No such order.'], 404);
}

$kitchenSteps = ['accepted', 'preparing', 'ready', 'cancelled'];
$riderSteps   = ['out_for_delivery', 'delivered'];

$isKitchen = $user['role'] === 'restaurant' && (int) $user['restaurant_id'] === (int) $order['restaurant_id'];
$isRider   = $user['role'] === 'rider' && (int) $order['rider_id'] === (int) $user['id'];
$isAdmin   = $user['role'] === 'admin';

$allowed = $isAdmin
    || ($isKitchen && in_array($next, $kitchenSteps, true))
    || ($isRider && in_array($next, $riderSteps, true));
if (!$allowed) {
    json_out(['error' => 'You cannot make that change.'], 403);
}

if ($next !== 'cancelled') {
    $from = array_search($order['status'], ORDER_STATUSES, true);
    $to   = array_search($next, ORDER_STATUSES, true);
    if ($to === false || $to <= $from) {
        json_out(['error' => 'That order is already ' . status_label($order['status']) . '.'], 409);
    }
}
if (in_array($order['status'], ['delivered', 'cancelled'], true)) {
    json_out(['error' => 'This order is finished.'], 409);
}

// Timestamps we measure the promise against.
$stamp = match ($next) {
    'accepted'  => 'accepted_at',
    'ready'     => 'ready_at',
    'delivered' => 'delivered_at',
    default     => null,
};
$sql = 'UPDATE orders SET status = ?' . ($stamp ? ", $stamp = NOW()" : '') . ' WHERE id = ?';
exec_sql($sql, [$next, $id]);

$note = null;
if ($next === 'delivered') {
    $o    = q1('SELECT * FROM orders WHERE id = ?', [$id]);
    $mins = (int) round((strtotime($o['delivered_at']) - strtotime($o['created_at'])) / 60);
    $note = $mins . ' min door to door, promised ' . (int) $o['promised_min'];
}
exec_sql('INSERT INTO order_events (order_id, status, note) VALUES (?,?,?)', [$id, $next, $note]);

json_out(['ok' => true, 'status' => $next, 'label' => status_label($next)]);
