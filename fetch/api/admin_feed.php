<?php
/** GET — live numbers for the admin overview. */
require_once __DIR__ . '/../config.php';
require_role('admin');

$restaurants = q('SELECT id, name FROM restaurants WHERE is_approved = 1 ORDER BY name');
$loads       = kitchen_loads(array_map('intval', array_column($restaurants, 'id')));

$kitchens = [];
foreach ($restaurants as $r) {
    $kitchens[] = ['name' => $r['name']] + $loads[(int) $r['id']];
}

$live = array_map(function ($o) {
    $p = promise_state($o);
    return ['code' => $o['code'], 'restaurant' => $o['restaurant'], 'status' => status_label($o['status']),
            'left' => $p['minutes'], 'late' => $p['late']];
}, q(
    "SELECT o.*, r.name AS restaurant FROM orders o JOIN restaurants r ON r.id = o.restaurant_id
      WHERE o.status IN ('placed','accepted','preparing','ready','out_for_delivery')
      ORDER BY o.promised_at LIMIT 12"
));

json_out(['kitchens' => $kitchens, 'live' => $live]);
