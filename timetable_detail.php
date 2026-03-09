<?php
require_once __DIR__ . '/auth/guard.php';
require_once __DIR__ . '/config/db.php';

$date = $_GET['date'] ?? date('Y-m-d');

// Validate date
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');

$courts = $pdo->query('SELECT * FROM courts ORDER BY court_type DESC, vip_room_name ASC, court_no ASC')->fetchAll();

$startDay = $date . ' 00:00:00';
$endDay   = $date . ' 23:59:59';
$stmt = $pdo->prepare("
    SELECT b.*, c.court_no, c.court_type, c.vip_room_name, c.is_vip,
           p.name AS promotion_name, p.code AS promotion_code, p.discount_type AS promotion_discount_type,
           mbp.hours_total AS pkg_hours_total, mbp.hours_used AS pkg_hours_used,
           mbp.expiry_date AS pkg_expiry_date, mbp.payment_slip_path AS pkg_slip_path,
           bpt.name AS pkg_type_name
    FROM bookings b
    JOIN courts c ON b.court_id = c.id
    LEFT JOIN promotions p ON b.promotion_id = p.id
    LEFT JOIN member_badminton_packages mbp ON b.member_badminton_package_id = mbp.id
    LEFT JOIN badminton_package_types bpt ON mbp.badminton_package_type_id = bpt.id
    WHERE b.status = 'booked'
      AND b.start_datetime BETWEEN ? AND ?
    ORDER BY c.court_type DESC, c.vip_room_name ASC, c.court_no, b.start_datetime
");
$stmt->execute([$startDay, $endDay]);
$bookings = $stmt->fetchAll();

// ---- slot constants: 06:00–23:00, 34 half-hour slots ----
const SLOT_START = 12; // slot index for 06:00 in 48-slot system
const SLOT_COUNT = 34; // 06:00–22:30 (34 slots × 30min = 17h)

// Build full 48-slot grid
$grid        = [];
$gridDetails = [];
foreach ($courts as $c) {
    $grid[$c['id']]        = array_fill(0, 48, null);
    $gridDetails[$c['id']] = array_fill(0, 48, null);
}

$uniqueBookings = []; // id => booking row
foreach ($bookings as $b) {
    $s         = new DateTime($b['start_datetime']);
    $startSlot = (int)$s->format('G') * 2 + ((int)$s->format('i') >= 30 ? 1 : 0);
    $numSlots  = max(1, (int)$b['duration_hours'] * 2);
    for ($i = 0; $i < $numSlots; $i++) {
        $slot = $startSlot + $i;
        if ($slot >= 0 && $slot < 48) {
            $grid[$b['court_id']][$slot]        = $b['id'];
            $gridDetails[$b['court_id']][$slot] = $b;
        }
    }
    $uniqueBookings[$b['id']] = $b;
}

// ---- stats (business hours only: SLOT_START to SLOT_START+SLOT_COUNT-1) ----
$totalBusinessSlots = count($courts) * SLOT_COUNT;
$bookedBusinessSlots = 0;
foreach ($courts as $c) {
    for ($s = SLOT_START; $s < SLOT_START + SLOT_COUNT; $s++) {
        if ($grid[$c['id']][$s] !== null) $bookedBusinessSlots++;
    }
}
$freeBusinessSlots = $totalBusinessSlots - $bookedBusinessSlots;
$bookedHours = round($bookedBusinessSlots / 2, 1);
$freeHours   = round($freeBusinessSlots / 2, 1);
$occupancy   = $totalBusinessSlots > 0 ? round($bookedBusinessSlots / $totalBusinessSlots * 100, 1) : 0;
$bookingCount = count($uniqueBookings);

// ---- Thai date ----
$dateObj    = new DateTime($date);
$thaiMonths = [1=>'มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
$thaiDays   = ['อาทิตย์','จันทร์','อังคาร','พุธ','พฤหัสบดี','ศุกร์','เสาร์'];
$dayName    = $thaiDays[(int)$dateObj->format('w')];
$thaiDate   = "วัน{$dayName} ที่ ".$dateObj->format('d')." ".$thaiMonths[(int)$dateObj->format('n')]." ".($dateObj->format('Y')+543);
$isToday    = ($date === date('Y-m-d'));

function getCourtDisplayName(array $court): string {
    if ($court['court_type'] === 'vip' || $court['is_vip'] == 1)
        return $court['vip_room_name'] ?? 'ห้อง VIP';
    return 'คอร์ต ' . $court['court_no'];
}

// prev/next date
$prevDate = (new DateTime($date))->modify('-1 day')->format('Y-m-d');
$nextDate = (new DateTime($date))->modify('+1 day')->format('Y-m-d');

// Group bookings by court for card view
$bookingsByCourt = [];
foreach ($bookings as $b) {
    $bookingsByCourt[$b['court_id']][] = $b;
}
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
  <title>ตารางคอร์ต – <?= htmlspecialchars($thaiDate) ?></title>
  <style>
    /* ---- Timeline table ---- */
    .tl-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; position: relative; }
    .tl-table { border-collapse: collapse; table-layout: fixed; }
    .tl-table th, .tl-table td { height: 44px; padding: 0; border: 1px solid #e5e7eb; white-space: nowrap; }

    /* Sticky court name column */
    .tl-court-col { width: 130px; min-width: 130px; max-width: 130px; position: sticky; left: 0; z-index: 2; background: #fff; }
    .tl-court-col-head { width: 130px; min-width: 130px; position: sticky; left: 0; z-index: 3; background: #D32F2F; }

    /* Slot columns */
    .tl-slot { width: 48px; min-width: 48px; }

    /* Hour header — show every hour label, dim half-hour ticks */
    .tl-hour-head { font-size: 10px; text-align: center; color: #fff; vertical-align: middle; background: #D32F2F; user-select: none; }
    .tl-hour-head.half { background: #B71C1C; color: #8ab4cc; font-size: 9px; }

    /* Free slot */
    .tl-free { background: #f0f9ff; cursor: default; }
    .tl-free:hover { background: #e0f2fe; }

    /* Booked slot */
    .tl-booked { cursor: pointer; user-select: none; overflow: hidden; position: relative; }
    .tl-booked:hover { filter: brightness(0.92); }
    .tl-booked-inner {
      display: flex; flex-direction: column; justify-content: center;
      padding: 2px 6px; height: 100%; overflow: hidden;
    }
    .tl-booked-name { font-size: 11px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #fff; }
    .tl-booked-time { font-size: 9px; color: rgba(255,255,255,.75); }
    .tl-booked-slip  { position: absolute; top: 2px; right: 4px; font-size: 9px; opacity:.8; }

    /* VIP vs Normal colors */
    .tl-booked-normal { background: #B71C1C; }
    .tl-booked-vip    { background: #D32F2F; }

    /* Court name cell */
    .tl-court-cell {
      padding: 0 10px;
      font-size: 12px; font-weight: 600; color: #D32F2F;
      background: #fff;
      border-right: 2px solid #e5e7eb;
    }
    .tl-court-cell-vip { background: #f0f4ff; color: #B71C1C; }
    .tl-court-cell-head { font-size: 11px; color: rgba(255,255,255,.8); font-weight: 500; }

    /* Search highlight / dim */
    .search-highlight { outline: 3px solid #f59e0b !important; outline-offset: -2px; z-index: 5; position: relative; }
    .search-dim        { opacity: .2 !important; pointer-events: none; }
    .search-highlight-card { outline: 3px solid #f59e0b !important; border-radius: 8px; }
    .search-dim-card   { opacity: .2 !important; }

    /* Current time line */
    #timeLine {
      position: absolute; top: 0; bottom: 0; width: 2px;
      background: #ef4444; z-index: 10; pointer-events: none;
      transition: left .5s linear;
    }
    #timeLine::before {
      content: attr(data-time);
      position: absolute; top: -1px; left: 3px;
      background: #ef4444; color: #fff;
      font-size: 9px; font-weight: 700;
      padding: 1px 4px; border-radius: 3px;
      white-space: nowrap;
    }

    /* Quick-info tooltip (hover on timeline slots) */
    #bkTooltip {
      position: fixed; z-index: 200; pointer-events: none;
      background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;
      box-shadow: 0 8px 28px rgba(0,0,0,.14);
      padding: 12px 14px; min-width: 210px; max-width: 270px;
      opacity: 0; transition: opacity .1s ease;
    }
    #bkTooltip.tt-show { opacity: 1; }

    /* Modal */
    #bookingModal { backdrop-filter: blur(4px); }
    .slip-upload-area {
      border: 2px dashed #d1d5db; border-radius: 12px;
      padding: 20px; text-align: center; cursor: pointer;
      transition: border-color .2s, background .2s;
    }
    .slip-upload-area:hover, .slip-upload-area.drag-over { border-color: #D32F2F; background: #f0f9ff; }
    .slip-upload-area input[type=file] { display: none; }
  </style>
</head>
<body style="background:#FAFAFA;" class="min-h-screen">
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="max-w-full px-3 py-5">

  <!-- ===== Header Bar ===== -->
  <div class="bg-white rounded-xl border border-gray-200 p-4 mb-4">
    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-3">
      <div>
        <h1 style="color:#D32F2F;" class="text-xl font-bold leading-tight">ตารางคอร์ตแบดมินตัน</h1>
        <p class="text-gray-400 text-sm mt-0.5 flex items-center gap-1.5">
          <?= $thaiDate ?>
          <?php if ($isToday): ?>
          <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700 font-medium">วันนี้</span>
          <?php endif; ?>
        </p>
      </div>
      <div class="flex items-center gap-2 flex-wrap">
        <!-- Prev / Next Day -->
        <a href="?date=<?= $prevDate ?>" class="px-3 py-2 bg-gray-100 text-gray-600 text-sm rounded-lg hover:bg-gray-200 transition-colors">‹ ก่อนหน้า</a>
        <form method="get" class="flex gap-2">
          <input type="date" name="date" value="<?= htmlspecialchars($date) ?>"
                 onchange="this.form.submit()"
                 class="px-3 py-2 border border-gray-300 rounded-lg focus:border-[#D32F2F] outline-none text-sm">
        </form>
        <a href="?date=<?= $nextDate ?>" class="px-3 py-2 bg-gray-100 text-gray-600 text-sm rounded-lg hover:bg-gray-200 transition-colors">ถัดไป ›</a>
        <!-- View toggle -->
        <div class="flex rounded-lg overflow-hidden border border-gray-200 text-sm flex-shrink-0">
          <button id="btnTimeline" onclick="setView('timeline')"
                  class="px-3 py-2 transition-colors">ตารางเวลา</button>
          <button id="btnCards" onclick="setView('cards')"
                  class="px-3 py-2 transition-colors border-l border-gray-200">การ์ดคอร์ต</button>
        </div>
        <a href="/bookings/create.php" style="background:#D32F2F;"
           class="px-4 py-2 text-white text-sm rounded-lg hover:opacity-90 transition-opacity whitespace-nowrap">+ จองใหม่</a>
      </div>
    </div>
  </div>

  <!-- ===== Stats Bar ===== -->
  <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
    <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-3">
      <div style="background:#EDF4FA;" class="w-10 h-10 rounded-lg flex items-center justify-center text-[#D32F2F] text-lg font-bold flex-shrink-0"><?= $bookingCount ?></div>
      <div>
        <p class="text-xs text-gray-400">การจองวันนี้</p>
        <p class="text-sm font-semibold text-gray-700"><?= $bookingCount ?> รายการ</p>
      </div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-3">
      <div style="background:#B71C1C;" class="w-10 h-10 rounded-lg flex items-center justify-center text-white text-lg font-bold flex-shrink-0"><?= $bookedHours ?></div>
      <div>
        <p class="text-xs text-gray-400">ชั่วโมงที่จอง</p>
        <p class="text-sm font-semibold text-gray-700"><?= $bookedHours ?> ชม.</p>
      </div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-3">
      <div style="background:#10b981;" class="w-10 h-10 rounded-lg flex items-center justify-center text-white text-lg font-bold flex-shrink-0"><?= $freeHours ?></div>
      <div>
        <p class="text-xs text-gray-400">ชั่วโมงว่าง</p>
        <p class="text-sm font-semibold text-gray-700"><?= $freeHours ?> ชม.</p>
      </div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4">
      <p class="text-xs text-gray-400 mb-1">อัตราการใช้ (06–23น.)</p>
      <p style="color:#D32F2F;" class="text-2xl font-bold leading-tight"><?= $occupancy ?>%</p>
      <div class="w-full bg-gray-100 rounded-full h-1.5 mt-2">
        <div style="width:<?= $occupancy ?>%; background:#B71C1C;" class="h-1.5 rounded-full transition-all"></div>
      </div>
    </div>
  </div>

  <!-- ===== Legend ===== -->
  <div class="bg-white rounded-xl border border-gray-200 px-4 py-3 mb-4 flex flex-wrap gap-4 items-center justify-between">
    <div class="flex flex-wrap gap-4">
      <div class="flex items-center gap-1.5 text-xs text-gray-500">
        <div class="w-4 h-4 rounded" style="background:#f0f9ff;border:1px solid #e0f2fe;"></div>ว่าง
      </div>
      <div class="flex items-center gap-1.5 text-xs text-gray-500">
        <div class="w-4 h-4 rounded" style="background:#B71C1C;"></div>จองแล้ว (ปกติ)
      </div>
      <div class="flex items-center gap-1.5 text-xs text-gray-500">
        <div class="w-4 h-4 rounded" style="background:#D32F2F;"></div>จองแล้ว (VIP)
      </div>
      <div class="flex items-center gap-1.5 text-xs text-gray-500">
        <div class="w-2 h-4 rounded" style="background:#ef4444;"></div>เวลาปัจจุบัน
      </div>
    </div>
    <div class="flex items-center gap-3">
      <?php if ($isToday): ?>
      <div id="liveStatus" class="text-xs text-gray-400 font-medium flex items-center gap-1.5">
        <span class="w-2 h-2 rounded-full bg-green-400 inline-block animate-pulse"></span>
        <span id="liveStatusText">กำลังโหลด...</span>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ===== Search Bar ===== -->
  <div class="bg-white rounded-xl border border-gray-200 px-4 py-3 mb-4">
    <div class="flex flex-col sm:flex-row gap-2 items-stretch sm:items-center">
      <div class="relative flex-1">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <input type="text" id="bookingSearch"
          placeholder="ค้นหาชื่อลูกค้าหรือเบอร์โทร..."
          oninput="doSearch(this.value)"
          class="w-full pl-9 pr-4 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:border-[#D32F2F] focus:ring-1 focus:ring-[#D32F2F]/20">
      </div>
      <button onclick="clearSearch()" id="btnClearSearch"
        class="hidden px-4 py-2 text-sm border border-gray-300 rounded-lg text-gray-500 hover:bg-gray-50 whitespace-nowrap">
        ล้าง
      </button>
      <div id="searchResultBadge" class="hidden px-3 py-2 text-xs font-semibold rounded-lg whitespace-nowrap" style="background:#FEF3C7;color:#92400E;"></div>
    </div>
    <!-- Result list (shown when searching) -->
    <div id="searchResultList" class="hidden mt-3 divide-y divide-gray-100 max-h-48 overflow-y-auto rounded-lg border border-gray-100"></div>
  </div>

  <!-- ===== Timeline View ===== -->
  <div id="viewTimeline">
  <div class="bg-white rounded-xl border border-gray-200 overflow-hidden mb-4">
    <div class="tl-wrap" id="tlWrap">
      <!-- Time Line (absolute, positioned by JS) -->
      <?php if ($isToday): ?>
      <div id="timeLine" data-time="" style="display:none;"></div>
      <?php endif; ?>

      <table class="tl-table" id="tlTable">
        <thead>
          <tr>
            <th class="tl-court-col-head tl-court-cell-head tl-court-col px-3 py-2 text-left">คอร์ต</th>
            <?php for ($i = 0; $i < SLOT_COUNT; $i++):
              $slotAbsolute = SLOT_START + $i;
              $slotHour = intdiv($slotAbsolute, 2);
              $slotMin  = ($slotAbsolute % 2 === 0) ? '00' : '30';
              $isHalf   = ($slotAbsolute % 2 !== 0);
            ?>
            <th class="tl-slot tl-hour-head <?= $isHalf ? 'half' : '' ?>">
              <?= $isHalf ? '' : sprintf('%02d', $slotHour) ?>
            </th>
            <?php endfor; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($courts as $c):
            $isVip = ($c['court_type'] === 'vip' || $c['is_vip'] == 1);
            $courtName = getCourtDisplayName($c);
            $courtCls  = $isVip ? 'tl-court-cell tl-court-cell-vip' : 'tl-court-cell';
            $bookedCls = $isVip ? 'tl-booked-vip' : 'tl-booked-normal';
          ?>
          <tr>
            <!-- Sticky court name -->
            <td class="tl-court-col <?= $courtCls ?>" title="<?= htmlspecialchars($courtName) ?>">
              <div class="flex items-center gap-1.5 overflow-hidden">
                <?php if ($isVip): ?>
                <span style="background:#D32F2F;" class="flex-shrink-0 w-6 h-6 rounded flex items-center justify-center text-white text-xs font-bold">V</span>
                <?php else: ?>
                <span style="background:#FFEBEE;color:#D32F2F;" class="flex-shrink-0 w-6 h-6 rounded flex items-center justify-center font-bold text-xs"><?= $c['court_no'] ?></span>
                <?php endif; ?>
                <span class="overflow-hidden text-ellipsis whitespace-nowrap text-xs"><?= htmlspecialchars($courtName) ?></span>
              </div>
            </td>

            <?php
            // Render slots with colspan merging
            $si = 0;
            while ($si < SLOT_COUNT):
              $slotAbs = SLOT_START + $si;
              $bkId    = $grid[$c['id']][$slotAbs];
              $bk      = $gridDetails[$c['id']][$slotAbs];

              if ($bkId !== null && $bk !== null):
                // Calculate colspan: how many consecutive slots belong to this booking
                $colspan = 0;
                $tmpSi = $si;
                while ($tmpSi < SLOT_COUNT && $grid[$c['id']][SLOT_START + $tmpSi] === $bkId) {
                  $colspan++;
                  $tmpSi++;
                }
                $startDt = new DateTime($bk['start_datetime']);
                $endDt   = (clone $startDt)->modify('+' . (int)$bk['duration_hours'] . ' hour');
                $hasSlip = !empty($bk['payment_slip_path']);
                $bkJson  = htmlspecialchars(json_encode($bk, JSON_UNESCAPED_UNICODE), ENT_QUOTES);
                $cnJson  = htmlspecialchars(json_encode($courtName, JSON_UNESCAPED_UNICODE), ENT_QUOTES);
            ?>
            <td class="tl-booked <?= $bookedCls ?>"
                colspan="<?= $colspan ?>"
                data-bk-id="<?= $bk['id'] ?>"
                data-bk-name="<?= htmlspecialchars($bk['customer_name'], ENT_QUOTES) ?>"
                data-bk-phone="<?= htmlspecialchars($bk['customer_phone'] ?? '', ENT_QUOTES) ?>"
                data-bk-court="<?= htmlspecialchars($courtName, ENT_QUOTES) ?>"
                data-bk-badge="<?= $isVip ? 'V' : $c['court_no'] ?>"
                data-bk-start="<?= $startDt->format('H:i') ?>"
                data-bk-end="<?= $endDt->format('H:i') ?>"
                data-bk-start-ts="<?= $startDt->getTimestamp() ?>"
                data-bk-end-ts="<?= $endDt->getTimestamp() ?>"
                data-bk-vip="<?= $isVip ? '1' : '0' ?>"
                onclick="showModal(<?= $bkJson ?>, <?= $cnJson ?>, <?= $isVip ? 'true' : 'false' ?>)"
                title="<?= htmlspecialchars($bk['customer_name']) ?> <?= $startDt->format('H:i') ?>–<?= $endDt->format('H:i') ?>">
              <div class="tl-booked-inner">
                <div class="tl-booked-name"><?= htmlspecialchars($bk['customer_name']) ?></div>
                <?php if ($colspan >= 3): ?>
                <div class="tl-booked-time"><?= $startDt->format('H:i') ?>–<?= $endDt->format('H:i') ?></div>
                <?php endif; ?>
              </div>
              <?php if ($hasSlip): ?>
              <span class="tl-booked-slip" title="มีสลิปแล้ว">📎</span>
              <?php endif; ?>
            </td>
            <?php
                $si += $colspan;
              else:
            ?>
            <td class="tl-free tl-slot"></td>
            <?php
                $si++;
              endif;
            endwhile;
            ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  </div><!-- /viewTimeline -->

  <!-- ===== Card View ===== -->
  <?php
  $vipCourts    = array_filter($courts, fn($c) => $c['court_type'] === 'vip' || $c['is_vip'] == 1);
  $normalCourts = array_filter($courts, fn($c) => !($c['court_type'] === 'vip' || $c['is_vip'] == 1));
  ?>
  <div id="viewCards" class="hidden space-y-5">

    <?php if (!empty($vipCourts)): ?>
    <!-- VIP Rooms section -->
    <div>
      <h2 class="text-sm font-bold mb-3 flex items-center gap-2" style="color:#B71C1C;">
        <span style="background:#B71C1C;" class="w-5 h-5 rounded text-white text-xs flex items-center justify-center font-bold flex-shrink-0">V</span>
        ห้อง VIP
      </h2>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
        <?php foreach ($vipCourts as $c):
          $courtName = getCourtDisplayName($c);
          $cBks = $bookingsByCourt[$c['id']] ?? [];
        ?>
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
          <div style="background:#B71C1C;" class="px-4 py-3 flex items-center gap-2">
            <span class="w-7 h-7 rounded-lg bg-white/20 flex items-center justify-center text-white font-bold text-xs flex-shrink-0">V</span>
            <span class="text-white font-semibold text-sm truncate"><?= htmlspecialchars($courtName) ?></span>
          </div>
          <div class="p-3 space-y-2 min-h-[60px]">
            <?php if (empty($cBks)): ?>
            <p class="text-green-600 text-xs font-medium text-center py-3">ว่าง</p>
            <?php else: foreach ($cBks as $bk):
              $startDt = new DateTime($bk['start_datetime']);
              $endDt   = (clone $startDt)->modify('+' . (int)$bk['duration_hours'] . ' hour');
              $hasSlip = !empty($bk['payment_slip_path']);
              $bkJson  = htmlspecialchars(json_encode($bk, JSON_UNESCAPED_UNICODE), ENT_QUOTES);
              $cnJson  = htmlspecialchars(json_encode($courtName, JSON_UNESCAPED_UNICODE), ENT_QUOTES);
            ?>
            <div class="rounded-lg px-3 py-2 cursor-pointer hover:opacity-80 transition-opacity bk-card-item"
                 style="background:#EDF4FA;"
                 data-bk-id="<?= $bk['id'] ?>"
                 data-bk-name="<?= htmlspecialchars(strtolower($bk['customer_name']), ENT_QUOTES) ?>"
                 data-bk-phone="<?= htmlspecialchars(strtolower($bk['customer_phone'] ?? ''), ENT_QUOTES) ?>"
                 data-bk-start-ts="<?= $startDt->getTimestamp() ?>"
                 data-bk-end-ts="<?= $endDt->getTimestamp() ?>"
                 onclick="showModal(<?= $bkJson ?>, <?= $cnJson ?>, true)">
              <div class="flex items-center justify-between mb-0.5">
                <span class="text-xs font-bold" style="color:#B71C1C;"><?= $startDt->format('H:i') ?>–<?= $endDt->format('H:i') ?></span>
                <?php if ($hasSlip): ?><span class="text-xs font-medium" style="color:#D32F2F;" title="มีสลิปแล้ว">สลิป</span><?php endif; ?>
              </div>
              <p class="text-sm font-semibold text-gray-800 leading-tight truncate"><?= htmlspecialchars($bk['customer_name']) ?></p>
              <p class="text-xs text-gray-400"><?= htmlspecialchars($bk['customer_phone'] ?? '') ?> &middot; <?= $bk['duration_hours'] ?> ชม.</p>
            </div>
            <?php endforeach; endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($normalCourts)): ?>
    <!-- Normal Courts section -->
    <div>
      <h2 class="text-sm font-bold mb-3 flex items-center gap-2" style="color:#D32F2F;">
        <span style="background:#D32F2F;" class="w-5 h-5 rounded text-white text-xs flex items-center justify-center font-bold flex-shrink-0">B</span>
        คอร์ตปกติ
      </h2>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
        <?php foreach ($normalCourts as $c):
          $courtName = getCourtDisplayName($c);
          $cBks = $bookingsByCourt[$c['id']] ?? [];
        ?>
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
          <div style="background:#D32F2F;" class="px-4 py-3 flex items-center gap-2">
            <span class="w-7 h-7 rounded-lg bg-white/20 flex items-center justify-center text-white font-bold text-sm flex-shrink-0"><?= $c['court_no'] ?></span>
            <span class="text-white font-semibold text-sm truncate"><?= htmlspecialchars($courtName) ?></span>
          </div>
          <div class="p-3 space-y-2 min-h-[60px]">
            <?php if (empty($cBks)): ?>
            <p class="text-green-600 text-xs font-medium text-center py-3">ว่าง</p>
            <?php else: foreach ($cBks as $bk):
              $startDt = new DateTime($bk['start_datetime']);
              $endDt   = (clone $startDt)->modify('+' . (int)$bk['duration_hours'] . ' hour');
              $hasSlip = !empty($bk['payment_slip_path']);
              $bkJson  = htmlspecialchars(json_encode($bk, JSON_UNESCAPED_UNICODE), ENT_QUOTES);
              $cnJson  = htmlspecialchars(json_encode($courtName, JSON_UNESCAPED_UNICODE), ENT_QUOTES);
            ?>
            <div class="rounded-lg px-3 py-2 cursor-pointer hover:opacity-80 transition-opacity bk-card-item"
                 style="background:#EDF4FA;"
                 data-bk-id="<?= $bk['id'] ?>"
                 data-bk-name="<?= htmlspecialchars(strtolower($bk['customer_name']), ENT_QUOTES) ?>"
                 data-bk-phone="<?= htmlspecialchars(strtolower($bk['customer_phone'] ?? ''), ENT_QUOTES) ?>"
                 data-bk-start-ts="<?= $startDt->getTimestamp() ?>"
                 data-bk-end-ts="<?= $endDt->getTimestamp() ?>"
                 onclick="showModal(<?= $bkJson ?>, <?= $cnJson ?>, false)">
              <div class="flex items-center justify-between mb-0.5">
                <span class="text-xs font-bold" style="color:#D32F2F;"><?= $startDt->format('H:i') ?>–<?= $endDt->format('H:i') ?></span>
                <?php if ($hasSlip): ?><span class="text-xs font-medium" style="color:#D32F2F;" title="มีสลิปแล้ว">สลิป</span><?php endif; ?>
              </div>
              <p class="text-sm font-semibold text-gray-800 leading-tight truncate"><?= htmlspecialchars($bk['customer_name']) ?></p>
              <p class="text-xs text-gray-400"><?= htmlspecialchars($bk['customer_phone'] ?? '') ?> &middot; <?= $bk['duration_hours'] ?> ชม.</p>
            </div>
            <?php endforeach; endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  </div><!-- /viewCards -->

</div><!-- /container -->

<!-- ===== Booking Detail Modal ===== -->
<div id="bookingModal" class="hidden fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4"
     onclick="if(event.target===this)closeModal()">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[90vh] overflow-y-auto">

    <!-- Modal header -->
    <div style="background:#D32F2F;" class="rounded-t-2xl px-5 py-4 flex items-center justify-between flex-shrink-0">
      <div class="flex items-center gap-3">
        <div id="modalCourtBadge" class="w-8 h-8 rounded-lg flex items-center justify-center text-white font-bold text-sm">V</div>
        <div>
          <p class="text-white font-semibold text-sm" id="modalCourtName">–</p>
          <p class="text-blue-200 text-xs" id="modalTimeRange">–</p>
        </div>
      </div>
      <button onclick="closeModal()" class="text-white/70 hover:text-white text-2xl leading-none">&times;</button>
    </div>

    <!-- Body -->
    <div class="p-5 space-y-4">

      <!-- Customer info -->
      <div class="grid grid-cols-2 gap-3">
        <div class="bg-gray-50 rounded-xl p-3">
          <p class="text-xs text-gray-400 mb-0.5">ผู้จอง</p>
          <p class="font-semibold text-gray-800 text-sm" id="modalCustomer">–</p>
        </div>
        <div class="bg-gray-50 rounded-xl p-3">
          <p class="text-xs text-gray-400 mb-0.5">เบอร์โทร</p>
          <p class="font-semibold text-gray-800 text-sm" id="modalPhone">–</p>
        </div>
        <div class="bg-gray-50 rounded-xl p-3 col-span-2">
          <p class="text-xs text-gray-400 mb-0.5">วันที่ &amp; เวลา</p>
          <p class="font-medium text-gray-800 text-sm" id="modalDate">–</p>
          <p class="text-xs text-[#D32F2F] font-semibold mt-0.5" id="modalTimeSlot">–</p>
        </div>
        <div class="bg-gray-50 rounded-xl p-3">
          <p class="text-xs text-gray-400 mb-0.5">ระยะเวลา</p>
          <p class="font-medium text-gray-800 text-sm" id="modalDuration">–</p>
        </div>
      </div>

      <!-- Price row -->
      <div style="background:#EDF4FA;" class="rounded-xl p-4 flex items-center justify-between">
        <div>
          <p class="text-xs text-gray-500">฿/ชม. × ชั่วโมง</p>
          <p class="text-xs text-gray-400" id="modalPriceBreakdown">–</p>
        </div>
        <div class="text-right">
          <p class="text-xs text-gray-400 mb-0.5">ยอดชำระ</p>
          <p style="color:#B71C1C;" class="text-2xl font-bold" id="modalTotal">–</p>
        </div>
      </div>
      <div id="modalDiscountRow" class="hidden text-xs text-green-600 -mt-2 text-right"></div>

      <!-- Promotion & Package section -->
      <div id="modalPromoPackage" class="hidden space-y-2">
        <!-- Promotion info -->
        <div id="modalPromoSection" class="hidden rounded-xl p-3 border" style="background:#f0fdf4;border-color:#bbf7d0;">
          <p class="text-xs font-semibold text-green-700 mb-1.5 flex items-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
            โปรโมชั่นที่ใช้
          </p>
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-bold text-green-800" id="modalPromoName">–</p>
              <p class="text-xs text-green-600" id="modalPromoCode">–</p>
            </div>
            <div class="text-right">
              <p class="text-xs text-green-600">ส่วนลด</p>
              <p class="text-base font-bold text-green-700" id="modalPromoDiscount">–</p>
            </div>
          </div>
        </div>

        <!-- Package info -->
        <div id="modalPkgSection" class="hidden rounded-xl p-3 border" style="background:#eff6ff;border-color:#bfdbfe;">
          <p class="text-xs font-semibold mb-1.5 flex items-center gap-1" style="color:#1d4ed8;">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
            แพ็กเกจคอร์ตแบดมินตัน
          </p>
          <p class="text-sm font-bold mb-2" style="color:#1e40af;" id="modalPkgTypeName">–</p>
          <div class="flex items-center gap-3">
            <div class="flex-1">
              <div class="flex justify-between text-xs mb-1">
                <span class="text-gray-500">ใช้ไปในจองนี้</span>
                <span class="font-semibold" style="color:#1d4ed8;" id="modalPkgUsedThis">–</span>
              </div>
              <div class="flex justify-between text-xs mb-1">
                <span class="text-gray-500">ใช้ไปทั้งหมด</span>
                <span class="font-semibold text-gray-700" id="modalPkgUsedTotal">–</span>
              </div>
              <div class="flex justify-between text-xs mb-2">
                <span class="text-gray-500">คงเหลือ</span>
                <span class="font-bold" id="modalPkgRemaining">–</span>
              </div>
              <!-- Progress bar -->
              <div class="w-full h-2 bg-blue-100 rounded-full overflow-hidden">
                <div id="modalPkgBar" class="h-full rounded-full bg-blue-500 transition-all" style="width:0%"></div>
              </div>
            </div>
          </div>
          <div class="flex items-center justify-between mt-2 pt-2 border-t border-blue-100">
            <span class="text-xs text-gray-400">วันหมดอายุ: <span id="modalPkgExpiry" class="text-gray-600">–</span></span>
            <span id="modalPkgSlipBadge" class="hidden text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 font-medium">📎 มีสลิปแพ็กเกจ</span>
          </div>
        </div>
      </div>

      <!-- Payment slip section -->
      <div id="slipSection">
        <!-- Shown when slip exists -->
        <div id="slipExist" class="hidden">
          <p class="text-xs text-gray-500 mb-2 font-medium">สลิปการชำระเงิน</p>
          <div class="relative group">
            <img id="slipPreviewImg" src="" alt="slip"
                 class="w-full rounded-xl border border-gray-200 max-h-48 object-contain cursor-pointer"
                 onclick="openFullSlip()">
            <button onclick="triggerReupload()"
                    class="absolute top-2 right-2 bg-white/90 text-gray-600 text-xs px-2 py-1 rounded-lg shadow hover:bg-white border border-gray-200 opacity-0 group-hover:opacity-100 transition-opacity">
              เปลี่ยนสลิป
            </button>
          </div>
        </div>

        <!-- Shown when no slip -->
        <div id="slipUpload" class="hidden">
          <p class="text-xs text-orange-500 mb-2 font-medium flex items-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            ยังไม่มีสลิป — กรุณาแนบสลิป
          </p>
          <div class="slip-upload-area" id="uploadArea" onclick="document.getElementById('slipFileInput').click()"
               ondragover="event.preventDefault();this.classList.add('drag-over')"
               ondragleave="this.classList.remove('drag-over')"
               ondrop="handleDrop(event)">
            <svg class="w-8 h-8 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            <p class="text-sm text-gray-500">คลิกหรือลากไฟล์มาวางที่นี่</p>
            <p class="text-xs text-gray-400 mt-1">JPG, PNG, WebP — สูงสุด 10MB</p>
            <input type="file" id="slipFileInput" accept="image/jpeg,image/png,image/webp" onchange="uploadSlip(this.files[0])">
          </div>
          <div id="uploadProgress" class="hidden mt-2 text-xs text-gray-500 flex items-center gap-2">
            <svg class="w-4 h-4 animate-spin text-[#D32F2F]" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path></svg>
            กำลังอัปโหลด...
          </div>
          <div id="uploadError" class="hidden mt-2 text-xs text-red-500"></div>
        </div>

        <!-- Re-upload input (hidden, triggered by change-slip btn) -->
        <input type="file" id="reuploadInput" accept="image/jpeg,image/png,image/webp" class="hidden" onchange="uploadSlip(this.files[0])">
      </div>

    </div>

    <!-- Footer buttons -->
    <div class="px-5 pb-5 flex gap-2">
      <a href="#" id="modalEditLink" style="background:#B71C1C;"
         class="flex-1 px-4 py-2.5 text-white text-sm rounded-xl text-center hover:opacity-90 transition-opacity font-medium">
        แก้ไข / เลื่อน
      </a>
      <a href="#" id="modalCancelLink"
         onclick="return confirmCancel(event)"
         class="px-4 py-2.5 bg-red-50 text-red-500 border border-red-200 text-sm rounded-xl text-center hover:bg-red-100 transition-colors font-medium">
        ยกเลิกจอง
      </a>
      <button onclick="closeModal()" class="px-4 py-2.5 bg-gray-100 text-gray-600 text-sm rounded-xl hover:bg-gray-200 transition-colors">
        ปิด
      </button>
    </div>
  </div>
</div>

<!-- Full-size slip viewer -->
<div id="fullSlipModal" class="hidden fixed inset-0 z-[60] bg-black/80 flex items-center justify-center p-4"
     onclick="document.getElementById('fullSlipModal').classList.add('hidden')">
  <img id="fullSlipImg" src="" alt="slip" class="max-w-full max-h-full rounded-xl">
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<!-- Quick-info tooltip -->
<div id="bkTooltip">
  <div class="flex items-center gap-2 mb-2">
    <div id="ttBadge" class="w-7 h-7 rounded-lg flex items-center justify-center text-white text-xs font-bold flex-shrink-0"></div>
    <div class="min-w-0">
      <p id="ttCourt" class="text-xs font-semibold text-gray-700 truncate"></p>
      <p id="ttTime" class="text-xs text-gray-400"></p>
    </div>
  </div>
  <p id="ttName" class="text-sm font-bold text-gray-900 mb-2 truncate"></p>
  <span id="ttStatus" class="inline-block text-xs px-2.5 py-1 rounded-full font-medium"></span>
</div>

<script>
// ===================== Booking Search =====================
function doSearch(q) {
  const term = q.trim().toLowerCase();
  const input     = document.getElementById('bookingSearch');
  const badge     = document.getElementById('searchResultBadge');
  const resultList = document.getElementById('searchResultList');
  const clearBtn  = document.getElementById('btnClearSearch');

  // Timeline cells
  const tlCells = document.querySelectorAll('.tl-booked[data-bk-name]');
  // Card items
  const cardItems = document.querySelectorAll('.bk-card-item');

  if (!term) {
    tlCells.forEach(el => el.classList.remove('search-highlight', 'search-dim'));
    cardItems.forEach(el => el.classList.remove('search-highlight-card', 'search-dim-card'));
    badge.classList.add('hidden');
    resultList.classList.add('hidden');
    clearBtn.classList.add('hidden');
    return;
  }

  clearBtn.classList.remove('hidden');

  // Match timeline cells – collect unique booking IDs that match
  const matchedIds = new Set();
  tlCells.forEach(el => {
    const name  = (el.dataset.bkName  || '').toLowerCase();
    const phone = (el.dataset.bkPhone || '').toLowerCase();
    const match = name.includes(term) || phone.includes(term);
    el.classList.toggle('search-highlight', match);
    el.classList.toggle('search-dim',       !match);
    if (match && el.dataset.bkId) matchedIds.add(el.dataset.bkId);
  });

  // Match card items
  cardItems.forEach(el => {
    const name  = (el.dataset.bkName  || '').toLowerCase();
    const phone = (el.dataset.bkPhone || '').toLowerCase();
    const match = name.includes(term) || phone.includes(term);
    el.classList.toggle('search-highlight-card', match);
    el.classList.toggle('search-dim-card',       !match);
    if (match && el.dataset.bkId) matchedIds.add(el.dataset.bkId);
  });

  const count = matchedIds.size;

  if (count === 0) {
    badge.textContent = 'ไม่พบข้อมูล';
    badge.style.background = '#FEE2E2';
    badge.style.color = '#991B1B';
  } else {
    badge.textContent = `พบ ${count} รายการ`;
    badge.style.background = '#FEF3C7';
    badge.style.color = '#92400E';
  }
  badge.classList.remove('hidden');

  // Build result list from timeline cells (unique bookings)
  const seen = new Set();
  const rows = [];
  tlCells.forEach(el => {
    const id = el.dataset.bkId;
    if (!id || seen.has(id)) return;
    const name  = (el.dataset.bkName  || '').toLowerCase();
    const phone = (el.dataset.bkPhone || '').toLowerCase();
    if (!(name.includes(term) || phone.includes(term))) return;
    seen.add(id);
    rows.push({
      name:  el.dataset.bkName,
      phone: el.dataset.bkPhone,
      court: el.dataset.bkCourt,
      start: el.dataset.bkStart,
      end:   el.dataset.bkEnd,
      vip:   el.dataset.bkVip === '1',
    });
  });

  if (rows.length > 0) {
    resultList.innerHTML = rows.map(r => `
      <div class="flex items-center gap-3 px-3 py-2 hover:bg-gray-50">
        <div class="w-7 h-7 rounded-lg flex items-center justify-center text-white text-xs font-bold flex-shrink-0"
             style="background:${r.vip ? '#B71C1C' : '#D32F2F'};">${r.vip ? 'V' : '●'}</div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-semibold text-gray-800 truncate">${escHtml(r.name)}</p>
          <p class="text-xs text-gray-400">${escHtml(r.phone)} · ${escHtml(r.court)} · ${r.start}–${r.end}</p>
        </div>
      </div>`).join('');
    resultList.classList.remove('hidden');
  } else {
    resultList.classList.add('hidden');
  }
}

function clearSearch() {
  document.getElementById('bookingSearch').value = '';
  doSearch('');
}

function escHtml(s) {
  return (s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ===================== Constants =====================
const SLOT_W      = 48;   // px per half-hour slot (must match CSS)
const COURT_COL_W = 130;  // px for sticky court column
const SLOT_START_H = 6;   // 06:00
const SLOT_TOTAL  = 34;   // 06:00–22:30

// ===================== Modal state =====================
let _currentBookingId = null;
let _currentSlipPath  = null;

// ===================== showModal =====================
function showModal(booking, courtName, isVip) {
  _currentBookingId = booking.id;
  _currentSlipPath  = booking.payment_slip_path || null;

  // Header badge
  const badge = document.getElementById('modalCourtBadge');
  badge.textContent = isVip ? 'V' : (booking.court_no || '#');
  badge.style.background = isVip ? '#B71C1C' : '#D32F2F';

  document.getElementById('modalCourtName').textContent = courtName;

  // Time
  const startDate  = new Date(booking.start_datetime);
  const endDate    = new Date(startDate.getTime() + booking.duration_hours * 3600000);
  const fmt = d => d.toLocaleTimeString('th-TH', {hour:'2-digit', minute:'2-digit'});
  document.getElementById('modalTimeRange').textContent = fmt(startDate) + ' – ' + fmt(endDate);

  // Customer
  document.getElementById('modalCustomer').textContent  = booking.customer_name || '–';
  document.getElementById('modalPhone').textContent     = booking.customer_phone || '–';

  // Date + Time slot
  const thm = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
  document.getElementById('modalDate').textContent =
    startDate.getDate() + ' ' + thm[startDate.getMonth()] + ' ' + (startDate.getFullYear()+543);
  const fmtT = d => String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0');
  document.getElementById('modalTimeSlot').textContent = fmtT(startDate) + '–' + fmtT(endDate);
  document.getElementById('modalDuration').textContent = booking.duration_hours + ' ชั่วโมง';

  // Price
  const pph = parseFloat(booking.price_per_hour || 0);
  const hrs = parseInt(booking.duration_hours || 0);
  const disc = parseFloat(booking.discount_amount || 0);
  const total = parseFloat(booking.total_amount || 0);
  document.getElementById('modalPriceBreakdown').textContent =
    '฿' + pph.toLocaleString() + ' × ' + hrs + ' ชม. = ฿' + (pph*hrs).toLocaleString();
  document.getElementById('modalTotal').textContent = '฿' + total.toLocaleString();

  const discRow = document.getElementById('modalDiscountRow');
  if (disc > 0) {
    discRow.classList.remove('hidden');
    discRow.textContent = '- ส่วนลด ฿' + disc.toLocaleString() + (booking.promotion_discount_percent ? ' (โปร ' + booking.promotion_discount_percent + '%)' : '');
  } else {
    discRow.classList.add('hidden');
  }

  // Promotion & Package section
  const promoPackageDiv = document.getElementById('modalPromoPackage');
  const promoSection = document.getElementById('modalPromoSection');
  const pkgSection   = document.getElementById('modalPkgSection');
  let showPromoPackage = false;

  // Promotion
  if (booking.promotion_id && booking.promotion_name) {
    showPromoPackage = true;
    promoSection.classList.remove('hidden');
    document.getElementById('modalPromoName').textContent = booking.promotion_name;
    document.getElementById('modalPromoCode').textContent = 'รหัส: ' + (booking.promotion_code || '–');
    const discType = booking.promotion_discount_type === 'fixed' ? '฿' : '%';
    const discVal  = parseFloat(booking.promotion_discount_percent || 0);
    document.getElementById('modalPromoDiscount').textContent =
      booking.promotion_discount_type === 'fixed'
        ? '฿' + discVal.toLocaleString()
        : discVal + '%';
  } else {
    promoSection.classList.add('hidden');
  }

  // Package
  if (booking.member_badminton_package_id && booking.pkg_hours_total != null) {
    showPromoPackage = true;
    pkgSection.classList.remove('hidden');
    const pkgTotal   = parseInt(booking.pkg_hours_total || 0);
    const pkgUsed    = parseInt(booking.pkg_hours_used  || 0);
    const pkgRemain  = Math.max(0, pkgTotal - pkgUsed);
    const usedThis   = parseInt(booking.used_package_hours || 0);
    const usedPct    = pkgTotal > 0 ? Math.min(100, Math.round(pkgUsed / pkgTotal * 100)) : 0;

    document.getElementById('modalPkgTypeName').textContent   = booking.pkg_type_name || '–';
    document.getElementById('modalPkgUsedThis').textContent   = usedThis + ' ชม.';
    document.getElementById('modalPkgUsedTotal').textContent  = pkgUsed + ' / ' + pkgTotal + ' ชม.';
    document.getElementById('modalPkgRemaining').textContent  = pkgRemain + ' ชม.';
    document.getElementById('modalPkgRemaining').className    = 'font-bold ' + (pkgRemain <= 2 ? 'text-red-600' : 'text-green-600');
    document.getElementById('modalPkgBar').style.width        = usedPct + '%';
    document.getElementById('modalPkgBar').className          = 'h-full rounded-full transition-all ' + (pkgRemain > pkgTotal * 0.3 ? 'bg-blue-500' : 'bg-amber-400');

    const expiry = booking.pkg_expiry_date;
    document.getElementById('modalPkgExpiry').textContent = expiry ? expiry : 'ไม่จำกัด';

    const slipBadge = document.getElementById('modalPkgSlipBadge');
    if (booking.pkg_slip_path) {
      slipBadge.classList.remove('hidden');
    } else {
      slipBadge.classList.add('hidden');
    }
  } else {
    pkgSection.classList.add('hidden');
  }

  promoPackageDiv.classList.toggle('hidden', !showPromoPackage);

  // Links
  document.getElementById('modalEditLink').href   = '/bookings/update.php?id=' + booking.id;
  document.getElementById('modalCancelLink').href = '/bookings/cancel.php?id='  + booking.id;

  // Slip section
  renderSlipSection(_currentSlipPath);

  document.getElementById('bookingModal').classList.remove('hidden');
}

function renderSlipSection(slipPath) {
  const existDiv  = document.getElementById('slipExist');
  const uploadDiv = document.getElementById('slipUpload');
  document.getElementById('uploadError').classList.add('hidden');
  document.getElementById('uploadProgress').classList.add('hidden');
  document.getElementById('slipFileInput').value = '';

  if (slipPath) {
    existDiv.classList.remove('hidden');
    uploadDiv.classList.add('hidden');
    document.getElementById('slipPreviewImg').src = '/' + slipPath + '?t=' + Date.now();
  } else {
    existDiv.classList.add('hidden');
    uploadDiv.classList.remove('hidden');
  }
}

function closeModal() {
  document.getElementById('bookingModal').classList.add('hidden');
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeModal(); document.getElementById('fullSlipModal').classList.add('hidden'); } });

// ===================== Cancel Booking =====================
function confirmCancel(e) {
  e.preventDefault();
  const href = e.currentTarget ? e.currentTarget.href : (e.target ? e.target.href : null);
  Swal.fire({
    title: 'ยืนยันยกเลิกการจอง?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'ยืนยัน',
    cancelButtonText: 'ยกเลิก',
    confirmButtonColor: '#dc2626',
    cancelButtonColor: '#6b7280',
  }).then(result => {
    if (result.isConfirmed && href) window.location.href = href;
  });
  return false;
}

// ===================== Slip Upload =====================
function triggerReupload() { document.getElementById('reuploadInput').click(); }
function openFullSlip() {
  const img = document.getElementById('slipPreviewImg').src;
  document.getElementById('fullSlipImg').src = img;
  document.getElementById('fullSlipModal').classList.remove('hidden');
}

function handleDrop(e) {
  e.preventDefault();
  document.getElementById('uploadArea').classList.remove('drag-over');
  const file = e.dataTransfer.files[0];
  if (file) uploadSlip(file);
}

function uploadSlip(file) {
  if (!file || !_currentBookingId) return;

  const allowed = ['image/jpeg','image/png','image/webp'];
  if (!allowed.includes(file.type)) {
    showUploadError('รองรับเฉพาะ JPG, PNG, WebP เท่านั้น');
    return;
  }
  if (file.size > 10 * 1024 * 1024) {
    showUploadError('ไฟล์ใหญ่เกินไป (สูงสุด 10MB)');
    return;
  }

  document.getElementById('uploadProgress').classList.remove('hidden');
  document.getElementById('uploadError').classList.add('hidden');

  const fd = new FormData();
  fd.append('booking_id', _currentBookingId);
  fd.append('slip_file', file);

  fetch('/bookings/upload_slip_ajax.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      document.getElementById('uploadProgress').classList.add('hidden');
      if (data.success) {
        _currentSlipPath = data.path;
        renderSlipSection(_currentSlipPath);
        // Update 📎 icon in table cell (if same booking is visible)
        refreshTableSlipIcon(_currentBookingId);
      } else {
        showUploadError(data.message || 'เกิดข้อผิดพลาด');
      }
    })
    .catch(() => {
      document.getElementById('uploadProgress').classList.add('hidden');
      showUploadError('ไม่สามารถเชื่อมต่อได้');
    });
}

function showUploadError(msg) {
  const el = document.getElementById('uploadError');
  el.textContent = msg;
  el.classList.remove('hidden');
}

function refreshTableSlipIcon(bookingId) {
  // Visually add 📎 to any booking cell that matches this booking_id
  document.querySelectorAll('[data-bk-id="' + bookingId + '"]').forEach(cell => {
    let span = cell.querySelector('.tl-booked-slip');
    if (!span) {
      span = document.createElement('span');
      span.className = 'tl-booked-slip';
      span.title = 'มีสลิปแล้ว';
      cell.appendChild(span);
    }
    span.textContent = '📎';
  });
}

// ===================== Real-time current time line =====================
<?php if ($isToday): ?>
const timeLine = document.getElementById('timeLine');
const tlWrap   = document.getElementById('tlWrap');

function updateTimeLine() {
  const now   = new Date();
  const mins  = (now.getHours() - SLOT_START_H) * 60 + now.getMinutes();
  const maxM  = SLOT_TOTAL * 30; // 17h × 60 = 1020

  // Update live status
  const statusEl = document.getElementById('liveStatusText');
  const hh = String(now.getHours()).padStart(2,'0');
  const mm = String(now.getMinutes()).padStart(2,'0');

  if (mins < 0 || mins > maxM) {
    timeLine.style.display = 'none';
    if (statusEl) statusEl.textContent = 'นอกเวลาเปิด (06:00–23:00)';
    return;
  }

  const leftPx = COURT_COL_W + (mins / 30) * SLOT_W;
  timeLine.style.left  = leftPx + 'px';
  timeLine.style.display = 'block';
  timeLine.dataset.time = hh + ':' + mm;

  // Check if any court has booking at this moment
  const currentSlotAbs = Math.floor(mins / 30) + 12; // +SLOT_START(12)
  const nowTs = now.getTime();
  let activeBookings = 0;
  <?php foreach ($uniqueBookings as $bk):
    $sTs = (new DateTime($bk['start_datetime']))->getTimestamp() * 1000;
    $eTs = $sTs + $bk['duration_hours'] * 3600000;
  ?>
  if (nowTs >= <?= $sTs ?> && nowTs < <?= $eTs ?>) activeBookings++;
  <?php endforeach; ?>

  if (statusEl) {
    statusEl.textContent = hh + ':' + mm + (activeBookings > 0 ? ' · กำลังใช้งาน ' + activeBookings + ' คอร์ต' : ' · ทุกคอร์ตว่าง');
  }
}

updateTimeLine();
setInterval(updateTimeLine, 30000);
<?php endif; ?>

// ===================== Scroll to current time on load =====================
<?php if ($isToday): ?>
window.addEventListener('load', function() {
  const now  = new Date();
  const mins = (now.getHours() - SLOT_START_H) * 60 + now.getMinutes();
  if (mins > 0) {
    const scrollTo = Math.max(0, COURT_COL_W + (mins / 30) * SLOT_W - window.innerWidth / 2);
    tlWrap.scrollLeft = scrollTo;
  }
});
<?php endif; ?>

// ===================== Annotate booking cells with data-bk-id =====================
document.querySelectorAll('.tl-booked').forEach(function(cell) {
  const onclickAttr = cell.getAttribute('onclick') || '';
  const match = onclickAttr.match(/"id"\s*:\s*"?(\d+)"?/);
  if (match) cell.dataset.bkId = match[1];
});

// ===================== Quick-info tooltip =====================
(function () {
  const tooltip = document.getElementById('bkTooltip');

  function getStatus(startTs, endTs) {
    const now = Date.now();
    const sMs = startTs * 1000, eMs = endTs * 1000;
    if (now >= sMs && now < eMs) {
      const minsLeft = Math.round((eMs - now) / 60000);
      const h = Math.floor(minsLeft / 60), m = minsLeft % 60;
      const txt = h > 0 ? `เหลือ ${h} ชม.${m ? ' ' + m + ' นาที' : ''}` : `เหลือ ${minsLeft} นาที`;
      return { text: txt, bg: '#d1fae5', color: '#065f46' };
    }
    if (now < sMs) {
      const mins = Math.round((sMs - now) / 60000);
      const h = Math.floor(mins / 60), m = mins % 60;
      const txt = h > 0 ? `อีก ${h} ชม.${m ? ' ' + m + ' นาที' : ''}` : `อีก ${mins} นาที`;
      return { text: txt, bg: '#dbeafe', color: '#1e40af' };
    }
    return { text: 'เสร็จแล้ว', bg: '#f3f4f6', color: '#6b7280' };
  }

  // Hover tooltip for timeline slots
  document.querySelectorAll('.tl-booked[data-bk-name]').forEach(cell => {
    cell.addEventListener('mousemove', function (e) {
      const { bkName, bkCourt, bkBadge, bkStart, bkEnd, bkStartTs, bkEndTs, bkVip } = this.dataset;
      const st = getStatus(parseInt(bkStartTs), parseInt(bkEndTs));

      document.getElementById('ttBadge').textContent     = bkBadge || (bkVip === '1' ? 'V' : '#');
      document.getElementById('ttBadge').style.background = bkVip === '1' ? '#B71C1C' : '#D32F2F';
      document.getElementById('ttCourt').textContent     = bkCourt;
      document.getElementById('ttTime').textContent      = bkStart + ' – ' + bkEnd;
      document.getElementById('ttName').textContent      = bkName;
      const statusEl = document.getElementById('ttStatus');
      statusEl.textContent     = st.text;
      statusEl.style.background = st.bg;
      statusEl.style.color      = st.color;

      let tx = e.clientX + 14, ty = e.clientY - 70;
      if (tx + 280 > window.innerWidth) tx = e.clientX - 280;
      if (ty < 8) ty = e.clientY + 14;
      tooltip.style.left = tx + 'px';
      tooltip.style.top  = ty + 'px';
      tooltip.classList.add('tt-show');
    });
    cell.addEventListener('mouseleave', () => tooltip.classList.remove('tt-show'));
  });

  // Status badge on card view items
  document.querySelectorAll('.bk-card-item[data-bk-start-ts]').forEach(item => {
    const st = getStatus(parseInt(item.dataset.bkStartTs), parseInt(item.dataset.bkEndTs));
    const badge = document.createElement('span');
    badge.className = 'inline-block text-xs px-2 py-0.5 rounded-full font-medium mt-1.5';
    badge.style.background = st.bg;
    badge.style.color = st.color;
    badge.textContent = st.text;
    item.appendChild(badge);
  });
})();

// ===================== View Toggle =====================
function setView(v) {
  const isTimeline = v === 'timeline';
  document.getElementById('viewTimeline').classList.toggle('hidden', !isTimeline);
  document.getElementById('viewCards').classList.toggle('hidden', isTimeline);
  const btnT = document.getElementById('btnTimeline');
  const btnC = document.getElementById('btnCards');
  btnT.style.cssText = isTimeline ? 'background:#D32F2F;color:#fff;font-weight:600;' : 'background:#fff;color:#374151;';
  btnC.style.cssText = isTimeline ? 'background:#fff;color:#374151;' : 'background:#D32F2F;color:#fff;font-weight:600;';
  localStorage.setItem('timetableView', v);
}
// Restore saved preference on load
(function() { setView(localStorage.getItem('timetableView') || 'timeline'); })();

<?php if ($isToday): ?>
// ===================== SweetAlert2 Court Expiry Notifications =====================
const bkAlerts = [
<?php foreach ($uniqueBookings as $bk):
    $s = new DateTime($bk['start_datetime']);
    $e = (clone $s)->modify('+' . (int)$bk['duration_hours'] . ' hour');
    $courtLabel = ($bk['court_type'] === 'vip' || $bk['is_vip'] == 1)
        ? ($bk['vip_room_name'] ?? 'ห้อง VIP')
        : 'คอร์ต ' . $bk['court_no'];
?>
  { id: <?= $bk['id'] ?>, court: <?= json_encode($courtLabel, JSON_UNESCAPED_UNICODE) ?>, name: <?= json_encode($bk['customer_name'], JSON_UNESCAPED_UNICODE) ?>, startTs: <?= $s->getTimestamp() * 1000 ?>, endTs: <?= $e->getTimestamp() * 1000 ?> },
<?php endforeach; ?>
];

(function () {
  const notifiedEnd  = new Set();
  const notifiedWarn = new Set();

  // Pre-mark only truly ENDED bookings (not the 5-min window)
  // so warnings still fire if page opens during last 5 minutes
  bkAlerts.forEach(bk => {
    if (Date.now() >= bk.endTs) {
      notifiedEnd.add(bk.id);
      notifiedWarn.add(bk.id); // suppress warning for already-ended bookings
    }
  });

  function fireWarn(bk, now) {
    notifiedWarn.add(bk.id);
    const minsLeft = Math.max(1, Math.round((bk.endTs - now) / 60000));
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'warning',
      title: `<span style="font-size:15px;font-weight:700;">${bk.court} ใกล้หมดเวลา</span>`,
      html: `<span style="font-size:13px;">${bk.name} · เหลืออีก <b>${minsLeft} นาที</b></span>`,
      showConfirmButton: false,
      timer: 12000,
      timerProgressBar: true,
      background: '#fffbeb',
      color: '#92400e',
      iconColor: '#d97706',
    });
  }

  function fireEnd(bk) {
    notifiedEnd.add(bk.id);
    Swal.fire({
      icon: 'info',
      title: `${bk.court} หมดเวลาแล้ว`,
      html: `ลูกค้า <b>${bk.name}</b><br>เล่นครบเวลาแล้ว กรุณาเตรียมคอร์ตสำหรับรายถัดไป`,
      confirmButtonText: 'รับทราบ',
      confirmButtonColor: '#D32F2F',
      timer: 30000,
      timerProgressBar: true,
    });
  }

  function checkAlerts() {
    const now = Date.now();
    bkAlerts.forEach(bk => {
      if (now < bk.startTs) return; // ยังไม่เริ่ม

      // 🔴 หมดเวลา
      if (now >= bk.endTs && !notifiedEnd.has(bk.id)) {
        fireEnd(bk);
        return;
      }
      // ⚠️ เหลือ 5 นาที
      if (now >= bk.endTs - 5 * 60 * 1000 && !notifiedWarn.has(bk.id)) {
        fireWarn(bk, now);
      }
    });
  }

  checkAlerts();
  setInterval(checkAlerts, 30000);
})();

<?php endif; ?>
</script>
</body>
</html>
