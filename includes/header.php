<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600&display=swap" rel="stylesheet">

<style>
  * { font-family: 'Prompt', sans-serif !important; }
</style>

<nav style="background:#0C2C55;" class="sticky top-0 z-50 shadow-md">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex justify-between items-center h-14">

      <!-- Logo -->
      <a href="/" class="flex items-center gap-2">
        <img src="/logo/BPL.png" alt="BPL" class="w-8 h-8 object-contain rounded">
        <span class="text-white font-semibold text-base">BARGAIN SPORT</span>
      </a>

      <!-- Desktop Menu -->
      <div class="hidden md:flex items-center gap-1">
        <a href="/timetable.php" class="px-3 py-2 text-sm text-blue-100 hover:text-white hover:bg-white/10 rounded transition-colors">ตารางคอร์ต</a>
        <a href="/bookings/" class="px-3 py-2 text-sm text-blue-100 hover:text-white hover:bg-white/10 rounded transition-colors">การจอง</a>
        <a href="/admin/courts.php" class="px-3 py-2 text-sm text-blue-100 hover:text-white hover:bg-white/10 rounded transition-colors">คอร์ต</a>

        <?php if (!empty($_SESSION['user']) && $_SESSION['user']['role'] === 'admin'): ?>
        <a href="/admin/users.php" class="px-3 py-2 text-sm text-blue-100 hover:text-white hover:bg-white/10 rounded transition-colors">ผู้ใช้งาน</a>
        <?php endif; ?>

        <div class="w-px h-5 bg-white/20 mx-2"></div>

        <?php if (!empty($_SESSION['user'])): ?>
        <span class="text-sm text-blue-200 px-2">
          <?php echo htmlspecialchars($_SESSION['user']['name'] ?? $_SESSION['user']['username'] ?? 'ผู้ใช้'); ?>
        </span>
        <?php endif; ?>

        <a href="/auth/logout.php"
           style="background:#296374;"
           class="px-3 py-1.5 text-sm text-white rounded hover:opacity-90 transition-opacity">
          ออกจากระบบ
        </a>
      </div>

      <!-- Mobile Button -->
      <button id="mobile-menu-btn" type="button" class="md:hidden p-2 text-white">
        <svg id="icon-bars" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
        <svg id="icon-close" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>
  </div>

  <!-- Mobile Menu -->
  <div id="mobile-menu" class="hidden md:hidden border-t border-white/10">
    <div class="px-4 py-3 space-y-1">
      <?php if (!empty($_SESSION['user'])): ?>
      <div class="py-2 px-3 text-sm text-blue-200 border-b border-white/10 mb-2">
        <?php echo htmlspecialchars($_SESSION['user']['name'] ?? $_SESSION['user']['username'] ?? 'ผู้ใช้'); ?>
        <span class="text-xs ml-1">(<?= $_SESSION['user']['role'] === 'admin' ? 'Admin' : 'User' ?>)</span>
      </div>
      <?php endif; ?>
      <a href="/timetable.php" class="block px-3 py-2 text-sm text-blue-100 hover:text-white hover:bg-white/10 rounded">ตารางคอร์ต</a>
      <a href="/bookings/" class="block px-3 py-2 text-sm text-blue-100 hover:text-white hover:bg-white/10 rounded">การจอง</a>
      <a href="/admin/courts.php" class="block px-3 py-2 text-sm text-blue-100 hover:text-white hover:bg-white/10 rounded">คอร์ต</a>
      <?php if (!empty($_SESSION['user']) && $_SESSION['user']['role'] === 'admin'): ?>
      <a href="/admin/users.php" class="block px-3 py-2 text-sm text-blue-100 hover:text-white hover:bg-white/10 rounded">ผู้ใช้งาน</a>
      <?php endif; ?>
      <a href="/auth/logout.php" class="block px-3 py-2 text-sm text-white bg-white/10 rounded mt-2">ออกจากระบบ</a>
    </div>
  </div>
</nav>

<script>
  document.getElementById('mobile-menu-btn').addEventListener('click', function() {
    const menu = document.getElementById('mobile-menu');
    const bars = document.getElementById('icon-bars');
    const close = document.getElementById('icon-close');
    menu.classList.toggle('hidden');
    bars.classList.toggle('hidden');
    close.classList.toggle('hidden');
  });
</script>
