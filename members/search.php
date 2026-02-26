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

// Search parameters
$search       = trim($_GET['search'] ?? '');
$level_filter = $_GET['level'] ?? '';

// Pagination
$per_page_raw = $_GET['per_page'] ?? '24';
$per_page     = ($per_page_raw === 'all') ? 0 : (int)$per_page_raw;
if (!in_array($per_page, [0, 12, 24, 48, 100])) $per_page = 24;
$page = max(1, (int)($_GET['page'] ?? 1));

// Build query
$where  = ['1=1'];
$params = [];

if (!empty($search)) {
    $where[]  = '(phone LIKE ? OR name LIKE ?)';
    $sp       = '%' . $search . '%';
    $params[] = $sp;
    $params[] = $sp;
}
if (!empty($level_filter)) {
    $where[]  = 'member_level = ?';
    $params[] = $level_filter;
}

$whereClause = implode(' AND ', $where);

// Count
$cntStmt = $pdo->prepare("SELECT COUNT(*) FROM members WHERE $whereClause");
$cntStmt->execute($params);
$totalRecords = (int)$cntStmt->fetchColumn();

// Pagination calc
$totalPages = 1; $offset = 0;
if ($per_page > 0) {
    $totalPages = max(1, (int)ceil($totalRecords / $per_page));
    $page       = min($page, $totalPages);
    $offset     = ($page - 1) * $per_page;
}

// Get members
$sql = "SELECT id, phone, name, email, points, total_bookings, total_spent,
               member_level, joined_date, last_booking_date, status
        FROM members WHERE $whereClause ORDER BY total_spent DESC, joined_date DESC";
if ($per_page > 0) $sql .= " LIMIT $per_page OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$members = $stmt->fetchAll();

// Member level colors and labels
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
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <title>ค้นหาสมาชิก - BARGAIN SPORT</title>
</head>
<body style="background:#FAFAFA;" class="min-h-screen">
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="max-w-7xl mx-auto px-4 py-8">

        <!-- Header -->
        <div class="mb-6">
            <h1 style="color:#005691;" class="text-2xl font-bold mb-2">ค้นหาสมาชิก</h1>
            <p class="text-gray-600 text-sm">ค้นหาสมาชิกด้วยเบอร์โทรศัพท์หรือชื่อ</p>
        </div>

        <!-- Search Bar -->
        <div class="bg-white rounded-xl border border-gray-200 p-4 mb-6">
            <form method="get" class="flex flex-col gap-3">
                <div class="flex flex-col sm:flex-row gap-3">
                    <div class="flex-1">
                        <input type="text" name="search"
                               value="<?= htmlspecialchars($search) ?>"
                               placeholder="ค้นหาด้วยเบอร์โทรหรือชื่อ..."
                               class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none text-sm">
                    </div>
                    <select name="level"
                            class="px-3 py-2.5 rounded-lg border border-gray-300 outline-none text-sm min-w-[130px]">
                        <option value="">ทุกระดับ</option>
                        <option value="Bronze"   <?= $level_filter === 'Bronze'   ? 'selected' : '' ?>>Bronze</option>
                        <option value="Silver"   <?= $level_filter === 'Silver'   ? 'selected' : '' ?>>Silver</option>
                        <option value="Gold"     <?= $level_filter === 'Gold'     ? 'selected' : '' ?>>Gold</option>
                        <option value="Platinum" <?= $level_filter === 'Platinum' ? 'selected' : '' ?>>Platinum</option>
                    </select>
                </div>
                <div class="flex flex-wrap gap-2 items-center">
                    <label class="text-xs text-gray-500 whitespace-nowrap">แสดง</label>
                    <select name="per_page" onchange="this.form.submit()"
                            class="px-3 py-2 rounded-lg border border-gray-300 outline-none text-sm">
                        <option value="12"  <?= $per_page === 12  ? 'selected' : '' ?>>12 รายการ</option>
                        <option value="24"  <?= $per_page === 24  ? 'selected' : '' ?>>24 รายการ</option>
                        <option value="48"  <?= $per_page === 48  ? 'selected' : '' ?>>48 รายการ</option>
                        <option value="100" <?= $per_page === 100 ? 'selected' : '' ?>>100 รายการ</option>
                        <option value="all" <?= $per_page === 0   ? 'selected' : '' ?>>ทั้งหมด</option>
                    </select>
                    <div class="flex-1"></div>
                    <button type="submit" style="background:#005691;"
                            class="px-5 py-2 text-white text-sm font-medium rounded-lg hover:opacity-90 transition-opacity">
                        ค้นหา
                    </button>
                    <?php if ($search !== '' || $level_filter !== ''): ?>
                    <a href="/members/search.php"
                       class="px-5 py-2 text-gray-600 text-sm font-medium rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors">
                        ล้างตัวกรอง
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Results count -->
        <?php if ($search !== '' || $level_filter !== ''): ?>
        <div class="mb-4 flex items-center justify-between">
            <p class="text-sm text-gray-600">
                พบ <span class="font-semibold" style="color:#005691;"><?= number_format($totalRecords) ?></span> รายการ
            </p>
            <?php if ($per_page > 0 && $totalPages > 1): ?>
            <p class="text-xs text-gray-400">หน้า <?= $page ?>/<?= $totalPages ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (count($members) === 0): ?>
            <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
                <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                <p class="text-gray-500">ไม่พบข้อมูลสมาชิก</p>
                <p class="text-sm text-gray-400 mt-1">ลองค้นหาด้วยคำอื่น</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($members as $member): ?>
                    <?php
                    $colors = $levelColors[$member['member_level']] ?? $levelColors['Bronze'];
                    $discount = $discounts[$member['member_level']] ?? 0;
                    ?>
                    <div class="bg-white rounded-xl border border-gray-200 p-5 hover:shadow-md transition-shadow">
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex-1">
                                <h3 class="font-semibold text-gray-900 mb-1"><?= htmlspecialchars($member['name']) ?></h3>
                                <p class="text-sm text-gray-500"><?= htmlspecialchars($member['phone']) ?></p>
                            </div>
                            <span class="<?= $colors['bg'] ?> <?= $colors['text'] ?> <?= $colors['border'] ?> border px-2 py-1 rounded text-xs font-medium">
                                <?= htmlspecialchars($member['member_level']) ?>
                            </span>
                        </div>

                        <div class="space-y-2 mb-4">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">คะแนนสะสม</span>
                                <span class="font-semibold text-gray-900"><?= number_format($member['points']) ?> แต้ม</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">ยอดใช้จ่ายรวม</span>
                                <span class="font-semibold text-gray-900">฿<?= number_format($member['total_spent'], 0) ?></span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">จำนวนครั้งที่จอง</span>
                                <span class="font-semibold text-gray-900"><?= number_format($member['total_bookings']) ?> ครั้ง</span>
                            </div>
                            <?php if ($discount > 0): ?>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">ส่วนลด</span>
                                <span class="font-semibold" style="color:#005691;"><?= $discount ?>%</span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="flex items-center gap-2 text-xs text-gray-400 mb-4">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <span>สมัคร: <?= date('d/m/Y', strtotime($member['joined_date'])) ?></span>
                        </div>

                        <a href="/members/profile.php?id=<?= $member['id'] ?>"
                           style="background:#E8F1F5; color:#005691;"
                           class="block w-full py-2.5 text-center text-sm font-medium rounded-lg hover:opacity-80 transition-opacity">
                            ดูโปรไฟล์
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Pagination -->
        <?php include __DIR__ . '/../includes/pagination.php'; ?>

    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
