<?php
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: /auth/login.php');
    exit;
}

$success = $error = '';

// Handle point adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'adjust_points') {
        $member_id = (int)$_POST['member_id'];
        $points_change = (int)$_POST['points_change'];
        $description = trim($_POST['description']);

        try {
            $pdo->beginTransaction();

            // Update member points
            $updateStmt = $pdo->prepare("UPDATE members SET points = points + ? WHERE id = ?");
            $updateStmt->execute([$points_change, $member_id]);

            // Record transaction
            $txnStmt = $pdo->prepare("
                INSERT INTO point_transactions (member_id, points, type, description, created_by)
                VALUES (?, ?, 'adjust', ?, ?)
            ");
            $txnStmt->execute([
                $member_id,
                abs($points_change),
                $description,
                $_SESSION['user']['id']
            ]);

            $pdo->commit();
            $success = 'ปรับแต้มสำเร็จ';
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'toggle_status') {
        $member_id = (int)$_POST['member_id'];
        $new_status = $_POST['new_status'] === 'active' ? 'active' : 'inactive';

        $stmt = $pdo->prepare("UPDATE members SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $member_id]);
        $success = 'อัปเดตสถานะสำเร็จ';
    }
}

// Search and filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$level_filter = isset($_GET['level']) ? $_GET['level'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$where = ['1=1'];
$params = [];

if (!empty($search)) {
    $where[] = '(phone LIKE ? OR name LIKE ?)';
    $searchParam = '%' . $search . '%';
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if (!empty($level_filter)) {
    $where[] = 'member_level = ?';
    $params[] = $level_filter;
}

if (!empty($status_filter)) {
    $where[] = 'status = ?';
    $params[] = $status_filter;
}

$whereClause = implode(' AND ', $where);

// Get members
$stmt = $pdo->prepare("
    SELECT *
    FROM members
    WHERE $whereClause
    ORDER BY total_spent DESC, joined_date DESC
");
$stmt->execute($params);
$members = $stmt->fetchAll();

// Get summary stats
$statsStmt = $pdo->query("
    SELECT
        COUNT(*) as total_members,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_members,
        SUM(total_spent) as total_revenue,
        SUM(points) as total_points
    FROM members
");
$stats = $statsStmt->fetch();

$levelColors = [
    'Bronze' => ['bg' => 'bg-amber-100', 'text' => 'text-amber-800', 'border' => 'border-amber-200'],
    'Silver' => ['bg' => 'bg-gray-100', 'text' => 'text-gray-800', 'border' => 'border-gray-300'],
    'Gold' => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-800', 'border' => 'border-yellow-300'],
    'Platinum' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-800', 'border' => 'border-blue-300']
];
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <title>จัดการสมาชิก - BARGAIN SPORT</title>
</head>
<body style="background:#FAFAFA;" class="min-h-screen">
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="max-w-7xl mx-auto px-4 py-8">

        <!-- Header -->
        <div class="mb-6">
            <h1 style="color:#005691;" class="text-2xl font-bold mb-2">จัดการสมาชิก</h1>
            <p class="text-gray-600 text-sm">จัดการข้อมูลสมาชิกและแต้มสะสม</p>
        </div>

        <!-- Messages -->
        <?php if ($success): ?>
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-5 text-sm">
            <?= htmlspecialchars($success) ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-5 text-sm">
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <p class="text-sm text-gray-500 mb-1">สมาชิกทั้งหมด</p>
                <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['total_members']) ?></p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <p class="text-sm text-gray-500 mb-1">สมาชิกที่ใช้งาน</p>
                <p class="text-2xl font-bold" style="color:#005691;"><?= number_format($stats['active_members']) ?></p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <p class="text-sm text-gray-500 mb-1">รายได้รวม</p>
                <p class="text-2xl font-bold text-gray-900">฿<?= number_format($stats['total_revenue'], 0) ?></p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <p class="text-sm text-gray-500 mb-1">แต้มรวม</p>
                <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['total_points']) ?></p>
            </div>
        </div>

        <!-- Search & Filter -->
        <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
            <form method="get" class="flex flex-col sm:flex-row gap-3">
                <div class="flex-1">
                    <input type="text"
                           name="search"
                           value="<?= htmlspecialchars($search) ?>"
                           placeholder="ค้นหาด้วยเบอร์โทรหรือชื่อ..."
                           class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:border-[#E8F1F5] focus:ring-2 focus:ring-[#E8F1F5]/20 outline-none text-sm">
                </div>

                <select name="level"
                        class="px-4 py-2.5 rounded-lg border border-gray-300 focus:border-[#E8F1F5] focus:ring-2 focus:ring-[#E8F1F5]/20 outline-none text-sm">
                    <option value="">ทุกระดับ</option>
                    <option value="Bronze" <?= $level_filter === 'Bronze' ? 'selected' : '' ?>>Bronze</option>
                    <option value="Silver" <?= $level_filter === 'Silver' ? 'selected' : '' ?>>Silver</option>
                    <option value="Gold" <?= $level_filter === 'Gold' ? 'selected' : '' ?>>Gold</option>
                    <option value="Platinum" <?= $level_filter === 'Platinum' ? 'selected' : '' ?>>Platinum</option>
                </select>

                <select name="status"
                        class="px-4 py-2.5 rounded-lg border border-gray-300 focus:border-[#E8F1F5] focus:ring-2 focus:ring-[#E8F1F5]/20 outline-none text-sm">
                    <option value="">ทุกสถานะ</option>
                    <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>

                <button type="submit"
                        style="background:#005691;"
                        class="px-6 py-2.5 text-white text-sm font-medium rounded-lg hover:opacity-90 transition-opacity">
                    ค้นหา
                </button>

                <?php if (!empty($search) || !empty($level_filter) || !empty($status_filter)): ?>
                <a href="/admin/members.php"
                   class="px-6 py-2.5 text-gray-600 text-sm font-medium rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors text-center">
                    ล้าง
                </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Members Table -->
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead style="background:#FAFAFA;">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">สมาชิก</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">ระดับ</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">แต้ม</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">จองทั้งหมด</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">ยอดใช้จ่าย</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">สถานะ</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (count($members) === 0): ?>
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-gray-500">
                                ไม่พบข้อมูลสมาชิก
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($members as $member): ?>
                                <?php $colors = $levelColors[$member['member_level']] ?? $levelColors['Bronze']; ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3">
                                        <div>
                                            <p class="font-medium text-gray-900"><?= htmlspecialchars($member['name']) ?></p>
                                            <p class="text-sm text-gray-500"><?= htmlspecialchars($member['phone']) ?></p>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="<?= $colors['bg'] ?> <?= $colors['text'] ?> <?= $colors['border'] ?> border px-2 py-1 rounded text-xs font-medium">
                                            <?= htmlspecialchars($member['member_level']) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold text-gray-900">
                                        <?= number_format($member['points']) ?>
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-700">
                                        <?= number_format($member['total_bookings']) ?>
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold text-gray-900">
                                        ฿<?= number_format($member['total_spent'], 0) ?>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <?php if ($member['status'] === 'active'): ?>
                                        <span class="bg-green-100 text-green-800 border border-green-200 px-2 py-1 rounded text-xs font-medium">
                                            Active
                                        </span>
                                        <?php else: ?>
                                        <span class="bg-red-100 text-red-800 border border-red-200 px-2 py-1 rounded text-xs font-medium">
                                            Inactive
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-center gap-2">
                                            <a href="/members/profile.php?id=<?= $member['id'] ?>"
                                               class="text-sm" style="color:#005691;"
                                               title="ดูโปรไฟล์">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                </svg>
                                            </a>
                                            <button onclick="openAdjustModal(<?= $member['id'] ?>, '<?= htmlspecialchars($member['name'], ENT_QUOTES) ?>', <?= $member['points'] ?>)"
                                                    class="text-sm text-yellow-600"
                                                    title="ปรับแต้ม">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                            </button>
                                            <form method="post" class="inline" onsubmit="return confirm('แน่ใจหรือไม่?')">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="member_id" value="<?= $member['id'] ?>">
                                                <input type="hidden" name="new_status" value="<?= $member['status'] === 'active' ? 'inactive' : 'active' ?>">
                                                <button type="submit"
                                                        class="text-sm <?= $member['status'] === 'active' ? 'text-red-600' : 'text-green-600' ?>"
                                                        title="<?= $member['status'] === 'active' ? 'ระงับ' : 'เปิดใช้งาน' ?>">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <?php if ($member['status'] === 'active'): ?>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                                        <?php else: ?>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                        <?php endif; ?>
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Adjust Points Modal -->
    <div id="adjustModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl p-6 max-w-md w-full mx-4">
            <h3 class="text-lg font-bold text-gray-900 mb-4">ปรับแต้มสมาชิก</h3>

            <form method="post">
                <input type="hidden" name="action" value="adjust_points">
                <input type="hidden" name="member_id" id="adjust_member_id">

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">สมาชิก</label>
                    <p class="text-gray-900 font-semibold" id="adjust_member_name"></p>
                    <p class="text-sm text-gray-500">แต้มปัจจุบัน: <span id="adjust_current_points" class="font-semibold"></span> แต้ม</p>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">ปรับแต้ม</label>
                    <input type="number" name="points_change" required
                           placeholder="ใส่จำนวนเป็นบวกเพื่อเพิ่ม หรือลบเพื่อลด"
                           class="w-full px-3 py-2.5 rounded-lg border border-gray-300 focus:border-[#E8F1F5] focus:ring-2 focus:ring-[#E8F1F5]/20 outline-none text-sm">
                    <p class="text-xs text-gray-500 mt-1">ใส่เลขบวก (+) เพื่อเพิ่มแต้ม หรือเลขลบ (-) เพื่อลดแต้ม</p>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">รายละเอียด</label>
                    <textarea name="description" required rows="3"
                              placeholder="เหตุผลในการปรับแต้ม..."
                              class="w-full px-3 py-2.5 rounded-lg border border-gray-300 focus:border-[#E8F1F5] focus:ring-2 focus:ring-[#E8F1F5]/20 outline-none text-sm"></textarea>
                </div>

                <div class="flex gap-3">
                    <button type="submit"
                            style="background:#005691;"
                            class="flex-1 px-4 py-2.5 text-white text-sm font-medium rounded-lg hover:opacity-90 transition-opacity">
                        บันทึก
                    </button>
                    <button type="button"
                            onclick="closeAdjustModal()"
                            class="flex-1 px-4 py-2.5 text-gray-700 text-sm font-medium rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors">
                        ยกเลิก
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script>
    function openAdjustModal(memberId, memberName, currentPoints) {
        document.getElementById('adjust_member_id').value = memberId;
        document.getElementById('adjust_member_name').textContent = memberName;
        document.getElementById('adjust_current_points').textContent = currentPoints.toLocaleString('th-TH');
        document.getElementById('adjustModal').classList.remove('hidden');
    }

    function closeAdjustModal() {
        document.getElementById('adjustModal').classList.add('hidden');
    }

    // Close modal on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAdjustModal();
        }
    });

    // Close modal on background click
    document.getElementById('adjustModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeAdjustModal();
        }
    });
    </script>
</body>
</html>
