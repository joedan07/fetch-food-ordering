<?php
/**
 * USP 2 — the honest ETA.
 *
 * Instead of showing everyone the same "30–35 min", we work the wait out from
 * what the kitchen is actually holding right now:
 *
 *     queue wait  = work still on the stoves ÷ how many orders it cooks at once
 *     cook time   = the slowest dish in your cart (+ a little for each extra dish)
 *     ride time   = kitchen door to your door
 *
 * The number a customer sees before ordering is the same number we promise on
 * the order, so "late" is something the system can measure instead of guess.
 */

/** Work the kitchen is still holding, in minutes, and what that means. */
function kitchen_load(int $restaurantId): array
{
    $r = q1('SELECT max_parallel_orders, prep_buffer_min FROM restaurants WHERE id = ?', [$restaurantId]);
    $capacity = max(1, (int) ($r['max_parallel_orders'] ?? 3));
    $buffer   = (int) ($r['prep_buffer_min'] ?? 2);

    // Orders still needing the stove. Anything already cooked (ready / out /
    // delivered) no longer occupies the kitchen.
    $rows = q(
        "SELECT status, cook_minutes, accepted_at
           FROM orders
          WHERE restaurant_id = ? AND status IN ('placed','accepted','preparing')",
        [$restaurantId]
    );

    $pending = 0.0;
    foreach ($rows as $o) {
        $work = (float) $o['cook_minutes'];
        if ($o['status'] === 'preparing' && $o['accepted_at']) {
            // Part of this one is already done — only count what is left.
            $done = (time() - strtotime($o['accepted_at'])) / 60;
            $work = max(0.0, $work - $done);
        }
        $pending += $work;
    }

    $queueWait = (int) ceil($pending / $capacity);
    // A kitchen is "100% loaded" when every burner has ~20 minutes queued on it.
    $loadPct = (int) min(100, round($pending / ($capacity * 20) * 100));

    return [
        'orders_cooking' => count($rows),
        'pending_min'    => (int) ceil($pending),
        'capacity'       => $capacity,
        'buffer'         => $buffer,
        'queue_wait'     => $queueWait,
        'load_pct'       => $loadPct,
        'level'          => $loadPct < 30 ? 'free' : ($loadPct < 65 ? 'steady' : ($loadPct < 90 ? 'busy' : 'slammed')),
        'label'          => $loadPct < 30 ? 'Kitchen free' : ($loadPct < 65 ? 'Steady' : ($loadPct < 90 ? 'Busy' : 'Slammed')),
    ];
}

/**
 * How long this exact basket will take, broken down so the customer can see
 * where every minute comes from.
 *
 * @param array $items rows of ['prep_minutes' => int, 'qty' => int]
 */
function eta_breakdown(int $restaurantId, array $items, bool $delivery = true): array
{
    $load = kitchen_load($restaurantId);

    $slowest = 0;
    $extra   = 0;
    foreach ($items as $i) {
        $slowest = max($slowest, (int) $i['prep_minutes']);
        $qty     = max(1, (int) ($i['qty'] ?? 1));
        // Dishes cook alongside each other, but each extra plate still costs a little.
        $extra  += (int) ceil((int) $i['prep_minutes'] * 0.15 * $qty);
    }
    $cook  = $items ? $slowest + min($extra, 15) + $load['buffer'] : 0;
    $ride  = $delivery ? RIDE_MINUTES : 0;
    $total = $load['queue_wait'] + $cook + $ride;

    return [
        'queue_wait' => $load['queue_wait'],
        'cook'       => $cook,
        'ride'       => $ride,
        'total'      => (int) $total,
        'load'       => $load,
    ];
}

/** The cook time we store on an order, used later to price the queue. */
function cook_minutes_for(array $items): int
{
    $slowest = 0;
    $extra   = 0;
    foreach ($items as $i) {
        $slowest = max($slowest, (int) $i['prep_minutes']);
        $extra  += (int) ceil((int) $i['prep_minutes'] * 0.15 * max(1, (int) ($i['qty'] ?? 1)));
    }
    return (int) ($slowest + min($extra, 15));
}

/** Live view of one order's promise: minutes left, and whether we broke it. */
function promise_state(array $order): array
{
    $left = minutes_until($order['promised_at']);
    $done = in_array($order['status'], ['delivered', 'cancelled'], true);

    if ($done) {
        $actual = $order['delivered_at']
            ? (int) round((strtotime($order['delivered_at']) - strtotime($order['created_at'])) / 60)
            : null;
        return [
            'done'     => true,
            'late'     => $actual !== null && $actual > (int) $order['promised_min'],
            'minutes'  => $actual,
            'promised' => (int) $order['promised_min'],
        ];
    }
    return [
        'done'     => false,
        'late'     => $left !== null && $left < 0,
        'minutes'  => $left,
        'promised' => (int) $order['promised_min'],
    ];
}

/**
 * The same load calculation for several kitchens in one round trip.
 *
 * The landing page shows a meter per restaurant; asking the database twice per
 * restaurant was fine on localhost and slow across the internet, so this does
 * it with two queries no matter how many kitchens there are.
 *
 * @return array<int, array> keyed by restaurant id
 */
function kitchen_loads(array $restaurantIds): array
{
    if (!$restaurantIds) {
        return [];
    }
    $in  = implode(',', array_fill(0, count($restaurantIds), '?'));
    $out = [];

    $caps = q("SELECT id, max_parallel_orders, prep_buffer_min FROM restaurants WHERE id IN ($in)", $restaurantIds);
    $rows = q(
        "SELECT restaurant_id, status, cook_minutes, accepted_at
           FROM orders
          WHERE restaurant_id IN ($in) AND status IN ('placed','accepted','preparing')",
        $restaurantIds
    );

    $pending = [];
    $cooking = [];
    foreach ($rows as $o) {
        $rid  = (int) $o['restaurant_id'];
        $work = (float) $o['cook_minutes'];
        if ($o['status'] === 'preparing' && $o['accepted_at']) {
            $work = max(0.0, $work - (time() - strtotime($o['accepted_at'])) / 60);
        }
        $pending[$rid] = ($pending[$rid] ?? 0) + $work;
        $cooking[$rid] = ($cooking[$rid] ?? 0) + 1;
    }

    foreach ($caps as $r) {
        $id       = (int) $r['id'];
        $capacity = max(1, (int) $r['max_parallel_orders']);
        $work     = $pending[$id] ?? 0;
        $loadPct  = (int) min(100, round($work / ($capacity * 20) * 100));
        $out[$id] = [
            'orders_cooking' => $cooking[$id] ?? 0,
            'pending_min'    => (int) ceil($work),
            'capacity'       => $capacity,
            'buffer'         => (int) $r['prep_buffer_min'],
            'queue_wait'     => (int) ceil($work / $capacity),
            'load_pct'       => $loadPct,
            'level'          => $loadPct < 30 ? 'free' : ($loadPct < 65 ? 'steady' : ($loadPct < 90 ? 'busy' : 'slammed')),
            'label'          => $loadPct < 30 ? 'Kitchen free' : ($loadPct < 65 ? 'Steady' : ($loadPct < 90 ? 'Busy' : 'Slammed')),
        ];
    }
    return $out;
}
