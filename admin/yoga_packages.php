<?php
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: /auth/login.php'); exit;
}

$today   = date('Y-m-d');
$adminId = $_SESSION['user']['id'];
$success = $error = '';

// ──────────────────────────────────────────────────────────
// POST handlers
// ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── ขายแพ็กเกจให้สมาชิก ──────────────────────────────
    if ($action === 'sell_package') {
        $name   = trim($_POST['student_name'] ?? '');
        $phone  = trim($_POST['student_phone'] ?? '');
        $typeId = (int)$_POST['yoga_package_type_id'];
        $pdate  = $_POST['purchase_date'] ?? $today;
        $notes  = trim($_POST['notes'] ?? '');

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
        $pkgId = (int)$_POST['pkg_id'];
        $pdo->prepare("DELETE FROM member_yoga_packages WHERE id=?")->execute([$pkgId]);
        $success = 'ลบแพ็กเกจแล้ว';
    }

    // ── เพิ่มประเภทแพ็กเกจใหม่ ────────────────────────────
    elseif ($action === 'add_pkg_type') {
        $ptName   = trim($_POST['pt_name']     ?? '');
        $ptSess   = max(1, (int)($_POST['pt_sessions'] ?? 1));
        $ptBonus  = max(0, (int)($_POST['pt_bonus']    ?? 0));
        $ptPrice  = (float)($_POST['pt_price'] ?? 0);
        $ptValid  = ((int)($_POST['pt_validity'] ?? 0)) ?: null;
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
        $ptId     = (int)$_POST['pt_id'];
        $ptName   = trim($_POST['pt_name']     ?? '');
        $ptSess   = max(1, (int)($_POST['pt_sessions'] ?? 1));
        $ptBonus  = max(0, (int)($_POST['pt_bonus']    ?? 0));
        $ptPrice  = (float)($_POST['pt_price'] ?? 0);
        $ptValid  = ((int)($_POST['pt_validity'] ?? 0)) ?: null;
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
        $ptId = (int)$_POST['pt_id'];
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

// ── Search member packages ────────────────────────────────
$search = trim($_GET['search'] ?? '');
$where  = ['1=1'];
$params = [];
if (!empty($search)) {
    $where[]  = '(myp.student_name LIKE ? OR myp.student_phone LIKE ?)';
    $sp       = '%' . $search . '%';
    $params[] = $sp;
    $params[] = $sp;
}
$whereSQL = implode(' AND ', $where);

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
    WHERE $whereSQL
    ORDER BY
      CASE WHEN (myp.sessions_total - myp.sessions_used) > 0 AND (myp.expiry_date IS NULL OR myp.expiry_date >= ?) THEN 0 ELSE 1 END,
      myp.student_name ASC, myp.expiry_date ASC
");
$stmt->execute(array_merge([$today, $today], $params));
$memberPkgs = $stmt->fetchAll();

// ── Package types (ทั้งหมด รวม inactive) ─────────────────
$pkgTypes       = $pdo->query("SELECT * FROM yoga_package_types ORDER BY sessions_total ASC")->fetchAll();
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
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>* { font-family: 'Prompt', sans-serif !important; }</style>
</head>
<body style="background:#f8fafc;" class="min-h-screen">
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="max-w-7xl mx-auto px-4 py-6">

  <!-- ── Header ── -->
  <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
    <div>
      <h1 class="text-2xl font-bold" style="color:#005691;">แพ็กเกจโยคะสมาชิก</h1>
      <p class="text-gray-500 text-sm">จัดการและขายแพ็กเกจให้ลูกค้า</p>
    </div>
    <div class="flex gap-2 flex-wrap">
      <a href="/admin/yoga_classes.php"
         class="flex items-center gap-1.5 px-4 py-2 text-sm border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50">
        ตารางคลาส
      </a>
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

  <!-- Messages -->
  <?php if ($success): ?>
  <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4 text-sm">✅ <?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
  <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4 text-sm">❌ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- ── Stats ── -->
  <div class="grid grid-cols-3 gap-4 mb-5">
    <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
      <p class="text-xs text-gray-400 mb-1">แพ็กเกจทั้งหมด</p>
      <p class="text-2xl font-bold text-gray-900"><?= $stats['total_pkgs'] ?? 0 ?></p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
      <p class="text-xs text-gray-400 mb-1">ยังใช้งานได้</p>
      <p class="text-2xl font-bold" style="color:#005691;"><?= $stats['active_pkgs'] ?? 0 ?></p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
      <p class="text-xs text-gray-400 mb-1">ครั้งที่เหลือรวม</p>
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
              style="color:#005691;border-color:#005691;">+ เพิ่มประเภท</button>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full">
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
                <button onclick="openEditType(<?= htmlspecialchars(json_encode($pt, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>)"
                        class="text-xs px-2.5 py-1 rounded border border-gray-300 text-gray-600 hover:bg-gray-50 transition-colors">แก้ไข</button>
                <form method="post" class="inline" onsubmit="return confirm('ลบประเภทนี้?')">
                  <input type="hidden" name="action" value="delete_pkg_type">
                  <input type="hidden" name="pt_id" value="<?= $pt['id'] ?>">
                  <button type="submit" class="text-red-400 hover:text-red-600 text-sm" title="ลบ">🗑</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($pkgTypes)): ?>
          <tr><td colspan="8" class="px-4 py-8 text-center text-gray-400 text-sm">ยังไม่มีประเภทแพ็กเกจ</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ── Search ── -->
  <div class="bg-white rounded-xl border border-gray-200 p-3 mb-4">
    <form method="get" class="flex gap-2">
      <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
             placeholder="🔍 ค้นหาชื่อหรือเบอร์โทรสมาชิก..."
             class="flex-1 px-4 py-2.5 rounded-lg border border-gray-300 text-sm outline-none focus:border-blue-400">
      <button type="submit" class="px-5 py-2.5 text-sm text-white rounded-lg font-medium" style="background:#005691;">ค้นหา</button>
      <?php if ($search): ?>
      <a href="/admin/yoga_packages.php" class="px-4 py-2.5 text-sm border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50">ล้าง</a>
      <?php endif; ?>
    </form>
  </div>

  <!-- ── Member packages table ── -->
  <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <div class="px-4 py-3 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
      <span class="text-sm font-semibold text-gray-700">แพ็กเกจสมาชิก
        <span class="font-normal text-gray-400 ml-1">พบ <span class="font-bold" style="color:#005691;"><?= count($memberPkgs) ?></span> รายการ</span>
      </span>
    </div>
    <?php if (empty($memberPkgs)): ?>
    <div class="p-12 text-center text-gray-400">ยังไม่มีข้อมูลแพ็กเกจสมาชิก</div>
    <?php else: ?>
    <div class="overflow-x-auto">
      <table class="w-full">
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
            $statusBadge = match($mp['pkg_status']) {
              'active'  => ['text'=>'ใช้งานได้','bg'=>'bg-green-100','txt'=>'text-green-800'],
              'expired' => ['text'=>'หมดอายุ',  'bg'=>'bg-red-100',  'txt'=>'text-red-800'],
              'empty'   => ['text'=>'ครั้งหมด', 'bg'=>'bg-gray-100', 'txt'=>'text-gray-500'],
              default   => ['text'=>'—',        'bg'=>'bg-gray-100', 'txt'=>'text-gray-500'],
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
                <div class="h-full rounded-full <?= $remainPct > 30 ? 'bg-green-400' : 'bg-amber-400' ?>" style="width:<?= 100 - $remainPct ?>%"></div>
              </div>
            </td>
            <td class="px-4 py-3 text-center">
              <span class="text-xl font-bold <?= $mp['remaining'] <= 2 ? 'text-red-600' : 'text-green-600' ?>"><?= max(0, (int)$mp['remaining']) ?></span>
            </td>
            <td class="px-4 py-3 text-center text-sm text-gray-600">
              <?= $mp['expiry_date'] ? date('d/m/Y', strtotime($mp['expiry_date'])) : '—' ?>
            </td>
            <td class="px-4 py-3 text-center">
              <span class="text-xs px-2 py-1 rounded-full font-medium <?= $statusBadge['bg'] ?> <?= $statusBadge['txt'] ?>"><?= $statusBadge['text'] ?></span>
            </td>
            <td class="px-4 py-3 text-center">
              <form method="post" class="inline" onsubmit="return confirm('ลบแพ็กเกจนี้?')">
                <input type="hidden" name="action" value="delete_package">
                <input type="hidden" name="pkg_id" value="<?= $mp['id'] ?>">
                <button type="submit" class="text-red-400 hover:text-red-600 text-sm" title="ลบ">🗑</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
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
      <input type="hidden" name="pt_id"  id="ptId">
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
        <button type="submit" class="flex-1 py-2.5 text-white text-sm font-semibold rounded-lg hover:opacity-90" style="background:#005691;">
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
      <button onclick="document.getElementById('sellModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 text-xl">×</button>
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
          <option value="<?= $pt['id'] ?>"
                  data-price="<?= $pt['price'] ?>"
                  data-total="<?= $pt['sessions_total'] + $pt['bonus_sessions'] ?>"
                  data-validity="<?= $pt['validity_days'] ?? 0 ?>">
            <?= htmlspecialchars($pt['name']) ?> — ฿<?= number_format($pt['price'], 0) ?>
          </option>
          <?php endforeach; ?>
        </select>
        <div id="pkgPreview" class="mt-2 text-xs text-gray-500 bg-blue-50 border border-blue-100 rounded-lg px-3 py-2"></div>
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
        <button type="submit" class="flex-1 py-2.5 text-white text-sm font-semibold rounded-lg hover:opacity-90" style="background:#005691;">
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

<script>
// ── Package type preview ──────────────────────────────────
function updatePkgPreview() {
  const sel = document.getElementById('pkgTypeSelect');
  if (!sel) return;
  const opt = sel.options[sel.selectedIndex];
  if (!opt) return;
  const total = opt.dataset.total;
  const price = parseFloat(opt.dataset.price).toLocaleString('th-TH');
  const val   = parseInt(opt.dataset.validity);
  const expTxt = val > 0 ? `· ใช้ได้ ${val} วัน` : '· ไม่หมดอายุ';
  document.getElementById('pkgPreview').textContent = `✔ ${total} ครั้ง ${expTxt} · ราคา ฿${price}`;
}
document.addEventListener('DOMContentLoaded', updatePkgPreview);

// ── Package type modal ────────────────────────────────────
function closeTypeModal() {
  document.getElementById('addTypeModal').classList.add('hidden');
  // reset form
  document.getElementById('typeAction').value = 'add_pkg_type';
  document.getElementById('addTypeTitle').textContent  = 'เพิ่มประเภทแพ็กเกจ';
  document.getElementById('ptId').value       = '';
  document.getElementById('ptName').value     = '';
  document.getElementById('ptSessions').value = '10';
  document.getElementById('ptBonus').value    = '0';
  document.getElementById('ptPrice').value    = '';
  document.getElementById('ptValidity').value = '';
  document.getElementById('ptActive').checked = true;
}

function openEditType(pt) {
  document.getElementById('typeAction').value  = 'edit_pkg_type';
  document.getElementById('addTypeTitle').textContent   = 'แก้ไขประเภทแพ็กเกจ';
  document.getElementById('ptId').value        = pt.id;
  document.getElementById('ptName').value      = pt.name;
  document.getElementById('ptSessions').value  = pt.sessions_total;
  document.getElementById('ptBonus').value     = pt.bonus_sessions;
  document.getElementById('ptPrice').value     = pt.price;
  document.getElementById('ptValidity').value  = pt.validity_days || '';
  document.getElementById('ptActive').checked  = pt.is_active == 1;
  document.getElementById('addTypeModal').classList.remove('hidden');
}
</script>
</body>
</html>
