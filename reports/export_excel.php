<?php
require_once __DIR__.'/../auth/guard.php';
require_once __DIR__.'/../config/db.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Font;

require_once __DIR__.'/../vendor/autoload.php';

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-t');
$exportType = $_GET['type'] ?? 'range';

if ($exportType === 'today') {
    $from = $to = date('Y-m-d');
} elseif ($exportType === 'all') {
    $from = '2000-01-01';
    $to   = date('Y-m-d');
}

// ============================================================
// EXCEL DOWNLOAD
// ============================================================
if (isset($_GET['download'])) {

    $stmt = $pdo->prepare("
        SELECT b.*,
               c.court_no, c.vip_room_name, c.is_vip, c.court_type,
               p.name AS promo_name, p.code AS promo_code
        FROM bookings b
        JOIN courts c ON b.court_id = c.id
        LEFT JOIN promotions p ON b.promotion_id = p.id
        WHERE DATE(b.created_at) BETWEEN :f AND :t
        ORDER BY b.created_at DESC
    ");
    $stmt->execute([':f' => $from, ':t' => $to]);
    $rows = $stmt->fetchAll();

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('รายงานการจอง');

    // ---- Title Block ----
    $sheet->setCellValue('A1', 'รายงานการจองคอร์ตแบดมินตัน BARGAIN SPORT');
    $sheet->setCellValue('A2', 'ช่วงเวลา: ' . date('d/m/Y', strtotime($from)) . ' ถึง ' . date('d/m/Y', strtotime($to)));
    $sheet->setCellValue('A3', 'ออกรายงานเมื่อ: ' . date('d/m/Y H:i:s'));
    $sheet->mergeCells('A1:Q1');
    $sheet->mergeCells('A2:Q2');
    $sheet->mergeCells('A3:Q3');

    $titleStyle = [
        'font'      => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '005691']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ];
    $subStyle = [
        'font' => ['size' => 10, 'color' => ['rgb' => '004A7C']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8F1F5']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ];
    $sheet->getStyle('A1')->applyFromArray($titleStyle);
    $sheet->getStyle('A2:A3')->applyFromArray($subStyle);
    $sheet->getRowDimension(1)->setRowHeight(24);

    // ---- Header Row ----
    $headers = [
        'ลำดับ', 'วันที่ทำรายการ', 'เวลาทำรายการ',
        'คอร์ต/ห้อง', 'ผู้จอง', 'เบอร์โทร',
        'วันที่ใช้', 'เวลาเริ่ม', 'เวลาจบ', 'จำนวนชม.',
        'ราคา/ชม.', 'ส่วนลด (฿)', 'โปรโมชั่น', 'ส่วนลด %',
        'รวมเงิน', 'สถานะ', 'มีสลิป'
    ];
    $sheet->fromArray([$headers], null, 'A5');
    $headerStyle = [
        'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '004A7C']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
    ];
    $sheet->getStyle('A5:Q5')->applyFromArray($headerStyle);
    $sheet->getRowDimension(5)->setRowHeight(20);

    // ---- Data Rows ----
    $r = 6;
    $no = 1;
    $totalRevenue  = 0;
    $totalDiscount = 0;
    $promoCount    = 0;
    $slipCount     = 0;
    $cancelCount   = 0;

    foreach ($rows as $x) {
        $created   = new DateTime($x['created_at']);
        $start     = new DateTime($x['start_datetime']);
        $end       = (clone $start)->modify('+' . $x['duration_hours'] . ' hour');
        $isVip     = ($x['court_type'] === 'vip' || $x['is_vip'] == 1);
        $courtName = $isVip ? ($x['vip_room_name'] ?? 'ห้อง VIP') : 'คอร์ต ' . $x['court_no'];
        $isBooked  = $x['status'] === 'booked';

        $promoLabel = '';
        if (!empty($x['promo_name'])) {
            $promoLabel = $x['promo_name'];
        } elseif (!empty($x['promo_code'])) {
            $promoLabel = $x['promo_code'];
        }

        $promoPercent = !empty($x['promotion_discount_percent']) ? $x['promotion_discount_percent'] . '%' : '';
        $hasSlip      = !empty($x['payment_slip_path']) ? 'มีสลิป' : '-';

        $sheet->fromArray([[
            $no,
            $created->format('Y-m-d'),
            $created->format('H:i:s'),
            $courtName,
            $x['customer_name'],
            $x['customer_phone'],
            $start->format('Y-m-d'),
            $start->format('H:i'),
            $end->format('H:i'),
            (int)$x['duration_hours'],
            (float)$x['price_per_hour'],
            (float)$x['discount_amount'],
            $promoLabel,
            $promoPercent,
            (float)$x['total_amount'],
            $isBooked ? 'จองแล้ว' : 'ยกเลิก',
            $hasSlip,
        ]], null, 'A' . $r);

        // Zebra stripe
        if ($no % 2 === 0) {
            $sheet->getStyle("A{$r}:Q{$r}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F7FAFD']],
            ]);
        }

        // สถานะ color
        $statusCell = 'P' . $r;
        if ($isBooked) {
            $sheet->getStyle($statusCell)->getFont()->getColor()->setRGB('15803d');
        } else {
            $sheet->getStyle($statusCell)->getFont()->getColor()->setRGB('6b7280');
        }

        // สลิป color
        if (!empty($x['payment_slip_path'])) {
            $sheet->getStyle('Q' . $r)->getFont()->getColor()->setRGB('7c3aed');
        }

        if ($isBooked) {
            $totalRevenue  += $x['total_amount'];
            $totalDiscount += $x['discount_amount'];
        }
        if (!empty($x['promotion_id'])) $promoCount++;
        if (!empty($x['payment_slip_path'])) $slipCount++;
        if (!$isBooked) $cancelCount++;

        $r++;
        $no++;
    }

    // ---- Summary ----
    $r += 1;
    $sheet->setCellValue('A' . $r, 'สรุป');
    $sheet->getStyle('A' . $r)->applyFromArray([
        'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '005691']],
    ]);
    $r++;
    $summaryData = [
        ['จำนวนรายการทั้งหมด',     count($rows) . ' รายการ'],
        ['รายการที่จองสำเร็จ',      (count($rows) - $cancelCount) . ' รายการ'],
        ['รายการที่ยกเลิก',         $cancelCount . ' รายการ'],
        ['ใช้โปรโมชั่น',            $promoCount . ' รายการ'],
        ['มีสลิปแนบ',               $slipCount . ' รายการ'],
        ['ยอดส่วนลดรวม',            '฿' . number_format($totalDiscount, 2)],
        ['รายได้รวมทั้งหมด',        '฿' . number_format($totalRevenue, 2)],
    ];
    foreach ($summaryData as $sd) {
        $sheet->fromArray([$sd], null, 'A' . $r);
        $sheet->getStyle('A' . $r)->getFont()->setBold(true);
        $r++;
    }

    // ---- Column Widths ----
    $widths = [8, 14, 12, 22, 18, 14, 12, 10, 10, 10, 12, 13, 20, 10, 13, 12, 10];
    $cols = range('A', 'Q');
    foreach ($cols as $i => $col) {
        $sheet->getColumnDimension($col)->setWidth($widths[$i]);
    }

    // Center align cols: A, J, H, I, P, Q
    foreach (['A', 'H', 'I', 'J', 'P', 'Q'] as $col) {
        $sheet->getStyle($col . '6:' . $col . ($r - 1))
              ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="BARGAIN_SPORT-' . date('Y-m-d_H-i-s') . '.xlsx"');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// ============================================================
// PAGE DATA
// ============================================================
$stmt = $pdo->prepare("
    SELECT b.*,
           c.court_no, c.vip_room_name, c.is_vip, c.court_type,
           p.name AS promo_name, p.code AS promo_code
    FROM bookings b
    JOIN courts c ON b.court_id = c.id
    LEFT JOIN promotions p ON b.promotion_id = p.id
    WHERE DATE(b.created_at) BETWEEN :f AND :t
    ORDER BY b.created_at DESC
    LIMIT 10
");
$stmt->execute([':f' => $from, ':t' => $to]);
$previewRows = $stmt->fetchAll();

$statsStmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'booked' THEN total_amount ELSE 0 END) AS revenue,
        SUM(CASE WHEN status = 'booked' THEN discount_amount ELSE 0 END) AS total_discount,
        SUM(CASE WHEN status = 'booked' AND promotion_id IS NOT NULL THEN 1 ELSE 0 END) AS promo_count,
        SUM(CASE WHEN payment_slip_path IS NOT NULL THEN 1 ELSE 0 END) AS slip_count,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancel_count
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
<title>ออกรายงาน Excel - BARGAIN SPORT</title>
</head>
<body style="background:#FAFAFA;" class="min-h-screen">
<?php include __DIR__.'/../includes/header.php'; ?>

<div class="max-w-7xl mx-auto px-4 py-8">

  <!-- Header -->
  <div class="mb-6">
    <h1 style="color:#005691;" class="text-2xl font-bold mb-1">ออกรายงาน Excel</h1>
    <p class="text-gray-500 text-sm">ดาวน์โหลดรายงานการจองพร้อมข้อมูลโปรโมชั่นและสลิปในรูปแบบ Excel</p>
  </div>

  <!-- Export Options -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">

    <form method="get" class="bg-white rounded-xl border border-gray-200 p-6">
      <div class="mb-4">
        <h3 style="color:#005691;" class="text-lg font-bold mb-1">ข้อมูลวันนี้</h3>
        <p class="text-sm text-gray-600">Export การจองวันที่ <?= date('d/m/Y') ?></p>
      </div>
      <input type="hidden" name="type" value="today">
      <button type="submit" name="download" value="1"
              style="background:#004A7C;"
              class="w-full px-4 py-2.5 text-white rounded-lg font-medium hover:opacity-90 transition-opacity text-sm">
        ดาวน์โหลด Excel
      </button>
    </form>

    <form method="get" class="bg-white rounded-xl border border-gray-200 p-6">
      <div class="mb-4">
        <h3 style="color:#005691;" class="text-lg font-bold mb-1">ข้อมูลทั้งหมด</h3>
        <p class="text-sm text-gray-600">Export การจองทั้งหมดตั้งแต่เริ่มต้น</p>
      </div>
      <input type="hidden" name="type" value="all">
      <button type="submit" name="download" value="1"
              style="background:#004A7C;"
              class="w-full px-4 py-2.5 text-white rounded-lg font-medium hover:opacity-90 transition-opacity text-sm">
        ดาวน์โหลด Excel
      </button>
    </form>

    <form method="get" class="bg-white rounded-xl border border-gray-200 p-6">
      <div class="mb-4">
        <h3 style="color:#005691;" class="text-lg font-bold mb-1">ช่วงเวลาที่กำหนด</h3>
        <p class="text-sm text-gray-600 mb-3">Export ตามช่วงเวลาที่เลือก</p>
      </div>
      <div class="space-y-3 mb-4">
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">วันที่เริ่มต้น</label>
          <input type="date" name="from" value="<?= htmlspecialchars($from) ?>"
                 class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-[#005691] outline-none text-sm">
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">วันที่สิ้นสุด</label>
          <input type="date" name="to" value="<?= htmlspecialchars($to) ?>"
                 class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-[#005691] outline-none text-sm">
        </div>
      </div>
      <input type="hidden" name="type" value="range">
      <button type="submit" name="download" value="1"
              style="background:#004A7C;"
              class="w-full px-4 py-2.5 text-white rounded-lg font-medium hover:opacity-90 transition-opacity text-sm">
        ดาวน์โหลด Excel
      </button>
    </form>
  </div>

  <!-- Stats Cards -->
  <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-gray-200 p-4">
      <p class="text-gray-400 text-xs mb-1">รายการทั้งหมด</p>
      <p style="color:#005691;" class="text-2xl font-bold"><?= number_format($stats['total']) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4">
      <p class="text-gray-400 text-xs mb-1">รายได้รวม</p>
      <p style="color:#004A7C;" class="text-2xl font-bold">฿<?= number_format($stats['revenue'] ?? 0, 0) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4">
      <p class="text-gray-400 text-xs mb-1">ยอดส่วนลดรวม</p>
      <p class="text-2xl font-bold text-orange-500">฿<?= number_format($stats['total_discount'] ?? 0, 0) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4">
      <p class="text-gray-400 text-xs mb-1">ใช้โปรโมชั่น</p>
      <p class="text-2xl font-bold text-purple-600"><?= number_format($stats['promo_count'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4">
      <p class="text-gray-400 text-xs mb-1">มีสลิปแนบ</p>
      <p class="text-2xl font-bold text-indigo-500"><?= number_format($stats['slip_count'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4">
      <p class="text-gray-400 text-xs mb-1">ยกเลิก</p>
      <p class="text-2xl font-bold text-gray-400"><?= number_format($stats['cancel_count'] ?? 0) ?></p>
    </div>
  </div>

  <!-- Preview Section -->
  <?php if (count($previewRows) > 0): ?>
  <div class="bg-white rounded-xl border border-gray-200 overflow-hidden mb-6">
    <div style="background:#005691;" class="px-6 py-4 flex justify-between items-center">
      <h2 class="text-sm font-bold text-white">ตัวอย่างข้อมูล (10 รายการแรก)</h2>
      <span class="text-blue-200 text-xs">คอลัมน์ Excel: ลำดับ · วันที่ · คอร์ต · ผู้จอง · เบอร์ · วันใช้ · เวลา · ชม. · ราคา · ส่วนลด · โปรโมชั่น · % · รวม · สถานะ · สลิป</span>
    </div>

    <!-- Desktop -->
    <div class="hidden lg:block overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr style="background:#004A7C;" class="text-white text-xs">
            <th class="px-3 py-3 text-center font-medium">#</th>
            <th class="px-3 py-3 text-left font-medium">วันที่ทำรายการ</th>
            <th class="px-3 py-3 text-center font-medium">คอร์ต/ห้อง</th>
            <th class="px-3 py-3 text-left font-medium">ผู้จอง</th>
            <th class="px-3 py-3 text-center font-medium">เบอร์</th>
            <th class="px-3 py-3 text-center font-medium">วันใช้</th>
            <th class="px-3 py-3 text-center font-medium">เวลา</th>
            <th class="px-3 py-3 text-center font-medium">ชม.</th>
            <th class="px-3 py-3 text-right font-medium">ราคา/ชม.</th>
            <th class="px-3 py-3 text-right font-medium">ส่วนลด</th>
            <th class="px-3 py-3 text-center font-medium">โปรโมชั่น</th>
            <th class="px-3 py-3 text-right font-medium">รวมเงิน</th>
            <th class="px-3 py-3 text-center font-medium">สถานะ</th>
            <th class="px-3 py-3 text-center font-medium">สลิป</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php foreach ($previewRows as $index => $x):
            $created   = new DateTime($x['created_at']);
            $start     = new DateTime($x['start_datetime']);
            $end       = (clone $start)->modify('+' . $x['duration_hours'] . ' hour');
            $isVip     = ($x['court_type'] === 'vip' || $x['is_vip'] == 1);
            $courtName = $isVip ? ($x['vip_room_name'] ?? 'ห้อง VIP') : 'คอร์ต ' . $x['court_no'];
            $isBooked  = $x['status'] === 'booked';
            $promoLabel = !empty($x['promo_name']) ? $x['promo_name'] : (!empty($x['promo_code']) ? $x['promo_code'] : '');
          ?>
          <tr class="hover:bg-gray-50 transition-colors <?= $index % 2 === 1 ? 'bg-[#F7FAFD]' : '' ?>">
            <td class="px-3 py-3 text-center text-gray-500 text-xs"><?= $index + 1 ?></td>
            <td class="px-3 py-3">
              <div class="font-medium text-gray-800 text-xs"><?= $created->format('d/m/Y') ?></div>
              <div class="text-[10px] text-gray-400"><?= $created->format('H:i') ?></div>
            </td>
            <td class="px-3 py-3 text-center text-xs">
              <?php if ($isVip): ?>
                <span class="bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded text-[10px] font-medium">VIP</span><br>
              <?php endif; ?>
              <span class="text-gray-800"><?= htmlspecialchars($courtName) ?></span>
            </td>
            <td class="px-3 py-3 text-gray-800 text-xs"><?= htmlspecialchars($x['customer_name']) ?></td>
            <td class="px-3 py-3 text-center text-gray-600 text-xs"><?= htmlspecialchars($x['customer_phone']) ?></td>
            <td class="px-3 py-3 text-center text-gray-600 text-xs"><?= $start->format('d/m/Y') ?></td>
            <td class="px-3 py-3 text-center text-gray-600 text-xs"><?= $start->format('H:i') ?>-<?= $end->format('H:i') ?></td>
            <td class="px-3 py-3 text-center text-gray-700 text-xs font-medium"><?= $x['duration_hours'] ?></td>
            <td class="px-3 py-3 text-right text-gray-700 text-xs">฿<?= number_format($x['price_per_hour'], 0) ?></td>
            <td class="px-3 py-3 text-right text-xs">
              <?php if ($x['discount_amount'] > 0): ?>
                <span class="text-red-500">-฿<?= number_format($x['discount_amount'], 0) ?></span>
              <?php else: ?>
                <span class="text-gray-300">-</span>
              <?php endif; ?>
            </td>
            <td class="px-3 py-3 text-center text-xs">
              <?php if ($promoLabel): ?>
                <span class="bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded text-[10px] font-medium">
                  <?= htmlspecialchars($promoLabel) ?>
                  <?php if (!empty($x['promotion_discount_percent'])): ?>
                    (<?= $x['promotion_discount_percent'] ?>%)
                  <?php endif; ?>
                </span>
              <?php else: ?>
                <span class="text-gray-300">-</span>
              <?php endif; ?>
            </td>
            <td style="color:#004A7C;" class="px-3 py-3 text-right font-bold text-xs">฿<?= number_format($x['total_amount'], 0) ?></td>
            <td class="px-3 py-3 text-center">
              <span class="text-[10px] px-2 py-0.5 rounded-full <?= $isBooked ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
                <?= $isBooked ? 'จองแล้ว' : 'ยกเลิก' ?>
              </span>
            </td>
            <td class="px-3 py-3 text-center text-xs">
              <?php if (!empty($x['payment_slip_path'])): ?>
                <span class="text-indigo-600 font-medium">✓ มีสลิป</span>
              <?php else: ?>
                <span class="text-gray-300">-</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Mobile -->
    <div class="block lg:hidden divide-y divide-gray-100">
      <?php foreach ($previewRows as $index => $x):
        $created   = new DateTime($x['created_at']);
        $start     = new DateTime($x['start_datetime']);
        $end       = (clone $start)->modify('+' . $x['duration_hours'] . ' hour');
        $isVip     = ($x['court_type'] === 'vip' || $x['is_vip'] == 1);
        $courtName = $isVip ? ($x['vip_room_name'] ?? 'ห้อง VIP') : 'คอร์ต ' . $x['court_no'];
        $isBooked  = $x['status'] === 'booked';
        $promoLabel = !empty($x['promo_name']) ? $x['promo_name'] : (!empty($x['promo_code']) ? $x['promo_code'] : '');
      ?>
      <div class="p-4">
        <div class="flex justify-between items-start mb-2">
          <div class="flex-1">
            <div class="font-bold text-gray-800 mb-0.5"><?= htmlspecialchars($x['customer_name']) ?></div>
            <div class="text-sm text-gray-600 mb-0.5"><?= htmlspecialchars($courtName) ?></div>
            <div class="text-xs text-gray-400"><?= $created->format('d/m/Y H:i') ?> · <?= htmlspecialchars($x['customer_phone']) ?></div>
          </div>
          <div class="text-right">
            <div style="color:#004A7C;" class="text-lg font-bold">฿<?= number_format($x['total_amount'], 0) ?></div>
            <span class="text-[10px] px-2 py-0.5 rounded-full <?= $isBooked ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
              <?= $isBooked ? 'จองแล้ว' : 'ยกเลิก' ?>
            </span>
          </div>
        </div>
        <div class="flex flex-wrap gap-2 text-xs text-gray-500 border-t pt-2 mt-2">
          <span><?= $start->format('d/m/Y') ?> <?= $start->format('H:i') ?>-<?= $end->format('H:i') ?></span>
          <span><?= $x['duration_hours'] ?> ชม.</span>
          <?php if ($promoLabel): ?>
          <span class="bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded text-[10px]">
            <?= htmlspecialchars($promoLabel) ?>
            <?php if (!empty($x['promotion_discount_percent'])): ?>(<?= $x['promotion_discount_percent'] ?>%)<?php endif; ?>
          </span>
          <?php endif; ?>
          <?php if (!empty($x['payment_slip_path'])): ?>
          <span class="text-indigo-600 text-[10px] font-medium">✓ มีสลิป</span>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if (count($previewRows) >= 10): ?>
    <div class="bg-gray-50 border-t border-gray-200 px-6 py-3 text-center">
      <p class="text-xs text-gray-500">แสดง 10 รายการแรก — ดาวน์โหลด Excel เพื่อดูข้อมูลทั้งหมดพร้อมคอลัมน์โปรโมชั่นและสลิป</p>
    </div>
    <?php endif; ?>
  </div>
  <?php else: ?>
  <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
    <h3 class="text-xl font-bold text-gray-800 mb-2">ไม่พบข้อมูล</h3>
    <p class="text-gray-500">ไม่มีรายการจองในช่วงเวลาที่เลือก</p>
  </div>
  <?php endif; ?>

</div>

<?php include __DIR__.'/../includes/footer.php'; ?>
</body>
</html>
