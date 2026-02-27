<?php
require_once __DIR__.'/../config/db.php';


function is_weekend($dt) { // $dt: DateTime
$w = (int)$dt->format('w'); // 0=Sun,6=Sat
return $w === 0 || $w === 6;
}


function pick_price_per_hour(DateTime $start, ?int $group_id = null): float {
    global $pdo;
    $dayType = is_weekend($start) ? 'weekend' : 'weekday';
    $time = $start->format('H:i:s');

    // ถ้ามี group_id ให้ค้นหากฎของ group นั้นก่อน
    if ($group_id !== null) {
        $stmt = $pdo->prepare("SELECT price_per_hour FROM pricing_rules
            WHERE group_id = :gid AND day_type = :dayType AND start_time <= :t AND end_time > :t2
            ORDER BY start_time DESC, id DESC LIMIT 1");
        $stmt->execute([':gid' => $group_id, ':dayType' => $dayType, ':t' => $time, ':t2' => $time]);
        $row = $stmt->fetch();
        if ($row) return (float)$row['price_per_hour'];
    }

    // Fallback: กฎ global (group_id IS NULL)
    $stmt = $pdo->prepare("SELECT price_per_hour FROM pricing_rules
        WHERE group_id IS NULL AND day_type = :dayType AND start_time <= :t AND end_time > :t2
        ORDER BY start_time DESC, id DESC LIMIT 1");
    $stmt->execute([':dayType' => $dayType, ':t' => $time, ':t2' => $time]);
    $row = $stmt->fetch();
    return $row ? (float)$row['price_per_hour'] : 0.0;
}


function pick_pricing_rule(DateTime $start, ?int $group_id = null): ?array {
    global $pdo;
    $dayType = is_weekend($start) ? 'weekend' : 'weekday';
    $time = $start->format('H:i:s');

    if ($group_id !== null) {
        $stmt = $pdo->prepare("SELECT * FROM pricing_rules
            WHERE group_id = :gid AND day_type = :dayType AND start_time <= :t AND end_time > :t2
            ORDER BY start_time DESC, id DESC LIMIT 1");
        $stmt->execute([':gid' => $group_id, ':dayType' => $dayType, ':t' => $time, ':t2' => $time]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) return $row;
    }

    $stmt = $pdo->prepare("SELECT * FROM pricing_rules
        WHERE group_id IS NULL AND day_type = :dayType AND start_time <= :t AND end_time > :t2
        ORDER BY start_time DESC, id DESC LIMIT 1");
    $stmt->execute([':dayType' => $dayType, ':t' => $time, ':t2' => $time]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}



function has_overlap($court_id, DateTime $start, int $hours, $exclude_booking_id = null): bool {
global $pdo;
$end = clone $start; $end->modify("+{$hours} hour");
$sql = "SELECT COUNT(*) AS c FROM bookings
WHERE court_id = :cid AND status='booked'
AND NOT( end_dt <= :s OR start_datetime >= :e )";
// เราจะคำนวณ end_dt แบบ runtime
$stmt = $pdo->prepare("SELECT id, start_datetime, duration_hours FROM bookings
WHERE court_id = :cid AND status='booked'");
$stmt->execute([':cid'=>$court_id]);
while ($b = $stmt->fetch()) {
if ($exclude_booking_id && (int)$b['id'] === (int)$exclude_booking_id) continue;
$bs = new DateTime($b['start_datetime']);
$be = (clone $bs)->modify("+".(int)$b['duration_hours']." hour");
if (!($be <= $start || $bs >= $end)) return true; // overlap
}
return false;
}


function compute_total(float $price_per_hour, int $hours, float $discount_amount): float {
$gross = $price_per_hour * $hours;
$total = max(0, $gross - $discount_amount);
return round($total, 2);
}