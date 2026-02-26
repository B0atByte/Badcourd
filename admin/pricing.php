<?php
require_once __DIR__ . '/../auth/guard.php';
require_role(['admin']);
require_once __DIR__ . '/../config/db.php';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    $day_type      = in_array($_POST['day_type'] ?? '', ['weekday','weekend']) ? $_POST['day_type'] : '';
    $start_time    = $_POST['start_time'] ?? '';
    $end_time      = $_POST['end_time']   ?? '';
    $price_per_hour = (int)($_POST['price_per_hour'] ?? 0);

    if (!$day_type || !$start_time || !$end_time) {
        $error = 'กรุณากรอกข้อมูลให้ครบถ้วน';
    } elseif ($end_time <= $start_time) {
        $error = 'เวลาสิ้นสุดต้องมากกว่าเวลาเริ่มต้น';
    } elseif ($price_per_hour < 50 || $price_per_hour > 10000) {
        $error = 'ราคาต้องอยู่ระหว่าง 50–10,000 บาท';
    } else {
        try {
            $stmt = $pdo->prepare('INSERT INTO pricing_rules(day_type,start_time,end_time,price_per_hour) VALUES(:d,:s,:e,:p)');
            $stmt->execute([':d'=>$day_type, ':s'=>$start_time, ':e'=>$end_time, ':p'=>$price_per_hour]);
            header('Location: pricing.php?added=1'); exit;
        } catch (PDOException $e) {
            $error = 'เกิดข้อผิดพลาดในการบันทึก กรุณาลองอีกครั้ง';
        }
    }
}

if (isset($_GET['delete'])) {
    $pdo->prepare('DELETE FROM pricing_rules WHERE id=?')->execute([(int)$_GET['delete']]);
    header('Location: pricing.php?deleted=1'); exit;
}

if (isset($_GET['added']))   $success = 'เพิ่มกฎราคาสำเร็จ';
if (isset($_GET['deleted'])) $success = 'ลบกฎราคาสำเร็จ';

$rules = $pdo->query('SELECT * FROM pricing_rules ORDER BY day_type, start_time')->fetchAll();
$weekdayRules = array_filter($rules, fn($r) => $r['day_type'] === 'weekday');
$weekendRules = array_filter($rules, fn($r) => $r['day_type'] === 'weekend');
$allPrices    = array_column($rules, 'price_per_hour');
$avgPrice     = count($allPrices) ? array_sum($allPrices) / count($allPrices) : 0;

// Generate time options (06:00 – 23:30, every 30 min)
$timeOptions = [];
for ($h = 6; $h <= 23; $h++) {
    foreach (['00','30'] as $m) {
        $timeOptions[] = sprintf('%02d:%s', $h, $m);
    }
}
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
  <title>ตั้งราคาคอร์ต - BARGAIN SPORT</title>
</head>
<body style="background:#FAFAFA;" class="min-h-screen">
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="max-w-6xl mx-auto px-4 py-8">

  <!-- Header -->
  <div class="mb-6">
    <h1 style="color:#005691;" class="text-2xl font-bold">ตั้งราคาคอร์ต</h1>
    <p class="text-gray-500 text-sm mt-0.5">Admin Panel · กำหนดอัตราค่าเช่าคอร์ตตามวันและช่วงเวลา</p>
  </div>

  <!-- Flash messages -->
  <?php if ($success): ?>
  <div class="mb-4 bg-green-50 border border-green-200 rounded-xl px-4 py-3 text-green-700 text-sm"><?= $success ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
  <div class="mb-4 bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-red-600 text-sm"><?= $error ?></div>
  <?php endif; ?>

  <!-- Stats -->
  <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-gray-200 p-4">
      <p class="text-gray-400 text-xs mb-1">กฎทั้งหมด</p>
      <p style="color:#005691;" class="text-2xl font-bold"><?= count($rules) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4">
      <p class="text-gray-400 text-xs mb-1">จันทร์–ศุกร์</p>
      <p class="text-2xl font-bold text-gray-800"><?= count($weekdayRules) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4">
      <p class="text-gray-400 text-xs mb-1">เสาร์–อาทิตย์</p>
      <p class="text-2xl font-bold text-gray-800"><?= count($weekendRules) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4">
      <p class="text-gray-400 text-xs mb-1">ราคาเฉลี่ย/ชม.</p>
      <p class="text-2xl font-bold text-gray-800">฿<?= count($rules) ? number_format($avgPrice, 0) : '—' ?></p>
    </div>
  </div>

  <!-- Add Form -->
  <div class="bg-white rounded-xl border border-gray-200 p-6 mb-5">
    <h2 style="color:#005691;" class="font-semibold mb-4 text-sm uppercase tracking-wide">เพิ่มกฎราคาใหม่</h2>
    <form method="post" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">

      <!-- Day type -->
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">ประเภทวัน</label>
        <select name="day_type" required
                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:border-[#E8F1F5] focus:ring-2 focus:ring-[#E8F1F5]/20 outline-none text-sm">
          <option value="weekday">จันทร์–ศุกร์</option>
          <option value="weekend">เสาร์–อาทิตย์</option>
        </select>
      </div>

      <!-- Start time -->
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">เวลาเริ่ม</label>
        <div class="relative">
          <input type="text" id="startDisplay" readonly value="08:00"
                 onclick="togglePicker('start')"
                 class="w-full px-3 py-2.5 border border-gray-300 rounded-lg outline-none text-sm text-center cursor-pointer font-medium bg-white hover:border-gray-400 transition-colors">
          <input type="hidden" name="start_time" id="startVal" value="08:00">
          <div id="startPicker" class="hidden absolute z-50 mt-1 w-full bg-white border border-gray-200 rounded-xl shadow-lg max-h-56 overflow-y-auto">
            <div style="background:#005691;" class="px-3 py-2 text-white text-xs font-medium rounded-t-xl">เลือกเวลาเริ่ม</div>
            <div class="grid grid-cols-3 gap-1 p-2">
              <?php foreach ($timeOptions as $t): ?>
              <button type="button" onclick="pickTime('start','<?= $t ?>')"
                      class="picker-btn px-2 py-1.5 text-xs text-center rounded-lg hover:text-white transition-colors font-medium border border-gray-100"
                      style="--ac:#005691;">
                <?= $t ?>
              </button>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- End time -->
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">เวลาสิ้นสุด</label>
        <div class="relative">
          <input type="text" id="endDisplay" readonly value="12:00"
                 onclick="togglePicker('end')"
                 class="w-full px-3 py-2.5 border border-gray-300 rounded-lg outline-none text-sm text-center cursor-pointer font-medium bg-white hover:border-gray-400 transition-colors">
          <input type="hidden" name="end_time" id="endVal" value="12:00">
          <div id="endPicker" class="hidden absolute z-50 mt-1 w-full bg-white border border-gray-200 rounded-xl shadow-lg max-h-56 overflow-y-auto">
            <div style="background:#004A7C;" class="px-3 py-2 text-white text-xs font-medium rounded-t-xl">เลือกเวลาสิ้นสุด</div>
            <div class="grid grid-cols-3 gap-1 p-2">
              <?php foreach ($timeOptions as $t): ?>
              <button type="button" onclick="pickTime('end','<?= $t ?>')"
                      class="picker-btn px-2 py-1.5 text-xs text-center rounded-lg hover:text-white transition-colors font-medium border border-gray-100">
                <?= $t ?>
              </button>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Price -->
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">ราคา (฿/ชม.)</label>
        <input type="number" name="price_per_hour" required min="50" max="10000" step="10" value="400"
               placeholder="เช่น 400"
               class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:border-[#E8F1F5] focus:ring-2 focus:ring-[#E8F1F5]/20 outline-none text-sm">
      </div>

      <!-- Submit -->
      <div class="flex items-end">
        <button type="submit" name="add"
                style="background:#004A7C;"
                class="w-full px-5 py-2.5 text-white text-sm font-medium rounded-lg hover:opacity-90 transition-opacity">
          + เพิ่มกฎ
        </button>
      </div>
    </form>
  </div>

  <!-- Rules Table -->
  <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <div style="background:#005691;" class="px-5 py-3 flex justify-between items-center">
      <h2 class="text-white font-medium text-sm">กฎราคาทั้งหมด</h2>
      <span class="text-blue-200 text-xs"><?= count($rules) ?> กฎ</span>
    </div>

    <?php if (count($rules) === 0): ?>
    <div class="p-12 text-center text-gray-400">
      <p class="mb-1 font-medium">ยังไม่มีกฎราคา</p>
      <p class="text-sm">เพิ่มกฎราคาจากฟอร์มด้านบน</p>
    </div>
    <?php else: ?>

    <!-- Mobile -->
    <div class="block lg:hidden divide-y divide-gray-100">
      <?php foreach ($rules as $r):
        $isWeekend = $r['day_type'] === 'weekend';
        $dayText   = $isWeekend ? 'เสาร์–อาทิตย์' : 'จันทร์–ศุกร์';
        $badgeCls  = $isWeekend ? 'bg-orange-100 text-orange-700' : 'bg-blue-100 text-blue-700';
      ?>
      <div class="p-4">
        <div class="flex justify-between items-start mb-3">
          <div>
            <span class="text-xs px-2 py-1 rounded-full <?= $badgeCls ?> font-medium"><?= $dayText ?></span>
            <p class="text-sm font-semibold text-gray-800 mt-2">
              <?= substr($r['start_time'],0,5) ?> – <?= substr($r['end_time'],0,5) ?>
            </p>
          </div>
          <div class="text-right">
            <p style="color:#005691;" class="text-xl font-bold">฿<?= number_format($r['price_per_hour'],0) ?></p>
            <p class="text-xs text-gray-400">ต่อชั่วโมง</p>
          </div>
        </div>
        <a href="?delete=<?= $r['id'] ?>"
           onclick="return confirm('ยืนยันลบกฎราคา <?= $dayText ?> เวลา <?= substr($r['start_time'],0,5) ?>–<?= substr($r['end_time'],0,5) ?>?')"
           class="block w-full py-2 border border-red-200 text-red-500 text-xs rounded-lg text-center hover:bg-red-50 transition-colors">
          ลบกฎนี้
        </a>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Desktop -->
    <div class="hidden lg:block overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-gray-50 border-b border-gray-200 text-left">
            <th class="px-5 py-3 font-medium text-gray-500">ประเภทวัน</th>
            <th class="px-5 py-3 font-medium text-gray-500 text-center">ช่วงเวลา</th>
            <th class="px-5 py-3 font-medium text-gray-500 text-right">ราคา/ชม.</th>
            <th class="px-5 py-3 font-medium text-gray-500 text-center">จัดการ</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php foreach ($rules as $r):
            $isWeekend = $r['day_type'] === 'weekend';
            $dayText   = $isWeekend ? 'เสาร์–อาทิตย์' : 'จันทร์–ศุกร์';
            $badgeCls  = $isWeekend ? 'bg-orange-100 text-orange-700' : 'bg-blue-100 text-blue-700';
          ?>
          <tr class="hover:bg-gray-50 transition-colors">
            <td class="px-5 py-3">
              <span class="text-xs px-2.5 py-1 rounded-full <?= $badgeCls ?> font-medium"><?= $dayText ?></span>
            </td>
            <td class="px-5 py-3 text-center font-medium text-gray-700">
              <?= substr($r['start_time'],0,5) ?> – <?= substr($r['end_time'],0,5) ?>
            </td>
            <td class="px-5 py-3 text-right">
              <span style="color:#005691;" class="text-base font-bold">฿<?= number_format($r['price_per_hour'],0) ?></span>
              <span class="text-xs text-gray-400 ml-0.5">/ชม.</span>
            </td>
            <td class="px-5 py-3 text-center">
              <a href="?delete=<?= $r['id'] ?>"
                 onclick="return confirm('ยืนยันลบกฎราคา <?= $dayText ?> เวลา <?= substr($r['start_time'],0,5) ?>–<?= substr($r['end_time'],0,5) ?>?')"
                 class="px-3 py-1.5 border border-red-200 text-red-500 text-xs rounded hover:bg-red-50 transition-colors">
                ลบ
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <!-- Tip -->
  <div class="mt-5 bg-white rounded-xl border border-gray-200 p-5">
    <h3 style="color:#005691;" class="font-medium mb-2 text-sm">คำแนะนำการตั้งราคา</h3>
    <ul class="text-gray-500 text-xs space-y-1">
      <li>· เวลาเช้า 06:00–12:00 — ราคาปานกลาง เหมาะผู้เล่นประจำ</li>
      <li>· เวลาบ่าย 12:00–16:00 — ราคาต่ำ ช่วงที่มีคนเล่นน้อย</li>
      <li>· เวลาเย็น 16:00–21:00 — ราคาสูง Peak Hours</li>
      <li>· ราคาวันหยุดควรสูงกว่าวันธรรมดา 20–30%</li>
      <li>· ตรวจสอบให้แน่ใจว่าไม่มีช่วงเวลาซ้อนทับกัน</li>
    </ul>
  </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<style>
.picker-btn:hover { background: #005691; color: #fff; border-color: #005691; }
.picker-btn.selected { background: #005691; color: #fff; border-color: #005691; }
</style>

<script>
function togglePicker(type) {
    var p = document.getElementById(type + 'Picker');
    var other = type === 'start' ? 'end' : 'start';
    document.getElementById(other + 'Picker').classList.add('hidden');
    p.classList.toggle('hidden');
}
function pickTime(type, time) {
    document.getElementById(type + 'Display').value = time;
    document.getElementById(type + 'Val').value = time;
    document.getElementById(type + 'Picker').classList.add('hidden');
    // highlight selected
    document.querySelectorAll('#' + type + 'Picker .picker-btn').forEach(function(b) {
        b.classList.toggle('selected', b.textContent.trim() === time);
    });
}
// Close pickers on outside click
document.addEventListener('click', function(e) {
    ['start','end'].forEach(function(t) {
        var wrap = document.getElementById(t + 'Display');
        var picker = document.getElementById(t + 'Picker');
        if (wrap && picker && !wrap.contains(e.target) && !picker.contains(e.target)) {
            picker.classList.add('hidden');
        }
    });
});
</script>
</body>
</html>
