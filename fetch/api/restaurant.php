<?php
/** POST — small switches a kitchen flips from its dashboard. */
require_once __DIR__ . '/../config.php';
$user = require_role('restaurant', 'admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['error' => 'POST only'], 405);
}
csrf_check();

$in  = json_in();
$rid = (int) ($user['restaurant_id'] ?? 0);

switch ($in['action'] ?? '') {
    case 'open':
        exec_sql('UPDATE restaurants SET is_open = ? WHERE id = ?', [(int) !empty($in['is_open']), $rid]);
        json_out(['ok' => true]);

    case 'availability': {
        $item = q1('SELECT id FROM menu_items WHERE id = ? AND restaurant_id = ?', [(int) ($in['item_id'] ?? 0), $rid]);
        if (!$item) {
            json_out(['error' => 'Not your dish.'], 403);
        }
        exec_sql('UPDATE menu_items SET is_available = ? WHERE id = ?', [(int) !empty($in['available']), $item['id']]);
        json_out(['ok' => true]);
    }

    default:
        json_out(['error' => 'Unknown action.'], 400);
}
