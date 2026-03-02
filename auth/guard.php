<?php
ob_start(); // Flush buffer to suppress BOM from calling files
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user'])) {
    header('Location: /auth/login.php');
    exit;
}

/**
 * ตรวจสอบว่า user มี role ที่กำหนดไหม
 */
function require_role(array $roles = []): void
{
    if (!$roles)
        return;
    $role = $_SESSION['user']['role'] ?? 'user';
    if (!in_array($role, $roles, true)) {
        http_response_code(403);
        header('Location: /auth/login.php?err=forbidden');
        exit;
    }
}

/**
 * ตรวจสอบว่า user มีสิทธิ์เข้าถึง page ที่กำหนดไหม
 * admin เข้าได้ทุกหน้าโดยอัตโนมัติ
 * user ต้องมีชื่อ page อยู่ใน permissions array
 */
function require_permission(string $page): void
{
    $role = $_SESSION['user']['role'] ?? 'user';
    if ($role === 'admin')
        return; // admin ผ่านเสมอ

    $perms = $_SESSION['user']['permissions'] ?? [];
    if (is_string($perms))
        $perms = json_decode($perms, true) ?: [];

    if (!in_array($page, $perms, true)) {
        http_response_code(403);
        header('Location: /?err=forbidden');
        exit;
    }
}

/**
 * คืน array ของ permissions ของ user ปัจจุบัน
 * admin คืน ['*'] หมายถึงทุกอย่าง
 */
function get_permissions(): array
{
    $role = $_SESSION['user']['role'] ?? 'user';
    if ($role === 'admin')
        return ['*'];
    $perms = $_SESSION['user']['permissions'] ?? [];
    if (is_string($perms))
        $perms = json_decode($perms, true) ?: [];
    return $perms;
}

/**
 * ตรวจว่า user มีสิทธิ์ page นี้ไหม (ไม่ redirect — ใช้สำหรับ ternary ใน view)
 */
function can(string $page): bool
{
    $role = $_SESSION['user']['role'] ?? 'user';
    if ($role === 'admin')
        return true;
    $perms = $_SESSION['user']['permissions'] ?? [];
    if (is_string($perms))
        $perms = json_decode($perms, true) ?: [];
    return in_array($page, $perms, true);
}