<?php
require_once __DIR__ . '/config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$isLoggedIn = isset($_SESSION['user']);

$date = $_GET['date'] ?? date('Y-m-d');

$courts = $pdo->query('SELECT * FROM courts ORDER BY court_type DESC, vip_room_name ASC, court_no ASC')->fetchAll();
$vipCourts = array_filter($courts, fn($c) => $c['court_type'] === 'vip' || $c['is_vip'] == 1);
$normalCourts = array_filter($courts, fn($c) => $c['court_type'] === 'normal' || $c['is_vip'] == 0);

$startDay = $date . ' 00:00:00';
$endDay   = $date . ' 23:59:59';
$stmt = $pdo->prepare("
    SELECT b.*, c.court_no, c.court_type, c.vip_room_name, c.is_vip
    FROM bookings b
    JOIN courts c ON b.court_id = c.id
    WHERE b.status = 'booked'
      AND b.start_datetime BETWEEN ? AND ?
    ORDER BY c.court_type DESC, c.vip_room_name ASC, c.court_no, b.start_datetime
");
$stmt->execute([$startDay, $endDay]);
$bookings = $stmt->fetchAll();

$grid = [];
$gridDetails = [];
foreach ($courts as $c) {
    $grid[$c['id']] = array_fill(0, 48, '');
    $gridDetails[$c['id']] = array_fill(0, 48, null);
}

foreach ($bookings as $b) {
    $s = new DateTime($b['start_datetime']);
    $startHour = (int)$s->format('G');
    $startMin = (int)$s->format('i');
    $startSlot = $startHour * 2 + ($startMin >= 30 ? 1 : 0);
    $totalSlots = $b['duration_hours'] * 2;
    for ($i = 0; $i < $totalSlots; $i++) {
        $slot = $startSlot + $i;
        if ($slot >= 0 && $slot < 48) {
            $grid[$b['court_id']][$slot] = 'จองแล้ว';
            $gridDetails[$b['court_id']][$slot] = $b;
        }
    }
}

$dateObj = new DateTime($date);
$thaiMonths = [1=>'มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
$thaiDays = ['อาทิตย์','จันทร์','อังคาร','พุธ','พฤหัสบดี','ศุกร์','เสาร์'];
$dayName = $thaiDays[(int)$dateObj->format('w')];
$thaiDate = "วัน$dayName ที่ ".$dateObj->format('d')." ".$thaiMonths[(int)$dateObj->format('n')]." ".($dateObj->format('Y')+543);

function getCourtDisplayName($court) {
    if ($court['court_type'] === 'vip' || $court['is_vip'] == 1) return $court['vip_room_name'] ?? 'ห้อง VIP';
    return 'คอร์ต ' . $court['court_no'];
}
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600&display=swap" rel="stylesheet">
  <title>ตารางคอร์ต - <?= htmlspecialchars($date) ?> - BARGAIN SPORT</title>
  <style>
    * { font-family: 'Prompt', sans-serif !important; }
    .booking-item {
      border-radius: 8px;
      padding: 8px 12px;
      margin-bottom: 6px;
      font-size: 13px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      transition: opacity 0.15s;
    }
    .booking-booked { background: #004A7C; color: white; }
    .booking-vip { background: #005691; color: white; }
    .booking-empty { background: #E8F1F5; color: #666; cursor: default; }
    .court-card {
      background: white;
      border-radius: 12px;
      padding: 16px;
      margin-bottom: 20px;
      border: 1px solid #e5e7eb;
    }
    .court-header {
      font-size: 15px;
      font-weight: 600;
      color: #005691;
      margin-bottom: 12px;
      padding-bottom: 10px;
      border-bottom: 1px solid #f0f0f0;
    }
    .bookings-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
      gap: 8px;
    }
    .bookings-grid .booking-empty { grid-column: 1 / -1; }
  </style>
</head>
<body style="background:#FAFAFA;" class="min-h-screen">

<!-- Simple Header -->
<nav style="background:#005691;" class="sticky top-0 z-50 shadow-md">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex justify-between items-center h-14">
      <a href="/" class="flex items-center gap-2">
        <img src="/logo/BPL.png" alt="BPL" class="w-8 h-8 object-contain rounded">
        <span class="text-white font-semibold text-base">BARGAIN SPORT</span>
      </a>
      <?php if ($isLoggedIn): ?>
      <div class="flex items-center gap-2">
        <span class="text-blue-200 text-sm hidden sm:inline"><?= htmlspecialchars($_SESSION['user']['username']) ?></span>
        <a href="/bookings/create.php"
           class="px-4 py-1.5 text-sm text-white border border-white/30 rounded hover:bg-white/10 transition-colors">
          กลับ
        </a>
      </div>
      <?php else: ?>
      <a href="/auth/login.php"
         style="background:#FF0000;"
         class="px-4 py-1.5 text-sm text-white rounded hover:opacity-90 transition-opacity">
        เข้าสู่ระบบ
      </a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<div class="max-w-6xl mx-auto px-4 py-8">

  <!-- Header -->
  <div class="bg-white rounded-xl border border-gray-200 p-5 mb-5">
    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
      <div>
        <h1 style="color:#005691;" class="text-2xl font-bold mb-1">ตารางคอร์ตแบดมินตัน</h1>
        <p class="text-gray-500 text-sm"><?= $thaiDate ?></p>
      </div>

      <form method="get" class="flex flex-col sm:flex-row gap-2 w-full lg:w-auto">
        <input type="date" name="date" value="<?= htmlspecialchars($date) ?>"
               class="px-3 py-2 border border-gray-300 rounded-lg focus:border-[#005691] focus:ring-2 focus:ring-[#005691]/20 outline-none text-sm">
        <button type="submit"
                style="background:#004A7C;"
                class="px-5 py-2 text-white text-sm rounded-lg hover:opacity-90 transition-opacity">
          แสดงตาราง
        </button>
      </form>
    </div>
  </div>

  <!-- Legend -->
  <div class="bg-white rounded-xl border border-gray-200 p-4 mb-5 flex flex-wrap gap-4 items-center">
    <div class="flex items-center gap-2 text-sm">
      <div class="w-4 h-4 rounded" style="background:#E8F1F5;"></div>
      <span class="text-gray-600">ว่าง</span>
    </div>
    <div class="flex items-center gap-2 text-sm">
      <div class="w-4 h-4 rounded" style="background:#004A7C;"></div>
      <span class="text-gray-600">จองแล้ว (คอร์ตปกติ)</span>
    </div>
    <div class="flex items-center gap-2 text-sm">
      <div class="w-4 h-4 rounded" style="background:#005691;"></div>
      <span class="text-gray-600">จองแล้ว (VIP)</span>
    </div>
    <div class="flex-1 text-right">
      <span class="text-xs text-gray-400">เข้าสู่ระบบเพื่อดูรายละเอียดเพิ่มเติม</span>
    </div>
  </div>

  <!-- VIP Rooms -->
  <?php if (count($vipCourts) > 0): ?>
  <div class="mb-6">
    <h2 style="color:#005691;" class="text-lg font-bold mb-3">ห้อง VIP</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
      <?php foreach ($vipCourts as $c):
        $displayName = getCourtDisplayName($c);
      ?>
      <div class="court-card">
        <div class="court-header">
          <?= htmlspecialchars($displayName) ?>
          <?php if ($c['vip_price']): ?>
          <span class="text-sm font-normal text-gray-400 ml-1">(<?= number_format($c['vip_price'], 0) ?> ฿/ชม.)</span>
          <?php endif; ?>
        </div>
        <div class="bookings-grid">
          <?php
          $displayed = [];
          for ($slot = 0; $slot < 48; $slot++):
            $name = $grid[$c['id']][$slot];
            $details = $gridDetails[$c['id']][$slot];
            if (!empty($name) && $details && !in_array($details['id'], $displayed)):
              $displayed[] = $details['id'];
              $startDate = new DateTime($details['start_datetime']);
              $endDate = new DateTime('@' . ($startDate->getTimestamp() + ($details['duration_hours'] * 3600)));
          ?>
            <div class="booking-item booking-vip">
              <div>
                <div class="font-medium">จองแล้ว</div>
                <div class="text-xs opacity-80 mt-0.5"><?= $startDate->format('H:i') ?> - <?= $endDate->format('H:i') ?></div>
              </div>
            </div>
          <?php endif; endfor; ?>
          <?php if (empty($displayed)): ?>
            <div class="booking-item booking-empty">ทั้งหมดว่าง</div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Normal Courts -->
  <?php if (count($normalCourts) > 0): ?>
  <div class="mb-6">
    <h2 style="color:#005691;" class="text-lg font-bold mb-3">คอร์ตปกติ</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
      <?php foreach ($normalCourts as $c):
        $displayName = getCourtDisplayName($c);
      ?>
      <div class="court-card">
        <div class="court-header flex items-center gap-2">
          <span style="background:#005691;" class="w-7 h-7 rounded flex items-center justify-center text-white text-xs font-bold">
            <?= htmlspecialchars($c['court_no']) ?>
          </span>
          <?= htmlspecialchars($displayName) ?>
        </div>
        <div class="bookings-grid">
          <?php
          $displayed = [];
          for ($slot = 0; $slot < 48; $slot++):
            $name = $grid[$c['id']][$slot];
            $details = $gridDetails[$c['id']][$slot];
            if (!empty($name) && $details && !in_array($details['id'], $displayed)):
              $displayed[] = $details['id'];
              $startDate = new DateTime($details['start_datetime']);
              $endDate = new DateTime('@' . ($startDate->getTimestamp() + ($details['duration_hours'] * 3600)));
          ?>
            <div class="booking-item booking-booked">
              <div>
                <div class="font-medium">จองแล้ว</div>
                <div class="text-xs opacity-80 mt-0.5"><?= $startDate->format('H:i') ?> - <?= $endDate->format('H:i') ?></div>
              </div>
            </div>
          <?php endif; endfor; ?>
          <?php if (empty($displayed)): ?>
            <div class="booking-item booking-empty">ทั้งหมดว่าง</div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Stats -->
  <?php
  $totalSlots = count($courts) * 48;
  $bookedSlots = 0;
  foreach ($grid as $courtData) {
    foreach ($courtData as $slot) { if (!empty($slot)) $bookedSlots++; }
  }
  $freeSlots = $totalSlots - $bookedSlots;
  $occupancyRate = $totalSlots > 0 ? round(($bookedSlots / $totalSlots) * 100, 1) : 0;
  ?>
  <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
    <div class="bg-white rounded-xl border border-gray-200 p-4">
      <p class="text-gray-400 text-xs mb-1">ช่วงเวลาทั้งหมด</p>
      <p style="color:#005691;" class="text-2xl font-bold"><?= $totalSlots ?></p>
      <p class="text-gray-400 text-xs">30 นาที/ช่วง</p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4">
      <p class="text-gray-400 text-xs mb-1">จองแล้ว</p>
      <p style="color:#004A7C;" class="text-2xl font-bold"><?= $bookedSlots ?></p>
      <p class="text-gray-400 text-xs"><?= round($bookedSlots/2, 1) ?> ชั่วโมง</p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4">
      <p class="text-gray-400 text-xs mb-1">ว่าง</p>
      <p style="color:#E8F1F5;" class="text-2xl font-bold"><?= $freeSlots ?></p>
      <p class="text-gray-400 text-xs"><?= round($freeSlots/2, 1) ?> ชั่วโมง</p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4">
      <p class="text-gray-400 text-xs mb-1">อัตราการใช้</p>
      <p style="color:#005691;" class="text-2xl font-bold"><?= $occupancyRate ?>%</p>
      <div class="w-full bg-gray-100 rounded-full h-1.5 mt-2">
        <div style="width:<?= $occupancyRate ?>%; background:#004A7C;" class="h-1.5 rounded-full"></div>
      </div>
    </div>
  </div>

  <?php if (!$isLoggedIn): ?>
  <!-- Login CTA -->
  <div class="mt-6 bg-white rounded-xl border border-gray-200 p-6 text-center">
    <h3 style="color:#005691;" class="text-lg font-semibold mb-2">ต้องการดูรายละเอียดเพิ่มเติมหรือจองคอร์ต?</h3>
    <p class="text-gray-500 text-sm mb-4">เข้าสู่ระบบเพื่อดูชื่อผู้จอง เบอร์โทร และจัดการการจอง</p>
    <a href="/auth/login.php"
       style="background:#FF0000;"
       class="inline-block px-6 py-2.5 text-white text-sm font-medium rounded-lg hover:opacity-90 transition-opacity">
      เข้าสู่ระบบ
    </a>
  </div>
  <?php endif; ?>

</div>

<footer style="background:#005691;" class="mt-12 py-4 text-center text-sm text-blue-200">
  © <?= date('Y') ?> Boat Patthanapong &nbsp;|&nbsp; BARGAIN SPORT System
</footer>

</body>
</html>
