<?php
// AJAX endpoint สำหรับค้นหาแพ็กเกจโยคะโดยเบอร์โทร
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

$phone = trim($_GET['phone'] ?? '');
if (empty($phone)) { echo json_encode(['packages'=>[]]); exit; }

$today = date('Y-m-d');

$stmt = $pdo->prepare("
    SELECT myp.id,
           myp.student_name,
           myp.student_phone,
           myp.sessions_total,
           myp.sessions_used,
           (myp.sessions_total - myp.sessions_used) AS remaining,
           myp.expiry_date,
           ypt.name AS type_name
    FROM member_yoga_packages myp
    JOIN yoga_package_types ypt ON ypt.id = myp.yoga_package_type_id
    WHERE myp.student_phone = ?
      AND (myp.expiry_date IS NULL OR myp.expiry_date >= ?)
      AND (myp.sessions_total - myp.sessions_used) > 0
    ORDER BY myp.expiry_date ASC, myp.id ASC
");
$stmt->execute([$phone, $today]);
$pkgs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$studentName = '';
if (!empty($pkgs)) {
    $studentName = $pkgs[0]['student_name'];
} else {
    // ลองดึงชื่อจาก packages ที่หมดอายุหรือหมดครั้ง
    $fallback = $pdo->prepare("SELECT student_name FROM member_yoga_packages WHERE student_phone=? LIMIT 1");
    $fallback->execute([$phone]);
    $fb = $fallback->fetch();
    if ($fb) $studentName = $fb['student_name'];
}

echo json_encode([
    'student_name' => $studentName,
    'packages'     => $pkgs,
], JSON_UNESCAPED_UNICODE);
