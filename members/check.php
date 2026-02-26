<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

// รับเบอร์โทรศัพท์
$phone = isset($_GET['phone']) ? trim($_GET['phone']) : '';

// ตรวจสอบว่ามีเบอร์โทรศัพท์หรือไม่
if (empty($phone)) {
    echo json_encode([
        'success' => false,
        'message' => 'กรุณาระบุเบอร์โทรศัพท์'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ตรวจสอบรูปแบบเบอร์โทรศัพท์ (10 หลัก)
if (!preg_match('/^0[0-9]{9}$/', $phone)) {
    echo json_encode([
        'success' => false,
        'message' => 'รูปแบบเบอร์โทรศัพท์ไม่ถูกต้อง (ต้องเป็น 10 หลัก)'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // ค้นหาสมาชิกจากเบอร์โทรศัพท์
    $stmt = $pdo->prepare("
        SELECT
            id,
            phone,
            name,
            email,
            points,
            total_bookings,
            total_spent,
            member_level,
            joined_date,
            last_booking_date,
            birth_date,
            status
        FROM members
        WHERE phone = ? AND status = 'active'
    ");
    $stmt->execute([$phone]);
    $member = $stmt->fetch();

    // ดึงชื่อที่เคยใช้กับเบอร์นี้จากประวัติการจอง (เรียงจากล่าสุด)
    $namesStmt = $pdo->prepare("
        SELECT customer_name
        FROM (
            SELECT customer_name, MAX(created_at) AS last_used
            FROM bookings
            WHERE customer_phone = ? AND customer_name != '' AND status != 'cancelled'
            GROUP BY customer_name
        ) sub
        ORDER BY last_used DESC
    ");
    $namesStmt->execute([$phone]);
    $pastNames = array_column($namesStmt->fetchAll(), 'customer_name');

    if ($member) {
        // พบสมาชิก — รวมชื่อสมาชิกไว้ด้านหน้า (ถ้ายังไม่มีใน list)
        $discounts = [
            'Bronze' => 0,
            'Silver' => 5,
            'Gold' => 10,
            'Platinum' => 15
        ];
        $discount_percent = $discounts[$member['member_level']] ?? 0;

        // รวม member name ไว้ก่อน แล้วตามด้วยชื่ออื่นจาก booking history
        $allNames = array_values(array_unique(
            array_merge([$member['name']], $pastNames)
        ));

        echo json_encode([
            'success' => true,
            'is_member' => true,
            'member' => [
                'id' => $member['id'],
                'phone' => $member['phone'],
                'name' => $member['name'],
                'email' => $member['email'],
                'points' => (int)$member['points'],
                'total_bookings' => (int)$member['total_bookings'],
                'total_spent' => (float)$member['total_spent'],
                'member_level' => $member['member_level'],
                'discount_percent' => $discount_percent,
                'joined_date' => $member['joined_date'],
                'last_booking_date' => $member['last_booking_date'],
                'birth_date' => $member['birth_date']
            ],
            'names' => $allNames,
            'message' => 'พบข้อมูลสมาชิก'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        // ไม่พบสมาชิก — แต่อาจมีประวัติการจองเก่า
        $allNames = array_values(array_unique($pastNames));

        echo json_encode([
            'success' => true,
            'is_member' => false,
            'names' => $allNames,
            'message' => 'ไม่พบข้อมูลสมาชิก (จะลงทะเบียนอัตโนมัติเมื่อจองสำเร็จ)'
        ], JSON_UNESCAPED_UNICODE);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
