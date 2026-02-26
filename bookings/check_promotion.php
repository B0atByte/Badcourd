<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

$code = isset($_GET['code']) ? strtoupper(trim($_GET['code'])) : '';

if (empty($code)) {
    echo json_encode(['success' => false, 'message' => 'กรุณาระบุรหัสโปรโมชั่น'], JSON_UNESCAPED_UNICODE);
    exit;
}

$today = date('Y-m-d');

try {
    $stmt = $pdo->prepare("
        SELECT id, code, name, discount_percent, start_date, end_date
        FROM promotions
        WHERE code = ?
          AND is_active = 1
          AND start_date <= ?
          AND end_date >= ?
    ");
    $stmt->execute([$code, $today, $today]);
    $promo = $stmt->fetch();

    if ($promo) {
        echo json_encode([
            'success' => true,
            'found'   => true,
            'promotion' => [
                'id'               => (int)$promo['id'],
                'code'             => $promo['code'],
                'name'             => $promo['name'],
                'discount_percent' => (float)$promo['discount_percent'],
            ],
            'message' => 'พบโปรโมชั่น: ' . $promo['name'],
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => true,
            'found'   => false,
            'message' => 'ไม่พบโปรโมชั่น หรือโปรโมชั่นหมดอายุแล้ว',
        ], JSON_UNESCAPED_UNICODE);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด'], JSON_UNESCAPED_UNICODE);
}
