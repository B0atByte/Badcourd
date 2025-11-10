<?php
require_once __DIR__.'/../auth/guard.php';
require_role(['admin']);
require_once __DIR__.'/../config/db.php';

// เพิ่มผู้ใช้งาน
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role = $_POST['role'];
    if ($username && $password) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, active, created_at)
                               VALUES (:u, :p, :r, 1, NOW())");
        $stmt->execute([':u' => $username, ':p' => $hash, ':r' => $role]);
    }
    header('Location: users.php');
    exit;
}

// ปิด/เปิดการใช้งาน
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $pdo->query("UPDATE users SET active = 1 - active WHERE id = $id");
    header('Location: users.php');
    exit;
}

// ลบผู้ใช้
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM users WHERE id = :id")->execute([':id' => $id]);
    header('Location: users.php');
    exit;
}

$users = $pdo->query("SELECT * FROM users ORDER BY id ASC")->fetchAll();

// คำนวณสถิติ
$totalUsers = count($users);
$activeUsers = count(array_filter($users, fn($u) => $u['active'] == 1));
$inactiveUsers = $totalUsers - $activeUsers;
$adminCount = count(array_filter($users, fn($u) => $u['role'] === 'admin'));
$userCount = $totalUsers - $adminCount;
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <title>จัดการผู้ใช้งาน - BadCourt</title>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
<?php include __DIR__.'/../includes/header.php'; ?>

<div class="container mx-auto px-4 py-8 max-w-7xl">
  <!-- Header Section -->
  <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
      <div>
        <h1 class="text-3xl font-bold text-gray-800 flex items-center gap-3 mb-2">
          <i class="fas fa-users text-purple-600"></i>
          จัดการผู้ใช้งาน
        </h1>
        <p class="text-gray-600 flex items-center gap-2">
          <i class="fas fa-shield-alt text-purple-500"></i>
          <span class="font-semibold">Admin Panel</span> - จัดการบัญชีผู้ใช้ทั้งหมด
        </p>
      </div>
    </div>
  </div>

  <!-- Stats Cards -->
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-md p-5 hover:shadow-lg transition-shadow">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-gray-600 text-sm mb-1">ผู้ใช้ทั้งหมด</p>
          <p class="text-3xl font-bold text-gray-800"><?= $totalUsers ?></p>
        </div>
        <div class="w-14 h-14 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-xl flex items-center justify-center">
          <i class="fas fa-users text-white text-2xl"></i>
        </div>
      </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-md p-5 hover:shadow-lg transition-shadow">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-gray-600 text-sm mb-1">ใช้งานอยู่</p>
          <p class="text-3xl font-bold text-green-600"><?= $activeUsers ?></p>
        </div>
        <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl flex items-center justify-center">
          <i class="fas fa-user-check text-white text-2xl"></i>
        </div>
      </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-md p-5 hover:shadow-lg transition-shadow">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-gray-600 text-sm mb-1">ปิดใช้งาน</p>
          <p class="text-3xl font-bold text-red-600"><?= $inactiveUsers ?></p>
        </div>
        <div class="w-14 h-14 bg-gradient-to-br from-red-500 to-pink-600 rounded-xl flex items-center justify-center">
          <i class="fas fa-user-slash text-white text-2xl"></i>
        </div>
      </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-md p-5 hover:shadow-lg transition-shadow">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-gray-600 text-sm mb-1">ผู้ดูแลระบบ</p>
          <p class="text-3xl font-bold text-orange-600"><?= $adminCount ?></p>
        </div>
        <div class="w-14 h-14 bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl flex items-center justify-center">
          <i class="fas fa-crown text-white text-2xl"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Add User Form -->
  <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
    <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
      <i class="fas fa-user-plus text-purple-600"></i>
      เพิ่มผู้ใช้งานใหม่
    </h2>
    
    <form method="post" class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <!-- Username -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">
          <i class="fas fa-user text-blue-500 mr-1"></i>ชื่อผู้ใช้
        </label>
        <input type="text" name="username" placeholder="Username" required
               class="w-full px-4 py-2.5 border-2 border-gray-300 rounded-xl focus:border-purple-500 
                      focus:ring-2 focus:ring-purple-200 transition-all outline-none font-medium">
      </div>

      <!-- Password -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">
          <i class="fas fa-lock text-green-500 mr-1"></i>รหัสผ่าน
        </label>
        <input type="password" name="password" placeholder="Password" required
               class="w-full px-4 py-2.5 border-2 border-gray-300 rounded-xl focus:border-purple-500 
                      focus:ring-2 focus:ring-purple-200 transition-all outline-none font-medium">
      </div>

      <!-- Role -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">
          <i class="fas fa-shield-alt text-orange-500 mr-1"></i>สิทธิ์การใช้งาน
        </label>
        <select name="role" required
                class="w-full px-4 py-2.5 border-2 border-gray-300 rounded-xl focus:border-purple-500 
                       focus:ring-2 focus:ring-purple-200 transition-all outline-none font-medium">
          <option value="user">👤 ผู้ใช้งาน</option>
          <option value="admin">👑 ผู้ดูแลระบบ</option>
        </select>
      </div>

      <!-- Submit Button -->
      <div class="flex items-end">
        <button type="submit" name="add_user"
                class="w-full px-6 py-2.5 bg-gradient-to-r from-purple-500 to-indigo-600 text-white rounded-xl 
                       font-bold hover:from-purple-600 hover:to-indigo-700 hover:shadow-lg transform hover:scale-105 
                       transition-all duration-300 flex items-center justify-center gap-2">
          <i class="fas fa-user-plus"></i>
          <span>เพิ่มผู้ใช้</span>
        </button>
      </div>
    </form>
  </div>

  <!-- Users List -->
  <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
    <div class="bg-gradient-to-r from-purple-500 to-indigo-600 px-6 py-4">
      <h2 class="text-xl font-bold text-white flex items-center gap-2">
        <i class="fas fa-list-ul"></i>
        รายชื่อผู้ใช้งานทั้งหมด
      </h2>
    </div>

    <!-- Mobile View -->
    <div class="block lg:hidden">
      <?php foreach($users as $u): 
        $roleColor = $u['role'] === 'admin' ? 'orange' : 'blue';
        $roleIcon = $u['role'] === 'admin' ? 'crown' : 'user';
        $roleText = $u['role'] === 'admin' ? 'ผู้ดูแลระบบ' : 'ผู้ใช้งาน';
        $statusColor = $u['active'] ? 'green' : 'red';
        $statusIcon = $u['active'] ? 'check-circle' : 'times-circle';
        $statusText = $u['active'] ? 'ใช้งานอยู่' : 'ปิดใช้งาน';
      ?>
      <div class="border-b border-gray-200 p-5 hover:bg-gray-50 transition-colors">
        <div class="flex justify-between items-start mb-3">
          <div class="flex items-center gap-3">
            <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-full flex items-center justify-center text-white font-bold text-lg">
              <i class="fas fa-<?= $roleIcon ?>"></i>
            </div>
            <div>
              <h3 class="font-bold text-lg text-gray-800"><?= htmlspecialchars($u['username']) ?></h3>
              <p class="text-sm text-gray-500">ID: <?= $u['id'] ?></p>
            </div>
          </div>
        </div>
        
        <div class="flex gap-2 mb-3">
          <span class="px-3 py-1 rounded-full text-xs font-semibold bg-<?= $roleColor ?>-100 text-<?= $roleColor ?>-700">
            <i class="fas fa-<?= $roleIcon ?> mr-1"></i><?= $roleText ?>
          </span>
          <span class="px-3 py-1 rounded-full text-xs font-semibold bg-<?= $statusColor ?>-100 text-<?= $statusColor ?>-700">
            <i class="fas fa-<?= $statusIcon ?> mr-1"></i><?= $statusText ?>
          </span>
        </div>
        
        <p class="text-sm text-gray-600 mb-3">
          <i class="fas fa-calendar-alt mr-2"></i>
          สร้างเมื่อ: <?= date('d/m/Y H:i', strtotime($u['created_at'])) ?>
        </p>
        
        <div class="flex gap-2">
          <a href="?toggle=<?= $u['id'] ?>" 
             class="flex-1 px-4 py-2 bg-yellow-500 text-white rounded-lg font-medium hover:bg-yellow-600 transition-colors text-center">
            <i class="fas fa-power-off mr-2"></i><?= $u['active'] ? 'ปิดใช้งาน' : 'เปิดใช้งาน' ?>
          </a>
          <a href="?delete=<?= $u['id'] ?>" 
             onclick="return confirm('ยืนยันการลบผู้ใช้ <?= htmlspecialchars($u['username']) ?>?\n\nการลบจะไม่สามารถกู้คืนได้!')"
             class="flex-1 px-4 py-2 bg-red-500 text-white rounded-lg font-medium hover:bg-red-600 transition-colors text-center">
            <i class="fas fa-trash mr-2"></i>ลบ
          </a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Desktop View -->
    <div class="hidden lg:block overflow-x-auto">
      <table class="w-full">
        <thead>
          <tr class="bg-gradient-to-r from-gray-700 to-gray-900 text-white">
            <th class="px-6 py-4 text-left font-semibold">
              <i class="fas fa-hashtag mr-2"></i>ID
            </th>
            <th class="px-6 py-4 text-left font-semibold">
              <i class="fas fa-user mr-2"></i>ชื่อผู้ใช้
            </th>
            <th class="px-6 py-4 text-center font-semibold">
              <i class="fas fa-shield-alt mr-2"></i>สิทธิ์
            </th>
            <th class="px-6 py-4 text-center font-semibold">
              <i class="fas fa-toggle-on mr-2"></i>สถานะ
            </th>
            <th class="px-6 py-4 text-center font-semibold">
              <i class="fas fa-calendar-alt mr-2"></i>วันที่สร้าง
            </th>
            <th class="px-6 py-4 text-center font-semibold">
              <i class="fas fa-cog mr-2"></i>จัดการ
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <?php foreach($users as $u): 
            $roleColor = $u['role'] === 'admin' ? 'orange' : 'blue';
            $roleIcon = $u['role'] === 'admin' ? 'crown' : 'user';
            $roleText = $u['role'] === 'admin' ? 'ผู้ดูแลระบบ' : 'ผู้ใช้งาน';
            $statusColor = $u['active'] ? 'green' : 'red';
            $statusIcon = $u['active'] ? 'check-circle' : 'times-circle';
            $statusText = $u['active'] ? 'ใช้งานอยู่' : 'ปิดใช้งาน';
          ?>
          <tr class="hover:bg-gray-50 transition-colors">
            <td class="px-6 py-4 text-gray-700 font-semibold">
              <?= $u['id'] ?>
            </td>
            <td class="px-6 py-4">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-full flex items-center justify-center text-white font-bold">
                  <i class="fas fa-<?= $roleIcon ?>"></i>
                </div>
                <span class="font-semibold text-gray-800"><?= htmlspecialchars($u['username']) ?></span>
              </div>
            </td>
            <td class="px-6 py-4 text-center">
              <span class="inline-flex items-center px-4 py-1.5 rounded-full text-sm font-semibold 
                           bg-<?= $roleColor ?>-100 text-<?= $roleColor ?>-700">
                <i class="fas fa-<?= $roleIcon ?> mr-2"></i>
                <?= $roleText ?>
              </span>
            </td>
            <td class="px-6 py-4 text-center">
              <span class="inline-flex items-center px-4 py-1.5 rounded-full text-sm font-semibold 
                           bg-<?= $statusColor ?>-100 text-<?= $statusColor ?>-700">
                <i class="fas fa-<?= $statusIcon ?> mr-2"></i>
                <?= $statusText ?>
              </span>
            </td>
            <td class="px-6 py-4 text-center text-gray-700 font-medium">
              <?= date('d/m/Y H:i', strtotime($u['created_at'])) ?>
            </td>
            <td class="px-6 py-4 text-center">
              <div class="flex gap-2 justify-center">
                <a href="?toggle=<?= $u['id'] ?>" 
                   class="px-4 py-2 bg-yellow-500 text-white rounded-lg text-sm font-medium hover:bg-yellow-600 
                          hover:shadow-md transform hover:scale-105 transition-all inline-flex items-center gap-2">
                  <i class="fas fa-power-off"></i>
                  <span><?= $u['active'] ? 'ปิด' : 'เปิด' ?></span>
                </a>
                <a href="?delete=<?= $u['id'] ?>" 
                   onclick="return confirm('ยืนยันการลบผู้ใช้ <?= htmlspecialchars($u['username']) ?>?\n\nการลบจะไม่สามารถกู้คืนได้!')"
                   class="px-4 py-2 bg-red-500 text-white rounded-lg text-sm font-medium hover:bg-red-600 
                          hover:shadow-md transform hover:scale-105 transition-all inline-flex items-center gap-2">
                  <i class="fas fa-trash"></i>
                  <span>ลบ</span>
                </a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Empty State -->
    <?php if(count($users) === 0): ?>
    <div class="p-12 text-center">
      <i class="fas fa-users text-6xl text-gray-300 mb-4"></i>
      <h3 class="text-2xl font-bold text-gray-800 mb-2">ยังไม่มีผู้ใช้งาน</h3>
      <p class="text-gray-600">เริ่มต้นเพิ่มผู้ใช้งานเข้าสู่ระบบ</p>
    </div>
    <?php endif; ?>
  </div>

  <!-- Security Info Card -->
  <div class="mt-6 bg-gradient-to-r from-red-50 to-orange-50 rounded-2xl shadow-md p-6 border border-red-200">
    <div class="flex items-start gap-4">
      <div class="w-12 h-12 bg-gradient-to-br from-red-500 to-orange-600 rounded-xl flex items-center justify-center flex-shrink-0">
        <i class="fas fa-shield-alt text-white text-xl"></i>
      </div>
      <div>
        <h3 class="font-bold text-gray-800 mb-2">🔐 ข้อควรระวังด้านความปลอดภัย</h3>
        <ul class="text-sm text-gray-700 space-y-1">
          <li>• ใช้รหัสผ่านที่แข็งแรงสำหรับบัญชีผู้ดูแลระบบ</li>
          <li>• ตรวจสอบสิทธิ์การเข้าถึงของผู้ใช้เป็นประจำ</li>
          <li>• ปิดการใช้งานบัญชีที่ไม่ได้ใช้งานแล้ว</li>
          <li>• อย่าลบบัญชีผู้ดูแลระบบหลักโดยไม่มีสำรอง</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__.'/../includes/footer.php'; ?>
</body>
</html>