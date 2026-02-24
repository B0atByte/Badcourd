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
        // ดึงข้อมูลคอร์ต
        $courtStmt = $pdo->prepare('SELECT court_type, is_vip, vip_price, normal_price FROM courts WHERE id = ?');
        $courtStmt->execute([$court_id]);
        $court = $courtStmt->fetch();
        
        if ($court) {
            $isVip = ($court['court_type'] === 'vip' || $court['is_vip'] == 1);
            
            if ($isVip && $court['vip_price'] > 0) {
                // ถ้าเป็น VIP ให้ใช้ราคา VIP
                $response['price'] = floatval($court['vip_price']);
                $response['success'] = true;
            } elseif (!$isVip && $court['normal_price'] > 0) {
                // ถ้าคอร์ตปกติมีราคาคงที่ ให้ใช้ราคานั้น
                $response['price'] = floatval($court['normal_price']);
                $response['success'] = true;
            } else {
                // ใช้ราคาตามช่วงเวลา
                $dateTime = new DateTime($date . ' ' . $time);
                $response['price'] = floatval(pick_price_per_hour($dateTime));
                
                // ดึงกฎราคาที่ใช้
                $rule = pick_pricing_rule($dateTime);
                if ($rule) {
                    $response['rule'] = [
                        'day_type' => $rule['day_type'],
                        'start_time' => substr($rule['start_time'], 0, 5),
                        'end_time' => substr($rule['end_time'], 0, 5)
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
