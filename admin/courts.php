<?php
require_once __DIR__.'/../auth/guard.php';
require_once __DIR__.'/../config/db.php';
require_role(['admin']);

// ตรวจสอบว่าตาราง courts มีคอลัมน์ vip_room_name หรือไม่
$checkColumns = $pdo->query("SHOW COLUMNS FROM courts LIKE 'vip_room_name'")->fetch();
if (!$checkColumns) {
    try {
        $pdo->exec("ALTER TABLE courts ADD COLUMN vip_room_name VARCHAR(100) NULL COMMENT 'ชื่อห้อง VIP' AFTER court_no");
    } catch (Exception $e) {
        // ถ้าเพิ่มไม่ได้ก็ข้าม
    }
}

// ตรวจสอบว่าตาราง courts มีคอลัมน์ normal_price หรือไม่
$checkNormalPrice = $pdo->query("SHOW COLUMNS FROM courts LIKE 'normal_price'")->fetch();
if (!$checkNormalPrice) {
    try {
        $pdo->exec("ALTER TABLE courts ADD COLUMN normal_price DECIMAL(10,2) NULL COMMENT 'ราคาคงที่สำหรับคอร์ตปกติ (optional)' AFTER vip_price");
    } catch (Exception $e) {
        // ถ้าเพิ่มไม่ได้ก็ข้าม
    }
}

// Create
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create'])) {
    $type = $_POST['court_type'] ?? 'normal';
    $isVip = $type === 'vip' ? 1 : 0;
    
    if ($type === 'vip') {
        // คอร์ต VIP: ใช้ชื่อห้อง
        $roomName = trim($_POST['vip_room_name'] ?? '');
        $vipPrice = floatval($_POST['vip_price'] ?? 0);
        
        // หาเลข court_no ที่ไม่ซ้ำสำหรับ VIP (ใช้เลขติดลบ)
        $stmt = $pdo->query('SELECT MIN(court_no) as min_no FROM courts WHERE court_type = "vip"');
        $result = $stmt->fetch();
        $courtNo = ($result['min_no'] !== null && $result['min_no'] < 0) ? $result['min_no'] - 1 : -1;
        
        // ตรวจสอบว่ากรอกข้อมูลครบ
        if (empty($roomName) || $vipPrice <= 0) {
            $error = "กรุณากรอกชื่อห้อง VIP และราคาให้ครบถ้วน";
        } else {
            $stmt = $pdo->prepare('INSERT INTO courts (court_no, vip_room_name, status, is_vip, court_type, vip_price) VALUES (:n, :room_name, "Available", :is_vip, :type, :price)');
            $stmt->execute([
                ':n' => $courtNo,
                ':room_name' => $roomName,
                ':is_vip' => $isVip,
                ':type' => $type,
                ':price' => $vipPrice
            ]);
            header('Location: courts.php?success=1');
            exit;
        }
    } else {
        // คอร์ตปกติ: ใช้ตัวเลข
        $no = (int)($_POST['court_no'] ?? 0);
        $normalPrice = !empty($_POST['normal_price']) ? floatval($_POST['normal_price']) : null;
        
        if ($no <= 0) {
            $error = "กรุณากรอกหมายเลขคอร์ตที่ถูกต้อง";
        } else {
            // ตรวจสอบว่าหมายเลขคอร์ตซ้ำหรือไม่
            $checkStmt = $pdo->prepare('SELECT COUNT(*) FROM courts WHERE court_no = :n AND court_type = "normal"');
            $checkStmt->execute([':n' => $no]);
            if ($checkStmt->fetchColumn() > 0) {
                $error = "หมายเลขคอร์ต {$no} มีอยู่แล้ว กรุณาใช้หมายเลขอื่น";
            } else {
                $stmt = $pdo->prepare('INSERT INTO courts (court_no, vip_room_name, status, is_vip, court_type, vip_price, normal_price) VALUES (:n, NULL, "Available", :is_vip, :type, NULL, :normal_price)');
                $stmt->execute([
                    ':n' => $no,
                    ':is_vip' => $isVip,
                    ':type' => $type,
                    ':normal_price' => $normalPrice
                ]);
                header('Location: courts.php?success=1');
                exit;
            }
        }
    }
}

// Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $id = (int)$_POST['id'];
    $st = $_POST['status'];
    $type = $_POST['court_type'] ?? 'normal';
    $isVip = $type === 'vip' ? 1 : 0;
    
    if ($type === 'vip') {
        // อัพเดทคอร์ต VIP
        $roomName = trim($_POST['vip_room_name'] ?? '');
        $vipPrice = floatval($_POST['vip_price'] ?? 0);
        
        // ดึง court_no เดิมมาใช้ (ถ้ามี) หรือสร้างใหม่
        $oldData = $pdo->prepare('SELECT court_no, court_type FROM courts WHERE id = :id');
        $oldData->execute([':id' => $id]);
        $old = $oldData->fetch();
        
        if ($old['court_type'] === 'vip' && $old['court_no'] < 0) {
            // ถ้าเป็น VIP อยู่แล้ว ใช้เลขเดิม
            $courtNo = $old['court_no'];
        } else {
            // ถ้าเปลี่ยนจากปกติเป็น VIP หาเลขใหม่
            $stmt = $pdo->query('SELECT MIN(court_no) as min_no FROM courts WHERE court_type = "vip"');
            $result = $stmt->fetch();
            $courtNo = ($result['min_no'] !== null && $result['min_no'] < 0) ? $result['min_no'] - 1 : -1;
        }
        
        $stmt = $pdo->prepare('UPDATE courts SET court_no=:n, vip_room_name=:room_name, status=:s, is_vip=:is_vip, court_type=:type, vip_price=:price WHERE id=:id');
        $stmt->execute([
            ':n' => $courtNo,
            ':room_name' => $roomName,
            ':s' => $st,
            ':is_vip' => $isVip,
            ':type' => $type,
            ':price' => $vipPrice,
            ':id' => $id
        ]);
    } else {
        // อัพเดทคอร์ตปกติ
        $no = (int)$_POST['court_no'];
        $normalPrice = !empty($_POST['normal_price']) ? floatval($_POST['normal_price']) : null;
        
        $stmt = $pdo->prepare('UPDATE courts SET court_no=:n, vip_room_name=NULL, status=:s, is_vip=:is_vip, court_type=:type, vip_price=NULL, normal_price=:normal_price WHERE id=:id');
        $stmt->execute([
            ':n' => $no,
            ':s' => $st,
            ':is_vip' => $isVip,
            ':type' => $type,
            ':normal_price' => $normalPrice,
            ':id' => $id
        ]);
    }
    
    header('Location: courts.php?updated=1');
    exit;
}

// Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare('DELETE FROM courts WHERE id=:id')->execute([':id' => $id]);
    header('Location: courts.php?deleted=1');
    exit;
}

// Query data
$courts = $pdo->query('SELECT * FROM courts ORDER BY court_type DESC, vip_room_name ASC, court_no ASC')->fetchAll();

// คำนวณสถิติ
$totalCourts = count($courts);
$vipCourts = count(array_filter($courts, fn($c) => $c['court_type'] === 'vip' || $c['is_vip'] == 1));
$normalCourts = count(array_filter($courts, fn($c) => $c['court_type'] === 'normal' || $c['is_vip'] == 0));
$availableCourts = count(array_filter($courts, fn($c) => $c['status'] === 'Available'));
$inUseCourts = count(array_filter($courts, fn($c) => $c['status'] === 'In Use'));
$maintenanceCourts = count(array_filter($courts, fn($c) => $c['status'] === 'Maintenance'));

// แปลสถานะเป็นภาษาไทย
$statusThai = [
    'Available' => 'พร้อมใช้งาน',
    'Booked' => 'ถูกจอง', 
    'In Use' => 'กำลังใช้งาน',
    'Maintenance' => 'ซ่อมบำรุง'
];
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <title>จัดการคอร์ต - BARGAIN_SPORT</title>
  <script>
    function toggleCourtType(selectElement) {
      const form = selectElement.closest('form');
      const normalFields = form.querySelector('.normal-fields');
      const vipFields = form.querySelector('.vip-fields');
      
      if (selectElement.value === 'vip') {
        if (normalFields) {
          normalFields.classList.add('hidden');
          const courtNoInput = normalFields.querySelector('input[name="court_no"]');
          if (courtNoInput) courtNoInput.removeAttribute('required');
        }
        if (vipFields) {
          vipFields.classList.remove('hidden');
          const roomNameInput = vipFields.querySelector('input[name="vip_room_name"]');
          const priceInput = vipFields.querySelector('input[name="vip_price"]');
          if (roomNameInput) roomNameInput.setAttribute('required', 'required');
          if (priceInput) priceInput.setAttribute('required', 'required');
        }
      } else {
        if (normalFields) {
          normalFields.classList.remove('hidden');
          const courtNoInput = normalFields.querySelector('input[name="court_no"]');
          if (courtNoInput) courtNoInput.setAttribute('required', 'required');
        }
        if (vipFields) {
          vipFields.classList.add('hidden');
          const roomNameInput = vipFields.querySelector('input[name="vip_room_name"]');
          const priceInput = vipFields.querySelector('input[name="vip_price"]');
          if (roomNameInput) roomNameInput.removeAttribute('required');
          if (priceInput) priceInput.removeAttribute('required');
        }
      }
    }
  </script>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
<?php include __DIR__.'/../includes/header.php'; ?>

<div class="container mx-auto px-4 py-8 max-w-7xl">
  
  <!-- Success/Error Messages -->
  <?php if (isset($_GET['success'])): ?>
  <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg shadow-md animate-pulse">
    <div class="flex items-center">
      <i class="fas fa-check-circle text-2xl mr-3"></i>
      <div>
        <p class="font-bold">สำเร็จ!</p>
        <p>เพิ่มคอร์ตเรียบร้อยแล้ว</p>
      </div>
    </div>
  </div>
  <?php endif; ?>
  
  <?php if (isset($_GET['updated'])): ?>
  <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-6 rounded-lg shadow-md">
    <div class="flex items-center">
      <i class="fas fa-info-circle text-2xl mr-3"></i>
      <div>
        <p class="font-bold">อัพเดทสำเร็จ!</p>
        <p>ข้อมูลถูกแก้ไขเรียบร้อยแล้ว</p>
      </div>
    </div>
  </div>
  <?php endif; ?>
  
  <?php if (isset($_GET['deleted'])): ?>
  <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg shadow-md">
    <div class="flex items-center">
      <i class="fas fa-trash-alt text-2xl mr-3"></i>
      <div>
        <p class="font-bold">ลบสำเร็จ!</p>
        <p>คอร์ตถูกลบออกจากระบบแล้ว</p>
      </div>
    </div>
  </div>
  <?php endif; ?>
  
  <?php if (isset($error)): ?>
  <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg shadow-md">
    <div class="flex items-center">
      <i class="fas fa-exclamation-triangle text-2xl mr-3"></i>
      <div>
        <p class="font-bold">เกิดข้อผิดพลาด!</p>
        <p><?= htmlspecialchars($error) ?></p>
      </div>
    </div>
  </div>
  <?php endif; ?>
  
  <!-- Header Section -->
  <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
      <div>
        <h1 class="text-3xl font-bold text-gray-800 flex items-center gap-3 mb-2">
          <i class="fas fa-warehouse text-slate-600"></i>
          จัดการคอร์ต
          <span class="text-sm px-3 py-1 bg-gradient-to-r from-amber-400 to-amber-500 text-white rounded-full">
            <i class="fas fa-crown"></i> VIP Custom Name
          </span>
        </h1>
        <p class="text-gray-600 flex items-center gap-2">
          <i class="fas fa-shield-alt text-purple-500"></i>
          <span class="font-semibold">Admin Panel</span> - จัดการคอร์ตแบดมินตัน (คอร์ตปกติใช้ตัวเลข, VIP ใส่ชื่อห้องได้)
        </p>
      </div>
      
      <!-- Add Court Form -->
      <form method="post" class="flex flex-col gap-3 w-full lg:w-auto bg-gradient-to-br from-gray-50 to-blue-50 p-5 rounded-xl border-2 border-blue-200 shadow-lg">
        <div class="mb-2">
          <label class="block text-sm font-bold text-gray-700 mb-2">ประเภทคอร์ต</label>
          <select name="court_type" onchange="toggleCourtType(this)" required
                  class="w-full px-4 py-2.5 border-2 border-gray-300 rounded-xl focus:border-blue-500 
                         focus:ring-2 focus:ring-blue-200 transition-all outline-none font-bold text-gray-700 bg-white">
            <option value="normal">🏸 คอร์ตปกติ (ใช้ตัวเลข)</option>
            <option value="vip">⭐ คอร์ต VIP (ใส่ชื่อห้อง)</option>
          </select>
        </div>
        
        <!-- ฟิลด์สำหรับคอร์ตปกติ -->
        <div class="normal-fields space-y-3">
          <div>
            <label class="block text-sm font-bold text-gray-700 mb-2">หมายเลขคอร์ต</label>
            <div class="relative">
              <input type="number" min="1" name="court_no" placeholder="เช่น 1, 2, 3..." required
                     class="w-full pl-10 pr-4 py-2.5 border-2 border-gray-300 rounded-xl focus:border-blue-500 
                            focus:ring-2 focus:ring-blue-200 transition-all outline-none font-medium text-gray-700">
              <i class="fas fa-hashtag absolute left-3 top-3.5 text-gray-400"></i>
            </div>
          </div>
          
          <div>
            <label class="block text-sm font-bold text-gray-700 mb-2">
              <i class="fas fa-tag text-blue-500"></i> ราคา (บาท/ชั่วโมง) - ไม่บังคับ
            </label>
            <div class="relative">
              <input type="number" step="0.01" min="0" name="normal_price" placeholder=""
                     class="w-full pl-10 pr-4 py-2.5 border-2 border-blue-300 rounded-xl focus:border-blue-500 
                            focus:ring-2 focus:ring-blue-200 transition-all outline-none font-medium text-gray-700
                            bg-gradient-to-br from-blue-50 to-cyan-50">
              <i class="fas fa-dollar-sign absolute left-3 top-3.5 text-blue-500"></i>
            </div>
            <p class="text-xs text-gray-600 mt-1 ml-1">
              <i class="fas fa-info-circle text-blue-500"></i> ถ้าระบุราคา จะใช้ราคาคงที่ / ไม่ระบุจะคำนวณตามช่วงเวลา
            </p>
          </div>
        </div>
        
        <!-- ฟิลด์สำหรับคอร์ต VIP -->
        <div class="vip-fields hidden space-y-3">
          <div>
            <label class="block text-sm font-bold text-gray-700 mb-2">
              <i class="fas fa-door-open text-amber-500"></i> ชื่อห้อง VIP
            </label>
            <div class="relative">
              <input type="text" name="vip_room_name" placeholder="เช่น ห้อง VIP A, Executive Room..." maxlength="100"
                     class="w-full pl-10 pr-4 py-2.5 border-2 border-amber-300 rounded-xl focus:border-amber-500 
                            focus:ring-2 focus:ring-amber-200 transition-all outline-none font-medium text-gray-700
                            bg-gradient-to-br from-amber-50 to-yellow-50">
              <i class="fas fa-signature absolute left-3 top-3.5 text-amber-500"></i>
            </div>
            <p class="text-xs text-gray-600 mt-1 ml-1">
              <i class="fas fa-lightbulb text-yellow-500"></i> ตัวอย่าง: VIP A, Executive, Royal Suite, Diamond Room
            </p>
          </div>
          
          <div>
            <label class="block text-sm font-bold text-gray-700 mb-2">
              <i class="fas fa-tag text-amber-500"></i> ราคา VIP (บาท/ชั่วโมง)
            </label>
            <div class="relative">
              <input type="number" step="0.01" min="0.01" name="vip_price" placeholder="เช่น 500, 800, 1000..."
                     class="w-full pl-10 pr-4 py-2.5 border-2 border-amber-300 rounded-xl focus:border-amber-500 
                            focus:ring-2 focus:ring-amber-200 transition-all outline-none font-medium text-gray-700
                            bg-gradient-to-br from-amber-50 to-yellow-50">
              <i class="fas fa-dollar-sign absolute left-3 top-3.5 text-amber-500"></i>
            </div>
          </div>
        </div>
        
        <button type="submit" name="create"
                class="mt-2 px-6 py-3 bg-gradient-to-r from-blue-500 via-blue-600 to-purple-600 text-white rounded-xl font-bold
                       hover:from-blue-600 hover:via-blue-700 hover:to-purple-700 hover:shadow-xl transform hover:scale-105 
                       transition-all duration-300 flex items-center justify-center gap-2 shadow-lg">
          <i class="fas fa-plus-circle"></i>
          <span>เพิ่มคอร์ต</span>
        </button>
      </form>
    </div>
  </div>

  <!-- Stats Cards -->
  <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-lg p-5 hover:shadow-xl transition-all transform hover:scale-105">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-gray-600 text-sm mb-1 font-medium">คอร์ตทั้งหมด</p>
          <p class="text-3xl font-bold bg-gradient-to-r from-gray-700 to-gray-900 bg-clip-text text-transparent"><?= $totalCourts ?></p>
        </div>
        <div class="w-14 h-14 bg-gradient-to-br from-gray-700 to-gray-900 rounded-xl flex items-center justify-center shadow-md">
          <i class="fas fa-warehouse text-white text-2xl"></i>
        </div>
      </div>
    </div>
    
    <div class="bg-gradient-to-br from-amber-400 via-amber-500 to-amber-600 rounded-xl shadow-lg p-5 hover:shadow-2xl transition-all transform hover:scale-105">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-white/90 text-sm mb-1 font-medium">ห้อง VIP</p>
          <p class="text-4xl font-bold text-white drop-shadow-lg"><?= $vipCourts ?></p>
        </div>
        <div class="w-14 h-14 bg-white/20 backdrop-blur rounded-xl flex items-center justify-center shadow-lg">
          <i class="fas fa-crown text-white text-2xl drop-shadow-md"></i>
        </div>
      </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-lg p-5 hover:shadow-xl transition-all transform hover:scale-105">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-gray-600 text-sm mb-1 font-medium">คอร์ตปกติ</p>
          <p class="text-3xl font-bold bg-gradient-to-r from-slate-600 to-slate-800 bg-clip-text text-transparent"><?= $normalCourts ?></p>
        </div>
        <div class="w-14 h-14 bg-gradient-to-br from-slate-500 to-slate-700 rounded-xl flex items-center justify-center shadow-md">
          <i class="fas fa-table-tennis text-white text-2xl"></i>
        </div>
      </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-lg p-5 hover:shadow-xl transition-all transform hover:scale-105">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-gray-600 text-sm mb-1 font-medium">พร้อมใช้งาน</p>
          <p class="text-3xl font-bold text-green-600"><?= $availableCourts ?></p>
        </div>
        <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl flex items-center justify-center shadow-md">
          <i class="fas fa-check-circle text-white text-2xl"></i>
        </div>
      </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-lg p-5 hover:shadow-xl transition-all transform hover:scale-105">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-gray-600 text-sm mb-1 font-medium">กำลังใช้งาน</p>
          <p class="text-3xl font-bold text-blue-600"><?= $inUseCourts ?></p>
        </div>
        <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center shadow-md">
          <i class="fas fa-play-circle text-white text-2xl"></i>
        </div>
      </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-lg p-5 hover:shadow-xl transition-all transform hover:scale-105">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-gray-600 text-sm mb-1 font-medium">ซ่อมบำรุง</p>
          <p class="text-3xl font-bold text-orange-600"><?= $maintenanceCourts ?></p>
        </div>
        <div class="w-14 h-14 bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl flex items-center justify-center shadow-md">
          <i class="fas fa-tools text-white text-2xl"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Courts List -->
  <div class="bg-white rounded-2xl shadow-xl overflow-hidden border border-gray-100">
    <?php if(count($courts) > 0): ?>
    <div class="overflow-x-auto">
      <table class="w-full">
        <thead>
          <tr class="bg-gradient-to-r from-slate-700 via-slate-800 to-slate-900 text-white">
            <th class="px-6 py-4 text-left font-bold">
              <i class="fas fa-hashtag mr-2"></i>ID
            </th>
            <th class="px-6 py-4 text-left font-bold">
              <i class="fas fa-door-open mr-2"></i>ชื่อคอร์ต/ห้อง
            </th>
            <th class="px-6 py-4 text-center font-bold">
              <i class="fas fa-star mr-2"></i>ประเภท
            </th>
            <th class="px-6 py-4 text-center font-bold">
              <i class="fas fa-tag mr-2"></i>ราคา
            </th>
            <th class="px-6 py-4 text-center font-bold">
              <i class="fas fa-info-circle mr-2"></i>สถานะ
            </th>
            <th class="px-6 py-4 text-center font-bold">
              <i class="fas fa-cog mr-2"></i>จัดการ
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <?php foreach($courts as $c): 
            $statusColors = [
              'Available' => ['bg' => 'green', 'icon' => 'check-circle'],
              'Booked' => ['bg' => 'yellow', 'icon' => 'calendar-check'],
              'In Use' => ['bg' => 'blue', 'icon' => 'play-circle'],
              'Maintenance' => ['bg' => 'orange', 'icon' => 'tools']
            ];
            $color = $statusColors[$c['status']] ?? ['bg' => 'gray', 'icon' => 'circle'];
            $isVip = ($c['court_type'] === 'vip' || $c['is_vip'] == 1);
            $displayName = $isVip ? ($c['vip_room_name'] ?? 'ห้อง VIP') : 'คอร์ต ' . $c['court_no'];
          ?>
          <tr class="hover:bg-gray-50 transition-all <?= $isVip ? 'bg-gradient-to-r from-amber-50/50 via-yellow-50/30 to-amber-50/50' : '' ?>">
            <td class="px-6 py-4 text-gray-700 font-bold">
              <?= $c['id'] ?>
            </td>
            <td class="px-6 py-4">
              <div class="flex items-center gap-3">
                <div class="w-11 h-11 <?= $isVip ? 'bg-gradient-to-br from-amber-400 via-amber-500 to-amber-600 shadow-lg' : 'bg-gradient-to-br from-blue-500 to-purple-600 shadow-md' ?> rounded-xl flex items-center justify-center text-white font-bold text-lg relative">
                  <?php if($isVip): ?>
                  <i class="fas fa-door-open"></i>
                  <i class="fas fa-crown absolute -top-1 -right-1 text-yellow-300 text-xs drop-shadow"></i>
                  <?php else: ?>
                  <?= $c['court_no'] ?>
                  <?php endif; ?>
                </div>
                <span class="font-bold text-gray-800"><?= htmlspecialchars($displayName) ?></span>
              </div>
            </td>
            <td class="px-6 py-4 text-center">
              <?php if($isVip): ?>
              <span class="inline-flex items-center px-4 py-1.5 rounded-full text-sm font-bold bg-gradient-to-r from-amber-400 to-amber-500 text-white shadow-md">
                <i class="fas fa-crown mr-2"></i> VIP
              </span>
              <?php else: ?>
              <span class="inline-flex items-center px-4 py-1.5 rounded-full text-sm font-bold bg-gray-200 text-gray-700 shadow-sm">
                <i class="fas fa-table-tennis mr-2"></i> ปกติ
              </span>
              <?php endif; ?>
            </td>
            <td class="px-6 py-4 text-center">
              <?php if($isVip && $c['vip_price']): ?>
              <span class="font-bold text-lg text-amber-600">
                <?= number_format($c['vip_price'], 2) ?> <span class="text-sm">฿/ชม.</span>
              </span>
              <p class="text-xs text-amber-500 mt-1">VIP</p>
              <?php elseif(!$isVip && $c['normal_price']): ?>
              <span class="font-bold text-lg text-blue-600">
                <?= number_format($c['normal_price'], 2) ?> <span class="text-sm">฿/ชม.</span>
              </span>
              <p class="text-xs text-blue-500 mt-1">ราคาคงที่</p>
              <?php else: ?>
              <span class="text-gray-500 font-medium text-sm">
                <i class="fas fa-clock"></i> ตามเวลา
              </span>
              <?php endif; ?>
            </td>
            <td class="px-6 py-4 text-center">
              <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-bold 
                           bg-<?= $color['bg'] ?>-100 text-<?= $color['bg'] ?>-700 shadow-sm">
                <i class="fas fa-<?= $color['icon'] ?> mr-2"></i>
                <?= $statusThai[$c['status']] ?? $c['status'] ?>
              </span>
            </td>
            <td class="px-6 py-4">
              <form method="post" class="flex items-center justify-center gap-2 flex-wrap">
                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                
                <select name="court_type" onchange="toggleCourtType(this)"
                        class="px-3 py-2 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all outline-none text-sm font-bold">
                  <option value="normal" <?= !$isVip ? 'selected' : '' ?>>🏸 ปกติ</option>
                  <option value="vip" <?= $isVip ? 'selected' : '' ?>>⭐ VIP</option>
                </select>
                
                <div class="normal-fields <?= $isVip ? 'hidden' : '' ?>">
                  <input type="number" name="court_no" value="<?= $c['court_no'] ?>"
                         class="w-20 px-3 py-2 border-2 border-gray-300 rounded-lg text-center focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all outline-none font-bold">
                  <input type="number" step="0.01" name="normal_price" value="<?= $c['normal_price'] ?? '' ?>" placeholder="ราคา (optional)"
                         class="w-28 px-3 py-2 border-2 border-blue-300 rounded-lg text-center focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all outline-none font-bold bg-blue-50"
                         title="ราคาคงที่ (ไม่ระบุ = ใช้ราคาตามเวลา)">
                </div>
                
                <div class="vip-fields <?= $isVip ? '' : 'hidden' ?> flex gap-2">
                  <input type="text" name="vip_room_name" value="<?= htmlspecialchars($c['vip_room_name'] ?? '') ?>" placeholder="ชื่อห้อง"
                         class="w-32 px-3 py-2 border-2 border-amber-300 rounded-lg focus:border-amber-500 focus:ring-2 focus:ring-amber-200 transition-all outline-none font-bold bg-amber-50">
                  <input type="number" step="0.01" name="vip_price" value="<?= $c['vip_price'] ?? '' ?>" placeholder="ราคา"
                         class="w-24 px-3 py-2 border-2 border-amber-300 rounded-lg text-center focus:border-amber-500 focus:ring-2 focus:ring-amber-200 transition-all outline-none font-bold bg-amber-50">
                </div>
                
                <select name="status" class="px-3 py-2 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all outline-none text-sm font-medium">
                  <?php foreach(['Available','Booked','In Use','Maintenance'] as $s): ?>
                    <option value="<?= $s ?>" <?= $c['status'] === $s ? 'selected' : '' ?>><?= $statusThai[$s] ?></option>
                  <?php endforeach; ?>
                </select>
                
                <button type="submit" name="update"
                        class="px-4 py-2 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-lg text-sm font-bold hover:from-blue-600 hover:to-blue-700 hover:shadow-lg transform hover:scale-105 transition-all">
                  <i class="fas fa-save mr-1"></i>บันทึก
                </button>
                
                <a href="?delete=<?= $c['id'] ?>" 
                   onclick="return confirm('⚠️ ยืนยันลบ <?= htmlspecialchars($displayName) ?>?\n\nการลบจะไม่สามารถกู้คืนได้!')"
                   class="px-4 py-2 bg-gradient-to-r from-red-500 to-red-600 text-white rounded-lg text-sm font-bold hover:from-red-600 hover:to-red-700 hover:shadow-lg transform hover:scale-105 transition-all">
                  <i class="fas fa-trash mr-1"></i>ลบ
                </a>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="p-16 text-center">
      <div class="w-24 h-24 bg-gradient-to-br from-gray-200 to-gray-300 rounded-full flex items-center justify-center mx-auto mb-6">
        <i class="fas fa-warehouse text-5xl text-gray-400"></i>
      </div>
      <h3 class="text-3xl font-bold text-gray-800 mb-3">ยังไม่มีคอร์ต</h3>
      <p class="text-gray-600 text-lg mb-2">เริ่มต้นเพิ่มคอร์ตแบดมินตันของคุณได้เลย</p>
      <div class="mt-4 space-y-2">
        <p class="text-gray-700 font-medium">🏸 คอร์ตปกติ: ใช้ตัวเลข (1, 2, 3...)</p>
        <p class="text-amber-600 font-medium">⭐ ห้อง VIP: ใส่ชื่อห้องได้ (VIP A, Executive...)</p>
      </div>
    </div>
    <?php endif; ?>
  </div>

</div>

<?php include __DIR__.'/../includes/footer.php'; ?>
</body>
</html>