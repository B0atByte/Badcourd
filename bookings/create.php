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
        $courtInfo = $pdo->prepare('SELECT court_type, is_vip, vip_price, normal_price FROM courts WHERE id = ?');
        $courtInfo->execute([$court_id]);
        $court = $courtInfo->fetch();

        $isVip = ($court['court_type'] === 'vip' || $court['is_vip'] == 1);
        if ($isVip && $court['vip_price'] > 0) {
            $pph = $court['vip_price'];
        } elseif (!$isVip && $court['normal_price'] > 0) {
            $pph = $court['normal_price'];
        } else {
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

function getCourtDisplayName($court) {
    $isVip = ($court['court_type'] === 'vip' || $court['is_vip'] == 1);
    if ($isVip) {
        $name = $court['vip_room_name'] ?? 'ห้อง VIP';
        $price = $court['vip_price'] > 0 ? ' (' . number_format($court['vip_price'], 0) . ' ฿/ชม.)' : '';
        return "[VIP] $name$price";
    } else {
        return 'คอร์ต ' . $court['court_no'];
    }
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <title>จองคอร์ต - BARGAIN SPORT</title>
</head>
<body style="background:#EDEDCE;" class="min-h-screen">
    <?php include __DIR__.'/../includes/header.php'; ?>

    <div class="max-w-5xl mx-auto px-4 py-8">

        <div class="mb-6">
            <h1 style="color:#0C2C55;" class="text-2xl font-bold">จองคอร์ตแบดมินตัน</h1>
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

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Form -->
            <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 p-6">
                <form method="post" id="bookingForm">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">เลือกคอร์ต / ห้อง</label>
                            <select name="court_id" id="courtSelect" required onchange="updatePriceOnCourtChange()"
                                    class="w-full px-3 py-2.5 rounded-lg border border-gray-300 focus:border-[#629FAD] focus:ring-2 focus:ring-[#629FAD]/20 outline-none text-sm">
                                <?php
                                $vipCourts = array_filter($courts, fn($c) => $c['court_type'] === 'vip' || $c['is_vip'] == 1);
                                $normalCourts = array_filter($courts, fn($c) => $c['court_type'] === 'normal' || $c['is_vip'] == 0);

                                if (count($vipCourts) > 0): ?>
                                    <optgroup label="ห้อง VIP">
                                    <?php foreach ($vipCourts as $c): ?>
                                        <option value="<?= $c['id'] ?>"
                                                data-is-vip="1"
                                                data-vip-price="<?= $c['vip_price'] ?? 0 ?>"
                                                data-court-name="<?= htmlspecialchars($c['vip_room_name'] ?? 'ห้อง VIP') ?>"
                                                <?= $posted_court_id == $c['id'] ? 'selected' : '' ?>>
                                            <?= getCourtDisplayName($c) ?> (<?= htmlspecialchars($c['status']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>

                                <?php if (count($normalCourts) > 0): ?>
                                    <optgroup label="คอร์ตปกติ">
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
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">ชื่อผู้จอง</label>
                            <input type="text" name="customer_name" required
                                   value="<?= htmlspecialchars($posted_customer_name) ?>"
                                   placeholder="กรอกชื่อผู้จอง"
                                   class="w-full px-3 py-2.5 rounded-lg border border-gray-300 focus:border-[#629FAD] focus:ring-2 focus:ring-[#629FAD]/20 outline-none text-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">เบอร์โทรศัพท์</label>
                            <input type="tel" name="customer_phone"
                                   value="<?= htmlspecialchars($posted_customer_phone) ?>"
                                   placeholder="0XX-XXX-XXXX"
                                   maxlength="10" pattern="[0-9]{10}"
                                   class="w-full px-3 py-2.5 rounded-lg border border-gray-300 focus:border-[#629FAD] focus:ring-2 focus:ring-[#629FAD]/20 outline-none text-sm"
                                   oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10)">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">วันที่จอง</label>
                            <input type="date" name="date" id="dateInput" required
                                   value="<?= htmlspecialchars($posted_date) ?>"
                                   onchange="updatePriceDisplay()"
                                   class="w-full px-3 py-2.5 rounded-lg border border-gray-300 focus:border-[#629FAD] focus:ring-2 focus:ring-[#629FAD]/20 outline-none text-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">เวลาเริ่มต้น</label>
                            <div class="flex gap-2">
                                <div class="flex-1">
                                    <input type="number" id="hourInput" min="6" max="23"
                                           value="<?= date('H', strtotime($posted_start_time)) ?>"
                                           placeholder="ชม."
                                           class="w-full px-3 py-2.5 rounded-lg border border-gray-300 focus:border-[#629FAD] focus:ring-2 focus:ring-[#629FAD]/20 outline-none text-sm text-center"
                                           oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 2); updateTimeDisplay();"
                                           onchange="updateTimeDisplay()">
                                </div>
                                <div class="flex items-center text-gray-400 font-bold">:</div>
                                <div class="flex-1">
                                    <input type="number" id="minuteInput" min="0" max="59"
                                           value="<?= date('i', strtotime($posted_start_time)) ?>"
                                           placeholder="นาที"
                                           class="w-full px-3 py-2.5 rounded-lg border border-gray-300 focus:border-[#629FAD] focus:ring-2 focus:ring-[#629FAD]/20 outline-none text-sm text-center"
                                           oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 2); updateTimeDisplay();"
                                           onchange="updateTimeDisplay()">
                                </div>
                            </div>
                            <input type="hidden" name="start_time" id="timeInput" value="<?= htmlspecialchars($posted_start_time) ?>">
                            <p class="text-xs text-gray-400 mt-1">06:00 - 23:00 น.</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">จำนวนชั่วโมง</label>
                            <input type="number" name="hours" id="hoursInput" required
                                   min="1" max="6" value="<?= $posted_hours ?>"
                                   oninput="updatePriceDisplay()"
                                   class="w-full px-3 py-2.5 rounded-lg border border-gray-300 focus:border-[#629FAD] focus:ring-2 focus:ring-[#629FAD]/20 outline-none text-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">ส่วนลด (บาท)</label>
                            <input type="number" step="1" name="discount" id="discountInput"
                                   value="<?= $posted_discount ?>"
                                   oninput="updatePriceDisplay()"
                                   class="w-full px-3 py-2.5 rounded-lg border border-gray-300 focus:border-[#629FAD] focus:ring-2 focus:ring-[#629FAD]/20 outline-none text-sm">
                        </div>

                        <div class="md:col-span-2 flex gap-3 mt-2">
                            <button type="submit"
                                    style="background:#296374;"
                                    class="flex-1 sm:flex-none px-8 py-2.5 text-white text-sm font-medium rounded-lg hover:opacity-90 transition-opacity">
                                บันทึกการจอง
                            </button>
                            <a href="/timetable.php"
                               style="color:#296374; border-color:#629FAD;"
                               class="flex-1 sm:flex-none px-6 py-2.5 border text-sm font-medium rounded-lg text-center hover:bg-[#EDEDCE] transition-colors">
                                ดูตารางเวลา
                            </a>
                        </div>

                    </div>
                </form>
            </div>

            <!-- Summary -->
            <div class="lg:col-span-1">
                <div style="background:#0C2C55;" class="rounded-xl p-6 sticky top-20">
                    <h3 class="text-white font-semibold mb-5 text-sm uppercase tracking-wide">สรุปการจอง</h3>

                    <div class="space-y-3 mb-5">
                        <div class="flex justify-between text-sm">
                            <span class="text-blue-200">ราคาต่อชั่วโมง</span>
                            <span class="text-white font-medium"><span id="priceDisplay"><?= number_format($currentPricePerHour, 0) ?></span> ฿</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-blue-200">จำนวน</span>
                            <span class="text-white font-medium"><span id="hoursDisplay"><?= $posted_hours ?></span> ชม.</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-blue-200">ยอดรวม</span>
                            <span class="text-white font-medium"><span id="subtotalDisplay"><?= number_format($currentSubtotal, 0) ?></span> ฿</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-blue-200">ส่วนลด</span>
                            <span style="color:#629FAD;" class="font-medium">-<span id="discountDisplay"><?= number_format($posted_discount, 0) ?></span> ฿</span>
                        </div>
                    </div>

                    <div style="background:#296374;" class="rounded-lg p-4 text-center">
                        <p class="text-blue-100 text-xs mb-1">ยอดชำระ</p>
                        <p class="text-white text-3xl font-bold" id="totalDisplay">฿<?= number_format($currentTotal, 0) ?></p>
                    </div>

                    <div class="mt-4 text-xs text-blue-300" id="priceInfoBox">
                        <?php if ($isVipSelected): ?>
                            ห้อง VIP · ฿<?= number_format($currentPricePerHour, 0) ?>/ชม.
                        <?php elseif ($matchedRuleDisplay): ?>
                            <?= htmlspecialchars($matchedRuleDisplay['day_text']) ?> · <?= htmlspecialchars($matchedRuleDisplay['start_time']) ?>-<?= htmlspecialchars($matchedRuleDisplay['end_time']) ?> น.
                        <?php else: ?>
                            <?= $dayType ?> · <?= $posted_start_time ?> น.
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <?php include __DIR__.'/../includes/footer.php'; ?>

    <script>
    function updatePriceOnCourtChange() {
        const courtSelect = document.getElementById('courtSelect');
        const selectedOption = courtSelect.options[courtSelect.selectedIndex];
        const isVip = selectedOption.getAttribute('data-is-vip') === '1';
        const vipPrice = parseFloat(selectedOption.getAttribute('data-vip-price')) || 0;
        const normalPrice = parseFloat(selectedOption.getAttribute('data-normal-price')) || 0;
        const courtName = selectedOption.getAttribute('data-court-name') || selectedOption.text;
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

        if (isVip && vipPrice > 0) {
            updateDisplayWithPrice(vipPrice, hours, discount, courtName, null, isVip);
        } else if (!isVip && normalPrice > 0) {
            updateDisplayWithPrice(normalPrice, hours, discount, courtName, null, false);
        } else {
            const urlParams = new URLSearchParams({ date, time, court_id: courtSelect.value });
            fetch(`get_price_ajax.php?${urlParams}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) updateDisplayWithPrice(parseFloat(data.price) || 0, hours, discount, courtName, data.rule, isVip);
                })
                .catch(() => updateDisplayWithPrice(100, hours, discount, courtName, null, isVip));
        }
    }

    function updateDisplayWithPrice(price, hours, discount, courtName, rule, isVip) {
        const subtotal = price * hours;
        const total = Math.max(0, subtotal - discount);
        document.getElementById('priceDisplay').textContent = price.toLocaleString('th-TH');
        document.getElementById('hoursDisplay').textContent = hours;
        document.getElementById('subtotalDisplay').textContent = subtotal.toLocaleString('th-TH');
        document.getElementById('discountDisplay').textContent = discount.toLocaleString('th-TH');
        document.getElementById('totalDisplay').textContent = '฿' + total.toLocaleString('th-TH');

        let info = '';
        if (isVip) {
            info = `ห้อง VIP · ฿${price.toLocaleString('th-TH')}/ชม.`;
        } else if (rule) {
            const dayText = rule.day_type === 'weekday' ? 'จันทร์-ศุกร์' : 'เสาร์-อาทิตย์';
            info = `${dayText} · ${rule.start_time}-${rule.end_time} น.`;
        } else {
            info = `ราคาคงที่ ฿${price.toLocaleString('th-TH')}/ชม.`;
        }
        document.getElementById('priceInfoBox').textContent = info;
    }

    function updateTimeDisplay() {
        let hour = Math.max(6, Math.min(23, parseInt(document.getElementById('hourInput').value) || 6));
        let minute = Math.max(0, Math.min(59, parseInt(document.getElementById('minuteInput').value) || 0));
        document.getElementById('hourInput').value = hour;
        document.getElementById('minuteInput').value = minute;
        document.getElementById('timeInput').value = `${String(hour).padStart(2,'0')}:${String(minute).padStart(2,'0')}`;
        updatePriceDisplay();
    }

    document.addEventListener('DOMContentLoaded', function() {
        updatePriceOnCourtChange();
    });
    </script>
</body>
</html>
