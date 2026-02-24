<?php
// ✅ ตรวจสอบก่อนเรียก session_start() เพื่อไม่ให้ขึ้น Notice ซ้ำ
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!-- ✅ ฟอนต์ไทย + ไอคอน -->
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://kit.fontawesome.com/a2e0f1f0b3.js" crossorigin="anonymous"></script>

<style>
  body, .navbar, .nav-link, .font-medium, .font-bold, span, a, button {
    font-family: 'Prompt', sans-serif !important;
  }
</style>

<!-- Navbar -->
<nav class="bg-white shadow-lg border-b border-gray-200 sticky top-0 z-50">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex justify-between items-center h-16">

      <!-- ✅ Logo/Brand -->
      <div class="flex items-center">
        <a href="/" class="flex items-center gap-2 group">
          <!-- เปลี่ยนจาก icon เป็นโลโก้ -->
          <div class="w-10 h-10 flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
            <img src="/logo/BPL.png" alt="BPL Logo" class="w-10 h-10 object-contain rounded-md shadow-sm">
          </div>

          <div class="flex flex-col leading-tight">
            <span class="text-2xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">
              BARGAIN_SPORT
            </span>
            <span class="text-sm text-gray-500 -mt-1">ระบบจองคอร์ตแบดมินตัน</span>
          </div>
        </a>
      </div>

      <!-- Desktop Menu -->
      <div class="hidden md:flex items-center space-x-1">
        <a href="/timetable.php" 
           class="px-4 py-2 rounded-lg text-gray-700 hover:bg-gradient-to-r hover:from-blue-50 hover:to-purple-50 
                  hover:text-blue-600 transition-all duration-300 font-medium flex items-center gap-2">
          <i class="fas fa-calendar-alt text-sm"></i>
          <span>ตารางคอร์ต</span>
        </a>
        
        <a href="/bookings/" 
           class="px-4 py-2 rounded-lg text-gray-700 hover:bg-gradient-to-r hover:from-green-50 hover:to-cyan-50 
                  hover:text-green-600 transition-all duration-300 font-medium flex items-center gap-2">
          <i class="fas fa-clipboard-list text-sm"></i>
          <span>การจอง</span>
        </a>
        
        <a href="/admin/courts.php" 
           class="px-4 py-2 rounded-lg text-gray-700 hover:bg-gradient-to-r hover:from-slate-50 hover:to-gray-50 
                  hover:text-slate-600 transition-all duration-300 font-medium flex items-center gap-2">
          <i class="fas fa-warehouse text-sm"></i>
          <span>คอร์ต</span>
        </a>
        

        <?php if (!empty($_SESSION['user']) && $_SESSION['user']['role'] === 'admin'): ?>
        <a href="/admin/users.php" 
           class="px-4 py-2 rounded-lg text-gray-700 hover:bg-gradient-to-r hover:from-purple-50 hover:to-indigo-50 
                  hover:text-purple-600 transition-all duration-300 font-medium flex items-center gap-2">
          <i class="fas fa-users text-sm"></i>
          <span>ผู้ใช้งาน</span>
        </a>
        <?php endif; ?>

        <!-- Divider -->
        <div class="w-px h-6 bg-gray-300 mx-2"></div>

        <!-- User Info & Logout -->
        <div class="flex items-center gap-3 ml-2">
          <?php if (!empty($_SESSION['user'])): ?>
          <div class="flex items-center gap-2 px-3 py-1.5 bg-gradient-to-r from-blue-50 to-purple-50 rounded-lg">
            <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center">
              <i class="fas fa-user text-white text-xs"></i>
            </div>
            <span class="text-sm font-medium text-gray-700">
              <?php echo htmlspecialchars($_SESSION['user']['name'] ?? 'ผู้ใช้'); ?>
            </span>
          </div>
          <?php endif; ?>

          <a href="/auth/logout.php" 
             class="px-4 py-2 bg-gradient-to-r from-red-500 to-pink-600 text-white rounded-lg font-medium 
                    hover:from-red-600 hover:to-pink-700 hover:shadow-lg transform hover:scale-105 
                    transition-all duration-300 flex items-center gap-2">
            <i class="fas fa-sign-out-alt text-sm"></i>
            <span>ออกจากระบบ</span>
          </a>
        </div>
      </div>

      <!-- Mobile Menu Button -->
      <div class="md:hidden">
        <button id="mobile-menu-btn" type="button" 
                class="p-2 rounded-lg text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500">
          <i class="fas fa-bars text-xl"></i>
        </button>
      </div>
    </div>
  </div>

  <!-- Mobile Menu -->
  <div id="mobile-menu" class="hidden md:hidden border-t border-gray-200 bg-gray-50">
    <div class="px-4 py-3 space-y-2">
      <?php if (!empty($_SESSION['user'])): ?>
      <div class="flex items-center gap-3 p-3 bg-white rounded-lg mb-3 shadow-sm">
        <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center">
          <i class="fas fa-user text-white"></i>
        </div>
        <div>
          <div class="text-sm font-medium text-gray-700">
            <?php echo htmlspecialchars($_SESSION['user']['name'] ?? 'ผู้ใช้'); ?>
          </div>
          <div class="text-xs text-gray-500">
            <?php echo $_SESSION['user']['role'] === 'admin' ? 'ผู้ดูแลระบบ' : 'สมาชิก'; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <a href="/timetable.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-white hover:shadow-sm transition-all">
        <i class="fas fa-calendar-alt text-blue-600 w-5"></i><span class="font-medium">ตารางคอร์ต</span>
      </a>
      <a href="/bookings/" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-white hover:shadow-sm transition-all">
        <i class="fas fa-clipboard-list text-green-600 w-5"></i><span class="font-medium">การจอง</span>
      </a>
      <a href="/admin/courts.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-white hover:shadow-sm transition-all">
        <i class="fas fa-warehouse text-slate-600 w-5"></i><span class="font-medium">คอร์ต</span>
      </a>
      <a href="/admin/pricing.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-white hover:shadow-sm transition-all">
        <i class="fas fa-tag text-pink-600 w-5"></i><span class="font-medium">ราคา</span>
      </a>

      <?php if (!empty($_SESSION['user']) && $_SESSION['user']['role'] === 'admin'): ?>
      <a href="/admin/users.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-white hover:shadow-sm transition-all">
        <i class="fas fa-users text-purple-600 w-5"></i><span class="font-medium">ผู้ใช้งาน</span>
      </a>
      <?php endif; ?>

      <div class="pt-2 mt-2 border-t border-gray-200">
        <a href="/auth/logout.php" class="flex items-center gap-3 px-4 py-3 rounded-lg bg-gradient-to-r from-red-500 to-pink-600 
                  text-white font-medium hover:from-red-600 hover:to-pink-700 transition-all">
          <i class="fas fa-sign-out-alt w-5"></i><span>ออกจากระบบ</span>
        </a>
      </div>
    </div>
  </div>
</nav>

<script>
  // ✅ Mobile Menu Toggle
  document.getElementById('mobile-menu-btn').addEventListener('click', function() {
    const menu = document.getElementById('mobile-menu');
    const icon = this.querySelector('i');
    
    menu.classList.toggle('hidden');
    
    if (menu.classList.contains('hidden')) {
      icon.classList.remove('fa-times');
      icon.classList.add('fa-bars');
    } else {
      icon.classList.remove('fa-bars');
      icon.classList.add('fa-times');
    }
  });
</script>
