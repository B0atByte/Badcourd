<?php
require_once __DIR__ . '/../auth/guard.php';
require_permission('reports');
require_once __DIR__ . '/../config/db.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

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

// ──────────────────────────────────────────────────────────────────────────
// HELPER: สไตล์หัวตาราง
// ──────────────────────────────────────────────────────────────────────────
function headerStyle(string $colorHex = 'D32F2F'): array
{
    return [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colorHex]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
    ];
}

function zebraStyle(): array
{
    return ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0F9FF']]];
}

function titleBlockStyle(): array
{
    return [
        'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D32F2F']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ];
}

function subBlockStyle(): array
{
    return [
        'font' => ['size' => 10, 'color' => ['rgb' => 'B71C1C']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8F4FE']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ];
}

// ──────────────────────────────────────────────────────────────────────────
// EXCEL DOWNLOAD
// ──────────────────────────────────────────────────────────────────────────
if (isset($_GET['download'])) {

    $spreadsheet = new Spreadsheet();
    $spreadsheet->getProperties()
        ->setTitle('BARGAIN SPORT - รายงานโยคะ')
        ->setCreator('BARGAIN SPORT System');

    // ══════════════════════════════════════════════════════════════════════
    // SHEET 1 — บันทึกการเรียน (Yoga Attendance)
    // ══════════════════════════════════════════════════════════════════════
    $sh1 = $spreadsheet->getActiveSheet();
    $sh1->setTitle('บันทึกการเรียน');

    // ---- Query: booking + course + package type ----
    $attStmt = $pdo->prepare("
        SELECT
            yb.id AS booking_id,
            yc.course_date,
            yc.start_time,
            yc.end_time,
            yc.room,
            yc.instructor,
            yb.student_name,
            yb.student_phone,
            yb.status,
            yb.attended_at,
            ypt.name AS package_type_name,
            myp.sessions_total,
            myp.sessions_used,
            (myp.sessions_total - myp.sessions_used) AS remaining,
            myp.expiry_date,
            yb.created_at
        FROM yoga_bookings yb
        JOIN yoga_courses yc ON yc.id = yb.yoga_course_id
        LEFT JOIN member_yoga_packages myp ON myp.id = yb.member_package_id
        LEFT JOIN yoga_package_types ypt ON ypt.id = myp.yoga_package_type_id
        WHERE yc.course_date BETWEEN :f AND :t
          AND yb.status != 'cancelled'
        ORDER BY yc.course_date ASC, yc.start_time ASC, yb.created_at ASC
    ");
    $attStmt->execute([':f' => $from, ':t' => $to]);
    $attRows = $attStmt->fetchAll();

    // Title block
    $sh1->setCellValue('A1', 'รายงานบันทึกการเรียนคลาสโยคะ — BARGAIN SPORT');
    $sh1->setCellValue('A2', 'ช่วงเวลา: ' . date('d/m/Y', strtotime($from)) . ' ถึง ' . date('d/m/Y', strtotime($to)));
    $sh1->setCellValue('A3', 'ออกรายงานเมื่อ: ' . date('d/m/Y H:i:s'));
    foreach (['A1', 'A2', 'A3'] as $cell) {
        $sh1->mergeCells($cell . ':L' . substr($cell, 1));
    }
    $sh1->getStyle('A1')->applyFromArray(titleBlockStyle());
    $sh1->getStyle('A2:A3')->applyFromArray(subBlockStyle());
    $sh1->getRowDimension(1)->setRowHeight(24);

    // Headers
    $attHeaders = [
        '#',
        'วันที่เรียน',
        'เวลา',
        'ห้อง',
        'ครูผู้สอน',
        'ชื่อนักเรียน',
        'เบอร์โทร',
        'แพ็กเกจ',
        'ครั้งที่ใช้/ทั้งหมด',
        'เหลือ',
        'หมดอายุ',
        'สถานะ'
    ];
    $sh1->fromArray([$attHeaders], null, 'A5');
    $sh1->getStyle('A5:L5')->applyFromArray(headerStyle('D32F2F'));
    $sh1->getRowDimension(5)->setRowHeight(22);

    // Data
    $r = 6;
    $no = 1;
    $totalAttended = 0;
    $totalBooked = 0;
    foreach ($attRows as $x) {
        $statusTh = match ($x['status']) {
            'attended' => 'เช็คแล้ว ✓',
            'booked' => 'ยังไม่เช็ค',
            default => $x['status'],
        };
        $timeRange = substr($x['start_time'], 0, 5) . '–' . substr($x['end_time'], 0, 5);
        $pkgLabel = $x['package_type_name'] ?? '— ไม่ใช้แพ็กเกจ —';
        $usageLabel = $x['sessions_total'] ? $x['sessions_used'] . '/' . $x['sessions_total'] : '–';
        $remainLabel = $x['sessions_total'] ? (string) max(0, (int) $x['remaining']) : '–';
        $expiryLabel = $x['expiry_date'] ? date('d/m/Y', strtotime($x['expiry_date'])) : '–';
        $attendedLabel = $x['attended_at'] ? date('d/m/Y H:i', strtotime($x['attended_at'])) : '–';

        $sh1->fromArray([
            [
                $no,
                date('d/m/Y', strtotime($x['course_date'])),
                $timeRange,
                $x['room'],
                $x['instructor'],
                $x['student_name'],
                $x['student_phone'],
                $pkgLabel,
                $usageLabel,
                $remainLabel,
                $expiryLabel,
                $statusTh,
            ]
        ], null, 'A' . $r);

        // Zebra
        if ($no % 2 === 0)
            $sh1->getStyle("A{$r}:L{$r}")->applyFromArray(zebraStyle());

        // Status color
        $statusCell = 'L' . $r;
        if ($x['status'] === 'attended') {
            $sh1->getStyle($statusCell)->getFont()->getColor()->setRGB('15803d');
        } else {
            $sh1->getStyle($statusCell)->getFont()->getColor()->setRGB('d97706');
        }

        // Low remaining warning
        if ($x['sessions_total'] && (int) $x['remaining'] <= 2) {
            $sh1->getStyle('J' . $r)->getFont()->getColor()->setRGB('dc2626');
        }

        if ($x['status'] === 'attended')
            $totalAttended++;
        else
            $totalBooked++;
        $r++;
        $no++;
    }

    // Summary
    $r++;
    $sh1->setCellValue('A' . $r, 'สรุป');
    $sh1->getStyle('A' . $r)->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D32F2F']]]);
    $r++;
    foreach ([
        ['รายการทั้งหมด', count($attRows) . ' รายการ'],
        ['เช็คชื่อแล้ว', $totalAttended . ' รายการ'],
        ['ยังไม่เช็ค', $totalBooked . ' รายการ'],
    ] as $sd) {
        $sh1->fromArray([$sd], null, 'A' . $r);
        $sh1->getStyle('A' . $r)->getFont()->setBold(true);
        $r++;
    }

    // Column widths
    foreach (['A' => 6, 'B' => 12, 'C' => 12, 'D' => 12, 'E' => 14, 'F' => 18, 'G' => 14, 'H' => 22, 'I' => 14, 'J' => 8, 'K' => 12, 'L' => 14] as $col => $w) {
        $sh1->getColumnDimension($col)->setWidth($w);
    }

    // ══════════════════════════════════════════════════════════════════════
    // SHEET 2 — การขายแพ็กเกจ (Package Sales)
    // ══════════════════════════════════════════════════════════════════════
    $sh2 = $spreadsheet->createSheet();
    $sh2->setTitle('การขายแพ็กเกจ');

    $pkgStmt = $pdo->prepare("
        SELECT
            myp.id,
            myp.student_name,
            myp.student_phone,
            ypt.name AS package_name,
            ypt.price,
            myp.sessions_total,
            myp.sessions_used,
            (myp.sessions_total - myp.sessions_used) AS remaining,
            myp.purchase_date,
            myp.expiry_date,
            myp.notes,
            myp.created_at,
            CASE
                WHEN myp.expiry_date IS NOT NULL AND myp.expiry_date < CURDATE() THEN 'หมดอายุ'
                WHEN (myp.sessions_total - myp.sessions_used) <= 0 THEN 'หมดครั้ง'
                ELSE 'ใช้งานได้'
            END AS pkg_status
        FROM member_yoga_packages myp
        JOIN yoga_package_types ypt ON ypt.id = myp.yoga_package_type_id
        WHERE myp.purchase_date BETWEEN :f AND :t
        ORDER BY myp.purchase_date ASC, myp.created_at ASC
    ");
    $pkgStmt->execute([':f' => $from, ':t' => $to]);
    $pkgRows = $pkgStmt->fetchAll();

    // Title block
    $sh2->setCellValue('A1', 'รายงานการขายแพ็กเกจโยคะ — BARGAIN SPORT');
    $sh2->setCellValue('A2', 'ช่วงเวลา: ' . date('d/m/Y', strtotime($from)) . ' ถึง ' . date('d/m/Y', strtotime($to)));
    $sh2->setCellValue('A3', 'ออกรายงานเมื่อ: ' . date('d/m/Y H:i:s'));
    foreach (['A1', 'A2', 'A3'] as $cell) {
        $sh2->mergeCells($cell . ':L' . substr($cell, 1));
    }
    $sh2->getStyle('A1')->applyFromArray(titleBlockStyle());
    $sh2->getStyle('A2:A3')->applyFromArray(subBlockStyle());
    $sh2->getRowDimension(1)->setRowHeight(24);

    // Headers
    $pkgHeaders = [
        '#',
        'วันซื้อ',
        'ชื่อนักเรียน',
        'เบอร์โทร',
        'ประเภทแพ็กเกจ',
        'ราคา (฿)',
        'ครั้งทั้งหมด',
        'ใช้ไปแล้ว',
        'เหลือ',
        'วันหมดอายุ',
        'หมายเหตุ',
        'สถานะ'
    ];
    $sh2->fromArray([$pkgHeaders], null, 'A5');
    $sh2->getStyle('A5:L5')->applyFromArray(headerStyle('B71C1C'));
    $sh2->getRowDimension(5)->setRowHeight(22);

    // Data
    $r = 6;
    $no = 1;
    $totalRevenue = 0;
    foreach ($pkgRows as $x) {
        $expiryLabel = $x['expiry_date'] ? date('d/m/Y', strtotime($x['expiry_date'])) : 'ไม่มีวันหมดอายุ';

        $sh2->fromArray([
            [
                $no,
                date('d/m/Y', strtotime($x['purchase_date'])),
                $x['student_name'],
                $x['student_phone'],
                $x['package_name'],
                (float) $x['price'],
                (int) $x['sessions_total'],
                (int) $x['sessions_used'],
                (int) $x['remaining'],
                $expiryLabel,
                $x['notes'] ?? '',
                $x['pkg_status'],
            ]
        ], null, 'A' . $r);

        // Zebra
        if ($no % 2 === 0)
            $sh2->getStyle("A{$r}:L{$r}")->applyFromArray(zebraStyle());

        // Status color
        $stCell = 'L' . $r;
        $sh2->getStyle($stCell)->getFont()->getColor()->setRGB(match ($x['pkg_status']) {
            'ใช้งานได้' => '15803d',
            'หมดอายุ' => '6b7280',
            'หมดครั้ง' => 'dc2626',
            default => '374151',
        });

        // Price format
        $sh2->getStyle('F' . $r)->getNumberFormat()->setFormatCode('#,##0.00');
        // Low remaining
        if ((int) $x['remaining'] <= 2 && (int) $x['remaining'] > 0) {
            $sh2->getStyle('I' . $r)->getFont()->getColor()->setRGB('d97706');
        }

        $totalRevenue += (float) $x['price'];
        $r++;
        $no++;
    }

    // Summary
    $r++;
    $sh2->setCellValue('A' . $r, 'สรุป');
    $sh2->getStyle('A' . $r)->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'B71C1C']]]);
    $r++;
    foreach ([
        ['จำนวนแพ็กเกจที่ซื้อ', count($pkgRows) . ' แพ็กเกจ'],
        ['รายได้จากแพ็กเกจรวม', '฿' . number_format($totalRevenue, 2)],
        ['ใช้งานได้', count(array_filter($pkgRows, fn($p) => $p['pkg_status'] === 'ใช้งานได้')) . ' แพ็กเกจ'],
        ['หมดอายุ/หมดครั้ง', count(array_filter($pkgRows, fn($p) => $p['pkg_status'] !== 'ใช้งานได้')) . ' แพ็กเกจ'],
    ] as $sd) {
        $sh2->fromArray([$sd], null, 'A' . $r);
        $sh2->getStyle('A' . $r)->getFont()->setBold(true);
        $r++;
    }

    // Column widths
    foreach (['A' => 6, 'B' => 12, 'C' => 18, 'D' => 14, 'E' => 22, 'F' => 12, 'G' => 12, 'H' => 12, 'I' => 8, 'J' => 14, 'K' => 18, 'L' => 12] as $col => $w) {
        $sh2->getColumnDimension($col)->setWidth($w);
    }

    // ── Set Sheet 1 active ──
    $spreadsheet->setActiveSheetIndex(0);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="BARGAIN_SPORT-Yoga-' . date('Y-m-d_H-i-s') . '.xlsx"');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// ──────────────────────────────────────────────────────────────────────────
// PAGE DATA (preview)
// ──────────────────────────────────────────────────────────────────────────
$previewStmt = $pdo->prepare("
    SELECT
        yb.id,
        yc.course_date,
        yc.start_time,
        yc.end_time,
        yc.room,
        yc.instructor,
        yb.student_name,
        yb.student_phone,
        yb.status,
        ypt.name AS package_type_name
    FROM yoga_bookings yb
    JOIN yoga_courses yc ON yc.id = yb.yoga_course_id
    LEFT JOIN member_yoga_packages myp ON myp.id = yb.member_package_id
    LEFT JOIN yoga_package_types ypt ON ypt.id = myp.yoga_package_type_id
    WHERE yc.course_date BETWEEN :f AND :t
      AND yb.status != 'cancelled'
    ORDER BY yc.course_date DESC, yc.start_time ASC
    LIMIT 10
");
$previewStmt->execute([':f' => $from, ':t' => $to]);
$previewRows = $previewStmt->fetchAll();

// Stats
$statsStmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total_bookings,
        SUM(CASE WHEN yb.status='attended' THEN 1 ELSE 0 END) AS attended,
        SUM(CASE WHEN yb.status='booked'   THEN 1 ELSE 0 END) AS pending,
        COUNT(DISTINCT yc.id)   AS total_courses,
        COUNT(DISTINCT yc.course_date) AS total_days
    FROM yoga_bookings yb
    JOIN yoga_courses yc ON yc.id = yb.yoga_course_id
    WHERE yc.course_date BETWEEN :f AND :t
      AND yb.status != 'cancelled'
");
$statsStmt->execute([':f' => $from, ':t' => $to]);
$stats = $statsStmt->fetch();

$pkgStatsStmt = $pdo->prepare("
    SELECT COUNT(*) AS sold, SUM(ypt.price) AS revenue
    FROM member_yoga_packages myp
    JOIN yoga_package_types ypt ON ypt.id = myp.yoga_package_type_id
    WHERE myp.purchase_date BETWEEN :f AND :t
");
$pkgStatsStmt->execute([':f' => $from, ':t' => $to]);
$pkgStats = $pkgStatsStmt->fetch();
?>
<!doctype html>
<html lang="th">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <title>ออกรายงาน Excel — คลาสโยคะ · BARGAIN SPORT</title>
    <style>
        * {
            font-family: 'Prompt', sans-serif !important;
        }
    </style>
</head>

<body style="background:#FAFAFA;" class="min-h-screen">
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="max-w-7xl mx-auto px-4 py-8">

        <!-- Header -->
        <div class="mb-6">
            <div class="flex items-center gap-2 mb-1">
                <a href="/reports/export_excel.php"
                    class="text-gray-400 hover:text-gray-600 text-sm transition-colors">รายงานแบดมินตัน</a>
                <span class="text-gray-300">/</span>
                <span class="text-sm font-medium" style="color:#D32F2F;">รายงานโยคะ</span>
            </div>
            <h1 style="color:#D32F2F;" class="text-2xl font-bold mb-1">ออกรายงาน Excel — คลาสโยคะ</h1>
            <p class="text-gray-500 text-sm">ดาวน์โหลดบันทึกการเรียนและข้อมูลการขายแพ็กเกจโยคะในรูปแบบ Excel (2 Sheet)
            </p>
        </div>

        <!-- Export Options -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">

            <form method="get" class="bg-white rounded-xl border border-gray-200 p-6">
                <div class="mb-4">
                    <h3 style="color:#D32F2F;" class="text-lg font-bold mb-1">ข้อมูลวันนี้</h3>
                    <p class="text-sm text-gray-600">Export คลาสวันที่
                        <?= date('d/m/Y') ?>
                    </p>
                </div>
                <input type="hidden" name="type" value="today">
                <button type="submit" name="download" value="1" style="background:#D32F2F;"
                    class="w-full px-4 py-2.5 text-white rounded-lg font-medium hover:opacity-90 transition-opacity text-sm">
                    ดาวน์โหลด Excel
                </button>
            </form>

            <form method="get" class="bg-white rounded-xl border border-gray-200 p-6">
                <div class="mb-4">
                    <h3 style="color:#D32F2F;" class="text-lg font-bold mb-1">ข้อมูลทั้งหมด</h3>
                    <p class="text-sm text-gray-600">Export ทุกคลาสและแพ็กเกจตั้งแต่เริ่มต้น</p>
                </div>
                <input type="hidden" name="type" value="all">
                <button type="submit" name="download" value="1" style="background:#D32F2F;"
                    class="w-full px-4 py-2.5 text-white rounded-lg font-medium hover:opacity-90 transition-opacity text-sm">
                    ดาวน์โหลด Excel
                </button>
            </form>

            <form method="get" class="bg-white rounded-xl border border-gray-200 p-6">
                <div class="mb-4">
                    <h3 style="color:#D32F2F;" class="text-lg font-bold mb-1">ช่วงเวลาที่กำหนด</h3>
                    <p class="text-sm text-gray-600 mb-3">Export ตามช่วงวันที่เลือก</p>
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
                <button type="submit" name="download" value="1" style="background:#D32F2F;"
                    class="w-full px-4 py-2.5 text-white rounded-lg font-medium hover:opacity-90 transition-opacity text-sm">
                    ดาวน์โหลด Excel
                </button>
            </form>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-gray-400 text-xs mb-1">รายการทั้งหมด</p>
                <p style="color:#D32F2F;" class="text-2xl font-bold">
                    <?= number_format($stats['total_bookings'] ?? 0) ?>
                </p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-gray-400 text-xs mb-1">เช็คชื่อแล้ว</p>
                <p class="text-2xl font-bold text-green-600">
                    <?= number_format($stats['attended'] ?? 0) ?>
                </p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-gray-400 text-xs mb-1">ยังไม่เช็ค</p>
                <p class="text-2xl font-bold text-amber-500">
                    <?= number_format($stats['pending'] ?? 0) ?>
                </p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-gray-400 text-xs mb-1">แพ็กเกจที่ขาย</p>
                <p style="color:#B71C1C;" class="text-2xl font-bold">
                    <?= number_format($pkgStats['sold'] ?? 0) ?>
                </p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-gray-400 text-xs mb-1">รายได้จากแพ็กเกจ</p>
                <p class="text-2xl font-bold text-purple-600">฿
                    <?= number_format($pkgStats['revenue'] ?? 0, 0) ?>
                </p>
            </div>
        </div>

        <!-- Excel Sheet Preview note -->
        <div
            class="bg-sky-50 border border-sky-200 rounded-xl px-5 py-3 mb-4 flex items-center gap-3 text-sm text-sky-700">
            <span class="text-lg">📊</span>
            <div>
                ไฟล์ Excel จะมี <strong>2 Sheet</strong>:
                <span
                    class="inline-block bg-sky-200 text-sky-800 text-xs px-2 py-0.5 rounded-full mx-1">บันทึกการเรียน</span>
                และ
                <span
                    class="inline-block bg-purple-100 text-purple-800 text-xs px-2 py-0.5 rounded-full mx-1">การขายแพ็กเกจ</span>
            </div>
        </div>

        <!-- Preview Section -->
        <?php if (count($previewRows) > 0): ?>
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden mb-6">
                <div style="background:#D32F2F;" class="px-6 py-4 flex justify-between items-center">
                    <h2 class="text-sm font-bold text-white">ตัวอย่าง Sheet 1: บันทึกการเรียน (10 รายการแรก)</h2>
                    <span class="text-blue-200 text-xs hidden sm:block">วันที่ · เวลา · ห้อง · ครู · ชื่อ · เบอร์ · แพ็กเกจ
                        · สถานะ</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr style="background:#B71C1C;" class="text-white text-xs">
                                <th class="px-3 py-3 text-center font-medium">#</th>
                                <th class="px-3 py-3 text-left font-medium">วันที่เรียน</th>
                                <th class="px-3 py-3 text-center font-medium">เวลา</th>
                                <th class="px-3 py-3 text-center font-medium">ห้อง</th>
                                <th class="px-3 py-3 text-left font-medium">ครู</th>
                                <th class="px-3 py-3 text-left font-medium">นักเรียน</th>
                                <th class="px-3 py-3 text-center font-medium">เบอร์</th>
                                <th class="px-3 py-3 text-left font-medium">แพ็กเกจ</th>
                                <th class="px-3 py-3 text-center font-medium">สถานะ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($previewRows as $i => $x): ?>
                                <tr class="hover:bg-gray-50 transition-colors <?= $i % 2 === 1 ? 'bg-[#F0F9FF]' : '' ?>">
                                    <td class="px-3 py-3 text-center text-gray-500 text-xs">
                                        <?= $i + 1 ?>
                                    </td>
                                    <td class="px-3 py-3 text-xs text-gray-800 font-medium">
                                        <?= date('d/m/Y', strtotime($x['course_date'])) ?>
                                    </td>
                                    <td class="px-3 py-3 text-center text-xs text-gray-600">
                                        <?= substr($x['start_time'], 0, 5) ?>–
                                        <?= substr($x['end_time'], 0, 5) ?>
                                    </td>
                                    <td class="px-3 py-3 text-center text-xs">
                                        <?php
                                        $roomColors = ['ห้องร่วม' => 'sky', 'ห้องเล็ก' => 'green', 'ห้องใหญ่' => 'yellow'];
                                        $rc = $roomColors[$x['room']] ?? 'gray';
                                        ?>
                                        <span
                                            class="bg-<?= $rc ?>-100 text-<?= $rc ?>-700 text-[10px] px-2 py-0.5 rounded-full font-medium">
                                            <?= htmlspecialchars($x['room']) ?>
                                        </span>
                                    </td>
                                    <td class="px-3 py-3 text-xs text-gray-700">อ.
                                        <?= htmlspecialchars($x['instructor']) ?>
                                    </td>
                                    <td class="px-3 py-3 text-xs text-gray-800 font-medium">
                                        <?= htmlspecialchars($x['student_name']) ?>
                                    </td>
                                    <td class="px-3 py-3 text-center text-xs text-gray-500">
                                        <?= htmlspecialchars($x['student_phone']) ?>
                                    </td>
                                    <td class="px-3 py-3 text-xs text-gray-600">
                                        <?= $x['package_type_name'] ? htmlspecialchars($x['package_type_name']) : '<span class="text-gray-400">ไม่มีแพ็กเกจ</span>' ?>
                                    </td>
                                    <td class="px-3 py-3 text-center">
                                        <?php if ($x['status'] === 'attended'): ?>
                                            <span
                                                class="text-[10px] px-2 py-0.5 rounded-full bg-green-100 text-green-700 font-medium">เช็คแล้ว
                                                ✓</span>
                                        <?php else: ?>
                                            <span
                                                class="text-[10px] px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">ยังไม่เช็ค</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (count($previewRows) >= 10): ?>
                    <div class="bg-gray-50 border-t border-gray-200 px-6 py-3 text-center">
                        <p class="text-xs text-gray-500">แสดง 10 รายการแรก — ดาวน์โหลด Excel เพื่อดูข้อมูลทั้งหมดพร้อม 2 Sheet
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
                <div class="text-5xl mb-4">🧘</div>
                <h3 class="text-xl font-bold text-gray-800 mb-2">ไม่พบข้อมูลคลาสโยคะ</h3>
                <p class="text-gray-500">ไม่มีรายการในช่วงเวลาที่เลือก</p>
            </div>
        <?php endif; ?>

    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>

</html>