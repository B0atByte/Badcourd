<?php
require_once __DIR__.'/../auth/guard.php';
require_once __DIR__.'/../config/db.php';
$rows=$pdo->query("SELECT b.*, c.court_no FROM bookings b JOIN courts c ON b.court_id=c.id ORDER BY b.start_datetime DESC LIMIT 200")->fetchAll();
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<title>การจอง - BARGAIN SPORT</title>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
<?php include __DIR__.'/../includes/header.php'; ?>

<div class="container mx-auto px-4 py-8 max-w-7xl">
  <!-- Header Section -->
  <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
      <div>
        <h1 class="text-3xl font-bold text-gray-800 flex items-center gap-3 mb-2">
          <i class="fas fa-clipboard-list text-green-600"></i>
          รายการจองทั้งหมด
        </h1>
        <p class="text-gray-600 flex items-center gap-2">
          <i class="fas fa-info-circle text-blue-500"></i>
          แสดงการจอง <?= count($rows) ?> รายการล่าสุด
        </p>
      </div>
      
      <a href="create.php" 
         class="px-6 py-3 bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-xl font-medium 
                hover:from-green-600 hover:to-emerald-700 hover:shadow-lg transform hover:scale-105 
                transition-all duration-300 flex items-center gap-2">
        <i class="fas fa-plus-circle"></i>
        <span>จองคอร์ตใหม่</span>
      </a>
    </div>
  </div>

  <!-- Stats Cards -->
  <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <?php
    $totalBookings = count($rows);
    $bookedCount = count(array_filter($rows, fn($r) => $r['status'] === 'booked'));
    $cancelledCount = count(array_filter($rows, fn($r) => $r['status'] === 'cancelled'));
    $totalRevenue = array_sum(array_map(fn($r) => $r['status'] === 'booked' ? $r['total_amount'] : 0, $rows));
    ?>
    
    <div class="bg-white rounded-xl shadow-md p-5 hover:shadow-lg transition-shadow">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-gray-600 text-sm mb-1">จองทั้งหมด</p>
          <p class="text-2xl font-bold text-gray-800"><?= $totalBookings ?></p>
        </div>
        <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center">
          <i class="fas fa-calendar-alt text-white text-xl"></i>
        </div>
      </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-md p-5 hover:shadow-lg transition-shadow">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-gray-600 text-sm mb-1">กำลังใช้งาน</p>
          <p class="text-2xl font-bold text-green-600"><?= $bookedCount ?></p>
        </div>
        <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl flex items-center justify-center">
          <i class="fas fa-check-circle text-white text-xl"></i>
        </div>
      </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-md p-5 hover:shadow-lg transition-shadow">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-gray-600 text-sm mb-1">ยกเลิกแล้ว</p>
          <p class="text-2xl font-bold text-red-600"><?= $cancelledCount ?></p>
        </div>
        <div class="w-12 h-12 bg-gradient-to-br from-red-500 to-pink-600 rounded-xl flex items-center justify-center">
          <i class="fas fa-times-circle text-white text-xl"></i>
        </div>
      </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-md p-5 hover:shadow-lg transition-shadow">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-gray-600 text-sm mb-1">รายได้รวม</p>
          <p class="text-2xl font-bold text-purple-600">฿<?= number_format($totalRevenue, 0) ?></p>
        </div>
        <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-pink-600 rounded-xl flex items-center justify-center">
          <i class="fas fa-coins text-white text-xl"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Table Section -->
  <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
    <!-- Mobile View -->
    <div class="block md:hidden">
      <?php foreach($rows as $r): 
        $s = new DateTime($r['start_datetime']); 
        $statusColor = $r['status'] === 'booked' ? 'green' : ($r['status'] === 'cancelled' ? 'red' : 'gray');
        $statusIcon = $r['status'] === 'booked' ? 'check-circle' : ($r['status'] === 'cancelled' ? 'times-circle' : 'clock');
      ?>
      <div class="border-b border-gray-200 p-4 hover:bg-gray-50 transition-colors">
        <div class="flex justify-between items-start mb-3">
          <div>
            <h3 class="font-bold text-lg text-gray-800 mb-1">
              <i class="fas fa-warehouse text-blue-600 mr-2"></i>คอร์ต <?=$r['court_no']?>
            </h3>
            <p class="text-sm text-gray-600">
              <i class="fas fa-calendar mr-2"></i><?=$s->format('d/m/Y')?>
            </p>
          </div>
          <span class="px-3 py-1 rounded-full text-xs font-semibold bg-<?=$statusColor?>-100 text-<?=$statusColor?>-700">
            <i class="fas fa-<?=$statusIcon?> mr-1"></i><?=$r['status']?>
          </span>
        </div>
        
        <div class="grid grid-cols-2 gap-2 text-sm mb-3">
          <div>
            <span class="text-gray-500">เวลา:</span>
            <span class="font-medium ml-1"><?=$s->format('H:i')?> - <?=$s->modify('+'.$r['duration_hours'].' hour')->format('H:i')?></span>
          </div>
          <div>
            <span class="text-gray-500">ผู้จอง:</span>
            <span class="font-medium ml-1"><?=htmlspecialchars($r['customer_name'])?></span>
          </div>
          <div>
            <span class="text-gray-500">ระยะเวลา:</span>
            <span class="font-medium ml-1"><?=$r['duration_hours']?> ชม.</span>
          </div>
          <div>
            <span class="text-gray-500">ราคารวม:</span>
            <span class="font-bold ml-1 text-purple-600">฿<?=number_format($r['total_amount'],2)?></span>
          </div>
        </div>
        
        <div class="flex gap-2">
          <a href="update.php?id=<?=$r['id']?>" 
             class="flex-1 px-4 py-2 bg-blue-500 text-white rounded-lg font-medium hover:bg-blue-600 
                    transition-colors text-center text-sm">
            <i class="fas fa-edit mr-1"></i>เลื่อน
          </a>
          <?php if($r['status']==='booked'): ?>
          <a href="cancel.php?id=<?=$r['id']?>" 
             onclick="return confirm('ยกเลิกการจอง?')"
             class="flex-1 px-4 py-2 bg-red-500 text-white rounded-lg font-medium hover:bg-red-600 
                    transition-colors text-center text-sm">
            <i class="fas fa-times-circle mr-1"></i>ยกเลิก
          </a>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Desktop View -->
    <div class="hidden md:block overflow-x-auto">
      <table class="w-full">
        <thead>
          <tr class="bg-gradient-to-r from-blue-600 to-purple-600 text-white">
            <th class="px-4 py-4 text-left font-semibold">
              <i class="fas fa-calendar mr-2"></i>วันที่
            </th>
            <th class="px-4 py-4 text-left font-semibold">
              <i class="fas fa-clock mr-2"></i>เวลา
            </th>
            <th class="px-4 py-4 text-center font-semibold">
              <i class="fas fa-warehouse mr-2"></i>คอร์ต
            </th>
            <th class="px-4 py-4 text-left font-semibold">
              <i class="fas fa-user mr-2"></i>ผู้จอง
            </th>
            <th class="px-4 py-4 text-center font-semibold">ชม.</th>
            <th class="px-4 py-4 text-right font-semibold">ราคา/ชม.</th>
            <th class="px-4 py-4 text-right font-semibold">ส่วนลด</th>
            <th class="px-4 py-4 text-right font-semibold">
              <i class="fas fa-coins mr-2"></i>รวม
            </th>
            <th class="px-4 py-4 text-center font-semibold">สถานะ</th>
            <th class="px-4 py-4 text-center font-semibold">จัดการ</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <?php foreach($rows as $r): 
            $s = new DateTime($r['start_datetime']); 
            $statusColor = $r['status'] === 'booked' ? 'green' : ($r['status'] === 'cancelled' ? 'red' : 'gray');
            $statusIcon = $r['status'] === 'booked' ? 'check-circle' : ($r['status'] === 'cancelled' ? 'times-circle' : 'clock');
          ?>
          <tr class="hover:bg-gray-50 transition-colors">
            <td class="px-4 py-4 text-gray-700 font-medium">
              <?=$s->format('d/m/Y')?>
            </td>
            <td class="px-4 py-4 text-gray-700">
              <span class="font-medium"><?=$s->format('H:i')?></span>
              <span class="text-gray-400 mx-1">-</span>
              <span class="font-medium"><?=$s->modify('+'.$r['duration_hours'].' hour')->format('H:i')?></span>
            </td>
            <td class="px-4 py-4 text-center">
              <span class="inline-flex items-center justify-center w-10 h-10 bg-gradient-to-br from-blue-500 to-purple-600 
                           text-white rounded-lg font-bold">
                <?=$r['court_no']?>
              </span>
            </td>
            <td class="px-4 py-4 text-gray-800 font-medium">
              <?=htmlspecialchars($r['customer_name'])?>
            </td>
            <td class="px-4 py-4 text-center text-gray-700 font-semibold">
              <?=$r['duration_hours']?>
            </td>
            <td class="px-4 py-4 text-right text-gray-700">
              ฿<?=number_format($r['price_per_hour'],2)?>
            </td>
            <td class="px-4 py-4 text-right text-red-600 font-medium">
              <?=$r['discount_amount'] > 0 ? '-฿'.number_format($r['discount_amount'],2) : '-'?>
            </td>
            <td class="px-4 py-4 text-right font-bold text-purple-600">
              ฿<?=number_format($r['total_amount'],2)?>
            </td>
            <td class="px-4 py-4 text-center">
              <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold 
                           bg-<?=$statusColor?>-100 text-<?=$statusColor?>-700">
                <i class="fas fa-<?=$statusIcon?> mr-1"></i>
                <?=$r['status']?>
              </span>
            </td>
            <td class="px-4 py-4 text-center">
              <div class="flex gap-2 justify-center">
                <a href="update.php?id=<?=$r['id']?>" 
                   class="px-3 py-1.5 bg-blue-500 text-white rounded-lg text-sm font-medium 
                          hover:bg-blue-600 hover:shadow-md transform hover:scale-105 transition-all 
                          flex items-center gap-1">
                  <i class="fas fa-edit"></i>
                  <span>เลื่อน</span>
                </a>
                <?php if($r['status']==='booked'): ?>
                <a href="cancel.php?id=<?=$r['id']?>" 
                   onclick="return confirm('ยกเลิกการจอง?')"
                   class="px-3 py-1.5 bg-red-500 text-white rounded-lg text-sm font-medium 
                          hover:bg-red-600 hover:shadow-md transform hover:scale-105 transition-all 
                          flex items-center gap-1">
                  <i class="fas fa-times-circle"></i>
                  <span>ยกเลิก</span>
                </a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if(count($rows) === 0): ?>
  <div class="bg-white rounded-2xl shadow-lg p-12 text-center">
    <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
    <h3 class="text-2xl font-bold text-gray-800 mb-2">ยังไม่มีการจอง</h3>
    <p class="text-gray-600 mb-6">เริ่มต้นจองคอร์ตแบดมินตันของคุณได้เลย</p>
    <a href="create.php" 
       class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-green-500 to-emerald-600 
              text-white rounded-xl font-medium hover:from-green-600 hover:to-emerald-700 hover:shadow-lg 
              transform hover:scale-105 transition-all duration-300">
      <i class="fas fa-plus-circle"></i>
      <span>จองคอร์ตเลย</span>
    </a>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__.'/../includes/footer.php'; ?>
</body>
</html>