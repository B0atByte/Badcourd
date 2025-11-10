<?php
require_once __DIR__.'/../auth/guard.php';
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../includes/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user'])) {
    header('Location: /BARGAIN SPORT/auth/login.php');
    exit;
}

$courts = $pdo->query("SELECT * FROM courts WHERE status <> 'Maintenance' ORDER BY court_no")->fetchAll();
$success = $error = '';

$posted_court_id = '';
$posted_customer_name = '';
$posted_customer_phone = '';
$posted_date = date('Y-m-d');
$posted_start_time = '16:00';
$posted_hours = 2;
$posted_discount = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $court_id = (int)$_POST['court_id'];
    $customer_name = trim($_POST['customer_name']);
    $customer_phone = trim($_POST['customer_phone']);
    $date = $_POST['date'];
    $start_time = $_POST['start_time'];
    $hours = (int)$_POST['hours'];
    $discount = (float)($_POST['discount'] ?? 0);

    $posted_court_id = $court_id;
    $posted_customer_name = $customer_name;
    $posted_customer_phone = $customer_phone;
    $posted_date = $date;
    $posted_start_time = $start_time;
    $posted_hours = $hours;
    $posted_discount = $discount;

    $start = new DateTime($date . ' ' . $start_time);
    if (has_overlap($court_id, $start, $hours)) {
        $error = 'เวลานี้มีการจองอยู่แล้ว';
    } else {
        $pph = pick_price_per_hour($start);
        $total = compute_total($pph, $hours, $discount);
        $created_by = $_SESSION['user']['id'];

        $stmt = $pdo->prepare('INSERT INTO bookings(
            court_id, customer_name, customer_phone, start_datetime, 
            duration_hours, price_per_hour, discount_amount, total_amount, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        
        $stmt->execute([
            $court_id, $customer_name, $customer_phone,
            $start->format('Y-m-d H:i:s'),
            $hours, $pph, $discount, $total, $created_by
        ]);

        $success = 'จองสำเร็จ';
        $posted_court_id = '';
        $posted_customer_name = '';
        $posted_customer_phone = '';
        $posted_date = date('Y-m-d');
        $posted_start_time = '16:00';
        $posted_hours = 2;
        $posted_discount = 0;
    }
}

$currentStart = new DateTime("$posted_date $posted_start_time");
$currentPricePerHour = pick_price_per_hour($currentStart);
$currentSubtotal = $currentPricePerHour * $posted_hours;
$currentTotal = max(0, $currentSubtotal - $posted_discount);

$matchedRule = pick_pricing_rule($currentStart);
$matchedRuleDisplay = null;
if ($matchedRule) {
    $matchedRuleDisplay = [
        'day_type' => $matchedRule['day_type'],
        'day_text' => $matchedRule['day_type'] === 'weekday' ? 'จันทร์-ศุกร์' : 'เสาร์-อาทิตย์',
        'start_time' => substr($matchedRule['start_time'], 0, 5),
        'end_time' => substr($matchedRule['end_time'], 0, 5),
        'price_per_hour' => (float)$matchedRule['price_per_hour']
    ];
}

$dayOfWeek = (int)$currentStart->format('w');
$dayType = ($dayOfWeek === 0 || $dayOfWeek === 6) ? 'เสาร์-อาทิตย์' : 'จันทร์-ศุกร์';
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>จองคอร์ต - BARGAIN SPORT</title>
    <style>
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); }
    </style>
</head>
<body class="min-h-screen">
    <?php include __DIR__.'/../includes/header.php'; ?>
    
    <div class="container mx-auto px-4 py-8 max-w-7xl">
        <div class="bg-white rounded-3xl shadow-2xl p-6 md:p-8 mb-8">
            
            <div class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-2xl p-6 mb-6 shadow-lg">
                <h4 class="text-2xl md:text-3xl font-bold flex items-center gap-3 m-0">
                    <i class="fas fa-calendar-check"></i>
                    <span>จองคอร์ตแบดมินตัน</span>
                </h4>
            </div>

            <?php if ($success): ?>
            <div class="bg-green-50 border-l-4 border-green-500 text-green-800 p-4 rounded-lg mb-6 shadow-md">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-2xl mr-3"></i>
                    <span class="font-medium"><?= htmlspecialchars($success) ?></span>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="bg-red-50 border-l-4 border-red-500 text-red-800 p-4 rounded-lg mb-6 shadow-md">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-2xl mr-3"></i>
                    <span class="font-medium"><?= htmlspecialchars($error) ?></span>
                </div>
            </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                
                <div class="lg:col-span-7 xl:col-span-8">
                    <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-2xl p-6 border-2 border-gray-200 shadow-md">
                        <form method="post" id="bookingForm">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                
                                <div class="md:col-span-2">
                                    <label class="flex items-center gap-2 text-gray-700 font-semibold mb-2">
                                        <i class="fas fa-warehouse text-purple-600"></i>
                                        เลือกคอร์ต
                                    </label>
                                    <select name="court_id" required
                                            class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all duration-300 outline-none">
                                        <?php foreach ($courts as $c): ?>
                                            <option value="<?= $c['id'] ?>" <?= $posted_court_id == $c['id'] ? 'selected' : '' ?>>
                                                คอร์ต <?= htmlspecialchars($c['court_no']) ?> 
                                                (<?= htmlspecialchars($c['status']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div>
                                    <label class="flex items-center gap-2 text-gray-700 font-semibold mb-2">
                                        <i class="fas fa-user text-purple-600"></i>
                                        ชื่อผู้จอง
                                    </label>
                                    <input type="text" name="customer_name" required
                                           value="<?= htmlspecialchars($posted_customer_name) ?>"
                                           placeholder="กรอกชื่อผู้จอง"
                                           class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all duration-300 outline-none">
                                </div>

                                <div>
                                    <label class="flex items-center gap-2 text-gray-700 font-semibold mb-2">
                                        <i class="fas fa-phone text-purple-600"></i>
                                        เบอร์โทรศัพท์
                                    </label>
                                    <input type="tel" name="customer_phone"
                                           value="<?= htmlspecialchars($posted_customer_phone) ?>"
                                           placeholder="0XX-XXX-XXXX"
                                           class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all duration-300 outline-none">
                                </div>

                                <div>
                                    <label class="flex items-center gap-2 text-gray-700 font-semibold mb-2">
                                        <i class="fas fa-calendar-alt text-purple-600"></i>
                                        วันที่จอง
                                    </label>
                                    <input type="date" name="date" id="dateInput" required
                                           value="<?= htmlspecialchars($posted_date) ?>"
                                           onchange="updatePriceDisplay()"
                                           class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all duration-300 outline-none">
                                </div>

                                <div>
                                    <label class="flex items-center gap-2 text-gray-700 font-semibold mb-2">
                                        <i class="fas fa-clock text-purple-600"></i>
                                        เวลาเริ่มต้น (24 ชั่วโมง)
                                    </label>
                                    <div class="relative">
                                        <input type="text" id="timeDisplay" readonly
                                               value="<?= htmlspecialchars($posted_start_time) ?>"
                                               onclick="toggleTimePicker()"
                                               class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all duration-300 outline-none cursor-pointer text-lg font-bold text-center"
                                               placeholder="เลือกเวลา">
                                        <input type="hidden" name="start_time" id="timeInput" value="<?= htmlspecialchars($posted_start_time) ?>">
                                        
                                        <!-- Time Picker Dropdown -->
                                        <div id="timePicker" class="hidden absolute z-50 mt-2 w-full bg-white rounded-xl shadow-2xl border-2 border-purple-300 max-h-64 overflow-y-auto">
                                            <div class="p-2 bg-purple-600 text-white font-bold text-center rounded-t-xl">
                                                เลือกเวลา (06:00 - 23:00)
                                            </div>
                                            <div class="grid grid-cols-2 gap-1 p-2">
                                                <?php
                                                for ($h = 6; $h <= 23; $h++) {
                                                    foreach (['00', '30'] as $m) {
                                                        $timeValue = sprintf('%02d:%s', $h, $m);
                                                        echo '<button type="button" onclick="selectTime(\''.$timeValue.'\')" class="time-option px-4 py-2 text-center rounded-lg hover:bg-purple-100 hover:text-purple-700 transition-colors font-semibold">'.$timeValue.'</button>';
                                                    }
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">⏰ คลิกเพื่อเลือกเวลา (ตัวอย่าง: 16:00, 17:30)</p>
                                </div>

                                <div>
                                    <label class="flex items-center gap-2 text-gray-700 font-semibold mb-2">
                                        <i class="fas fa-hourglass-half text-purple-600"></i>
                                        จำนวนชั่วโมง
                                    </label>
                                    <input type="number" name="hours" id="hoursInput" required
                                           min="1" max="6" value="<?= $posted_hours ?>"
                                           onchange="updatePriceDisplay()"
                                           class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all duration-300 outline-none">
                                </div>

                                <div>
                                    <label class="flex items-center gap-2 text-gray-700 font-semibold mb-2">
                                        <i class="fas fa-tag text-purple-600"></i>
                                        ส่วนลด (บาท)
                                    </label>
                                    <input type="number" step="1" name="discount" id="discountInput"
                                           value="<?= $posted_discount ?>"
                                           onchange="updatePriceDisplay()"
                                           class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all duration-300 outline-none">
                                </div>

                                <div class="md:col-span-2 mt-4 flex flex-wrap gap-3">
                                    <button type="submit" 
                                            class="flex-1 md:flex-initial bg-gradient-to-r from-green-500 to-emerald-600 text-white px-8 py-3 rounded-xl font-semibold shadow-lg hover:shadow-xl hover:-translate-y-1 transition-all duration-300 flex items-center justify-center gap-2">
                                        <i class="fas fa-check-circle text-xl"></i>
                                        บันทึกการจอง
                                    </button>
                                    <a href="/BARGAIN SPORT/timetable.php"
                                       class="flex-1 md:flex-initial border-2 border-gray-400 text-gray-700 px-8 py-3 rounded-xl font-semibold hover:bg-gray-100 hover:border-gray-600 hover:-translate-y-1 transition-all duration-300 flex items-center justify-center gap-2">
                                        <i class="fas fa-table text-xl"></i>
                                        ดูตารางเวลา
                                    </a>
                                </div>

                            </div>
                        </form>
                    </div>
                </div>

                <div class="lg:col-span-5 xl:col-span-4">
                    <div class="bg-gradient-to-br from-slate-800 to-slate-900 rounded-3xl p-6 shadow-2xl sticky top-4">
                        
                        <div class="bg-gradient-to-r from-purple-600 to-indigo-600 rounded-2xl p-4 mb-6">
                            <h2 class="text-xl font-bold text-white flex items-center gap-2 m-0">
                                <i class="fas fa-receipt"></i>
                                สรุปการจอง
                            </h2>
                        </div>

                        <div class="space-y-4">
                            <div class="bg-slate-700/50 rounded-xl p-4 flex justify-between items-center">
                                <div class="flex items-center gap-3">
                                    <div class="bg-purple-600/50 p-2.5 rounded-lg">
                                        <i class="fas fa-money-bill-wave text-white text-lg"></i>
                                    </div>
                                    <span class="text-slate-300 font-medium">ราคาต่อชั่วโมง</span>
                                </div>
                                <div class="text-right">
                                    <div class="text-white text-2xl font-bold" id="priceDisplay">
                                        <?= number_format($currentPricePerHour, 0) ?>
                                    </div>
                                    <div class="text-slate-400 text-xs">บาท</div>
                                </div>
                            </div>

                            <div class="bg-slate-700/50 rounded-xl p-4 flex justify-between items-center">
                                <div class="flex items-center gap-3">
                                    <div class="bg-blue-600/50 p-2.5 rounded-lg">
                                        <i class="fas fa-clock text-white text-lg"></i>
                                    </div>
                                    <span class="text-slate-300 font-medium">จำนวนชั่วโมง</span>
                                </div>
                                <div class="text-right">
                                    <div class="text-white text-2xl font-bold" id="hoursDisplay"><?= $posted_hours ?></div>
                                    <div class="text-slate-400 text-xs">ชั่วโมง</div>
                                </div>
                            </div>

                            <div class="bg-slate-700/50 rounded-xl p-4 flex justify-between items-center">
                                <div class="flex items-center gap-3">
                                    <div class="bg-indigo-600/50 p-2.5 rounded-lg">
                                        <i class="fas fa-calculator text-white text-lg"></i>
                                    </div>
                                    <span class="text-slate-300 font-medium">รวมเงิน</span>
                                </div>
                                <div class="text-right">
                                    <div class="text-white text-2xl font-bold" id="subtotalDisplay">
                                        <?= number_format($currentSubtotal, 0) ?>
                                    </div>
                                    <div class="text-slate-400 text-xs">บาท</div>
                                </div>
                            </div>

                            <div class="bg-slate-700/50 rounded-xl p-4 flex justify-between items-center">
                                <div class="flex items-center gap-3">
                                    <div class="bg-yellow-600/50 p-2.5 rounded-lg">
                                        <i class="fas fa-tag text-white text-lg"></i>
                                    </div>
                                    <span class="text-slate-300 font-medium">ส่วนลด</span>
                                </div>
                                <div class="text-right">
                                    <div class="text-yellow-400 text-2xl font-bold" id="discountDisplay">
                                        -<?= number_format($posted_discount, 0) ?>
                                    </div>
                                    <div class="text-slate-400 text-xs">บาท</div>
                                </div>
                            </div>

                            <div class="bg-gradient-to-r from-green-600 to-emerald-600 rounded-xl p-6 mt-6">
                                <div class="text-center">
                                    <div class="text-white/90 text-sm mb-2 flex items-center justify-center gap-2">
                                        <i class="fas fa-wallet"></i>
                                        <span>ยอดชำระทั้งหมด</span>
                                    </div>
                                    <div class="text-white text-5xl font-black" id="totalDisplay">
                                        ฿<?= number_format($currentTotal, 0) ?>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-blue-500/20 border border-blue-500/50 rounded-xl p-4 mt-4">
                                <div class="flex items-start gap-3">
                                    <i class="fas fa-info-circle text-blue-400 text-lg mt-0.5"></i>
                                    <div class="text-sm text-blue-200" id="priceInfoBox">
                                        <p class="font-semibold mb-1">ราคาคำนวณจาก:</p>
                                        <?php if ($matchedRuleDisplay): ?>
                                            <p>• วัน: <?= htmlspecialchars($matchedRuleDisplay['day_text']) ?></p>
                                            <p>• ช่วงเวลา: <?= htmlspecialchars($matchedRuleDisplay['start_time']) ?> - <?= htmlspecialchars($matchedRuleDisplay['end_time']) ?> น.</p>
                                            <p>• ฿<?= number_format($matchedRuleDisplay['price_per_hour'], 0) ?>/ชม.</p>
                                        <?php else: ?>
                                            <p>• วัน: <?= $dayType ?></p>
                                            <p>• เวลา: <?= $posted_start_time ?> น.</p>
                                            <p>• ฿<?= number_format($currentPricePerHour, 0) ?>/ชม.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </div>

    <?php include __DIR__.'/../includes/footer.php'; ?>
    
    <script>
    function toggleTimePicker() {
        const picker = document.getElementById('timePicker');
        picker.classList.toggle('hidden');
    }

    function selectTime(time) {
        document.getElementById('timeDisplay').value = time;
        document.getElementById('timeInput').value = time;
        document.getElementById('timePicker').classList.add('hidden');
        updatePriceDisplay();
    }

    // ปิด dropdown เมื่อคลิกนอก
    document.addEventListener('click', function(event) {
        const timePicker = document.getElementById('timePicker');
        const timeDisplay = document.getElementById('timeDisplay');
        if (!timePicker.contains(event.target) && event.target !== timeDisplay) {
            timePicker.classList.add('hidden');
        }
    });

    function updatePriceDisplay() {
        const date = document.getElementById('dateInput').value;
        const time = document.getElementById('timeInput').value;
        const hours = parseInt(document.getElementById('hoursInput').value) || 0;
        const discount = parseInt(document.getElementById('discountInput').value) || 0;

        if (!date || !time) return;

        fetch(`get_price_ajax.php?date=${date}&time=${time}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const price = parseFloat(data.price) || 0;
                    const subtotal = price * hours;
                    const total = Math.max(0, subtotal - discount);

                    document.getElementById('priceDisplay').textContent = price.toLocaleString('th-TH');
                    document.getElementById('hoursDisplay').textContent = hours;
                    document.getElementById('subtotalDisplay').textContent = subtotal.toLocaleString('th-TH');
                    document.getElementById('discountDisplay').textContent = '-' + discount.toLocaleString('th-TH');
                    document.getElementById('totalDisplay').textContent = '฿' + total.toLocaleString('th-TH');

                    if (data.rule) {
                        const dayText = data.rule.day_type === 'weekday' ? 'จันทร์-ศุกร์' : 'เสาร์-อาทิตย์';
                        document.getElementById('priceInfoBox').innerHTML = `
                            <p class="font-semibold mb-1">ราคาคำนวณจาก:</p>
                            <p>• วัน: ${dayText}</p>
                            <p>• ช่วงเวลา: ${data.rule.start_time} - ${data.rule.end_time} น.</p>
                            <p>• ฿${price.toLocaleString('th-TH')}/ชม.</p>
                        `;
                    }
                }
            })
            .catch(error => console.error('Error:', error));
    }
    </script>
</body>
</html>