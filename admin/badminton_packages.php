<?php
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../config/db.php';

require_permission('badminton_packages');

$today = date('Y-m-d');
$adminId = $_SESSION['user']['id'];
$success = $error = '';

// ──────────────────────────────────────────────────────────
// POST handlers
// ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  // ── ขายแพ็กเกจให้ลูกค้า ──────────────────────────────
  if ($action === 'sell_package') {
    $name = trim($_POST['customer_name'] ?? '');
    $phone = trim($_POST['customer_phone'] ?? '');
    $typeId = (int) $_POST['badminton_package_type_id'];
    $pdate = $_POST['purchase_date'] ?? $today;
    $notes = trim($_POST['notes'] ?? '');

    if (empty($name) || empty($phone) || !$typeId) {
      $error = 'กรุณากรอกข้อมูลให้ครบ';
    } else {
      $typeRow = $pdo->prepare("SELECT * FROM badminton_package_types WHERE id=? AND is_active=1");
      $typeRow->execute([$typeId]);
      $pkg = $typeRow->fetch();
      if (!$pkg) {
        $error = 'ไม่พบแพ็กเกจนี้';
      } else {
        // ค้นหา member_id จากเบอร์โทร (ถ้ามี)
        $memberStmt = $pdo->prepare("SELECT id FROM members WHERE phone=?");
        $memberStmt->execute([$phone]);
        $member = $memberStmt->fetch();
        $memberId = $member ? $member['id'] : null;

        $totalHours = $pkg['hours_total'] + $pkg['bonus_hours'];
        $expiry = null;
        if ($pkg['validity_days']) {
          $expiry = (new DateTime($pdate))->modify('+' . $pkg['validity_days'] . ' day')->format('Y-m-d');
        }
        $stmt = $pdo->prepare("
                    INSERT INTO member_badminton_packages
                      (member_id, customer_name, customer_phone, badminton_package_type_id, hours_total, hours_used, purchase_date, expiry_date, notes, created_by)
                    VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?)
                ");
        $stmt->execute([$memberId, $name, $phone, $typeId, $totalHours, $pdate, $expiry, $notes ?: null, $adminId]);
        $success = "ขายแพ็กเกจ \"{$pkg['name']}\" ให้ {$name} เรียบร้อย ({$totalHours} ชม." . ($expiry ? " หมด {$expiry}" : '') . ')';
      }
    }
  }

  // ── ลบแพ็กเกจสมาชิก ──────────────────────────────────
  elseif ($action === 'delete_package') {
    $pkgId = (int) $_POST['pkg_id'];
    $pdo->prepare("DELETE FROM member_badminton_packages WHERE id=?")->execute([$pkgId]);
    $success = 'ลบแพ็กเกจแล้ว';
  }

  // ── แก้ไขชื่อ/เบอร์ลูกค้า ────────────────────
  elseif ($action === 'edit_customer') {
    $pkgId = (int) $_POST['pkg_id'];
    $name = trim($_POST['customer_name'] ?? '');
    $phone = trim($_POST['customer_phone'] ?? '');
    if (empty($name) || empty($phone)) {
      $error = 'กรุณากรอกชื่อและเบอร์โทร';
    } else {
      $pdo->prepare("UPDATE member_badminton_packages SET customer_name=?, customer_phone=? WHERE id=?")
        ->execute([$name, $phone, $pkgId]);
      $success = "อัปเดตข้อมูลลูกค้า \"{$name}\" เรียบร้อย";
    }
  }

  // ── แก้ไขชั่วโมงใช้งาน / วันหมดอายุ (ย้อนหลัง) ──────
  elseif ($action === 'update_pkg_detail') {
    $pkgId      = (int) $_POST['pkg_id'];
    $hoursUsed  = max(0, (int) $_POST['hours_used']);
    $expiry     = trim($_POST['expiry_date'] ?? '');
    $purchDate  = trim($_POST['purchase_date'] ?? '');
    $notes      = trim($_POST['notes'] ?? '');

    // validate hours_used ≤ hours_total
    $row = $pdo->prepare("SELECT hours_total, customer_name FROM member_badminton_packages WHERE id=?");
    $row->execute([$pkgId]);
    $existing = $row->fetch();
    if (!$existing) {
      $error = 'ไม่พบแพ็กเกจนี้';
    } elseif ($hoursUsed > $existing['hours_total']) {
      $error = 'ชั่วโมงที่ใช้ไปเกินจำนวนรวมของแพ็กเกจ';
    } else {
      $expiryVal  = $expiry   ?: null;
      $purchVal   = $purchDate ?: null;
      $pdo->prepare("
        UPDATE member_badminton_packages
        SET hours_used=?, expiry_date=?, purchase_date=COALESCE(?,purchase_date), notes=?
        WHERE id=?
      ")->execute([$hoursUsed, $expiryVal, $purchVal, $notes ?: null, $pkgId]);
      $success = "อัปเดตข้อมูลแพ็กเกจของ \"{$existing['customer_name']}\" เรียบร้อย";
    }
  }

  // ── เพิ่มประเภทแพ็กเกจใหม่ ────────────────────────────
  elseif ($action === 'add_pkg_type') {
    $ptName = trim($_POST['pt_name'] ?? '');
    $ptHours = max(1, (int) ($_POST['pt_hours'] ?? 1));
    $ptBonus = max(0, (int) ($_POST['pt_bonus'] ?? 0));
    $ptPrice = (float) ($_POST['pt_price'] ?? 0);
    $ptValid = ((int) ($_POST['pt_validity'] ?? 0)) ?: null;
    $ptActive = isset($_POST['pt_active']) ? 1 : 0;
    if (empty($ptName) || $ptPrice <= 0) {
      $error = 'กรุณากรอกชื่อและราคา';
    } else {
      $pdo->prepare("
                INSERT INTO badminton_package_types (name, hours_total, bonus_hours, price, validity_days, is_active)
                VALUES (?,?,?,?,?,?)
            ")->execute([$ptName, $ptHours, $ptBonus, $ptPrice, $ptValid, $ptActive]);
      $success = "เพิ่มแพ็กเกจ \"{$ptName}\" เรียบร้อย";
    }
  }

  // ── แก้ไขประเภทแพ็กเกจ ────────────────────────────────
  elseif ($action === 'edit_pkg_type') {
    $ptId = (int) $_POST['pt_id'];
    $ptName = trim($_POST['pt_name'] ?? '');
    $ptHours = max(1, (int) ($_POST['pt_hours'] ?? 1));
    $ptBonus = max(0, (int) ($_POST['pt_bonus'] ?? 0));
    $ptPrice = (float) ($_POST['pt_price'] ?? 0);
    $ptValid = ((int) ($_POST['pt_validity'] ?? 0)) ?: null;
    $ptActive = isset($_POST['pt_active']) ? 1 : 0;
    if (empty($ptName) || $ptPrice <= 0) {
      $error = 'กรุณากรอกชื่อและราคา';
    } else {
      $pdo->prepare("
                UPDATE badminton_package_types
                SET name=?, hours_total=?, bonus_hours=?, price=?, validity_days=?, is_active=?
                WHERE id=?
            ")->execute([$ptName, $ptHours, $ptBonus, $ptPrice, $ptValid, $ptActive, $ptId]);
      $success = "อัปเดตแพ็กเกจ \"{$ptName}\" เรียบร้อย";
    }
  }

  // ── ลบประเภทแพ็กเกจ ───────────────────────────────────
  elseif ($action === 'delete_pkg_type') {
    $ptId = (int) $_POST['pt_id'];
    $inUse = $pdo->prepare("SELECT COUNT(*) FROM member_badminton_packages WHERE badminton_package_type_id=?");
    $inUse->execute([$ptId]);
    if ($inUse->fetchColumn() > 0) {
      $error = 'ไม่สามารถลบได้ เพราะมีลูกค้าใช้แพ็กเกจนี้อยู่ (ปิดการใช้งานแทน)';
    } else {
      $pdo->prepare("DELETE FROM badminton_package_types WHERE id=?")->execute([$ptId]);
      $success = 'ลบประเภทแพ็กเกจแล้ว';
    }
  }
}

// ── Load all member packages (client-side filter/pagination) ──
$stmt = $pdo->prepare("
    SELECT mbp.*, bpt.name AS type_name, bpt.price,
           (mbp.hours_total - mbp.hours_used) AS remaining,
           CASE
             WHEN (mbp.hours_total - mbp.hours_used) <= 0 THEN 'empty'
             WHEN mbp.expiry_date IS NOT NULL AND mbp.expiry_date < ? THEN 'expired'
             ELSE 'active'
           END AS pkg_status
    FROM member_badminton_packages mbp
    JOIN badminton_package_types bpt ON bpt.id = mbp.badminton_package_type_id
    ORDER BY
      CASE WHEN (mbp.hours_total - mbp.hours_used) > 0 AND (mbp.expiry_date IS NULL OR mbp.expiry_date >= ?) THEN 0 ELSE 1 END,
      mbp.customer_name ASC, mbp.expiry_date ASC
");
$stmt->execute([$today, $today]);
$memberPkgs = $stmt->fetchAll();

// ── Package types (ทั้งหมด รวม inactive) ─────────────────
$pkgTypes = $pdo->query("SELECT * FROM badminton_package_types ORDER BY hours_total ASC")->fetchAll();
$pkgTypesActive = array_filter($pkgTypes, fn($p) => $p['is_active']);

// ── Stats ─────────────────────────────────────────────────
$stats = $pdo->query("
    SELECT
      COUNT(*) AS total_pkgs,
      SUM(CASE WHEN (hours_total - hours_used) > 0 AND (expiry_date IS NULL OR expiry_date >= CURDATE()) THEN 1 ELSE 0 END) AS active_pkgs,
      SUM(hours_total - hours_used) AS total_remaining
    FROM member_badminton_packages
")->fetch();
?>
<!doctype html>
<html lang="th">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>แพ็กเกจคอร์ตแบดมินตัน – BARGAIN SPORT</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    * {
      font-family: 'Prompt', sans-serif !important;
    }
  </style>
</head>

<body style="background:#f8fafc;" class="min-h-screen">
  <?php include __DIR__ . '/../includes/header.php'; ?>
  <?php include __DIR__ . '/../includes/swal_flash.php'; ?>

  <div class="max-w-7xl mx-auto px-4 py-6">

    <!-- ── Header ── -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
      <div>
        <h1 class="text-2xl font-bold" style="color:#D32F2F;">แพ็กเกจคอร์ตแบดมินตัน</h1>
        <p class="text-gray-500 text-sm">จัดการและขายแพ็กเกจให้ลูกค้า</p>
      </div>
      <div class="flex gap-2 flex-wrap">
        <button onclick="document.getElementById('addTypeModal').classList.remove('hidden')"
          class="flex items-center gap-1.5 px-4 py-2 text-sm border rounded-lg font-medium hover:opacity-90 transition-opacity"
          style="color:#D32F2F;border-color:#D32F2F;">
          + ประเภทแพ็กเกจ
        </button>
        <button onclick="document.getElementById('sellModal').classList.remove('hidden')"
          class="flex items-center gap-1.5 px-4 py-2 text-sm text-white rounded-lg font-medium hover:opacity-90 transition-opacity"
          style="background:#D32F2F;">
          + ขายแพ็กเกจ
        </button>
      </div>
    </div>

    <!-- ── Stats ── -->
    <div class="grid grid-cols-3 gap-4 mb-5">
      <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
        <p class="text-xs text-gray-400 mb-1">แพ็กเกจทั้งหมด</p>
        <p class="text-2xl font-bold text-gray-900"><?= $stats['total_pkgs'] ?? 0 ?></p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
        <p class="text-xs text-gray-400 mb-1">ยังใช้งานได้</p>
        <p class="text-2xl font-bold" style="color:#D32F2F;"><?= $stats['active_pkgs'] ?? 0 ?></p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
        <p class="text-xs text-gray-400 mb-1">ชั่วโมงที่เหลือรวม</p>
        <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['total_remaining'] ?? 0) ?></p>
      </div>
    </div>

    <!-- ── Package Types Table ── -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden mb-5">
      <div class="px-4 py-3 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
        <div>
          <span class="text-sm font-semibold text-gray-700">ประเภทแพ็กเกจ</span>
          <span class="text-xs text-gray-400 ml-2"><?= count($pkgTypes) ?> รายการ</span>
        </div>
        <button onclick="document.getElementById('addTypeModal').classList.remove('hidden')"
          class="text-xs px-3 py-1.5 rounded-lg border font-medium hover:bg-blue-50 transition-colors"
          style="color:#D32F2F;border-color:#D32F2F;">+ เพิ่มประเภท</button>
      </div>
      <div class="px-4 py-2.5 border-b border-gray-100 flex flex-col sm:flex-row gap-2 items-center">
        <input type="text" id="ptSearch" placeholder="🔍 ค้นหาชื่อแพ็กเกจ..."
          class="flex-1 w-full px-3 py-1.5 rounded-lg border border-gray-300 text-sm outline-none focus:border-blue-400">
        <div class="flex items-center gap-2 text-sm text-gray-500 whitespace-nowrap">
          <span>แสดง</span>
          <select id="ptPerPage"
            class="px-2 py-1.5 border border-gray-300 rounded-lg text-sm outline-none focus:border-blue-400">
            <option value="5">5</option>
            <option value="10" selected>10</option>
            <option value="25">25</option>
            <option value="99999">ทั้งหมด</option>
          </select>
          <span>รายการ/หน้า</span>
        </div>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full" id="pkgTypesTable">
          <thead style="background:#FAFAFA;">
            <tr>
              <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600">ชื่อแพ็กเกจ</th>
              <th class="px-4 py-2.5 text-center text-xs font-semibold text-gray-600">ชั่วโมงหลัก</th>
              <th class="px-4 py-2.5 text-center text-xs font-semibold text-gray-600">โบนัส</th>
              <th class="px-4 py-2.5 text-center text-xs font-semibold text-gray-600">รวม</th>
              <th class="px-4 py-2.5 text-center text-xs font-semibold text-gray-600">ราคา</th>
              <th class="px-4 py-2.5 text-center text-xs font-semibold text-gray-600">อายุ (วัน)</th>
              <th class="px-4 py-2.5 text-center text-xs font-semibold text-gray-600">สถานะ</th>
              <th class="px-4 py-2.5 text-center text-xs font-semibold text-gray-600">จัดการ</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <?php foreach ($pkgTypes as $pt): ?>
              <tr class="hover:bg-gray-50 <?= !$pt['is_active'] ? 'opacity-50' : '' ?>">
                <td class="px-4 py-3">
                  <p class="font-medium text-gray-800 text-sm"><?= htmlspecialchars($pt['name']) ?></p>
                </td>
                <td class="px-4 py-3 text-center text-sm text-gray-700"><?= $pt['hours_total'] ?></td>
                <td class="px-4 py-3 text-center text-sm">
                  <?= $pt['bonus_hours'] > 0
                    ? '<span class="text-green-600 font-medium">+' . $pt['bonus_hours'] . '</span>'
                    : '<span class="text-gray-300">—</span>' ?>
                </td>
                <td class="px-4 py-3 text-center text-sm font-semibold text-gray-800">
                  <?= $pt['hours_total'] + $pt['bonus_hours'] ?>
                </td>
                <td class="px-4 py-3 text-center text-sm font-semibold">
                  ฿<?= number_format($pt['price'], 0) ?>
                </td>
                <td class="px-4 py-3 text-center text-sm text-gray-600">
                  <?= $pt['validity_days'] ? $pt['validity_days'] : '<span class="text-gray-300">ไม่จำกัด</span>' ?>
                </td>
                <td class="px-4 py-3 text-center">
                  <?php if ($pt['is_active']): ?>
                    <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700 font-medium">เปิด</span>
                  <?php else: ?>
                    <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-500">ปิด</span>
                  <?php endif; ?>
                </td>
                <td class="px-4 py-3 text-center">
                  <div class="flex items-center justify-center gap-2">
                    <button onclick="editPkgType(<?= htmlspecialchars(json_encode($pt, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>)"
                      class="text-xs px-2.5 py-1 rounded border border-gray-300 text-gray-600 hover:bg-gray-50 transition-colors">แก้ไข</button>
                    <button onclick="deletePkgType(<?= $pt['id'] ?>)"
                      class="text-red-400 hover:text-red-600 text-sm" title="ลบ">🗑</button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($pkgTypes)): ?>
              <tr>
                <td colspan="8" class="px-4 py-8 text-center text-gray-400 text-sm">ยังไม่มีประเภทแพ็กเกจ</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="px-4 py-3 border-t border-gray-100 flex flex-col sm:flex-row items-center justify-between gap-2">
        <span class="text-xs text-gray-400" id="ptInfo"></span>
        <div class="flex gap-1 flex-wrap justify-end" id="ptPager"></div>
      </div>
    </div>

    <!-- ── Member packages table ── -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <div class="px-4 py-3 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
        <span class="text-sm font-semibold text-gray-700">แพ็กเกจสมาชิก
          <span class="font-normal text-gray-400 ml-1">ทั้งหมด <span class="font-bold"
              style="color:#D32F2F;"><?= count($memberPkgs) ?></span> รายการ</span>
        </span>
      </div>
      <div class="px-4 py-2.5 border-b border-gray-100 flex flex-col sm:flex-row gap-2 items-center">
        <input type="text" id="mpSearch" placeholder="🔍 ค้นหาชื่อหรือเบอร์โทรสมาชิก..."
          class="flex-1 w-full px-3 py-1.5 rounded-lg border border-gray-300 text-sm outline-none focus:border-blue-400">
        <div class="flex items-center gap-2 text-sm text-gray-500 whitespace-nowrap">
          <span>แสดง</span>
          <select id="mpPerPage"
            class="px-2 py-1.5 border border-gray-300 rounded-lg text-sm outline-none focus:border-blue-400">
            <option value="5">5</option>
            <option value="10" selected>10</option>
            <option value="25">25</option>
            <option value="99999">ทั้งหมด</option>
          </select>
          <span>รายการ/หน้า</span>
        </div>
      </div>
      <?php if (empty($memberPkgs)): ?>
        <div class="p-12 text-center text-gray-400">ยังไม่มีข้อมูลแพ็กเกจสมาชิก</div>
      <?php else: ?>
        <div class="overflow-x-auto">
          <table class="w-full" id="memberPkgsTable">
            <thead style="background:#FAFAFA;">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">ลูกค้า</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">แพ็กเกจ</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600">ใช้แล้ว</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600">เหลือ</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600">วันหมด</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600">สลิป</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600">สถานะ</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600">จัดการ</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <?php foreach ($memberPkgs as $mp):
                $statusBadge = match ($mp['pkg_status']) {
                  'active'  => ['text' => 'ใช้งานได้', 'bg' => 'bg-green-100', 'txt' => 'text-green-800'],
                  'expired' => ['text' => 'หมดอายุ',   'bg' => 'bg-red-100',   'txt' => 'text-red-800'],
                  'empty'   => ['text' => 'ชม.หมด',    'bg' => 'bg-gray-100',  'txt' => 'text-gray-500'],
                  default   => ['text' => '—',          'bg' => 'bg-gray-100',  'txt' => 'text-gray-500'],
                };
                $remaining  = max(0, (int)$mp['remaining']);
                $remainPct  = $mp['hours_total'] > 0 ? min(100, round($remaining / $mp['hours_total'] * 100)) : 0;
                $usedPct    = 100 - $remainPct;
              ?>
                <tr class="hover:bg-gray-50 <?= $mp['pkg_status'] !== 'active' ? 'opacity-60' : '' ?>">
                  <td class="px-4 py-3">
                    <p class="font-medium text-gray-900"><?= htmlspecialchars($mp['customer_name']) ?></p>
                    <p class="text-xs text-gray-500"><?= htmlspecialchars($mp['customer_phone']) ?></p>
                  </td>
                  <td class="px-4 py-3">
                    <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($mp['type_name']) ?></p>
                    <p class="text-xs text-gray-400">ซื้อ <?= date('d/m/Y', strtotime($mp['purchase_date'])) ?></p>
                  </td>
                  <td class="px-4 py-3 text-center">
                    <span class="font-semibold text-gray-700"><?= $mp['hours_used'] ?></span>
                    <span class="text-xs text-gray-400">/ <?= $mp['hours_total'] ?> ชม.</span>
                    <div class="w-full h-1 bg-gray-200 rounded-full mt-1 overflow-hidden">
                      <div class="h-full rounded-full <?= $remainPct > 30 ? 'bg-green-400' : 'bg-amber-400' ?>"
                        style="width:<?= $usedPct ?>%"></div>
                    </div>
                  </td>
                  <td class="px-4 py-3 text-center">
                    <span class="text-xl font-bold <?= $remaining <= 2 ? 'text-red-600' : 'text-green-600' ?>"><?= $remaining ?></span>
                    <span class="text-xs text-gray-400 block">ชม.</span>
                  </td>
                  <td class="px-4 py-3 text-center text-sm text-gray-600">
                    <?= $mp['expiry_date'] ? date('d/m/Y', strtotime($mp['expiry_date'])) : '—' ?>
                  </td>
                  <td class="px-4 py-3 text-center">
                    <?php if (!empty($mp['payment_slip_path'])): ?>
                      <img src="/<?= htmlspecialchars($mp['payment_slip_path']) ?>"
                           alt="สลิป"
                           onclick="viewSlip('/<?= htmlspecialchars($mp['payment_slip_path']) ?>')"
                           class="w-10 h-10 object-cover rounded-lg border border-gray-200 cursor-pointer hover:opacity-80 mx-auto transition-opacity"
                           title="คลิกดูสลิป">
                      <button onclick="openSlipUpload(<?= $mp['id'] ?>, '<?= htmlspecialchars($mp['customer_name']) ?>')"
                        class="text-xs text-blue-500 hover:text-blue-700 mt-0.5 block mx-auto">เปลี่ยน</button>
                    <?php else: ?>
                      <button onclick="openSlipUpload(<?= $mp['id'] ?>, '<?= htmlspecialchars($mp['customer_name']) ?>')"
                        class="text-xs px-2 py-1 rounded border border-dashed border-gray-300 text-gray-400 hover:border-blue-400 hover:text-blue-500 transition-colors">
                        + แนบสลิป
                      </button>
                    <?php endif; ?>
                  </td>
                  <td class="px-4 py-3 text-center">
                    <span class="text-xs px-2 py-1 rounded-full font-medium <?= $statusBadge['bg'] ?> <?= $statusBadge['txt'] ?>"><?= $statusBadge['text'] ?></span>
                  </td>
                  <td class="px-4 py-3 text-center">
                    <div class="flex items-center justify-center gap-2 flex-wrap">
                      <button onclick="editCustomer(<?= htmlspecialchars(json_encode($mp, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>)"
                        class="text-xs px-2.5 py-1 rounded border border-gray-300 text-gray-600 hover:bg-gray-50 transition-colors">แก้ไข</button>
                      <button onclick="openDetailEdit(<?= htmlspecialchars(json_encode($mp, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>)"
                        class="text-xs px-2.5 py-1 rounded border font-medium hover:bg-blue-50 transition-colors" style="color:#D32F2F;border-color:#D32F2F;">ปรับข้อมูล</button>
                      <button onclick="deletePackage(<?= $mp['id'] ?>)"
                        class="text-red-400 hover:text-red-600 text-sm" title="ลบ">🗑</button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="px-4 py-3 border-t border-gray-100 flex flex-col sm:flex-row items-center justify-between gap-2">
          <span class="text-xs text-gray-400" id="mpInfo"></span>
          <div class="flex gap-1 flex-wrap justify-end" id="mpPager"></div>
        </div>
      <?php endif; ?>
    </div>

  </div>

  <!-- ══════════════════════════════════════════════════════════ -->
  <!-- Modal: เพิ่มประเภทแพ็กเกจ -->
  <!-- ══════════════════════════════════════════════════════════ -->
  <div id="addTypeModal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md" onclick="event.stopPropagation();">
      <div class="px-5 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold" style="color:#D32F2F;">เพิ่มประเภทแพ็กเกจ</h3>
      </div>
      <form method="post" class="p-5 space-y-4">
        <input type="hidden" name="action" value="add_pkg_type">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อแพ็กเกจ *</label>
          <input type="text" name="pt_name" required placeholder="เช่น 10 ชม. + แถม 2"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">ชั่วโมงหลัก *</label>
            <input type="number" name="pt_hours" required min="1" value="10"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">ชั่วโมงโบนัส</label>
            <input type="number" name="pt_bonus" min="0" value="0"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
          </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">ราคา (บาท) *</label>
            <input type="number" name="pt_price" required min="0" step="0.01"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">อายุแพ็ก (วัน)</label>
            <input type="number" name="pt_validity" min="0" placeholder="เว้นว่าง = ไม่หมดอายุ"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
          </div>
        </div>
        <div>
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="pt_active" checked class="w-4 h-4 text-blue-600 rounded">
            <span class="text-sm text-gray-700">เปิดใช้งาน</span>
          </label>
        </div>
        <div class="flex gap-2 pt-2">
          <button type="button" onclick="document.getElementById('addTypeModal').classList.add('hidden')"
            class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">ยกเลิก</button>
          <button type="submit"
            class="flex-1 px-4 py-2 text-white rounded-lg hover:opacity-90"
            style="background:#D32F2F;">บันทึก</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════════════════ -->
  <!-- Modal: แก้ไขประเภทแพ็กเกจ -->
  <!-- ══════════════════════════════════════════════════════════ -->
  <div id="editTypeModal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md" onclick="event.stopPropagation();">
      <div class="px-5 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold" style="color:#D32F2F;">แก้ไขประเภทแพ็กเกจ</h3>
      </div>
      <form method="post" class="p-5 space-y-4">
        <input type="hidden" name="action" value="edit_pkg_type">
        <input type="hidden" name="pt_id" id="edit_pt_id">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อแพ็กเกจ *</label>
          <input type="text" name="pt_name" id="edit_pt_name" required
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">ชั่วโมงหลัก *</label>
            <input type="number" name="pt_hours" id="edit_pt_hours" required min="1"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">ชั่วโมงโบนัส</label>
            <input type="number" name="pt_bonus" id="edit_pt_bonus" min="0"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
          </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">ราคา (บาท) *</label>
            <input type="number" name="pt_price" id="edit_pt_price" required min="0" step="0.01"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">อายุแพ็ก (วัน)</label>
            <input type="number" name="pt_validity" id="edit_pt_validity" min="0"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
          </div>
        </div>
        <div>
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="pt_active" id="edit_pt_active" class="w-4 h-4 text-blue-600 rounded">
            <span class="text-sm text-gray-700">เปิดใช้งาน</span>
          </label>
        </div>
        <div class="flex gap-2 pt-2">
          <button type="button" onclick="document.getElementById('editTypeModal').classList.add('hidden')"
            class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">ยกเลิก</button>
          <button type="submit"
            class="flex-1 px-4 py-2 text-white rounded-lg hover:opacity-90"
            style="background:#D32F2F;">บันทึก</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════════════════ -->
  <!-- Modal: ขายแพ็กเกจ -->
  <!-- ══════════════════════════════════════════════════════════ -->
  <div id="sellModal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md" onclick="event.stopPropagation();">
      <div class="px-5 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold" style="color:#D32F2F;">ขายแพ็กเกจให้ลูกค้า</h3>
      </div>
      <form method="post" class="p-5 space-y-4">
        <input type="hidden" name="action" value="sell_package">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อลูกค้า *</label>
          <input type="text" name="customer_name" required
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">เบอร์โทร *</label>
          <input type="text" name="customer_phone" required pattern="[0-9]{9,10}"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">แพ็กเกจ *</label>
          <select name="badminton_package_type_id" required
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
            <option value="">-- เลือกแพ็กเกจ --</option>
            <?php foreach ($pkgTypesActive as $pt): ?>
              <option value="<?= $pt['id'] ?>">
                <?= htmlspecialchars($pt['name']) ?> (฿<?= number_format($pt['price'], 2) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">วันที่ซื้อ</label>
          <input type="date" name="purchase_date" value="<?= $today ?>"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">หมายเหตุ</label>
          <textarea name="notes" rows="2"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none"></textarea>
        </div>
        <div class="flex gap-2 pt-2">
          <button type="button" onclick="document.getElementById('sellModal').classList.add('hidden')"
            class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">ยกเลิก</button>
          <button type="submit"
            class="flex-1 px-4 py-2 text-white rounded-lg hover:opacity-90"
            style="background:#D32F2F;">ขายแพ็กเกจ</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════════════════ -->
  <!-- Modal: แก้ไขข้อมูลลูกค้า -->
  <!-- ══════════════════════════════════════════════════════════ -->
  <div id="editCustomerModal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md" onclick="event.stopPropagation();">
      <div class="px-5 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold" style="color:#D32F2F;">แก้ไขข้อมูลลูกค้า</h3>
      </div>
      <form method="post" class="p-5 space-y-4">
        <input type="hidden" name="action" value="edit_customer">
        <input type="hidden" name="pkg_id" id="edit_cust_pkg_id">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อลูกค้า *</label>
          <input type="text" name="customer_name" id="edit_cust_name" required
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">เบอร์โทร *</label>
          <input type="text" name="customer_phone" id="edit_cust_phone" required pattern="[0-9]{9,10}"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
        </div>
        <div class="flex gap-2 pt-2">
          <button type="button" onclick="document.getElementById('editCustomerModal').classList.add('hidden')"
            class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">ยกเลิก</button>
          <button type="submit"
            class="flex-1 px-4 py-2 text-white rounded-lg hover:opacity-90"
            style="background:#D32F2F;">บันทึก</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════════════════ -->
  <!-- Modal: ปรับข้อมูลย้อนหลัง -->
  <!-- ══════════════════════════════════════════════════════════ -->
  <div id="detailEditModal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md" onclick="event.stopPropagation();">
      <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
        <div>
          <h3 class="text-base font-bold text-gray-900">ปรับข้อมูลย้อนหลัง</h3>
          <p class="text-xs text-gray-500 mt-0.5" id="detailEditCustomerName"></p>
        </div>
        <button onclick="document.getElementById('detailEditModal').classList.add('hidden')"
          class="text-gray-400 hover:text-gray-600 text-xl leading-none">×</button>
      </div>
      <form method="post" class="px-6 py-4 space-y-4">
        <input type="hidden" name="action" value="update_pkg_detail">
        <input type="hidden" name="pkg_id" id="detailEditPkgId">
        <!-- Package info display -->
        <div class="bg-blue-50 rounded-xl p-3 text-xs text-blue-700" id="detailEditPkgInfo"></div>
        <!-- Hours used -->
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">ชั่วโมงที่ใช้ไปแล้ว (ทั้งหมด)</label>
          <div class="flex items-center gap-2">
            <input type="number" name="hours_used" id="detailEditHoursUsed" min="0"
              class="flex-1 px-3 py-2.5 border border-gray-300 rounded-lg text-sm outline-none focus:border-blue-400">
            <span class="text-xs text-gray-500" id="detailEditHoursMax"></span>
          </div>
          <p class="text-xs text-gray-400 mt-1">กรอกจำนวนชั่วโมงรวมที่ใช้ไปแล้ว (รวมทุกครั้ง)</p>
        </div>
        <!-- Purchase date -->
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">วันที่ซื้อ</label>
          <input type="date" name="purchase_date" id="detailEditPurchaseDate"
            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm outline-none focus:border-blue-400">
        </div>
        <!-- Expiry date -->
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">วันหมดอายุ <span class="text-gray-400">(ว่าง = ไม่จำกัด)</span></label>
          <input type="date" name="expiry_date" id="detailEditExpiryDate"
            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm outline-none focus:border-blue-400">
        </div>
        <!-- Notes -->
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">หมายเหตุ</label>
          <input type="text" name="notes" id="detailEditNotes" placeholder="ไม่บังคับ"
            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm outline-none focus:border-blue-400">
        </div>
        <div class="flex gap-3 pt-1">
          <button type="submit" class="flex-1 py-2.5 text-white text-sm font-semibold rounded-lg hover:opacity-90"
            style="background:#D32F2F;">บันทึก</button>
          <button type="button" onclick="document.getElementById('detailEditModal').classList.add('hidden')"
            class="flex-1 py-2.5 text-gray-600 text-sm rounded-lg border border-gray-300 hover:bg-gray-50">ยกเลิก</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════════════════ -->
  <!-- Modal: อัปโหลดสลิป -->
  <!-- ══════════════════════════════════════════════════════════ -->
  <div id="slipModal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm" onclick="event.stopPropagation();">
      <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200">
        <div>
          <h3 class="text-base font-semibold" style="color:#D32F2F;">แนบสลิปการชำระเงิน</h3>
          <p class="text-xs text-gray-500 mt-0.5" id="slipModalCustomerName"></p>
        </div>
        <button onclick="closeSlipModal()" class="text-gray-400 hover:text-gray-600 text-xl leading-none">×</button>
      </div>
      <div class="p-5 space-y-4">
        <input type="hidden" id="slipPkgId">
        <!-- Drop zone -->
        <div id="slipDropZone"
          class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center cursor-pointer hover:border-blue-400 hover:bg-blue-50/30 transition-colors"
          onclick="document.getElementById('slipFileInput').click()"
          ondragover="event.preventDefault();this.classList.add('border-blue-400','bg-blue-50/30')"
          ondragleave="this.classList.remove('border-blue-400','bg-blue-50/30')"
          ondrop="handleSlipDrop(event)">
          <div id="slipDropContent">
            <svg class="w-10 h-10 mx-auto text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <p class="text-sm text-gray-500">ลากไฟล์มาวาง หรือ <span class="text-blue-500 font-medium">คลิกเลือกไฟล์</span></p>
            <p class="text-xs text-gray-400 mt-1">JPG, PNG, WebP · สูงสุด 10MB</p>
          </div>
          <img id="slipPreviewImg" src="" alt="preview" class="hidden max-h-48 mx-auto rounded-lg object-contain">
        </div>
        <input type="file" id="slipFileInput" accept="image/jpeg,image/png,image/webp" class="hidden" onchange="previewSlip(this)">
        <!-- Status -->
        <div id="slipStatus" class="hidden text-sm text-center py-2 px-3 rounded-lg"></div>
        <!-- Buttons -->
        <div class="flex gap-2">
          <button type="button" onclick="closeSlipModal()"
            class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-sm">ยกเลิก</button>
          <button type="button" id="slipUploadBtn" onclick="uploadSlip()"
            class="flex-1 px-4 py-2 text-white rounded-lg hover:opacity-90 text-sm font-medium disabled:opacity-40"
            style="background:#D32F2F;" disabled>อัปโหลด</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════════════════ -->
  <!-- Lightbox: ดูสลิป -->
  <!-- ══════════════════════════════════════════════════════════ -->
  <div id="slipLightbox" class="hidden fixed inset-0 z-[60] bg-black/80 flex items-center justify-center p-4"
    onclick="document.getElementById('slipLightbox').classList.add('hidden')">
    <div class="relative max-w-2xl w-full" onclick="event.stopPropagation()">
      <img id="slipLightboxImg" src="" alt="สลิป" class="w-full rounded-xl shadow-2xl object-contain max-h-[80vh]">
      <button onclick="document.getElementById('slipLightbox').classList.add('hidden')"
        class="absolute -top-3 -right-3 bg-white rounded-full w-8 h-8 flex items-center justify-center text-gray-600 hover:text-gray-900 shadow-lg text-lg font-bold">×</button>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════════════════ -->
  <!-- JavaScript -->
  <!-- ══════════════════════════════════════════════════════════ -->
  <script>
    // ══════════════════════════════════════════════════════════
    // TableManager – search, rows-per-page, pagination
    // ══════════════════════════════════════════════════════════
    class TableManager {
      constructor({ tableId, searchId, perPageId, pagerId, infoId }) {
        this.tableId = tableId;
        this.searchId = searchId;
        this.perPageId = perPageId;
        this.pagerId = pagerId;
        this.infoId = infoId;
        this.page = 1;

        document.getElementById(searchId)?.addEventListener('input', () => { this.page = 1; this.render(); });
        document.getElementById(perPageId)?.addEventListener('change', () => { this.page = 1; this.render(); });
        this.render();
      }

      render() {
        const searchVal = (document.getElementById(this.searchId)?.value || '').toLowerCase().trim();
        const perPage = parseInt(document.getElementById(this.perPageId)?.value || '10');
        const table = document.getElementById(this.tableId);
        if (!table) return;

        const rows = Array.from(table.querySelectorAll('tbody tr'));
        const filtered = rows.filter(row => !searchVal || row.textContent.toLowerCase().includes(searchVal));
        const totalPages = Math.max(1, Math.ceil(filtered.length / perPage));
        if (this.page > totalPages) this.page = 1;

        const start = (this.page - 1) * perPage;
        const end = start + perPage;

        rows.forEach(row => row.style.display = 'none');
        filtered.forEach((row, i) => { row.style.display = (i >= start && i < end) ? '' : 'none'; });

        const infoEl = document.getElementById(this.infoId);
        if (infoEl) {
          if (filtered.length === 0) {
            infoEl.textContent = 'ไม่พบข้อมูลที่ค้นหา';
          } else {
            const from = start + 1;
            const to = Math.min(end, filtered.length);
            infoEl.textContent = `แสดง ${from}–${to} จาก ${filtered.length} รายการ`;
          }
        }

        const pagerEl = document.getElementById(this.pagerId);
        if (!pagerEl) return;
        pagerEl.innerHTML = '';
        if (totalPages <= 1) return;

        this._btn(pagerEl, '‹', this.page > 1, () => { this.page--; this.render(); });

        const range = this._pageRange(this.page, totalPages);
        let prev = null;
        range.forEach(p => {
          if (prev !== null && p - prev > 1) {
            const dots = document.createElement('span');
            dots.textContent = '...';
            dots.className = 'min-w-[2rem] h-8 px-1 text-xs flex items-center justify-center text-gray-400';
            pagerEl.appendChild(dots);
          }
          this._btn(pagerEl, p, true, () => { this.page = p; this.render(); }, p === this.page);
          prev = p;
        });

        this._btn(pagerEl, '›', this.page < totalPages, () => { this.page++; this.render(); });
      }

      _pageRange(current, total) {
        if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
        const pages = new Set([1, total]);
        for (let i = Math.max(2, current - 2); i <= Math.min(total - 1, current + 2); i++) pages.add(i);
        return Array.from(pages).sort((a, b) => a - b);
      }

      _btn(container, label, enabled, onClick, isActive = false) {
        const btn = document.createElement('button');
        btn.textContent = label;
        if (isActive) {
          btn.className = 'min-w-[2rem] h-8 px-2 text-xs rounded border border-transparent text-white transition-colors';
          btn.style.background = '#D32F2F';
        } else if (enabled) {
          btn.className = 'min-w-[2rem] h-8 px-2 text-xs rounded border border-gray-300 text-gray-600 hover:bg-gray-50 transition-colors cursor-pointer';
          btn.addEventListener('click', onClick);
        } else {
          btn.className = 'min-w-[2rem] h-8 px-2 text-xs rounded border border-gray-200 text-gray-300 cursor-default';
        }
        container.appendChild(btn);
      }
    }

    // ── Init tables ──────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
      new TableManager({ tableId: 'pkgTypesTable', searchId: 'ptSearch', perPageId: 'ptPerPage', pagerId: 'ptPager', infoId: 'ptInfo' });
      new TableManager({ tableId: 'memberPkgsTable', searchId: 'mpSearch', perPageId: 'mpPerPage', pagerId: 'mpPager', infoId: 'mpInfo' });
    });

    // ── Edit package type ─────────────────────────────────────
    function editPkgType(pkg) {
      document.getElementById('edit_pt_id').value = pkg.id;
      document.getElementById('edit_pt_name').value = pkg.name;
      document.getElementById('edit_pt_hours').value = pkg.hours_total;
      document.getElementById('edit_pt_bonus').value = pkg.bonus_hours;
      document.getElementById('edit_pt_price').value = pkg.price;
      document.getElementById('edit_pt_validity').value = pkg.validity_days || '';
      document.getElementById('edit_pt_active').checked = pkg.is_active == 1;
      document.getElementById('editTypeModal').classList.remove('hidden');
    }

    // ── Delete package type ───────────────────────────────────
    function deletePkgType(id) {
      swalDelete('ลบประเภทแพ็กเกจนี้?', 'การกระทำนี้ไม่สามารถย้อนกลับได้', function() {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `<input type="hidden" name="action" value="delete_pkg_type"><input type="hidden" name="pt_id" value="${id}">`;
        document.body.appendChild(form);
        form.submit();
      });
    }

    // ── Edit customer ─────────────────────────────────────────
    function editCustomer(pkg) {
      document.getElementById('edit_cust_pkg_id').value = pkg.id;
      document.getElementById('edit_cust_name').value = pkg.customer_name;
      document.getElementById('edit_cust_phone').value = pkg.customer_phone;
      document.getElementById('editCustomerModal').classList.remove('hidden');
    }

    // ── Delete package ────────────────────────────────────────
    function deletePackage(id) {
      swalDelete('ลบแพ็กเกจนี้?', 'การกระทำนี้ไม่สามารถย้อนกลับได้', function() {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `<input type="hidden" name="action" value="delete_package"><input type="hidden" name="pkg_id" value="${id}">`;
        document.body.appendChild(form);
        form.submit();
      });
    }

    // ── Detail edit (retroactive) ─────────────────────────
    function openDetailEdit(mp) {
      document.getElementById('detailEditPkgId').value = mp.id;
      document.getElementById('detailEditCustomerName').textContent = mp.customer_name + ' · ' + mp.customer_phone;
      document.getElementById('detailEditHoursUsed').value = mp.hours_used;
      document.getElementById('detailEditHoursUsed').max   = mp.hours_total;
      document.getElementById('detailEditHoursMax').textContent = '/ ' + mp.hours_total + ' ชม.';
      document.getElementById('detailEditPurchaseDate').value = mp.purchase_date || '';
      document.getElementById('detailEditExpiryDate').value  = mp.expiry_date  || '';
      document.getElementById('detailEditNotes').value       = mp.notes        || '';
      const remaining = Math.max(0, parseInt(mp.hours_total) - parseInt(mp.hours_used));
      document.getElementById('detailEditPkgInfo').innerHTML =
        `<strong>${mp.type_name || 'แพ็กเกจ'}</strong> · รวม ${mp.hours_total} ชม. · ใช้ไป ${mp.hours_used} ชม. · <span class="${remaining <= 2 ? 'text-red-600 font-bold' : 'text-green-700 font-bold'}">เหลือ ${remaining} ชม.</span>`;
      document.getElementById('detailEditModal').classList.remove('hidden');
    }

    // ── Slip upload ───────────────────────────────────────
    let _slipFile = null;

    function openSlipUpload(pkgId, name) {
      _slipFile = null;
      document.getElementById('slipPkgId').value = pkgId;
      document.getElementById('slipModalCustomerName').textContent = name;
      document.getElementById('slipFileInput').value = '';
      document.getElementById('slipPreviewImg').classList.add('hidden');
      document.getElementById('slipDropContent').classList.remove('hidden');
      document.getElementById('slipStatus').classList.add('hidden');
      document.getElementById('slipUploadBtn').disabled = true;
      document.getElementById('slipUploadBtn').textContent = 'อัปโหลด';
      document.getElementById('slipModal').classList.remove('hidden');
    }

    function closeSlipModal() {
      document.getElementById('slipModal').classList.add('hidden');
    }

    function previewSlip(input) {
      if (!input.files || !input.files[0]) return;
      _slipFile = input.files[0];
      const reader = new FileReader();
      reader.onload = e => {
        const img = document.getElementById('slipPreviewImg');
        img.src = e.target.result;
        img.classList.remove('hidden');
        document.getElementById('slipDropContent').classList.add('hidden');
        document.getElementById('slipUploadBtn').disabled = false;
      };
      reader.readAsDataURL(_slipFile);
    }

    function handleSlipDrop(e) {
      e.preventDefault();
      document.getElementById('slipDropZone').classList.remove('border-blue-400', 'bg-blue-50/30');
      const file = e.dataTransfer.files[0];
      if (!file) return;
      if (!['image/jpeg','image/png','image/webp'].includes(file.type)) {
        showSlipStatus('รองรับเฉพาะ JPG, PNG, WebP', 'error');
        return;
      }
      const dt = new DataTransfer();
      dt.items.add(file);
      document.getElementById('slipFileInput').files = dt.files;
      previewSlip(document.getElementById('slipFileInput'));
    }

    function uploadSlip() {
      if (!_slipFile) return;
      const pkgId = document.getElementById('slipPkgId').value;
      const btn = document.getElementById('slipUploadBtn');
      btn.disabled = true;
      btn.textContent = 'กำลังอัปโหลด...';

      const fd = new FormData();
      fd.append('pkg_id', pkgId);
      fd.append('slip_file', _slipFile);

      fetch('/admin/upload_badminton_slip_ajax.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
          if (data.success) {
            showSlipStatus('อัปโหลดสลิปสำเร็จ!', 'success');
            setTimeout(() => { closeSlipModal(); location.reload(); }, 1200);
          } else {
            showSlipStatus(data.message || 'เกิดข้อผิดพลาด', 'error');
            btn.disabled = false;
            btn.textContent = 'อัปโหลด';
          }
        })
        .catch(() => {
          showSlipStatus('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์', 'error');
          btn.disabled = false;
          btn.textContent = 'อัปโหลด';
        });
    }

    function showSlipStatus(msg, type) {
      const el = document.getElementById('slipStatus');
      el.textContent = msg;
      el.className = 'text-sm text-center py-2 px-3 rounded-lg ' +
        (type === 'success' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700');
      el.classList.remove('hidden');
    }

    function viewSlip(url) {
      document.getElementById('slipLightboxImg').src = url;
      document.getElementById('slipLightbox').classList.remove('hidden');
    }
  </script>

</body>

</html>
