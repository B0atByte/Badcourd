<?php
require_once __DIR__ . '/../auth/guard.php';
require_role(['admin']);
require_once __DIR__ . '/../config/db.php';

// หน้าที่ให้กำหนดสิทธิ์ได้
$ALL_PAGES = [
  // ── เมนูหลัก ──────────────────────────────────────────────
  'timetable'          => 'ตารางคอร์ต',
  'bookings'           => 'การจอง (ดู/สร้าง/เลื่อน/ยกเลิก)',
  'members'            => 'ค้นหา/จัดการสมาชิก',
  // ── จัดการ ────────────────────────────────────────────────
  'courts'             => 'จัดการคอร์ต',
  'yoga_classes'       => 'คลาสโยคะ',
  'yoga_packages'      => 'แพ็กเกจโยคะ',
  'badminton_packages' => 'แพ็กเกจแบดมินตัน',
  'promotions'         => 'โปรโมชั่น',
  'pricing'            => 'ตั้งราคา',
  'reports'            => 'รายงาน Excel',
  // ── ระบบ ──────────────────────────────────────────────────
  'users'              => 'จัดการผู้ใช้งาน',
];
// หมายเหตุ: 'settings' ไม่อยู่ในนี้ เพราะเป็นสิทธิ์ admin เท่านั้น

$success = $error = '';

// ── Auto-migrate: เพิ่ม column permissions ถ้ายังไม่มี ────
try {
  $colCheck = $pdo->query("SHOW COLUMNS FROM users LIKE 'permissions'");
  if ($colCheck->rowCount() === 0) {
    $pdo->exec("ALTER TABLE users ADD COLUMN permissions JSON NULL DEFAULT NULL AFTER role");
  }
} catch (PDOException $e) {
  // ignore ถ้า ALTER ไม่ได้ (เช่น permission ไม่พอ)
}

// ── เพิ่มผู้ใช้ใหม่ ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_user') {
  $username = trim($_POST['username'] ?? '');
  $password = trim($_POST['password'] ?? '');
  $role = in_array($_POST['role'] ?? '', ['admin', 'user']) ? $_POST['role'] : 'user';
  $perms = $role === 'admin'
    ? null  // admin เข้าได้ทุกหน้า ไม่ต้องเก็บ
    : array_values(array_intersect(array_keys($ALL_PAGES), (array) ($_POST['perms'] ?? [])));

  if (empty($username) || empty($password)) {
    $error = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
  } elseif (mb_strlen($password) < 6) {
    $error = 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร';
  } else {
    $chk = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $chk->execute([$username]);
    if ($chk->fetch()) {
      $error = "ชื่อผู้ใช้ \"$username\" มีอยู่แล้ว";
    } else {
      $hash = password_hash($password, PASSWORD_DEFAULT);
      try {
        $pdo->prepare("INSERT INTO users (username, password_hash, role, permissions, active, created_at) VALUES (?,?,?,?,1,NOW())")
          ->execute([$username, $hash, $role, $role === 'admin' ? null : json_encode($perms)]);
      } catch (PDOException $e) {
        // fallback: insert without permissions column
        $pdo->prepare("INSERT INTO users (username, password_hash, role, active, created_at) VALUES (?,?,?,1,NOW())")
          ->execute([$username, $hash, $role]);
      }
      $success = "สร้างผู้ใช้ \"$username\" สำเร็จ";
    }
  }
}

// ── แก้ไข permissions ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_perms') {
  $uid = (int) $_POST['uid'];
  $role = in_array($_POST['role'] ?? '', ['admin', 'user']) ? $_POST['role'] : 'user';
  $perms = $role === 'admin'
    ? null
    : array_values(array_intersect(array_keys($ALL_PAGES), (array) ($_POST['perms'] ?? [])));
  try {
    $pdo->prepare("UPDATE users SET role=?, permissions=? WHERE id=?")
      ->execute([$role, $role === 'admin' ? null : json_encode($perms), $uid]);
  } catch (PDOException $e) {
    // fallback: update role only
    $pdo->prepare("UPDATE users SET role=? WHERE id=?")->execute([$role, $uid]);
  }
  $success = 'อัปเดตสิทธิ์เรียบร้อย';
}

// ── เปิด/ปิด ─────────────────────────────────────────────
if (isset($_GET['toggle'])) {
  $id = (int) $_GET['toggle'];
  $pdo->prepare("UPDATE users SET active = 1 - active WHERE id = ?")->execute([$id]);
  header('Location: users.php?msg=toggled');
  exit;
}

// ── ลบ ───────────────────────────────────────────────────
if (isset($_GET['delete'])) {
  $id = (int) $_GET['delete'];
  if ($id === (int) ($_SESSION['user']['id'] ?? 0)) {
    header('Location: users.php?err=selfdelete');
    exit;
  }
  $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
  header('Location: users.php?msg=deleted');
  exit;
}

// query message
if (isset($_GET['msg'])) {
  $success = match ($_GET['msg']) {
    'toggled' => 'อัปเดตสถานะเรียบร้อย',
    'deleted' => 'ลบผู้ใช้เรียบร้อย',
    default => ''
  };
}
if (isset($_GET['err']) && $_GET['err'] === 'selfdelete') {
  $error = 'ไม่สามารถลบบัญชีของตัวเองได้';
}

// ── ดึง users ─────────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$where = ['1=1'];
$params = [];
if ($search !== '') {
  $where[] = 'username LIKE ?';
  $params[] = "%$search%";
}
$users = $pdo->prepare("SELECT * FROM users WHERE " . implode(' AND ', $where) . " ORDER BY id ASC");
$users->execute($params);
$users = $users->fetchAll();

$stats = $pdo->query("SELECT COUNT(*) total, SUM(active) act, SUM(role='admin') adm FROM users")->fetch();
?>
<!doctype html>
<html lang="th">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <title>จัดการผู้ใช้งาน - BARGAIN SPORT</title>
  <style>
    * {
      font-family: 'Prompt', sans-serif !important;
    }

    .perm-tag {
      display: inline-block;
      font-size: 0.7rem;
      padding: 2px 8px;
      border-radius: 99px;
      background: #FFEBEE;
      color: #D32F2F;
      margin: 1px;
    }
  </style>
</head>

<body style="background:#f8fafc;" class="min-h-screen">
  <?php include __DIR__ . '/../includes/header.php'; ?>
  <?php include __DIR__ . '/../includes/swal_flash.php'; ?>

  <div class="max-w-6xl mx-auto px-4 py-8">

    <!-- Page Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold" style="color:#D32F2F;">จัดการผู้ใช้งาน</h1>
        <p class="text-gray-400 text-sm mt-0.5">สร้างและกำหนดสิทธิ์การเข้าถึงสำหรับแต่ละบัญชี</p>
      </div>
      <button onclick="document.getElementById('addModal').classList.remove('hidden')"
        class="px-4 py-2 text-sm text-white font-medium rounded-lg hover:opacity-90 transition-opacity"
        style="background:#D32F2F;">+ เพิ่มผู้ใช้</button>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-3 gap-4 mb-6">
      <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
        <p class="text-xs text-gray-400 mb-1">ผู้ใช้ทั้งหมด</p>
        <p class="text-2xl font-bold" style="color:#D32F2F;"><?= $stats['total'] ?></p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
        <p class="text-xs text-gray-400 mb-1">ใช้งานอยู่</p>
        <p class="text-2xl font-bold text-green-600"><?= $stats['act'] ?></p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
        <p class="text-xs text-gray-400 mb-1">Admin</p>
        <p class="text-2xl font-bold text-gray-700"><?= $stats['adm'] ?></p>
      </div>
    </div>

    <!-- Search -->
    <div class="bg-white rounded-xl border border-gray-200 p-4 mb-4">
      <form method="get" class="flex gap-2">
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="ค้นหาชื่อผู้ใช้..."
          class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:border-blue-400">
        <button type="submit" class="px-5 py-2 text-sm text-white rounded-lg" style="background:#D32F2F;">ค้นหา</button>
        <?php if ($search): ?>
          <a href="users.php"
            class="px-4 py-2 text-sm border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50">ล้าง</a>
        <?php endif; ?>
      </form>
    </div>

    <!-- Users Table -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-200">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500">ชื่อผู้ใช้</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500">Role</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500">สิทธิ์ที่เข้าถึงได้</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500">สถานะ</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500">สร้างเมื่อ</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500">จัดการ</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php foreach ($users as $u):
            $perms = $u['role'] === 'admin' ? array_keys($ALL_PAGES) : (json_decode($u['permissions'] ?? '[]', true) ?: []);
            $isSelf = (int) $u['id'] === (int) ($_SESSION['user']['id'] ?? 0);
            ?>
            <tr class="hover:bg-gray-50 <?= !$u['active'] ? 'opacity-60' : '' ?>">
              <!-- Username -->
              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <div
                    class="w-7 h-7 rounded-full flex items-center justify-center text-white text-xs font-semibold shrink-0"
                    style="background:#D32F2F;"><?= strtoupper(mb_substr($u['username'], 0, 1)) ?></div>
                  <div>
                    <p class="font-medium text-gray-800"><?= htmlspecialchars($u['username']) ?></p>
                    <?php if ($isSelf): ?><span class="text-xs text-blue-500">(คุณ)</span><?php endif; ?>
                  </div>
                </div>
              </td>
              <!-- Role -->
              <td class="px-4 py-3 text-center">
                <?php if ($u['role'] === 'admin'): ?>
                  <span class="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 font-medium">Admin</span>
                <?php else: ?>
                  <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">User</span>
                <?php endif; ?>
              </td>
              <!-- Permissions -->
              <td class="px-4 py-3 max-w-xs">
                <?php if ($u['role'] === 'admin'): ?>
                  <span class="text-xs text-gray-400 italic">เข้าถึงได้ทุกหน้า</span>
                <?php elseif (empty($perms)): ?>
                  <span class="text-xs text-red-400">ไม่มีสิทธิ์ใดเลย</span>
                <?php else: ?>
                  <div class="flex flex-wrap gap-0.5">
                    <?php foreach ($perms as $p): ?>
                      <?php if (isset($ALL_PAGES[$p])): ?>
                        <span class="perm-tag"><?= htmlspecialchars($ALL_PAGES[$p]) ?></span>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </td>
              <!-- Status -->
              <td class="px-4 py-3 text-center">
                <span
                  class="text-xs px-2 py-0.5 rounded-full <?= $u['active'] ? 'bg-green-100 text-green-700' : 'bg-red-50 text-red-500' ?>">
                  <?= $u['active'] ? 'เปิด' : 'ปิด' ?>
                </span>
              </td>
              <!-- Date -->
              <td class="px-4 py-3 text-center text-xs text-gray-400">
                <?= date('d/m/Y', strtotime($u['created_at'])) ?>
              </td>
              <!-- Actions -->
              <td class="px-4 py-3 text-center">
                <div class="flex items-center justify-center gap-1.5">
                  <!-- Edit perms button -->
                  <button onclick="openEdit(<?= htmlspecialchars(json_encode([
                    'id' => $u['id'],
                    'username' => $u['username'],
                    'role' => $u['role'],
                    'perms' => $perms,
                  ], JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>)"
                    class="px-2.5 py-1 text-xs border border-gray-300 rounded text-gray-600 hover:bg-gray-50 transition-colors">
                    สิทธิ์
                  </button>
                  <!-- Toggle -->
                  <a href="?toggle=<?= $u['id'] ?>"
                    class="px-2.5 py-1 text-xs border rounded transition-colors <?= $u['active'] ? 'border-amber-200 text-amber-600 hover:bg-amber-50' : 'border-green-200 text-green-600 hover:bg-green-50' ?>">
                    <?= $u['active'] ? 'ปิด' : 'เปิด' ?>
                  </a>
                  <!-- Delete -->
                  <?php if (!$isSelf): ?>
                    <button type="button"
                      onclick="swalDelete('?delete=<?= $u['id'] ?>', '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>')"
                      class="px-2.5 py-1 text-xs border border-red-200 text-red-500 rounded hover:bg-red-50 transition-colors">
                      ลบ
                    </button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($users)): ?>
            <tr>
              <td colspan="6" class="px-4 py-10 text-center text-gray-400">ไม่พบผู้ใช้งาน</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </div>

  <!-- ===== Modal: เพิ่มผู้ใช้ใหม่ ===== -->
  <div id="addModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl max-w-lg w-full shadow-2xl max-h-[90vh] overflow-y-auto">
      <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
        <h3 class="text-base font-bold text-gray-900">เพิ่มผู้ใช้งานใหม่</h3>
        <button onclick="document.getElementById('addModal').classList.add('hidden')"
          class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
      </div>
      <form method="post" class="px-6 py-5 space-y-4">
        <input type="hidden" name="action" value="add_user">
        <!-- Username -->
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">ชื่อผู้ใช้</label>
          <input type="text" name="username" required placeholder="เช่น staff01"
            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm outline-none focus:border-blue-400">
        </div>
        <!-- Password -->
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">รหัสผ่าน</label>
          <input type="password" name="password" required placeholder="อย่างน้อย 6 ตัวอักษร"
            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm outline-none focus:border-blue-400">
        </div>
        <!-- Role -->
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Role</label>
          <select name="role" id="addRole" onchange="togglePermSection('add')"
            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm outline-none focus:border-blue-400">
            <option value="user">User (กำหนดสิทธิ์เอง)</option>
            <option value="admin">Admin (เข้าได้ทุกหน้า)</option>
          </select>
        </div>
        <!-- Permissions -->
        <div id="addPermSection">
          <label class="block text-xs font-medium text-gray-600 mb-2">สิทธิ์การเข้าถึง</label>
          <div class="border border-gray-200 rounded-lg p-3 space-y-2 bg-gray-50">
            <div class="flex items-center justify-between mb-2 pb-2 border-b border-gray-200">
              <span class="text-xs text-gray-500">เลือกหน้าที่เข้าถึงได้</span>
              <div class="flex gap-2">
                <button type="button" onclick="selectAll('add',true)"
                  class="text-xs text-blue-600 hover:underline">เลือกทั้งหมด</button>
                <button type="button" onclick="selectAll('add',false)"
                  class="text-xs text-gray-400 hover:underline">ล้าง</button>
              </div>
            </div>
            <?php foreach ($ALL_PAGES as $key => $label): ?>
              <label class="flex items-center gap-2 cursor-pointer hover:bg-white rounded px-2 py-1 transition-colors">
                <input type="checkbox" name="perms[]" value="<?= $key ?>" class="add-perm w-4 h-4 accent-blue-600"
                  checked>
                <span class="text-sm text-gray-700"><?= $label ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
        <!-- Submit -->
        <div class="flex gap-3 pt-1">
          <button type="submit" class="flex-1 py-2.5 text-white text-sm font-semibold rounded-lg hover:opacity-90"
            style="background:#D32F2F;">
            สร้างผู้ใช้
          </button>
          <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')"
            class="flex-1 py-2.5 text-gray-600 text-sm rounded-lg border border-gray-300 hover:bg-gray-50">
            ยกเลิก
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- ===== Modal: แก้ไขสิทธิ์ ===== -->
  <div id="editModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl max-w-lg w-full shadow-2xl max-h-[90vh] overflow-y-auto">
      <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
        <h3 class="text-base font-bold text-gray-900">แก้ไขสิทธิ์ — <span id="editUsername"
            class="text-blue-700"></span></h3>
        <button onclick="document.getElementById('editModal').classList.add('hidden')"
          class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
      </div>
      <form method="post" class="px-6 py-5 space-y-4">
        <input type="hidden" name="action" value="edit_perms">
        <input type="hidden" name="uid" id="editUid">
        <!-- Role -->
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Role</label>
          <select name="role" id="editRole" onchange="togglePermSection('edit')"
            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm outline-none focus:border-blue-400">
            <option value="user">User (กำหนดสิทธิ์เอง)</option>
            <option value="admin">Admin (เข้าได้ทุกหน้า)</option>
          </select>
        </div>
        <!-- Permissions -->
        <div id="editPermSection">
          <label class="block text-xs font-medium text-gray-600 mb-2">สิทธิ์การเข้าถึง</label>
          <div class="border border-gray-200 rounded-lg p-3 space-y-2 bg-gray-50">
            <div class="flex items-center justify-between mb-2 pb-2 border-b border-gray-200">
              <span class="text-xs text-gray-500">เลือกหน้าที่เข้าถึงได้</span>
              <div class="flex gap-2">
                <button type="button" onclick="selectAll('edit',true)"
                  class="text-xs text-blue-600 hover:underline">เลือกทั้งหมด</button>
                <button type="button" onclick="selectAll('edit',false)"
                  class="text-xs text-gray-400 hover:underline">ล้าง</button>
              </div>
            </div>
            <?php foreach ($ALL_PAGES as $key => $label): ?>
              <label class="flex items-center gap-2 cursor-pointer hover:bg-white rounded px-2 py-1 transition-colors">
                <input type="checkbox" name="perms[]" value="<?= $key ?>" class="edit-perm w-4 h-4 accent-blue-600">
                <span class="text-sm text-gray-700"><?= $label ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
        <!-- Submit -->
        <div class="flex gap-3 pt-1">
          <button type="submit" class="flex-1 py-2.5 text-white text-sm font-semibold rounded-lg hover:opacity-90"
            style="background:#D32F2F;">
            บันทึก
          </button>
          <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')"
            class="flex-1 py-2.5 text-gray-600 text-sm rounded-lg border border-gray-300 hover:bg-gray-50">
            ยกเลิก
          </button>
        </div>
      </form>
    </div>
  </div>

  <?php include __DIR__ . '/../includes/footer.php'; ?>

  <script>
    function togglePermSection(prefix) {
      const role = document.getElementById(prefix + 'Role').value;
      const section = document.getElementById(prefix + 'PermSection');
      section.style.display = role === 'admin' ? 'none' : 'block';
    }

    function selectAll(prefix, checked) {
      document.querySelectorAll('.' + prefix + '-perm').forEach(cb => cb.checked = checked);
    }

    function openEdit(data) {
      document.getElementById('editUid').value = data.id;
      document.getElementById('editUsername').textContent = data.username;
      document.getElementById('editRole').value = data.role;

      // reset checkboxes
      document.querySelectorAll('.edit-perm').forEach(cb => {
        cb.checked = data.perms.includes(cb.value);
      });

      togglePermSection('edit');
      document.getElementById('editModal').classList.remove('hidden');
    }

    // init hide/show on load
    window.addEventListener('DOMContentLoaded', () => {
      togglePermSection('add');
    });
  </script>
</body>

</html>