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
<title>การจอง - BARGAIN SPORT</title>
</head>
<body style="background:#EDEDCE;" class="min-h-screen">
<?php include __DIR__.'/../includes/header.php'; ?>

<div class="max-w-7xl mx-auto px-4 py-8">

  <!-- Header -->
  <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
    <div>
      <h1 style="color:#0C2C55;" class="text-2xl font-bold">รายการจองทั้งหมด</h1>
      <p class="text-gray-500 text-sm mt-0.5">แสดง <?= count($rows) ?> รายการล่าสุด</p>
    </div>
    <a href="create.php"
       style="background:#296374;"
       class="px-5 py-2.5 text-white text-sm font-medium rounded-lg hover:opacity-90 transition-opacity">
      + จองคอร์ตใหม่
    </a>
  </div>

  <!-- Stats -->
  <?php
  $totalBookings = count($rows);
  $bookedCount = count(array_filter($rows, fn($r) => $r['status'] === 'booked'));
  $cancelledCount = count(array_filter($rows, fn($r) => $r['status'] === 'cancelled'));
  $totalRevenue = array_sum(array_map(fn($r) => $r['status'] === 'booked' ? $r['total_amount'] : 0, $rows));
  ?>
  <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-gray-200 p-4">
      <p class="text-gray-500 text-xs mb-1">จองทั้งหมด</p>
      <p style="color:#0C2C55;" class="text-2xl font-bold"><?= $totalBookings ?></p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4">
      <p class="text-gray-500 text-xs mb-1">กำลังใช้งาน</p>
      <p style="color:#296374;" class="text-2xl font-bold"><?= $bookedCount ?></p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4">
      <p class="text-gray-500 text-xs mb-1">ยกเลิกแล้ว</p>
      <p class="text-2xl font-bold text-gray-400"><?= $cancelledCount ?></p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4">
      <p class="text-gray-500 text-xs mb-1">รายได้รวม</p>
      <p style="color:#296374;" class="text-2xl font-bold">฿<?= number_format($totalRevenue, 0) ?></p>
    </div>
  </div>

  <!-- Table -->
  <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">

    <!-- Mobile -->
    <div class="block md:hidden divide-y divide-gray-100">
      <?php foreach($rows as $r):
        $s = new DateTime($r['start_datetime']);
        $isBooked = $r['status'] === 'booked';
      ?>
      <div class="p-4">
        <div class="flex justify-between items-start mb-2">
          <div>
            <span style="color:#0C2C55;" class="font-semibold">คอร์ต <?=$r['court_no']?></span>
            <span class="text-gray-500 text-sm ml-2"><?=$s->format('d/m/Y')?></span>
          </div>
          <span class="text-xs px-2 py-1 rounded-full <?= $isBooked ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
            <?= $isBooked ? 'จองแล้ว' : 'ยกเลิก' ?>
          </span>
        </div>
        <div class="text-sm text-gray-600 mb-2">
          <?=$s->format('H:i')?> - <?=$s->modify('+'.$r['duration_hours'].' hour')->format('H:i')?>
          &nbsp;·&nbsp; <?=htmlspecialchars($r['customer_name'])?>
          &nbsp;·&nbsp; <span style="color:#296374;" class="font-medium">฿<?=number_format($r['total_amount'],0)?></span>
        </div>
        <div class="flex gap-2">
          <a href="update.php?id=<?=$r['id']?>"
             style="color:#296374;"
             class="text-sm border border-[#629FAD] px-3 py-1 rounded hover:bg-[#EDEDCE] transition-colors">เลื่อน</a>
          <?php if($isBooked): ?>
          <a href="cancel.php?id=<?=$r['id']?>"
             onclick="return confirm('ยืนยันยกเลิกการจอง?')"
             class="text-sm border border-red-300 text-red-500 px-3 py-1 rounded hover:bg-red-50 transition-colors">ยกเลิก</a>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Desktop -->
    <div class="hidden md:block overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr style="background:#0C2C55;" class="text-white text-left">
            <th class="px-4 py-3 font-medium">วันที่</th>
            <th class="px-4 py-3 font-medium">เวลา</th>
            <th class="px-4 py-3 font-medium text-center">คอร์ต</th>
            <th class="px-4 py-3 font-medium">ผู้จอง</th>
            <th class="px-4 py-3 font-medium text-center">ชม.</th>
            <th class="px-4 py-3 font-medium text-right">ราคา/ชม.</th>
            <th class="px-4 py-3 font-medium text-right">ส่วนลด</th>
            <th class="px-4 py-3 font-medium text-right">รวม</th>
            <th class="px-4 py-3 font-medium text-center">สถานะ</th>
            <th class="px-4 py-3 font-medium text-center">จัดการ</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php foreach($rows as $r):
            $s = new DateTime($r['start_datetime']);
            $isBooked = $r['status'] === 'booked';
          ?>
          <tr class="hover:bg-gray-50 transition-colors">
            <td class="px-4 py-3 text-gray-700"><?=$s->format('d/m/Y')?></td>
            <td class="px-4 py-3 text-gray-700">
              <?=$s->format('H:i')?> - <?=$s->modify('+'.$r['duration_hours'].' hour')->format('H:i')?>
            </td>
            <td class="px-4 py-3 text-center">
              <span style="background:#296374;" class="inline-flex items-center justify-center w-8 h-8 text-white rounded-lg text-xs font-bold">
                <?=$r['court_no']?>
              </span>
            </td>
            <td class="px-4 py-3 text-gray-800"><?=htmlspecialchars($r['customer_name'])?></td>
            <td class="px-4 py-3 text-center text-gray-700"><?=$r['duration_hours']?></td>
            <td class="px-4 py-3 text-right text-gray-600">฿<?=number_format($r['price_per_hour'],0)?></td>
            <td class="px-4 py-3 text-right text-gray-500">
              <?=$r['discount_amount'] > 0 ? '-฿'.number_format($r['discount_amount'],0) : '-'?>
            </td>
            <td style="color:#296374;" class="px-4 py-3 text-right font-semibold">฿<?=number_format($r['total_amount'],0)?></td>
            <td class="px-4 py-3 text-center">
              <span class="text-xs px-2 py-1 rounded-full <?= $isBooked ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
                <?= $isBooked ? 'จองแล้ว' : 'ยกเลิก' ?>
              </span>
            </td>
            <td class="px-4 py-3">
              <div class="flex gap-1.5 justify-center">
                <a href="update.php?id=<?=$r['id']?>"
                   style="color:#296374; border-color:#629FAD;"
                   class="px-3 py-1 border rounded text-xs hover:bg-[#EDEDCE] transition-colors">เลื่อน</a>
                <?php if($isBooked): ?>
                <a href="cancel.php?id=<?=$r['id']?>"
                   onclick="return confirm('ยืนยันยกเลิกการจอง?')"
                   class="px-3 py-1 border border-red-300 text-red-500 rounded text-xs hover:bg-red-50 transition-colors">ยกเลิก</a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if(count($rows) === 0): ?>
    <div class="p-12 text-center">
      <p class="text-gray-400 text-lg mb-4">ยังไม่มีการจอง</p>
      <a href="create.php" style="background:#296374;" class="px-5 py-2.5 text-white text-sm rounded-lg hover:opacity-90 transition-opacity">+ จองคอร์ตเลย</a>
    </div>
    <?php endif; ?>
  </div>

</div>

<?php include __DIR__.'/../includes/footer.php'; ?>
</body>
</html>
