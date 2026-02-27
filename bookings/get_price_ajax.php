<?php
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../includes/helpers.php';

header('Content-Type: application/json');

$court_id = isset($_GET['court_id']) ? (int)$_GET['court_id'] : 0;
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$time = isset($_GET['time']) ? $_GET['time'] : '08:00';

$response = [
    'success' => false,
    'price' => 0,
    'rule' => null
];

try {
    if ($court_id > 0 && $date && $time) {
        $courtStmt = $pdo->prepare('SELECT court_type, is_vip, vip_price, normal_price, pricing_group_id FROM courts WHERE id = ?');
        $courtStmt->execute([$court_id]);
        $court = $courtStmt->fetch();

        if ($court) {
            $isVip    = ($court['court_type'] === 'vip' || $court['is_vip'] == 1);
            $group_id = $court['pricing_group_id'] ? (int)$court['pricing_group_id'] : null;
            $dateTime = new DateTime($date . ' ' . $time);

            if ($group_id !== null) {
                // ใช้กลุ่มกฎราคาที่กำหนดให้คอร์ต
                $response['price'] = floatval(pick_price_per_hour($dateTime, $group_id));
                $rule = pick_pricing_rule($dateTime, $group_id);
                if ($rule) {
                    $response['rule'] = [
                        'day_type'   => $rule['day_type'],
                        'start_time' => substr($rule['start_time'], 0, 5),
                        'end_time'   => substr($rule['end_time'], 0, 5),
                    ];
                }
                $response['success'] = true;
            } elseif ($isVip && $court['vip_price'] > 0) {
                // ราคาคงที่ VIP
                $response['price'] = floatval($court['vip_price']);
                $response['success'] = true;
            } elseif (!$isVip && $court['normal_price'] > 0) {
                // ราคาคงที่คอร์ตปกติ
                $response['price'] = floatval($court['normal_price']);
                $response['success'] = true;
            } else {
                // กฎ global (group_id IS NULL)
                $response['price'] = floatval(pick_price_per_hour($dateTime));
                $rule = pick_pricing_rule($dateTime);
                if ($rule) {
                    $response['rule'] = [
                        'day_type'   => $rule['day_type'],
                        'start_time' => substr($rule['start_time'], 0, 5),
                        'end_time'   => substr($rule['end_time'], 0, 5),
                    ];
                }
                $response['success'] = true;
            }
        }
    }
} catch (Exception $e) {
    error_log('Error in get_price_ajax.php: ' . $e->getMessage());
}

echo json_encode($response);
?>
