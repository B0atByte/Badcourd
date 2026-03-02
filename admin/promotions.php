<?php
require_once __DIR__ . '/../auth/guard.php';
require_permission('promotions');
require_once __DIR__ . '/../config/db.php';

$success = $error = '';

// --- ADD ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
  $code = strtoupper(trim($_POST['code'] ?? ''));
  $name = trim($_POST['name'] ?? '');
  $discount_type = in_array($_POST['discount_type'] ?? '', ['percent', 'fixed']) ? $_POST['discount_type'] : 'percent';
  $discount_percent = (float) ($_POST['discount_percent'] ?? 0);
  $start_date = $_POST['start_date'] ?? '';
  $end_date = $_POST['end_date'] ?? '';
  $description = trim($_POST['description'] ?? '');
  $created_by = $_SESSION['user']['id'];

  if (!preg_match('/^[A-Z0-9]{3,30}$/', $code)) {
    $error = 'รหัสโปรโมชั่นต้องเป็นตัวอักษรภาษาอังกฤษหรือตัวเลข 3-30 ตัว ไม่มีช่องว่าง';
  } elseif (empty($name)) {
    $error = 'กรุณาระบุชื่อโปรโมชั่น';
  } elseif ($discount_type === 'percent' && ($discount_percent < 1 || $discount_percent > 100)) {
    $error = 'ส่วนลดต้องอยู่ระหว่าง 1-100%';
  } elseif ($discount_type === 'fixed' && ($discount_percent <= 0 || $discount_percent > 99999)) {
    $error = 'จำนวนเงินลดต้องอยู่ระหว่าง 1-99,999 บาท';
  } elseif (empty($start_date) || empty($end_date)) {
    $error = 'กรุณาระบุวันที่เริ่มต้นและสิ้นสุด';
  } elseif ($end_date < $start_date) {
    $error = 'วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่มต้น';
  } else {
    try {
      $stmt = $pdo->prepare('
                INSERT INTO promotions (code, name, discount_type, discount_percent, start_date, end_date, description, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ');
      $stmt->execute([$code, $name, $discount_type, $discount_percent, $start_date, $end_date, $description, $created_by]);
      header('Location: promotions.php?added=1');
      exit;
    } catch (PDOException $e) {
      $error = 'รหัสโปรโมชั่น "' . htmlspecialchars($code) . '" มีอยู่แล้ว กรุณาใช้รหัสอื่น';
    }
  }
}

// --- TOGGLE ACTIVE ---
if (isset($_GET['toggle'])) {
  $id = (int) $_GET['toggle'];
  $pdo->prepare('UPDATE promotions SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
  header('Location: promotions.php');
  exit;
}

// --- DELETE ---
if (isset($_GET['delete'])) {
  $id = (int) $_GET['delete'];
  $pdo->prepare('DELETE FROM promotions WHERE id = ?')->execute([$id]);
  header('Location: promotions.php?deleted=1');
  exit;
}

if (isset($_GET['added']))
  $success = 'เพิ่มโปรโมชั่นสำเร็จ';
if (isset($_GET['deleted']))
  $success = 'ลบโปรโมชั่นสำเร็จ';

$today = date('Y-m-d');

// --- STATS (all records, ignoring filters) ---
$allRows = $pdo->query("SELECT is_active, start_date, end_date FROM promotions")->fetchAll();
$total = count($allRows);
$active = 0;
$inactive = 0;
$expired = 0;
foreach ($allRows as $p) {
  if (!$p['is_active']) {
    $inactive++;
    continue;
  }
  if ($today > $p['end_date']) {
    $expired++;
    continue;
  }
  $active++;
}

// --- SEARCH / FILTER / PAGINATION ---
$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status_filter'] ?? '');
$per_page_raw = $_GET['per_page'] ?? '25';
$per_page = ($per_page_raw === 'all') ? 0 : (int) $per_page_raw;
if (!in_array($per_page, [0, 10, 25, 50, 100]))
  $per_page = 25;
$page = max(1, (int) ($_GET['page'] ?? 1));

$pWhere = ['1=1'];
$pParams = [];

if (!empty($search)) {
  $pWhere[] = '(p.code LIKE ? OR p.name LIKE ?)';
  $sp = '%' . $search . '%';
  $pParams[] = $sp;
  $pParams[] = $sp;
}
if ($status_filter === 'active') {
  $pWhere[] = 'p.is_active = 1 AND p.start_date <= ? AND p.end_date >= ?';
  $pParams[] = $today;
  $pParams[] = $today;
} elseif ($status_filter === 'expired') {
  $pWhere[] = 'p.is_active = 1 AND p.end_date < ?';
  $pParams[] = $today;
} elseif ($status_filter === 'inactive') {
  $pWhere[] = 'p.is_active = 0';
} elseif ($status_filter === 'upcoming') {
  $pWhere[] = 'p.is_active = 1 AND p.start_date > ?';
  $pParams[] = $today;
}
$pWhereClause = implode(' AND ', $pWhere);

// Count
$cntStmt = $pdo->prepare("SELECT COUNT(*) FROM promotions p WHERE $pWhereClause");
$cntStmt->execute($pParams);
$totalRecords = (int) $cntStmt->fetchColumn();

// Pagination calc
$totalPages = 1;
$offset = 0;
if ($per_page > 0) {
  $totalPages = max(1, (int) ceil($totalRecords / $per_page));
  $page = min($page, $totalPages);
  $offset = ($page - 1) * $per_page;
}

// --- FETCH (filtered + paginated) ---
$pQuery = "SELECT p.*, u.username AS creator FROM promotions p LEFT JOIN users u ON p.created_by = u.id WHERE $pWhereClause ORDER BY p.created_at DESC";
if ($per_page > 0)
  $pQuery .= " LIMIT $per_page OFFSET $offset";
$pStmt = $pdo->prepare($pQuery);
$pStmt->execute($pParams);
$promotions = $pStmt->fetchAll();
?>
<!doctype html>
<html lang="th">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
  <title>จัดการโปรโมชั่น - BARGAIN SPORT</title>
</head>

<body style="background:#FAFAFA;" class="min-h-screen">
  <?php include __DIR__ . '/../includes/header.php'; ?>

  <div class="max-w-6xl mx-auto px-4 py-8">

    <!-- Header -->
    <div class="mb-6">
      <h1 style="color:#005691;" class="text-2xl font-bold">จัดการโปรโมชั่น</h1>
      <p class="text-gray-500 text-sm mt-0.5">Admin Panel · กำหนดส่วนลด % สำหรับการจอง</p>
    </div>

    <!-- Flash messages -->
    <?php if ($success): ?>
      <div class="mb-4 bg-green-50 border border-green-200 rounded-xl px-4 py-3 text-green-700 text-sm"><?= $success ?>
      </div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="mb-4 bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-red-600 text-sm"><?= $error ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
      <div class="bg-white rounded-xl border border-gray-200 p-4">
        <p class="text-gray-400 text-xs mb-1">โปรโมชั่นทั้งหมด</p>
        <p style="color:#005691;" class="text-2xl font-bold"><?= $total ?></p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-4">
        <p class="text-gray-400 text-xs mb-1">ใช้งานอยู่</p>
        <p class="text-2xl font-bold text-green-600"><?= $active ?></p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-4">
        <p class="text-gray-400 text-xs mb-1">หมดอายุ</p>
        <p class="text-2xl font-bold text-gray-400"><?= $expired ?></p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-4">
        <p class="text-gray-400 text-xs mb-1">ปิดใช้งาน</p>
        <p class="text-2xl font-bold text-red-400"><?= $inactive ?></p>
      </div>
    </div>

    <!-- Add Form -->
    <div class="bg-white rounded-xl border border-gray-200 p-6 mb-5">
      <h2 style="color:#005691;" class="font-semibold mb-4 text-sm uppercase tracking-wide">เพิ่มโปรโมชั่นใหม่</h2>
      <form method="post" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">รหัสโปรโมชั่น (Code)</label>
          <input type="text" name="code" required maxlength="30" placeholder="เช่น STAFF15"
            oninput="this.value = this.value.toUpperCase()" style="text-transform:uppercase"
            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:border-[#E8F1F5] focus:ring-2 focus:ring-[#E8F1F5]/20 outline-none text-sm">
          <p class="text-xs text-gray-400 mt-1">ตัวอักษรภาษาอังกฤษ/ตัวเลข ไม่มีช่องว่าง (3-30 ตัว)</p>
        </div>

        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">ชื่อโปรโมชั่น</label>
          <input type="text" name="name" required maxlength="100" placeholder="เช่น พนักงาน 15%"
            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:border-[#E8F1F5] focus:ring-2 focus:ring-[#E8F1F5]/20 outline-none text-sm">
        </div>

        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">ประเภทส่วนลด</label>
          <div class="flex gap-4 py-2.5">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="radio" name="discount_type" value="percent" id="dtPercent" checked
                onchange="updateDiscountLabel()" class="accent-[#005691]">
              <span class="text-sm text-gray-700">ลดเป็น %</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="radio" name="discount_type" value="fixed" id="dtFixed" onchange="updateDiscountLabel()"
                class="accent-[#005691]">
              <span class="text-sm text-gray-700">ลดเป็นบาท (คงที่)</span>
            </label>
          </div>
        </div>

        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1" id="discountLabel">ส่วนลด (%)</label>
          <div class="relative">
            <input type="number" name="discount_percent" id="discountInput" required min="1" step="0.5"
              placeholder="เช่น 15"
              class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:border-[#E8F1F5] focus:ring-2 focus:ring-[#E8F1F5]/20 outline-none text-sm pr-12">
            <span id="discountUnit"
              class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400 pointer-events-none">%</span>
          </div>
        </div>

        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">วันที่เริ่มต้น</label>
          <input type="date" name="start_date" required value="<?= date('Y-m-d') ?>"
            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:border-[#E8F1F5] focus:ring-2 focus:ring-[#E8F1F5]/20 outline-none text-sm">
        </div>

        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">วันที่สิ้นสุด</label>
          <input type="date" name="end_date" required
            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:border-[#E8F1F5] focus:ring-2 focus:ring-[#E8F1F5]/20 outline-none text-sm">
        </div>

        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">หมายเหตุ (ไม่บังคับ)</label>
          <input type="text" name="description" maxlength="255" placeholder="รายละเอียดเพิ่มเติม"
            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:border-[#E8F1F5] focus:ring-2 focus:ring-[#E8F1F5]/20 outline-none text-sm">
        </div>

        <div class="sm:col-span-2 lg:col-span-3 flex justify-end">
          <button type="submit" name="add" style="background:#004A7C;"
            class="px-8 py-2.5 text-white text-sm font-medium rounded-lg hover:opacity-90 transition-opacity">
            + เพิ่มโปรโมชั่น
          </button>
        </div>
      </form>
    </div>

    <!-- Search / Filter bar -->
    <div class="bg-white rounded-xl border border-gray-200 p-4 mb-5">
      <form method="get" class="flex flex-col gap-3">
        <div class="flex flex-col sm:flex-row gap-3">
          <div class="flex-1">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
              placeholder="🔍 ค้นหาด้วยรหัสหรือชื่อโปรโมชั่น..."
              class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none text-sm">
          </div>
          <select name="status_filter"
            class="px-3 py-2.5 rounded-lg border border-gray-300 focus:border-blue-400 outline-none text-sm min-w-[140px]">
            <option value="">ทุกสถานะ</option>
            <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>ใช้งานอยู่</option>
            <option value="expired" <?= $status_filter === 'expired' ? 'selected' : '' ?>>หมดอายุ</option>
            <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>ปิดใช้งาน</option>
            <option value="upcoming" <?= $status_filter === 'upcoming' ? 'selected' : '' ?>>ยังไม่เริ่ม</option>
          </select>
        </div>
        <div class="flex flex-wrap gap-2 items-center">
          <label class="text-xs text-gray-500 whitespace-nowrap">แสดง</label>
          <select name="per_page" onchange="this.form.submit()"
            class="px-3 py-2 rounded-lg border border-gray-300 outline-none text-sm">
            <option value="10" <?= $per_page === 10 ? 'selected' : '' ?>>10 รายการ</option>
            <option value="25" <?= $per_page === 25 ? 'selected' : '' ?>>25 รายการ</option>
            <option value="50" <?= $per_page === 50 ? 'selected' : '' ?>>50 รายการ</option>
            <option value="100" <?= $per_page === 100 ? 'selected' : '' ?>>100 รายการ</option>
            <option value="all" <?= $per_page === 0 ? 'selected' : '' ?>>ทั้งหมด</option>
          </select>
          <div class="flex-1"></div>
          <button type="submit" style="background:#004A7C;"
            class="px-5 py-2 text-white text-sm font-medium rounded-lg hover:opacity-90 transition-opacity">
            ค้นหา
          </button>
          <?php if ($search !== '' || $status_filter !== ''): ?>
            <a href="promotions.php"
              class="px-5 py-2 text-gray-600 text-sm font-medium rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors">
              ล้างตัวกรอง
            </a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <!-- Promotions List -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <div style="background:#005691;" class="px-5 py-3 flex justify-between items-center">
        <h2 class="text-white font-medium text-sm">รายการโปรโมชั่น</h2>
        <span class="text-blue-200 text-xs">
          พบ <?= number_format($totalRecords) ?>
          รายการ<?= ($search !== '' || $status_filter !== '') ? ' (กรองแล้ว)' : '' ?>
        </span>
      </div>

      <!-- Mobile -->
      <div class="block lg:hidden divide-y divide-gray-100">
        <?php foreach ($promotions as $p):
          if (!$p['is_active']) {
            $badgeClass = 'bg-red-100 text-red-500';
            $badgeText = 'ปิดใช้งาน';
          } elseif ($today > $p['end_date']) {
            $badgeClass = 'bg-gray-100 text-gray-500';
            $badgeText = 'หมดอายุ';
          } elseif ($today < $p['start_date']) {
            $badgeClass = 'bg-yellow-100 text-yellow-600';
            $badgeText = 'ยังไม่เริ่ม';
          } else {
            $badgeClass = 'bg-green-100 text-green-700';
            $badgeText = 'ใช้งาน';
          }
          ?>
          <div class="p-4">
            <div class="flex justify-between items-start mb-2">
              <div>
                <p class="font-medium text-gray-800"><?= htmlspecialchars($p['name']) ?></p>
                <p class="text-gray-400 text-xs font-mono mt-0.5"><?= htmlspecialchars($p['code']) ?></p>
              </div>
              <div class="flex flex-col items-end gap-1">
                <span class="text-lg font-bold" style="color:#005691;">
                  <?php $dtype = $p['discount_type'] ?? 'percent'; ?>
                  <?= number_format($p['discount_percent'], ($p['discount_percent'] == floor($p['discount_percent']) ? 0 : 2)) ?>  <?= $dtype === 'fixed' ? ' บ.' : '%' ?>
                </span>
                <span class="text-xs px-2 py-0.5 rounded-full <?= $badgeClass ?>"><?= $badgeText ?></span>
              </div>
            </div>
            <p class="text-gray-400 text-xs mb-3">
              <?= date('d/m/Y', strtotime($p['start_date'])) ?> — <?= date('d/m/Y', strtotime($p['end_date'])) ?>
              <?php if ($p['description']): ?>· <?= htmlspecialchars($p['description']) ?><?php endif; ?>
            </p>
            <div class="flex gap-2">
              <a href="?toggle=<?= $p['id'] ?>" style="border-color:#E8F1F5; color:#004A7C;"
                class="flex-1 py-1.5 border text-xs rounded text-center hover:bg-[#FAFAFA] transition-colors">
                <?= $p['is_active'] ? 'ปิดใช้งาน' : 'เปิดใช้งาน' ?>
              </a>
              <a href="?delete=<?= $p['id'] ?>" onclick="return confirm('ยืนยันลบโปรโมชั่น \"
                <?= htmlspecialchars($p['name'], ENT_QUOTES) ?>\"?')"
                class="flex-1 py-1.5 border border-red-200 text-red-500 text-xs rounded text-center hover:bg-red-50 transition-colors">
                ลบ
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Desktop -->
      <div class="hidden lg:block overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50 border-b border-gray-200 text-left">
              <th class="px-5 py-3 font-medium text-gray-500">ID</th>
              <th class="px-5 py-3 font-medium text-gray-500">Code</th>
              <th class="px-5 py-3 font-medium text-gray-500">ชื่อโปรโมชั่น</th>
              <th class="px-5 py-3 font-medium text-gray-500 text-center">ส่วนลด</th>
              <th class="px-5 py-3 font-medium text-gray-500 text-center">วันเริ่ม</th>
              <th class="px-5 py-3 font-medium text-gray-500 text-center">วันสิ้นสุด</th>
              <th class="px-5 py-3 font-medium text-gray-500 text-center">สถานะ</th>
              <th class="px-5 py-3 font-medium text-gray-500 text-center">จัดการ</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <?php foreach ($promotions as $p):
              if (!$p['is_active']) {
                $badgeClass = 'bg-red-100 text-red-500';
                $badgeText = 'ปิดใช้งาน';
              } elseif ($today > $p['end_date']) {
                $badgeClass = 'bg-gray-100 text-gray-500';
                $badgeText = 'หมดอายุ';
              } elseif ($today < $p['start_date']) {
                $badgeClass = 'bg-yellow-100 text-yellow-600';
                $badgeText = 'ยังไม่เริ่ม';
              } else {
                $badgeClass = 'bg-green-100 text-green-700';
                $badgeText = 'ใช้งาน';
              }
              ?>
              <tr class="hover:bg-gray-50 transition-colors">
                <td class="px-5 py-3 text-gray-400"><?= $p['id'] ?></td>
                <td class="px-5 py-3">
                  <span
                    class="font-mono text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded"><?= htmlspecialchars($p['code']) ?></span>
                </td>
                <td class="px-5 py-3">
                  <p class="font-medium text-gray-800"><?= htmlspecialchars($p['name']) ?></p>
                  <?php if ($p['description']): ?>
                    <p class="text-xs text-gray-400 mt-0.5"><?= htmlspecialchars($p['description']) ?></p>
                  <?php endif; ?>
                </td>
                <td class="px-5 py-3 text-center">
                  <?php $dtype = $p['discount_type'] ?? 'percent'; ?>
                  <span style="color:#005691;"
                    class="text-lg font-bold"><?= number_format($p['discount_percent'], ($p['discount_percent'] == floor($p['discount_percent']) ? 0 : 2)) ?></span>
                  <span class="text-xs text-gray-500"><?= $dtype === 'fixed' ? ' บาท' : '%' ?></span>
                </td>
                <td class="px-5 py-3 text-center text-gray-500 text-xs"><?= date('d/m/Y', strtotime($p['start_date'])) ?>
                </td>
                <td class="px-5 py-3 text-center text-gray-500 text-xs"><?= date('d/m/Y', strtotime($p['end_date'])) ?>
                </td>
                <td class="px-5 py-3 text-center">
                  <span class="text-xs px-2.5 py-1 rounded-full <?= $badgeClass ?>"><?= $badgeText ?></span>
                </td>
                <td class="px-5 py-3">
                  <div class="flex gap-1.5 justify-center">
                    <a href="?toggle=<?= $p['id'] ?>" style="border-color:#E8F1F5; color:#004A7C;"
                      class="px-3 py-1.5 border text-xs rounded hover:bg-[#FAFAFA] transition-colors">
                      <?= $p['is_active'] ? 'ปิด' : 'เปิด' ?>
                    </a>
                    <a href="?delete=<?= $p['id'] ?>" onclick="return confirm('ยืนยันลบโปรโมชั่น \"
                      <?= htmlspecialchars($p['name'], ENT_QUOTES) ?>\"?')"
                      class="px-3 py-1.5 border border-red-200 text-red-500 text-xs rounded hover:bg-red-50 transition-colors">
                      ลบ
                    </a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php if ($totalRecords === 0 && $search === '' && $status_filter === ''): ?>
        <div class="p-10 text-center text-gray-400">ยังไม่มีโปรโมชั่น</div>
      <?php elseif (count($promotions) === 0): ?>
        <div class="p-10 text-center text-gray-400">ไม่พบโปรโมชั่นที่ตรงกับการค้นหา</div>
      <?php endif; ?>

      <!-- Pagination -->
      <div class="px-5 pb-4">
        <?php include __DIR__ . '/../includes/pagination.php'; ?>
      </div>
    </div>

  </div>

  <?php include __DIR__ . '/../includes/footer.php'; ?>
  <script>
    function updateDiscountLabel() {
      const isFixed = document.getElementById('dtFixed').checked;
      document.getElementById('discountLabel').textContent = isFixed ? 'จำนวนเงินที่ลด (บาท)' : 'ส่วนลด (%)';
      document.getElementById('discountUnit').textContent = isFixed ? '฿' : '%';
      const inp = document.getElementById('discountInput');
      if (isFixed) {
        inp.removeAttribute('max');
        inp.placeholder = 'เช่น 50';
      } else {
        inp.setAttribute('max', '100');
        inp.placeholder = 'เช่น 15';
      }
    }
  </script>
</body>

</html>