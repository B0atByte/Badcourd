<?php
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user'])) {
    header('Location: /auth/login.php');
    exit;
}

// Get member ID
$member_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($member_id === 0) {
    header('Location: /members/search.php');
    exit;
}

// Get member information
$stmt = $pdo->prepare("
    SELECT *
    FROM members
    WHERE id = ?
");
$stmt->execute([$member_id]);
$member = $stmt->fetch();

if (!$member) {
    header('Location: /members/search.php');
    exit;
}

// Get booking history
$bookingsStmt = $pdo->prepare("
    SELECT
        b.*,
        c.court_no,
        c.vip_room_name,
        c.court_type
    FROM bookings b
    LEFT JOIN courts c ON b.court_id = c.id
    WHERE b.member_id = ?
    ORDER BY b.start_datetime DESC
    LIMIT 10
");
$bookingsStmt->execute([$member_id]);
$bookings = $bookingsStmt->fetchAll();

// Get point transactions
$pointsStmt = $pdo->prepare("
    SELECT *
    FROM point_transactions
    WHERE member_id = ?
    ORDER BY created_at DESC
    LIMIT 10
");
$pointsStmt->execute([$member_id]);
$pointTransactions = $pointsStmt->fetchAll();

// Member level colors
$levelColors = [
    'Bronze' => ['bg' => 'bg-amber-100', 'text' => 'text-amber-800', 'border' => 'border-amber-200'],
    'Silver' => ['bg' => 'bg-gray-100', 'text' => 'text-gray-800', 'border' => 'border-gray-300'],
    'Gold' => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-800', 'border' => 'border-yellow-300'],
    'Platinum' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-800', 'border' => 'border-blue-300']
];

$discounts = [
    'Bronze' => 0,
    'Silver' => 5,
    'Gold' => 10,
    'Platinum' => 15
];

$colors = $levelColors[$member['member_level']] ?? $levelColors['Bronze'];
$discount = $discounts[$member['member_level']] ?? 0;
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <title>โปรไฟล์สมาชิก - <?= htmlspecialchars($member['name']) ?></title>
</head>
<body style="background:#FAFAFA;" class="min-h-screen">
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="max-w-7xl mx-auto px-4 py-8">

        <!-- Back Button -->
        <div class="mb-6">
            <a href="/members/search.php" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                กลับไปค้นหาสมาชิก
            </a>
        </div>

        <!-- Member Info Card -->
        <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
            <div class="flex flex-col md:flex-row md:items-start gap-6">

                <!-- Avatar & Basic Info -->
                <div class="flex-shrink-0">
                    <div style="background:#E8F1F5;" class="w-24 h-24 rounded-full flex items-center justify-center">
                        <svg class="w-12 h-12 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                </div>

                <!-- Details -->
                <div class="flex-1">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-4">
                        <h1 class="text-2xl font-bold text-gray-900"><?= htmlspecialchars($member['name']) ?></h1>
                        <span class="<?= $colors['bg'] ?> <?= $colors['text'] ?> <?= $colors['border'] ?> border px-3 py-1 rounded-full text-sm font-semibold w-fit">
                            <?= htmlspecialchars($member['member_level']) ?>
                        </span>
                        <?php if ($member['status'] === 'inactive'): ?>
                        <span class="bg-red-100 text-red-800 border border-red-200 px-3 py-1 rounded-full text-sm font-semibold w-fit">
                            Inactive
                        </span>
                        <?php endif; ?>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <p class="text-sm text-gray-500 mb-1">เบอร์โทรศัพท์</p>
                            <p class="font-semibold text-gray-900"><?= htmlspecialchars($member['phone']) ?></p>
                        </div>
                        <?php if ($member['email']): ?>
                        <div>
                            <p class="text-sm text-gray-500 mb-1">อีเมล</p>
                            <p class="font-semibold text-gray-900"><?= htmlspecialchars($member['email']) ?></p>
                        </div>
                        <?php endif; ?>
                        <div>
                            <p class="text-sm text-gray-500 mb-1">สมัครสมาชิก</p>
                            <p class="font-semibold text-gray-900"><?= date('d/m/Y', strtotime($member['joined_date'])) ?></p>
                        </div>
                        <?php if ($member['last_booking_date']): ?>
                        <div>
                            <p class="text-sm text-gray-500 mb-1">จองล่าสุด</p>
                            <p class="font-semibold text-gray-900"><?= date('d/m/Y', strtotime($member['last_booking_date'])) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

            <!-- Points -->
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <div class="flex items-center gap-3">
                    <div style="background:#E8F1F5;" class="w-12 h-12 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6" style="color:#005691;" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">คะแนนสะสม</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($member['points']) ?></p>
                    </div>
                </div>
            </div>

            <!-- Total Bookings -->
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <div class="flex items-center gap-3">
                    <div style="background:#E8F1F5;" class="w-12 h-12 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6" style="color:#005691;" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">จองทั้งหมด</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($member['total_bookings']) ?></p>
                    </div>
                </div>
            </div>

            <!-- Total Spent -->
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <div class="flex items-center gap-3">
                    <div style="background:#E8F1F5;" class="w-12 h-12 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6" style="color:#005691;" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/>
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">ยอดใช้จ่าย</p>
                        <p class="text-2xl font-bold text-gray-900">฿<?= number_format($member['total_spent'], 0) ?></p>
                    </div>
                </div>
            </div>

            <!-- Discount -->
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <div class="flex items-center gap-3">
                    <div style="background:#E8F1F5;" class="w-12 h-12 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6" style="color:#005691;" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5 2a1 1 0 011 1v1h1a1 1 0 010 2H6v1a1 1 0 01-2 0V6H3a1 1 0 010-2h1V3a1 1 0 011-1zm0 10a1 1 0 011 1v1h1a1 1 0 110 2H6v1a1 1 0 11-2 0v-1H3a1 1 0 110-2h1v-1a1 1 0 011-1zM12 2a1 1 0 01.967.744L14.146 7.2 17.5 9.134a1 1 0 010 1.732l-3.354 1.935-1.18 4.455a1 1 0 01-1.933 0L9.854 12.8 6.5 10.866a1 1 0 010-1.732l3.354-1.935 1.18-4.455A1 1 0 0112 2z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">ส่วนลดสมาชิก</p>
                        <p class="text-2xl font-bold text-gray-900"><?= $discount ?>%</p>
                    </div>
                </div>
            </div>

        </div>

        <!-- Tabs -->
        <div class="bg-white rounded-xl border border-gray-200">

            <!-- Tab Headers -->
            <div class="flex border-b border-gray-200">
                <button onclick="switchTab('bookings')" id="tab-bookings"
                        class="flex-1 px-6 py-4 text-sm font-medium transition-colors border-b-2 tab-active">
                    ประวัติการจอง
                </button>
                <button onclick="switchTab('points')" id="tab-points"
                        class="flex-1 px-6 py-4 text-sm font-medium text-gray-500 transition-colors border-b-2 border-transparent hover:text-gray-700">
                    ประวัติแต้ม
                </button>
            </div>

            <!-- Bookings Tab -->
            <div id="content-bookings" class="p-6">
                <?php if (count($bookings) === 0): ?>
                    <div class="text-center py-12 text-gray-500">
                        <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <p>ยังไม่มีประวัติการจอง</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($bookings as $booking): ?>
                            <?php
                            $isVip = $booking['court_type'] === 'vip';
                            $courtName = $isVip ? ($booking['vip_room_name'] ?? 'ห้อง VIP') : 'คอร์ต ' . $booking['court_no'];
                            $statusColors = [
                                'booked' => 'bg-green-100 text-green-800 border-green-200',
                                'cancelled' => 'bg-red-100 text-red-800 border-red-200'
                            ];
                            $statusColor = $statusColors[$booking['status']] ?? 'bg-gray-100 text-gray-800 border-gray-200';
                            ?>
                            <div class="border border-gray-200 rounded-lg p-4 hover:border-gray-300 transition-colors">
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-2">
                                            <h4 class="font-semibold text-gray-900"><?= htmlspecialchars($courtName) ?></h4>
                                            <span class="<?= $statusColor ?> border px-2 py-0.5 rounded text-xs font-medium">
                                                <?= $booking['status'] === 'booked' ? 'จองแล้ว' : 'ยกเลิก' ?>
                                            </span>
                                        </div>
                                        <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-600">
                                            <span>📅 <?= date('d/m/Y H:i', strtotime($booking['start_datetime'])) ?> น.</span>
                                            <span>⏱️ <?= $booking['duration_hours'] ?> ชม.</span>
                                            <span>💰 ฿<?= number_format($booking['total_amount'], 0) ?></span>
                                        </div>
                                    </div>
                                    <a href="/bookings/?search=<?= urlencode($member['phone']) ?>"
                                       class="text-sm" style="color:#005691;">
                                        ดูทั้งหมด →
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Points Tab -->
            <div id="content-points" class="p-6 hidden">
                <?php if (count($pointTransactions) === 0): ?>
                    <div class="text-center py-12 text-gray-500">
                        <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                        </svg>
                        <p>ยังไม่มีประวัติการใช้แต้ม</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($pointTransactions as $txn): ?>
                            <?php
                            $isEarn = $txn['type'] === 'earn';
                            $color = $isEarn ? 'text-green-600' : 'text-red-600';
                            $sign = $isEarn ? '+' : '-';
                            ?>
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <p class="font-medium text-gray-900 mb-1">
                                            <?= htmlspecialchars($txn['description']) ?>
                                        </p>
                                        <p class="text-sm text-gray-500">
                                            <?= date('d/m/Y H:i', strtotime($txn['created_at'])) ?> น.
                                        </p>
                                    </div>
                                    <span class="<?= $color ?> font-bold text-lg">
                                        <?= $sign ?><?= number_format($txn['points']) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>

    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script>
    function switchTab(tab) {
        // Update tab buttons
        document.getElementById('tab-bookings').classList.remove('tab-active');
        document.getElementById('tab-points').classList.remove('tab-active');
        document.getElementById('tab-' + tab).classList.add('tab-active');

        // Update content
        document.getElementById('content-bookings').classList.add('hidden');
        document.getElementById('content-points').classList.add('hidden');
        document.getElementById('content-' + tab).classList.remove('hidden');
    }
    </script>

    <style>
    .tab-active {
        color: #005691 !important;
        border-bottom-color: #005691 !important;
    }
    </style>
</body>
</html>
