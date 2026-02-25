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
        ['รายงานการจองคอร์ตแบดมินตัน BARGAIN SPORT'],
        ['ช่วงเวลา: ' . date('d/m/Y', strtotime($from)) . ' ถึง ' . date('d/m/Y', strtotime($to))],
        ['ออกรายงานเมื่อ: ' . date('d/m/Y H:i:s')],
        [],
        ['ลำดับ', 'วันที่ทำรายการ', 'เวลาทำรายการ', 'คอร์ต/ห้อง', 'ผู้จอง', 'เบอร์โทร', 'วันที่ใช้', 'เวลาเริ่ม', 'เวลาจบ', 'จำนวนชม.', 'ราคา/ชม.', 'ส่วนลด', 'รวมเงิน', 'สถานะ']
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
            $courtName = ($x['vip_room_name'] ?? 'ห้อง VIP');
        } else {
            $courtName = 'คอร์ต ' . $x['court_no'];
        }

        $statusText = $x['status'] === 'booked' ? 'จองแล้ว' : 'ยกเลิก';

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
                $x['total_amount'],
                $statusText
            ]
        ], null, 'A'.$r);

        if ($x['status'] === 'booked') {
            $totalRevenue += $x['total_amount'];
        }
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
    $sheet->getColumnDimension('N')->setWidth(12);

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
    WHERE DATE(created_at) BETWEEN :f AND :t AND status = 'booked'
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
<title>ออกรายงาน Excel - BARGAIN SPORT</title>
</head>
<body style="background:#FAFAFA;" class="min-h-screen">
<?php include __DIR__.'/../includes/header.php'; ?>

<div class="max-w-7xl mx-auto px-4 py-8">

  <!-- Header -->
  <div class="mb-6">
    <h1 style="color:#005691;" class="text-2xl font-bold mb-1">ออกรายงาน Excel</h1>
    <p class="text-gray-500 text-sm">ดาวน์โหลดรายงานการจองในรูปแบบ Excel</p>
  </div>

  <!-- Export Options -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">

    <!-- Export Today -->
    <form method="get" class="bg-white rounded-xl border border-gray-200 p-6">
      <div class="mb-4">
        <h3 style="color:#005691;" class="text-lg font-bold mb-2">ข้อมูลวันนี้</h3>
        <p class="text-sm text-gray-600">Export การจองวันที่ <?= date('d/m/Y') ?></p>
      </div>
      <input type="hidden" name="type" value="today">
      <button type="submit" name="download" value="1"
              style="background:#004A7C;"
              class="w-full px-4 py-2.5 text-white rounded-lg font-medium hover:opacity-90 transition-opacity text-sm">
        ดาวน์โหลด
      </button>
    </form>

    <!-- Export All Data -->
    <form method="get" class="bg-white rounded-xl border border-gray-200 p-6">
      <div class="mb-4">
        <h3 style="color:#005691;" class="text-lg font-bold mb-2">ข้อมูลทั้งหมด</h3>
        <p class="text-sm text-gray-600">Export การจองทั้งหมดตั้งแต่เริ่มต้น</p>
      </div>
      <input type="hidden" name="type" value="all">
      <button type="submit" name="download" value="1"
              style="background:#004A7C;"
              class="w-full px-4 py-2.5 text-white rounded-lg font-medium hover:opacity-90 transition-opacity text-sm">
        ดาวน์โหลด
      </button>
    </form>

    <!-- Export Custom Range -->
    <form method="get" class="bg-white rounded-xl border border-gray-200 p-6">
      <div class="mb-4">
        <h3 style="color:#005691;" class="text-lg font-bold mb-2">ช่วงเวลาที่กำหนด</h3>
        <p class="text-sm text-gray-600 mb-3">Export ตามช่วงเวลาที่เลือก</p>
      </div>
      <div class="space-y-3 mb-4">
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">วันที่เริ่มต้น</label>
          <input type="date" name="from" value="<?= htmlspecialchars($from) ?>"
                 class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-[#005691] focus:ring-2 focus:ring-[#005691]/20 outline-none text-sm">
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">วันที่สิ้นสุด</label>
          <input type="date" name="to" value="<?= htmlspecialchars($to) ?>"
                 class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-[#005691] focus:ring-2 focus:ring-[#005691]/20 outline-none text-sm">
        </div>
      </div>
      <input type="hidden" name="type" value="range">
      <button type="submit" name="download" value="1"
              style="background:#004A7C;"
              class="w-full px-4 py-2.5 text-white rounded-lg font-medium hover:opacity-90 transition-opacity text-sm">
        ดาวน์โหลด
      </button>
    </form>
  </div>

  <!-- Stats Cards -->
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-gray-200 p-5">
      <p class="text-gray-500 text-xs mb-1">รายการจองในช่วงนี้</p>
      <p style="color:#005691;" class="text-3xl font-bold"><?= number_format($stats['total']) ?></p>
      <p class="text-sm text-gray-500 mt-1">รายการ</p>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-5">
      <p class="text-gray-500 text-xs mb-1">รายได้รวม</p>
      <p style="color:#004A7C;" class="text-3xl font-bold">฿<?= number_format($stats['revenue'] ?? 0, 0) ?></p>
      <p class="text-sm text-gray-500 mt-1">บาท</p>
    </div>
  </div>

  <!-- Preview Section -->
  <?php if(count($previewRows) > 0): ?>
  <div class="bg-white rounded-xl border border-gray-200 overflow-hidden mb-6">
    <div style="background:#005691;" class="px-6 py-4">
      <h2 class="text-lg font-bold text-white">ตัวอย่างข้อมูล (10 รายการแรก)</h2>
    </div>

    <!-- Desktop View -->
    <div class="hidden lg:block overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr style="background:#004A7C;" class="text-white">
            <th class="px-4 py-3 text-center font-medium">ลำดับ</th>
            <th class="px-4 py-3 text-left font-medium">วันที่จอง</th>
            <th class="px-4 py-3 text-center font-medium">คอร์ต/ห้อง</th>
            <th class="px-4 py-3 text-left font-medium">ผู้จอง</th>
            <th class="px-4 py-3 text-center font-medium">เบอร์โทร</th>
            <th class="px-4 py-3 text-center font-medium">วันใช้</th>
            <th class="px-4 py-3 text-center font-medium">เวลา</th>
            <th class="px-4 py-3 text-center font-medium">ชม.</th>
            <th class="px-4 py-3 text-right font-medium">ราคา</th>
            <th class="px-4 py-3 text-right font-medium">ส่วนลด</th>
            <th class="px-4 py-3 text-right font-medium">รวมเงิน</th>
            <th class="px-4 py-3 text-center font-medium">สถานะ</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php foreach($previewRows as $index => $x):
            $created = new DateTime($x['created_at']);
            $start = new DateTime($x['start_datetime']);
            $end = (clone $start)->modify('+'.$x['duration_hours'].' hour');

            $isVip = ($x['court_type'] === 'vip' || $x['is_vip'] == 1);
            $courtName = $isVip
              ? ($x['vip_room_name'] ?? 'ห้อง VIP')
              : 'คอร์ต ' . $x['court_no'];

            $isBooked = $x['status'] === 'booked';
          ?>
          <tr class="hover:bg-gray-50 transition-colors">
            <td class="px-4 py-3 text-center text-gray-700">
              <?= $index + 1 ?>
            </td>
            <td class="px-4 py-3">
              <div class="font-medium text-gray-800"><?= $created->format('d/m/Y') ?></div>
              <div class="text-xs text-gray-500"><?= $created->format('H:i:s') ?></div>
            </td>
            <td class="px-4 py-3 text-center">
              <span class="font-medium text-gray-800"><?= htmlspecialchars($courtName) ?></span>
            </td>
            <td class="px-4 py-3 text-gray-800">
              <?= htmlspecialchars($x['customer_name']) ?>
            </td>
            <td class="px-4 py-3 text-center text-gray-700">
              <?= htmlspecialchars($x['customer_phone']) ?>
            </td>
            <td class="px-4 py-3 text-center text-gray-700">
              <?= $start->format('d/m/Y') ?>
            </td>
            <td class="px-4 py-3 text-center text-gray-700">
              <?= $start->format('H:i') ?> - <?= $end->format('H:i') ?>
            </td>
            <td class="px-4 py-3 text-center font-medium text-gray-700">
              <?= $x['duration_hours'] ?>
            </td>
            <td class="px-4 py-3 text-right text-gray-700">
              ฿<?= number_format($x['price_per_hour'], 0) ?>
            </td>
            <td class="px-4 py-3 text-right text-gray-600">
              <?= $x['discount_amount'] > 0 ? '-฿'.number_format($x['discount_amount'], 0) : '-' ?>
            </td>
            <td style="color:#004A7C;" class="px-4 py-3 text-right font-bold">
              ฿<?= number_format($x['total_amount'], 0) ?>
            </td>
            <td class="px-4 py-3 text-center">
              <span class="text-xs px-2 py-1 rounded-full <?= $isBooked ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
                <?= $isBooked ? 'จองแล้ว' : 'ยกเลิก' ?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Mobile View -->
    <div class="block lg:hidden divide-y divide-gray-100">
      <?php foreach($previewRows as $index => $x):
        $created = new DateTime($x['created_at']);
        $start = new DateTime($x['start_datetime']);
        $end = (clone $start)->modify('+'.$x['duration_hours'].' hour');

        $isVip = ($x['court_type'] === 'vip' || $x['is_vip'] == 1);
        $courtName = $isVip
          ? ($x['vip_room_name'] ?? 'ห้อง VIP')
          : 'คอร์ต ' . $x['court_no'];

        $isBooked = $x['status'] === 'booked';
      ?>
      <div class="p-4">
        <div class="flex justify-between items-start mb-2">
          <div class="flex-1">
            <div class="font-bold text-gray-800 mb-1"><?= htmlspecialchars($x['customer_name']) ?></div>
            <div class="text-sm text-gray-600 mb-1"><?= htmlspecialchars($courtName) ?></div>
            <div class="text-xs text-gray-500">
              <?= $created->format('d/m/Y H:i') ?> · <?= htmlspecialchars($x['customer_phone']) ?>
            </div>
          </div>
          <div class="text-right">
            <div style="color:#004A7C;" class="text-lg font-bold">฿<?= number_format($x['total_amount'], 0) ?></div>
            <span class="text-xs px-2 py-1 rounded-full <?= $isBooked ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
              <?= $isBooked ? 'จองแล้ว' : 'ยกเลิก' ?>
            </span>
          </div>
        </div>
        <div class="flex items-center gap-3 text-xs text-gray-600 border-t pt-2 mt-2">
          <span><?= $start->format('d/m/Y') ?></span>
          <span><?= $start->format('H:i') ?> - <?= $end->format('H:i') ?></span>
          <span><?= $x['duration_hours'] ?> ชม.</span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if(count($previewRows) >= 10): ?>
    <div class="bg-gray-50 border-t border-gray-200 px-6 py-3 text-center">
      <p class="text-sm text-gray-600">
        แสดงเพียง 10 รายการแรก - กรุณาดาวน์โหลด Excel เพื่อดูข้อมูลทั้งหมด
      </p>
    </div>
    <?php endif; ?>
  </div>
  <?php else: ?>
  <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
    <h3 class="text-xl font-bold text-gray-800 mb-2">ไม่พบข้อมูล</h3>
    <p class="text-gray-600">ไม่มีรายการจองในช่วงเวลาที่เลือก</p>
  </div>
  <?php endif; ?>

</div>

<?php include __DIR__.'/../includes/footer.php'; ?>
</body>
</html>
