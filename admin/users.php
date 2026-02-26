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
    $pdo->prepare("UPDATE users SET active = 1 - active WHERE id = ?")->execute([$id]);
    header('Location: users.php'); exit;
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM users WHERE id = :id")->execute([':id'=>$id]);
    header('Location: users.php'); exit;
}

// Stats from ALL users (not filtered)
$statsRow   = $pdo->query("SELECT COUNT(*) as total, SUM(active) as act, SUM(role='admin') as adm FROM users")->fetch();
$totalUsers    = (int)$statsRow['total'];
$activeUsers   = (int)$statsRow['act'];
$inactiveUsers = $totalUsers - $activeUsers;
$adminCount    = (int)$statsRow['adm'];

// Search / Filter / Pagination
$search        = trim($_GET['search']  ?? '');
$role_filter   = $_GET['role']   ?? '';
$status_filter = $_GET['ustatus'] ?? '';
$per_page_raw  = $_GET['per_page'] ?? 'all';
$per_page      = ($per_page_raw === 'all') ? 0 : (int)$per_page_raw;
if (!in_array($per_page, [0, 10, 25, 50, 100])) $per_page = 0;
$page = max(1, (int)($_GET['page'] ?? 1));

$uWhere  = ['1=1'];
$uParams = [];
if (!empty($search)) {
    $uWhere[]  = 'username LIKE :search';
    $uParams[':search'] = '%' . $search . '%';
}
if (!empty($role_filter)) {
    $uWhere[]  = 'role = :role';
    $uParams[':role'] = $role_filter;
}
if ($status_filter === 'active') {
    $uWhere[] = 'active = 1';
} elseif ($status_filter === 'inactive') {
    $uWhere[] = 'active = 0';
}
$uWhereClause = implode(' AND ', $uWhere);

// Count
$cntStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE $uWhereClause");
$cntStmt->execute($uParams);
$totalRecords = (int)$cntStmt->fetchColumn();

// Pagination calc
$totalPages = 1; $offset = 0;
if ($per_page > 0) {
    $totalPages = max(1, (int)ceil($totalRecords / $per_page));
    $page       = min($page, $totalPages);
    $offset     = ($page - 1) * $per_page;
}

// Fetch users (filtered + paginated)
$uQuery = "SELECT * FROM users WHERE $uWhereClause ORDER BY id ASC";
if ($per_page > 0) $uQuery .= " LIMIT $per_page OFFSET $offset";
$uStmt = $pdo->prepare($uQuery);
$uStmt->execute($uParams);
$users = $uStmt->fetchAll();
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
  <title>จัดการผู้ใช้งาน - BARGAIN SPORT</title>
</head>
<body style="background:#FAFAFA;" class="min-h-screen">
<?php include __DIR__.'/../includes/header.php'; ?>

<div class="max-w-5xl mx-auto px-4 py-8">

  <!-- Header -->
  <div class="mb-6">
    <h1 style="color:#005691;" class="text-2xl font-bold">จัดการผู้ใช้งาน</h1>
    <p class="text-gray-500 text-sm mt-0.5">Admin Panel · จัดการบัญชีผู้ใช้ทั้งหมด</p>
  </div>

  <!-- Stats -->
  <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-gray-200 p-4">
      <p class="text-gray-400 text-xs mb-1">ผู้ใช้ทั้งหมด</p>
      <p style="color:#005691;" class="text-2xl font-bold"><?= $totalUsers ?></p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4">
      <p class="text-gray-400 text-xs mb-1">ใช้งานอยู่</p>
      <p style="color:#004A7C;" class="text-2xl font-bold"><?= $activeUsers ?></p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4">
      <p class="text-gray-400 text-xs mb-1">ปิดใช้งาน</p>
      <p class="text-2xl font-bold text-gray-400"><?= $inactiveUsers ?></p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4">
      <p class="text-gray-400 text-xs mb-1">ผู้ดูแลระบบ</p>
      <p style="color:#E8F1F5;" class="text-2xl font-bold"><?= $adminCount ?></p>
    </div>
  </div>

  <!-- Add User Form -->
  <div class="bg-white rounded-xl border border-gray-200 p-6 mb-5">
    <h2 style="color:#005691;" class="font-semibold mb-4 text-sm uppercase tracking-wide">เพิ่มผู้ใช้งานใหม่</h2>
    <form method="post" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">ชื่อผู้ใช้</label>
        <input type="text" name="username" placeholder="Username" required
               class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:border-[#E8F1F5] focus:ring-2 focus:ring-[#E8F1F5]/20 outline-none text-sm">
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">รหัสผ่าน</label>
        <input type="password" name="password" placeholder="Password" required
               class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:border-[#E8F1F5] focus:ring-2 focus:ring-[#E8F1F5]/20 outline-none text-sm">
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">สิทธิ์</label>
        <select name="role" required
                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:border-[#E8F1F5] focus:ring-2 focus:ring-[#E8F1F5]/20 outline-none text-sm">
          <option value="user">ผู้ใช้งาน</option>
          <option value="admin">ผู้ดูแลระบบ</option>
        </select>
      </div>
      <div class="flex items-end">
        <button type="submit" name="add_user"
                style="background:#004A7C;"
                class="w-full px-5 py-2.5 text-white text-sm font-medium rounded-lg hover:opacity-90 transition-opacity">
          + เพิ่มผู้ใช้
        </button>
      </div>
    </form>
  </div>

  <!-- Search / Filter -->
  <div class="bg-white rounded-xl border border-gray-200 p-4 mb-5">
    <form method="get" class="flex flex-col gap-3">
      <div class="flex flex-col sm:flex-row gap-3">
        <div class="flex-1">
          <input type="text" name="search"
                 value="<?= htmlspecialchars($search) ?>"
                 placeholder="🔍 ค้นหาด้วยชื่อผู้ใช้..."
                 class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 outline-none text-sm">
        </div>
        <select name="role"
                class="px-3 py-2.5 rounded-lg border border-gray-300 focus:border-blue-400 outline-none text-sm min-w-[140px]">
          <option value="">ทุกสิทธิ์</option>
          <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>ผู้ดูแลระบบ</option>
          <option value="user"  <?= $role_filter === 'user'  ? 'selected' : '' ?>>ผู้ใช้งาน</option>
        </select>
        <select name="ustatus"
                class="px-3 py-2.5 rounded-lg border border-gray-300 focus:border-blue-400 outline-none text-sm min-w-[130px]">
          <option value="">ทุกสถานะ</option>
          <option value="active"   <?= $status_filter === 'active'   ? 'selected' : '' ?>>ใช้งานอยู่</option>
          <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>ปิดใช้งาน</option>
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
        <?php if ($search !== '' || $role_filter !== '' || $status_filter !== ''): ?>
        <a href="users.php"
           class="px-5 py-2 text-gray-600 text-sm font-medium rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors">
          ล้างตัวกรอง
        </a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Users List -->
  <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <div style="background:#005691;" class="px-5 py-3 flex justify-between items-center">
      <h2 class="text-white font-medium text-sm">รายชื่อผู้ใช้งาน</h2>
      <span class="text-blue-200 text-xs">
        พบ <?= number_format($totalRecords) ?> รายการ<?= ($search !== '' || $role_filter !== '' || $status_filter !== '') ? ' (กรองแล้ว)' : '' ?>
      </span>
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
            <span class="text-xs px-2 py-1 rounded-full <?= $u['role']==='admin' ? 'bg-[#FAFAFA] text-[#004A7C]' : 'bg-gray-100 text-gray-500' ?>">
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
             style="border-color:#E8F1F5; color:#004A7C;"
             class="flex-1 py-1.5 border text-xs rounded text-center hover:bg-[#FAFAFA] transition-colors">
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
                <div style="background:#005691;" class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-medium">
                  <?= strtoupper(substr($u['username'], 0, 1)) ?>
                </div>
                <span class="font-medium text-gray-800"><?= htmlspecialchars($u['username']) ?></span>
              </div>
            </td>
            <td class="px-5 py-3 text-center">
              <span class="text-xs px-2.5 py-1 rounded-full <?= $u['role']==='admin' ? 'bg-[#FAFAFA] text-[#004A7C] font-medium' : 'bg-gray-100 text-gray-500' ?>">
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
                   style="border-color:#E8F1F5; color:#004A7C;"
                   class="px-3 py-1.5 border text-xs rounded hover:bg-[#FAFAFA] transition-colors">
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

    <?php if ($totalRecords === 0 && $search === '' && $role_filter === '' && $status_filter === ''): ?>
    <div class="p-10 text-center text-gray-400">ยังไม่มีผู้ใช้งาน</div>
    <?php elseif (count($users) === 0): ?>
    <div class="p-10 text-center text-gray-400">ไม่พบผู้ใช้งานที่ตรงกับการค้นหา</div>
    <?php endif; ?>
    <!-- Pagination -->
    <div class="px-5 pb-4">
      <?php include __DIR__ . '/../includes/pagination.php'; ?>
    </div>
  </div>

  <!-- Security note -->
  <div class="mt-5 bg-white rounded-xl border border-gray-200 p-5">
    <h3 style="color:#005691;" class="font-medium mb-2 text-sm">ข้อควรระวังด้านความปลอดภัย</h3>
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
