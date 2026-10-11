<?php
/**
 * POST { action: "claim", order_id } — a rider picks up a ready order.
 * Claiming is a single conditional UPDATE, so two riders tapping at the same
 * moment can never both get the same order.
 */
require_once __DIR__ . '/../config.php';
$user = require_role('rider');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['error' => 'POST only'], 405);
}
csrf_check();

$in = json_in();
if (($in['action'] ?? '') !== 'claim') {
    json_out(['error' => 'Unknown action.'], 400);
}

$id   = (int) ($in['order_id'] ?? 0);
$rows = exec_sql(
    "UPDATE orders SET rider_id = ? WHERE id = ? AND rider_id IS NULL AND status = 'ready'",
    [$user['id'], $id]
);
if (!$rows) {
    json_out(['error' => 'Another rider got there first.'], 409);
}
exec_sql('INSERT INTO order_events (order_id, status, note) VALUES (?, "ready", ?)', [$id, $user['name'] . ' picked it up']);

json_out(['ok' => true]);
