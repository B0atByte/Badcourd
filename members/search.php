<?php
// Redirect ไปยังหน้า admin/members.php (รวมไฟล์แล้ว)
$qs = $_SERVER['QUERY_STRING'] ?? '';
$redirect = '/admin/members.php?view=card' . ($qs ? '&' . $qs : '');
header('Location: ' . $redirect, true, 301);
exit;
