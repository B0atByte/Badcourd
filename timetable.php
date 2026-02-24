<?php
require_once __DIR__ . '/auth/guard.php';
require_once __DIR__ . '/config/db.php'; // เชื่อมต่อฐานข้อมูล

// วันที่ที่ต้องการแสดง (ถ้าไม่มีให้ใช้วันที่ปัจจุบัน)
$date = $_GET['date'] ?? date('Y-m-d');

// ดึงข้อมูลคอร์ตทั้งหมด แยกตามประเภท
$courts = $pdo->query('SELECT * FROM courts ORDER BY court_type DESC, vip_room_name ASC, court_no ASC')->fetchAll();

// แยกคอร์ตตามประเภท
$vipCourts = array_filter($courts, fn($c) => $c['court_type'] === 'vip' || $c['is_vip'] == 1);
$normalCourts = array_filter($courts, fn($c) => $c['court_type'] === 'normal' || $c['is_vip'] == 0);

// ดึงจองของวันนั้นทั้งหมด
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

// สร้างเมทริกซ์ ช่วงเวลา 30 นาที (48 ช่วง)
$grid = [];
$gridDetails = []; // เก็บรายละเอียดการจองเต็ม
foreach ($courts as $c) {
    $grid[$c['id']] = array_fill(0, 48, '');
    $gridDetails[$c['id']] = array_fill(0, 48, null);
}

foreach ($bookings as $b) {
    $s = new DateTime($b['start_datetime']);
    $startHour = (int)$s->format('G');
    $startMin = (int)$s->format('i');
    $startSlot = $startHour * 2 + ($startMin >= 30 ? 1 : 0);
    
    // คำนวณจำนวนช่วง 30 นาที
    $totalSlots = $b['duration_hours'] * 2;
    
    for ($i = 0; $i < $totalSlots; $i++) {
        $slot = $startSlot + $i;
        if ($slot >= 0 && $slot < 48) {
            $grid[$b['court_id']][$slot] = $b['customer_name'];
            $gridDetails[$b['court_id']][$slot] = $b; // เก็บข้อมูลเต็มสำหรับ Modal
        }
    }
}

// แปลงวันที่เป็นภาษาไทย
$dateObj = new DateTime($date);
$thaiMonths = [
    1 => 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
    'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'
];
$thaiDays = ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'];
$dayName = $thaiDays[(int)$dateObj->format('w')];
$dayNum = $dateObj->format('d');
$monthName = $thaiMonths[(int)$dateObj->format('n')];
$year = $dateObj->format('Y') + 543;
$thaiDate = "วัน$dayName ที่ $dayNum $monthName $year";

// ฟังก์ชันแสดงชื่อคอร์ต/ห้อง
function getCourtDisplayName($court) {
    $isVip = ($court['court_type'] === 'vip' || $court['is_vip'] == 1);
    if ($isVip) {
        return $court['vip_room_name'] ?? 'ห้อง VIP';
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
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <title>ตารางคอร์ต - <?= htmlspecialchars($date) ?></title>
  <style>
    .booking-card {
      border-radius: 12px;
      padding: 12px;
      margin-bottom: 8px;
      font-size: 13px;
      font-weight: 500;
      display: flex;
      justify-content: space-between;
      align-items: center;
      transition: all 0.2s ease;
      cursor: pointer;
    }
    .booking-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    .booking-booked {
      background: linear-gradient(135deg, #ef4444 0%, #f87171 100%);
      color: white;
    }
    .booking-vip {
      background: linear-gradient(135deg, #fbbf24 0%, #fcd34d 100%);
      color: #78350f;
    }
    .booking-empty {
      background: linear-gradient(135deg, #4ade80 0%, #86efac 100%);
      color: white;
      cursor: default;
    }
    .court-card {
      background: white;
      border-radius: 16px;
      padding: 20px;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
      margin-bottom: 24px;
      border: 2px solid #e5e7eb;
      transition: all 0.3s ease;
    }
    .court-card:hover {
      box-shadow: 0 8px 16px rgba(0,0,0,0.12);
      border-color: #d1d5db;
    }
    .court-title {
      font-size: 18px;
      font-weight: 700;
      margin-bottom: 16px;
      padding-bottom: 12px;
      border-bottom: 2px solid #f0f0f0;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .court-title.vip {
      background: linear-gradient(135deg, #fbbf24 0%, #fcd34d 100%);
      background-clip: text;
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }
    .bookings-list {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 12px;
      grid-auto-flow: row;
    }
    .bookings-list .booking-empty {
      grid-column: 1 / -1;
      margin-bottom: 0;
    }
  </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="container mx-auto px-4 py-8 max-w-6xl">
  <!-- Header Section -->
  <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
      <!-- Title -->
      <div>
        <h1 class="text-3xl font-bold text-gray-800 flex items-center gap-3 mb-2">
          <i class="fas fa-table-tennis text-blue-600"></i>
          ตารางคอร์ตแบดมินตัน
        </h1>
        <p class="text-gray-600 flex items-center gap-2">
          <i class="fas fa-clock text-blue-500"></i>
          <span class="font-semibold"><?= $thaiDate ?></span>
        </p>
      </div>
      
      <!-- Date Picker & Actions -->
      <form method="get" class="flex flex-col sm:flex-row gap-3 w-full lg:w-auto">
        <div class="relative">
          <input type="date" name="date" value="<?= htmlspecialchars($date) ?>" 
                 class="pl-10 pr-4 py-2.5 border-2 border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200 
                        transition-all outline-none font-medium text-gray-700 min-w-[200px]">
          <i class="fas fa-calendar absolute left-3 top-3.5 text-gray-400"></i>
        </div>
        
        <button type="submit" 
                class="px-6 py-2.5 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-xl font-medium 
                       hover:from-blue-600 hover:to-blue-700 hover:shadow-lg transform hover:scale-105 
                       transition-all duration-300 flex items-center justify-center gap-2">
          <i class="fas fa-search"></i>
          <span>แสดงตาราง</span>
        </button>
        
        <a href="/bookings/create.php" 
           class="px-6 py-2.5 bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-xl font-medium 
                  hover:from-green-600 hover:to-emerald-700 hover:shadow-lg transform hover:scale-105 
                  transition-all duration-300 flex items-center justify-center gap-2">
          <i class="fas fa-plus-circle"></i>
          <span>จองใหม่</span>
        </a>
      </form>
    </div>
  </div>

  <!-- Legend -->
  <div class="bg-white rounded-xl shadow-md p-4 mb-6 flex flex-wrap gap-6 items-center justify-center">
    <div class="flex items-center gap-2">
      <div class="px-4 py-2 bg-gradient-to-r from-green-400 to-emerald-500 text-white rounded-lg text-sm font-medium">ว่าง</div>
    </div>
    <div class="flex items-center gap-2">
      <div class="px-4 py-2 bg-gradient-to-r from-red-400 to-red-500 text-white rounded-lg text-sm font-medium">จองแล้ว</div>
    </div>
    <div class="flex items-center gap-2">
      <div class="px-4 py-2 bg-gradient-to-r from-yellow-400 to-amber-400 text-amber-900 rounded-lg text-sm font-medium">👑 VIP</div>
    </div>
  </div>

  <!-- VIP Rooms Section -->
  <?php if (count($vipCourts) > 0): ?>
  <div class="mb-8">
    <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center gap-3">
      <i class="fas fa-crown text-amber-500"></i>
      ห้อง VIP
    </h2>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
      <?php foreach ($vipCourts as $c): 
        $displayName = getCourtDisplayName($c);
        $courtsBookingsForDay = array_filter($grid[$c['id']], fn($b) => !empty($b));
      ?>
      <div class="court-card border-amber-200">
        <div class="court-title vip">
          <i class="fas fa-door-open text-amber-500"></i>
          <?= htmlspecialchars($displayName) ?>
        </div>
        <?php if ($c['vip_price']): ?>
        <div class="text-sm text-amber-600 font-semibold mb-4 pb-4 border-b border-amber-100">
          <i class="fas fa-tag"></i> <?= number_format($c['vip_price'], 0) ?> ฿/ชม.
        </div>
        <?php endif; ?>
        
        <div class="bookings-list">
          <?php 
          $displayedBookings = [];
          for ($slot = 0; $slot < 48; $slot++):
            $name = $grid[$c['id']][$slot];
            $details = $gridDetails[$c['id']][$slot];
            $hour = floor($slot / 2);
            $min = ($slot % 2) * 30;
            $timeStr = sprintf('%02d:%02d', $hour, $min);
            
            if (!empty($name) && $details && !in_array($details['id'], $displayedBookings)):
              $displayedBookings[] = $details['id'];
              $startDate = new DateTime($details['start_datetime']);
              $endDate = new DateTime('@' . ($startDate->getTimestamp() + ($details['duration_hours'] * 60 * 60)));
              $timeStartStr = $startDate->format('H:i');
              $timeEndStr = $endDate->format('H:i');
          ?>
            <div class="booking-card booking-booked" 
                 onclick="showBookingDetails(<?= htmlspecialchars(json_encode($details)) ?>, '<?= $timeStartStr ?>', '<?= htmlspecialchars($displayName) ?>', true)">
              <div class="flex-1">
                <div><i class="fas fa-user-check mr-2"></i><?= htmlspecialchars($name) ?></div>
                <div class="text-[11px] opacity-90 mt-1"><?= $timeStartStr ?> - <?= $timeEndStr ?></div>
              </div>
              <i class="fas fa-info-circle text-white/70 ml-2 flex-shrink-0"></i>
            </div>
          <?php 
            endif;
          endfor;
          
          if (empty($displayedBookings)):
          ?>
            <div class="booking-card booking-empty">
              <div><i class="fas fa-check-circle mr-2"></i>ทั้งหมดว่าง</div>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Normal Courts Section -->
  <?php if (count($normalCourts) > 0): ?>
  <div>
    <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center gap-3">
      <i class="fas fa-table-tennis text-blue-600"></i>
      คอร์ตปกติ
    </h2>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
      <?php foreach ($normalCourts as $c): 
        $displayName = getCourtDisplayName($c);
        $courtsBookingsForDay = array_filter($grid[$c['id']], fn($b) => !empty($b));
      ?>
      <div class="court-card">
        <div class="court-title">
          <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-purple-600 rounded-lg flex items-center justify-center text-white text-sm font-bold">
            <?= htmlspecialchars($c['court_no']) ?>
          </div>
          <?= htmlspecialchars($displayName) ?>
        </div>
        
        <div class="bookings-list">
          <?php 
          $displayedBookings = [];
          for ($slot = 0; $slot < 48; $slot++):
            $name = $grid[$c['id']][$slot];
            $details = $gridDetails[$c['id']][$slot];
            $hour = floor($slot / 2);
            $min = ($slot % 2) * 30;
            $timeStr = sprintf('%02d:%02d', $hour, $min);
            
            if (!empty($name) && $details && !in_array($details['id'], $displayedBookings)):
              $displayedBookings[] = $details['id'];
              $startDate = new DateTime($details['start_datetime']);
              $endDate = new DateTime('@' . ($startDate->getTimestamp() + ($details['duration_hours'] * 60 * 60)));
              $timeStartStr = $startDate->format('H:i');
              $timeEndStr = $endDate->format('H:i');
          ?>
            <div class="booking-card booking-booked" 
                 onclick="showBookingDetails(<?= htmlspecialchars(json_encode($details)) ?>, '<?= $timeStartStr ?>', '<?= htmlspecialchars($displayName) ?>', false)">
              <div class="flex-1">
                <div><i class="fas fa-user-check mr-2"></i><?= htmlspecialchars($name) ?></div>
                <div class="text-[11px] opacity-90 mt-1"><?= $timeStartStr ?> - <?= $timeEndStr ?></div>
              </div>
              <i class="fas fa-info-circle text-white/70 ml-2 flex-shrink-0"></i>
            </div>
          <?php 
            endif;
          endfor;
          
          if (empty($displayedBookings)):
          ?>
            <div class="booking-card booking-empty">
              <div><i class="fas fa-check-circle mr-2"></i>ทั้งหมดว่าง</div>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Stats Card -->
  <div class="mt-8 grid grid-cols-1 md:grid-cols-4 gap-4">
    <?php
    $totalSlots = count($courts) * 48;
    $bookedSlots = 0;
    foreach ($grid as $courtData) {
      foreach ($courtData as $slot) {
        if (!empty($slot)) $bookedSlots++;
      }
    }
    $freeSlots = $totalSlots - $bookedSlots;
    $occupancyRate = $totalSlots > 0 ? round(($bookedSlots / $totalSlots) * 100, 1) : 0;
    ?>
    
    <div class="bg-white rounded-xl shadow-md p-6">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-gray-600 text-sm mb-1">ช่วงเวลาทั้งหมด</p>
          <p class="text-3xl font-bold text-gray-800"><?= $totalSlots ?></p>
          <p class="text-xs text-gray-500 mt-1">ช่วงละ 30 นาที</p>
        </div>
        <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center">
          <i class="fas fa-calendar-day text-white text-2xl"></i>
        </div>
      </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-md p-6">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-gray-600 text-sm mb-1">จองแล้ว</p>
          <p class="text-3xl font-bold text-red-600"><?= $bookedSlots ?></p>
          <p class="text-xs text-gray-500 mt-1"><?= round(($bookedSlots/2), 1) ?> ชั่วโมง</p>
        </div>
        <div class="w-14 h-14 bg-gradient-to-br from-red-500 to-pink-600 rounded-2xl flex items-center justify-center">
          <i class="fas fa-calendar-check text-white text-2xl"></i>
        </div>
      </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-md p-6">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-gray-600 text-sm mb-1">ว่าง</p>
          <p class="text-3xl font-bold text-green-600"><?= $freeSlots ?></p>
          <p class="text-xs text-gray-500 mt-1"><?= round(($freeSlots/2), 1) ?> ชั่วโมง</p>
        </div>
        <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center">
          <i class="fas fa-calendar-times text-white text-2xl"></i>
        </div>
      </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-md p-6">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-gray-600 text-sm mb-1">อัตราการใช้</p>
          <p class="text-3xl font-bold text-blue-600"><?= $occupancyRate ?>%</p>
          <div class="w-full bg-gray-200 rounded-full h-2 mt-2">
            <div class="bg-gradient-to-r from-blue-500 to-purple-600 h-2 rounded-full transition-all" 
                 style="width: <?= $occupancyRate ?>%"></div>
          </div>
        </div>
        <div class="w-14 h-14 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-2xl flex items-center justify-center">
          <i class="fas fa-chart-pie text-white text-2xl"></i>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal for Booking Details -->
<div id="bookingModal" class="modal">
  <div class="modal-content bg-white rounded-2xl shadow-2xl p-8 max-w-lg w-full mx-4">
    <div class="flex justify-between items-start mb-6">
      <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
        <i class="fas fa-info-circle text-blue-600"></i>
        รายละเอียดการจอง
      </h2>
      <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
        <i class="fas fa-times text-2xl"></i>
      </button>
    </div>
    
    <div class="space-y-4">
      <!-- Court & Time -->
      <div id="modalCourtBadge" class="bg-gradient-to-r from-blue-50 to-purple-50 rounded-xl p-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <p class="text-sm text-gray-600 mb-1">
              <i class="fas fa-warehouse text-blue-500 mr-1"></i> <span id="modalCourtLabel">คอร์ต</span>
            </p>
            <p class="text-lg font-bold text-gray-800" id="modalCourt">-</p>
          </div>
          <div>
            <p class="text-sm text-gray-600 mb-1">
              <i class="fas fa-clock text-purple-500 mr-1"></i> เวลา
            </p>
            <p class="text-lg font-bold text-gray-800" id="modalTime">-</p>
          </div>
        </div>
      </div>
      
      <!-- Customer Info -->
      <div class="border-t pt-4">
        <div class="flex items-start gap-3 mb-3">
          <div class="w-12 h-12 bg-gradient-to-br from-green-400 to-emerald-500 rounded-full flex items-center justify-center">
            <i class="fas fa-user text-white text-lg"></i>
          </div>
          <div class="flex-1">
            <p class="text-sm text-gray-600">ชื่อผู้จอง</p>
            <p class="text-xl font-bold text-gray-800" id="modalCustomer">-</p>
          </div>
        </div>
        
        <div class="flex items-start gap-3 mb-3">
          <div class="w-12 h-12 bg-gradient-to-br from-orange-400 to-red-500 rounded-full flex items-center justify-center">
            <i class="fas fa-phone text-white text-lg"></i>
          </div>
          <div class="flex-1">
            <p class="text-sm text-gray-600">เบอร์โทร</p>
            <p class="text-lg font-semibold text-gray-800" id="modalPhone">-</p>
          </div>
        </div>
      </div>
      
      <!-- Booking Details -->
      <div class="border-t pt-4">
        <div class="grid grid-cols-2 gap-4 mb-3">
          <div>
            <p class="text-sm text-gray-600 mb-1">
              <i class="fas fa-calendar-alt text-blue-500 mr-1"></i> วันที่จอง
            </p>
            <p class="font-semibold text-gray-800" id="modalDate">-</p>
          </div>
          <div>
            <p class="text-sm text-gray-600 mb-1">
              <i class="fas fa-hourglass-half text-purple-500 mr-1"></i> ระยะเวลา
            </p>
            <p class="font-semibold text-gray-800" id="modalDuration">-</p>
          </div>
        </div>
        
        <div>
          <p class="text-sm text-gray-600 mb-1">
            <i class="fas fa-dollar-sign text-green-500 mr-1"></i> ยอดเงิน
          </p>
          <p class="text-2xl font-bold text-green-600" id="modalPrice">-</p>
        </div>
      </div>
      
      <!-- Status -->
      <div class="bg-green-50 rounded-xl p-4 border border-green-200">
        <p class="text-sm text-gray-600 mb-1">สถานะ</p>
        <p class="font-bold text-green-600 flex items-center gap-2">
          <i class="fas fa-check-circle"></i>
          <span id="modalStatus">จองแล้ว</span>
        </p>
      </div>
      
      <!-- Actions -->
      <div class="flex gap-3 pt-4">
        <a href="#" id="modalEditLink" 
           class="flex-1 px-4 py-3 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-xl font-medium 
                  hover:from-blue-600 hover:to-blue-700 transition-all text-center flex items-center justify-center gap-2">
          <i class="fas fa-edit"></i> แก้ไข
        </a>
        <button onclick="closeModal()" 
                class="flex-1 px-4 py-3 bg-gray-200 text-gray-700 rounded-xl font-medium 
                       hover:bg-gray-300 transition-all flex items-center justify-center gap-2">
          <i class="fas fa-times"></i> ปิด
        </button>
      </div>
    </div>
  </div>
</div>

<style>
  .modal {
    display: none;
    position: fixed;
    z-index: 50;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    backdrop-filter: blur(4px);
  }
  .modal.active {
    display: flex;
    align-items: center;
    justify-content: center;
    animation: fadeIn 0.2s ease;
  }
  .modal-content {
    animation: slideUp 0.3s ease;
  }
  @keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
  }
  @keyframes slideUp {
    from { 
      transform: translateY(20px);
      opacity: 0;
    }
    to { 
      transform: translateY(0);
      opacity: 1;
    }
  }
</style>

<script>
function showBookingDetails(booking, timeStr, courtName, isVip) {
  const modal = document.getElementById('bookingModal');
  const modalCourtBadge = document.getElementById('modalCourtBadge');
  const modalCourtLabel = document.getElementById('modalCourtLabel');
  
  if (isVip) {
    modalCourtBadge.className = 'bg-gradient-to-r from-amber-50 to-yellow-50 rounded-xl p-4 border-2 border-amber-200';
    modalCourtLabel.innerHTML = '<i class="fas fa-crown text-amber-500 mr-1"></i> ห้อง VIP';
  } else {
    modalCourtBadge.className = 'bg-gradient-to-r from-blue-50 to-purple-50 rounded-xl p-4';
    modalCourtLabel.innerHTML = '<i class="fas fa-warehouse text-blue-500 mr-1"></i> คอร์ต';
  }
  
  const startDate = new Date(booking.start_datetime);
  const thaiMonths = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 
                      'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
  const dateStr = `${startDate.getDate()} ${thaiMonths[startDate.getMonth()]} ${startDate.getFullYear() + 543}`;
  const timeStartStr = startDate.toLocaleTimeString('th-TH', {hour: '2-digit', minute: '2-digit'});
  
  const endDate = new Date(startDate.getTime() + (booking.duration_hours * 60 * 60 * 1000));
  const timeEndStr = endDate.toLocaleTimeString('th-TH', {hour: '2-digit', minute: '2-digit'});
  
  document.getElementById('modalCourt').textContent = courtName;
  document.getElementById('modalTime').textContent = `${timeStartStr} - ${timeEndStr}`;
  document.getElementById('modalCustomer').textContent = booking.customer_name || '-';
  document.getElementById('modalPhone').textContent = booking.customer_phone || '-';
  document.getElementById('modalDate').textContent = dateStr;
  document.getElementById('modalDuration').textContent = `${booking.duration_hours} ชั่วโมง`;
  document.getElementById('modalPrice').textContent = `฿${parseFloat(booking.total_price).toLocaleString()}`;
  document.getElementById('modalEditLink').href = `/BARGAIN_SPORT/bookings/update.php?id=${booking.id}`;
  
  modal.classList.add('active');
}

function closeModal() {
  const modal = document.getElementById('bookingModal');
  modal.classList.remove('active');
}

document.getElementById('bookingModal').addEventListener('click', function(e) {
  if (e.target === this) {
    closeModal();
  }
});

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeModal();
  }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>