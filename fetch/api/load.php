<?php
/** GET — live kitchen load for every approved restaurant (drives the busy meters). */
require_once __DIR__ . '/../config.php';

$ids = array_map('intval', array_column(q('SELECT id FROM restaurants WHERE is_approved = 1'), 'id'));

$out = [];
foreach (kitchen_loads($ids) as $id => $load) {
    $out[$id] = [
        'level'       => $load['level'],
        'label'       => $load['label'],
        'load_pct'    => $load['load_pct'],
        'queue_wait'  => $load['queue_wait'],
        'cooking'     => $load['orders_cooking'],
        // what a typical two-dish order would be quoted right now
        'typical_eta' => $load['queue_wait'] + 12 + $load['buffer'] + RIDE_MINUTES,
    ];
}

json_out(['loads' => $out, 'at' => date('H:i:s')]);
