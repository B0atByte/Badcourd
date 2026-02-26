<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
$_isAdmin = !empty($_SESSION['user']) && $_SESSION['user']['role'] === 'admin';
$_username = htmlspecialchars($_SESSION['user']['name'] ?? $_SESSION['user']['username'] ?? 'ผู้ใช้');
?>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600&display=swap" rel="stylesheet">

<style>
  * { font-family: 'Prompt', sans-serif !important; }

  /* ── Smooth page transitions ───────────────────────── */
  @view-transition { navigation: auto; }
  @keyframes _slideIn  { from { opacity:0; translate:0 8px;  } to { opacity:1; translate:0 0;    } }
  @keyframes _slideOut { from { opacity:1; translate:0 0;    } to { opacity:0; translate:0 -6px; } }
  ::view-transition-new(root) { animation: _slideIn  0.2s ease-out; }
  ::view-transition-old(root) { animation: _slideOut 0.15s ease-in; }
  @media (prefers-reduced-motion: reduce) {
    ::view-transition-new(root), ::view-transition-old(root) { animation: none; }
  }

  /* ── Active nav link ───────────────────────────────── */
  nav a.nav-on, nav button.nav-on {
    background: rgba(255,255,255,0.18) !important;
    color: #fff !important;
  }

  /* ── Admin Dropdown ────────────────────────────────── */
  #admin-dropdown {
    display: none;
    position: absolute;
    top: calc(100% + 6px);
    right: 0;
    min-width: 200px;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.13);
    border: 1px solid #e5e7eb;
    overflow: hidden;
    z-index: 200;
    transform-origin: top right;
  }
  #admin-dropdown.open {
    display: block;
    animation: _ddOpen 0.18s ease-out;
  }
  @keyframes _ddOpen {
    from { opacity: 0; transform: scale(0.95) translateY(-4px); }
    to   { opacity: 1; transform: scale(1)    translateY(0);    }
  }
  #admin-dropdown a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 16px;
    font-size: 0.875rem;
    color: #374151;
    text-decoration: none;
    transition: background 0.12s;
  }
  #admin-dropdown a:hover { background: #f3f4f6; }
  #admin-dropdown a.nav-on { background: #EBF4FA !important; color: #005691 !important; font-weight:600; }
  #admin-dropdown .dd-icon { font-size: 1rem; width: 20px; text-align: center; }
  #admin-dropdown .dd-sep {
    height: 1px; background: #f3f4f6; margin: 4px 0;
  }

  /* ── Mobile menu slide ─────────────────────────────── */
  #mobile-menu {
    overflow: hidden;
    max-height: 0;
    transition: max-height 0.25s ease;
  }
  #mobile-menu.open { max-height: 600px; }
</style>

<nav style="background:#005691;" class="sticky top-0 z-50 shadow-md">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex justify-between items-center h-14">

      <!-- Logo -->
      <a href="/" class="flex items-center gap-2 shrink-0">
        <img src="/logo/BPL.png" alt="BPL" class="w-8 h-8 object-contain rounded">
        <span class="text-white font-semibold text-base">BARGAIN SPORT</span>
      </a>

      <!-- Desktop Menu -->
      <div class="hidden md:flex items-center gap-1">

        <!-- Main menus -->
        <a href="/timetable_detail.php"
           class="px-3 py-2 text-sm text-blue-100 hover:text-white hover:bg-white/10 rounded-lg transition-colors">
          ตารางคอร์ต
        </a>
        <a href="/bookings/"
           class="px-3 py-2 text-sm text-blue-100 hover:text-white hover:bg-white/10 rounded-lg transition-colors">
          การจอง
        </a>
        <a href="/members/search.php"
           class="px-3 py-2 text-sm text-blue-100 hover:text-white hover:bg-white/10 rounded-lg transition-colors">
          สมาชิก
        </a>

        <!-- Admin dropdown -->
        <?php if ($_isAdmin): ?>
        <div class="relative" id="admin-dd-wrap">
          <button id="admin-dd-btn" type="button"
                  class="flex items-center gap-1.5 px-3 py-2 text-sm text-blue-100 hover:text-white hover:bg-white/10 rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            จัดการ
            <svg id="admin-dd-chevron" class="w-3.5 h-3.5 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
            </svg>
          </button>

          <div id="admin-dropdown">
            <a href="/admin/courts.php">
              <svg class="dd-icon w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
              คอร์ต
            </a>
            <a href="/admin/members.php">
              <svg class="dd-icon w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
              จัดการสมาชิก
            </a>
            <div class="dd-sep"></div>
            <a href="/admin/promotions.php">
              <svg class="dd-icon w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
              โปรโมชั่น
            </a>
            <a href="/reports/export_excel.php">
              <svg class="dd-icon w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
              รายงาน
            </a>
            <a href="/admin/pricing.php">
              <svg class="dd-icon w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              ตั้งราคา
            </a>
            <div class="dd-sep"></div>
            <a href="/admin/users.php">
              <svg class="dd-icon w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
              ผู้ใช้งาน
            </a>
          </div>
        </div>
        <?php endif; ?>

        <div class="w-px h-5 bg-white/20 mx-1"></div>

        <!-- Username -->
        <?php if (!empty($_SESSION['user'])): ?>
        <span class="text-sm text-blue-200 px-2 hidden lg:inline"><?= $_username ?></span>
        <?php endif; ?>

        <!-- Logout -->
        <a href="/auth/logout.php"
           class="flex items-center gap-1.5 px-3 py-1.5 text-sm text-white rounded-lg bg-white/10 hover:bg-red-500 transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
          </svg>
          ออกจากระบบ
        </a>
      </div>

      <!-- Mobile hamburger -->
      <button id="mobile-menu-btn" type="button" class="md:hidden p-2 text-white rounded-lg hover:bg-white/10 transition-colors">
        <svg id="icon-bars"  class="w-5 h-5"        fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
        <svg id="icon-close" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>
  </div>

  <!-- Mobile menu -->
  <div id="mobile-menu" class="md:hidden border-t border-white/10">
    <div class="px-4 py-3 space-y-0.5">

      <?php if (!empty($_SESSION['user'])): ?>
      <div class="flex items-center gap-2 py-2 px-3 mb-2 text-sm text-blue-200 border-b border-white/10">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
        </svg>
        <?= $_username ?>
        <span class="text-xs bg-white/10 rounded px-1.5 py-0.5 ml-auto">
          <?= $_isAdmin ? 'Admin' : 'User' ?>
        </span>
      </div>
      <?php endif; ?>

      <!-- Main menus -->
      <p class="text-xs text-blue-300/60 uppercase tracking-wider px-3 pt-1 pb-0.5 font-medium">เมนูหลัก</p>
      <a href="/timetable_detail.php" class="flex items-center gap-2.5 px-3 py-2.5 text-sm text-blue-100 hover:text-white hover:bg-white/10 rounded-lg transition-colors">
        <svg class="w-4 h-4 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        ตารางคอร์ต
      </a>
      <a href="/bookings/" class="flex items-center gap-2.5 px-3 py-2.5 text-sm text-blue-100 hover:text-white hover:bg-white/10 rounded-lg transition-colors">
        <svg class="w-4 h-4 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        การจอง
      </a>
      <a href="/members/search.php" class="flex items-center gap-2.5 px-3 py-2.5 text-sm text-blue-100 hover:text-white hover:bg-white/10 rounded-lg transition-colors">
        <svg class="w-4 h-4 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
        สมาชิก
      </a>

      <!-- Admin menus -->
      <?php if ($_isAdmin): ?>
      <div class="border-t border-white/10 mt-2 pt-2">
        <p class="text-xs text-blue-300/60 uppercase tracking-wider px-3 pb-0.5 font-medium">จัดการ</p>
        <a href="/admin/courts.php" class="flex items-center gap-2.5 px-3 py-2.5 text-sm text-blue-100 hover:text-white hover:bg-white/10 rounded-lg transition-colors">
          <svg class="w-4 h-4 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
          คอร์ต
        </a>
        <a href="/admin/members.php" class="flex items-center gap-2.5 px-3 py-2.5 text-sm text-blue-100 hover:text-white hover:bg-white/10 rounded-lg transition-colors">
          <svg class="w-4 h-4 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
          จัดการสมาชิก
        </a>
        <a href="/admin/promotions.php" class="flex items-center gap-2.5 px-3 py-2.5 text-sm text-blue-100 hover:text-white hover:bg-white/10 rounded-lg transition-colors">
          <svg class="w-4 h-4 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
          โปรโมชั่น
        </a>
        <a href="/reports/export_excel.php" class="flex items-center gap-2.5 px-3 py-2.5 text-sm text-blue-100 hover:text-white hover:bg-white/10 rounded-lg transition-colors">
          <svg class="w-4 h-4 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
          รายงาน
        </a>
        <a href="/admin/pricing.php" class="flex items-center gap-2.5 px-3 py-2.5 text-sm text-blue-100 hover:text-white hover:bg-white/10 rounded-lg transition-colors">
          <svg class="w-4 h-4 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          ตั้งราคา
        </a>
        <a href="/admin/users.php" class="flex items-center gap-2.5 px-3 py-2.5 text-sm text-blue-100 hover:text-white hover:bg-white/10 rounded-lg transition-colors">
          <svg class="w-4 h-4 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
          ผู้ใช้งาน
        </a>
      </div>
      <?php endif; ?>

      <!-- Logout -->
      <div class="border-t border-white/10 mt-2 pt-2">
        <a href="/auth/logout.php"
           class="flex items-center gap-2.5 px-3 py-2.5 text-sm text-white hover:bg-red-500/40 rounded-lg transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
          </svg>
          ออกจากระบบ
        </a>
      </div>
    </div>
  </div>
</nav>

<script>
(function () {
  /* ── Mobile menu toggle ─────────────────────── */
  var mBtn  = document.getElementById('mobile-menu-btn');
  var mMenu = document.getElementById('mobile-menu');
  var mBars = document.getElementById('icon-bars');
  var mClose = document.getElementById('icon-close');

  mBtn.addEventListener('click', function () {
    var open = mMenu.classList.toggle('open');
    mBars.classList.toggle('hidden', open);
    mClose.classList.toggle('hidden', !open);
  });

  /* ── Admin dropdown toggle ──────────────────── */
  var ddBtn  = document.getElementById('admin-dd-btn');
  var ddMenu = document.getElementById('admin-dropdown');
  var ddChev = document.getElementById('admin-dd-chevron');

  if (ddBtn) {
    ddBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      var open = ddMenu.classList.toggle('open');
      ddChev.style.transform = open ? 'rotate(180deg)' : '';
      ddBtn.classList.toggle('nav-on', open);
    });

    /* close dropdown when clicking outside */
    document.addEventListener('click', function () {
      ddMenu.classList.remove('open');
      ddChev.style.transform = '';
      ddBtn.classList.remove('nav-on');
    });

    /* close on Escape */
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        ddMenu.classList.remove('open');
        ddChev.style.transform = '';
        ddBtn.classList.remove('nav-on');
      }
    });
  }

  /* ── Active nav link highlight ──────────────── */
  document.addEventListener('DOMContentLoaded', function () {
    var path = location.pathname.replace(/\/$/, '') || '/';

    /* main nav links */
    document.querySelectorAll('nav a[href]').forEach(function (a) {
      var lp = (a.pathname || '').replace(/\/$/, '') || '/';
      var match = (lp === path) || (lp !== '' && lp !== '/' && path.startsWith(lp));
      if (match) a.classList.add('nav-on');
    });

    /* if current page is an admin page → highlight the dropdown button too */
    if (ddBtn) {
      var adminPaths = ['/admin/', '/reports/'];
      var inAdmin = adminPaths.some(function (p) { return path.startsWith(p); });
      if (inAdmin) ddBtn.classList.add('nav-on');
    }
  });
})();
</script>
