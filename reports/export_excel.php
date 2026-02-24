<?php
require_once __DIR__.'/../auth/guard.php';
require_once __DIR__.'/../config/db.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require_once __DIR__.'/../vendor/autoload.php';

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-t');
$exportType = $_GET['type'] ?? 'range'; // range, today, all

// กำหนดช่วงวันที่ตามประเภท
if ($exportType === 'today') {
    $from = date('Y-m-d');
    $to = date('Y-m-d');
} elseif ($exportType === 'all') {
    $from = '2000-01-01';
    $to = date('Y-m-d');
}

if (isset($_GET['download'])) {
    // ดึงข้อมูลการจองทั้งหมดตามช่วงวันที่
    $stmt = $pdo->prepare("
        SELECT b.*, c.court_no, c.vip_room_name, c.is_vip, c.court_type
        FROM bookings b 
        JOIN courts c ON b.court_id = c.id
        WHERE DATE(b.created_at) BETWEEN :f AND :t 
        ORDER BY b.created_at DESC
    ");
    $stmt->execute([':f' => $from, ':t' => $to]);
    $rows = $stmt->fetchAll();

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('รายงานการจอง');
    
    // ส่วนหัว
    $sheet->fromArray([
        ['รายงานการจองคอร์ตแบดมินตัน BARGAIN_SPORT'],
        ['ช่วงเวลา: ' . date('d/m/Y', strtotime($from)) . ' ถึง ' . date('d/m/Y', strtotime($to))],
        ['ออกรายงานเมื่อ: ' . date('d/m/Y H:i:s')],
        [],
        ['ลำดับ', 'วันที่ทำรายการ', 'เวลาทำรายการ', 'คอร์ต/ห้อง', 'ผู้จอง', 'เบอร์โทร', 'วันที่ใช้', 'เวลาเริ่ม', 'เวลาจบ', 'จำนวนชม.', 'ราคา/ชม.', 'ส่วนลด', 'รวมเงิน']
    ]);

    $r = 6;
    $no = 1;
    $totalRevenue = 0;
    
    foreach($rows as $x) {
        $created = new DateTime($x['created_at']);
        $start = new DateTime($x['start_datetime']);
        $end = (clone $start)->modify('+'.$x['duration_hours'].' hour');
        
        // กำหนดชื่อคอร์ต
        $courtName = '';
        $isVip = ($x['court_type'] === 'vip' || $x['is_vip'] == 1);
        if ($isVip) {
            $courtName = '👑 ' . ($x['vip_room_name'] ?? 'ห้อง VIP');
        } else {
            $courtName = '🏸 คอร์ต ' . $x['court_no'];
        }
        
        $sheet->fromArray([
            [
                $no,
                $created->format('Y-m-d'),
                $created->format('H:i:s'),
                $courtName,
                $x['customer_name'],
                $x['customer_phone'],
                $start->format('Y-m-d'),
                $start->format('H:i'),
                $end->format('H:i'),
                $x['duration_hours'],
                $x['price_per_hour'],
                $x['discount_amount'],
                $x['total_amount']
            ]
        ], null, 'A'.$r);
        
        $totalRevenue += $x['total_amount'];
        $r++;
        $no++;
    }

    // สรุป
    $r += 1;
    $sheet->fromArray([
        ['สรุป'],
        ['จำนวนรายการทั้งหมด', count($rows)],
        ['รายได้รวมทั้งหมด', '฿' . number_format($totalRevenue, 2)]
    ], null, 'A'.$r);

    // ปรับปรุงการแสดงผล
    $sheet->getColumnDimension('A')->setWidth(8);
    $sheet->getColumnDimension('B')->setWidth(12);
    $sheet->getColumnDimension('C')->setWidth(12);
    $sheet->getColumnDimension('D')->setWidth(25);
    $sheet->getColumnDimension('E')->setWidth(20);
    $sheet->getColumnDimension('F')->setWidth(15);
    $sheet->getColumnDimension('G')->setWidth(12);
    $sheet->getColumnDimension('H')->setWidth(10);
    $sheet->getColumnDimension('I')->setWidth(10);
    $sheet->getColumnDimension('J')->setWidth(10);
    $sheet->getColumnDimension('K')->setWidth(12);
    $sheet->getColumnDimension('L')->setWidth(12);
    $sheet->getColumnDimension('M')->setWidth(15);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="BARGAIN_SPORT-' . date('Y-m-d_H-i-s') . '.xlsx"');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// Preview data
$stmt = $pdo->prepare("
    SELECT b.*, c.court_no, c.vip_room_name, c.is_vip, c.court_type 
    FROM bookings b 
    JOIN courts c ON b.court_id = c.id
    WHERE DATE(b.created_at) BETWEEN :f AND :t 
    ORDER BY b.created_at DESC 
    LIMIT 10
");
$stmt->execute([':f' => $from, ':t' => $to]);
$previewRows = $stmt->fetchAll();

// Stats
$statsStmt = $pdo->prepare("
    SELECT COUNT(*) as total, SUM(total_amount) as revenue 
    FROM bookings
    WHERE DATE(created_at) BETWEEN :f AND :t
");
$statsStmt->execute([':f' => $from, ':t' => $to]);
$stats = $statsStmt->fetch();
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<title>ออกรายงาน Excel - BARGAIN_SPORT</title>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
<?php include __DIR__.'/../includes/header.php'; ?>

<div class="container mx-auto px-4 py-8 max-w-7xl">
  <!-- Header Section -->
  <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
      <div>
        <h1 class="text-3xl font-bold text-gray-800 flex items-center gap-3 mb-2">
          <i class="fas fa-file-excel text-green-600"></i>
          ออกรายงาน Excel
        </h1>
        <p class="text-gray-600 flex items-center gap-2">
          <i class="fas fa-download text-blue-500"></i>
          ดาวน์โหลดรายงานการจองในรูปแบบ Excel
        </p>
      </div>
    </div>
  </div>

  <!-- Export Options -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <!-- Export Today -->
    <form method="get" class="bg-white rounded-xl shadow-md p-6 hover:shadow-lg transition-all">
      <div class="text-center mb-4">
        <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center mx-auto mb-3">
          <i class="fas fa-calendar-day text-white text-3xl"></i>
        </div>
        <h3 class="text-xl font-bold text-gray-800 mb-2">ข้อมูลวันนี้</h3>
        <p class="text-sm text-gray-600 mb-4">Export การจองวันที่ <?= date('d/m/Y') ?></p>
      </div>
      <input type="hidden" name="type" value="today">
      <button type="submit" name="download" value="1"
              class="w-full px-4 py-3 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-lg font-bold
                     hover:from-blue-600 hover:to-blue-700 hover:shadow-lg transform hover:scale-105 
                     transition-all duration-300 flex items-center justify-center gap-2">
        <i class="fas fa-download"></i>
        <span>ดาวน์โหลด</span>
      </button>
    </form>

    <!-- Export All Data -->
    <form method="get" class="bg-white rounded-xl shadow-md p-6 hover:shadow-lg transition-all">
      <div class="text-center mb-4">
        <div class="w-16 h-16 bg-gradient-to-br from-purple-500 to-purple-600 rounded-2xl flex items-center justify-center mx-auto mb-3">
          <i class="fas fa-database text-white text-3xl"></i>
        </div>
        <h3 class="text-xl font-bold text-gray-800 mb-2">ข้อมูลทั้งหมด</h3>
        <p class="text-sm text-gray-600 mb-4">Export การจองทั้งหมดตั้งแต่เริ่มต้น</p>
      </div>
      <input type="hidden" name="type" value="all">
      <button type="submit" name="download" value="1"
              class="w-full px-4 py-3 bg-gradient-to-r from-purple-500 to-purple-600 text-white rounded-lg font-bold
                     hover:from-purple-600 hover:to-purple-700 hover:shadow-lg transform hover:scale-105 
                     transition-all duration-300 flex items-center justify-center gap-2">
        <i class="fas fa-download"></i>
        <span>ดาวน์โหลด</span>
      </button>
    </form>

    <!-- Export Custom Range -->
    <form method="get" class="bg-white rounded-xl shadow-md p-6 hover:shadow-lg transition-all">
      <div class="text-center mb-4">
        <div class="w-16 h-16 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center mx-auto mb-3">
          <i class="fas fa-calendar-range text-white text-3xl"></i>
        </div>
        <h3 class="text-xl font-bold text-gray-800 mb-2">ช่วงเวลาที่กำหนด</h3>
        <p class="text-sm text-gray-600 mb-4">Export ตามช่วงเวลาที่เลือก</p>
      </div>
      <div class="space-y-2 mb-3">
        <div>
          <label class="text-xs font-medium text-gray-700 block mb-1">วันที่เริ่มต้น</label>
          <input type="date" name="from" value="<?= htmlspecialchars($from) ?>"
                 class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg focus:border-green-500 
                        focus:ring-2 focus:ring-green-200 transition-all outline-none text-sm font-medium">
        </div>
        <div>
          <label class="text-xs font-medium text-gray-700 block mb-1">วันที่สิ้นสุด</label>
          <input type="date" name="to" value="<?= htmlspecialchars($to) ?>"
                 class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg focus:border-green-500 
                        focus:ring-2 focus:ring-green-200 transition-all outline-none text-sm font-medium">
        </div>
      </div>
      <input type="hidden" name="type" value="range">
      <button type="submit" name="download" value="1"
              class="w-full px-4 py-3 bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-lg font-bold
                     hover:from-green-600 hover:to-emerald-700 hover:shadow-lg transform hover:scale-105 
                     transition-all duration-300 flex items-center justify-center gap-2">
        <i class="fas fa-download"></i>
        <span>ดาวน์โหลด</span>
      </button>
    </form>
  </div>

  <!-- Stats Cards -->
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-md p-6 hover:shadow-lg transition-shadow">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-gray-600 text-sm mb-1">รายการจองในช่วงนี้</p>
          <p class="text-4xl font-bold text-blue-600"><?= number_format($stats['total']) ?></p>
          <p class="text-sm text-gray-500 mt-1">รายการ</p>
        </div>
        <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center">
          <i class="fas fa-list-alt text-white text-3xl"></i>
        </div>
      </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-md p-6 hover:shadow-lg transition-shadow">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-gray-600 text-sm mb-1">รายได้รวม</p>
          <p class="text-4xl font-bold text-green-600">฿<?= number_format($stats['revenue'] ?? 0, 0) ?></p>
          <p class="text-sm text-gray-500 mt-1">บาท</p>
        </div>
        <div class="w-16 h-16 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center">
          <i class="fas fa-money-bill-wave text-white text-3xl"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Preview Section -->
  <?php if(count($previewRows) > 0): ?>
  <div class="bg-white rounded-2xl shadow-lg overflow-hidden mb-6">
    <div class="bg-gradient-to-r from-blue-600 to-purple-600 px-6 py-4">
      <h2 class="text-xl font-bold text-white flex items-center gap-2">
        <i class="fas fa-eye"></i>
        ตัวอย่างข้อมูล (10 รายการแรก)
      </h2>
    </div>

    <!-- Desktop View -->
    <div class="hidden lg:block overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-gradient-to-r from-gray-700 to-gray-900 text-white">
            <th class="px-4 py-3 text-center font-semibold">ลำดับ</th>
            <th class="px-4 py-3 text-left font-semibold">วันที่จอง</th>
            <th class="px-4 py-3 text-center font-semibold">คอร์ต/ห้อง</th>
            <th class="px-4 py-3 text-left font-semibold">ผู้จอง</th>
            <th class="px-4 py-3 text-center font-semibold">เบอร์โทร</th>
            <th class="px-4 py-3 text-center font-semibold">วันใช้</th>
            <th class="px-4 py-3 text-center font-semibold">เวลา</th>
            <th class="px-4 py-3 text-center font-semibold">ชม.</th>
            <th class="px-4 py-3 text-right font-semibold">ราคา</th>
            <th class="px-4 py-3 text-right font-semibold">ส่วนลด</th>
            <th class="px-4 py-3 text-right font-semibold">รวมเงิน</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <?php foreach($previewRows as $index => $x): 
            $created = new DateTime($x['created_at']);
            $start = new DateTime($x['start_datetime']);
            $end = (clone $start)->modify('+'.$x['duration_hours'].' hour');
            
            $isVip = ($x['court_type'] === 'vip' || $x['is_vip'] == 1);
            $courtName = $isVip 
              ? '👑 ' . ($x['vip_room_name'] ?? 'ห้อง VIP')
              : '🏸 คอร์ต ' . $x['court_no'];
          ?>
          <tr class="hover:bg-gray-50 transition-colors">
            <td class="px-4 py-3 text-center font-medium text-gray-700">
              <?= $index + 1 ?>
            </td>
            <td class="px-4 py-3">
              <div class="font-medium text-gray-800"><?= $created->format('d/m/Y') ?></div>
              <div class="text-xs text-gray-500"><?= $created->format('H:i:s') ?></div>
            </td>
            <td class="px-4 py-3 text-center font-medium">
              <?= htmlspecialchars($courtName) ?>
            </td>
            <td class="px-4 py-3 font-medium text-gray-800">
              <?= htmlspecialchars($x['customer_name']) ?>
            </td>
            <td class="px-4 py-3 text-center text-gray-700">
              <?= htmlspecialchars($x['customer_phone']) ?>
            </td>
            <td class="px-4 py-3 text-center text-gray-700">
              <?= $start->format('d/m/Y') ?>
            </td>
            <td class="px-4 py-3 text-center">
              <span class="font-medium text-gray-700"><?= $start->format('H:i') ?></span>
              <span class="text-gray-400">-</span>
              <span class="font-medium text-gray-700"><?= $end->format('H:i') ?></span>
            </td>
            <td class="px-4 py-3 text-center font-semibold text-gray-700">
              <?= $x['duration_hours'] ?>
            </td>
            <td class="px-4 py-3 text-right text-gray-700">
              ฿<?= number_format($x['price_per_hour'], 2) ?>
            </td>
            <td class="px-4 py-3 text-right text-red-600 font-medium">
              <?= $x['discount_amount'] > 0 ? '-฿'.number_format($x['discount_amount'], 2) : '-' ?>
            </td>
            <td class="px-4 py-3 text-right font-bold text-green-600">
              ฿<?= number_format($x['total_amount'], 2) ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Mobile View -->
    <div class="block lg:hidden">
      <?php foreach($previewRows as $index => $x): 
        $created = new DateTime($x['created_at']);
        $start = new DateTime($x['start_datetime']);
        $end = (clone $start)->modify('+'.$x['duration_hours'].' hour');
        
        $isVip = ($x['court_type'] === 'vip' || $x['is_vip'] == 1);
        $courtName = $isVip 
          ? '👑 ' . ($x['vip_room_name'] ?? 'ห้อง VIP')
          : '🏸 คอร์ต ' . $x['court_no'];
      ?>
      <div class="border-b border-gray-200 p-4 hover:bg-gray-50 transition-colors">
        <div class="flex justify-between items-start mb-3">
          <div class="flex-1">
            <div class="font-bold text-gray-800 mb-1"><?= htmlspecialchars($x['customer_name']) ?></div>
            <div class="text-sm text-gray-600 mb-2"><?= htmlspecialchars($courtName) ?></div>
            <div class="text-xs text-gray-500 space-y-1">
              <div><i class="fas fa-calendar text-blue-500 mr-1"></i><?= $created->format('d/m/Y H:i') ?></div>
              <div><i class="fas fa-phone text-green-500 mr-1"></i><?= htmlspecialchars($x['customer_phone']) ?></div>
            </div>
          </div>
          <div class="text-right">
            <div class="text-lg font-bold text-green-600">฿<?= number_format($x['total_amount'], 2) ?></div>
            <?php if($x['discount_amount'] > 0): ?>
            <div class="text-xs text-red-600">-฿<?= number_format($x['discount_amount'], 2) ?></div>
            <?php endif; ?>
          </div>
        </div>
        <div class="flex items-center gap-3 text-xs text-gray-600 border-t pt-2">
          <span><i class="fas fa-calendar text-blue-500 mr-1"></i><?= $start->format('d/m/Y') ?></span>
          <span><i class="fas fa-clock text-orange-500 mr-1"></i><?= $start->format('H:i') ?> - <?= $end->format('H:i') ?></span>
          <span><i class="fas fa-hourglass-half text-purple-500 mr-1"></i><?= $x['duration_hours'] ?> ชม.</span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if(count($previewRows) >= 10): ?>
    <div class="bg-yellow-50 border-t border-yellow-200 px-6 py-3 text-center">
      <p class="text-sm text-yellow-800">
        <i class="fas fa-info-circle mr-2"></i>
        แสดงเพียง 10 รายการแรก - กรุณาดาวน์โหลด Excel เพื่อดูข้อมูลทั้งหมด
      </p>
    </div>
    <?php endif; ?>
  </div>
  <?php else: ?>
  <div class="bg-white rounded-2xl shadow-lg p-12 text-center">
    <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
    <h3 class="text-2xl font-bold text-gray-800 mb-2">ไม่พบข้อมูล</h3>
    <p class="text-gray-600">ไม่มีรายการจองในช่วงเวลาที่เลือก</p>
  </div>
  <?php endif; ?>

</div>

<?php include __DIR__.'/../includes/footer.php'; ?>
</body>
</html>