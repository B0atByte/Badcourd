<?php
require_once __DIR__.'/../auth/guard.php';
require_once __DIR__.'/../config/db.php';

// Get filter parameters
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$per_page = isset($_GET['per_page']) && $_GET['per_page'] !== 'all' ? (int)$_GET['per_page'] : 10;
$page = max(1, (int)($_GET['page'] ?? 1));

// Build query
$where = ['1=1'];
$params = [];

if ($search) {
    $where[] = '(b.customer_name LIKE :search1 OR b.customer_phone LIKE :search2 OR c.court_no LIKE :search3)';
    $params[':search1'] = '%' . $search . '%';
    $params[':search2'] = '%' . $search . '%';
    $params[':search3'] = '%' . $search . '%';
}

if ($status) {
    $where[] = 'b.status = :status';
    $params[':status'] = $status;
}

if ($date_from) {
    $where[] = 'DATE(b.start_datetime) >= :date_from';
    $params[':date_from'] = $date_from;
}

if ($date_to) {
    $where[] = 'DATE(b.start_datetime) <= :date_to';
    $params[':date_to'] = $date_to;
}

$whereClause = implode(' AND ', $where);

// Count total
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings b JOIN courts c ON b.court_id=c.id WHERE $whereClause");
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();

// Pagination
$offset = 0;
$totalPages = 1;
if ($per_page > 0) {
    $totalPages = max(1, ceil($totalRecords / $per_page));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $per_page;
}

// Fetch data
$query = "SELECT b.*, c.court_no, c.vip_room_name, c.court_type, c.is_vip FROM bookings b JOIN courts c ON b.court_id=c.id WHERE $whereClause ORDER BY b.start_datetime DESC";
if ($per_page > 0) {
    $query .= " LIMIT $per_page OFFSET $offset";
}
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Stats
$bookedCount = $pdo->prepare("SELECT COUNT(*) FROM bookings b JOIN courts c ON b.court_id=c.id WHERE $whereClause AND b.status='booked'");
$bookedCount->execute($params);
$bookedTotal = $bookedCount->fetchColumn();

$cancelledCount = $pdo->prepare("SELECT COUNT(*) FROM bookings b JOIN courts c ON b.court_id=c.id WHERE $whereClause AND b.status='cancelled'");
$cancelledCount->execute($params);
$cancelledTotal = $cancelledCount->fetchColumn();

$revenueStmt = $pdo->prepare("SELECT SUM(b.total_amount) FROM bookings b JOIN courts c ON b.court_id=c.id WHERE $whereClause AND b.status='booked'");
$revenueStmt->execute($params);
$totalRevenue = $revenueStmt->fetchColumn() ?? 0;
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="https://cdn.tailwindcss.com"></script>
<title>การจอง - BARGAIN SPORT</title>
</head>
<body style="background:#FAFAFA;" class="min-h-screen">
<?php include __DIR__.'/../includes/header.php'; ?>

<div class="max-w-7xl mx-auto px-4 py-8">

  <!-- Header -->
  <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
    <div>
      <h1 style="color:#005691;" class="text-2xl font-bold">รายการจองทั้งหมด</h1>
      <p class="text-gray-500 text-sm mt-0.5">แสดง <?= count($rows) ?> จาก <?= $totalRecords ?> รายการ</p>
    </div>
    <a href="create.php"
       style="background:#004A7C;"
       class="px-5 py-2.5 text-white text-sm font-medium rounded-lg hover:opacity-90 transition-opacity">
      + จองคอร์ตใหม่
    </a>
  </div>

  <!-- Stats -->
  <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-gray-200 p-4">
      <p class="text-gray-500 text-xs mb-1">จองทั้งหมด</p>
      <p style="color:#005691;" class="text-2xl font-bold"><?= $totalRecords ?></p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4">
      <p class="text-gray-500 text-xs mb-1">กำลังใช้งาน</p>
      <p style="color:#004A7C;" class="text-2xl font-bold"><?= $bookedTotal ?></p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4">
      <p class="text-gray-500 text-xs mb-1">ยกเลิกแล้ว</p>
      <p class="text-2xl font-bold text-gray-400"><?= $cancelledTotal ?></p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4">
      <p class="text-gray-500 text-xs mb-1">รายได้รวม</p>
      <p style="color:#004A7C;" class="text-2xl font-bold">฿<?= number_format($totalRevenue, 0) ?></p>
    </div>
  </div>

  <!-- Filters -->
  <div class="bg-white rounded-xl border border-gray-200 p-5 mb-5">
    <form method="get" class="space-y-4">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">

        <!-- Search -->
        <div class="lg:col-span-2">
          <label class="block text-xs font-medium text-gray-600 mb-1.5">ค้นหา</label>
          <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                 placeholder="ชื่อ, เบอร์โทร, คอร์ต..."
                 class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-[#005691] focus:ring-2 focus:ring-[#005691]/20 outline-none text-sm">
        </div>

        <!-- Status Filter -->
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1.5">สถานะ</label>
          <select name="status"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-[#005691] focus:ring-2 focus:ring-[#005691]/20 outline-none text-sm">
            <option value="">ทั้งหมด</option>
            <option value="booked" <?= $status === 'booked' ? 'selected' : '' ?>>จองแล้ว</option>
            <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>ยกเลิก</option>
          </select>
        </div>

        <!-- Per Page -->
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1.5">แสดง</label>
          <select name="per_page"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-[#005691] focus:ring-2 focus:ring-[#005691]/20 outline-none text-sm">
            <option value="10" <?= $per_page === 10 ? 'selected' : '' ?>>10 รายการ</option>
            <option value="25" <?= $per_page === 25 ? 'selected' : '' ?>>25 รายการ</option>
            <option value="50" <?= $per_page === 50 ? 'selected' : '' ?>>50 รายการ</option>
            <option value="100" <?= $per_page === 100 ? 'selected' : '' ?>>100 รายการ</option>
            <option value="all" <?= $per_page === 0 ? 'selected' : '' ?>>ทั้งหมด</option>
          </select>
        </div>

        <!-- Date From -->
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1.5">จากวันที่</label>
          <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>"
                 class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-[#005691] focus:ring-2 focus:ring-[#005691]/20 outline-none text-sm">
        </div>

        <!-- Date To -->
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1.5">ถึงวันที่</label>
          <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>"
                 class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-[#005691] focus:ring-2 focus:ring-[#005691]/20 outline-none text-sm">
        </div>

        <!-- Buttons -->
        <div class="lg:col-span-2 flex gap-2 items-end">
          <button type="submit"
                  style="background:#004A7C;"
                  class="px-5 py-2 text-white text-sm font-medium rounded-lg hover:opacity-90 transition-opacity">
            ค้นหา
          </button>
          <a href="index.php"
             class="px-5 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 transition-colors">
            ล้างค่า
          </a>
        </div>

      </div>
    </form>
  </div>

  <!-- Table -->
  <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">

    <!-- Mobile -->
    <div class="block md:hidden divide-y divide-gray-100">
      <?php if (count($rows) > 0): ?>
      <?php foreach($rows as $r):
        $s = new DateTime($r['start_datetime']);
        $isBooked = $r['status'] === 'booked';
        $isVipCourt = ($r['court_type'] === 'vip' || $r['is_vip'] == 1);
        $courtLabel = $isVipCourt ? ($r['vip_room_name'] ?? 'ห้อง VIP') : 'คอร์ต ' . $r['court_no'];
      ?>
      <div class="p-4">
        <div class="flex justify-between items-start mb-2">
          <div>
            <span style="color:#005691;" class="font-semibold"><?= htmlspecialchars($courtLabel) ?></span>
            <span class="text-gray-500 text-sm ml-2"><?=$s->format('d/m/Y')?></span>
          </div>
          <span class="text-xs px-2 py-1 rounded-full <?= $isBooked ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
            <?= $isBooked ? 'จองแล้ว' : 'ยกเลิก' ?>
          </span>
        </div>
        <div class="text-sm text-gray-600 mb-2">
          <?=$s->format('H:i')?> - <?=$s->modify('+'.$r['duration_hours'].' hour')->format('H:i')?>
          &nbsp;·&nbsp; <?=htmlspecialchars($r['customer_name'])?>
          &nbsp;·&nbsp; <span style="color:#004A7C;" class="font-medium">฿<?=number_format($r['total_amount'],0)?></span>
        </div>
        <div class="flex gap-2 flex-wrap">
          <?php if (!empty($r['payment_slip_path'])): ?>
          <button onclick="viewSlip('<?= htmlspecialchars($r['payment_slip_path'], ENT_QUOTES) ?>')"
                  class="text-sm border border-purple-200 text-purple-600 px-3 py-1 rounded hover:bg-purple-50 transition-colors">ดูสลิป</button>
          <?php endif; ?>
          <a href="update.php?id=<?=$r['id']?>"
             style="color:#004A7C;"
             class="text-sm border border-[#E8F1F5] px-3 py-1 rounded hover:bg-[#FAFAFA] transition-colors">เลื่อน</a>
          <?php if($isBooked): ?>
          <a href="cancel.php?id=<?=$r['id']?>"
             onclick="return confirm('ยืนยันยกเลิกการจอง?')"
             class="text-sm border border-red-300 text-red-500 px-3 py-1 rounded hover:bg-red-50 transition-colors">ยกเลิก</a>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
      <?php else: ?>
      <div class="p-12 text-center">
        <p class="text-gray-400 text-lg mb-4">ไม่พบข้อมูล</p>
        <a href="index.php" class="text-sm text-gray-500 underline">ล้างตัวกรอง</a>
      </div>
      <?php endif; ?>
    </div>

    <!-- Desktop -->
    <div class="hidden md:block overflow-x-auto">
      <?php if (count($rows) > 0): ?>
      <table class="w-full text-sm">
        <thead>
          <tr style="background:#005691;" class="text-white text-left">
            <th class="px-4 py-3 font-medium">วันที่</th>
            <th class="px-4 py-3 font-medium">เวลา</th>
            <th class="px-4 py-3 font-medium text-center">คอร์ต</th>
            <th class="px-4 py-3 font-medium">ผู้จอง</th>
            <th class="px-4 py-3 font-medium">เบอร์โทร</th>
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
            $isVipCourt = ($r['court_type'] === 'vip' || $r['is_vip'] == 1);
            $courtLabel = $isVipCourt ? ($r['vip_room_name'] ?? 'VIP') : $r['court_no'];
          ?>
          <tr class="hover:bg-gray-50 transition-colors">
            <td class="px-4 py-3 text-gray-700"><?=$s->format('d/m/Y')?></td>
            <td class="px-4 py-3 text-gray-700">
              <?=$s->format('H:i')?> - <?=(clone $s)->modify('+'.$r['duration_hours'].' hour')->format('H:i')?>
            </td>
            <td class="px-4 py-3 text-center">
              <?php if ($isVipCourt): ?>
              <span style="background:#005691;" class="inline-flex items-center justify-center px-2 h-8 text-white rounded-lg text-xs font-bold whitespace-nowrap max-w-[7rem] overflow-hidden" title="<?= htmlspecialchars($courtLabel) ?>">
                <?= htmlspecialchars(mb_strimwidth($courtLabel, 0, 8, '…')) ?>
              </span>
              <?php else: ?>
              <span style="background:#004A7C;" class="inline-flex items-center justify-center w-8 h-8 text-white rounded-lg text-xs font-bold">
                <?= htmlspecialchars($courtLabel) ?>
              </span>
              <?php endif; ?>
            </td>
            <td class="px-4 py-3 text-gray-800"><?=htmlspecialchars($r['customer_name'])?></td>
            <td class="px-4 py-3 text-gray-600"><?=htmlspecialchars($r['customer_phone'])?></td>
            <td class="px-4 py-3 text-center text-gray-700"><?=$r['duration_hours']?></td>
            <td class="px-4 py-3 text-right text-gray-600">฿<?=number_format($r['price_per_hour'],0)?></td>
            <td class="px-4 py-3 text-right text-gray-500">
              <?php if ($r['discount_amount'] > 0): ?>
                -฿<?= number_format($r['discount_amount'], 0) ?>
                <?php if (!empty($r['promotion_discount_percent'])): ?>
                <span class="text-xs text-purple-600 block">(โปร <?= $r['promotion_discount_percent'] ?>%)</span>
                <?php endif; ?>
              <?php else: ?>-<?php endif; ?>
            </td>
            <td style="color:#004A7C;" class="px-4 py-3 text-right font-semibold">฿<?=number_format($r['total_amount'],0)?></td>
            <td class="px-4 py-3 text-center">
              <span class="text-xs px-2 py-1 rounded-full <?= $isBooked ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
                <?= $isBooked ? 'จองแล้ว' : 'ยกเลิก' ?>
              </span>
            </td>
            <td class="px-4 py-3">
              <div class="flex gap-1.5 justify-center flex-wrap">
                <?php if (!empty($r['payment_slip_path'])): ?>
                <button onclick="viewSlip('<?= htmlspecialchars($r['payment_slip_path'], ENT_QUOTES) ?>')"
                        class="px-3 py-1 border border-purple-200 text-purple-600 rounded text-xs hover:bg-purple-50 transition-colors">
                    ดูสลิป
                </button>
                <?php endif; ?>
                <a href="update.php?id=<?=$r['id']?>"
                   style="color:#004A7C; border-color:#E8F1F5;"
                   class="px-3 py-1 border rounded text-xs hover:bg-[#FAFAFA] transition-colors">เลื่อน</a>
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
      <?php else: ?>
      <div class="p-12 text-center">
        <p class="text-gray-400 text-lg mb-4">ไม่พบข้อมูล</p>
        <a href="index.php" class="text-sm text-gray-500 underline">ล้างตัวกรอง</a>
      </div>
      <?php endif; ?>
    </div>

  </div>

  <!-- Pagination -->
  <?php if ($per_page > 0 && $totalPages > 1): ?>
  <div class="mt-5 flex flex-col sm:flex-row justify-between items-center gap-4">
    <p class="text-sm text-gray-500">
      แสดงหน้า <?= $page ?> จาก <?= $totalPages ?> (<?= number_format($totalRecords) ?> รายการ)
    </p>
    <div class="flex gap-2">
      <?php
      $queryParams = $_GET;
      $queryParams['page'] = max(1, $page - 1);
      $prevLink = 'index.php?' . http_build_query($queryParams);

      $queryParams['page'] = min($totalPages, $page + 1);
      $nextLink = 'index.php?' . http_build_query($queryParams);
      ?>

      <a href="<?= $prevLink ?>"
         class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm hover:bg-gray-50 transition-colors <?= $page <= 1 ? 'opacity-50 pointer-events-none' : '' ?>">
        ก่อนหน้า
      </a>

      <?php
      $startPage = max(1, $page - 2);
      $endPage = min($totalPages, $page + 2);

      for ($i = $startPage; $i <= $endPage; $i++):
        $queryParams['page'] = $i;
        $pageLink = 'index.php?' . http_build_query($queryParams);
      ?>
        <a href="<?= $pageLink ?>"
           class="px-4 py-2 border rounded-lg text-sm transition-colors <?= $i === $page ? 'bg-[#005691] text-white border-[#005691]' : 'bg-white border-gray-300 hover:bg-gray-50' ?>">
          <?= $i ?>
        </a>
      <?php endfor; ?>

      <a href="<?= $nextLink ?>"
         class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm hover:bg-gray-50 transition-colors <?= $page >= $totalPages ? 'opacity-50 pointer-events-none' : '' ?>">
        ถัดไป
      </a>
    </div>
  </div>
  <?php endif; ?>

</div>

<?php include __DIR__.'/../includes/footer.php'; ?>

<!-- Slip Modal -->
<div id="slipModal"
     class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60"
     onclick="closeSlip()">
  <div class="bg-white rounded-2xl p-5 max-w-sm w-full mx-4 shadow-2xl"
       onclick="event.stopPropagation()">
    <div class="flex justify-between items-center mb-4">
      <h3 style="color:#005691;" class="font-semibold text-sm">สลิปการชำระเงิน</h3>
      <button onclick="closeSlip()"
              class="text-gray-400 hover:text-gray-600 text-lg leading-none">&times;</button>
    </div>
    <img id="slipModalImg" src="" alt="slip"
         class="w-full rounded-xl border border-gray-200 max-h-[70vh] object-contain">
  </div>
</div>

<script>
function viewSlip(path) {
    document.getElementById('slipModalImg').src = '/' + path;
    const modal = document.getElementById('slipModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}
function closeSlip() {
    const modal = document.getElementById('slipModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.getElementById('slipModalImg').src = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeSlip(); });
</script>
</body>
</html>
