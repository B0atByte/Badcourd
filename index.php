<?php
require_once __DIR__.'/auth/guard.php';
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>BARGAIN SPORT - ระบบจองคอร์ตแบดมินตัน</title>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#667eea',
                        secondary: '#764ba2',
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    <?php include __DIR__.'/includes/header.php'; ?>
    
    <div class="container mx-auto px-4 py-8 max-w-7xl">
        <!-- Page Header -->
        <div class="bg-white rounded-2xl shadow-lg p-8 mb-8 border border-gray-100">
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div>
                    <h1 class="text-4xl font-bold text-gray-800 flex items-center gap-3 mb-2">
                        <i class="fas fa-table-tennis text-primary"></i>
                        <span class="bg-gradient-to-r from-primary to-secondary bg-clip-text text-transparent">
                            BARGAIN SPORT
                        </span>
                    </h1>
                    <p class="text-gray-600 text-lg">ระบบจองคอร์ตแบดมินตัน</p>
                </div>
                <div class="flex flex-col items-end gap-2">
                    <div class="text-sm text-gray-500">ยินดีต้อนรับ</div>
                    <div class="font-semibold text-gray-800 text-lg">
                        <?php echo htmlspecialchars($_SESSION['user']['name'] ?? 'ผู้ใช้'); ?>
                    </div>
                    <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-sm font-medium
                        <?php echo $_SESSION['user']['role'] === 'admin' 
                            ? 'bg-gradient-to-r from-purple-500 to-pink-500 text-white' 
                            : 'bg-gradient-to-r from-blue-500 to-cyan-500 text-white'; ?>">
                        <i class="fas fa-<?php echo $_SESSION['user']['role'] === 'admin' ? 'crown' : 'user-circle'; ?>"></i>
                        <?php echo $_SESSION['user']['role'] === 'admin' ? 'ผู้ดูแลระบบ' : 'สมาชิก'; ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- User Menu Section -->
        <div class="mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center gap-2">
                <i class="fas fa-th-large text-primary"></i>
                เมนูหลัก
            </h2>
            <div class="grid md:grid-cols-2 gap-6">
                <!-- Timetable Card -->
                <a href="/BARGAIN SPORT/timetable.php" 
                   class="group block bg-gradient-to-br from-blue-500 to-purple-600 rounded-2xl shadow-lg hover:shadow-2xl 
                          transform hover:-translate-y-2 transition-all duration-300 overflow-hidden">
                    <div class="p-8 text-white relative">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-white opacity-10 rounded-full -mr-16 -mt-16"></div>
                        <div class="relative z-10">
                            <div class="w-16 h-16 bg-white bg-opacity-20 rounded-2xl flex items-center justify-center mb-4 
                                      group-hover:scale-110 transition-transform duration-300">
                                <i class="fas fa-calendar-alt text-3xl"></i>
                            </div>
                            <h3 class="text-2xl font-bold mb-2">ตารางคอร์ต 24 ชม.</h3>
                            <p class="text-blue-100">ดูตารางการใช้งานคอร์ทท้งหมด</p>
                        </div>
                        <div class="mt-6 flex items-center text-sm font-semibold">
                            <span>เข้าชม</span>
                            <i class="fas fa-arrow-right ml-2 group-hover:translate-x-2 transition-transform duration-300"></i>
                        </div>
                    </div>
                </a>
                
                <!-- Booking Card -->
                <a href="/BARGAIN SPORT/bookings/create.php" 
                   class="group block bg-gradient-to-br from-green-400 to-cyan-500 rounded-2xl shadow-lg hover:shadow-2xl 
                          transform hover:-translate-y-2 transition-all duration-300 overflow-hidden">
                    <div class="p-8 text-white relative">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-white opacity-10 rounded-full -mr-16 -mt-16"></div>
                        <div class="relative z-10">
                            <div class="w-16 h-16 bg-white bg-opacity-20 rounded-2xl flex items-center justify-center mb-4 
                                      group-hover:scale-110 transition-transform duration-300">
                                <i class="fas fa-plus-circle text-3xl"></i>
                            </div>
                            <h3 class="text-2xl font-bold mb-2">จองคอร์ต</h3>
                            <p class="text-green-100">จองคอร์ตแบดมินตันของคุณ</p>
                        </div>
                        <div class="mt-6 flex items-center text-sm font-semibold">
                            <span>จองเลย</span>
                            <i class="fas fa-arrow-right ml-2 group-hover:translate-x-2 transition-transform duration-300"></i>
                        </div>
                    </div>
                </a>
            </div>
        </div>
        
        <!-- Admin Menu Section -->
        <?php if($_SESSION['user']['role'] === 'admin'): ?>
        <div class="pt-8 border-t-2 border-gray-200">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center gap-2">
                <i class="fas fa-cog text-purple-600"></i>
                เมนูผู้ดูแลระบบ
            </h2>
            <div class="grid md:grid-cols-3 gap-6">
                <!-- Courts Management -->
                <a href="/BARGAIN SPORT/admin/courts.php" 
                   class="group block bg-gradient-to-br from-slate-700 to-slate-900 rounded-2xl shadow-lg hover:shadow-2xl 
                          transform hover:-translate-y-2 transition-all duration-300 overflow-hidden">
                    <div class="p-6 text-white relative">
                        <div class="absolute bottom-0 right-0 w-24 h-24 bg-white opacity-5 rounded-full -mr-12 -mb-12"></div>
                        <div class="relative z-10">
                            <div class="w-14 h-14 bg-white bg-opacity-20 rounded-xl flex items-center justify-center mb-4 
                                      group-hover:rotate-12 transition-transform duration-300">
                                <i class="fas fa-warehouse text-2xl"></i>
                            </div>
                            <h3 class="text-xl font-bold mb-2">จัดการคอร์ต</h3>
                            <p class="text-slate-300 text-sm">เพิ่ม แก้ไข ลบคอร์ต</p>
                        </div>
                    </div>
                </a>
                
                <!-- Pricing -->
                <a href="/BARGAIN SPORT/admin/pricing.php" 
                   class="group block bg-gradient-to-br from-pink-500 to-rose-600 rounded-2xl shadow-lg hover:shadow-2xl 
                          transform hover:-translate-y-2 transition-all duration-300 overflow-hidden">
                    <div class="p-6 text-white relative">
                        <div class="absolute bottom-0 right-0 w-24 h-24 bg-white opacity-5 rounded-full -mr-12 -mb-12"></div>
                        <div class="relative z-10">
                            <div class="w-14 h-14 bg-white bg-opacity-20 rounded-xl flex items-center justify-center mb-4 
                                      group-hover:rotate-12 transition-transform duration-300">
                                <i class="fas fa-tag text-2xl"></i>
                            </div>
                            <h3 class="text-xl font-bold mb-2">ตั้งราคา</h3>
                            <p class="text-pink-100 text-sm">กำหนดราคาค่าเช่าคอร์ต</p>
                        </div>
                    </div>
                </a>
                
                <!-- Reports -->
                <a href="/BARGAIN SPORT/reports/export_excel.php" 
                   class="group block bg-gradient-to-br from-amber-400 to-orange-500 rounded-2xl shadow-lg hover:shadow-2xl 
                          transform hover:-translate-y-2 transition-all duration-300 overflow-hidden">
                    <div class="p-6 text-white relative">
                        <div class="absolute bottom-0 right-0 w-24 h-24 bg-white opacity-5 rounded-full -mr-12 -mb-12"></div>
                        <div class="relative z-10">
                            <div class="w-14 h-14 bg-white bg-opacity-20 rounded-xl flex items-center justify-center mb-4 
                                      group-hover:rotate-12 transition-transform duration-300">
                                <i class="fas fa-file-excel text-2xl"></i>
                            </div>
                            <h3 class="text-xl font-bold mb-2">ออกรายงาน Excel</h3>
                            <p class="text-amber-100 text-sm">ดาวน์โหลดรายงานการจอง</p>
                        </div>
                    </div>
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Footer Space -->
        <div class="mt-12"></div>
    </div>
    
    <?php include __DIR__.'/includes/footer.php'; ?>
</body>
</html>