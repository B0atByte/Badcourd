<?php
require_once __DIR__.'/auth/guard.php';
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <title>BARGAIN SPORT - หน้าหลัก</title>
</head>
<body style="background:#EDEDCE;" class="min-h-screen">
    <?php include __DIR__.'/includes/header.php'; ?>

    <div class="max-w-4xl mx-auto px-4 py-10">

        <!-- Welcome -->
        <div class="mb-8">
            <h1 style="color:#0C2C55;" class="text-2xl font-bold mb-1">ยินดีต้อนรับ</h1>
            <p class="text-gray-500 text-sm">
                <?php echo htmlspecialchars($_SESSION['user']['name'] ?? $_SESSION['user']['username'] ?? 'ผู้ใช้'); ?>
                &nbsp;·&nbsp;
                <?= $_SESSION['user']['role'] === 'admin' ? 'ผู้ดูแลระบบ' : 'สมาชิก' ?>
            </p>
        </div>

        <!-- Main Menu -->
        <div class="mb-6">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">เมนูหลัก</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                <a href="/timetable.php"
                   class="bg-white border border-gray-200 rounded-xl p-6 hover:shadow-md transition-shadow group">
                    <div style="color:#296374;" class="font-semibold text-lg mb-1 group-hover:opacity-80">ตารางคอร์ต</div>
                    <p class="text-gray-500 text-sm">ดูตารางการใช้งานคอร์ตทั้งหมด 24 ชั่วโมง</p>
                    <div style="color:#629FAD;" class="text-sm mt-3">ดูตาราง →</div>
                </a>

                <a href="/bookings/create.php"
                   style="background:#0C2C55;"
                   class="rounded-xl p-6 hover:opacity-95 transition-opacity group">
                    <div class="text-white font-semibold text-lg mb-1">จองคอร์ต</div>
                    <p style="color:#629FAD;" class="text-sm">จองคอร์ตแบดมินตันสำหรับลูกค้า</p>
                    <div class="text-blue-200 text-sm mt-3">จองเลย →</div>
                </a>

            </div>
        </div>

        <!-- Admin Menu -->
        <?php if($_SESSION['user']['role'] === 'admin'): ?>
        <div class="border-t border-gray-300 pt-6">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">เมนูผู้ดูแลระบบ</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

                <a href="/admin/courts.php"
                   class="bg-white border border-gray-200 rounded-xl p-5 hover:shadow-md transition-shadow">
                    <div style="color:#0C2C55;" class="font-semibold mb-1">จัดการคอร์ต</div>
                    <p class="text-gray-500 text-sm">เพิ่ม แก้ไข ลบคอร์ต</p>
                </a>

                <a href="/admin/users.php"
                   class="bg-white border border-gray-200 rounded-xl p-5 hover:shadow-md transition-shadow">
                    <div style="color:#0C2C55;" class="font-semibold mb-1">จัดการผู้ใช้</div>
                    <p class="text-gray-500 text-sm">บัญชีผู้ใช้และสิทธิ์การเข้าถึง</p>
                </a>

                <a href="/reports/export_excel.php"
                   class="bg-white border border-gray-200 rounded-xl p-5 hover:shadow-md transition-shadow">
                    <div style="color:#0C2C55;" class="font-semibold mb-1">ออกรายงาน Excel</div>
                    <p class="text-gray-500 text-sm">ดาวน์โหลดรายงานการจอง</p>
                </a>

            </div>
        </div>
        <?php endif; ?>

    </div>

    <?php include __DIR__.'/includes/footer.php'; ?>
</body>
</html>
