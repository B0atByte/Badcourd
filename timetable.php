<?php
require_once __DIR__ . '/config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$isLoggedIn = isset($_SESSION['user']);

$courts = $pdo->query('SELECT * FROM courts ORDER BY court_type DESC, vip_room_name ASC, court_no ASC')->fetchAll();
$vipCourts    = array_filter($courts, fn($c) => $c['court_type'] === 'vip' || $c['is_vip'] == 1);
$normalCourts = array_filter($courts, fn($c) => !($c['court_type'] === 'vip' || $c['is_vip'] == 1));

// ดึงเฉพาะการจองที่กำลังเล่นอยู่ตอนนี้
$stmt = $pdo->prepare("
    SELECT b.*, c.court_no, c.court_type, c.vip_room_name, c.is_vip
    FROM bookings b
    JOIN courts c ON b.court_id = c.id
    WHERE b.status = 'booked'
      AND b.start_datetime <= NOW()
      AND DATE_ADD(b.start_datetime, INTERVAL b.duration_hours HOUR) > NOW()
    ORDER BY c.court_type DESC, c.vip_room_name ASC, c.court_no
");
$stmt->execute();
$activeBookings = $stmt->fetchAll();

// map court_id => active booking
$activeByCourt = [];
foreach ($activeBookings as $b) {
    $activeByCourt[$b['court_id']] = $b;
}

// ดึงการจองถัดไปวันนี้ (ยังไม่ถึงเวลา) — per court เอาอันใกล้สุด
$nextStmt = $pdo->prepare("
    SELECT b.*, c.court_no, c.court_type, c.vip_room_name, c.is_vip
    FROM bookings b
    JOIN courts c ON b.court_id = c.id
    WHERE b.status = 'booked'
      AND b.start_datetime > NOW()
      AND DATE(b.start_datetime) = CURDATE()
    ORDER BY b.start_datetime ASC
");
$nextStmt->execute();
$upcomingAll = $nextStmt->fetchAll();

$nextByCourt = [];
foreach ($upcomingAll as $b) {
    if (!isset($nextByCourt[$b['court_id']])) {
        $nextByCourt[$b['court_id']] = $b;
    }
}

$now = new DateTime();
$thaiMonths = [1=>'มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
$thaiDays   = ['อาทิตย์','จันทร์','อังคาร','พุธ','พฤหัสบดี','ศุกร์','เสาร์'];
$thaiDate   = 'วัน'.$thaiDays[(int)$now->format('w')].' ที่ '.$now->format('d').' '.$thaiMonths[(int)$now->format('n')].' '.($now->format('Y')+543);

function getCourtDisplayName($court): string {
    if ($court['court_type'] === 'vip' || $court['is_vip'] == 1) return $court['vip_room_name'] ?? 'ห้อง VIP';
    return 'คอร์ต ' . $court['court_no'];
}
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="refresh" content="60">
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600&display=swap" rel="stylesheet">
  <title>สถานะคอร์ตปัจจุบัน - BARGAIN SPORT</title>
  <style>
    * { font-family: 'Prompt', sans-serif !important; }
    .court-card { background:white; border-radius:14px; border:1px solid #e5e7eb; overflow:hidden; }
    .court-active { border-color:#004A7C; }
    .court-active-vip { border-color:#005691; }
  </style>
</head>
<body style="background:#FAFAFA;" class="min-h-screen">

<!-- Nav -->
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

<div class="max-w-6xl mx-auto px-4 py-6">

  <!-- Header -->
  <div class="bg-white rounded-xl border border-gray-200 p-5 mb-5">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div>
        <h1 style="color:#005691;" class="text-xl font-bold">สถานะคอร์ตขณะนี้</h1>
        <p class="text-gray-400 text-sm mt-0.5"><?= $thaiDate ?></p>
      </div>
      <div class="flex items-center gap-3">
        <div class="flex items-center gap-1.5">
          <span class="w-2.5 h-2.5 rounded-full bg-green-400 inline-block animate-pulse"></span>
          <span class="text-sm font-semibold text-gray-700" id="liveClock"><?= $now->format('H:i') ?></span>
        </div>
        <span class="text-xs text-gray-400">รีเฟรชอัตโนมัติทุก 60 วิ</span>
      </div>
    </div>
  </div>

  <!-- Legend -->
  <div class="bg-white rounded-xl border border-gray-200 px-5 py-3 mb-5 flex gap-5 flex-wrap">
    <div class="flex items-center gap-2 text-sm text-gray-500">
      <div class="w-3 h-3 rounded-full bg-green-400"></div>ว่าง
    </div>
    <div class="flex items-center gap-2 text-sm text-gray-500">
      <div class="w-3 h-3 rounded-full" style="background:#004A7C;"></div>กำลังเล่น (ปกติ)
    </div>
    <div class="flex items-center gap-2 text-sm text-gray-500">
      <div class="w-3 h-3 rounded-full" style="background:#005691;"></div>กำลังเล่น (VIP)
    </div>
  </div>

  <!-- VIP Rooms -->
  <?php if (!empty($vipCourts)): ?>
  <div class="mb-6">
    <h2 class="text-sm font-semibold mb-3" style="color:#004A7C;">ห้อง VIP</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
      <?php foreach ($vipCourts as $c):
        $name   = getCourtDisplayName($c);
        $active = $activeByCourt[$c['id']] ?? null;
      ?>
      <div class="court-card <?= $active ? 'court-active-vip' : '' ?>">
        <!-- Card header -->
        <div class="px-4 py-3 flex items-center gap-2"
             style="background:<?= $active ? '#005691' : '#f8fafc' ?>;">
          <span class="w-7 h-7 rounded-lg flex items-center justify-center font-bold text-xs flex-shrink-0"
                style="background:<?= $active ? 'rgba(255,255,255,.2)' : '#E8F1F5' ?>;color:<?= $active ? '#fff' : '#005691' ?>;">V</span>
          <span class="font-semibold text-sm truncate" style="color:<?= $active ? '#fff' : '#005691' ?>;"><?= htmlspecialchars($name) ?></span>
          <span class="ml-auto text-xs font-medium px-2 py-0.5 rounded-full flex-shrink-0
            <?= $active ? 'bg-white/20 text-white' : 'bg-green-100 text-green-700' ?>">
            <?= $active ? 'กำลังเล่น' : 'ว่าง' ?>
          </span>
        </div>
        <!-- Card body -->
        <div class="px-4 py-3">
          <?php
            $next = $nextByCourt[$c['id']] ?? null;
            if ($active):
            $sd = new DateTime($active['start_datetime']);
            $ed = (clone $sd)->modify('+' . (int)$active['duration_hours'] . ' hour');
            $remainMin = (int)(($ed->getTimestamp() - $now->getTimestamp()) / 60);
          ?>
          <p class="font-semibold text-gray-800"><?= htmlspecialchars($active['customer_name']) ?></p>
          <p class="text-xs text-gray-400 mt-0.5"><?= $sd->format('H:i') ?> – <?= $ed->format('H:i') ?></p>
          <p class="text-xs font-medium mt-2" style="color:#005691;">เหลืออีก <?= $remainMin ?> นาที</p>
          <?php elseif ($next):
            $nsd = new DateTime($next['start_datetime']);
            $ned = (clone $nsd)->modify('+' . (int)$next['duration_hours'] . ' hour');
            $waitMin = (int)(($nsd->getTimestamp() - $now->getTimestamp()) / 60);
          ?>
          <p class="text-xs text-gray-400 mb-1">ว่างอยู่ · จองถัดไป</p>
          <p class="font-semibold text-gray-800 text-sm"><?= htmlspecialchars($next['customer_name']) ?></p>
          <p class="text-xs text-gray-400 mt-0.5"><?= $nsd->format('H:i') ?> – <?= $ned->format('H:i') ?></p>
          <p class="text-xs text-orange-400 font-medium mt-1">อีก <?= $waitMin ?> นาที</p>
          <?php else: ?>
          <p class="text-sm text-gray-400 py-1">ว่าง — ไม่มีการจองวันนี้</p>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Normal Courts -->
  <?php if (!empty($normalCourts)): ?>
  <div class="mb-6">
    <h2 class="text-sm font-semibold mb-3" style="color:#005691;">คอร์ตปกติ</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
      <?php foreach ($normalCourts as $c):
        $name   = getCourtDisplayName($c);
        $active = $activeByCourt[$c['id']] ?? null;
      ?>
      <div class="court-card <?= $active ? 'court-active' : '' ?>">
        <!-- Card header -->
        <div class="px-4 py-3 flex items-center gap-2"
             style="background:<?= $active ? '#004A7C' : '#f8fafc' ?>;">
          <span class="w-7 h-7 rounded-lg flex items-center justify-center font-bold text-sm flex-shrink-0"
                style="background:<?= $active ? 'rgba(255,255,255,.2)' : '#E8F1F5' ?>;color:<?= $active ? '#fff' : '#005691' ?>;"><?= $c['court_no'] ?></span>
          <span class="font-semibold text-sm truncate" style="color:<?= $active ? '#fff' : '#005691' ?>;"><?= htmlspecialchars($name) ?></span>
          <span class="ml-auto text-xs font-medium px-2 py-0.5 rounded-full flex-shrink-0
            <?= $active ? 'bg-white/20 text-white' : 'bg-green-100 text-green-700' ?>">
            <?= $active ? 'กำลังเล่น' : 'ว่าง' ?>
          </span>
        </div>
        <!-- Card body -->
        <div class="px-4 py-3">
          <?php
            $next = $nextByCourt[$c['id']] ?? null;
            if ($active):
            $sd = new DateTime($active['start_datetime']);
            $ed = (clone $sd)->modify('+' . (int)$active['duration_hours'] . ' hour');
            $remainMin = (int)(($ed->getTimestamp() - $now->getTimestamp()) / 60);
          ?>
          <p class="font-semibold text-gray-800"><?= htmlspecialchars($active['customer_name']) ?></p>
          <p class="text-xs text-gray-400 mt-0.5"><?= $sd->format('H:i') ?> – <?= $ed->format('H:i') ?></p>
          <p class="text-xs font-medium mt-2" style="color:#004A7C;">เหลืออีก <?= $remainMin ?> นาที</p>
          <?php elseif ($next):
            $nsd = new DateTime($next['start_datetime']);
            $ned = (clone $nsd)->modify('+' . (int)$next['duration_hours'] . ' hour');
            $waitMin = (int)(($nsd->getTimestamp() - $now->getTimestamp()) / 60);
          ?>
          <p class="text-xs text-gray-400 mb-1">ว่างอยู่ · จองถัดไป</p>
          <p class="font-semibold text-gray-800 text-sm"><?= htmlspecialchars($next['customer_name']) ?></p>
          <p class="text-xs text-gray-400 mt-0.5"><?= $nsd->format('H:i') ?> – <?= $ned->format('H:i') ?></p>
          <p class="text-xs text-orange-400 font-medium mt-1">อีก <?= $waitMin ?> นาที</p>
          <?php else: ?>
          <p class="text-sm text-gray-400 py-1">ว่าง — ไม่มีการจองวันนี้</p>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!$isLoggedIn): ?>
  <div class="mt-2 bg-white rounded-xl border border-gray-200 p-5 text-center">
    <p class="text-gray-500 text-sm mb-3">เข้าสู่ระบบเพื่อจัดการการจองและดูรายละเอียดเพิ่มเติม</p>
    <a href="/auth/login.php" style="background:#FF0000;"
       class="inline-block px-6 py-2 text-white text-sm font-medium rounded-lg hover:opacity-90 transition-opacity">
      เข้าสู่ระบบ
    </a>
  </div>
  <?php endif; ?>

</div>

<footer style="background:#005691;" class="mt-10 py-4 text-center text-sm text-blue-200">
  © <?= date('Y') ?> Boat Patthanapong &nbsp;|&nbsp; BARGAIN SPORT System
</footer>

<script>
// Live clock
function updateClock() {
  const now = new Date();
  const hh = String(now.getHours()).padStart(2,'0');
  const mm = String(now.getMinutes()).padStart(2,'0');
  const ss = String(now.getSeconds()).padStart(2,'0');
  document.getElementById('liveClock').textContent = hh + ':' + mm + ':' + ss;
}
updateClock();
setInterval(updateClock, 1000);
</script>
</body>
</html>
