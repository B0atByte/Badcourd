<?php
require_once __DIR__.'/../auth/guard.php';
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../includes/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user'])) {
    header('Location: /auth/login.php');
    exit;
}

// ดึงข้อมูลคอร์ตทั้งหมด แยกตามประเภท
$courts = $pdo->query("SELECT * FROM courts WHERE status <> 'Maintenance' ORDER BY court_type DESC, vip_room_name ASC, court_no")->fetchAll();
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
        // ดึงข้อมูลคอร์ตเพื่อเช็คว่าเป็น VIP หรือไม่
        $courtInfo = $pdo->prepare('SELECT court_type, is_vip, vip_price, normal_price FROM courts WHERE id = ?');
        $courtInfo->execute([$court_id]);
        $court = $courtInfo->fetch();
        
        // ถ้าเป็น VIP ใช้ราคา VIP ถ้าไม่ใช้ pick_price_per_hour หรือ normal_price
        $isVip = ($court['court_type'] === 'vip' || $court['is_vip'] == 1);
        if ($isVip && $court['vip_price'] > 0) {
            $pph = $court['vip_price'];
        } elseif (!$isVip && $court['normal_price'] > 0) {
            // ถ้าคอร์ตปกติมีราคาคงที่ ให้ใช้ราคานั้น
            $pph = $court['normal_price'];
        } else {
            // ใช้ราคาตามช่วงเวลา
            $pph = pick_price_per_hour($start);
        }
        
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

// คำนวณราคาเริ่มต้น
$currentPricePerHour = pick_price_per_hour(new DateTime("$posted_date $posted_start_time"));
$isVipSelected = false;
$selectedCourtName = '';

if ($posted_court_id) {
    $courtCheck = $pdo->prepare('SELECT court_type, is_vip, vip_price, vip_room_name, court_no FROM courts WHERE id = ?');
    $courtCheck->execute([$posted_court_id]);
    $selectedCourt = $courtCheck->fetch();
    
    if ($selectedCourt) {
        $isVipSelected = ($selectedCourt['court_type'] === 'vip' || $selectedCourt['is_vip'] == 1);
        if ($isVipSelected) {
            $currentPricePerHour = $selectedCourt['vip_price'] ?? $currentPricePerHour;
            $selectedCourtName = $selectedCourt['vip_room_name'] ?? 'ห้อง VIP';
        } else {
            $selectedCourtName = 'คอร์ต ' . $selectedCourt['court_no'];
        }
    }
}

$currentSubtotal = $currentPricePerHour * $posted_hours;
$currentTotal = max(0, $currentSubtotal - $posted_discount);

$matchedRule = pick_pricing_rule(new DateTime("$posted_date $posted_start_time"));
$matchedRuleDisplay = null;
if ($matchedRule && !$isVipSelected) {
    $matchedRuleDisplay = [
        'day_type' => $matchedRule['day_type'],
        'day_text' => $matchedRule['day_type'] === 'weekday' ? 'จันทร์-ศุกร์' : 'เสาร์-อาทิตย์',
        'start_time' => substr($matchedRule['start_time'], 0, 5),
        'end_time' => substr($matchedRule['end_time'], 0, 5),
        'price_per_hour' => (float)$matchedRule['price_per_hour']
    ];
}

$currentStart = new DateTime("$posted_date $posted_start_time");
$dayOfWeek = (int)$currentStart->format('w');
$dayType = ($dayOfWeek === 0 || $dayOfWeek === 6) ? 'เสาร์-อาทิตย์' : 'จันทร์-ศุกร์';

// ฟังก์ชันแสดงชื่อคอร์ต
function getCourtDisplayName($court) {
    $isVip = ($court['court_type'] === 'vip' || $court['is_vip'] == 1);
    if ($isVip) {
        $name = $court['vip_room_name'] ?? 'ห้อง VIP';
        $icon = '👑';
        $price = $court['vip_price'] > 0 ? ' (' . number_format($court['vip_price'], 0) . ' ฿/ชม.)' : '';
        return "$icon $name$price";
    } else {
        return '🏸 คอร์ต ' . $court['court_no'];
    }
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>จองคอร์ต - BARGAIN_SPORT</title>
    <style>
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); }
        .court-option-vip {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            font-weight: bold;
        }
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
            <div class="bg-green-50 border-l-4 border-green-500 text-green-800 p-4 rounded-lg mb-6 shadow-md animate-pulse">
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
                                        เลือกคอร์ต / ห้อง VIP
                                    </label>
                                    <select name="court_id" id="courtSelect" required onchange="updatePriceOnCourtChange()"
                                            class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all duration-300 outline-none text-base">
                                        <?php 
                                        // แยก VIP และปกติ
                                        $vipCourts = array_filter($courts, fn($c) => $c['court_type'] === 'vip' || $c['is_vip'] == 1);
                                        $normalCourts = array_filter($courts, fn($c) => $c['court_type'] === 'normal' || $c['is_vip'] == 0);
                                        
                                        if (count($vipCourts) > 0): ?>
                                            <optgroup label="🌟 ห้อง VIP" class="font-bold">
                                            <?php foreach ($vipCourts as $c): ?>
                                                <option value="<?= $c['id'] ?>" 
                                                        data-is-vip="1"
                                                        data-vip-price="<?= $c['vip_price'] ?? 0 ?>"
                                                        data-court-name="<?= htmlspecialchars($c['vip_room_name'] ?? 'ห้อง VIP') ?>"
                                                        <?= $posted_court_id == $c['id'] ? 'selected' : '' ?>
                                                        class="court-option-vip">
                                                    <?= getCourtDisplayName($c) ?> (<?= htmlspecialchars($c['status']) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                            </optgroup>
                                        <?php endif; ?>
                                        
                                        <?php if (count($normalCourts) > 0): ?>
                                            <optgroup label="🏸 คอร์ตปกติ">
                                            <?php foreach ($normalCourts as $c): ?>
                                                <option value="<?= $c['id'] ?>" 
                                                        data-is-vip="0"
                                                        data-court-no="<?= $c['court_no'] ?>"
                                                        data-normal-price="<?= $c['normal_price'] ?? 0 ?>"
                                                        <?= $posted_court_id == $c['id'] ? 'selected' : '' ?>>
                                                    <?= getCourtDisplayName($c) ?> (<?= htmlspecialchars($c['status']) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                            </optgroup>
                                        <?php endif; ?>
                                    </select>
                                    <p class="text-xs text-gray-600 mt-2 flex items-center gap-1">
                                        <i class="fas fa-crown text-amber-500"></i>
                                        <span>ห้อง VIP มีราคาพิเศษตามที่กำหนด</span>
                                    </p>
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
                                           maxlength="10"
                                           pattern="[0-9]{10}"
                                           title="กรุณาใส่เบอร์โทรศัพท์ 10 เลข (เฉพาะตัวเลข)"
                                           class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all duration-300 outline-none"
                                           oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10)">
                                    <p class="text-xs text-gray-500 mt-2">📱 ใส่เบอร์โทร 10 เลข (เช่น 0812345678)</p>
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
                                    <div class="flex gap-2">
                                        <div class="flex-1">
                                            <label class="text-xs text-gray-600 mb-1 block">ชั่วโมง</label>
                                            <input type="number" id="hourInput" min="6" max="23" value="<?= date('H', strtotime($posted_start_time)) ?>"
                                                   class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all duration-300 outline-none text-center text-lg font-bold"
                                                   oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 2); updateTimeDisplay();"
                                                   onchange="updateTimeDisplay()">
                                        </div>
                                        <div class="flex items-end">
                                            <span class="text-2xl font-bold text-gray-500 px-2 pb-3">:</span>
                                        </div>
                                        <div class="flex-1">
                                            <label class="text-xs text-gray-600 mb-1 block">นาที</label>
                                            <input type="number" id="minuteInput" min="0" max="59" value="<?= date('i', strtotime($posted_start_time)) ?>"
                                                   class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all duration-300 outline-none text-center text-lg font-bold"
                                                   oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 2); updateTimeDisplay();"
                                                   onchange="updateTimeDisplay()">
                                        </div>
                                    </div>
                                    <input type="hidden" name="start_time" id="timeInput" value="<?= htmlspecialchars($posted_start_time) ?>">
                                    <p class="text-xs text-gray-500 mt-2">⏰ เลือกเวลาระหว่าง 06:00 - 23:00 (ตัวเลขเท่านั้น)</p>
                                </div>

                                <div>
                                    <label class="flex items-center gap-2 text-gray-700 font-semibold mb-2">
                                        <i class="fas fa-hourglass-half text-purple-600"></i>
                                        จำนวนชั่วโมง
                                    </label>
                                    <input type="number" name="hours" id="hoursInput" required
                                           min="1" max="6" value="<?= $posted_hours ?>"
                                           oninput="updatePriceDisplay()"
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
                                           oninput="updatePriceDisplay()"
                                           onchange="updatePriceDisplay()"
                                           class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all duration-300 outline-none">
                                </div>

                                <div class="md:col-span-2 mt-4 flex flex-wrap gap-3">
                                    <button type="submit" 
                                            class="flex-1 md:flex-initial bg-gradient-to-r from-green-500 to-emerald-600 text-white px-8 py-3 rounded-xl font-semibold shadow-lg hover:shadow-xl hover:-translate-y-1 transition-all duration-300 flex items-center justify-center gap-2">
                                        <i class="fas fa-check-circle text-xl"></i>
                                        บันทึกการจอง
                                    </button>
                                    <a href="/timetable.php"
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
                            <!-- Selected Court Display -->
                            <div id="selectedCourtBox" class="bg-gradient-to-r from-amber-500 to-amber-600 rounded-xl p-4 <?= $isVipSelected ? '' : 'hidden' ?>">
                                <div class="text-center">
                                    <div class="text-white/90 text-xs mb-1 flex items-center justify-center gap-1">
                                        <i class="fas fa-crown"></i>
                                        <span>คุณเลือก</span>
                                    </div>
                                    <div class="text-white text-lg font-bold" id="selectedCourtName">
                                        <?= htmlspecialchars($selectedCourtName) ?>
                                    </div>
                                </div>
                            </div>

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
                                        <?php if ($isVipSelected): ?>
                                            <p>• ห้อง VIP: <?= htmlspecialchars($selectedCourtName) ?></p>
                                            <p>• ราคาพิเศษ ฿<?= number_format($currentPricePerHour, 0) ?>/ชม.</p>
                                        <?php elseif ($matchedRuleDisplay): ?>
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

    function updatePriceOnCourtChange() {
        const courtSelect = document.getElementById('courtSelect');
        const selectedOption = courtSelect.options[courtSelect.selectedIndex];
        const isVip = selectedOption.getAttribute('data-is-vip') === '1';
        const vipPrice = parseFloat(selectedOption.getAttribute('data-vip-price')) || 0;
        const normalPrice = parseFloat(selectedOption.getAttribute('data-normal-price')) || 0;
        const courtName = selectedOption.getAttribute('data-court-name') || selectedOption.text;
        
        const selectedCourtBox = document.getElementById('selectedCourtBox');
        const selectedCourtName = document.getElementById('selectedCourtName');
        
        if (isVip) {
            selectedCourtBox.classList.remove('hidden');
            selectedCourtName.textContent = courtName;
        } else {
            selectedCourtBox.classList.add('hidden');
        }
        
        updatePriceDisplay();
    }

    function updatePriceDisplay() {
        const courtSelect = document.getElementById('courtSelect');
        const selectedOption = courtSelect.options[courtSelect.selectedIndex];
        const isVip = selectedOption.getAttribute('data-is-vip') === '1';
        const vipPrice = parseFloat(selectedOption.getAttribute('data-vip-price')) || 0;
        const normalPrice = parseFloat(selectedOption.getAttribute('data-normal-price')) || 0;
        const courtName = selectedOption.getAttribute('data-court-name') || selectedOption.text;
        
        const date = document.getElementById('dateInput').value;
        const time = document.getElementById('timeInput').value;
        const hours = parseInt(document.getElementById('hoursInput').value) || 0;
        const discount = parseInt(document.getElementById('discountInput').value) || 0;

        if (!date || !time || hours < 1) return;

        // ถ้าเป็น VIP ใช้ราคา VIP ตรงๆ
        if (isVip && vipPrice > 0) {
            updateDisplayWithPrice(vipPrice, hours, discount, courtName, null, isVip);
        } else if (!isVip && normalPrice > 0) {
            // ถ้าคอร์ตปกติมีราคาคงที่
            updateDisplayWithPrice(normalPrice, hours, discount, courtName, null, false);
        } else {
            // ถ้าไม่ใช่ VIP และไม่มีราคาคงที่ ใช้ราคาตามเวลา
            const urlParams = new URLSearchParams({
                date: date,
                time: time,
                court_id: courtSelect.value
            });
            
            fetch(`get_price_ajax.php?${urlParams}`)
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        const price = parseFloat(data.price) || 0;
                        updateDisplayWithPrice(price, hours, discount, courtName, data.rule, isVip);
                    }
                })
                .catch(error => {
                    console.error('Error fetching price:', error);
                    const defaultPrice = 100;
                    updateDisplayWithPrice(defaultPrice, hours, discount, courtName, null, isVip);
                });
        }
    }

    function updateDisplayWithPrice(price, hours, discount, courtName, rule, isVip) {
        const subtotal = price * hours;
        const total = Math.max(0, subtotal - discount);

        document.getElementById('priceDisplay').textContent = price.toLocaleString('th-TH');
        document.getElementById('hoursDisplay').textContent = hours;
        document.getElementById('subtotalDisplay').textContent = subtotal.toLocaleString('th-TH');
        document.getElementById('discountDisplay').textContent = '-' + discount.toLocaleString('th-TH');
        document.getElementById('totalDisplay').textContent = '฿' + total.toLocaleString('th-TH');

        if (isVip) {
            document.getElementById('priceInfoBox').innerHTML = `
                <p class="font-semibold mb-1">ราคาคำนวณจาก:</p>
                <p>• ห้อง VIP: ${courtName}</p>
                <p>• ราคาพิเศษ ฿${price.toLocaleString('th-TH')}/ชม.</p>
            `;
        } else if (rule) {
            const dayText = rule.day_type === 'weekday' ? 'จันทร์-ศุกร์' : 'เสาร์-อาทิตย์';
            document.getElementById('priceInfoBox').innerHTML = `
                <p class="font-semibold mb-1">ราคาคำนวณจาก:</p>
                <p>• วัน: ${dayText}</p>
                <p>• ช่วงเวลา: ${rule.start_time} - ${rule.end_time} น.</p>
                <p>• ฿${price.toLocaleString('th-TH')}/ชม.</p>
            `;
        } else {
            const date = document.getElementById('dateInput').value;
            const time = document.getElementById('timeInput').value;
            const dateObj = new Date(date);
            const dayOfWeek = dateObj.getDay();
            const dayType = (dayOfWeek === 0 || dayOfWeek === 6) ? 'เสาร์-อาทิตย์' : 'จันทร์-ศุกร์';
            
            document.getElementById('priceInfoBox').innerHTML = `
                <p class="font-semibold mb-1">ราคาคำนวณจาก:</p>
                <p>• คอร์ตปกติ: ${courtName}</p>
                <p>• ราคาคงที่ ฿${price.toLocaleString('th-TH')}/ชม.</p>
            `;
        }
    }

    function updateTimeDisplay() {
        let hour = document.getElementById('hourInput').value;
        let minute = document.getElementById('minuteInput').value;
        
        // ตรวจสอบและแก้ไขค่า
        hour = Math.max(6, Math.min(23, parseInt(hour) || 6));
        minute = Math.max(0, Math.min(59, parseInt(minute) || 0));
        
        document.getElementById('hourInput').value = hour;
        document.getElementById('minuteInput').value = minute;
        
        const time = `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
        document.getElementById('timeInput').value = time;
        updatePriceDisplay();
    }

    // เรียกครั้งแรกเมื่อโหลดหน้า
    document.addEventListener('DOMContentLoaded', function() {
        updatePriceOnCourtChange();
    });
    </script>
</body>
</html>