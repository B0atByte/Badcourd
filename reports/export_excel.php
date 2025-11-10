<?php
require_once __DIR__.'/../auth/guard.php';
require_once __DIR__.'/../config/db.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require_once __DIR__.'/../vendor/autoload.php';

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-t');

if (isset($_GET['download'])) {
    $stmt=$pdo->prepare("SELECT b.*, c.court_no FROM bookings b JOIN courts c ON b.court_id=c.id
    WHERE DATE(b.created_at) BETWEEN :f AND :t ORDER BY b.created_at");
    $stmt->execute([':f'=>$from, ':t'=>$to]);
    $rows=$stmt->fetchAll();

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([
        ['วันที่ทำรายการ','เวลาทำรายการ','คอร์ดที่จอง','ผู้จอง','วันที่จองคอร์ด','จำนวนชมที่จอง','เวลาเริ่ม','เวลาจบ','ส่วนลด','ราคา']
    ]);

    $r=2;
    foreach($rows as $x){
        $created = new DateTime($x['created_at']);
        $start = new DateTime($x['start_datetime']);
        $end = (clone $start)->modify('+'.$x['duration_hours'].' hour');
        $sheet->fromArray([
            [
                $created->format('Y-m-d'),
                $created->format('H:i'),
                'คอร์ด '.$x['court_no'],
                $x['customer_name'],
                $start->format('Y-m-d'),
                $x['duration_hours'],
                $start->format('H:i'),
                $end->format('H:i'),
                $x['discount_amount'],
                $x['total_amount']
            ]
        ], null, 'A'.$r);
        $r++;
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="BARGAIN SPORT-report.xlsx"');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// Preview data
$stmt=$pdo->prepare("SELECT b.*, c.court_no FROM bookings b JOIN courts c ON b.court_id=c.id
WHERE DATE(b.created_at) BETWEEN :f AND :t ORDER BY b.created_at LIMIT 10");
$stmt->execute([':f'=>$from, ':t'=>$to]);
$previewRows=$stmt->fetchAll();

// Stats
$statsStmt=$pdo->prepare("SELECT COUNT(*) as total, SUM(total_amount) as revenue FROM bookings
WHERE DATE(created_at) BETWEEN :f AND :t AND status='booked'");
$statsStmt->execute([':f'=>$from, ':t'=>$to]);
$stats=$statsStmt->fetch();
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<title>ออกรายงาน Excel - BARGAIN SPORT</title>
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

  <!-- Date Range Selector -->
  <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
    <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
      <i class="fas fa-calendar-alt text-blue-600"></i>
      เลือกช่วงวันที่
    </h2>
    
    <form method="get" class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <!-- From Date -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">
          <i class="fas fa-calendar-plus text-green-500 mr-1"></i>วันที่เริ่มต้น
        </label>
        <input type="date" name="from" value="<?= htmlspecialchars($from) ?>"
               class="w-full px-4 py-2.5 border-2 border-gray-300 rounded-xl focus:border-green-500 
                      focus:ring-2 focus:ring-green-200 transition-all outline-none font-medium">
      </div>

      <!-- To Date -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">
          <i class="fas fa-calendar-minus text-red-500 mr-1"></i>วันที่สิ้นสุด
        </label>
        <input type="date" name="to" value="<?= htmlspecialchars($to) ?>"
               class="w-full px-4 py-2.5 border-2 border-gray-300 rounded-xl focus:border-green-500 
                      focus:ring-2 focus:ring-green-200 transition-all outline-none font-medium">
      </div>

      <!-- Download Button -->
      <div class="flex items-end">
        <button type="submit" name="download" value="1"
                class="w-full px-6 py-2.5 bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-xl 
                       font-bold hover:from-green-600 hover:to-emerald-700 hover:shadow-lg transform hover:scale-105 
                       transition-all duration-300 flex items-center justify-center gap-2">
          <i class="fas fa-file-download"></i>
          <span>ดาวน์โหลด Excel</span>
        </button>
      </div>
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
            <th class="px-4 py-3 text-left font-semibold">วันที่จอง</th>
            <th class="px-4 py-3 text-center font-semibold">คอร์ต</th>
            <th class="px-4 py-3 text-left font-semibold">ผู้จอง</th>
            <th class="px-4 py-3 text-center font-semibold">วันที่ใช้</th>
            <th class="px-4 py-3 text-center font-semibold">เวลา</th>
            <th class="px-4 py-3 text-center font-semibold">ชม.</th>
            <th class="px-4 py-3 text-right font-semibold">ส่วนลด</th>
            <th class="px-4 py-3 text-right font-semibold">ราคา</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <?php foreach($previewRows as $x): 
            $created = new DateTime($x['created_at']);
            $start = new DateTime($x['start_datetime']);
            $end = (clone $start)->modify('+'.$x['duration_hours'].' hour');
          ?>
          <tr class="hover:bg-gray-50 transition-colors">
            <td class="px-4 py-3">
              <div class="font-medium text-gray-800"><?= $created->format('d/m/Y') ?></div>
              <div class="text-xs text-gray-500"><?= $created->format('H:i') ?></div>
            </td>
            <td class="px-4 py-3 text-center">
              <span class="inline-flex items-center justify-center w-10 h-10 bg-gradient-to-br from-blue-500 to-purple-600 
                           text-white rounded-lg font-bold">
                <?= $x['court_no'] ?>
              </span>
            </td>
            <td class="px-4 py-3 font-medium text-gray-800">
              <?= htmlspecialchars($x['customer_name']) ?>
            </td>
            <td class="px-4 py-3 text-center text-gray-700">
              <?= $start->format('d/m/Y') ?>
            </td>
            <td class="px-4 py-3 text-center">
              <span class="font-medium text-gray-700"><?= $start->format('H:i') ?></span>
              <span class="text-gray-400 mx-1">-</span>
              <span class="font-medium text-gray-700"><?= $end->format('H:i') ?></span>
            </td>
            <td class="px-4 py-3 text-center font-semibold text-gray-700">
              <?= $x['duration_hours'] ?>
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
      <?php foreach($previewRows as $x): 
        $created = new DateTime($x['created_at']);
        $start = new DateTime($x['start_datetime']);
        $end = (clone $start)->modify('+'.$x['duration_hours'].' hour');
      ?>
      <div class="border-b border-gray-200 p-4 hover:bg-gray-50 transition-colors">
        <div class="flex justify-between items-start mb-2">
          <div class="flex items-center gap-2">
            <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-purple-600 rounded-lg flex items-center justify-center text-white font-bold">
              <?= $x['court_no'] ?>
            </div>
            <div>
              <div class="font-bold text-gray-800"><?= htmlspecialchars($x['customer_name']) ?></div>
              <div class="text-xs text-gray-500"><?= $created->format('d/m/Y H:i') ?></div>
            </div>
          </div>
          <div class="text-right">
            <div class="text-lg font-bold text-green-600">฿<?= number_format($x['total_amount'], 2) ?></div>
            <?php if($x['discount_amount'] > 0): ?>
            <div class="text-xs text-red-600">-฿<?= number_format($x['discount_amount'], 2) ?></div>
            <?php endif; ?>
          </div>
        </div>
        <div class="flex items-center gap-4 text-sm text-gray-600">
          <span><i class="fas fa-calendar text-blue-500 mr-1"></i><?= $start->format('d/m/Y') ?></span>
          <span><i class="fas fa-clock text-green-500 mr-1"></i><?= $start->format('H:i') ?> - <?= $end->format('H:i') ?></span>
          <span><i class="fas fa-hourglass-half text-orange-500 mr-1"></i><?= $x['duration_hours'] ?> ชม.</span>
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
    <p class="text-gray-600">ไม่มีรายการจองในช่วงวันที่ที่เลือก</p>
  </div>
  <?php endif; ?>

  <!-- Info Card
  <div class="bg-gradient-to-r from-green-50 to-emerald-50 rounded-2xl shadow-md p-6 border border-green-200">
    <div class="flex items-start gap-4">
      <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl flex items-center justify-center flex-shrink-0">
        <i class="fas fa-lightbulb text-white text-xl"></i>
      </div>
      <div>
        <h3 class="font-bold text-gray-800 mb-2">💡 ข้อมูลในรายงาน Excel</h3>
        <ul class="text-sm text-gray-700 space-y-1">
          <li>• <strong>วันที่ทำรายการ</strong> - วันที่ลูกค้าทำการจอง</li>
          <li>• <strong>วันที่จองคอร์ด</strong> - วันที่จะใช้คอร์ต</li>
          <li>• <strong>จำนวนชั่วโมง</strong> - ระยะเวลาที่จองคอร์ต</li>
          <li>• <strong>ส่วนลด</strong> - ส่วนลดที่ให้ลูกค้า (ถ้ามี)</li>
          <li>• <strong>ราคา</strong> - ยอดเงินรวมหลังหักส่วนลด</li>
        </ul>
      </div>
    </div>
  </div>
</div> -->

<?php include __DIR__.'/../includes/footer.php'; ?>
</body>
</html>