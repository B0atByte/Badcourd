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
        body {
            font-family: 'Prompt', sans-serif;
        }
    </style>
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
    
    <div class="w-full max-w-md">
        
        <!-- Logo & Title -->
        <div class="text-center mb-8">
            <div class="flex justify-center mb-4">
                <!-- ✅ เปลี่ยนจาก icon เป็นโลโก้ -->
                <img src="/logo/BPL.png" alt="BPL Logo" 
                     class="w-20 h-20 object-contain rounded-2xl shadow-md border border-gray-200">
            </div>
            <h1 class="text-3xl font-bold text-gray-800">BARGAIN SPORT SYSTEM</h1>
            <p class="text-gray-500 mt-1">ระบบจองคอร์ตแบดมินตัน</p>
        </div>

        <!-- Login Card -->
        <div class="bg-white rounded-2xl shadow-lg p-8 border border-gray-100">
            
            <!-- Header -->
            <div class="mb-6">
                <h2 class="text-xl font-bold text-gray-800">เข้าสู่ระบบ</h2>
                <p class="text-gray-500 text-sm mt-1">กรุณากรอกข้อมูลเพื่อเข้าสู่ระบบ</p>
            </div>

            <!-- Error Alert -->
            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 flex items-center gap-3">
                    <i class="bi bi-exclamation-circle text-lg"></i>
                    <span class="text-sm"><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="post" class="space-y-5">
                
                <!-- Username Field -->
                <div>
                    <label class="block text-gray-700 font-medium mb-2 text-sm">ชื่อผู้ใช้</label>
                    <input type="text" name="username" required placeholder="กรอกชื่อผู้ใช้"
                           class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all outline-none">
                </div>

                <!-- Password Field -->
                <div>
                    <label class="block text-gray-700 font-medium mb-2 text-sm">รหัสผ่าน</label>
                    <input type="password" name="password" required placeholder="กรอกรหัสผ่าน"
                           class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all outline-none">
                </div>

                <!-- Remember Me -->
                <div class="flex items-center">
                    <input type="checkbox" id="remember" class="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                    <label for="remember" class="ml-2 text-sm text-gray-600 cursor-pointer">จดจำการเข้าสู่ระบบ</label>
                </div>

                <!-- Login Button -->
                <button type="submit" 
                        class="w-full bg-purple-600 hover:bg-purple-700 text-white py-3 rounded-lg font-medium transition-colors duration-200 flex items-center justify-center gap-2">
                    <i class="bi bi-box-arrow-in-right"></i>
                    เข้าสู่ระบบ
                </button>
            </form>

            <!-- Divider -->
            <div class="relative my-6">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-gray-200"></div>
                </div>
            </div>
        </div>

        <!-- Copyright -->
        <div class="text-center mt-6 text-gray-500 text-sm">
            <p>© <?= date('Y') ?> Boat Patthanapong. All rights reserved.</p>
        </div>

    </div>

</body>
</html>
