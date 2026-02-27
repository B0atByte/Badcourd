<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

$booking_id = (int)($_POST['booking_id'] ?? 0);

if ($booking_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ไม่ระบุ booking_id'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Verify booking exists and is booked
$bkStmt = $pdo->prepare('SELECT id, payment_slip_path FROM bookings WHERE id = ? AND status = ?');
$bkStmt->execute([$booking_id, 'booked']);
$booking = $bkStmt->fetch();

if (!$booking) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบการจองหรือสถานะไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_FILES['slip_file']) || $_FILES['slip_file']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['slip_file']['error'] ?? -1;
    $errMsg  = match($errCode) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'ไฟล์ใหญ่เกินไป (สูงสุด 10MB)',
        UPLOAD_ERR_NO_FILE => 'ไม่ได้เลือกไฟล์',
        default            => 'เกิดข้อผิดพลาดในการอัปโหลด'
    };
    echo json_encode(['success' => false, 'message' => $errMsg], JSON_UNESCAPED_UNICODE);
    exit;
}

$file     = $_FILES['slip_file'];
$maxBytes = 10 * 1024 * 1024; // 10 MB

if ($file['size'] > $maxBytes) {
    echo json_encode(['success' => false, 'message' => 'ไฟล์ใหญ่เกินไป (สูงสุด 10MB)'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Validate MIME type via finfo
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);
$allowed  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

if (!isset($allowed[$mimeType])) {
    echo json_encode(['success' => false, 'message' => 'รองรับเฉพาะไฟล์ JPG, PNG, WebP เท่านั้น'], JSON_UNESCAPED_UNICODE);
    exit;
}

$ext      = $allowed[$mimeType];
$filename = 'slip_' . $booking_id . '_' . uniqid() . '.' . $ext;
$uploadDir = __DIR__ . '/../uploads/slips/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$uploadPath = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
    echo json_encode(['success' => false, 'message' => 'บันทึกไฟล์ไม่สำเร็จ'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Delete old slip if exists
if (!empty($booking['payment_slip_path'])) {
    $oldPath = __DIR__ . '/../' . $booking['payment_slip_path'];
    if (file_exists($oldPath)) @unlink($oldPath);
}

$relPath = 'uploads/slips/' . $filename;
$pdo->prepare('UPDATE bookings SET payment_slip_path = ? WHERE id = ?')->execute([$relPath, $booking_id]);

echo json_encode([
    'success' => true,
    'path'    => $relPath,
    'url'     => '/' . $relPath,
    'message' => 'อัปโหลดสลิปสำเร็จ',
], JSON_UNESCAPED_UNICODE);
