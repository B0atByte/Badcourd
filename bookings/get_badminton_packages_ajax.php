<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

$phone = trim($_GET['phone'] ?? '');

if (empty($phone)) {
    echo json_encode(['customer_name' => '', 'packages' => []]);
    exit;
}

try {
    // ดึงแพ็กเกจที่ใช้ได้ทั้งหมด
    $stmt = $pdo->prepare("
        SELECT
            mbp.id,
            mbp.customer_name,
            mbp.customer_phone,
            mbp.hours_total,
            mbp.hours_used,
            (mbp.hours_total - mbp.hours_used) AS remaining,
            mbp.expiry_date,
            mbp.status,
            bpt.name AS type_name,
            bpt.price
        FROM member_badminton_packages mbp
        JOIN badminton_package_types bpt ON bpt.id = mbp.badminton_package_type_id
        WHERE mbp.customer_phone = ?
          AND mbp.status = 'active'
          AND (mbp.expiry_date IS NULL OR mbp.expiry_date >= CURDATE())
          AND (mbp.hours_total - mbp.hours_used) > 0
        ORDER BY mbp.expiry_date ASC, mbp.id ASC
    ");
    $stmt->execute([$phone]);
    $packages = $stmt->fetchAll();

    // ดึงชื่อลูกค้าจากแพ็กเกจแรก (ถ้ามี)
    $customer_name = '';
    if (!empty($packages)) {
        $customer_name = $packages[0]['customer_name'];
    }

    // ส่งผลลัพธ์
    echo json_encode([
        'customer_name' => $customer_name,
        'packages' => $packages
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode(['customer_name' => '', 'packages' => [], 'error' => 'Database error']);
}
