<?php
require_once __DIR__.'/../auth/guard.php';
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../includes/helpers.php';

$id = (int)($_GET['id'] ?? 0);
$bk = $pdo->prepare('SELECT * FROM bookings WHERE id=?');
$bk->execute([$id]);
$booking = $bk->fetch();

if (!$booking) {
    header('Location: index.php');
    exit;
}

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['date'];
    $start_time = $_POST['start_time'];
    $hours = (int)$_POST['hours'];
    $start = new DateTime($date . ' ' . $start_time);

    if (has_overlap($booking['court_id'], $start, $hours, $id)) {
        $error = 'เวลานี้มีการจองอื่นอยู่แล้ว';
    } else {
        $pph = pick_price_per_hour($start);
        $total = compute_total($pph, $hours, (float)$booking['discount_amount']);
        $stmt = $pdo->prepare('UPDATE bookings SET start_datetime=?, duration_hours=?, price_per_hour=?, total_amount=?, updated_at=NOW() WHERE id=?');
        $stmt->execute([$start->format('Y-m-d H:i:s'), $hours, $pph, $total, $id]);
        $success = 'อัปเดตการจองสำเร็จ';

        $bk->execute([$id]);
        $booking = $bk->fetch();
    }
}

$startDt = new DateTime($booking['start_datetime']);
$currentDate = $startDt->format('Y-m-d');
$currentTime = $startDt->format('H:i');
$currentHours = $booking['duration_hours'];
$currentPricePerHour = $booking['price_per_hour'];
$currentSubtotal = $currentPricePerHour * $currentHours;
$currentDiscount = $booking['discount_amount'];
$currentTotal = $booking['total_amount'];

$courtStmt = $pdo->prepare('SELECT * FROM courts WHERE id=?');
$courtStmt->execute([$booking['court_id']]);
$court = $courtStmt->fetch();
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <title>เลื่อนการจอง - BARGAIN SPORT</title>
</head>
<body style="background:#EDEDCE;" class="min-h-screen">
    <?php include __DIR__.'/../includes/header.php'; ?>

    <div class="max-w-3xl mx-auto px-4 py-8">

        <div class="mb-6">
            <h1 style="color:#0C2C55;" class="text-2xl font-bold">เลื่อนการจอง</h1>
            <p class="text-gray-500 text-sm mt-0.5">แก้ไขวันที่และเวลาการจอง</p>
        </div>

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

        <!-- Booking Info -->
        <div class="bg-white rounded-xl border border-gray-200 p-5 mb-5">
            <h3 style="color:#0C2C55;" class="font-semibold mb-3 text-sm uppercase tracking-wide">ข้อมูลการจอง #<?= $booking['id'] ?></h3>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                <div>
                    <span class="text-gray-500">คอร์ต</span>
                    <p class="font-medium text-gray-800 mt-0.5">คอร์ต <?= htmlspecialchars($court['court_no']) ?></p>
                </div>
                <div>
                    <span class="text-gray-500">ผู้จอง</span>
                    <p class="font-medium text-gray-800 mt-0.5"><?= htmlspecialchars($booking['customer_name']) ?></p>
                </div>
                <div>
                    <span class="text-gray-500">เบอร์โทร</span>
                    <p class="font-medium text-gray-800 mt-0.5"><?= htmlspecialchars($booking['customer_phone']) ?></p>
                </div>
                <div>
                    <span class="text-gray-500">ส่วนลด</span>
                    <p class="font-medium text-gray-800 mt-0.5">฿<?= number_format($currentDiscount, 0) ?></p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">

            <!-- Form -->
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h3 style="color:#0C2C55;" class="font-semibold mb-4 text-sm">เลื่อนวันและเวลา</h3>

                <form method="post">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">วันที่ใหม่</label>
                            <input type="date" name="date" required
                                   value="<?= htmlspecialchars($currentDate) ?>"
                                   class="w-full px-3 py-2.5 rounded-lg border border-gray-300 focus:border-[#629FAD] focus:ring-2 focus:ring-[#629FAD]/20 outline-none text-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">เวลาเริ่มต้นใหม่</label>
                            <input type="time" name="start_time" required
                                   value="<?= htmlspecialchars($currentTime) ?>"
                                   min="06:00" max="23:00"
                                   class="w-full px-3 py-2.5 rounded-lg border border-gray-300 focus:border-[#629FAD] focus:ring-2 focus:ring-[#629FAD]/20 outline-none text-sm">
                            <p class="text-xs text-gray-400 mt-1">06:00 - 23:00 น.</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">จำนวนชั่วโมง</label>
                            <input type="number" name="hours" required
                                   min="1" max="6" value="<?= $currentHours ?>"
                                   class="w-full px-3 py-2.5 rounded-lg border border-gray-300 focus:border-[#629FAD] focus:ring-2 focus:ring-[#629FAD]/20 outline-none text-sm">
                        </div>

                        <div class="flex gap-3 pt-2">
                            <button type="submit"
                                    style="background:#296374;"
                                    class="flex-1 py-2.5 text-white text-sm font-medium rounded-lg hover:opacity-90 transition-opacity">
                                บันทึก
                            </button>
                            <a href="index.php"
                               style="color:#296374; border-color:#629FAD;"
                               class="flex-1 py-2.5 border text-sm font-medium rounded-lg text-center hover:bg-[#EDEDCE] transition-colors">
                                กลับ
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Summary -->
            <div style="background:#0C2C55;" class="rounded-xl p-6">
                <h3 class="text-white font-semibold mb-4 text-sm uppercase tracking-wide">สรุปยอดปัจจุบัน</h3>

                <div class="space-y-3 mb-5">
                    <div class="flex justify-between text-sm">
                        <span class="text-blue-200">ราคา/ชม.</span>
                        <span class="text-white">฿<?= number_format($currentPricePerHour, 0) ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-blue-200">จำนวน</span>
                        <span class="text-white"><?= $currentHours ?> ชม.</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-blue-200">รวม</span>
                        <span class="text-white">฿<?= number_format($currentSubtotal, 0) ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-blue-200">ส่วนลด</span>
                        <span style="color:#629FAD;">-฿<?= number_format($currentDiscount, 0) ?></span>
                    </div>
                </div>

                <div style="background:#296374;" class="rounded-lg p-4 text-center">
                    <p class="text-blue-100 text-xs mb-1">ยอดชำระ</p>
                    <p class="text-white text-3xl font-bold">฿<?= number_format($currentTotal, 0) ?></p>
                </div>

                <p class="text-blue-300 text-xs mt-4">หมายเหตุ: ราคาอาจเปลี่ยนตามเวลาที่เลือกใหม่</p>
            </div>

        </div>
    </div>

    <?php include __DIR__.'/../includes/footer.php'; ?>
</body>
</html>
