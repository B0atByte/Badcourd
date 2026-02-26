<?php
require_once __DIR__.'/../auth/guard.php';
require_once __DIR__.'/../config/db.php';
require_role(['admin']);

$checkColumns = $pdo->query("SHOW COLUMNS FROM courts LIKE 'vip_room_name'")->fetch();
if (!$checkColumns) {
    try { $pdo->exec("ALTER TABLE courts ADD COLUMN vip_room_name VARCHAR(100) NULL AFTER court_no"); } catch (Exception $e) {}
}

$checkNormalPrice = $pdo->query("SHOW COLUMNS FROM courts LIKE 'normal_price'")->fetch();
if (!$checkNormalPrice) {
    try { $pdo->exec("ALTER TABLE courts ADD COLUMN normal_price DECIMAL(10,2) NULL AFTER vip_price"); } catch (Exception $e) {}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create'])) {
    $type = $_POST['court_type'] ?? 'normal';
    $isVip = $type === 'vip' ? 1 : 0;

    if ($type === 'vip') {
        $roomName = trim($_POST['vip_room_name'] ?? '');
        $vipPrice = floatval($_POST['vip_price'] ?? 0);
        $stmt = $pdo->query('SELECT MIN(court_no) as min_no FROM courts WHERE court_type = "vip"');
        $result = $stmt->fetch();
        $courtNo = ($result['min_no'] !== null && $result['min_no'] < 0) ? $result['min_no'] - 1 : -1;

        if (empty($roomName) || $vipPrice <= 0) {
            $error = "กรุณากรอกชื่อห้อง VIP และราคาให้ครบถ้วน";
        } else {
            $stmt = $pdo->prepare('INSERT INTO courts (court_no, vip_room_name, status, is_vip, court_type, vip_price) VALUES (:n, :room_name, "Available", :is_vip, :type, :price)');
            $stmt->execute([':n'=>$courtNo,':room_name'=>$roomName,':is_vip'=>$isVip,':type'=>$type,':price'=>$vipPrice]);
            header('Location: courts.php?success=1'); exit;
        }
    } else {
        $no = (int)($_POST['court_no'] ?? 0);
        $normalPrice = !empty($_POST['normal_price']) ? floatval($_POST['normal_price']) : null;
        if ($no <= 0) {
            $error = "กรุณากรอกหมายเลขคอร์ตที่ถูกต้อง";
        } else {
            $checkStmt = $pdo->prepare('SELECT COUNT(*) FROM courts WHERE court_no = :n AND court_type = "normal"');
            $checkStmt->execute([':n'=>$no]);
            if ($checkStmt->fetchColumn() > 0) {
                $error = "หมายเลขคอร์ต {$no} มีอยู่แล้ว";
            } else {
                $stmt = $pdo->prepare('INSERT INTO courts (court_no, vip_room_name, status, is_vip, court_type, vip_price, normal_price) VALUES (:n, NULL, "Available", :is_vip, :type, NULL, :normal_price)');
                $stmt->execute([':n'=>$no,':is_vip'=>$isVip,':type'=>$type,':normal_price'=>$normalPrice]);
                header('Location: courts.php?success=1'); exit;
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $id = (int)$_POST['id'];
    $st = $_POST['status'];
    $type = $_POST['court_type'] ?? 'normal';
    $isVip = $type === 'vip' ? 1 : 0;

    if ($type === 'vip') {
        $roomName = trim($_POST['vip_room_name'] ?? '');
        $vipPrice = floatval($_POST['vip_price'] ?? 0);
        $oldData = $pdo->prepare('SELECT court_no, court_type FROM courts WHERE id = :id');
        $oldData->execute([':id'=>$id]);
        $old = $oldData->fetch();
        if ($old['court_type'] === 'vip' && $old['court_no'] < 0) {
            $courtNo = $old['court_no'];
        } else {
            $stmt = $pdo->query('SELECT MIN(court_no) as min_no FROM courts WHERE court_type = "vip"');
            $result = $stmt->fetch();
            $courtNo = ($result['min_no'] !== null && $result['min_no'] < 0) ? $result['min_no'] - 1 : -1;
        }
        $stmt = $pdo->prepare('UPDATE courts SET court_no=:n, vip_room_name=:room_name, status=:s, is_vip=:is_vip, court_type=:type, vip_price=:price WHERE id=:id');
        $stmt->execute([':n'=>$courtNo,':room_name'=>$roomName,':s'=>$st,':is_vip'=>$isVip,':type'=>$type,':price'=>$vipPrice,':id'=>$id]);
    } else {
        $no = (int)$_POST['court_no'];
        $normalPrice = !empty($_POST['normal_price']) ? floatval($_POST['normal_price']) : null;
        $stmt = $pdo->prepare('UPDATE courts SET court_no=:n, vip_room_name=NULL, status=:s, is_vip=:is_vip, court_type=:type, vip_price=NULL, normal_price=:normal_price WHERE id=:id');
        $stmt->execute([':n'=>$no,':s'=>$st,':is_vip'=>$isVip,':type'=>$type,':normal_price'=>$normalPrice,':id'=>$id]);
    }
    header('Location: courts.php?updated=1'); exit;
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare('DELETE FROM courts WHERE id=:id')->execute([':id'=>$id]);
    header('Location: courts.php?deleted=1'); exit;
}

// Stats from ALL courts (not filtered)
$allCourts = $pdo->query('SELECT court_type, is_vip, status FROM courts')->fetchAll();
$totalCourts      = count($allCourts);
$vipCourts        = count(array_filter($allCourts, fn($c) => $c['court_type'] === 'vip'       || $c['is_vip'] == 1));
$normalCourts     = count(array_filter($allCourts, fn($c) => $c['court_type'] === 'normal'    || $c['is_vip'] == 0));
$availableCourts  = count(array_filter($allCourts, fn($c) => $c['status'] === 'Available'));
$inUseCourts      = count(array_filter($allCourts, fn($c) => $c['status'] === 'In Use'));
$maintenanceCourts = count(array_filter($allCourts, fn($c) => $c['status'] === 'Maintenance'));

// Search / Filter / Pagination
$search        = trim($_GET['search']  ?? '');
$type_filter   = $_GET['type']   ?? '';
$status_filter = $_GET['cstatus'] ?? '';   // 'cstatus' to avoid clash with POST 'status'
$per_page_raw  = $_GET['per_page'] ?? 'all';
$per_page      = ($per_page_raw === 'all') ? 0 : (int)$per_page_raw;
if (!in_array($per_page, [0, 10, 25, 50, 100])) $per_page = 0;
$page = max(1, (int)($_GET['page'] ?? 1));

$cWhere  = ['1=1'];
$cParams = [];
if (!empty($search)) {
    $cWhere[]  = '(vip_room_name LIKE ? OR CAST(court_no AS CHAR) LIKE ?)';
    $sp        = '%' . $search . '%';
    $cParams[] = $sp;
    $cParams[] = $sp;
}
if ($type_filter === 'vip') {
    $cWhere[] = 'court_type = "vip"';
} elseif ($type_filter === 'normal') {
    $cWhere[] = 'court_type = "normal"';
}
if (!empty($status_filter)) {
    $cWhere[]  = 'status = ?';
    $cParams[] = $status_filter;
}
$cWhereClause = implode(' AND ', $cWhere);

// Count
$cntStmt = $pdo->prepare("SELECT COUNT(*) FROM courts WHERE $cWhereClause");
$cntStmt->execute($cParams);
$totalRecords = (int)$cntStmt->fetchColumn();

// Pagination calc
$totalPages = 1; $offset = 0;
if ($per_page > 0) {
    $totalPages = max(1, (int)ceil($totalRecords / $per_page));
    $page       = min($page, $totalPages);
    $offset     = ($page - 1) * $per_page;
}

// Fetch courts (filtered + paginated)
$cQuery = "SELECT * FROM courts WHERE $cWhereClause ORDER BY court_type DESC, vip_room_name ASC, court_no ASC";
if ($per_page > 0) $cQuery .= " LIMIT $per_page OFFSET $offset";
$cStmt = $pdo->prepare($cQuery);
$cStmt->execute($cParams);
$courts = $cStmt->fetchAll();

$statusThai = ['Available'=>'พร้อมใช้งาน','Booked'=>'ถูกจอง','In Use'=>'กำลังใช้งาน','Maintenance'=>'ซ่อมบำรุง'];

function toggleCourtTypeScript() { return '
  function toggleCourtType(selectElement) {
    const form = selectElement.closest("form");
    const normalFields = form.querySelector(".normal-fields");
    const vipFields = form.querySelector(".vip-fields");
    if (selectElement.value === "vip") {
      if (normalFields) { normalFields.classList.add("hidden"); const i = normalFields.querySelector("input[name=court_no]"); if(i) i.removeAttribute("required"); }
      if (vipFields) { vipFields.classList.remove("hidden"); const r = vipFields.querySelector("input[name=vip_room_name]"); const p = vipFields.querySelector("input[name=vip_price]"); if(r) r.setAttribute("required","required"); if(p) p.setAttribute("required","required"); }
    } else {
      if (normalFields) { normalFields.classList.remove("hidden"); const i = normalFields.querySelector("input[name=court_no]"); if(i) i.setAttribute("required","required"); }
      if (vipFields) { vipFields.classList.add("hidden"); const r = vipFields.querySelector("input[name=vip_room_name]"); const p = vipFields.querySelector("input[name=vip_price]"); if(r) r.removeAttribute("required"); if(p) p.removeAttribute("required"); }
    }
  }
'; }
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
  <title>จัดการคอร์ต - BARGAIN SPORT</title>
  <script><?= toggleCourtTypeScript() ?></script>
</head>
<body style="background:#FAFAFA;" class="min-h-screen">
<?php include __DIR__.'/../includes/header.php'; ?>

<div class="max-w-7xl mx-auto px-4 py-8">

  <!-- Alerts -->
  <?php if (isset($_GET['success'])): ?>
  <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-5 text-sm">เพิ่มคอร์ตเรียบร้อยแล้ว</div>
  <?php endif; ?>
  <?php if (isset($_GET['updated'])): ?>
  <div class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded-lg mb-5 text-sm">อัพเดทข้อมูลเรียบร้อยแล้ว</div>
  <?php endif; ?>
  <?php if (isset($_GET['deleted'])): ?>
  <div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg mb-5 text-sm">ลบคอร์ตออกจากระบบแล้ว</div>
  <?php endif; ?>
  <?php if (isset($error)): ?>
  <div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg mb-5 text-sm"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- Header + Add Form -->
  <div class="bg-white rounded-xl border border-gray-200 p-6 mb-5">
    <div class="flex flex-col lg:flex-row gap-6 justify-between">
      <div>
        <h1 style="color:#005691;" class="text-2xl font-bold mb-1">จัดการคอร์ต</h1>
        <p class="text-gray-500 text-sm">Admin Panel · คอร์ตปกติใช้ตัวเลข, VIP ใส่ชื่อห้องได้</p>
      </div>

      <!-- Add Form -->
      <form method="post" class="flex flex-col gap-3 w-full lg:max-w-xs">
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">ประเภทคอร์ต</label>
          <select name="court_type" onchange="toggleCourtType(this)" required
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-[#E8F1F5] outline-none text-sm">
            <option value="normal">คอร์ตปกติ</option>
            <option value="vip">ห้อง VIP</option>
          </select>
        </div>

        <div class="normal-fields space-y-2">
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">หมายเลขคอร์ต</label>
            <input type="number" min="1" name="court_no" placeholder="เช่น 1, 2, 3" required
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-[#E8F1F5] outline-none text-sm">
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">ราคา ฿/ชม. (ไม่บังคับ)</label>
            <input type="number" step="0.01" min="0" name="normal_price" placeholder="ถ้าไม่ระบุ = ตามช่วงเวลา"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-[#E8F1F5] outline-none text-sm">
          </div>
        </div>

        <div class="vip-fields hidden space-y-2">
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">ชื่อห้อง VIP</label>
            <input type="text" name="vip_room_name" placeholder="เช่น VIP A, Executive..." maxlength="100"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-[#E8F1F5] outline-none text-sm">
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">ราคา VIP ฿/ชม.</label>
            <input type="number" step="0.01" min="0.01" name="vip_price" placeholder="เช่น 500, 800..."
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-[#E8F1F5] outline-none text-sm">
          </div>
        </div>

        <button type="submit" name="create"
                style="background:#004A7C;"
                class="px-5 py-2.5 text-white text-sm font-medium rounded-lg hover:opacity-90 transition-opacity">
          + เพิ่มคอร์ต
        </button>
      </form>
    </div>
  </div>

  <!-- Stats -->
  <div class="grid grid-cols-3 sm:grid-cols-6 gap-3 mb-5">
    <?php
    $stats = [
      ['label'=>'ทั้งหมด','val'=>$totalCourts,'color'=>'#005691'],
      ['label'=>'VIP','val'=>$vipCourts,'color'=>'#004A7C'],
      ['label'=>'ปกติ','val'=>$normalCourts,'color'=>'#E8F1F5'],
      ['label'=>'พร้อมใช้','val'=>$availableCourts,'color'=>'#004A7C'],
      ['label'=>'ใช้งานอยู่','val'=>$inUseCourts,'color'=>'#E8F1F5'],
      ['label'=>'ซ่อมบำรุง','val'=>$maintenanceCourts,'color'=>'#005691'],
    ];
    foreach ($stats as $s):
    ?>
    <div class="bg-white rounded-xl border border-gray-200 p-4">
      <p class="text-gray-400 text-xs mb-1"><?= $s['label'] ?></p>
      <p style="color:<?= $s['color'] ?>;" class="text-2xl font-bold"><?= $s['val'] ?></p>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Search / Filter -->
  <div class="bg-white rounded-xl border border-gray-200 p-4 mb-5">
    <form method="get" class="flex flex-col gap-3">
      <div class="flex flex-col sm:flex-row gap-3">
        <div class="flex-1">
          <input type="text" name="search"
                 value="<?= htmlspecialchars($search) ?>"
                 placeholder="🔍 ค้นหาด้วยชื่อคอร์ต / เลขคอร์ต..."
                 class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none text-sm">
        </div>
        <select name="type"
                class="px-3 py-2.5 rounded-lg border border-gray-300 focus:border-blue-400 outline-none text-sm min-w-[130px]">
          <option value="">ทุกประเภท</option>
          <option value="normal" <?= $type_filter === 'normal' ? 'selected' : '' ?>>คอร์ตปกติ</option>
          <option value="vip"    <?= $type_filter === 'vip'    ? 'selected' : '' ?>>ห้อง VIP</option>
        </select>
        <select name="cstatus"
                class="px-3 py-2.5 rounded-lg border border-gray-300 focus:border-blue-400 outline-none text-sm min-w-[140px]">
          <option value="">ทุกสถานะ</option>
          <option value="Available"   <?= $status_filter === 'Available'   ? 'selected' : '' ?>>พร้อมใช้งาน</option>
          <option value="In Use"      <?= $status_filter === 'In Use'      ? 'selected' : '' ?>>กำลังใช้งาน</option>
          <option value="Booked"      <?= $status_filter === 'Booked'      ? 'selected' : '' ?>>ถูกจอง</option>
          <option value="Maintenance" <?= $status_filter === 'Maintenance' ? 'selected' : '' ?>>ซ่อมบำรุง</option>
        </select>
      </div>
      <div class="flex flex-wrap gap-2 items-center">
        <label class="text-xs text-gray-500 whitespace-nowrap">แสดง</label>
        <select name="per_page" onchange="this.form.submit()"
                class="px-3 py-2 rounded-lg border border-gray-300 outline-none text-sm">
          <option value="10"  <?= $per_page === 10  ? 'selected' : '' ?>>10 รายการ</option>
          <option value="25"  <?= $per_page === 25  ? 'selected' : '' ?>>25 รายการ</option>
          <option value="50"  <?= $per_page === 50  ? 'selected' : '' ?>>50 รายการ</option>
          <option value="100" <?= $per_page === 100 ? 'selected' : '' ?>>100 รายการ</option>
          <option value="all" <?= $per_page === 0   ? 'selected' : '' ?>>ทั้งหมด</option>
        </select>
        <div class="flex-1"></div>
        <button type="submit"
                style="background:#004A7C;"
                class="px-5 py-2 text-white text-sm font-medium rounded-lg hover:opacity-90 transition-opacity">
          ค้นหา
        </button>
        <?php if ($search !== '' || $type_filter !== '' || $status_filter !== ''): ?>
        <a href="courts.php"
           class="px-5 py-2 text-gray-600 text-sm font-medium rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors">
          ล้างตัวกรอง
        </a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Courts Table -->
  <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <!-- Count bar -->
    <div class="px-4 py-3 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
      <span class="text-sm text-gray-600 font-medium">
        พบ <span style="color:#005691;" class="font-bold"><?= number_format($totalRecords) ?></span> รายการ
        <?php if ($search !== '' || $type_filter !== '' || $status_filter !== ''): ?>
        <span class="text-gray-400 text-xs">(กรองแล้ว / ทั้งหมด <?= $totalCourts ?> คอร์ต)</span>
        <?php endif; ?>
      </span>
      <?php if ($per_page > 0 && $totalPages > 1): ?>
      <span class="text-xs text-gray-400">หน้า <?= $page ?>/<?= $totalPages ?></span>
      <?php endif; ?>
    </div>
    <?php if(count($courts) > 0): ?>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr style="background:#005691;" class="text-white text-left">
            <th class="px-4 py-3 font-medium">ID</th>
            <th class="px-4 py-3 font-medium">ชื่อคอร์ต / ห้อง</th>
            <th class="px-4 py-3 font-medium text-center">ประเภท</th>
            <th class="px-4 py-3 font-medium text-center">ราคา</th>
            <th class="px-4 py-3 font-medium text-center">สถานะ</th>
            <th class="px-4 py-3 font-medium text-center">จัดการ</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php foreach($courts as $c):
            $isVip = ($c['court_type'] === 'vip' || $c['is_vip'] == 1);
            $displayName = $isVip ? ($c['vip_room_name'] ?? 'ห้อง VIP') : 'คอร์ต ' . $c['court_no'];
            $statusBadge = [
              'Available' => 'bg-green-100 text-green-700',
              'Booked' => 'bg-yellow-100 text-yellow-700',
              'In Use' => 'bg-blue-100 text-blue-700',
              'Maintenance' => 'bg-orange-100 text-orange-700',
            ][$c['status']] ?? 'bg-gray-100 text-gray-600';
          ?>
          <tr class="hover:bg-gray-50 transition-colors <?= $isVip ? 'bg-[#FAFAFA]/30' : '' ?>">
            <td class="px-4 py-3 text-gray-500"><?= $c['id'] ?></td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <?php if($isVip): ?>
                <span style="background:#005691;" class="w-7 h-7 rounded flex items-center justify-center text-xs text-white font-bold">V</span>
                <?php else: ?>
                <span style="background:#E8F1F5;" class="w-7 h-7 rounded flex items-center justify-center text-xs text-white font-bold"><?= $c['court_no'] ?></span>
                <?php endif; ?>
                <span class="font-medium text-gray-800"><?= htmlspecialchars($displayName) ?></span>
                <?php if($isVip): ?>
                <span class="text-xs px-2 py-0.5 rounded" style="background:#FAFAFA; color:#004A7C;">VIP</span>
                <?php endif; ?>
              </div>
            </td>
            <td class="px-4 py-3 text-center">
              <span class="text-xs"><?= $isVip ? 'VIP' : 'ปกติ' ?></span>
            </td>
            <td class="px-4 py-3 text-center">
              <?php if($isVip && $c['vip_price']): ?>
              <span style="color:#004A7C;" class="font-medium"><?= number_format($c['vip_price'], 0) ?> ฿/ชม.</span>
              <?php elseif(!$isVip && $c['normal_price']): ?>
              <span style="color:#E8F1F5;" class="font-medium"><?= number_format($c['normal_price'], 0) ?> ฿/ชม.</span>
              <?php else: ?>
              <span class="text-gray-400 text-xs">ตามเวลา</span>
              <?php endif; ?>
            </td>
            <td class="px-4 py-3 text-center">
              <span class="text-xs px-2 py-1 rounded-full <?= $statusBadge ?>">
                <?= $statusThai[$c['status']] ?? $c['status'] ?>
              </span>
            </td>
            <td class="px-4 py-3">
              <form method="post" class="flex items-center gap-1.5 justify-center flex-wrap">
                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                <select name="court_type" onchange="toggleCourtType(this)"
                        class="px-2 py-1.5 border border-gray-300 rounded text-xs outline-none">
                  <option value="normal" <?= !$isVip ? 'selected' : '' ?>>ปกติ</option>
                  <option value="vip" <?= $isVip ? 'selected' : '' ?>>VIP</option>
                </select>
                <div class="normal-fields <?= $isVip ? 'hidden' : '' ?> flex gap-1">
                  <input type="number" name="court_no" value="<?= $c['court_no'] ?>"
                         class="w-14 px-2 py-1.5 border border-gray-300 rounded text-center text-xs outline-none">
                  <input type="number" step="0.01" name="normal_price" value="<?= $c['normal_price'] ?? '' ?>" placeholder="ราคา"
                         class="w-20 px-2 py-1.5 border border-gray-300 rounded text-center text-xs outline-none">
                </div>
                <div class="vip-fields <?= $isVip ? '' : 'hidden' ?> flex gap-1">
                  <input type="text" name="vip_room_name" value="<?= htmlspecialchars($c['vip_room_name'] ?? '') ?>" placeholder="ชื่อห้อง"
                         class="w-24 px-2 py-1.5 border border-gray-300 rounded text-xs outline-none">
                  <input type="number" step="0.01" name="vip_price" value="<?= $c['vip_price'] ?? '' ?>" placeholder="ราคา"
                         class="w-16 px-2 py-1.5 border border-gray-300 rounded text-center text-xs outline-none">
                </div>
                <select name="status" class="px-2 py-1.5 border border-gray-300 rounded text-xs outline-none">
                  <?php foreach(['Available','Booked','In Use','Maintenance'] as $s): ?>
                  <option value="<?= $s ?>" <?= $c['status'] === $s ? 'selected' : '' ?>><?= $statusThai[$s] ?></option>
                  <?php endforeach; ?>
                </select>
                <button type="submit" name="update"
                        style="background:#004A7C;"
                        class="px-3 py-1.5 text-white text-xs rounded hover:opacity-90 transition-opacity">
                  บันทึก
                </button>
                <a href="?delete=<?= $c['id'] ?>"
                   onclick="return confirm('ยืนยันลบ <?= htmlspecialchars($displayName) ?>?')"
                   class="px-3 py-1.5 bg-red-50 text-red-500 border border-red-200 text-xs rounded hover:bg-red-100 transition-colors">
                  ลบ
                </a>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="p-12 text-center">
      <?php if ($search !== '' || $type_filter !== '' || $status_filter !== ''): ?>
      <p class="text-gray-400 text-lg mb-2">ไม่พบคอร์ตที่ตรงกับการค้นหา</p>
      <a href="courts.php" class="text-sm" style="color:#005691;">ล้างตัวกรอง</a>
      <?php else: ?>
      <p class="text-gray-400 text-lg mb-2">ยังไม่มีคอร์ต</p>
      <p class="text-gray-500 text-sm">เพิ่มคอร์ตจากฟอร์มด้านบน</p>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <!-- Pagination -->
    <div class="px-4 pb-4">
      <?php include __DIR__ . '/../includes/pagination.php'; ?>
    </div>
  </div>

</div>

<?php include __DIR__.'/../includes/footer.php'; ?>
</body>
</html>
