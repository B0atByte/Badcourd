<?php
require_once __DIR__.'/../auth/guard.php';
require_role(['admin']);
require_once __DIR__.'/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role = $_POST['role'];
    if ($username && $password) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, active, created_at) VALUES (:u, :p, :r, 1, NOW())");
        $stmt->execute([':u'=>$username,':p'=>$hash,':r'=>$role]);
    }
    header('Location: users.php'); exit;
}

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $pdo->query("UPDATE users SET active = 1 - active WHERE id = $id");
    header('Location: users.php'); exit;
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM users WHERE id = :id")->execute([':id'=>$id]);
    header('Location: users.php'); exit;
}

$users = $pdo->query("SELECT * FROM users ORDER BY id ASC")->fetchAll();

$totalUsers = count($users);
$activeUsers = count(array_filter($users, fn($u) => $u['active'] == 1));
$inactiveUsers = $totalUsers - $activeUsers;
$adminCount = count(array_filter($users, fn($u) => $u['role'] === 'admin'));
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
  <title>จัดการผู้ใช้งาน - BARGAIN SPORT</title>
</head>
<body style="background:#EDEDCE;" class="min-h-screen">
<?php include __DIR__.'/../includes/header.php'; ?>

<div class="max-w-5xl mx-auto px-4 py-8">

  <!-- Header -->
  <div class="mb-6">
    <h1 style="color:#0C2C55;" class="text-2xl font-bold">จัดการผู้ใช้งาน</h1>
    <p class="text-gray-500 text-sm mt-0.5">Admin Panel · จัดการบัญชีผู้ใช้ทั้งหมด</p>
  </div>

  <!-- Stats -->
  <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-gray-200 p-4">
      <p class="text-gray-400 text-xs mb-1">ผู้ใช้ทั้งหมด</p>
      <p style="color:#0C2C55;" class="text-2xl font-bold"><?= $totalUsers ?></p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4">
      <p class="text-gray-400 text-xs mb-1">ใช้งานอยู่</p>
      <p style="color:#296374;" class="text-2xl font-bold"><?= $activeUsers ?></p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4">
      <p class="text-gray-400 text-xs mb-1">ปิดใช้งาน</p>
      <p class="text-2xl font-bold text-gray-400"><?= $inactiveUsers ?></p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4">
      <p class="text-gray-400 text-xs mb-1">ผู้ดูแลระบบ</p>
      <p style="color:#629FAD;" class="text-2xl font-bold"><?= $adminCount ?></p>
    </div>
  </div>

  <!-- Add User Form -->
  <div class="bg-white rounded-xl border border-gray-200 p-6 mb-5">
    <h2 style="color:#0C2C55;" class="font-semibold mb-4 text-sm uppercase tracking-wide">เพิ่มผู้ใช้งานใหม่</h2>
    <form method="post" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">ชื่อผู้ใช้</label>
        <input type="text" name="username" placeholder="Username" required
               class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:border-[#629FAD] focus:ring-2 focus:ring-[#629FAD]/20 outline-none text-sm">
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">รหัสผ่าน</label>
        <input type="password" name="password" placeholder="Password" required
               class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:border-[#629FAD] focus:ring-2 focus:ring-[#629FAD]/20 outline-none text-sm">
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">สิทธิ์</label>
        <select name="role" required
                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:border-[#629FAD] focus:ring-2 focus:ring-[#629FAD]/20 outline-none text-sm">
          <option value="user">ผู้ใช้งาน</option>
          <option value="admin">ผู้ดูแลระบบ</option>
        </select>
      </div>
      <div class="flex items-end">
        <button type="submit" name="add_user"
                style="background:#296374;"
                class="w-full px-5 py-2.5 text-white text-sm font-medium rounded-lg hover:opacity-90 transition-opacity">
          + เพิ่มผู้ใช้
        </button>
      </div>
    </form>
  </div>

  <!-- Users List -->
  <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <div style="background:#0C2C55;" class="px-5 py-3">
      <h2 class="text-white font-medium text-sm">รายชื่อผู้ใช้งานทั้งหมด</h2>
    </div>

    <!-- Mobile -->
    <div class="block lg:hidden divide-y divide-gray-100">
      <?php foreach($users as $u):
        $roleText = $u['role'] === 'admin' ? 'ผู้ดูแลระบบ' : 'ผู้ใช้งาน';
        $statusText = $u['active'] ? 'ใช้งานอยู่' : 'ปิดใช้งาน';
      ?>
      <div class="p-4">
        <div class="flex justify-between items-start mb-2">
          <div>
            <p class="font-medium text-gray-800"><?= htmlspecialchars($u['username']) ?></p>
            <p class="text-gray-400 text-xs">ID: <?= $u['id'] ?></p>
          </div>
          <div class="flex gap-1">
            <span class="text-xs px-2 py-1 rounded-full <?= $u['role']==='admin' ? 'bg-[#EDEDCE] text-[#296374]' : 'bg-gray-100 text-gray-500' ?>">
              <?= $roleText ?>
            </span>
            <span class="text-xs px-2 py-1 rounded-full <?= $u['active'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-500' ?>">
              <?= $statusText ?>
            </span>
          </div>
        </div>
        <p class="text-gray-400 text-xs mb-3">สร้างเมื่อ: <?= date('d/m/Y H:i', strtotime($u['created_at'])) ?></p>
        <div class="flex gap-2">
          <a href="?toggle=<?= $u['id'] ?>"
             style="border-color:#629FAD; color:#296374;"
             class="flex-1 py-1.5 border text-xs rounded text-center hover:bg-[#EDEDCE] transition-colors">
            <?= $u['active'] ? 'ปิดใช้งาน' : 'เปิดใช้งาน' ?>
          </a>
          <a href="?delete=<?= $u['id'] ?>"
             onclick="return confirm('ยืนยันการลบผู้ใช้ <?= htmlspecialchars($u['username']) ?>?')"
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
            <th class="px-5 py-3 font-medium text-gray-500">ชื่อผู้ใช้</th>
            <th class="px-5 py-3 font-medium text-gray-500 text-center">สิทธิ์</th>
            <th class="px-5 py-3 font-medium text-gray-500 text-center">สถานะ</th>
            <th class="px-5 py-3 font-medium text-gray-500 text-center">วันที่สร้าง</th>
            <th class="px-5 py-3 font-medium text-gray-500 text-center">จัดการ</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php foreach($users as $u):
            $roleText = $u['role'] === 'admin' ? 'ผู้ดูแลระบบ' : 'ผู้ใช้งาน';
            $statusText = $u['active'] ? 'ใช้งานอยู่' : 'ปิดใช้งาน';
          ?>
          <tr class="hover:bg-gray-50 transition-colors">
            <td class="px-5 py-3 text-gray-400"><?= $u['id'] ?></td>
            <td class="px-5 py-3">
              <div class="flex items-center gap-2">
                <div style="background:#0C2C55;" class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-medium">
                  <?= strtoupper(substr($u['username'], 0, 1)) ?>
                </div>
                <span class="font-medium text-gray-800"><?= htmlspecialchars($u['username']) ?></span>
              </div>
            </td>
            <td class="px-5 py-3 text-center">
              <span class="text-xs px-2.5 py-1 rounded-full <?= $u['role']==='admin' ? 'bg-[#EDEDCE] text-[#296374] font-medium' : 'bg-gray-100 text-gray-500' ?>">
                <?= $roleText ?>
              </span>
            </td>
            <td class="px-5 py-3 text-center">
              <span class="text-xs px-2.5 py-1 rounded-full <?= $u['active'] ? 'bg-green-100 text-green-700' : 'bg-red-50 text-red-400' ?>">
                <?= $statusText ?>
              </span>
            </td>
            <td class="px-5 py-3 text-center text-gray-500 text-xs">
              <?= date('d/m/Y H:i', strtotime($u['created_at'])) ?>
            </td>
            <td class="px-5 py-3">
              <div class="flex gap-1.5 justify-center">
                <a href="?toggle=<?= $u['id'] ?>"
                   style="border-color:#629FAD; color:#296374;"
                   class="px-3 py-1.5 border text-xs rounded hover:bg-[#EDEDCE] transition-colors">
                  <?= $u['active'] ? 'ปิด' : 'เปิด' ?>
                </a>
                <a href="?delete=<?= $u['id'] ?>"
                   onclick="return confirm('ยืนยันการลบผู้ใช้ <?= htmlspecialchars($u['username']) ?>?')"
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

    <?php if(count($users) === 0): ?>
    <div class="p-10 text-center text-gray-400">ยังไม่มีผู้ใช้งาน</div>
    <?php endif; ?>
  </div>

  <!-- Security note -->
  <div class="mt-5 bg-white rounded-xl border border-gray-200 p-5">
    <h3 style="color:#0C2C55;" class="font-medium mb-2 text-sm">ข้อควรระวังด้านความปลอดภัย</h3>
    <ul class="text-gray-500 text-xs space-y-1">
      <li>· ใช้รหัสผ่านที่แข็งแรงสำหรับบัญชีผู้ดูแลระบบ</li>
      <li>· ตรวจสอบสิทธิ์การเข้าถึงของผู้ใช้เป็นประจำ</li>
      <li>· ปิดการใช้งานบัญชีที่ไม่ได้ใช้งานแล้ว</li>
      <li>· อย่าลบบัญชีผู้ดูแลระบบหลักโดยไม่มีสำรอง</li>
    </ul>
  </div>

</div>

<?php include __DIR__.'/../includes/footer.php'; ?>
</body>
</html>
