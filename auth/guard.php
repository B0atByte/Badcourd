<?php
session_start();
if (!isset($_SESSION['user'])) {
header('Location: /BARGAIN SPORT/auth/login.php');
exit;
}
function require_role($roles = []) {
if (!$roles) return; // allow any logged-in
$role = $_SESSION['user']['role'] ?? 'user';
if (!in_array($role, $roles)) {
http_response_code(403);
die('Forbidden');
}
}