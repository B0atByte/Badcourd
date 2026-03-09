<?php
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../config/db.php';

require_role(['admin']);

$success = $error = '';

// ── โหลด settings ───────────────────────────────────────────────
$rows = $pdo->query("SELECT setting_key, setting_value FROM site_settings")->fetchAll();
$settings = [];
foreach ($rows as $r) { $settings[$r['setting_key']] = $r['setting_value']; }
$siteName    = $settings['site_name']    ?? 'BARGAIN SPORT';
$siteLogo    = $settings['site_logo']    ?? '/logo/BPL.png';
$siteFavicon = $settings['site_favicon'] ?? '/logo/BPL.png';

// ── helper: อัปโหลดรูป ──────────────────────────────────────────
function uploadImage(array $file, string $prefix): array {
    $allowed = ['png','jpg','jpeg','gif','svg','webp','ico'];
    $maxSize = 2 * 1024 * 1024;
    $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed))   return ['error' => 'รองรับเฉพาะ: ' . implode(', ', $allowed)];
    if ($file['size'] > $maxSize)    return ['error' => 'ไฟล์ใหญ่เกิน 2 MB'];
    $filename = $prefix . '.' . $ext;
    $destPath = __DIR__ . '/../logo/' . $filename;
    $webPath  = '/logo/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $destPath)) return ['error' => 'บันทึกไฟล์ไม่ได้ ตรวจสอบสิทธิ์โฟลเดอร์ /logo/'];
    return ['path' => $webPath, 'filename' => $filename];
}

function saveSetting(PDO $pdo, string $key, string $val): void {
    $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?,?)
                   ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")
        ->execute([$key, $val]);
}

// ── POST ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_name') {
        $newName = trim($_POST['site_name'] ?? '');
        if (!$newName) { $error = 'กรุณากรอกชื่อเว็บไซต์'; }
        else { saveSetting($pdo, 'site_name', $newName); $siteName = $newName; $success = "บันทึกชื่อเว็บ \"$newName\" แล้ว"; }
    }

    elseif ($action === 'upload_logo') {
        if (!isset($_FILES['logo_file']) || $_FILES['logo_file']['error'] !== UPLOAD_ERR_OK) {
            $error = 'กรุณาเลือกไฟล์รูปภาพ';
        } else {
            $res = uploadImage($_FILES['logo_file'], 'site_logo');
            if (isset($res['error'])) { $error = $res['error']; }
            else {
                saveSetting($pdo, 'site_logo', $res['path']);
                saveSetting($pdo, 'site_logo_filename', $res['filename']);
                $siteLogo = $res['path'];
                $success  = 'อัปโหลด Logo สำเร็จ';
            }
        }
    }

    elseif ($action === 'upload_favicon') {
        if (!isset($_FILES['favicon_file']) || $_FILES['favicon_file']['error'] !== UPLOAD_ERR_OK) {
            $error = 'กรุณาเลือกไฟล์ Favicon';
        } else {
            $res = uploadImage($_FILES['favicon_file'], 'site_favicon');
            if (isset($res['error'])) { $error = $res['error']; }
            else {
                saveSetting($pdo, 'site_favicon', $res['path']);
                $siteFavicon = $res['path'];
                $success     = 'อัปโหลด Favicon สำเร็จ';
            }
        }
    }
}

// helper สำหรับ drop-zone HTML
function dropZone(string $inputId, string $previewId, string $nameDisplayId, string $dzId): string {
    return <<<HTML
    <label for="$inputId" id="$dzId"
      class="flex flex-col items-center justify-center gap-2 border-2 border-dashed border-gray-300 rounded-xl py-7 cursor-pointer hover:border-blue-400 hover:bg-blue-50/30 transition-colors">
      <svg class="w-7 h-7 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
          d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
      </svg>
      <p class="text-sm text-gray-500">คลิกเพื่อเลือกไฟล์ หรือลากมาวางที่นี่</p>
      <span id="$nameDisplayId" class="text-xs text-blue-600 font-medium hidden"></span>
    </label>
HTML;
}
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>การตั้งค่า – <?= htmlspecialchars($siteName) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>* { font-family: 'Prompt', sans-serif !important; }</style>
</head>
<body style="background:#f8fafc;" class="min-h-screen">
  <?php include __DIR__ . '/../includes/header.php'; ?>
  <?php include __DIR__ . '/../includes/swal_flash.php'; ?>

  <div class="max-w-3xl mx-auto px-4 py-8">

    <div class="mb-7">
      <h1 class="text-2xl font-bold" style="color:#D32F2F;">การตั้งค่าเว็บไซต์</h1>
      <p class="text-gray-500 text-sm mt-1">ปรับแต่งชื่อเว็บ, โลโก้ใน Navbar และ Favicon ในแท็บเบราว์เซอร์</p>
    </div>

    <!-- ══ ชื่อเว็บ ══ -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-5">
      <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-2">
        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064"/>
        </svg>
        <h2 class="font-semibold text-gray-800">ชื่อเว็บไซต์</h2>
      </div>
      <form method="post" class="px-6 py-5">
        <input type="hidden" name="action" value="save_name">
        <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อที่แสดงใน Navbar</label>
        <div class="flex gap-3">
          <input type="text" name="site_name" required id="site-name-input"
            value="<?= htmlspecialchars($siteName) ?>" placeholder="เช่น BARGAIN SPORT"
            class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
          <button type="submit" class="px-5 py-2 text-white text-sm rounded-lg hover:opacity-90"
            style="background:#D32F2F;">บันทึก</button>
        </div>
        <p class="text-xs text-gray-400 mt-1.5">ชื่อนี้แสดงข้างโลโก้ในแถบด้านบนทุกหน้า</p>
      </form>
    </div>

    <!-- ══ 2 คอลัมน์: Logo + Favicon ══ -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5">

      <!-- Logo Navbar -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 flex flex-col">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
          <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
          </svg>
          <div>
            <h2 class="font-semibold text-gray-800 text-sm">Logo Navbar</h2>
            <p class="text-xs text-gray-400">แสดงใน header ทุกหน้า</p>
          </div>
        </div>
        <div class="px-5 py-4 flex-1 flex flex-col">
          <!-- current -->
          <div class="flex items-center gap-3 mb-4 p-3 bg-gray-50 rounded-lg border border-gray-200">
            <div class="w-12 h-12 rounded-lg bg-white border border-gray-200 flex items-center justify-center shrink-0 overflow-hidden">
              <img id="logo-preview" src="<?= htmlspecialchars($siteLogo) ?>?v=<?= time() ?>"
                alt="Logo" class="w-full h-full object-contain">
            </div>
            <div class="min-w-0">
              <p class="text-xs font-medium text-gray-700">ปัจจุบัน</p>
              <p class="text-xs text-gray-400 truncate"><?= htmlspecialchars($siteLogo) ?></p>
              <p class="text-xs text-gray-400 mt-0.5">แนะนำ: รูปสี่เหลี่ยม ไม่มีพื้นหลัง (PNG)</p>
            </div>
          </div>
          <form method="post" enctype="multipart/form-data" class="flex-1 flex flex-col">
            <input type="hidden" name="action" value="upload_logo">
            <?= dropZone('logo-input','logo-preview','logo-name-display','logo-dz') ?>
            <input type="file" id="logo-input" name="logo_file"
              accept="image/png,image/jpeg,image/gif,image/svg+xml,image/webp"
              class="hidden" onchange="previewFile(this,'logo-preview','logo-name-display')">
            <button type="submit" class="mt-3 w-full py-2 text-white text-sm rounded-lg hover:opacity-90"
              style="background:#D32F2F;">อัปโหลด Logo</button>
          </form>
        </div>
      </div>

      <!-- Favicon -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 flex flex-col">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
          <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/>
          </svg>
          <div>
            <h2 class="font-semibold text-gray-800 text-sm">Favicon แท็บเบราว์เซอร์</h2>
            <p class="text-xs text-gray-400">ไอคอนในแท็บ / Bookmark</p>
          </div>
        </div>
        <div class="px-5 py-4 flex-1 flex flex-col">
          <!-- current + browser tab simulation -->
          <div class="flex items-center gap-3 mb-4 p-3 bg-gray-50 rounded-lg border border-gray-200">
            <div class="w-12 h-12 rounded-lg bg-white border border-gray-200 flex items-center justify-center shrink-0 overflow-hidden">
              <img id="favicon-preview" src="<?= htmlspecialchars($siteFavicon) ?>?v=<?= time() ?>"
                alt="Favicon" class="w-full h-full object-contain">
            </div>
            <div class="min-w-0 flex-1">
              <p class="text-xs font-medium text-gray-700">ปัจจุบัน</p>
              <p class="text-xs text-gray-400 truncate"><?= htmlspecialchars($siteFavicon) ?></p>
              <!-- Tab simulation -->
              <div class="mt-1.5 inline-flex items-center gap-1.5 px-2 py-1 bg-white border border-gray-200 rounded-t-md text-xs text-gray-600" style="font-size:10px;">
                <img id="favicon-tab-preview" src="<?= htmlspecialchars($siteFavicon) ?>?v=<?= time() ?>"
                  class="w-3 h-3 object-contain">
                <span>ตัวอย่างแท็บ</span>
              </div>
            </div>
          </div>
          <form method="post" enctype="multipart/form-data" class="flex-1 flex flex-col">
            <input type="hidden" name="action" value="upload_favicon">
            <label for="favicon-input" id="favicon-dz"
              class="flex flex-col items-center justify-center gap-2 border-2 border-dashed border-amber-200 rounded-xl py-7 cursor-pointer hover:border-amber-400 hover:bg-amber-50/30 transition-colors">
              <svg class="w-7 h-7 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
              </svg>
              <p class="text-sm text-gray-500">คลิกเพื่อเลือกไฟล์ favicon</p>
              <p class="text-xs text-gray-400">PNG, ICO, SVG (แนะนำขนาด 32×32 หรือ 64×64)</p>
              <span id="favicon-name-display" class="text-xs text-amber-600 font-medium hidden"></span>
            </label>
            <input type="file" id="favicon-input" name="favicon_file"
              accept="image/png,image/x-icon,image/svg+xml,image/webp,image/jpeg"
              class="hidden" onchange="previewFile(this,'favicon-preview','favicon-name-display','favicon-tab-preview')">
            <button type="submit" class="mt-3 w-full py-2 text-sm rounded-lg hover:opacity-90 font-medium"
              style="background:#f59e0b;color:#fff;">อัปโหลด Favicon</button>
          </form>
        </div>
      </div>
    </div>

    <!-- ══ ตัวอย่าง Navbar ══ -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
      <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-2">
        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
        </svg>
        <h2 class="font-semibold text-gray-800">ตัวอย่าง Navbar + แท็บ</h2>
      </div>
      <div class="px-6 py-4">
        <!-- browser window simulation -->
        <div class="rounded-lg overflow-hidden border border-gray-200 shadow-sm">
          <!-- fake browser bar -->
          <div class="bg-gray-100 border-b border-gray-200 px-3 py-2 flex items-center gap-2">
            <div class="flex gap-1">
              <div class="w-2.5 h-2.5 rounded-full bg-red-400"></div>
              <div class="w-2.5 h-2.5 rounded-full bg-yellow-400"></div>
              <div class="w-2.5 h-2.5 rounded-full bg-green-400"></div>
            </div>
            <!-- fake tab -->
            <div class="flex items-center gap-1.5 bg-white rounded-t px-3 py-1 text-xs text-gray-700 border border-b-white border-gray-200 -mb-2 ml-2">
              <img id="favicon-preview-tab" src="<?= htmlspecialchars($siteFavicon) ?>?v=<?= time() ?>"
                class="w-3.5 h-3.5 object-contain">
              <span id="tab-name-preview"><?= htmlspecialchars($siteName) ?></span>
            </div>
          </div>
          <!-- fake navbar -->
          <div style="background:#D32F2F;" class="px-4 py-2.5 flex items-center gap-2">
            <img id="navbar-logo-preview" src="<?= htmlspecialchars($siteLogo) ?>?v=<?= time() ?>"
              style="width:38px;height:38px;object-fit:contain;border-radius:6px;">
            <span id="navbar-name-preview" class="text-white font-semibold text-sm"><?= htmlspecialchars($siteName) ?></span>
          </div>
        </div>
        <p class="text-xs text-gray-400 mt-2">* อัปเดตแบบ real-time ขณะเลือกไฟล์หรือพิมพ์ชื่อ</p>
      </div>
    </div>

  </div>

  <script>
    // Live preview ชื่อเว็บ
    document.getElementById('site-name-input').addEventListener('input', function() {
      var v = this.value || 'BARGAIN SPORT';
      document.getElementById('navbar-name-preview').textContent = v;
      document.getElementById('tab-name-preview').textContent = v;
    });

    // Preview รูปก่อนอัปโหลด
    function previewFile(input, previewId, nameDisplayId, extraId) {
      if (!input.files || !input.files[0]) return;
      var file = input.files[0];
      var nd = document.getElementById(nameDisplayId);
      if (nd) { nd.textContent = '✓ ' + file.name; nd.classList.remove('hidden'); }
      var reader = new FileReader();
      reader.onload = function(e) {
        var src = e.target.result;
        var el = document.getElementById(previewId);
        if (el) el.src = src;
        if (extraId) {
          var el2 = document.getElementById(extraId);
          if (el2) el2.src = src;
        }
        // อัปเดต preview ใน preview section ด้วย
        if (previewId === 'logo-preview') {
          var nb = document.getElementById('navbar-logo-preview');
          if (nb) nb.src = src;
        }
        if (previewId === 'favicon-preview') {
          var ft = document.getElementById('favicon-preview-tab');
          if (ft) ft.src = src;
          var ft2 = document.getElementById('favicon-tab-preview');
          if (ft2) ft2.src = src;
        }
      };
      reader.readAsDataURL(file);
    }

    // Drag & drop สำหรับ logo
    setupDrop('logo-dz', 'logo-input', 'logo-preview', 'logo-name-display');
    setupDrop('favicon-dz', 'favicon-input', 'favicon-preview', 'favicon-name-display');

    function setupDrop(dzId, inputId, previewId, nameId) {
      var dz = document.getElementById(dzId);
      var inp = document.getElementById(inputId);
      if (!dz || !inp) return;
      ['dragenter','dragover'].forEach(function(ev) {
        dz.addEventListener(ev, function(e) { e.preventDefault(); dz.style.borderColor='#3b82f6'; });
      });
      ['dragleave','drop'].forEach(function(ev) {
        dz.addEventListener(ev, function(e) { e.preventDefault(); dz.style.borderColor=''; });
      });
      dz.addEventListener('drop', function(e) {
        var files = e.dataTransfer.files;
        if (files.length) { inp.files = files; previewFile(inp, previewId, nameId); }
      });
    }
  </script>
</body>
</html>
