<?php
require_once __DIR__ . '/../auth/guard.php';
require_permission('reports');
require_once __DIR__ . '/../config/db.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Font;

require_once __DIR__ . '/../vendor/autoload.php';

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-t');
$exportType = $_GET['type'] ?? 'range';

if ($exportType === 'today') {
  $from = $to = date('Y-m-d');
} elseif ($exportType === 'all') {
  $from = '2000-01-01';
  $to = date('Y-m-d');
}

// ============================================================
// EXCEL DOWNLOAD
// ============================================================
if (isset($_GET['download'])) {

  $stmt = $pdo->prepare("
        SELECT b.*,
               c.court_no, c.vip_room_name, c.is_vip, c.court_type,
               p.name AS promo_name, p.code AS promo_code,
               bpt.name AS pkg_type_name,
               mbp.hours_total AS pkg_hours_total, mbp.hours_used AS pkg_hours_used,
               mbp.payment_slip_path AS pkg_slip_path
        FROM bookings b
        JOIN courts c ON b.court_id = c.id
        LEFT JOIN promotions p ON b.promotion_id = p.id
        LEFT JOIN member_badminton_packages mbp ON b.member_badminton_package_id = mbp.id
        LEFT JOIN badminton_package_types bpt ON mbp.badminton_package_type_id = bpt.id
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
  $sheet->mergeCells('A1:T1');
  $sheet->mergeCells('A2:T2');
  $sheet->mergeCells('A3:T3');

  $titleStyle = [
    'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D32F2F']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
  ];
  $subStyle = [
    'font' => ['size' => 10, 'color' => ['rgb' => 'B71C1C']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFEBEE']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
  ];
  $sheet->getStyle('A1')->applyFromArray($titleStyle);
  $sheet->getStyle('A2:A3')->applyFromArray($subStyle);
  $sheet->getRowDimension(1)->setRowHeight(24);

  // ---- Header Row ----
  $headers = [
    'ลำดับ',
    'วันที่ทำรายการ',
    'เวลาทำรายการ',
    'คอร์ต/ห้อง',
    'ผู้จอง',
    'เบอร์โทร',
    'วันที่ใช้',
    'เวลาเริ่ม',
    'เวลาจบ',
    'จำนวนชม.',
    'ราคา/ชม.',
    'ส่วนลด (฿)',
    'โปรโมชั่น',
    'ส่วนลด %',
    'รวมเงิน',
    'สถานะ',
    'มีสลิป',
    'แพ็กเกจที่ใช้',
    'ชม.จากแพ็กเกจ',
    'สลิปแพ็กเกจ',
  ];
  $sheet->fromArray([$headers], null, 'A5');
  $headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'B71C1C']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
  ];
  $sheet->getStyle('A5:T5')->applyFromArray($headerStyle);
  // Package columns header — highlight differently
  $sheet->getStyle('R5:T5')->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1d4ed8']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
  ]);
  $sheet->getRowDimension(5)->setRowHeight(20);

  // ---- Data Rows ----
  $r = 6;
  $no = 1;
  $totalRevenue = 0;
  $totalDiscount = 0;
  $promoCount = 0;
  $slipCount = 0;
  $cancelCount = 0;

  foreach ($rows as $x) {
    $created = new DateTime($x['created_at']);
    $start = new DateTime($x['start_datetime']);
    $end = (clone $start)->modify('+' . $x['duration_hours'] . ' hour');
    $isVip = ($x['court_type'] === 'vip' || $x['is_vip'] == 1);
    $courtName = $isVip ? ($x['vip_room_name'] ?? 'ห้อง VIP') : 'คอร์ต ' . $x['court_no'];
    $isBooked = $x['status'] === 'booked';

    $promoLabel = '';
    if (!empty($x['promo_name'])) {
      $promoLabel = $x['promo_name'];
    } elseif (!empty($x['promo_code'])) {
      $promoLabel = $x['promo_code'];
    }

    $promoPercent = !empty($x['promotion_discount_percent']) ? $x['promotion_discount_percent'] . '%' : '';
    $hasSlip = !empty($x['payment_slip_path']) ? 'มีสลิป' : '-';

    // Package columns
    $pkgName    = $x['pkg_type_name'] ?? '';
    $pkgHoursTotal = (int)($x['pkg_hours_total'] ?? 0);
    $pkgHoursUsed  = (int)($x['pkg_hours_used']  ?? 0);
    $pkgUsedThis   = (int)($x['used_package_hours'] ?? 0);
    $pkgRemaining  = max(0, $pkgHoursTotal - $pkgHoursUsed);
    $pkgLabel   = $pkgName ? "{$pkgName} (ใช้จองนี้ {$pkgUsedThis} ชม., รวมใช้ {$pkgHoursUsed}/{$pkgHoursTotal} ชม., เหลือ {$pkgRemaining} ชม.)" : '';
    $pkgHoursLabel = $pkgName ? "{$pkgUsedThis} ชม." : '';
    $pkgSlip    = !empty($x['pkg_slip_path']) ? 'มีสลิปแพ็กเกจ' : ($pkgName ? 'ไม่มีสลิป' : '');

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
        (int) $x['duration_hours'],
        (float) $x['price_per_hour'],
        (float) $x['discount_amount'],
        $promoLabel,
        $promoPercent,
        (float) $x['total_amount'],
        $isBooked ? 'จองแล้ว' : 'ยกเลิก',
        $hasSlip,
        $pkgLabel,
        $pkgHoursLabel,
        $pkgSlip,
      ]
    ], null, 'A' . $r);

    // Zebra stripe
    if ($no % 2 === 0) {
      $sheet->getStyle("A{$r}:T{$r}")->applyFromArray([
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

    // สลิปจอง color
    if (!empty($x['payment_slip_path'])) {
      $sheet->getStyle('Q' . $r)->getFont()->getColor()->setRGB('7c3aed');
    }

    // สลิปแพ็กเกจ color
    if (!empty($x['pkg_slip_path'])) {
      $sheet->getStyle('T' . $r)->getFont()->getColor()->setRGB('1d4ed8');
    } elseif ($pkgName) {
      $sheet->getStyle('T' . $r)->getFont()->getColor()->setRGB('9ca3af');
    }

    if ($isBooked) {
      $totalRevenue += $x['total_amount'];
      $totalDiscount += $x['discount_amount'];
    }
    if (!empty($x['promotion_id']))
      $promoCount++;
    if (!empty($x['payment_slip_path']))
      $slipCount++;
    if (!$isBooked)
      $cancelCount++;

    $r++;
    $no++;
  }

  // ---- Summary ----
  $r += 1;
  $sheet->setCellValue('A' . $r, 'สรุป');
  $sheet->getStyle('A' . $r)->applyFromArray([
    'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D32F2F']],
  ]);
  $r++;
  $pkgBookingCount = count(array_filter($rows, fn($x) => !empty($x['member_badminton_package_id'])));
  $pkgSlipCount    = count(array_filter($rows, fn($x) => !empty($x['pkg_slip_path'])));
  $summaryData = [
    ['จำนวนรายการทั้งหมด', count($rows) . ' รายการ'],
    ['รายการที่จองสำเร็จ', (count($rows) - $cancelCount) . ' รายการ'],
    ['รายการที่ยกเลิก', $cancelCount . ' รายการ'],
    ['ใช้โปรโมชั่น', $promoCount . ' รายการ'],
    ['มีสลิปแนบ', $slipCount . ' รายการ'],
    ['จองด้วยแพ็กเกจ', $pkgBookingCount . ' รายการ'],
    ['มีสลิปแพ็กเกจ', $pkgSlipCount . ' รายการ'],
    ['ยอดส่วนลดรวม', '฿' . number_format($totalDiscount, 2)],
    ['รายได้รวมทั้งหมด', '฿' . number_format($totalRevenue, 2)],
  ];
  foreach ($summaryData as $sd) {
    $sheet->fromArray([$sd], null, 'A' . $r);
    $sheet->getStyle('A' . $r)->getFont()->setBold(true);
    $r++;
  }

  // ---- Column Widths ----
  $widths = [8, 14, 12, 22, 18, 14, 12, 10, 10, 10, 12, 13, 20, 10, 13, 12, 10, 40, 14, 14];
  $cols = array_merge(range('A', 'Z'), ['AA','AB','AC','AD']);
  foreach ($cols as $i => $col) {
    if (!isset($widths[$i])) break;
    $sheet->getColumnDimension($col)->setWidth($widths[$i]);
  }

  // Center align cols
  foreach (['A', 'H', 'I', 'J', 'P', 'Q', 'S', 'T'] as $col) {
    $sheet->getStyle($col . '6:' . $col . ($r - 1))
      ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
  }

  // ============================================================
  // SHEET 2 — ยอดซื้อแพ็กเกจแบดมินตัน
  // ============================================================
  $pkgStmt = $pdo->prepare("
      SELECT mbp.*,
             bpt.name AS pkg_type_name,
             bpt.price AS pkg_price,
             bpt.hours_total AS pkg_type_hours,
             bpt.bonus_hours
      FROM member_badminton_packages mbp
      JOIN badminton_package_types bpt ON mbp.badminton_package_type_id = bpt.id
      WHERE mbp.purchase_date BETWEEN :f AND :t
      ORDER BY mbp.purchase_date DESC, mbp.created_at DESC
  ");
  $pkgStmt->execute([':f' => $from, ':t' => $to]);
  $pkgRows = $pkgStmt->fetchAll();

  $sheet2 = $spreadsheet->createSheet();
  $sheet2->setTitle('ยอดซื้อแพ็กเกจ');

  // Title
  $sheet2->setCellValue('A1', 'รายงานยอดซื้อแพ็กเกจแบดมินตัน BARGAIN SPORT');
  $sheet2->setCellValue('A2', 'ช่วงเวลา: ' . date('d/m/Y', strtotime($from)) . ' ถึง ' . date('d/m/Y', strtotime($to)));
  $sheet2->setCellValue('A3', 'ออกรายงานเมื่อ: ' . date('d/m/Y H:i:s'));
  $sheet2->mergeCells('A1:K1');
  $sheet2->mergeCells('A2:K2');
  $sheet2->mergeCells('A3:K3');
  $sheet2->getStyle('A1')->applyFromArray($titleStyle);
  $sheet2->getStyle('A2:A3')->applyFromArray($subStyle);
  $sheet2->getRowDimension(1)->setRowHeight(24);

  // Header
  $pkgHeaders = ['ลำดับ', 'วันที่ซื้อ', 'ชื่อสมาชิก', 'เบอร์โทร', 'แพ็กเกจ', 'ชม.รวม', 'ราคาแพ็กเกจ (฿)', 'วันหมดอายุ', 'สถานะ', 'สลิปชำระ', 'หมายเหตุ'];
  $sheet2->fromArray([$pkgHeaders], null, 'A5');
  $pkgHeaderStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1d4ed8']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
  ];
  $sheet2->getStyle('A5:K5')->applyFromArray($pkgHeaderStyle);
  $sheet2->getRowDimension(5)->setRowHeight(20);

  // Data
  $pr = 6;
  $pno = 1;
  $totalPkgRevenue = 0;
  $statusMap = ['active' => 'กำลังใช้งาน', 'expired' => 'หมดอายุ', 'exhausted' => 'ใช้หมดแล้ว'];
  foreach ($pkgRows as $pk) {
    $hoursLabel = $pk['hours_total'] . ' ชม.';
    if ($pk['bonus_hours'] > 0) {
      $hoursLabel = ($pk['pkg_type_hours'] ?? $pk['hours_total']) . '+' . $pk['bonus_hours'] . ' ชม. (รวม ' . $pk['hours_total'] . ')';
    }
    $statusLabel = $statusMap[$pk['status']] ?? $pk['status'];
    $hasSlip = !empty($pk['payment_slip_path']) ? 'มีสลิป' : 'ไม่มีสลิป';
    $expiry = !empty($pk['expiry_date']) ? date('d/m/Y', strtotime($pk['expiry_date'])) : 'ไม่กำหนด';
    $price = (float)$pk['pkg_price'];

    $sheet2->fromArray([[
      $pno,
      date('d/m/Y', strtotime($pk['purchase_date'])),
      $pk['customer_name'],
      $pk['customer_phone'],
      $pk['pkg_type_name'],
      $hoursLabel,
      $price,
      $expiry,
      $statusLabel,
      $hasSlip,
      $pk['notes'] ?? '',
    ]], null, 'A' . $pr);

    // Number format for price column
    $sheet2->getStyle('G' . $pr)->getNumberFormat()->setFormatCode('#,##0.00');

    // Zebra
    if ($pno % 2 === 0) {
      $sheet2->getStyle("A{$pr}:K{$pr}")->applyFromArray([
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EFF6FF']],
      ]);
    }

    // Status color
    $statusColors = ['กำลังใช้งาน' => '15803d', 'หมดอายุ' => '9ca3af', 'ใช้หมดแล้ว' => 'dc2626'];
    if (isset($statusColors[$statusLabel])) {
      $sheet2->getStyle('I' . $pr)->getFont()->getColor()->setRGB($statusColors[$statusLabel]);
    }
    // Slip color
    if (!empty($pk['payment_slip_path'])) {
      $sheet2->getStyle('J' . $pr)->getFont()->getColor()->setRGB('1d4ed8');
    } else {
      $sheet2->getStyle('J' . $pr)->getFont()->getColor()->setRGB('9ca3af');
    }

    $totalPkgRevenue += $price;
    $pr++;
    $pno++;
  }

  // Summary
  $pr += 1;
  $sheet2->setCellValue('A' . $pr, 'สรุปยอดซื้อแพ็กเกจ');
  $sheet2->getStyle('A' . $pr)->applyFromArray([
    'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1d4ed8']],
  ]);
  $pr++;
  $pkgSummary = [
    ['จำนวนแพ็กเกจที่ขาย', count($pkgRows) . ' แพ็กเกจ'],
    ['ยอดรายได้จากแพ็กเกจ', '฿' . number_format($totalPkgRevenue, 2)],
  ];
  foreach ($pkgSummary as $sd) {
    $sheet2->fromArray([$sd], null, 'A' . $pr);
    $sheet2->getStyle('A' . $pr)->getFont()->setBold(true);
    $pr++;
  }

  // Column widths for sheet 2
  $pkgWidths = [8, 14, 22, 16, 22, 18, 20, 16, 16, 14, 30];
  foreach ($pkgWidths as $i => $w) {
    $col = chr(65 + $i);
    $sheet2->getColumnDimension($col)->setWidth($w);
  }
  foreach (['A', 'B', 'F', 'G', 'H', 'I', 'J'] as $col) {
    $sheet2->getStyle($col . '6:' . $col . ($pr - 1))
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
           p.name AS promo_name, p.code AS promo_code,
           bpt.name AS pkg_type_name,
           mbp.hours_total AS pkg_hours_total, mbp.hours_used AS pkg_hours_used,
           mbp.payment_slip_path AS pkg_slip_path
    FROM bookings b
    JOIN courts c ON b.court_id = c.id
    LEFT JOIN promotions p ON b.promotion_id = p.id
    LEFT JOIN member_badminton_packages mbp ON b.member_badminton_package_id = mbp.id
    LEFT JOIN badminton_package_types bpt ON mbp.badminton_package_type_id = bpt.id
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

// Package purchases stats
$pkgStatsStmt = $pdo->prepare("
    SELECT
        COUNT(*) AS pkg_count,
        SUM(bpt.price) AS pkg_revenue,
        SUM(CASE WHEN mbp.payment_slip_path IS NOT NULL THEN 1 ELSE 0 END) AS pkg_slip_count
    FROM member_badminton_packages mbp
    JOIN badminton_package_types bpt ON mbp.badminton_package_type_id = bpt.id
    WHERE mbp.purchase_date BETWEEN :f AND :t
");
$pkgStatsStmt->execute([':f' => $from, ':t' => $to]);
$pkgStats = $pkgStatsStmt->fetch();

// Package preview rows (10 latest)
$pkgPreviewStmt = $pdo->prepare("
    SELECT mbp.*,
           bpt.name AS pkg_type_name,
           bpt.price AS pkg_price,
           bpt.hours_total AS pkg_type_hours,
           bpt.bonus_hours
    FROM member_badminton_packages mbp
    JOIN badminton_package_types bpt ON mbp.badminton_package_type_id = bpt.id
    WHERE mbp.purchase_date BETWEEN :f AND :t
    ORDER BY mbp.purchase_date DESC, mbp.created_at DESC
    LIMIT 10
");
$pkgPreviewStmt->execute([':f' => $from, ':t' => $to]);
$pkgPreviewRows = $pkgPreviewStmt->fetchAll();
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
  <?php include __DIR__ . '/../includes/header.php'; ?>

  <div class="max-w-7xl mx-auto px-4 py-8">

    <!-- Header -->
    <div class="mb-6">
      <h1 style="color:#D32F2F;" class="text-2xl font-bold mb-1">ออกรายงาน Excel</h1>
      <p class="text-gray-500 text-sm">ดาวน์โหลดรายงานการจองพร้อมข้อมูลโปรโมชั่นและสลิปในรูปแบบ Excel</p>
    </div>

    <!-- Export Options -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">

      <form method="get" class="bg-white rounded-xl border border-gray-200 p-6">
        <div class="mb-4">
          <h3 style="color:#D32F2F;" class="text-lg font-bold mb-1">ข้อมูลวันนี้</h3>
          <p class="text-sm text-gray-600">Export การจองวันที่ <?= date('d/m/Y') ?></p>
        </div>
        <input type="hidden" name="type" value="today">
        <button type="submit" name="download" value="1" style="background:#B71C1C;"
          class="w-full px-4 py-2.5 text-white rounded-lg font-medium hover:opacity-90 transition-opacity text-sm">
          ดาวน์โหลด Excel
        </button>
      </form>

      <form method="get" class="bg-white rounded-xl border border-gray-200 p-6">
        <div class="mb-4">
          <h3 style="color:#D32F2F;" class="text-lg font-bold mb-1">ข้อมูลทั้งหมด</h3>
          <p class="text-sm text-gray-600">Export การจองทั้งหมดตั้งแต่เริ่มต้น</p>
        </div>
        <input type="hidden" name="type" value="all">
        <button type="submit" name="download" value="1" style="background:#B71C1C;"
          class="w-full px-4 py-2.5 text-white rounded-lg font-medium hover:opacity-90 transition-opacity text-sm">
          ดาวน์โหลด Excel
        </button>
      </form>

      <form method="get" class="bg-white rounded-xl border border-gray-200 p-6">
        <div class="mb-4">
          <h3 style="color:#D32F2F;" class="text-lg font-bold mb-1">ช่วงเวลาที่กำหนด</h3>
          <p class="text-sm text-gray-600 mb-3">Export ตามช่วงเวลาที่เลือก</p>
        </div>
        <div class="space-y-3 mb-4">
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">วันที่เริ่มต้น</label>
            <input type="date" name="from" value="<?= htmlspecialchars($from) ?>"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-[#D32F2F] outline-none text-sm">
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">วันที่สิ้นสุด</label>
            <input type="date" name="to" value="<?= htmlspecialchars($to) ?>"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-[#D32F2F] outline-none text-sm">
          </div>
        </div>
        <input type="hidden" name="type" value="range">
        <button type="submit" name="download" value="1" style="background:#B71C1C;"
          class="w-full px-4 py-2.5 text-white rounded-lg font-medium hover:opacity-90 transition-opacity text-sm">
          ดาวน์โหลด Excel
        </button>
      </form>
    </div>

    <!-- Stats Cards — การจอง -->
    <div class="mb-2">
      <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-2">สรุปการจองคอร์ต</h2>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-4">
      <div class="bg-white rounded-xl border border-gray-200 p-4">
        <p class="text-gray-400 text-xs mb-1">รายการทั้งหมด</p>
        <p style="color:#D32F2F;" class="text-2xl font-bold"><?= number_format($stats['total']) ?></p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-4">
        <p class="text-gray-400 text-xs mb-1">รายได้จองคอร์ต</p>
        <p style="color:#B71C1C;" class="text-2xl font-bold">฿<?= number_format($stats['revenue'] ?? 0, 0) ?></p>
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

    <!-- Stats Cards — ยอดซื้อแพ็กเกจ -->
    <div class="mb-2">
      <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-2">สรุปยอดซื้อแพ็กเกจแบดมินตัน</h2>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-6">
      <div class="bg-white rounded-xl border-2 border-blue-200 p-4">
        <p class="text-blue-400 text-xs mb-1">แพ็กเกจที่ขายได้</p>
        <p class="text-2xl font-bold text-blue-700"><?= number_format($pkgStats['pkg_count'] ?? 0) ?> แพ็กเกจ</p>
      </div>
      <div class="bg-white rounded-xl border-2 border-blue-200 p-4">
        <p class="text-blue-400 text-xs mb-1">รายได้จากแพ็กเกจ</p>
        <p class="text-2xl font-bold text-blue-700">฿<?= number_format($pkgStats['pkg_revenue'] ?? 0, 0) ?></p>
        <p class="text-xs text-gray-400 mt-1">ชำระครั้งเดียว</p>
      </div>
      <div class="bg-white rounded-xl border-2 border-blue-200 p-4">
        <p class="text-blue-400 text-xs mb-1">มีสลิปชำระแพ็กเกจ</p>
        <p class="text-2xl font-bold text-blue-700"><?= number_format($pkgStats['pkg_slip_count'] ?? 0) ?></p>
        <p class="text-xs text-gray-400 mt-1">จาก <?= $pkgStats['pkg_count'] ?? 0 ?> แพ็กเกจ</p>
      </div>
    </div>

    <!-- Preview Section -->
    <?php if (count($previewRows) > 0): ?>
      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden mb-6">
        <div style="background:#D32F2F;" class="px-6 py-4 flex justify-between items-center">
          <h2 class="text-sm font-bold text-white">ตัวอย่างข้อมูล (10 รายการแรก)</h2>
          <span class="text-blue-200 text-xs">คอลัมน์ Excel: ลำดับ · วันที่ · คอร์ต · ผู้จอง · เบอร์ · วันใช้ · เวลา · ชม.
            · ราคา · ส่วนลด · โปรโมชั่น · % · รวม · สถานะ · สลิป</span>
        </div>

        <!-- Desktop -->
        <div class="hidden lg:block overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr style="background:#B71C1C;" class="text-white text-xs">
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
                <th class="px-3 py-3 text-center font-medium">สลิปจอง</th>
                <th class="px-3 py-3 text-center font-medium" style="background:#1d4ed8;">แพ็กเกจที่ใช้</th>
                <th class="px-3 py-3 text-center font-medium" style="background:#1d4ed8;">ชม.แพ็กเกจ</th>
                <th class="px-3 py-3 text-center font-medium" style="background:#1d4ed8;">สลิปแพ็กเกจ</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <?php foreach ($previewRows as $index => $x):
                $created = new DateTime($x['created_at']);
                $start = new DateTime($x['start_datetime']);
                $end = (clone $start)->modify('+' . $x['duration_hours'] . ' hour');
                $isVip = ($x['court_type'] === 'vip' || $x['is_vip'] == 1);
                $courtName = $isVip ? ($x['vip_room_name'] ?? 'ห้อง VIP') : 'คอร์ต ' . $x['court_no'];
                $isBooked = $x['status'] === 'booked';
                $promoLabel = !empty($x['promo_name']) ? $x['promo_name'] : (!empty($x['promo_code']) ? $x['promo_code'] : '');
                $pkgName = $x['pkg_type_name'] ?? '';
                $pkgTotal = (int)($x['pkg_hours_total'] ?? 0);
                $pkgUsed  = (int)($x['pkg_hours_used']  ?? 0);
                $pkgUsedThis = (int)($x['used_package_hours'] ?? 0);
                $pkgRemain = max(0, $pkgTotal - $pkgUsed);
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
                  <td class="px-3 py-3 text-center text-gray-600 text-xs">
                    <?= $start->format('H:i') ?>-<?= $end->format('H:i') ?></td>
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
                  <td style="color:#B71C1C;" class="px-3 py-3 text-right font-bold text-xs">
                    ฿<?= number_format($x['total_amount'], 0) ?></td>
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
                  <!-- Package columns -->
                  <td class="px-3 py-3 text-xs" style="background:#eff6ff;">
                    <?php if ($pkgName): ?>
                      <div class="font-medium text-blue-700"><?= htmlspecialchars($pkgName) ?></div>
                      <div class="text-[10px] text-blue-400">ใช้ไป <?= $pkgUsed ?>/<?= $pkgTotal ?> ชม. · เหลือ <span class="<?= $pkgRemain <= 2 ? 'text-red-500 font-bold' : 'text-green-600 font-bold' ?>"><?= $pkgRemain ?></span> ชม.</div>
                    <?php else: ?>
                      <span class="text-gray-300">-</span>
                    <?php endif; ?>
                  </td>
                  <td class="px-3 py-3 text-center text-xs" style="background:#eff6ff;">
                    <?php if ($pkgName): ?>
                      <span class="font-semibold text-blue-700"><?= $pkgUsedThis ?> ชม.</span>
                    <?php else: ?>
                      <span class="text-gray-300">-</span>
                    <?php endif; ?>
                  </td>
                  <td class="px-3 py-3 text-center text-xs" style="background:#eff6ff;">
                    <?php if (!empty($x['pkg_slip_path'])): ?>
                      <span class="text-blue-600 font-medium">✓ มีสลิป</span>
                    <?php elseif ($pkgName): ?>
                      <span class="text-gray-400">ไม่มีสลิป</span>
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
            $created = new DateTime($x['created_at']);
            $start = new DateTime($x['start_datetime']);
            $end = (clone $start)->modify('+' . $x['duration_hours'] . ' hour');
            $isVip = ($x['court_type'] === 'vip' || $x['is_vip'] == 1);
            $courtName = $isVip ? ($x['vip_room_name'] ?? 'ห้อง VIP') : 'คอร์ต ' . $x['court_no'];
            $isBooked = $x['status'] === 'booked';
            $promoLabel = !empty($x['promo_name']) ? $x['promo_name'] : (!empty($x['promo_code']) ? $x['promo_code'] : '');
            ?>
            <div class="p-4">
              <div class="flex justify-between items-start mb-2">
                <div class="flex-1">
                  <div class="font-bold text-gray-800 mb-0.5"><?= htmlspecialchars($x['customer_name']) ?></div>
                  <div class="text-sm text-gray-600 mb-0.5"><?= htmlspecialchars($courtName) ?></div>
                  <div class="text-xs text-gray-400"><?= $created->format('d/m/Y H:i') ?> ·
                    <?= htmlspecialchars($x['customer_phone']) ?></div>
                </div>
                <div class="text-right">
                  <div style="color:#B71C1C;" class="text-lg font-bold">฿<?= number_format($x['total_amount'], 0) ?></div>
                  <span
                    class="text-[10px] px-2 py-0.5 rounded-full <?= $isBooked ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
                    <?= $isBooked ? 'จองแล้ว' : 'ยกเลิก' ?>
                  </span>
                </div>
              </div>
              <div class="flex flex-wrap gap-2 text-xs text-gray-500 border-t pt-2 mt-2">
                <span><?= $start->format('d/m/Y') ?>     <?= $start->format('H:i') ?>-<?= $end->format('H:i') ?></span>
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
            <p class="text-xs text-gray-500">แสดง 10 รายการแรก — ดาวน์โหลด Excel
              เพื่อดูข้อมูลทั้งหมดพร้อมคอลัมน์โปรโมชั่นและสลิป</p>
          </div>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
        <h3 class="text-xl font-bold text-gray-800 mb-2">ไม่พบข้อมูลการจอง</h3>
        <p class="text-gray-500">ไม่มีรายการจองในช่วงเวลาที่เลือก</p>
      </div>
    <?php endif; ?>

    <!-- Package Purchases Preview -->
    <div class="mt-6">
      <h2 style="color:#1d4ed8;" class="text-lg font-bold mb-3">ยอดซื้อแพ็กเกจแบดมินตัน (ชำระครั้งเดียว)</h2>
      <?php if (count($pkgPreviewRows) > 0): ?>
        <div class="bg-white rounded-xl border-2 border-blue-200 overflow-hidden mb-6">
          <div style="background:#1d4ed8;" class="px-6 py-4 flex justify-between items-center">
            <h2 class="text-sm font-bold text-white">ตัวอย่างยอดซื้อแพ็กเกจ (10 รายการล่าสุด)</h2>
            <span class="text-blue-200 text-xs">Excel Sheet 2: ยอดซื้อแพ็กเกจ</span>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr style="background:#1e40af;" class="text-white text-xs">
                  <th class="px-3 py-3 text-center font-medium">#</th>
                  <th class="px-3 py-3 text-left font-medium">วันที่ซื้อ</th>
                  <th class="px-3 py-3 text-left font-medium">ชื่อสมาชิก</th>
                  <th class="px-3 py-3 text-center font-medium">เบอร์โทร</th>
                  <th class="px-3 py-3 text-left font-medium">แพ็กเกจ</th>
                  <th class="px-3 py-3 text-center font-medium">ชม.รวม</th>
                  <th class="px-3 py-3 text-right font-medium">ราคา (฿)</th>
                  <th class="px-3 py-3 text-center font-medium">ชม.ที่ใช้แล้ว</th>
                  <th class="px-3 py-3 text-center font-medium">วันหมดอายุ</th>
                  <th class="px-3 py-3 text-center font-medium">สถานะ</th>
                  <th class="px-3 py-3 text-center font-medium">สลิปชำระ</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-blue-100">
                <?php
                $pkgStatusMap = ['active' => ['label' => 'กำลังใช้งาน', 'class' => 'bg-green-100 text-green-700'],
                                 'expired' => ['label' => 'หมดอายุ', 'class' => 'bg-gray-100 text-gray-500'],
                                 'exhausted' => ['label' => 'ใช้หมดแล้ว', 'class' => 'bg-red-100 text-red-600']];
                foreach ($pkgPreviewRows as $pidx => $pk):
                  $hoursLabel = $pk['hours_total'] . ' ชม.';
                  if ($pk['bonus_hours'] > 0) {
                    $hoursLabel = ($pk['pkg_type_hours'] ?? $pk['hours_total']) . '+' . $pk['bonus_hours'] . ' (รวม ' . $pk['hours_total'] . ')';
                  }
                  $stInfo = $pkgStatusMap[$pk['status']] ?? ['label' => $pk['status'], 'class' => 'bg-gray-100 text-gray-500'];
                  $expiry = !empty($pk['expiry_date']) ? date('d/m/Y', strtotime($pk['expiry_date'])) : 'ไม่กำหนด';
                  $remaining = max(0, $pk['hours_total'] - $pk['hours_used']);
                ?>
                  <tr class="hover:bg-blue-50 transition-colors <?= $pidx % 2 === 1 ? 'bg-blue-50/40' : '' ?>">
                    <td class="px-3 py-3 text-center text-gray-500 text-xs"><?= $pidx + 1 ?></td>
                    <td class="px-3 py-3 text-xs">
                      <div class="font-medium text-gray-800"><?= date('d/m/Y', strtotime($pk['purchase_date'])) ?></div>
                    </td>
                    <td class="px-3 py-3 text-gray-800 text-xs font-medium"><?= htmlspecialchars($pk['customer_name']) ?></td>
                    <td class="px-3 py-3 text-center text-gray-600 text-xs"><?= htmlspecialchars($pk['customer_phone']) ?></td>
                    <td class="px-3 py-3 text-xs">
                      <span class="bg-blue-100 text-blue-700 px-2 py-0.5 rounded font-medium"><?= htmlspecialchars($pk['pkg_type_name']) ?></span>
                    </td>
                    <td class="px-3 py-3 text-center text-xs text-gray-700 font-medium"><?= $hoursLabel ?></td>
                    <td class="px-3 py-3 text-right text-xs">
                      <span class="font-bold text-blue-700 text-sm">฿<?= number_format($pk['pkg_price'], 0) ?></span>
                      <div class="text-[10px] text-gray-400">ชำระครั้งเดียว</div>
                    </td>
                    <td class="px-3 py-3 text-center text-xs">
                      <div class="text-gray-700"><?= $pk['hours_used'] ?>/<?= $pk['hours_total'] ?> ชม.</div>
                      <div class="text-[10px] <?= $remaining <= 2 ? 'text-red-500 font-bold' : 'text-green-600' ?>">เหลือ <?= $remaining ?> ชม.</div>
                    </td>
                    <td class="px-3 py-3 text-center text-xs text-gray-500"><?= $expiry ?></td>
                    <td class="px-3 py-3 text-center">
                      <span class="text-[10px] px-2 py-0.5 rounded-full <?= $stInfo['class'] ?>"><?= $stInfo['label'] ?></span>
                    </td>
                    <td class="px-3 py-3 text-center text-xs">
                      <?php if (!empty($pk['payment_slip_path'])): ?>
                        <span class="text-blue-600 font-medium">✓ มีสลิป</span>
                      <?php else: ?>
                        <span class="text-gray-300">-</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php if (count($pkgPreviewRows) >= 10): ?>
            <div class="bg-blue-50 border-t border-blue-200 px-6 py-3 text-center">
              <p class="text-xs text-blue-500">แสดง 10 รายการล่าสุด — ดาวน์โหลด Excel เพื่อดูข้อมูลทั้งหมด (Sheet 2)</p>
            </div>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="bg-white rounded-xl border-2 border-blue-200 p-8 text-center">
          <p class="text-blue-400">ไม่มียอดซื้อแพ็กเกจในช่วงเวลาที่เลือก</p>
        </div>
      <?php endif; ?>
    </div>

  </div>

  <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>

</html>