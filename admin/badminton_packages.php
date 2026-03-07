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
        <h1 class="text-2xl font-bold" style="color:#005691;">แพ็กเกจคอร์ตแบดมินตัน</h1>
        <p class="text-gray-500 text-sm">จัดการและขายแพ็กเกจให้ลูกค้า</p>
      </div>
      <div class="flex gap-2 flex-wrap">
        <button onclick="document.getElementById('addTypeModal').classList.remove('hidden')"
          class="flex items-center gap-1.5 px-4 py-2 text-sm border rounded-lg font-medium hover:opacity-90 transition-opacity"
          style="color:#005691;border-color:#005691;">
          + ประเภทแพ็กเกจ
        </button>
        <button onclick="document.getElementById('sellModal').classList.remove('hidden')"
          class="flex items-center gap-1.5 px-4 py-2 text-sm text-white rounded-lg font-medium hover:opacity-90 transition-opacity"
          style="background:#005691;">
          + ขายแพ็กเกจ
        </button>
      </div>
    </div>

    <!-- ── Stats ── -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
      <div class="bg-white rounded-lg p-4 shadow-sm border border-gray-200">
        <div class="text-xs text-gray-500 mb-1">แพ็กเกจทั้งหมด</div>
        <div class="text-2xl font-bold" style="color:#005691;"><?= number_format($stats['total_pkgs'] ?? 0) ?></div>
      </div>
      <div class="bg-white rounded-lg p-4 shadow-sm border border-gray-200">
        <div class="text-xs text-gray-500 mb-1">แพ็กเกจยังใช้ได้</div>
        <div class="text-2xl font-bold text-green-600"><?= number_format($stats['active_pkgs'] ?? 0) ?></div>
      </div>
      <div class="bg-white rounded-lg p-4 shadow-sm border border-gray-200">
        <div class="text-xs text-gray-500 mb-1">ชั่วโมงเหลือรวม</div>
        <div class="text-2xl font-bold text-blue-600"><?= number_format($stats['total_remaining'] ?? 0) ?> ชม.</div>
      </div>
    </div>

    <!-- ── Tabs ── -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
      <div class="border-b border-gray-200">
        <div class="flex">
          <button onclick="switchTab('types')" id="tab-types"
            class="px-6 py-3 text-sm font-medium border-b-2 transition-colors"
            style="color:#005691;border-color:#005691;">
            ประเภทแพ็กเกจ
          </button>
          <button onclick="switchTab('members')" id="tab-members"
            class="px-6 py-3 text-sm font-medium text-gray-500 border-b-2 border-transparent hover:text-gray-700 transition-colors">
            แพ็กเกจสมาชิก
          </button>
        </div>
      </div>

      <div class="p-4">
        <!-- ── Tab 1: ประเภทแพ็กเกจ ── -->
        <div id="content-types">
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                  <th class="px-4 py-3 text-left font-semibold text-gray-600">ชื่อแพ็กเกจ</th>
                  <th class="px-4 py-3 text-center font-semibold text-gray-600">ชั่วโมง</th>
                  <th class="px-4 py-3 text-center font-semibold text-gray-600">โบนัส</th>
                  <th class="px-4 py-3 text-center font-semibold text-gray-600">รวม</th>
                  <th class="px-4 py-3 text-right font-semibold text-gray-600">ราคา</th>
                  <th class="px-4 py-3 text-center font-semibold text-gray-600">อายุ (วัน)</th>
                  <th class="px-4 py-3 text-center font-semibold text-gray-600">สถานะ</th>
                  <th class="px-4 py-3 text-center font-semibold text-gray-600">จัดการ</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($pkgTypes as $pt):
                  $total = $pt['hours_total'] + $pt['bonus_hours'];
                ?>
                <tr class="border-b border-gray-100 hover:bg-gray-50">
                  <td class="px-4 py-3 font-medium" style="color:#005691;"><?= htmlspecialchars($pt['name']) ?></td>
                  <td class="px-4 py-3 text-center text-gray-600"><?= $pt['hours_total'] ?></td>
                  <td class="px-4 py-3 text-center text-green-600">+<?= $pt['bonus_hours'] ?></td>
                  <td class="px-4 py-3 text-center font-semibold"><?= $total ?></td>
                  <td class="px-4 py-3 text-right text-gray-900">฿<?= number_format($pt['price'], 2) ?></td>
                  <td class="px-4 py-3 text-center text-gray-600"><?= $pt['validity_days'] ?: '—' ?></td>
                  <td class="px-4 py-3 text-center">
                    <?php if ($pt['is_active']): ?>
                      <span class="px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs">เปิด</span>
                    <?php else: ?>
                      <span class="px-2 py-1 bg-gray-100 text-gray-500 rounded-full text-xs">ปิด</span>
                    <?php endif; ?>
                  </td>
                  <td class="px-4 py-3 text-center">
                    <button onclick="editPkgType(<?= htmlspecialchars(json_encode($pt), ENT_QUOTES) ?>)"
                      class="text-blue-600 hover:text-blue-800 text-xs mr-2">แก้ไข</button>
                    <button onclick="deletePkgType(<?= $pt['id'] ?>)"
                      class="text-red-600 hover:text-red-800 text-xs">ลบ</button>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- ── Tab 2: แพ็กเกจสมาชิก ── -->
        <div id="content-members" class="hidden">
          <div class="mb-3">
            <input type="text" id="searchBox" placeholder="ค้นหา ชื่อ, เบอร์โทร..." onkeyup="filterPackages()"
              class="w-full md:w-64 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                  <th class="px-4 py-3 text-left font-semibold text-gray-600">ลูกค้า</th>
                  <th class="px-4 py-3 text-left font-semibold text-gray-600">แพ็กเกจ</th>
                  <th class="px-4 py-3 text-center font-semibold text-gray-600">ใช้แล้ว</th>
                  <th class="px-4 py-3 text-center font-semibold text-gray-600">เหลือ</th>
                  <th class="px-4 py-3 text-center font-semibold text-gray-600">วันซื้อ</th>
                  <th class="px-4 py-3 text-center font-semibold text-gray-600">วันหมดอายุ</th>
                  <th class="px-4 py-3 text-center font-semibold text-gray-600">สถานะ</th>
                  <th class="px-4 py-3 text-center font-semibold text-gray-600">จัดการ</th>
                </tr>
              </thead>
              <tbody id="pkgTableBody">
                <?php foreach ($memberPkgs as $mp): ?>
                <tr class="border-b border-gray-100 hover:bg-gray-50 pkg-row"
                    data-name="<?= htmlspecialchars($mp['customer_name']) ?>"
                    data-phone="<?= htmlspecialchars($mp['customer_phone']) ?>">
                  <td class="px-4 py-3">
                    <div class="font-medium" style="color:#005691;"><?= htmlspecialchars($mp['customer_name']) ?></div>
                    <div class="text-xs text-gray-500"><?= htmlspecialchars($mp['customer_phone']) ?></div>
                  </td>
                  <td class="px-4 py-3 text-gray-700"><?= htmlspecialchars($mp['type_name']) ?></td>
                  <td class="px-4 py-3 text-center text-gray-600"><?= $mp['hours_used'] ?> / <?= $mp['hours_total'] ?></td>
                  <td class="px-4 py-3 text-center font-semibold <?= $mp['remaining'] > 0 ? 'text-green-600' : 'text-gray-400' ?>">
                    <?= $mp['remaining'] ?> ชม.
                  </td>
                  <td class="px-4 py-3 text-center text-gray-600"><?= date('d/m/Y', strtotime($mp['purchase_date'])) ?></td>
                  <td class="px-4 py-3 text-center text-gray-600">
                    <?= $mp['expiry_date'] ? date('d/m/Y', strtotime($mp['expiry_date'])) : '—' ?>
                  </td>
                  <td class="px-4 py-3 text-center">
                    <?php if ($mp['pkg_status'] === 'active'): ?>
                      <span class="px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs">ใช้ได้</span>
                    <?php elseif ($mp['pkg_status'] === 'expired'): ?>
                      <span class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded-full text-xs">หมดอายุ</span>
                    <?php else: ?>
                      <span class="px-2 py-1 bg-gray-100 text-gray-500 rounded-full text-xs">หมด</span>
                    <?php endif; ?>
                  </td>
                  <td class="px-4 py-3 text-center">
                    <button onclick="editCustomer(<?= htmlspecialchars(json_encode($mp), ENT_QUOTES) ?>)"
                      class="text-blue-600 hover:text-blue-800 text-xs mr-2">แก้ไข</button>
                    <button onclick="deletePackage(<?= $mp['id'] ?>)"
                      class="text-red-600 hover:text-red-800 text-xs">ลบ</button>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

      </div>
    </div>

  </div>

  <!-- ══════════════════════════════════════════════════════════ -->
  <!-- Modal: เพิ่มประเภทแพ็กเกจ -->
  <!-- ══════════════════════════════════════════════════════════ -->
  <div id="addTypeModal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md" onclick="event.stopPropagation();">
      <div class="px-5 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold" style="color:#005691;">เพิ่มประเภทแพ็กเกจ</h3>
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
            style="background:#005691;">บันทึก</button>
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
        <h3 class="text-lg font-semibold" style="color:#005691;">แก้ไขประเภทแพ็กเกจ</h3>
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
            style="background:#005691;">บันทึก</button>
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
        <h3 class="text-lg font-semibold" style="color:#005691;">ขายแพ็กเกจให้ลูกค้า</h3>
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
            style="background:#005691;">ขายแพ็กเกจ</button>
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
        <h3 class="text-lg font-semibold" style="color:#005691;">แก้ไขข้อมูลลูกค้า</h3>
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
            style="background:#005691;">บันทึก</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════════════════ -->
  <!-- JavaScript -->
  <!-- ══════════════════════════════════════════════════════════ -->
  <script>
    // Tab switching
    function switchTab(tab) {
      const tabs = ['types', 'members'];
      tabs.forEach(t => {
        document.getElementById(`tab-${t}`).style.color = t === tab ? '#005691' : '#6b7280';
        document.getElementById(`tab-${t}`).style.borderColor = t === tab ? '#005691' : 'transparent';
        document.getElementById(`content-${t}`).classList.toggle('hidden', t !== tab);
      });
    }

    // Edit package type
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

    // Delete package type
    function deletePkgType(id) {
      swalDelete('ลบประเภทแพ็กเกจนี้?', 'การกระทำนี้ไม่สามารถย้อนกลับได้', function() {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `<input type="hidden" name="action" value="delete_pkg_type"><input type="hidden" name="pt_id" value="${id}">`;
        document.body.appendChild(form);
        form.submit();
      });
    }

    // Edit customer
    function editCustomer(pkg) {
      document.getElementById('edit_cust_pkg_id').value = pkg.id;
      document.getElementById('edit_cust_name').value = pkg.customer_name;
      document.getElementById('edit_cust_phone').value = pkg.customer_phone;
      document.getElementById('editCustomerModal').classList.remove('hidden');
    }

    // Delete package
    function deletePackage(id) {
      swalDelete('ลบแพ็กเกจนี้?', 'การกระทำนี้ไม่สามารถย้อนกลับได้', function() {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `<input type="hidden" name="action" value="delete_package"><input type="hidden" name="pkg_id" value="${id}">`;
        document.body.appendChild(form);
        form.submit();
      });
    }

    // Filter packages
    function filterPackages() {
      const search = document.getElementById('searchBox').value.toLowerCase();
      const rows = document.querySelectorAll('.pkg-row');
      rows.forEach(row => {
        const name = row.dataset.name.toLowerCase();
        const phone = row.dataset.phone.toLowerCase();
        row.style.display = (name.includes(search) || phone.includes(search)) ? '' : 'none';
      });
    }

    // Close modals on click outside
    document.querySelectorAll('.fixed').forEach(modal => {
      modal.addEventListener('click', e => {
        if (e.target === modal) modal.classList.add('hidden');
      });
    });
  </script>

</body>

</html>
