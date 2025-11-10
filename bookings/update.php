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
        
        // Refresh booking data
        $bk->execute([$id]);
        $booking = $bk->fetch();
    }
}

$startDt = new DateTime($booking['start_datetime']);
$currentDate = $startDt->format('Y-m-d');
$currentTime = $startDt->format('H:i');
$currentHours = $booking['duration_hours'];

// คำนวณราคาปัจจุบัน
$currentStart = new DateTime($booking['start_datetime']);
$currentPricePerHour = $booking['price_per_hour'];
$currentSubtotal = $currentPricePerHour * $currentHours;
$currentDiscount = $booking['discount_amount'];
$currentTotal = $booking['total_amount'];

// ดึงข้อมูลคอร์ต
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>เลื่อนการจอง - BARGAIN SPORT</title>
    <style>
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); }
    </style>
</head>
<body class="min-h-screen">
    <?php include __DIR__.'/../includes/header.php'; ?>
    
    <div class="container mx-auto px-4 py-8 max-w-5xl">
        <div class="bg-white rounded-3xl shadow-2xl p-6 md:p-8 mb-8">
            
            <!-- Page Header -->
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-2xl p-6 mb-6 shadow-lg">
                <h4 class="text-2xl md:text-3xl font-bold flex items-center gap-3 m-0">
                    <i class="fas fa-edit"></i>
                    <span>เลื่อนการจอง</span>
                </h4>
                <p class="mt-2 text-blue-100">แก้ไขวันที่และเวลาการจอง</p>
            </div>

            <!-- Success Alert -->
            <?php if ($success): ?>
            <div class="bg-green-50 border-l-4 border-green-500 text-green-800 p-4 rounded-lg mb-6 shadow-md">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-2xl mr-3"></i>
                    <span class="font-medium"><?= htmlspecialchars($success) ?></span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Error Alert -->
            <?php if ($error): ?>
            <div class="bg-red-50 border-l-4 border-red-500 text-red-800 p-4 rounded-lg mb-6 shadow-md">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-2xl mr-3"></i>
                    <span class="font-medium"><?= htmlspecialchars($error) ?></span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Booking Info Card -->
            <div class="bg-gradient-to-br from-blue-50 to-purple-50 rounded-2xl p-6 mb-6 border-2 border-blue-200">
                <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
                    <i class="fas fa-info-circle text-blue-600"></i>
                    ข้อมูลการจอง
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <span class="text-gray-600 text-sm">เลขที่จอง:</span>
                        <span class="font-bold text-gray-800 ml-2">#<?= $booking['id'] ?></span>
                    </div>
                    <div>
                        <span class="text-gray-600 text-sm">คอร์ต:</span>
                        <span class="font-bold text-gray-800 ml-2">คอร์ต <?= htmlspecialchars($court['court_no']) ?></span>
                    </div>
                    <div>
                        <span class="text-gray-600 text-sm">ผู้จอง:</span>
                        <span class="font-bold text-gray-800 ml-2"><?= htmlspecialchars($booking['customer_name']) ?></span>
                    </div>
                    <div>
                        <span class="text-gray-600 text-sm">เบอร์โทร:</span>
                        <span class="font-bold text-gray-800 ml-2"><?= htmlspecialchars($booking['customer_phone']) ?></span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                
                <!-- Form Section -->
                <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-2xl p-6 border-2 border-gray-200 shadow-md">
                    <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
                        <i class="fas fa-calendar-alt text-purple-600"></i>
                        เลื่อนวันและเวลา
                    </h3>
                    
                    <form method="post" id="updateForm">
                        <div class="space-y-4">
                            
                            <!-- Date -->
                            <div>
                                <label class="flex items-center gap-2 text-gray-700 font-semibold mb-2">
                                    <i class="fas fa-calendar text-purple-600"></i>
                                    วันที่ใหม่
                                </label>
                                <input type="date" name="date" required
                                       value="<?= htmlspecialchars($currentDate) ?>"
                                       class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all duration-300 outline-none">
                            </div>

                            <!-- Start Time -->
                            <div>
                                <label class="flex items-center gap-2 text-gray-700 font-semibold mb-2">
                                    <i class="fas fa-clock text-purple-600"></i>
                                    เวลาเริ่มต้นใหม่
                                </label>
                                <input type="time" name="start_time" required
                                       value="<?= htmlspecialchars($currentTime) ?>"
                                       min="06:00" max="23:00"
                                       class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all duration-300 outline-none">
                                <p class="text-xs text-gray-500 mt-1">⏰ เวลา 06:00-23:00 น.</p>
                            </div>

                            <!-- Hours -->
                            <div>
                                <label class="flex items-center gap-2 text-gray-700 font-semibold mb-2">
                                    <i class="fas fa-hourglass-half text-purple-600"></i>
                                    จำนวนชั่วโมง
                                </label>
                                <input type="number" name="hours" required
                                       min="1" max="6" value="<?= $currentHours ?>"
                                       class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all duration-300 outline-none">
                            </div>

                            <!-- Buttons -->
                            <div class="flex flex-col gap-3 mt-6">
                                <button type="submit" 
                                        class="w-full bg-gradient-to-r from-blue-500 to-indigo-600 text-white px-6 py-3 rounded-xl font-semibold shadow-lg hover:shadow-xl hover:-translate-y-1 transition-all duration-300 flex items-center justify-center gap-2">
                                    <i class="fas fa-save text-xl"></i>
                                    บันทึกการเปลี่ยนแปลง
                                </button>
                                <a href="index.php"
                                   class="w-full border-2 border-gray-400 text-gray-700 px-6 py-3 rounded-xl font-semibold hover:bg-gray-100 hover:border-gray-600 transition-all duration-300 flex items-center justify-center gap-2">
                                    <i class="fas fa-arrow-left text-xl"></i>
                                    กลับหน้ารายการจอง
                                </a>
                            </div>

                        </div>
                    </form>
                </div>

                <!-- Summary Section -->
                <div class="bg-gradient-to-br from-slate-800 to-slate-900 rounded-2xl p-6 shadow-2xl">
                    
                    <div class="bg-gradient-to-r from-purple-600 to-indigo-600 rounded-xl p-4 mb-6">
                        <h3 class="text-lg font-bold text-white flex items-center gap-2 m-0">
                            <i class="fas fa-receipt"></i>
                            สรุปการจอง
                        </h3>
                    </div>

                    <div class="space-y-4">
                        <!-- Price per hour -->
                        <div class="bg-slate-700/50 rounded-xl p-4 flex justify-between items-center">
                            <div class="flex items-center gap-3">
                                <div class="bg-purple-600/50 p-2 rounded-lg">
                                    <i class="fas fa-money-bill-wave text-white"></i>
                                </div>
                                <span class="text-slate-300 text-sm font-medium">ราคาต่อชั่วโมง</span>
                            </div>
                            <div class="text-right">
                                <div class="text-white text-xl font-bold">
                                    <?= number_format($currentPricePerHour, 0) ?>
                                </div>
                                <div class="text-slate-400 text-xs">บาท</div>
                            </div>
                        </div>

                        <!-- Hours -->
                        <div class="bg-slate-700/50 rounded-xl p-4 flex justify-between items-center">
                            <div class="flex items-center gap-3">
                                <div class="bg-blue-600/50 p-2 rounded-lg">
                                    <i class="fas fa-clock text-white"></i>
                                </div>
                                <span class="text-slate-300 text-sm font-medium">จำนวนชั่วโมง</span>
                            </div>
                            <div class="text-right">
                                <div class="text-white text-xl font-bold"><?= $currentHours ?></div>
                                <div class="text-slate-400 text-xs">ชั่วโมง</div>
                            </div>
                        </div>

                        <!-- Subtotal -->
                        <div class="bg-slate-700/50 rounded-xl p-4 flex justify-between items-center">
                            <div class="flex items-center gap-3">
                                <div class="bg-indigo-600/50 p-2 rounded-lg">
                                    <i class="fas fa-calculator text-white"></i>
                                </div>
                                <span class="text-slate-300 text-sm font-medium">รวมเงิน</span>
                            </div>
                            <div class="text-right">
                                <div class="text-white text-xl font-bold">
                                    <?= number_format($currentSubtotal, 0) ?>
                                </div>
                                <div class="text-slate-400 text-xs">บาท</div>
                            </div>
                        </div>

                        <!-- Discount -->
                        <div class="bg-slate-700/50 rounded-xl p-4 flex justify-between items-center">
                            <div class="flex items-center gap-3">
                                <div class="bg-yellow-600/50 p-2 rounded-lg">
                                    <i class="fas fa-tag text-white"></i>
                                </div>
                                <span class="text-slate-300 text-sm font-medium">ส่วนลด</span>
                            </div>
                            <div class="text-right">
                                <div class="text-yellow-400 text-xl font-bold">
                                    -<?= number_format($currentDiscount, 0) ?>
                                </div>
                                <div class="text-slate-400 text-xs">บาท</div>
                            </div>
                        </div>

                        <!-- Total -->
                        <div class="bg-gradient-to-r from-green-600 to-emerald-600 rounded-xl p-6 mt-6">
                            <div class="text-center">
                                <div class="text-white/90 text-sm mb-2 flex items-center justify-center gap-2">
                                    <i class="fas fa-wallet"></i>
                                    <span>ยอดชำระทั้งหมด</span>
                                </div>
                                <div class="text-white text-4xl font-black">
                                    ฿<?= number_format($currentTotal, 0) ?>
                                </div>
                            </div>
                        </div>

                        <!-- Warning -->
                        <div class="bg-orange-500/20 border border-orange-500/50 rounded-xl p-4 mt-4">
                            <div class="flex items-start gap-3">
                                <i class="fas fa-exclamation-triangle text-orange-400 text-lg mt-0.5"></i>
                                <div class="text-sm text-orange-200">
                                    <p class="font-semibold mb-1">⚠️ คำเตือน</p>
                                    <p>• ราคาอาจเปลี่ยนตามเวลาที่เลือกใหม่</p>
                                    <p>• ตรวจสอบว่าคอร์ตว่างในเวลาที่ต้องการ</p>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </div>

    <?php include __DIR__.'/../includes/footer.php'; ?>
</body>
</html>