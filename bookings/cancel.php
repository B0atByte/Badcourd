<?php // cancel.php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../config/db.php';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: /bookings/index.php');
    exit;
}

// ตรวจสอบว่า booking มีอยู่จริงและสถานะเป็น 'booked'
$check = $pdo->prepare("SELECT id, status FROM bookings WHERE id = ? AND status = 'booked'");
$check->execute([$id]);
if (!$check->fetch()) {
    header('Location: /bookings/index.php?err=not_found');
    exit;
}

$pdo->prepare("UPDATE bookings SET status='cancelled', updated_at=NOW() WHERE id=?")->execute([$id]);
header('Location: /bookings/index.php?msg=cancelled');
exit;
