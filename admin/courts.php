<?php
require_once __DIR__.'/../auth/guard.php';
require_once __DIR__.'/../config/db.php';
require_role(['admin']);

// Create
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create'])) {
    $no = (int)$_POST['court_no'];
    $stmt = $pdo->prepare('INSERT INTO courts (court_no,status) VALUES (:n, "Available")');
    $stmt->execute([':n' => $no]);
    header('Location: courts.php');
    exit;
}

// Update status/name
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $id = (int)$_POST['id'];
    $no = (int)$_POST['court_no'];
    $st = $_POST['status'];
    $stmt = $pdo->prepare('UPDATE courts SET court_no=:n, status=:s WHERE id=:id');
    $stmt->execute([':n' => $no, ':s' => $st, ':id' => $id]);
    header('Location: courts.php');
    exit;
}

// Delete with confirm
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare('DELETE FROM courts WHERE id=:id')->execute([':id' => $id]);
    header('Location: courts.php');
    exit;
}

$courts = $pdo->query('SELECT * FROM courts ORDER BY court_no')->fetchAll();

// คำนวณสถิติ
$totalCourts = count($courts);
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
  <title>จัดการคอร์ต - BARGAIN SPORT</title>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
<?php include __DIR__.'/../includes/header.php'; ?>

<div class="container mx-auto px-4 py-8 max-w-7xl">
  <!-- Header Section -->
  <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
      <div>
        <h1 class="text-3xl font-bold text-gray-800 flex items-center gap-3 mb-2">
          <i class="fas fa-warehouse text-slate-600"></i>
          จัดการคอร์ต
        </h1>
        <p class="text-gray-600 flex items-center gap-2">
          <i class="fas fa-shield-alt text-purple-500"></i>
          <span class="font-semibold">Admin Panel</span> - จัดการคอร์ตแบดมินตันทั้งหมด
        </p>
      </div>
      
      <!-- Add Court Form -->
      <form method="post" class="flex flex-col sm:flex-row gap-3 w-full lg:w-auto">
        <div class="relative">
          <input type="number" min="1" name="court_no" placeholder="หมายเลขคอร์ต" required
                 class="pl-10 pr-4 py-2.5 border-2 border-gray-300 rounded-xl focus:border-blue-500 
                        focus:ring-2 focus:ring-blue-200 transition-all outline-none font-medium text-gray-700 w-full sm:w-48">
          <i class="fas fa-hashtag absolute left-3 top-3.5 text-gray-400"></i>
        </div>
        
        <button type="submit" name="create"
                class="px-6 py-2.5 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-xl font-medium 
                       hover:from-blue-600 hover:to-blue-700 hover:shadow-lg transform hover:scale-105 
                       transition-all duration-300 flex items-center justify-center gap-2">
          <i class="fas fa-plus-circle"></i>
          <span>เพิ่มคอร์ต</span>
        </button>
      </form>
    </div>
  </div>

  <!-- Stats Cards -->
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-md p-5 hover:shadow-lg transition-shadow">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-gray-600 text-sm mb-1">คอร์ตทั้งหมด</p>
          <p class="text-3xl font-bold text-gray-800"><?= $totalCourts ?></p>
        </div>
        <div class="w-14 h-14 bg-gradient-to-br from-gray-700 to-gray-900 rounded-xl flex items-center justify-center">
          <i class="fas fa-warehouse text-white text-2xl"></i>
        </div>
      </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-md p-5 hover:shadow-lg transition-shadow">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-gray-600 text-sm mb-1">พร้อมใช้งาน</p>
          <p class="text-3xl font-bold text-green-600"><?= $availableCourts ?></p>
        </div>
        <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl flex items-center justify-center">
          <i class="fas fa-check-circle text-white text-2xl"></i>
        </div>
      </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-md p-5 hover:shadow-lg transition-shadow">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-gray-600 text-sm mb-1">กำลังใช้งาน</p>
          <p class="text-3xl font-bold text-blue-600"><?= $inUseCourts ?></p>
        </div>
        <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center">
          <i class="fas fa-play-circle text-white text-2xl"></i>
        </div>
      </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-md p-5 hover:shadow-lg transition-shadow">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-gray-600 text-sm mb-1">ซ่อมบำรุง</p>
          <p class="text-3xl font-bold text-orange-600"><?= $maintenanceCourts ?></p>
        </div>
        <div class="w-14 h-14 bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl flex items-center justify-center">
          <i class="fas fa-tools text-white text-2xl"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Courts List -->
  <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
    <!-- Mobile View -->
    <div class="block lg:hidden">
      <?php foreach($courts as $c): 
        $statusColors = [
          'Available' => ['bg' => 'green', 'icon' => 'check-circle'],
          'Booked' => ['bg' => 'yellow', 'icon' => 'calendar-check'],
          'In Use' => ['bg' => 'blue', 'icon' => 'play-circle'],
          'Maintenance' => ['bg' => 'orange', 'icon' => 'tools']
        ];
        $color = $statusColors[$c['status']] ?? ['bg' => 'gray', 'icon' => 'circle'];
      ?>
      <div class="border-b border-gray-200 p-5">
        <div class="flex justify-between items-start mb-4">
          <div class="flex items-center gap-3">
            <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-purple-600 rounded-xl flex items-center justify-center text-white font-bold text-lg">
              <?= $c['court_no'] ?>
            </div>
            <div>
              <h3 class="font-bold text-lg text-gray-800">คอร์ต <?= $c['court_no'] ?></h3>
              <p class="text-sm text-gray-500">ID: <?= $c['id'] ?></p>
            </div>
          </div>
          <span class="px-3 py-1 rounded-full text-xs font-semibold bg-<?= $color['bg'] ?>-100 text-<?= $color['bg'] ?>-700">
            <i class="fas fa-<?= $color['icon'] ?> mr-1"></i><?= $statusThai[$c['status']] ?? $c['status'] ?>
          </span>
        </div>
        
        <form method="post" class="space-y-3">
          <input type="hidden" name="id" value="<?= $c['id'] ?>">
          
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">หมายเลขคอร์ต</label>
            <input type="number" name="court_no" value="<?= $c['court_no'] ?>"
                   class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all outline-none">
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">สถานะ</label>
            <select name="status" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all outline-none">
              <?php foreach(['Available','Booked','In Use','Maintenance'] as $s): ?>
                <option value="<?= $s ?>" <?= $c['status'] === $s ? 'selected' : '' ?>><?= $statusThai[$s] ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <div class="flex gap-2">
            <button type="submit" name="update" 
                    class="flex-1 px-4 py-2 bg-blue-500 text-white rounded-lg font-medium hover:bg-blue-600 transition-colors">
              <i class="fas fa-save mr-2"></i>บันทึก
            </button>
            <a href="?delete=<?= $c['id'] ?>" 
               onclick="return confirm('ยืนยันลบคอร์ต <?= $c['court_no'] ?>?')"
               class="flex-1 px-4 py-2 bg-red-500 text-white rounded-lg font-medium hover:bg-red-600 transition-colors text-center">
              <i class="fas fa-trash mr-2"></i>ลบ
            </a>
          </div>
        </form>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Desktop View -->
    <div class="hidden lg:block overflow-x-auto">
      <table class="w-full">
        <thead>
          <tr class="bg-gradient-to-r from-slate-700 to-slate-900 text-white">
            <th class="px-6 py-4 text-left font-semibold">
              <i class="fas fa-hashtag mr-2"></i>ID
            </th>
            <th class="px-6 py-4 text-left font-semibold">
              <i class="fas fa-warehouse mr-2"></i>หมายเลขคอร์ต
            </th>
            <th class="px-6 py-4 text-center font-semibold">
              <i class="fas fa-info-circle mr-2"></i>สถานะ
            </th>
            <th class="px-6 py-4 text-center font-semibold">
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
          ?>
          <tr class="hover:bg-gray-50 transition-colors">
            <td class="px-6 py-4 text-gray-700 font-semibold">
              <?= $c['id'] ?>
            </td>
            <td class="px-6 py-4">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-purple-600 rounded-lg flex items-center justify-center text-white font-bold">
                  <?= $c['court_no'] ?>
                </div>
                <span class="font-semibold text-gray-800">คอร์ต <?= $c['court_no'] ?></span>
              </div>
            </td>
            <td class="px-6 py-4 text-center">
              <span class="inline-flex items-center px-4 py-1.5 rounded-full text-sm font-semibold 
                           bg-<?= $color['bg'] ?>-100 text-<?= $color['bg'] ?>-700">
                <i class="fas fa-<?= $color['icon'] ?> mr-2"></i>
                <?= $statusThai[$c['status']] ?? $c['status'] ?>
              </span>
            </td>
            <td class="px-6 py-4">
              <form method="post" class="flex items-center justify-center gap-2">
                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                
                <input type="number" name="court_no" value="<?= $c['court_no'] ?>"
                       class="w-20 px-3 py-1.5 border-2 border-gray-300 rounded-lg text-center focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all outline-none font-semibold">
                
                <select name="status" class="px-3 py-1.5 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all outline-none text-sm font-medium">
                  <?php foreach(['Available','Booked','In Use','Maintenance'] as $s): ?>
                    <option value="<?= $s ?>" <?= $c['status'] === $s ? 'selected' : '' ?>><?= $statusThai[$s] ?></option>
                  <?php endforeach; ?>
                </select>
                
                <button type="submit" name="update"
                        class="px-4 py-1.5 bg-blue-500 text-white rounded-lg text-sm font-medium hover:bg-blue-600 hover:shadow-md transform hover:scale-105 transition-all">
                  <i class="fas fa-save mr-1"></i>บันทึก
                </button>
                
                <a href="?delete=<?= $c['id'] ?>" 
                   onclick="return confirm('ยืนยันลบคอร์ต <?= $c['court_no'] ?>?\n\nการลบจะไม่สามารถกู้คืนได้!')"
                   class="px-4 py-1.5 bg-red-500 text-white rounded-lg text-sm font-medium hover:bg-red-600 hover:shadow-md transform hover:scale-105 transition-all">
                  <i class="fas fa-trash mr-1"></i>ลบ
                </a>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if(count($courts) === 0): ?>
  <div class="bg-white rounded-2xl shadow-lg p-12 text-center mt-6">
    <i class="fas fa-warehouse text-6xl text-gray-300 mb-4"></i>
    <h3 class="text-2xl font-bold text-gray-800 mb-2">ยังไม่มีคอร์ต</h3>
    <p class="text-gray-600 mb-6">เริ่มต้นเพิ่มคอร์ตแบดมินตันของคุณได้เลย</p>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__.'/../includes/footer.php'; ?>
</body>
</html>