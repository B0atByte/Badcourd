<?php
date_default_timezone_set('Asia/Bangkok');

// Load .env file if environment variables are not already set
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (!isset($_ENV[$key]) && !getenv($key)) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

$host    = getenv('DB_HOST')    ?: 'mysql';
$db      = getenv('DB_NAME')    ?: 'badcourt';
$user    = getenv('DB_USER')    ?: 'root';
$pass    = getenv('DB_PASS')    ?: '';
$charset = getenv('DB_CHARSET') ?: 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("SET time_zone = '+07:00'");
} catch (PDOException $e) {
    die('DB Connection failed: ' . $e->getMessage());
}
