<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

// guard.php จัดการ session_start() และ redirect แล้ว ไม่ต้องทำซ้ำ

$courts = $pdo->query("SELECT * FROM courts WHERE status <> 'Maintenance' ORDER BY court_type DESC, vip_room_name ASC, court_no")->fetchAll();
$today = date('Y-m-d');
$promoQuery = $pdo->prepare("SELECT id, code, name, discount_percent, discount_type FROM promotions WHERE is_active = 1 AND start_date <= ? AND end_date >= ? ORDER BY name ASC");
$promoQuery->execute([$today, $today]);
$activePromos = $promoQuery->fetchAll();
$success = $error = '';

$posted_court_id = '';
$posted_customer_name = '';
$posted_customer_phone = '';
$posted_date = date('Y-m-d');
$posted_start_time = '16:00';
$posted_hours = 1;
$posted_discount = 0;
$posted_promotion_id = null;
$posted_badminton_package_id = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $court_id = (int) $_POST['court_id'];
    $customer_name = trim($_POST['customer_name']);
    $customer_phone = trim($_POST['customer_phone']);
    $date = $_POST['date'];
    $start_time = $_POST['start_time'];
    $hours = (int) $_POST['hours'];
    $discount = (float) ($_POST['discount'] ?? 0);
    $badminton_package_id = !empty($_POST['badminton_package_id']) ? (int) $_POST['badminton_package_id'] : null;
    $use_package = !empty($badminton_package_id);

    $posted_court_id = $court_id;
    $posted_customer_name = $customer_name;
    $posted_customer_phone = $customer_phone;
    $posted_date = $date;
    $posted_start_time = $start_time;
    $posted_hours = $hours;
    $posted_discount = $discount;
    $posted_badminton_package_id = $badminton_package_id;
    $promotion_id = !empty($_POST['promotion_id']) ? (int) $_POST['promotion_id'] : null;
    $promo_code_input = strtoupper(trim($_POST['promo_code'] ?? ''));
    $posted_promotion_id = $promotion_id;

    // Server-side validation
    if ($court_id <= 0) {
        $error = 'กรุณาเลือกคอร์ต';
    } elseif (empty($customer_name)) {
        $error = 'กรุณากรอกชื่อผู้จอง';
    } elseif (empty($customer_phone)) {
        $error = 'กรุณากรอกเบอร์โทรศัพท์';
    } elseif (!preg_match('/^[0-9]{9,10}$/', $customer_phone)) {
        $error = 'เบอร์โทรศัพท์ต้องเป็นตัวเลข 9-10 หลัก';
    } elseif ($hours < 1 || $hours > 24) {
        $error = 'จำนวนชั่วโมงต้องอยู่ระหว่าง 1–24 ชั่วโมง';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) {
        $error = 'รูปแบบวันที่ไม่ถูกต้อง';
    }

    $start = new DateTime($date . ' ' . $start_time);
    // ตรวจสอบว่า start time อยู่ระหว่าง 06:00–23:00
    $startHour = (int) $start->format('G');
    if (!$error && ($startHour < 6 || $startHour >= 23)) {
        $error = 'เวลาเริ่มต้องอยู่ระหว่าง 06:00–23:00 น.';
    }
    // ตรวจสอบว่า end time ไม่เลยเที่ยงคืน
    $endHour = $startHour + $hours;
    if (!$error && $endHour > 24) {
        $error = 'เวลาสิ้นสุดต้องไม่เกิน 24:00 น.';
    }
    if (!$error && has_overlap($court_id, $start, $hours)) {
        $error = 'เวลานี้มีการจองอยู่แล้ว';
    } elseif (!$error) {
        $courtInfo = $pdo->prepare('SELECT court_type, is_vip, vip_price, normal_price, pricing_group_id FROM courts WHERE id = ?');
        $courtInfo->execute([$court_id]);
        $court = $courtInfo->fetch();

        $isVip = ($court['court_type'] === 'vip' || $court['is_vip'] == 1);
        $group_id = $court['pricing_group_id'] ? (int) $court['pricing_group_id'] : null;

        // Validate badminton package if selected
        if ($use_package) {
            $pkgStmt = $pdo->prepare("
                SELECT mbp.*, bpt.name as type_name, bpt.validity_days
                FROM member_badminton_packages mbp
                JOIN badminton_package_types bpt ON bpt.id = mbp.badminton_package_type_id
                WHERE mbp.id = ? AND mbp.customer_phone = ?
            ");
            $pkgStmt->execute([$badminton_package_id, $customer_phone]);
            $pkg = $pkgStmt->fetch();

            if (!$pkg) {
                $error = 'ไม่พบแพ็กเกจนี้';
            } elseif ($pkg['status'] !== 'active') {
                $error = 'แพ็กเกจหมดอายุหรือใช้ครบแล้ว';
            } elseif (($pkg['hours_total'] - $pkg['hours_used']) < $hours) {
                $remaining = $pkg['hours_total'] - $pkg['hours_used'];
                $error = "แพ็กเกจมีชั่วโมงไม่พอ (เหลือ {$remaining} ชม.)";
            } elseif ($pkg['expiry_date'] && $pkg['expiry_date'] < $date) {
                $error = 'แพ็กเกจหมดอายุแล้ว';
            } elseif ($isVip) {
                $error = 'แพ็กเกจใช้ได้เฉพาะคอร์ตปกติเท่านั้น (ไม่ใช่ VIP)';
            }
        }

        if ($group_id !== null) {
            $pph = pick_price_per_hour($start, $group_id);
        } elseif ($isVip && $court['vip_price'] > 0) {
            $pph = $court['vip_price'];
        } elseif (!$isVip && $court['normal_price'] > 0) {
            $pph = $court['normal_price'];
        } else {
            $pph = pick_price_per_hour($start);
        }

        $total = compute_total($pph, $hours, $discount);
        $created_by = $_SESSION['user']['id'];

        // Check if member exists, if not create new member
        $member_id = null;
        if (!empty($customer_phone)) {
            $memberStmt = $pdo->prepare("SELECT id, points, total_bookings, total_spent FROM members WHERE phone = ?");
            $memberStmt->execute([$customer_phone]);
            $member = $memberStmt->fetch();

            if ($member) {
                // Existing member
                $member_id = $member['id'];
            } elseif (!empty($_POST['register_as_member']) && $_POST['register_as_member'] === '1') {
                // Create new member only if staff chose to register
                $insertMember = $pdo->prepare("
                    INSERT INTO members (phone, name, email, points, total_bookings, total_spent, member_level, status)
                    VALUES (?, ?, NULL, 0, 0, 0, 'Bronze', 'active')
                ");
                $insertMember->execute([$customer_phone, $customer_name]);
                $member_id = $pdo->lastInsertId();
            }
            // else: regular customer, no member record created
        }

        // Promotion validation (promo replaces member discount)
        $applied_promotion_id = null;
        $applied_promo_percent = 0.0;
        if ($promotion_id) {
            $promoCheck = $pdo->prepare("SELECT id, name, discount_percent, discount_type FROM promotions WHERE id = ? AND is_active = 1 AND start_date <= ? AND end_date >= ?");
            $promoCheck->execute([$promotion_id, $start->format('Y-m-d'), $start->format('Y-m-d')]);
            $promoRow = $promoCheck->fetch();
            if ($promoRow) {
                $applied_promotion_id = $promoRow['id'];
                $applied_promo_percent = (float) $promoRow['discount_percent'];
            }
        } elseif (!empty($promo_code_input)) {
            $promoCheck = $pdo->prepare("SELECT id, name, discount_percent, discount_type FROM promotions WHERE code = ? AND is_active = 1 AND start_date <= ? AND end_date >= ?");
            $promoCheck->execute([$promo_code_input, $start->format('Y-m-d'), $start->format('Y-m-d')]);
            $promoRow = $promoCheck->fetch();
            if ($promoRow) {
                $applied_promotion_id = $promoRow['id'];
                $applied_promo_percent = (float) $promoRow['discount_percent'];
            } else {
                $error = 'รหัสโปรโมชั่นไม่ถูกต้อง หรือโปรโมชั่นหมดอายุแล้ว';
            }
        }

        if ($applied_promotion_id) {
            $promoType = $promoRow['discount_type'] ?? 'percent';
            if ($promoType === 'fixed') {
                // ลดเป็นจำนวนเงินคงที่
                $discount = min((float) $promoRow['discount_percent'], $pph * $hours);
            } else {
                // ลดเป็น %
                $discount = (int) floor($pph * $hours * $applied_promo_percent / 100);
            }
            $total = compute_total($pph, $hours, $discount);
        }

        if (!$error) {
            // Determine final price based on package vs promotion
            if ($use_package) {
                // ใช้แพ็กเกจ → ราคา 0, ส่วนลด 0, ไม่ใช้โปรโมชั่น
                $final_price_per_hour = 0;
                $final_discount = 0;
                $final_total = 0;
                $final_promotion_id = null;
                $final_promo_percent = null;
            } else {
                // ไม่ใช้แพ็กเกจ → ใช้ logic เดิม
                $final_price_per_hour = $pph;
                $final_discount = $discount;
                $final_total = $total;
                $final_promotion_id = $applied_promotion_id;
                $final_promo_percent = $applied_promotion_id ? $applied_promo_percent : null;
            }

            // Insert booking with package support
            $stmt = $pdo->prepare('INSERT INTO bookings(
                court_id, customer_name, customer_phone, member_id, start_datetime,
                duration_hours, price_per_hour, discount_amount, total_amount,
                promotion_id, promotion_discount_percent,
                member_badminton_package_id, used_package_hours, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

            $stmt->execute([
                $court_id,
                $customer_name,
                $customer_phone,
                $member_id,
                $start->format('Y-m-d H:i:s'),
                $hours,
                $final_price_per_hour,
                $final_discount,
                $final_total,
                $final_promotion_id,
                $final_promo_percent,
                $use_package ? $badminton_package_id : null,
                $use_package ? $hours : null,
                $created_by
            ]);

            $booking_id = $pdo->lastInsertId();

            // Update badminton package hours if used
            if ($use_package) {
                $pdo->prepare("
                    UPDATE member_badminton_packages
                    SET hours_used = hours_used + ?,
                        status = CASE WHEN hours_total - (hours_used + ?) <= 0
                                 THEN 'exhausted' ELSE 'active' END,
                        updated_at = NOW()
                    WHERE id = ?
                ")->execute([$hours, $hours, $badminton_package_id]);
            }

            // Update member stats and award points (only if not using package)
            $points_earned = 0;
            if ($member_id) {
                if (!$use_package) {
                    // Calculate points: 1 point per 100 baht spent (only for paid bookings)
                    $points_earned = floor($final_total / 100);

                    // Update member statistics
                    $updateMember = $pdo->prepare("
                        UPDATE members
                        SET total_bookings = total_bookings + 1,
                            total_spent = total_spent + ?,
                            points = points + ?,
                            last_booking_date = NOW(),
                            member_level = CASE
                                WHEN total_spent + ? >= 20000 THEN 'Platinum'
                                WHEN total_spent + ? >= 10000 THEN 'Gold'
                                WHEN total_spent + ? >= 5000 THEN 'Silver'
                                ELSE 'Bronze'
                            END
                        WHERE id = ?
                    ");
                    $updateMember->execute([$final_total, $points_earned, $final_total, $final_total, $final_total, $member_id]);
                } else {
                    // ใช้แพ็กเกจ: อัพเดตเฉพาะ total_bookings และ last_booking_date
                    $updateMember = $pdo->prepare("
                        UPDATE members
                        SET total_bookings = total_bookings + 1,
                            last_booking_date = NOW()
                        WHERE id = ?
                    ");
                    $updateMember->execute([$member_id]);
                }

                // Record point transaction
                if ($points_earned > 0) {
                    $pointTxn = $pdo->prepare("
                        INSERT INTO point_transactions (member_id, booking_id, points, type, description, created_by)
                        VALUES (?, ?, ?, 'earn', ?, ?)
                    ");
                    $pointTxn->execute([
                        $member_id,
                        $booking_id,
                        $points_earned,
                        "รับแต้มจากการจอง (฿" . number_format($total, 0) . ")",
                        $created_by
                    ]);
                }
            }

            // Payment slip upload (optional)
            $slip_warning = '';
            if (isset($_FILES['payment_slip']) && $_FILES['payment_slip']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['payment_slip'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                $maxSize = 10 * 1024 * 1024; // 10MB

                if (!in_array($ext, $allowed)) {
                    $slip_warning = 'รูปสลิปต้องเป็น JPG, PNG หรือ WEBP เท่านั้น';
                } elseif ($file['size'] > $maxSize) {
                    $slip_warning = 'ขนาดไฟล์สลิปต้องไม่เกิน 10MB';
                } else {
                    // Validate MIME type จริง (ไม่เชื่อแค่ extension)
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mimeType = $finfo->file($file['tmp_name']);
                    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
                    if (!in_array($mimeType, $allowedMimes)) {
                        $slip_warning = 'ไฟล์ไม่ใช่รูปภาพจริง (JPG/PNG/WebP เท่านั้น)';
                    } else {
                        $filename = 'slip_' . $booking_id . '_' . uniqid() . '.' . $ext;
                        $uploadDir = __DIR__ . '/../uploads/slips/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true); // สร้าง directory หากยังไม่มี
                        }
                        $uploadPath = $uploadDir . $filename;
                        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                            $pdo->prepare('UPDATE bookings SET payment_slip_path = ? WHERE id = ?')
                                ->execute(['uploads/slips/' . $filename, $booking_id]);
                        } else {
                            $slip_warning = 'ไม่สามารถบันทึกไฟล์สลิปได้';
                        }
                    }
                }
            }

            $success = 'จองสำเร็จ' . ($member_id && $points_earned > 0 ? " (ได้รับแต้ม +" . $points_earned . ")" : "")
                . ($slip_warning ? ' — ⚠️ ' . $slip_warning : '');
            $posted_court_id = '';
            $posted_customer_name = '';
            $posted_customer_phone = '';
            $posted_date = date('Y-m-d');
            $posted_start_time = '16:00';
            $posted_hours = 1;
            $posted_discount = 0;
            $posted_promotion_id = null;
        }
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
        'price_per_hour' => (float) $matchedRule['price_per_hour']
    ];
}

$currentStart = new DateTime("$posted_date $posted_start_time");
$dayOfWeek = (int) $currentStart->format('w');
$dayType = ($dayOfWeek === 0 || $dayOfWeek === 6) ? 'เสาร์-อาทิตย์' : 'จันทร์-ศุกร์';

function getCourtDisplayName($court)
{
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

<body style="background:#FAFAFA;" class="min-h-screen">
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <?php include __DIR__ . '/../includes/swal_flash.php'; ?>

    <div class="max-w-5xl mx-auto px-4 py-8">

        <div class="mb-6">
            <h1 style="color:#D32F2F;" class="text-2xl font-bold">จองคอร์ตแบดมินตัน</h1>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Form -->
            <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 p-6">
                <form method="post" id="bookingForm" enctype="multipart/form-data">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">เลือกคอร์ต / ห้อง</label>
                            <select name="court_id" id="courtSelect" required onchange="updatePriceOnCourtChange()"
                                class="w-full px-3 py-2.5 rounded-lg border border-gray-300 focus:border-[#FFEBEE] focus:ring-2 focus:ring-[#FFEBEE]/20 outline-none text-sm">
                                <?php
                                $vipCourts = array_filter($courts, fn($c) => $c['court_type'] === 'vip' || $c['is_vip'] == 1);
                                $normalCourts = array_filter($courts, fn($c) => $c['court_type'] === 'normal' || $c['is_vip'] == 0);

                                if (count($vipCourts) > 0): ?>
                                    <optgroup label="ห้อง VIP">
                                        <?php foreach ($vipCourts as $c): ?>
                                            <option value="<?= $c['id'] ?>" data-is-vip="1"
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
                                            <option value="<?= $c['id'] ?>" data-is-vip="0"
                                                data-court-no="<?= $c['court_no'] ?>"
                                                data-normal-price="<?= $c['normal_price'] ?? 0 ?>" <?= $posted_court_id == $c['id'] ? 'selected' : '' ?>>
                                                <?= getCourtDisplayName($c) ?> (<?= htmlspecialchars($c['status']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">ชื่อผู้จอง</label>
                            <input type="text" name="customer_name" id="customerNameInput" required
                                value="<?= htmlspecialchars($posted_customer_name) ?>" placeholder="กรอกชื่อผู้จอง"
                                class="w-full px-3 py-2.5 rounded-lg border border-gray-300 focus:border-[#FFEBEE] focus:ring-2 focus:ring-[#FFEBEE]/20 outline-none text-sm">
                            <!-- ชื่อที่เคยใช้กับเบอร์นี้ -->
                            <div id="nameChipsWrap" class="hidden mt-2">
                                <p class="text-xs text-gray-500 mb-1.5">เลือกชื่อที่เคยใช้:</p>
                                <div id="nameChips" class="flex flex-wrap gap-1.5"></div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">เบอร์โทรศัพท์</label>
                            <input type="tel" name="customer_phone" id="phoneInput"
                                value="<?= htmlspecialchars($posted_customer_phone) ?>" placeholder="0XX-XXX-XXXX"
                                maxlength="10" pattern="[0-9]{10}"
                                class="w-full px-3 py-2.5 rounded-lg border border-gray-300 focus:border-[#FFEBEE] focus:ring-2 focus:ring-[#FFEBEE]/20 outline-none text-sm"
                                oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10); checkMemberOnPhoneInput();">

                            <!-- Member Info Box -->
                            <div id="memberInfoBox" class="mt-2 hidden">
                                <!-- Loading State -->
                                <div id="memberLoading"
                                    class="hidden bg-blue-50 border border-blue-200 rounded-lg px-3 py-2 text-xs text-blue-600">
                                    <span>กำลังตรวจสอบสมาชิก...</span>
                                </div>

                                <!-- Member Found -->
                                <div id="memberFound" class="hidden bg-green-50 border border-green-200 rounded-lg p-3">
                                    <div class="flex items-start gap-2">
                                        <svg class="w-4 h-4 text-green-600 mt-0.5" fill="currentColor"
                                            viewBox="0 0 20 20">
                                            <path fill-rule="evenodd"
                                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                clip-rule="evenodd" />
                                        </svg>
                                        <div class="flex-1">
                                            <p class="text-xs font-medium text-green-800 mb-1">สมาชิก <span
                                                    id="memberLevel" class="font-bold"></span></p>
                                            <div class="text-xs text-green-700 space-y-0.5">
                                                <div>คะแนนสะสม: <span id="memberPoints" class="font-semibold"></span>
                                                    แต้ม</div>
                                                <div>ส่วนลดสมาชิก: <span id="memberDiscount"
                                                        class="font-semibold"></span>%</div>
                                                <div class="text-[10px] text-green-600 mt-1">จำนวนครั้งที่จอง: <span
                                                        id="memberBookings"></span> ครั้ง</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- New Member -->
                                <div id="memberNew"
                                    class="hidden bg-yellow-50 border border-yellow-200 rounded-lg px-3 py-2 text-xs text-yellow-700">
                                    <div class="flex items-center justify-between gap-2">
                                        <span>ลูกค้าใหม่ · ยังไม่เป็นสมาชิก</span>
                                        <button type="button" id="registerMemberBtn" onclick="toggleRegisterMember()"
                                            class="px-2.5 py-1 rounded-md text-xs font-medium border border-yellow-500 text-yellow-700 hover:bg-yellow-100 transition-colors whitespace-nowrap">
                                            + สมัครสมาชิก
                                        </button>
                                    </div>
                                    <div id="registerMemberConfirm" class="hidden mt-2 text-green-700 font-medium">
                                        ✓ จะสมัครสมาชิกใหม่พร้อมการจองนี้
                                    </div>
                                </div>
                                <input type="hidden" name="register_as_member" id="registerAsMemberInput" value="0">

                                <!-- Badminton Package Selection -->
                                <div id="packageSection" class="hidden mt-3">
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                        แพ็กเกจแบดมินตัน (ถ้ามี)
                                    </label>
                                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                                        <div id="packageList"></div>
                                        <div id="noPackageMsg" class="text-xs text-gray-500">ไม่มีแพ็กเกจที่ใช้ได้</div>
                                    </div>
                                    <input type="hidden" name="badminton_package_id" id="packageInput" value="">
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">วันที่จอง</label>
                            <input type="date" name="date" id="dateInput" required
                                value="<?= htmlspecialchars($posted_date) ?>" onchange="updatePriceDisplay()"
                                class="w-full px-3 py-2.5 rounded-lg border border-gray-300 focus:border-[#FFEBEE] focus:ring-2 focus:ring-[#FFEBEE]/20 outline-none text-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">เวลาเริ่มต้น</label>

                            <!-- Time Display -->
                            <div id="timePickerDisplay" onclick="openTimePicker()"
                                class="flex items-center gap-2 px-4 py-2.5 rounded-lg border border-gray-300 bg-white cursor-pointer hover:border-[#D32F2F] transition-colors w-full">
                                <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span id="timePickerLabel" class="text-sm font-medium text-gray-800"><?php
                                $h = (int) date('G', strtotime($posted_start_time));
                                $m = date('i', strtotime($posted_start_time));
                                $h12 = $h > 12 ? $h - 12 : ($h === 0 ? 12 : $h);
                                $period = $h < 12 ? 'เช้า' : 'บ่าย';
                                echo str_pad($h12, 2, '0', STR_PAD_LEFT) . ':' . $m . ' ' . $period;
                                ?></span>
                            </div>
                            <input type="hidden" name="start_time" id="timeInput"
                                value="<?= htmlspecialchars($posted_start_time) ?>">
                            <p class="text-xs text-gray-400 mt-1">06:00 - 23:00 น.</p>
                        </div>


                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">จำนวนชั่วโมง</label>
                            <input type="number" name="hours" id="hoursInput" required min="1" max="24"
                                value="<?= $posted_hours ?>" oninput="updatePriceDisplay()"
                                class="w-full px-3 py-2.5 rounded-lg border border-gray-300 focus:border-[#FFEBEE] focus:ring-2 focus:ring-[#FFEBEE]/20 outline-none text-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">ส่วนลด (บาท)</label>
                            <input type="number" step="1" name="discount" id="discountInput"
                                value="<?= $posted_discount ?>" oninput="updatePriceDisplay()"
                                class="w-full px-3 py-2.5 rounded-lg border border-gray-300 focus:border-[#FFEBEE] focus:ring-2 focus:ring-[#FFEBEE]/20 outline-none text-sm">
                        </div>

                        <!-- Promotion Section -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">โปรโมชั่น (ไม่บังคับ)</label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">เลือกจากรายการ</label>
                                    <select id="promoSelect" name="promotion_id" onchange="onPromoDropdownChange()"
                                        class="w-full px-3 py-2.5 rounded-lg border border-gray-300 focus:border-[#FFEBEE] focus:ring-2 focus:ring-[#FFEBEE]/20 outline-none text-sm">
                                        <option value="">— ไม่ใช้โปรโมชั่น —</option>
                                        <?php foreach ($activePromos as $p): ?>
                                            <option value="<?= $p['id'] ?>" data-percent="<?= $p['discount_percent'] ?>"
                                                data-type="<?= htmlspecialchars($p['discount_type'], ENT_QUOTES) ?>"
                                                data-name="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>"
                                                data-code="<?= htmlspecialchars($p['code'], ENT_QUOTES) ?>"
                                                <?= $posted_promotion_id == $p['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($p['name']) ?> (<?= $p['discount_type'] === 'fixed' ? '฿' . number_format($p['discount_percent'], 0) : $p['discount_percent'] . '%' ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">หรือกรอกรหัสโปรโมชั่น</label>
                                    <div class="flex gap-2">
                                        <input type="text" id="promoCodeInput" name="promo_code" maxlength="30"
                                            placeholder="เช่น STAFF15" oninput="this.value = this.value.toUpperCase()"
                                            style="text-transform:uppercase"
                                            class="flex-1 px-3 py-2.5 rounded-lg border border-gray-300 focus:border-[#FFEBEE] focus:ring-2 focus:ring-[#FFEBEE]/20 outline-none text-sm">
                                        <button type="button" onclick="checkPromoCode()" style="background:#B71C1C;"
                                            class="px-4 py-2.5 text-white text-sm font-medium rounded-lg hover:opacity-90 transition-opacity whitespace-nowrap">
                                            ตรวจสอบ
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div id="promoInfoBox" class="mt-2 hidden">
                                <div id="promoFound"
                                    class="hidden bg-green-50 border border-green-200 rounded-lg px-3 py-2 text-xs text-green-700">
                                    <span class="font-semibold">ส่วนลดโปรโมชั่น:</span>
                                    <span id="promoNameDisplay"></span>
                                    <span id="promoPercentDisplay" class="font-bold"></span>%
                                    <span class="text-green-500 ml-1">(แทนส่วนลดสมาชิก)</span>
                                </div>
                                <div id="promoNotFound"
                                    class="hidden bg-red-50 border border-red-200 rounded-lg px-3 py-2 text-xs text-red-600">
                                    ไม่พบโปรโมชั่น หรือโปรโมชั่นหมดอายุแล้ว
                                </div>
                            </div>
                        </div>

                        <!-- Payment Slip Upload -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                แนบสลิปการชำระเงิน
                                <span class="text-gray-400 font-normal text-xs ml-1">(ไม่บังคับ · JPG, PNG, WEBP ไม่เกิน
                                    10MB)</span>
                            </label>
                            <div class="flex items-center gap-3">
                                <label for="slipInput" style="border-color:#FFEBEE; color:#B71C1C;"
                                    class="cursor-pointer flex items-center gap-2 px-4 py-2.5 border rounded-lg text-sm hover:bg-[#FAFAFA] transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <span id="slipLabel">เลือกรูปสลิป</span>
                                </label>
                                <button type="button" id="slipClearBtn" onclick="clearSlip()"
                                    class="hidden text-xs text-red-400 hover:text-red-600">
                                    ✕ ลบ
                                </button>
                                <input type="file" name="payment_slip" id="slipInput"
                                    accept="image/jpeg,image/png,image/webp" class="sr-only"
                                    onchange="previewSlip(this)">
                            </div>
                            <!-- Preview -->
                            <div id="slipPreviewWrap" class="hidden mt-3">
                                <img id="slipPreview"
                                    class="max-h-48 rounded-xl border border-gray-200 object-contain shadow-sm">
                            </div>
                        </div>

                        <div class="md:col-span-2 flex gap-3 mt-2">
                            <button type="submit" style="background:#B71C1C;"
                                class="flex-1 sm:flex-none px-8 py-2.5 text-white text-sm font-medium rounded-lg hover:opacity-90 transition-opacity">
                                บันทึกการจอง
                            </button>
                            <a href="/timetable.php" style="color:#B71C1C; border-color:#FFEBEE;"
                                class="flex-1 sm:flex-none px-6 py-2.5 border text-sm font-medium rounded-lg text-center hover:bg-[#FAFAFA] transition-colors">
                                ดูตารางเวลา
                            </a>
                        </div>

                    </div>
                </form>
            </div>

            <!-- Summary -->
            <div class="lg:col-span-1">
                <div style="background:#D32F2F;" class="rounded-xl p-6 sticky top-20">
                    <h3 class="text-white font-semibold mb-5 text-sm uppercase tracking-wide">สรุปการจอง</h3>

                    <div class="space-y-3 mb-5">
                        <div class="flex justify-between text-sm">
                            <span class="text-blue-200">ราคาต่อชั่วโมง</span>
                            <span class="text-white font-medium"><span
                                    id="priceDisplay"><?= number_format($currentPricePerHour, 0) ?></span> ฿</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-blue-200">จำนวน</span>
                            <span class="text-white font-medium"><span id="hoursDisplay"><?= $posted_hours ?></span>
                                ชม.</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-blue-200">ยอดรวม</span>
                            <span class="text-white font-medium"><span
                                    id="subtotalDisplay"><?= number_format($currentSubtotal, 0) ?></span> ฿</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-blue-200">ส่วนลด</span>
                            <span style="color:#FFEBEE;" class="font-medium">-<span
                                    id="discountDisplay"><?= number_format($posted_discount, 0) ?></span> ฿</span>
                        </div>
                    </div>

                    <div style="background:#B71C1C;" class="rounded-lg p-4 text-center">
                        <p class="text-blue-100 text-xs mb-1">ยอดชำระ</p>
                        <p class="text-white text-3xl font-bold" id="totalDisplay">
                            ฿<?= number_format($currentTotal, 0) ?></p>
                    </div>

                    <div class="mt-4 text-xs text-blue-300" id="priceInfoBox">
                        <?php if ($isVipSelected): ?>
                            ห้อง VIP · ฿<?= number_format($currentPricePerHour, 0) ?>/ชม.
                        <?php elseif ($matchedRuleDisplay): ?>
                            <?= htmlspecialchars($matchedRuleDisplay['day_text']) ?> ·
                            <?= htmlspecialchars($matchedRuleDisplay['start_time']) ?>-<?= htmlspecialchars($matchedRuleDisplay['end_time']) ?>
                            น.
                        <?php else: ?>
                            <?= $dayType ?> · <?= $posted_start_time ?> น.
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

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
            // Check if using package
            const packageId = document.getElementById('packageInput').value;
            const usingPackage = packageId && packageId !== '';

            if (usingPackage) {
                // Using package - show zero price
                document.getElementById('priceDisplay').textContent = '0';
                document.getElementById('hoursDisplay').textContent = hours;
                document.getElementById('subtotalDisplay').textContent = '0';
                document.getElementById('discountDisplay').textContent = '0';
                document.getElementById('totalDisplay').textContent = '฿0';
                document.getElementById('priceInfoBox').textContent = 'ใช้แพ็กเกจแบดมินตัน (ไม่เสียค่าใช้จ่าย)';
                return;
            }

            const subtotal = price * hours;

            // Promo overrides member discount; fallback to member discount if no promo
            if (currentPromoData && currentPromoData.discount_percent > 0) {
                let promoDiscount;
                if (currentPromoData.discount_type === 'fixed') {
                    promoDiscount = Math.min(currentPromoData.discount_percent, subtotal);
                } else {
                    promoDiscount = Math.floor(subtotal * currentPromoData.discount_percent / 100);
                }
                document.getElementById('discountInput').value = promoDiscount;
                discount = promoDiscount;
            } else if (currentMemberData && currentMemberData.discount_percent > 0) {
                const memberDiscount = Math.floor(subtotal * currentMemberData.discount_percent / 100);
                document.getElementById('discountInput').value = memberDiscount;
                discount = memberDiscount;
            }

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
            document.getElementById('timeInput').value = `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
            updatePriceDisplay();
        }

        // Promotion state
        let currentPromoData = null;

        function onPromoDropdownChange() {
            const select = document.getElementById('promoSelect');
            const selected = select.options[select.selectedIndex];
            document.getElementById('promoCodeInput').value = '';

            if (select.value === '') {
                currentPromoData = null;
                hidePromoInfo();
            } else {
                currentPromoData = {
                    id: parseInt(select.value),
                    name: selected.getAttribute('data-name'),
                    discount_percent: parseFloat(selected.getAttribute('data-percent')),
                    discount_type: selected.getAttribute('data-type') || 'percent',
                    code: selected.getAttribute('data-code')
                };
                showPromoInfo(currentPromoData.name, currentPromoData.discount_percent);
            }
            updatePriceDisplay();
        }

        function checkPromoCode() {
            const code = document.getElementById('promoCodeInput').value.trim().toUpperCase();
            if (!code) return;

            document.getElementById('promoSelect').value = '';
            currentPromoData = null;
            hidePromoInfo();

            fetch(`/bookings/check_promotion.php?code=${encodeURIComponent(code)}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.found) {
                        currentPromoData = {
                            id: data.promotion.id,
                            name: data.promotion.name,
                            discount_percent: data.promotion.discount_percent,
                            discount_type: data.promotion.discount_type || 'percent',
                            code: data.promotion.code
                        };
                        showPromoInfo(data.promotion.name, data.promotion.discount_percent);

                        // Sync dropdown if this code exists in the list
                        const select = document.getElementById('promoSelect');
                        for (let i = 0; i < select.options.length; i++) {
                            if (select.options[i].getAttribute('data-code') === data.promotion.code) {
                                select.selectedIndex = i;
                                break;
                            }
                        }
                    } else {
                        document.getElementById('promoInfoBox').classList.remove('hidden');
                        document.getElementById('promoFound').classList.add('hidden');
                        document.getElementById('promoNotFound').classList.remove('hidden');
                    }
                    updatePriceDisplay();
                })
                .catch(() => {
                    document.getElementById('promoInfoBox').classList.remove('hidden');
                    document.getElementById('promoNotFound').classList.remove('hidden');
                });
        }

        function showPromoInfo(name, percent) {
            document.getElementById('promoInfoBox').classList.remove('hidden');
            document.getElementById('promoFound').classList.remove('hidden');
            document.getElementById('promoNotFound').classList.add('hidden');
            document.getElementById('promoNameDisplay').textContent = name + ' ';
            document.getElementById('promoPercentDisplay').textContent = percent;
        }

        function hidePromoInfo() {
            document.getElementById('promoInfoBox').classList.add('hidden');
            document.getElementById('promoFound').classList.add('hidden');
            document.getElementById('promoNotFound').classList.add('hidden');
        }

        // Member Check Functionality
        let memberCheckTimeout = null;
        let currentMemberData = null;

        function toggleRegisterMember() {
            const btn = document.getElementById('registerMemberBtn');
            const confirm = document.getElementById('registerMemberConfirm');
            const input = document.getElementById('registerAsMemberInput');
            const isRegistering = input.value === '1';

            if (isRegistering) {
                // Cancel registration
                input.value = '0';
                btn.textContent = '+ สมัครสมาชิก';
                btn.classList.remove('bg-yellow-500', 'text-white', 'border-yellow-500');
                btn.classList.add('border-yellow-500', 'text-yellow-700');
                confirm.classList.add('hidden');
                // Remove member discount
                currentMemberData = null;
                document.getElementById('discountInput').value = 0;
                updatePriceDisplay();
            } else {
                // Confirm registration
                input.value = '1';
                btn.textContent = '✕ ยกเลิก';
                btn.classList.add('bg-yellow-500', 'text-white');
                btn.classList.remove('text-yellow-700');
                confirm.classList.remove('hidden');
            }
        }

        function resetRegisterMember() {
            const input = document.getElementById('registerAsMemberInput');
            const btn = document.getElementById('registerMemberBtn');
            const confirm = document.getElementById('registerMemberConfirm');
            if (input) input.value = '0';
            if (btn) {
                btn.textContent = '+ สมัครสมาชิก';
                btn.classList.remove('bg-yellow-500', 'text-white');
                btn.classList.add('text-yellow-700');
            }
            if (confirm) confirm.classList.add('hidden');
        }

        function renderNameChips(names) {
            const wrap = document.getElementById('nameChipsWrap');
            const container = document.getElementById('nameChips');
            container.innerHTML = '';
            if (!names || names.length <= 1) {
                wrap.classList.add('hidden');
                return;
            }
            names.forEach(name => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = name;
                btn.className = 'px-2.5 py-1 text-xs rounded-full border border-[#D32F2F] text-[#D32F2F] hover:bg-[#D32F2F] hover:text-white transition-colors cursor-pointer';
                btn.addEventListener('click', () => {
                    document.getElementById('customerNameInput').value = name;
                    // Highlight selected
                    container.querySelectorAll('button').forEach(b => {
                        b.classList.remove('bg-[#D32F2F]', 'text-white');
                        b.classList.add('text-[#D32F2F]');
                    });
                    btn.classList.add('bg-[#D32F2F]', 'text-white');
                    btn.classList.remove('text-[#D32F2F]');
                });
                container.appendChild(btn);
            });
            // Auto-select first chip
            if (container.firstChild) {
                container.firstChild.classList.add('bg-[#D32F2F]', 'text-white');
                container.firstChild.classList.remove('text-[#D32F2F]');
            }
            wrap.classList.remove('hidden');
        }

        function clearNameChips() {
            const wrap = document.getElementById('nameChipsWrap');
            const container = document.getElementById('nameChips');
            if (container) container.innerHTML = '';
            if (wrap) wrap.classList.add('hidden');
        }

        function checkMemberOnPhoneInput() {
            const phone = document.getElementById('phoneInput').value;
            const memberInfoBox = document.getElementById('memberInfoBox');
            const memberLoading = document.getElementById('memberLoading');
            const memberFound = document.getElementById('memberFound');
            const memberNew = document.getElementById('memberNew');

            // Clear previous timeout
            if (memberCheckTimeout) {
                clearTimeout(memberCheckTimeout);
            }

            // Hide all states
            memberInfoBox.classList.add('hidden');
            memberLoading.classList.add('hidden');
            memberFound.classList.add('hidden');
            memberNew.classList.add('hidden');
            currentMemberData = null;
            clearNameChips();
            resetRegisterMember();

            // Check if phone is 10 digits
            if (phone.length === 10) {
                // Show loading
                memberInfoBox.classList.remove('hidden');
                memberLoading.classList.remove('hidden');

                // Debounce API call
                memberCheckTimeout = setTimeout(() => {
                    fetch(`/members/check.php?phone=${phone}`)
                        .then(response => response.json())
                        .then(data => {
                            memberLoading.classList.add('hidden');

                            if (data.success && data.is_member) {
                                // Member found
                                currentMemberData = data.member;
                                memberFound.classList.remove('hidden');

                                // Update member info display
                                document.getElementById('memberLevel').textContent = data.member.member_level;
                                document.getElementById('memberPoints').textContent = data.member.points;
                                document.getElementById('memberDiscount').textContent = data.member.discount_percent;
                                document.getElementById('memberBookings').textContent = data.member.total_bookings;

                                // Auto-fill or show name chips
                                const nameInput = document.getElementById('customerNameInput');
                                const names = data.names || [data.member.name];
                                if (names.length > 1) {
                                    renderNameChips(names);
                                    if (!nameInput.value) nameInput.value = names[0];
                                } else {
                                    if (!nameInput.value) nameInput.value = names[0] || data.member.name;
                                }

                                // Calculate and apply member discount
                                const subtotal = parseFloat(document.getElementById('subtotalDisplay').textContent.replace(/,/g, '')) || 0;
                                const memberDiscount = Math.floor(subtotal * data.member.discount_percent / 100);

                                // Update discount field
                                document.getElementById('discountInput').value = memberDiscount;
                                updatePriceDisplay();

                                // Load badminton packages
                                loadPackages(phone);

                            } else if (data.success && !data.is_member) {
                                // Load packages for non-members too
                                loadPackages(phone);
                                // New member
                                memberNew.classList.remove('hidden');
                                // Show past names if any
                                const names = data.names || [];
                                if (names.length > 0) {
                                    const nameInput = document.getElementById('customerNameInput');
                                    if (names.length === 1) {
                                        if (!nameInput.value) nameInput.value = names[0];
                                    } else {
                                        renderNameChips(names);
                                        if (!nameInput.value) nameInput.value = names[0];
                                    }
                                }
                            }
                        })
                        .catch(error => {
                            console.error('Member check error:', error);
                            memberLoading.classList.add('hidden');
                            memberInfoBox.classList.add('hidden');
                        });
                }, 500);
            }
        }

        // Slip preview
        function previewSlip(input) {
            const preview = document.getElementById('slipPreview');
            const wrap = document.getElementById('slipPreviewWrap');
            const label = document.getElementById('slipLabel');
            const clearBtn = document.getElementById('slipClearBtn');

            if (input.files && input.files[0]) {
                const file = input.files[0];
                label.textContent = file.name.length > 24 ? file.name.substring(0, 21) + '...' : file.name;
                clearBtn.classList.remove('hidden');

                const reader = new FileReader();
                reader.onload = e => {
                    preview.src = e.target.result;
                    wrap.classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            }
        }

        function clearSlip() {
            document.getElementById('slipInput').value = '';
            document.getElementById('slipPreview').src = '';
            document.getElementById('slipPreviewWrap').classList.add('hidden');
            document.getElementById('slipLabel').textContent = 'เลือกรูปสลิป';
            document.getElementById('slipClearBtn').classList.add('hidden');
        }

        document.addEventListener('DOMContentLoaded', function () {
            updatePriceOnCourtChange();

            // Check member if phone is already filled (on page load after error)
            const phoneInput = document.getElementById('phoneInput');
            if (phoneInput.value.length === 10) {
                checkMemberOnPhoneInput();
            }
        });

        // ============================================================
        // Badminton Package Functions
        // ============================================================
        function loadPackages(phone) {
            const packageSection = document.getElementById('packageSection');
            const packageList = document.getElementById('packageList');
            const noPackageMsg = document.getElementById('noPackageMsg');

            if (!phone || phone.length < 9) {
                packageSection.classList.add('hidden');
                document.getElementById('packageInput').value = '';
                return;
            }

            fetch(`/bookings/get_badminton_packages_ajax.php?phone=${phone}`)
                .then(r => r.json())
                .then(data => {
                    if (data.packages && data.packages.length > 0) {
                        packageSection.classList.remove('hidden');
                        packageList.innerHTML = data.packages.map(pkg => `
                            <button type="button"
                                    class="w-full text-left mb-2 p-2 rounded border border-blue-300 hover:bg-blue-100 transition-colors pkg-btn"
                                    data-pkg-id="${pkg.id}"
                                    data-remaining="${pkg.remaining}"
                                    onclick="selectPackage(${pkg.id}, ${pkg.remaining})">
                                <div class="text-xs font-semibold text-blue-900">${pkg.type_name}</div>
                                <div class="text-xs text-blue-700">
                                    เหลือ: ${pkg.remaining} ชม. · หมด: ${pkg.expiry_date || 'ไม่จำกัด'}
                                </div>
                            </button>
                        `).join('');
                        noPackageMsg.classList.add('hidden');
                    } else {
                        packageSection.classList.add('hidden');
                        document.getElementById('packageInput').value = '';
                    }
                })
                .catch(() => {
                    packageSection.classList.add('hidden');
                    document.getElementById('packageInput').value = '';
                });
        }

        function selectPackage(pkgId, remaining) {
            document.getElementById('packageInput').value = pkgId;

            // Highlight selected button
            document.querySelectorAll('.pkg-btn').forEach(b => {
                b.classList.remove('bg-blue-200', 'border-blue-500');
                b.classList.add('border-blue-300');
            });
            event.target.closest('button').classList.add('bg-blue-200', 'border-blue-500');
            event.target.closest('button').classList.remove('border-blue-300');

            // Update price display to show "ใช้แพ็กเกจ" instead of price
            updatePriceDisplay();
        }
    </script>
    <!-- ════════════════════════════════════════════════════════════
     Stable Drum Time Picker Modal
     ════════════════════════════════════════════════════════════ -->
    <div id="timePickerModal" style="display:none;position:fixed;inset:0;z-index:9999;
            align-items:center;justify-content:center;
            background:rgba(0,0,0,.5);padding:16px" onclick="if(event.target===this)closeTimePicker()">

        <div style="background:#fff;border-radius:20px;width:100%;max-width:340px;
              overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.3)">

            <!-- Header -->
            <div
                style="background:#D32F2F;padding:14px 18px 10px;display:flex;justify-content:space-between;align-items:center">
                <span style="color:#fff;font-weight:600;font-size:.88rem">เลือกเวลาเริ่มต้น</span>
                <span style="color:#90caf9;font-size:.72rem">06:00 – 23:00 น.</span>
            </div>

            <!-- Live clock -->
            <div style="background:#B71C1C;text-align:center;padding:8px 0">
                <span id="tpLive" style="color:#fff;font-size:1.9rem;font-weight:700;letter-spacing:.1em">16:00</span>
            </div>

            <!-- Columns wrapper -->
            <div style="display:flex;align-items:flex-start;gap:8px;padding:0 12px;height:240px">

                <!-- ══ Hour + Minute (relative wrapper for highlight bar) ══ -->
                <div style="flex:4;display:flex;align-items:flex-start;position:relative;height:100%">

                    <!-- Highlight bar: only over hour+minute area -->
                    <div id="tpHighlightBar" style="
                        position:absolute;left:0;right:0;
                        top:108px;
                        height:44px;
                        background:rgba(0,86,145,.1);
                        border-top:2px solid rgba(0,86,145,.45);
                        border-bottom:2px solid rgba(0,86,145,.45);
                        border-radius:10px;pointer-events:none;z-index:1">
                    </div>

                    <!-- Hour column -->
                    <div style="flex:1;display:flex;flex-direction:column;align-items:center;height:100%">
                        <p style="font-size:.65rem;font-weight:600;color:#6b7280;margin:0 0 2px;line-height:18px">
                            ชั่วโมง</p>
                        <div id="tpHourCol" class="tp-drum"></div>
                    </div>

                    <!-- Colon -->
                    <div
                        style="display:flex;align-items:center;padding-top:18px;color:#9ca3af;font-weight:700;font-size:1.3rem;flex-shrink:0;width:16px;justify-content:center">
                        :</div>

                    <!-- Minute column -->
                    <div style="flex:1;display:flex;flex-direction:column;align-items:center;height:100%">
                        <p style="font-size:.65rem;font-weight:600;color:#6b7280;margin:0 0 2px;line-height:18px">นาที
                        </p>
                        <div id="tpMinCol" class="tp-drum"></div>
                    </div>

                </div><!-- /hour+minute wrapper -->

                <!-- Separator -->
                <div style="width:1px;background:#e5e7eb;margin:20px 0 8px;flex-shrink:0;align-self:stretch"></div>

                <!-- Period buttons -->
                <div style="flex:1.6;display:flex;flex-direction:column;align-items:center;height:100%">
                    <p style="font-size:.65rem;font-weight:600;color:#6b7280;margin:0 0 2px;line-height:18px">ช่วง</p>
                    <div style="flex:1;display:flex;flex-direction:column;gap:8px;justify-content:center;width:100%">
                        <button type="button" id="tpBtnAm" onclick="tpSetPeriod('am')"
                            class="tp-period-btn">เช้า</button>
                        <button type="button" id="tpBtnPm" onclick="tpSetPeriod('pm')"
                            class="tp-period-btn">บ่าย</button>
                    </div>
                </div>

            </div><!-- /columns -->


            <!-- Footer -->
            <div style="display:flex;gap:10px;padding:10px 16px 16px">
                <button type="button" onclick="closeTimePicker()" style="
        flex:1;padding:10px;border:1.5px solid #d1d5db;border-radius:12px;
        font-size:.85rem;color:#374151;background:#fff;cursor:pointer;font-weight:500">
                    ยกเลิก
                </button>
                <button type="button" onclick="confirmTimePicker()" style="
        flex:1;padding:10px;border:none;border-radius:12px;
        font-size:.85rem;font-weight:600;color:#fff;background:#D32F2F;cursor:pointer">
                    ยืนยัน
                </button>
            </div>

        </div>
    </div><!-- /modal -->


    <style>
        .tp-drum {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            width: 100%;
            text-align: center;
            -ms-overflow-style: none;
            scrollbar-width: none
        }

        .tp-drum::-webkit-scrollbar {
            display: none
        }

        .tp-row {
            height: 44px;
            line-height: 44px;
            font-size: 1.05rem;
            font-weight: 500;
            color: #9ca3af;
            cursor: pointer;
            border-radius: 8px;
            user-select: none;
            transition: color .12s, font-weight .12s
        }

        .tp-row:hover {
            color: #374151
        }

        .tp-row.tp-sel {
            color: #D32F2F;
            font-weight: 700;
            font-size: 1.1rem
        }

        .tp-period-btn {
            width: 100%;
            padding: 10px 4px;
            border-radius: 12px;
            font-size: .82rem;
            font-weight: 700;
            cursor: pointer;
            border: 2px solid #e5e7eb;
            background: #fff;
            color: #6b7280;
            transition: all .15s
        }

        .tp-period-btn.active {
            background: #D32F2F;
            color: #fff;
            border-color: #D32F2F
        }
    </style>

    <script>

// ─── Stable Drum Time Picker ────────────────────────────────────
                const TP_IH    = 44;   // item height in px — matches .tp-row height
                const TP_HOURS = Array.from({length:18}, (_,i)=>i+6);   // 6–23
                const TP_MINS  = Array.from({length:60}, (_,i)=>i);      // 0–59

                let _tpOpen    = false;
                let _tpTimers  = { };   // debounce timers per column

                // ── open ─────────────────────────────────────────────────────────
                function openTimePicker() {
  const raw = document.getElementById('timeInput').value || '16:00';
                const [h24, m] = raw.split(':').map(Number);

                tpInit('tpHourCol', TP_HOURS, h24);
                tpInit('tpMinCol',  TP_MINS,  m);

                const modal = document.getElementById('timePickerModal');
                modal.style.display = 'flex';
                _tpOpen = true;

  // Scroll after paint so layout is ready
  requestAnimationFrame(()=>{
                    requestAnimationFrame(() => {
                        tpJumpTo('tpHourCol', TP_HOURS.indexOf(h24));
                        tpJumpTo('tpMinCol', TP_MINS.indexOf(m));
                        tpRefresh();
                    });
  });
}

                // ── init one column ──────────────────────────────────────────────
                function tpInit(colId, items, selVal) {
  const col = document.getElementById(colId);

                // Detach old scroll listener by replacing node
                const fresh = col.cloneNode(false); // empty clone
                col.parentNode.replaceChild(fresh, col);

                // Top spacer (2 rows above center)
                fresh.innerHTML = `<div style="height:${TP_IH*2}px"></div>`;

  items.forEach((v, i) => {
    const div = document.createElement('div');
    div.className = 'tp-row' + (v === selVal ? ' tp-sel' : '');
    div.textContent = String(v).padStart(2,'0');
    div.addEventListener('click', () => tpSmoothTo(fresh, i));
    fresh.appendChild(div);
  });

  // Bottom spacer
  const bot = document.createElement('div');
  bot.style.height = (TP_IH*2) + 'px';
  fresh.appendChild(bot);

  // Scroll listener with debounced snap
  fresh.addEventListener('scroll', ()=>tpOnScroll(fresh, items), {passive:true});
}

// ── scroll snapping ──────────────────────────────────────────────
function tpOnScroll(col, items) {
  clearTimeout(_tpTimers[col.id]);
  _tpTimers[col.id] = setTimeout(()=>{
    const idx = tpNearestIdx(col, items);
    tpSmoothTo(col, idx);
    tpRefresh();
  }, 140); // snap 140ms after scroll stops
  // Lightweight live update while scrolling
  tpRefresh();
}

function tpNearestIdx(col, items) {
  const idx = Math.round(col.scrollTop / TP_IH);
  return Math.max(0, Math.min(idx, items.length - 1));
}

// Jump instantly (no animation) — used on open
function tpJumpTo(colId, idx) {
  const col = document.getElementById(colId);
  if (!col) return;
  col.scrollTop = idx * TP_IH;
}

// Smooth scroll — used on item click / period button
function tpSmoothTo(col, idx) {
  col.scrollTo({top: idx * TP_IH, behavior:'smooth'});
  setTimeout(tpRefresh, 200);
}

// ── refresh highlights + live display ───────────────────────────
function tpRefresh() {
  if (!_tpOpen) return;

  const hIdx = tpNearestIdx(document.getElementById('tpHourCol'), TP_HOURS);
  const mIdx = tpNearestIdx(document.getElementById('tpMinCol'),  TP_MINS);
  const h24  = TP_HOURS[hIdx];
  const min  = TP_MINS[mIdx];

  // Highlight rows
  tpMarkSel('tpHourCol', hIdx);
  tpMarkSel('tpMinCol',  mIdx);

  // Period buttons
  const isAm = h24 < 12;
  document.getElementById('tpBtnAm')?.classList.toggle('active',  isAm);
  document.getElementById('tpBtnPm')?.classList.toggle('active', !isAm);

  // Live display
  document.getElementById('tpLive').textContent =
    String(h24).padStart(2,'0') + ':' + String(min).padStart(2,'0');
}

function tpMarkSel(colId, selIdx) {
  document.getElementById(colId)
    ?.querySelectorAll('.tp-row')
    .forEach((el,i)=>el.classList.toggle('tp-sel', i===selIdx));
}

// ── period buttons ───────────────────────────────────────────────
function tpSetPeriod(period) {
  const col  = document.getElementById('tpHourCol');
  const idx  = tpNearestIdx(col, TP_HOURS);
  const h24  = TP_HOURS[idx];
  let   newH = h24;

  if (period==='am' && h24>=12) newH = Math.max(6,  h24-12);
  if (period==='pm' && h24< 12) newH = Math.min(23, h24+12);

  const newIdx = TP_HOURS.indexOf(newH);
  if (newIdx >= 0) tpSmoothTo(col, newIdx);
}

// ── confirm ──────────────────────────────────────────────────────
function confirmTimePicker() {
  const hIdx = tpNearestIdx(document.getElementById('tpHourCol'), TP_HOURS);
  const mIdx = tpNearestIdx(document.getElementById('tpMinCol'),  TP_MINS);
  const h24  = TP_HOURS[hIdx];
  const min  = TP_MINS[mIdx];

  const val24 = String(h24).padStart(2,'0') + ':' + String(min).padStart(2,'0');
  document.getElementById('timeInput').value = val24;

  // Update display button label
  const period = h24 < 12 ? 'เช้า' : 'บ่าย';
  const h12    = h24 > 12 ? h24-12 : (h24===0 ? 12 : h24);
  document.getElementById('timePickerLabel').textContent =
    String(h12).padStart(2,'0') + ':' + String(min).padStart(2,'0') + ' ' + period;

  if (typeof updatePriceDisplay === 'function') updatePriceDisplay();
  closeTimePicker();
}

// ── close ────────────────────────────────────────────────────────
function closeTimePicker() {
  _tpOpen = false;
  document.getElementById('timePickerModal').style.display = 'none';
}
    </script>

</body>

</html>