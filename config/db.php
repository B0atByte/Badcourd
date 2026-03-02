<?php
date_default_timezone_set('Asia/Bangkok'); // ← แก้ไข: ตั้งเวลาไทย UTC+7

$host = 'mysql'; // <-- ชื่อ service ของ MySQL ใน docker-compose.yml
$db = 'badcourt';
$user = 'root';
$pass = 'rootpassword'; // <-- ให้ตรงกับ MYSQL_ROOT_PASSWORD ใน docker-compose.yml
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("SET time_zone = '+07:00'"); // ← แก้ไข: ตั้ง MySQL timezone เป็นไทย
} catch (PDOException $e) {
    die('DB Connection failed: ' . $e->getMessage());
}
