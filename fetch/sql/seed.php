<?php
/**
 * Demo data. Four open restaurants, one waiting for admin approval, menus with
 * real prep times, people for every role, and a week of orders — including a
 * few still cooking, so the kitchen-load meter has something to show.
 */

function seed_everything(): array
{
    $pass = password_hash('fetch123', PASSWORD_DEFAULT);
    $log  = [];

    // ---------------------------------------------------------- restaurants
    $restaurants = [
        ['Spice Route', 'North Indian', 'Tandoor, dal and everything in between', 'Block C, Food Court', 4, 3, 8.0, 1],
        ['Dosa Dock', 'South Indian', 'Batter fermented for 12 hours, not 12 minutes', 'Block A, Ground Floor', 5, 2, 7.5, 1],
        ['Wok This Way', 'Pan-Asian', 'Loud wok, louder flavours', 'Campus Plaza, Shop 7', 3, 3, 9.0, 1],
        ['Brew & Bite', 'Cafe & Bakes', 'Coffee that keeps the hostel awake', 'Library Lawn', 4, 2, 6.0, 1],
        ['Midnight Maggi Co.', 'Late night', 'Open when nothing else is', 'Hostel B Backgate', 2, 2, 8.0, 0],
    ];
    $rid = [];
    foreach ($restaurants as [$name, $cuisine, $tag, $addr, $cap, $buf, $comm, $approved]) {
        $rid[$name] = insert(
            'INSERT INTO restaurants (name, cuisine, tagline, address, phone, max_parallel_orders, prep_buffer_min, commission_pct, is_approved, is_open)
             VALUES (?,?,?,?,?,?,?,?,?,1)',
            [$name, $cuisine, $tag, $addr, '98' . random_int(10000000, 99999999), $cap, $buf, $comm, $approved]
        );
    }
    $log[] = count($restaurants) . ' restaurants (1 awaiting approval)';

    // ---------------------------------------------------------------- people
    $users = [
        ['Admin', 'admin@fetch.test', 'admin', null],
        ['Anita Rao', 'spice@fetch.test', 'restaurant', 'Spice Route'],
        ['Karthik Iyer', 'dosa@fetch.test', 'restaurant', 'Dosa Dock'],
        ['Lin Chen', 'wok@fetch.test', 'restaurant', 'Wok This Way'],
        ['Farah Khan', 'brew@fetch.test', 'restaurant', 'Brew & Bite'],
        ['Dev Malhotra', 'maggi@fetch.test', 'restaurant', 'Midnight Maggi Co.'],
        ['Ravi Kumar', 'ravi@fetch.test', 'rider', null],
        ['Sana Sheikh', 'sana@fetch.test', 'rider', null],
        ['Joe Daniel', 'joe@fetch.test', 'customer', null],
        ['Aarav Menon', 'aarav@fetch.test', 'customer', null],
        ['Meera Nair', 'meera@fetch.test', 'customer', null],
    ];
    $uid = [];
    foreach ($users as [$name, $email, $role, $rest]) {
        $uid[$email] = insert(
            'INSERT INTO users (name, email, phone, address, password_hash, role, restaurant_id) VALUES (?,?,?,?,?,?,?)',
            [$name, $email, '9' . random_int(100000000, 999999999), 'Hostel B, Room ' . random_int(101, 420), $pass, $role, $rest ? $rid[$rest] : null]
        );
    }
    $log[] = count($users) . ' accounts (password: fetch123)';

    // ----------------------------------------------------------------- menus
    // name, price, veg, prep minutes, description
    $menus = [
        'Spice Route' => [
            'Tandoor' => [
                ['Paneer Tikka', 220, 1, 18, 'Charred cubes, mint chutney on the side'],
                ['Chicken Tikka', 270, 0, 22, 'Half a bird, overnight yoghurt marinade'],
                ['Tandoori Mushroom', 210, 1, 16, 'Button mushrooms, ajwain, lemon'],
            ],
            'Mains' => [
                ['Chicken Biryani', 260, 0, 25, 'Sealed handi, served with raita'],
                ['Dal Makhani', 190, 1, 20, 'On the stove since morning'],
                ['Butter Paneer', 240, 1, 18, 'Tomato gravy, finished with cream'],
            ],
            'Breads' => [
                ['Butter Naan', 45, 1, 6, 'Straight off the tandoor wall'],
                ['Laccha Paratha', 55, 1, 8, 'Layers you can count'],
            ],
        ],
        'Dosa Dock' => [
            'Dosas' => [
                ['Masala Dosa', 90, 1, 10, 'Potato masala, two chutneys, sambar'],
                ['Ghee Roast', 120, 1, 12, 'Crisp enough to hear across the table'],
                ['Cheese Chilli Dosa', 140, 1, 13, 'Not traditional. Still gone in minutes'],
            ],
            'Idli & Vada' => [
                ['Idli (2 pcs)', 60, 1, 6, 'Steamed to order'],
                ['Medu Vada (2 pcs)', 70, 1, 9, 'Crisp outside, cloud inside'],
            ],
            'Rice' => [
                ['Curd Rice', 80, 1, 5, 'Tempered with curry leaves'],
                ['Bisi Bele Bath', 110, 1, 14, 'Lentils, rice, and a lot of ghee'],
            ],
        ],
        'Wok This Way' => [
            'Noodles & Rice' => [
                ['Hakka Noodles', 160, 1, 12, 'High flame, fast hands'],
                ['Chicken Fried Rice', 190, 0, 14, 'Egg, spring onion, dark soy'],
                ['Pad Thai', 230, 0, 16, 'Tamarind, peanuts, lime'],
            ],
            'Small plates' => [
                ['Veg Momos (6)', 120, 1, 15, 'Steamed, with schezwan dip'],
                ['Chilli Chicken', 220, 0, 17, 'Dry, extra green chilli on request'],
                ['Spring Rolls (4)', 130, 1, 11, 'Cabbage and carrot, fried to order'],
            ],
        ],
        'Brew & Bite' => [
            'Coffee' => [
                ['Cold Coffee', 120, 1, 6, 'Blended thick, no ice cream'],
                ['Filter Coffee', 60, 1, 4, 'Davara and tumbler'],
                ['Cappuccino', 140, 1, 5, 'Double shot by default'],
            ],
            'Bakes' => [
                ['Chocolate Brownie', 110, 1, 3, 'Warm, if you ask'],
                ['Veg Puff', 45, 1, 4, 'The 4 p.m. classic'],
                ['Grilled Sandwich', 130, 1, 9, 'Cheese, corn, a lot of butter'],
            ],
        ],
        'Midnight Maggi Co.' => [
            'Maggi' => [
                ['Classic Maggi', 60, 1, 7, 'Two minutes is a lie, it takes seven'],
                ['Cheese Maggi', 90, 1, 9, 'A whole cube, melted in'],
            ],
        ],
    ];

    $items = [];   // restaurant name => list of [id, price, prep]
    $count = 0;
    foreach ($menus as $rName => $cats) {
        $sort = 0;
        foreach ($cats as $cName => $dishes) {
            $cid = insert('INSERT INTO categories (restaurant_id, name, sort_order) VALUES (?,?,?)', [$rid[$rName], $cName, $sort++]);
            foreach ($dishes as [$dn, $price, $veg, $prep, $desc]) {
                $id = insert(
                    'INSERT INTO menu_items (restaurant_id, category_id, name, description, price, is_veg, prep_minutes) VALUES (?,?,?,?,?,?,?)',
                    [$rid[$rName], $cid, $dn, $desc, $price, $veg, $prep]
                );
                $items[$rName][] = ['id' => $id, 'name' => $dn, 'price' => $price, 'prep' => $prep];
                $count++;
            }
        }
    }
    $log[] = $count . ' dishes across ' . count($menus) . ' menus';

    // ---------------------------------------------------------------- orders
    $customers = [$uid['joe@fetch.test'], $uid['aarav@fetch.test'], $uid['meera@fetch.test']];
    $riders    = [$uid['ravi@fetch.test'], $uid['sana@fetch.test']];
    $names     = ['Joe Daniel', 'Aarav Menon', 'Meera Nair'];
    $open      = ['Spice Route', 'Dosa Dock', 'Wok This Way', 'Brew & Bite'];

    // A week of finished orders, so the dashboards have history.
    $seq = 1040;
    for ($d = 6; $d >= 0; $d--) {
        $perDay = random_int(2, 4);
        for ($n = 0; $n < $perDay; $n++) {
            $rName  = $open[array_rand($open)];
            $ci     = array_rand($customers);
            $placed = strtotime("-$d days " . random_int(11, 21) . ':' . random_int(0, 59));
            if ($placed > time()) {
                $placed = time() - 7200;
            }
            seed_order(
                $seq++,
                $rid[$rName],
                $customers[$ci],
                $names[$ci],
                pick_items($items[$rName], random_int(1, 3)),
                'delivered',
                $placed,
                $riders[array_rand($riders)],
                // roughly one order in eight runs late — the on-time stat needs misses too
                random_int(1, 8) === 1
            );
        }
    }

    // Live orders happening right now: these are what load the kitchens.
    seed_order($seq++, $rid['Spice Route'], $customers[0], $names[0], pick_items($items['Spice Route'], 2), 'preparing', time() - 480, null, false);
    seed_order($seq++, $rid['Spice Route'], $customers[1], $names[1], pick_items($items['Spice Route'], 1), 'accepted', time() - 180, null, false);
    seed_order($seq++, $rid['Dosa Dock'], $customers[2], $names[2], pick_items($items['Dosa Dock'], 2), 'placed', time() - 60, null, false);
    seed_order($seq++, $rid['Wok This Way'], $customers[0], $names[0], pick_items($items['Wok This Way'], 2), 'ready', time() - 900, null, false);
    $log[] = ($seq - 1040) . ' orders, including 4 in progress';

    // Ratings on the delivered ones.
    $done = q("SELECT id, restaurant_id, user_id, promised_min, created_at, delivered_at FROM orders WHERE status = 'delivered'");
    $comments = ['Still hot when it arrived.', 'Exactly the time it said. Rare.', 'Good food, packaging could be better.',
                 'Ran a bit late but the kitchen meter did warn me.', 'Best dosa on campus.', null];
    foreach ($done as $o) {
        if (random_int(1, 10) > 7) {
            continue;                       // not everyone reviews
        }
        $mins   = (int) round((strtotime($o['delivered_at']) - strtotime($o['created_at'])) / 60);
        $onTime = $mins <= (int) $o['promised_min'];
        exec_sql(
            'INSERT INTO reviews (order_id, restaurant_id, user_id, stars, on_time, comment) VALUES (?,?,?,?,?,?)',
            [$o['id'], $o['restaurant_id'], $o['user_id'], $onTime ? random_int(4, 5) : random_int(2, 4), $onTime ? 1 : 0, $comments[array_rand($comments)]]
        );
    }
    $log[] = 'Ratings added';

    return $log;
}

/** Pick a few random dishes from a menu. */
function pick_items(array $menu, int $n): array
{
    shuffle($menu);
    $out = [];
    foreach (array_slice($menu, 0, $n) as $m) {
        $out[] = $m + ['qty' => random_int(1, 2)];
    }
    return $out;
}

/** Write one order with its items and its timeline. */
function seed_order(int $seq, int $restaurantId, int $userId, string $userName, array $items, string $status, int $placedAt, ?int $riderId, bool $late): void
{
    $subtotal = 0;
    $slowest  = 0;
    $extra    = 0;
    foreach ($items as $i) {
        $subtotal += $i['price'] * $i['qty'];
        $slowest   = max($slowest, $i['prep']);
        $extra    += (int) ceil($i['prep'] * 0.15 * $i['qty']);
    }
    $cook     = $slowest + min($extra, 15);
    $queue    = random_int(0, 9);
    $promised = $queue + $cook + 2 + RIDE_MINUTES;
    $total    = $subtotal + DELIVERY_FEE;

    $fmt = fn(int $t) => date('Y-m-d H:i:s', $t);
    $acceptedAt = $deliveredAt = $readyAt = null;
    if (in_array($status, ['accepted', 'preparing', 'ready', 'out_for_delivery', 'delivered'], true)) {
        $acceptedAt = $fmt($placedAt + random_int(40, 180));
    }
    if (in_array($status, ['ready', 'out_for_delivery', 'delivered'], true)) {
        $readyAt = $fmt($placedAt + ($cook + $queue) * 60);
    }
    if ($status === 'delivered') {
        $actual      = $promised + ($late ? random_int(6, 18) : random_int(-9, 0));
        $deliveredAt = $fmt($placedAt + $actual * 60);
    }

    $orderId = insert(
        'INSERT INTO orders (code, restaurant_id, user_id, rider_id, status, subtotal, delivery_fee, total, address,
                             payment_mode, cook_minutes, queue_wait_min, load_pct, promised_min, promised_at,
                             accepted_at, ready_at, delivered_at, created_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        ['FT-' . $seq, $restaurantId, $userId, $riderId, $status, $subtotal, DELIVERY_FEE, $total,
         'Hostel B, Room ' . random_int(101, 420), random_int(0, 1) ? 'cod' : 'upi',
         $cook, $queue, min(100, $queue * 11), $promised, $fmt($placedAt + $promised * 60),
         $acceptedAt, $readyAt, $deliveredAt, $fmt($placedAt)]
    );

    foreach ($items as $i) {
        exec_sql(
            'INSERT INTO order_items (order_id, item_id, name, unit_price, qty, member_name) VALUES (?,?,?,?,?,?)',
            [$orderId, $i['id'], $i['name'], $i['price'], $i['qty'], $userName]
        );
    }

    $steps = ['placed' => $placedAt, 'accepted' => $acceptedAt ? strtotime($acceptedAt) : null,
              'preparing' => $acceptedAt ? strtotime($acceptedAt) + 60 : null, 'ready' => $readyAt ? strtotime($readyAt) : null,
              'out_for_delivery' => $readyAt ? strtotime($readyAt) + 120 : null,
              'delivered' => $deliveredAt ? strtotime($deliveredAt) : null];
    $reached = array_slice(ORDER_STATUSES, 0, array_search($status, ORDER_STATUSES, true) + 1);
    foreach ($reached as $s) {
        if ($steps[$s]) {
            exec_sql('INSERT INTO order_events (order_id, status, created_at) VALUES (?,?,?)', [$orderId, $s, date('Y-m-d H:i:s', $steps[$s])]);
        }
    }
}
