<?php
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../config/db.php';

require_permission('yoga_packages');

$today = date('Y-m-d');
$adminId = $_SESSION['user']['id'];
$success = $error = '';

// ──────────────────────────────────────────────────────────
// POST handlers
// ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  // ── ขายแพ็กเกจให้สมาชิก ──────────────────────────────
  if ($action === 'sell_package') {
    $name = trim($_POST['student_name'] ?? '');
    $phone = trim($_POST['student_phone'] ?? '');
    $typeId = (int) $_POST['yoga_package_type_id'];
    $pdate = $_POST['purchase_date'] ?? $today;
    $notes = trim($_POST['notes'] ?? '');

    if (empty($name) || empty($phone) || !$typeId) {
      $error = 'กรุณากรอกข้อมูลให้ครบ';
    } else {
      $typeRow = $pdo->prepare("SELECT * FROM yoga_package_types WHERE id=? AND is_active=1");
      $typeRow->execute([$typeId]);
      $pkg = $typeRow->fetch();
      if (!$pkg) {
        $error = 'ไม่พบแพ็กเกจนี้';
      } else {
        $totalSessions = $pkg['sessions_total'] + $pkg['bonus_sessions'];
        $expiry = null;
        if ($pkg['validity_days']) {
          $expiry = (new DateTime($pdate))->modify('+' . $pkg['validity_days'] . ' day')->format('Y-m-d');
        }
        $stmt = $pdo->prepare("
                    INSERT INTO member_yoga_packages
                      (student_name, student_phone, yoga_package_type_id, sessions_total, sessions_used, purchase_date, expiry_date, notes, created_by)
                    VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?)
                ");
        $stmt->execute([$name, $phone, $typeId, $totalSessions, $pdate, $expiry, $notes ?: null, $adminId]);
        $success = "ขายแพ็กเกจ \"{$pkg['name']}\" ให้ {$name} เรียบร้อย ({$totalSessions} ครั้ง" . ($expiry ? " หมด {$expiry}" : '') . ')';
      }
    }
  }

  // ── ลบแพ็กเกจสมาชิก ──────────────────────────────────
  elseif ($action === 'delete_package') {
    $pkgId = (int) $_POST['pkg_id'];
    $pdo->prepare("DELETE FROM member_yoga_packages WHERE id=?")->execute([$pkgId]);
    $success = 'ลบแพ็กเกจแล้ว';
  }

  // ── แก้ไขชื่อ/เบอร์ลูกค้า ────────────────────
  elseif ($action === 'edit_customer') {
    $pkgId = (int) $_POST['pkg_id'];
    $name = trim($_POST['student_name'] ?? '');
    $phone = trim($_POST['student_phone'] ?? '');
    if (empty($name) || empty($phone)) {
      $error = 'กรุณากรอกชื่อและเบอร์โทร';
    } else {
      $pdo->prepare("UPDATE member_yoga_packages SET student_name=?, student_phone=? WHERE id=?")
        ->execute([$name, $phone, $pkgId]);
      $success = "อัปเดตข้อมูลลูกค้า \"{$name}\" เรียบร้อย";
    }
  }

  // ── เพิ่มประเภทแพ็กเกจใหม่ ────────────────────────────
  elseif ($action === 'add_pkg_type') {
    $ptName = trim($_POST['pt_name'] ?? '');
    $ptSess = max(1, (int) ($_POST['pt_sessions'] ?? 1));
    $ptBonus = max(0, (int) ($_POST['pt_bonus'] ?? 0));
    $ptPrice = (float) ($_POST['pt_price'] ?? 0);
    $ptValid = ((int) ($_POST['pt_validity'] ?? 0)) ?: null;
    $ptActive = isset($_POST['pt_active']) ? 1 : 0;
    if (empty($ptName) || $ptPrice <= 0) {
      $error = 'กรุณากรอกชื่อและราคา';
    } else {
      $pdo->prepare("
                INSERT INTO yoga_package_types (name, sessions_total, bonus_sessions, price, validity_days, is_active)
                VALUES (?,?,?,?,?,?)
            ")->execute([$ptName, $ptSess, $ptBonus, $ptPrice, $ptValid, $ptActive]);
      $success = "เพิ่มแพ็กเกจ \"{$ptName}\" เรียบร้อย";
    }
  }

  // ── แก้ไขประเภทแพ็กเกจ ────────────────────────────────
  elseif ($action === 'edit_pkg_type') {
    $ptId = (int) $_POST['pt_id'];
    $ptName = trim($_POST['pt_name'] ?? '');
    $ptSess = max(1, (int) ($_POST['pt_sessions'] ?? 1));
    $ptBonus = max(0, (int) ($_POST['pt_bonus'] ?? 0));
    $ptPrice = (float) ($_POST['pt_price'] ?? 0);
    $ptValid = ((int) ($_POST['pt_validity'] ?? 0)) ?: null;
    $ptActive = isset($_POST['pt_active']) ? 1 : 0;
    if (empty($ptName) || $ptPrice <= 0) {
      $error = 'กรุณากรอกชื่อและราคา';
    } else {
      $pdo->prepare("
                UPDATE yoga_package_types
                SET name=?, sessions_total=?, bonus_sessions=?, price=?, validity_days=?, is_active=?
                WHERE id=?
            ")->execute([$ptName, $ptSess, $ptBonus, $ptPrice, $ptValid, $ptActive, $ptId]);
      $success = "อัปเดตแพ็กเกจ \"{$ptName}\" เรียบร้อย";
    }
  }

  // ── ลบประเภทแพ็กเกจ ───────────────────────────────────
  elseif ($action === 'delete_pkg_type') {
    $ptId = (int) $_POST['pt_id'];
    $inUse = $pdo->prepare("SELECT COUNT(*) FROM member_yoga_packages WHERE yoga_package_type_id=?");
    $inUse->execute([$ptId]);
    if ($inUse->fetchColumn() > 0) {
      $error = 'ไม่สามารถลบได้ เพราะมีสมาชิกใช้แพ็กเกจนี้อยู่ (ปิดการใช้งานแทน)';
    } else {
      $pdo->prepare("DELETE FROM yoga_package_types WHERE id=?")->execute([$ptId]);
      $success = 'ลบประเภทแพ็กเกจแล้ว';
    }
  }
}

// ── Load all member packages (client-side filter/pagination) ──
$stmt = $pdo->prepare("
    SELECT myp.*, ypt.name AS type_name, ypt.price,
           (myp.sessions_total - myp.sessions_used) AS remaining,
           CASE
             WHEN (myp.sessions_total - myp.sessions_used) <= 0 THEN 'empty'
             WHEN myp.expiry_date IS NOT NULL AND myp.expiry_date < ? THEN 'expired'
             ELSE 'active'
           END AS pkg_status
    FROM member_yoga_packages myp
    JOIN yoga_package_types ypt ON ypt.id = myp.yoga_package_type_id
    ORDER BY
      CASE WHEN (myp.sessions_total - myp.sessions_used) > 0 AND (myp.expiry_date IS NULL OR myp.expiry_date >= ?) THEN 0 ELSE 1 END,
      myp.student_name ASC, myp.expiry_date ASC
");
$stmt->execute([$today, $today]);
$memberPkgs = $stmt->fetchAll();

// ── Package types (ทั้งหมด รวม inactive) ─────────────────
$pkgTypes = $pdo->query("SELECT * FROM yoga_package_types ORDER BY sessions_total ASC")->fetchAll();
$pkgTypesActive = array_filter($pkgTypes, fn($p) => $p['is_active']);

// ── Stats ─────────────────────────────────────────────────
$stats = $pdo->query("
    SELECT
      COUNT(*) AS total_pkgs,
      SUM(CASE WHEN (sessions_total - sessions_used) > 0 AND (expiry_date IS NULL OR expiry_date >= CURDATE()) THEN 1 ELSE 0 END) AS active_pkgs,
      SUM(sessions_total - sessions_used) AS total_remaining
    FROM member_yoga_packages
")->fetch();
?>
<!doctype html>
<html lang="th">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>แพ็กเกจโยคะ – BARGAIN SPORT</title>
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
        <h1 class="text-2xl font-bold" style="color:#D32F2F;">แพ็กเกจโยคะสมาชิก</h1>
        <p class="text-gray-500 text-sm">จัดการและขายแพ็กเกจให้ลูกค้า</p>
      </div>
      <div class="flex gap-2 flex-wrap">
        <a href="/admin/yoga_classes.php"
          class="flex items-center gap-1.5 px-4 py-2 text-sm border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50">
          ตารางคลาส
        </a>
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
        <p class="text-xs text-gray-400 mb-1">ครั้งที่เหลือรวม</p>
        <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['total_remaining'] ?? 0) ?></p>
      </div>
    </div>

    <!-- ── Package Types Table ── -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden mb-5">
      <!-- Card header -->
      <div class="px-4 py-3 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
        <div>
          <span class="text-sm font-semibold text-gray-700">ประเภทแพ็กเกจ</span>
          <span class="text-xs text-gray-400 ml-2"><?= count($pkgTypes) ?> รายการ</span>
        </div>
        <button onclick="document.getElementById('addTypeModal').classList.remove('hidden')"
          class="text-xs px-3 py-1.5 rounded-lg border font-medium hover:bg-blue-50 transition-colors"
          style="color:#D32F2F;border-color:#D32F2F;">+ เพิ่มประเภท</button>
      </div>
      <!-- Search + Per page -->
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
      <!-- Table -->
      <div class="overflow-x-auto">
        <table class="w-full" id="pkgTypesTable">
          <thead style="background:#FAFAFA;">
            <tr>
              <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600">ชื่อแพ็กเกจ</th>
              <th class="px-4 py-2.5 text-center text-xs font-semibold text-gray-600">ครั้งหลัก</th>
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
                <td class="px-4 py-3 text-center text-sm text-gray-700"><?= $pt['sessions_total'] ?></td>
                <td class="px-4 py-3 text-center text-sm">
                  <?= $pt['bonus_sessions'] > 0
                    ? '<span class="text-green-600 font-medium">+' . $pt['bonus_sessions'] . '</span>'
                    : '<span class="text-gray-300">—</span>' ?>
                </td>
                <td class="px-4 py-3 text-center text-sm font-semibold text-gray-800">
                  <?= $pt['sessions_total'] + $pt['bonus_sessions'] ?>
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
                    <button
                      onclick="openEditType(<?= htmlspecialchars(json_encode($pt, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>)"
                      class="text-xs px-2.5 py-1 rounded border border-gray-300 text-gray-600 hover:bg-gray-50 transition-colors">แก้ไข</button>
                    <form method="post" class="inline" id="delPkgType_<?= $pt['id'] ?>">
                      <input type="hidden" name="action" value="delete_pkg_type">
                      <input type="hidden" name="pt_id" value="<?= $pt['id'] ?>">
                      <button type="button" class="text-red-400 hover:text-red-600 text-sm" title="ลบ"
                        onclick="swalDelete(() => document.getElementById('delPkgType_<?= $pt['id'] ?>').submit(), '<?= htmlspecialchars($pt['name'], ENT_QUOTES) ?>', 'ยืนยันลบประเภทแพ็กเกจ?')">🗑</button>
                    </form>
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
      <!-- Pagination footer -->
      <div class="px-4 py-3 border-t border-gray-100 flex flex-col sm:flex-row items-center justify-between gap-2">
        <span class="text-xs text-gray-400" id="ptInfo"></span>
        <div class="flex gap-1 flex-wrap justify-end" id="ptPager"></div>
      </div>
    </div>

    <!-- ── Member packages table ── -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <!-- Card header -->
      <div class="px-4 py-3 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
        <span class="text-sm font-semibold text-gray-700">แพ็กเกจสมาชิก
          <span class="font-normal text-gray-400 ml-1">ทั้งหมด <span class="font-bold"
              style="color:#D32F2F;"><?= count($memberPkgs) ?></span> รายการ</span>
        </span>
      </div>
      <!-- Search + Per page -->
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
                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600">สถานะ</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600">จัดการ</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <?php foreach ($memberPkgs as $mp):
                $statusBadge = match ($mp['pkg_status']) {
                  'active' => ['text' => 'ใช้งานได้', 'bg' => 'bg-green-100', 'txt' => 'text-green-800'],
                  'expired' => ['text' => 'หมดอายุ', 'bg' => 'bg-red-100', 'txt' => 'text-red-800'],
                  'empty' => ['text' => 'ครั้งหมด', 'bg' => 'bg-gray-100', 'txt' => 'text-gray-500'],
                  default => ['text' => '—', 'bg' => 'bg-gray-100', 'txt' => 'text-gray-500'],
                };
                $remainPct = $mp['sessions_total'] > 0 ? round($mp['remaining'] / $mp['sessions_total'] * 100) : 0;
                ?>
                <tr class="hover:bg-gray-50 <?= $mp['pkg_status'] !== 'active' ? 'opacity-60' : '' ?>">
                  <td class="px-4 py-3">
                    <p class="font-medium text-gray-900"><?= htmlspecialchars($mp['student_name']) ?></p>
                    <p class="text-xs text-gray-500"><?= htmlspecialchars($mp['student_phone']) ?></p>
                  </td>
                  <td class="px-4 py-3">
                    <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($mp['type_name']) ?></p>
                    <p class="text-xs text-gray-400">ซื้อ <?= date('d/m/Y', strtotime($mp['purchase_date'])) ?></p>
                  </td>
                  <td class="px-4 py-3 text-center">
                    <span class="font-semibold text-gray-700"><?= $mp['sessions_used'] ?></span>
                    <span class="text-xs text-gray-400">/ <?= $mp['sessions_total'] ?></span>
                    <div class="w-full h-1 bg-gray-200 rounded-full mt-1 overflow-hidden">
                      <div class="h-full rounded-full <?= $remainPct > 30 ? 'bg-green-400' : 'bg-amber-400' ?>"
                        style="width:<?= 100 - $remainPct ?>%"></div>
                    </div>
                  </td>
                  <td class="px-4 py-3 text-center">
                    <span
                      class="text-xl font-bold <?= $mp['remaining'] <= 2 ? 'text-red-600' : 'text-green-600' ?>"><?= max(0, (int) $mp['remaining']) ?></span>
                  </td>
                  <td class="px-4 py-3 text-center text-sm text-gray-600">
                    <?= $mp['expiry_date'] ? date('d/m/Y', strtotime($mp['expiry_date'])) : '—' ?>
                  </td>
                  <td class="px-4 py-3 text-center">
                    <span
                      class="text-xs px-2 py-1 rounded-full font-medium <?= $statusBadge['bg'] ?> <?= $statusBadge['txt'] ?>"><?= $statusBadge['text'] ?></span>
                  </td>
                  <td class="px-4 py-3 text-center">
                    <div class="flex items-center justify-center gap-2">
                      <button
                        onclick="openEditCustomer(<?= $mp['id'] ?>, '<?= htmlspecialchars(addslashes($mp['student_name'])) ?>', '<?= htmlspecialchars(addslashes($mp['student_phone'])) ?>')"
                        class="text-xs px-2.5 py-1 rounded border border-gray-300 text-gray-600 hover:bg-gray-50 transition-colors">แก้ไข</button>
                      <form method="post" class="inline" onsubmit="return confirm('ลบแพ็กเกจนี้?')">
                        <input type="hidden" name="action" value="delete_package">
                        <input type="hidden" name="pkg_id" value="<?= $mp['id'] ?>">
                        <button type="submit" class="text-red-400 hover:text-red-600 text-sm" title="ลบ">🗑</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <!-- Pagination footer -->
        <div class="px-4 py-3 border-t border-gray-100 flex flex-col sm:flex-row items-center justify-between gap-2">
          <span class="text-xs text-gray-400" id="mpInfo"></span>
          <div class="flex gap-1 flex-wrap justify-end" id="mpPager"></div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════════════════
     Modal: เพิ่มประเภทแพ็กเกจ
══════════════════════════════════════════════════════════ -->
  <div id="addTypeModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl">
      <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
        <h3 class="text-lg font-bold text-gray-900" id="addTypeTitle">เพิ่มประเภทแพ็กเกจ</h3>
        <button onclick="closeTypeModal()" class="text-gray-400 hover:text-gray-600 text-xl">×</button>
      </div>
      <form method="post" class="px-6 py-4 space-y-4" id="typeForm">
        <input type="hidden" name="action" value="add_pkg_type" id="typeAction">
        <input type="hidden" name="pt_id" id="ptId">
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">ชื่อแพ็กเกจ</label>
          <input type="text" name="pt_name" id="ptName" required placeholder="เช่น 10 ครั้ง + แถม 2"
            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm outline-none focus:border-blue-400">
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">จำนวนครั้งหลัก</label>
            <input type="number" name="pt_sessions" id="ptSessions" value="10" min="1" required
              class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm outline-none focus:border-blue-400">
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">ครั้งโบนัส (แถม)</label>
            <input type="number" name="pt_bonus" id="ptBonus" value="0" min="0"
              class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm outline-none focus:border-blue-400">
          </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">ราคา (บาท)</label>
            <input type="number" name="pt_price" id="ptPrice" value="" min="1" step="0.01" required placeholder="2500"
              class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm outline-none focus:border-blue-400">
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">อายุแพ็กเกจ (วัน)</label>
            <input type="number" name="pt_validity" id="ptValidity" value="" min="0" placeholder="ว่าง = ไม่จำกัด"
              class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm outline-none focus:border-blue-400">
          </div>
        </div>
        <div class="flex items-center gap-2">
          <input type="checkbox" name="pt_active" id="ptActive" value="1" checked class="w-4 h-4 accent-blue-600">
          <label for="ptActive" class="text-sm text-gray-700">เปิดใช้งาน</label>
        </div>
        <div class="flex gap-3 pt-1">
          <button type="submit" class="flex-1 py-2.5 text-white text-sm font-semibold rounded-lg hover:opacity-90"
            style="background:#D32F2F;">
            บันทึก
          </button>
          <button type="button" onclick="closeTypeModal()"
            class="flex-1 py-2.5 text-gray-600 text-sm rounded-lg border border-gray-300 hover:bg-gray-50">
            ยกเลิก
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════════════════
     Modal: ขายแพ็กเกจ
══════════════════════════════════════════════════════════ -->
  <div id="sellModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl">
      <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
        <h3 class="text-lg font-bold text-gray-900">ขายแพ็กเกจโยคะ</h3>
        <button onclick="document.getElementById('sellModal').classList.add('hidden')"
          class="text-gray-400 hover:text-gray-600 text-xl">×</button>
      </div>
      <form method="post" class="px-6 py-4 space-y-4">
        <input type="hidden" name="action" value="sell_package">
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">ชื่อลูกค้า</label>
          <input type="text" name="student_name" required placeholder="ชื่อ-นามสกุล"
            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm outline-none focus:border-blue-400">
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">เบอร์โทร</label>
          <input type="tel" name="student_phone" required placeholder="0812345678"
            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm outline-none focus:border-blue-400">
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">แพ็กเกจ</label>
          <select name="yoga_package_type_id" required id="pkgTypeSelect" onchange="updatePkgPreview()"
            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm outline-none focus:border-blue-400">
            <?php foreach ($pkgTypesActive as $pt): ?>
              <option value="<?= $pt['id'] ?>" data-price="<?= $pt['price'] ?>"
                data-total="<?= $pt['sessions_total'] + $pt['bonus_sessions'] ?>"
                data-validity="<?= $pt['validity_days'] ?? 0 ?>">
                <?= htmlspecialchars($pt['name']) ?> — ฿<?= number_format($pt['price'], 0) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div id="pkgPreview"
            class="mt-2 text-xs text-gray-500 bg-blue-50 border border-blue-100 rounded-lg px-3 py-2"></div>
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">วันที่ซื้อ</label>
          <input type="date" name="purchase_date" value="<?= $today ?>"
            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm outline-none focus:border-blue-400">
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">หมายเหตุ</label>
          <input type="text" name="notes" placeholder="ไม่บังคับ"
            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm outline-none focus:border-blue-400">
        </div>
        <div class="flex gap-3 pt-1">
          <button type="submit" class="flex-1 py-2.5 text-white text-sm font-semibold rounded-lg hover:opacity-90"
            style="background:#D32F2F;">
            ยืนยันขาย
          </button>
          <button type="button" onclick="document.getElementById('sellModal').classList.add('hidden')"
            class="flex-1 py-2.5 text-gray-600 text-sm rounded-lg border border-gray-300 hover:bg-gray-50">
            ยกเลิก
          </button>
        </div>
      </form>
    </div>
  </div>

  <?php include __DIR__ . '/../includes/footer.php'; ?>

  <!-- ═══════════════════════════════════════════════════════
     Modal: แก้ไขชื่อลูกค้า
═══════════════════════════════════════════════════════ -->
  <div id="editCustomerModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl max-w-sm w-full shadow-2xl">
      <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
        <h3 class="text-base font-bold text-gray-900">แก้ไขข้อมูลลูกค้า</h3>
        <button onclick="document.getElementById('editCustomerModal').classList.add('hidden')"
          class="text-gray-400 hover:text-gray-600 text-xl">×</button>
      </div>
      <form method="post" class="px-6 py-4 space-y-4">
        <input type="hidden" name="action" value="edit_customer">
        <input type="hidden" name="pkg_id" id="editPkgId">
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">ชื่อลูกค้า</label>
          <input type="text" name="student_name" id="editStudentName" required placeholder="ชื่อ-นามสกุล"
            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm outline-none focus:border-blue-400">
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">เบอร์โทร</label>
          <input type="tel" name="student_phone" id="editStudentPhone" required placeholder="0812345678"
            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm outline-none focus:border-blue-400">
        </div>
        <div class="flex gap-3 pt-1">
          <button type="submit" class="flex-1 py-2.5 text-white text-sm font-semibold rounded-lg hover:opacity-90"
            style="background:#D32F2F;">
            บันทึก
          </button>
          <button type="button" onclick="document.getElementById('editCustomerModal').classList.add('hidden')"
            class="flex-1 py-2.5 text-gray-600 text-sm rounded-lg border border-gray-300 hover:bg-gray-50">
            ยกเลิก
          </button>
        </div>
      </form>
    </div>
  </div>

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

        // Info text
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

        // Pager
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
      updatePkgPreview();
    });

    // ── Package type preview ──────────────────────────────────
    function updatePkgPreview() {
      const sel = document.getElementById('pkgTypeSelect');
      if (!sel) return;
      const opt = sel.options[sel.selectedIndex];
      if (!opt) return;
      const total = opt.dataset.total;
      const price = parseFloat(opt.dataset.price).toLocaleString('th-TH');
      const val = parseInt(opt.dataset.validity);
      const expTxt = val > 0 ? `· ใช้ได้ ${val} วัน` : '· ไม่หมดอายุ';
      document.getElementById('pkgPreview').textContent = `✔ ${total} ครั้ง ${expTxt} · ราคา ฿${price}`;
    }

    // ── Package type modal ────────────────────────────────────
    function closeTypeModal() {
      document.getElementById('addTypeModal').classList.add('hidden');
      document.getElementById('typeAction').value = 'add_pkg_type';
      document.getElementById('addTypeTitle').textContent = 'เพิ่มประเภทแพ็กเกจ';
      document.getElementById('ptId').value = '';
      document.getElementById('ptName').value = '';
      document.getElementById('ptSessions').value = '10';
      document.getElementById('ptBonus').value = '0';
      document.getElementById('ptPrice').value = '';
      document.getElementById('ptValidity').value = '';
      document.getElementById('ptActive').checked = true;
    }

    function openEditType(pt) {
      document.getElementById('typeAction').value = 'edit_pkg_type';
      document.getElementById('addTypeTitle').textContent = 'แก้ไขประเภทแพ็กเกจ';
      document.getElementById('ptId').value = pt.id;
      document.getElementById('ptName').value = pt.name;
      document.getElementById('ptSessions').value = pt.sessions_total;
      document.getElementById('ptBonus').value = pt.bonus_sessions;
      document.getElementById('ptPrice').value = pt.price;
      document.getElementById('ptValidity').value = pt.validity_days || '';
      document.getElementById('ptActive').checked = pt.is_active == 1;
      document.getElementById('addTypeModal').classList.remove('hidden');
    }

    // ── Edit customer name/phone ──────────────────────────
    function openEditCustomer(pkgId, name, phone) {
      document.getElementById('editPkgId').value = pkgId;
      document.getElementById('editStudentName').value = name;
      document.getElementById('editStudentPhone').value = phone;
      document.getElementById('editCustomerModal').classList.remove('hidden');
    }
  </script>
</body>

</html>
