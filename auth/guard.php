<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user'])) {
    header('Location: /auth/login.php');
    exit;
}
function require_role(array $roles = []): void {
    if (!$roles) return;
    $role = $_SESSION['user']['role'] ?? 'user';
    if (!in_array($role, $roles, true)) {
        http_response_code(403);
        header('Location: /auth/login.php?err=forbidden');
        exit;
    }
}