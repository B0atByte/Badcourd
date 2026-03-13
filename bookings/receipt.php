<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../config/db.php';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: index.php'); exit; }

// โหลด booking + court
$stmt = $pdo->prepare("
    SELECT b.*, c.court_no, c.vip_room_name, c.court_type, c.is_vip,
           p.name AS promo_name
    FROM bookings b
    JOIN courts c ON b.court_id = c.id
    LEFT JOIN promotions p ON b.promotion_id = p.id
    WHERE b.id = ?
");
$stmt->execute([$id]);
$bk = $stmt->fetch();
if (!$bk) { header('Location: index.php'); exit; }

// โหลด site settings
$rows = $pdo->query("SELECT setting_key, setting_value FROM site_settings")->fetchAll();
$cfg = [];
foreach ($rows as $r) $cfg[$r['setting_key']] = $r['setting_value'];

$siteName      = $cfg['site_name']       ?? 'BARGAIN SPORT';
$siteLogo      = $cfg['site_logo']       ?? '/logo/BPL.png';
$receiptAddr   = $cfg['receipt_address'] ?? '';
$receiptPhone  = $cfg['receipt_phone']   ?? '';
$receiptTaxId  = $cfg['receipt_tax_id']  ?? '';
$receiptFooter = $cfg['receipt_footer']  ?? 'ขอบคุณที่ใช้บริการ';

// คำนวณ
$startDt    = new DateTime($bk['start_datetime']);
$endDt      = (clone $startDt)->modify('+' . $bk['duration_hours'] . ' hour');
$subtotal   = $bk['price_per_hour'] * $bk['duration_hours'];
$discount   = $bk['discount_amount'];
$total      = $bk['total_amount'];
$isVip      = ($bk['court_type'] === 'vip' || $bk['is_vip'] == 1);
$courtLabel = $isVip ? ($bk['vip_room_name'] ?? 'ห้อง VIP') : 'คอร์ต ' . $bk['court_no'];

// วันที่พิมพ์
$printDate = (new DateTime())->format('d/m/Y H:i');

// เลขใบเสร็จ
$receiptNo = 'REC-' . str_pad($bk['id'], 6, '0', STR_PAD_LEFT);

// ตรวจสอบ logo path
$logoAbsPath = __DIR__ . '/..' . $siteLogo;
$logoSrc = file_exists($logoAbsPath) ? $siteLogo . '?v=' . filemtime($logoAbsPath) : '';
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ใบเสร็จ <?= htmlspecialchars($receiptNo) ?> – <?= htmlspecialchars($siteName) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    * { font-family: 'Prompt', sans-serif; }

    @media print {
      .no-print { display: none !important; }
      body { background: white !important; }
      .receipt-card {
        box-shadow: none !important;
        border: none !important;
        margin: 0 !important;
        max-width: 100% !important;
      }
      @page {
        size: A5 portrait;
        margin: 12mm 10mm;
      }
    }

    .receipt-card {
      max-width: 480px;
    }

    .divider-dashed {
      border-top: 2px dashed #e5e7eb;
    }
  </style>
</head>
<body class="bg-gray-100 min-h-screen py-8 px-4">

  <!-- Toolbar (ซ่อนตอนพิมพ์) -->
  <div class="no-print receipt-card mx-auto mb-4 flex gap-2">
    <button onclick="window.print()"
      class="flex items-center gap-2 px-5 py-2.5 text-white text-sm font-medium rounded-lg hover:opacity-90"
      style="background:#D32F2F;">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
      </svg>
      พิมพ์ใบเสร็จ
    </button>
    <a href="index.php"
      class="flex items-center gap-2 px-5 py-2.5 bg-white text-gray-600 text-sm font-medium rounded-lg border border-gray-200 hover:bg-gray-50">
      ← กลับ
    </a>
  </div>

  <!-- ใบเสร็จ -->
  <div class="receipt-card mx-auto bg-white rounded-xl shadow-md overflow-hidden">

    <!-- Header: Logo + ชื่อกิจการ -->
    <div style="background:#D32F2F;" class="px-6 py-5 text-white">
      <div class="flex items-center gap-4">
        <?php if ($logoSrc): ?>
        <div class="w-16 h-16 rounded-xl bg-white/20 p-1.5 flex items-center justify-center shrink-0">
          <img src="<?= htmlspecialchars($logoSrc) ?>" alt="Logo"
            class="w-full h-full object-contain rounded-lg">
        </div>
        <?php endif; ?>
        <div class="flex-1 min-w-0">
          <h1 class="text-xl font-bold leading-tight"><?= htmlspecialchars($siteName) ?></h1>
          <?php if ($receiptAddr): ?>
          <p class="text-red-100 text-xs mt-0.5 leading-relaxed"><?= nl2br(htmlspecialchars($receiptAddr)) ?></p>
          <?php endif; ?>
          <div class="flex flex-wrap gap-x-4 gap-y-0.5 mt-1">
            <?php if ($receiptPhone): ?>
            <p class="text-red-100 text-xs">โทร: <?= htmlspecialchars($receiptPhone) ?></p>
            <?php endif; ?>
            <?php if ($receiptTaxId): ?>
            <p class="text-red-100 text-xs">เลขผู้เสียภาษี: <?= htmlspecialchars($receiptTaxId) ?></p>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- ชื่อใบเสร็จ -->
    <div class="px-6 py-3 border-b border-gray-100 flex justify-between items-center bg-gray-50">
      <div>
        <p class="font-bold text-gray-800 text-base">ใบเสร็จรับเงิน</p>
        <p class="text-xs text-gray-400">Receipt</p>
      </div>
      <div class="text-right">
        <p class="font-mono font-bold text-gray-700 text-sm"><?= htmlspecialchars($receiptNo) ?></p>
        <p class="text-xs text-gray-400">วันที่พิมพ์: <?= $printDate ?></p>
      </div>
    </div>

    <!-- ข้อมูลลูกค้า -->
    <div class="px-6 py-4 border-b border-gray-100">
      <p class="text-xs text-gray-400 uppercase tracking-wide mb-2 font-medium">ข้อมูลผู้จอง</p>
      <div class="grid grid-cols-2 gap-2 text-sm">
        <div>
          <p class="text-gray-400 text-xs">ชื่อ</p>
          <p class="font-medium text-gray-800"><?= htmlspecialchars($bk['customer_name']) ?></p>
        </div>
        <div>
          <p class="text-gray-400 text-xs">เบอร์โทร</p>
          <p class="font-medium text-gray-800"><?= htmlspecialchars($bk['customer_phone']) ?></p>
        </div>
      </div>
    </div>

    <!-- รายการจอง -->
    <div class="px-6 py-4 border-b border-gray-100">
      <p class="text-xs text-gray-400 uppercase tracking-wide mb-3 font-medium">รายการ</p>

      <div class="flex gap-3 items-start">
        <!-- ไอคอนคอร์ต -->
        <div style="background:#FFEBEE;" class="rounded-lg p-2.5 shrink-0">
          <svg class="w-5 h-5" style="color:#D32F2F;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2 2zm0 5h18M12 7V3m0 0L9 6m3-3l3 3"/>
          </svg>
        </div>
        <div class="flex-1">
          <p class="font-semibold text-gray-800"><?= htmlspecialchars($courtLabel) ?></p>
          <p class="text-gray-500 text-sm">
            <?= $startDt->format('d/m/Y') ?>
            &nbsp;·&nbsp;
            <?= $startDt->format('H:i') ?> – <?= $endDt->format('H:i') ?> น.
            (<?= $bk['duration_hours'] ?> ชม.)
          </p>
          <?php if ($bk['promo_name']): ?>
          <span class="inline-block mt-1 text-xs px-2 py-0.5 rounded-full bg-purple-100 text-purple-700">
            โปร: <?= htmlspecialchars($bk['promo_name']) ?>
          </span>
          <?php endif; ?>
          <?php if ($bk['member_badminton_package_id']): ?>
          <span class="inline-block mt-1 text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-700">
            ใช้แพ็กเกจ (<?= $bk['used_package_hours'] ?> ชม.)
          </span>
          <?php endif; ?>
        </div>
        <div class="text-right shrink-0">
          <p class="text-gray-800 font-medium">฿<?= number_format($subtotal, 0) ?></p>
          <p class="text-gray-400 text-xs">฿<?= number_format($bk['price_per_hour'], 0) ?>/ชม.</p>
        </div>
      </div>
    </div>

    <!-- สรุปราคา -->
    <div class="px-6 py-4">
      <div class="space-y-2 text-sm">
        <div class="flex justify-between text-gray-600">
          <span>ราคา (<?= $bk['duration_hours'] ?> ชม. × ฿<?= number_format($bk['price_per_hour'], 0) ?>)</span>
          <span>฿<?= number_format($subtotal, 0) ?></span>
        </div>
        <?php if ($discount > 0): ?>
        <div class="flex justify-between text-green-600">
          <span>
            ส่วนลด
            <?php if (!empty($bk['promotion_discount_percent']) && $bk['promo_name']): ?>
              <span class="text-xs text-gray-400">(<?= htmlspecialchars($bk['promo_name']) ?>)</span>
            <?php endif; ?>
          </span>
          <span>-฿<?= number_format($discount, 0) ?></span>
        </div>
        <?php endif; ?>
      </div>

      <div class="divider-dashed my-3"></div>

      <div class="flex justify-between items-center">
        <span class="font-bold text-gray-800">ยอดชำระ</span>
        <span class="font-bold text-2xl" style="color:#D32F2F;">฿<?= number_format($total, 0) ?></span>
      </div>

      <!-- สถานะการจอง -->
      <div class="mt-3 flex justify-between items-center">
        <span class="text-xs text-gray-400">สถานะ</span>
        <?php if ($bk['status'] === 'booked'): ?>
        <span class="text-xs px-3 py-1 rounded-full bg-green-100 text-green-700 font-medium">ชำระแล้ว</span>
        <?php else: ?>
        <span class="text-xs px-3 py-1 rounded-full bg-red-100 text-red-600 font-medium">ยกเลิก</span>
        <?php endif; ?>
      </div>

      <!-- สลิปการชำระ -->
      <?php if (!empty($bk['payment_slip_path'])): ?>
      <div class="no-print mt-3 flex justify-between items-center text-xs text-gray-400">
        <span>หลักฐานการชำระ</span>
        <a href="<?= htmlspecialchars($bk['payment_slip_path']) ?>" target="_blank"
           class="text-blue-500 underline hover:text-blue-700">ดูสลิป</a>
      </div>
      <?php endif; ?>
    </div>

    <!-- Footer -->
    <?php if ($receiptFooter): ?>
    <div class="px-6 py-3 border-t border-gray-100 bg-gray-50 text-center">
      <p class="text-xs text-gray-400"><?= nl2br(htmlspecialchars($receiptFooter)) ?></p>
    </div>
    <?php endif; ?>

    <!-- Ref -->
    <div class="px-6 py-2 border-t border-gray-100 text-center">
      <p class="text-xs text-gray-300">Booking #<?= $bk['id'] ?> · สร้างเมื่อ <?= (new DateTime($bk['created_at']))->format('d/m/Y H:i') ?></p>
    </div>

  </div>

</body>
</html>
