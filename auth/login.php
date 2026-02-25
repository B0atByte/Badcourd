<?php
require_once __DIR__.'/../config/db.php';
session_start();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :u AND active = 1');
    $stmt->execute([':u' => $username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user'] = [
            'id' => $user['id'],
            'username' => $user['username'],
            'name' => $user['username'],
            'role' => $user['role'],
        ];
        header('Location:/index.php');
        exit;
    } else {
        $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    }
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600&display=swap" rel="stylesheet">
    <title>เข้าสู่ระบบ - BARGAIN SPORT</title>
    <style>
        * { font-family: 'Prompt', sans-serif; }
    </style>
</head>
<body style="background:#FAFAFA;" class="min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-sm">

        <!-- Logo & Title -->
        <div class="text-center mb-8">
            <img src="/logo/BPL.png" alt="BPL Logo"
                 class="w-16 h-16 object-contain mx-auto mb-4 rounded-xl shadow">
            <h1 style="color:#005691;" class="text-2xl font-bold">BARGAIN SPORT</h1>
            <p class="text-gray-500 text-sm mt-1">ระบบจองคอร์ตแบดมินตัน</p>
        </div>

        <!-- Login Card -->
        <div class="bg-white rounded-xl shadow p-7 border border-gray-200">

            <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-5 text-sm">
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form method="post" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">ชื่อผู้ใช้</label>
                    <input type="text" name="username" required placeholder="กรอกชื่อผู้ใช้"
                           style="--tw-ring-color:#005691;"
                           class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:border-[#005691] focus:ring-2 focus:ring-[#005691]/20 outline-none transition-all text-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">รหัสผ่าน</label>
                    <input type="password" name="password" required placeholder="กรอกรหัสผ่าน"
                           class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:border-[#005691] focus:ring-2 focus:ring-[#005691]/20 outline-none transition-all text-sm">
                </div>

                <button type="submit"
                        style="background:#FF0000;"
                        class="w-full py-2.5 text-white rounded-lg font-medium hover:opacity-90 transition-opacity text-sm mt-2">
                    เข้าสู่ระบบ
                </button>
            </form>
        </div>

        <p class="text-center mt-5 text-gray-400 text-xs">© <?= date('Y') ?> Boat Patthanapong</p>
    </div>

</body>
</html>
