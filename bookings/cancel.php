<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../config/db.php';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: /bookings/index.php');
    exit;
}

$pdo->beginTransaction();
try {
    // ตรวจสอบว่า booking มีอยู่จริงและสถานะเป็น 'booked'
    $check = $pdo->prepare("
        SELECT id, status, member_badminton_package_id, used_package_hours
        FROM bookings
        WHERE id = ? AND status = 'booked'
    ");
    $check->execute([$id]);
    $booking = $check->fetch();

    if (!$booking) {
        $pdo->rollBack();
        header('Location: /bookings/index.php?err=not_found');
        exit;
    }

    // Update booking status
    $pdo->prepare("
        UPDATE bookings
        SET status='cancelled', updated_at=NOW()
        WHERE id=?
    ")->execute([$id]);

    // คืนชั่วโมงถ้าใช้แพ็กเกจ
    if ($booking['member_badminton_package_id']) {
        $pdo->prepare("
            UPDATE member_badminton_packages
            SET hours_used = hours_used - ?,
                status = 'active',
                updated_at = NOW()
            WHERE id = ?
        ")->execute([
            $booking['used_package_hours'],
            $booking['member_badminton_package_id']
        ]);
    }

    $pdo->commit();
    header('Location: /bookings/index.php?msg=cancelled');
} catch (Exception $e) {
    $pdo->rollBack();
    header('Location: /bookings/index.php?err=error');
}
exit;
