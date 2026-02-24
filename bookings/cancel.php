<?php // cancel.php
require_once __DIR__.'/../auth/guard.php';
require_once __DIR__.'/../config/db.php';
$id=(int)($_GET['id'] ?? 0);
$pdo->prepare("UPDATE bookings SET status='cancelled', updated_at=NOW() WHERE id=?")->execute([$id]);
header('Location: /bookings/index.php');