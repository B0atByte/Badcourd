<?php
require_once __DIR__ . '/../auth/guard.php';
require_role(['admin']);
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    $stmt = $pdo->prepare('INSERT INTO pricing_rules(day_type,start_time,end_time,price_per_hour) VALUES(:d,:s,:e,:p)');
    $stmt->execute([
        ':d' => $_POST['day_type'],
        ':s' => $_POST['start_time'],
        ':e' => $_POST['end_time'],
        ':p' => $_POST['price_per_hour']
    ]);
    header('Location: pricing.php');
    exit;
}

if (isset($_GET['delete'])) {
    $pdo->prepare('DELETE FROM pricing_rules WHERE id=:id')->execute([':id' => (int)$_GET['delete']]);
    header('Location: pricing.php');
    exit;
}

$rules = $pdo->query('SELECT * FROM pricing_rules ORDER BY day_type,start_time')->fetchAll();

// คำนวณสถิติ
$weekdayRules = array_filter($rules, fn($r) => $r['day_type'] === 'weekday');
$weekendRules = array_filter($rules, fn($r) => $r['day_type'] === 'weekend');
$avgWeekdayPrice = count($weekdayRules) > 0 ? array_sum(array_column($weekdayRules, 'price_per_hour')) / count($weekdayRules) : 0;
$avgWeekendPrice = count($weekendRules) > 0 ? array_sum(array_column($weekendRules, 'price_per_hour')) / count($weekendRules) : 0;
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <title>ตั้งราคา - BARGAIN SPORT</title>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container mx-auto px-4 py-8 max-w-7xl">
  <!-- Header Section -->
  <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
      <div>
        <h1 class="text-3xl font-bold text-gray-800 flex items-center gap-3 mb-2">
          <i class="fas fa-tag text-pink-600"></i>
          จัดการราคาคอร์ต
        </h1>
        <p class="text-gray-600 flex items-center gap-2">
          <i class="fas fa-shield-alt text-purple-500"></i>
          <span class="font-semibold">Admin Panel</span> - ตั้งค่าราคาตามวันและช่วงเวลา
        </p>
      </div>
    </div>
  </div>

  <!-- Stats Cards -->
  <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-md p-5 hover:shadow-lg transition-shadow">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-gray-600 text-sm mb-1">กฎทั้งหมด</p>
          <p class="text-3xl font-bold text-gray-800"><?= count($rules) ?></p>
        </div>
        <div class="w-14 h-14 bg-gradient-to-br from-pink-500 to-rose-600 rounded-xl flex items-center justify-center">
          <i class="fas fa-list-ul text-white text-2xl"></i>
        </div>
      </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-md p-5 hover:shadow-lg transition-shadow">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-gray-600 text-sm mb-1">จันทร์-ศุกร์</p>
          <p class="text-3xl font-bold text-blue-600"><?= count($weekdayRules) ?></p>
        </div>
        <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center">
          <i class="fas fa-briefcase text-white text-2xl"></i>
        </div>
      </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-md p-5 hover:shadow-lg transition-shadow">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-gray-600 text-sm mb-1">เสาร์-อาทิตย์</p>
          <p class="text-3xl font-bold text-orange-600"><?= count($weekendRules) ?></p>
        </div>
        <div class="w-14 h-14 bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl flex items-center justify-center">
          <i class="fas fa-umbrella-beach text-white text-2xl"></i>
        </div>
      </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-md p-5 hover:shadow-lg transition-shadow">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-gray-600 text-sm mb-1">ราคาเฉลี่ย</p>
          <p class="text-3xl font-bold text-green-600">฿<?= count($rules) > 0 ? number_format(($avgWeekdayPrice + $avgWeekendPrice) / 2, 0) : '0' ?></p>
        </div>
        <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl flex items-center justify-center">
          <i class="fas fa-coins text-white text-2xl"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Add Pricing Rule Form -->
  <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
    <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
      <i class="fas fa-plus-circle text-pink-600"></i>
      เพิ่มกฎราคาใหม่
    </h2>
    
    <form method="post" class="grid grid-cols-1 md:grid-cols-5 gap-4">
      <!-- Day Type -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">
          <i class="fas fa-calendar-day text-blue-500 mr-1"></i>ประเภทวัน
        </label>
        <select name="day_type" required
                class="w-full px-4 py-2.5 border-2 border-gray-300 rounded-xl focus:border-pink-500 
                       focus:ring-2 focus:ring-pink-200 transition-all outline-none font-medium">
          <option value="weekday">📅 จันทร์-ศุกร์</option>
          <option value="weekend">🎉 เสาร์-อาทิตย์</option>
        </select>
      </div>

      <!-- Start Time -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">
          <i class="fas fa-clock text-green-500 mr-1"></i>เวลาเริ่ม
        </label>
        <div class="relative">
          <input type="text" id="startTimeDisplay" readonly
                 value="08:00"
                 onclick="toggleTimePicker('start')"
                 class="w-full px-4 py-2.5 border-2 border-gray-300 rounded-xl focus:border-pink-500 focus:ring-2 focus:ring-pink-200 transition-all outline-none cursor-pointer font-bold text-center"
                 placeholder="เลือกเวลา">
          <input type="hidden" name="start_time" id="startTimeInput" value="08:00">
          
          <div id="startTimePicker" class="hidden absolute z-50 mt-2 w-full bg-white rounded-xl shadow-2xl border-2 border-pink-300 max-h-64 overflow-y-auto">
            <div class="p-2 bg-green-600 text-white font-bold text-center rounded-t-xl text-sm">
              เลือกเวลาเริ่ม
            </div>
            <div class="grid grid-cols-2 gap-1 p-2">
              <?php
              for ($h = 6; $h <= 23; $h++) {
                foreach (['00', '30'] as $m) {
                  $timeValue = sprintf('%02d:%s', $h, $m);
                  echo '<button type="button" onclick="selectTime(\'start\', \''.$timeValue.'\')" class="time-option px-3 py-2 text-center rounded-lg hover:bg-green-100 hover:text-green-700 transition-colors font-semibold text-sm">'.$timeValue.'</button>';
                }
              }
              ?>
            </div>
          </div>
        </div>
      </div>

      <!-- End Time -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">
          <i class="fas fa-clock text-red-500 mr-1"></i>เวลาสิ้นสุด
        </label>
        <div class="relative">
          <input type="text" id="endTimeDisplay" readonly
                 value="12:00"
                 onclick="toggleTimePicker('end')"
                 class="w-full px-4 py-2.5 border-2 border-gray-300 rounded-xl focus:border-pink-500 focus:ring-2 focus:ring-pink-200 transition-all outline-none cursor-pointer font-bold text-center"
                 placeholder="เลือกเวลา">
          <input type="hidden" name="end_time" id="endTimeInput" value="12:00">
          
          <div id="endTimePicker" class="hidden absolute z-50 mt-2 w-full bg-white rounded-xl shadow-2xl border-2 border-pink-300 max-h-64 overflow-y-auto">
            <div class="p-2 bg-red-600 text-white font-bold text-center rounded-t-xl text-sm">
              เลือกเวลาสิ้นสุด
            </div>
            <div class="grid grid-cols-2 gap-1 p-2">
              <?php
              for ($h = 6; $h <= 23; $h++) {
                foreach (['00', '30'] as $m) {
                  $timeValue = sprintf('%02d:%s', $h, $m);
                  echo '<button type="button" onclick="selectTime(\'end\', \''.$timeValue.'\')" class="time-option px-3 py-2 text-center rounded-lg hover:bg-red-100 hover:text-red-700 transition-colors font-semibold text-sm">'.$timeValue.'</button>';
                }
              }
              ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Price -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">
          <i class="fas fa-money-bill-wave text-yellow-500 mr-1"></i>ราคา/ชม.
        </label>
        <input type="number" step="1" name="price_per_hour" placeholder="บาท" required
               min="50" max="10000" value="400"
               class="w-full px-4 py-2.5 border-2 border-gray-300 rounded-xl focus:border-pink-500 
                      focus:ring-2 focus:ring-pink-200 transition-all outline-none font-medium">
      </div>

      <!-- Submit Button -->
      <div class="flex items-end">
        <button type="submit" name="add"
                class="w-full px-6 py-2.5 bg-gradient-to-r from-pink-500 to-rose-600 text-white rounded-xl 
                       font-bold hover:from-pink-600 hover:to-rose-700 hover:shadow-lg transform hover:scale-105 
                       transition-all duration-300 flex items-center justify-center gap-2">
          <i class="fas fa-plus-circle"></i>
          <span>เพิ่มกฎ</span>
        </button>
      </div>
    </form>
  </div>

  <!-- Pricing Rules List -->
  <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
    <div class="bg-gradient-to-r from-pink-500 to-rose-600 px-6 py-4">
      <h2 class="text-xl font-bold text-white flex items-center gap-2">
        <i class="fas fa-list-ul"></i>
        รายการกฎราคาทั้งหมด
      </h2>
    </div>

    <!-- Mobile View -->
    <div class="block lg:hidden">
      <?php if(count($rules) > 0): ?>
        <?php foreach($rules as $r): 
          $dayTypeColor = $r['day_type'] === 'weekday' ? 'blue' : 'orange';
          $dayTypeIcon = $r['day_type'] === 'weekday' ? 'briefcase' : 'umbrella-beach';
          $dayTypeText = $r['day_type'] === 'weekday' ? 'จันทร์-ศุกร์' : 'เสาร์-อาทิตย์';
        ?>
        <div class="border-b border-gray-200 p-5 hover:bg-gray-50 transition-colors">
          <div class="flex justify-between items-start mb-3">
            <div>
              <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold 
                           bg-<?= $dayTypeColor ?>-100 text-<?= $dayTypeColor ?>-700 mb-2">
                <i class="fas fa-<?= $dayTypeIcon ?> mr-2"></i>
                <?= $dayTypeText ?>
              </span>
              <div class="flex items-center gap-2 text-gray-700 mt-2">
                <i class="fas fa-clock text-gray-400"></i>
                <span class="font-semibold"><?= substr($r['start_time'], 0, 5) ?></span>
                <span class="text-gray-400">-</span>
                <span class="font-semibold"><?= substr($r['end_time'], 0, 5) ?></span>
              </div>
            </div>
            <div class="text-right">
              <div class="text-2xl font-bold text-pink-600">฿<?= number_format($r['price_per_hour'], 0) ?></div>
              <div class="text-xs text-gray-500">ต่อชั่วโมง</div>
            </div>
          </div>
          
          <a href="?delete=<?= $r['id'] ?>" 
             onclick="return confirm('ยืนยันลบกฎราคานี้?\n\nวัน: <?= $dayTypeText ?>\nเวลา: <?= substr($r['start_time'], 0, 5) ?> - <?= substr($r['end_time'], 0, 5) ?>\nราคา: ฿<?= number_format($r['price_per_hour'], 0) ?>')"
             class="block w-full px-4 py-2 bg-red-500 text-white rounded-lg font-medium hover:bg-red-600 transition-colors text-center">
            <i class="fas fa-trash mr-2"></i>ลบกฎนี้
          </a>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Desktop View -->
    <div class="hidden lg:block overflow-x-auto">
      <table class="w-full">
        <thead>
          <tr class="bg-gradient-to-r from-gray-700 to-gray-900 text-white">
            <th class="px-6 py-4 text-left font-semibold">
              <i class="fas fa-calendar-day mr-2"></i>ประเภทวัน
            </th>
            <th class="px-6 py-4 text-center font-semibold">
              <i class="fas fa-clock mr-2"></i>ช่วงเวลา
            </th>
            <th class="px-6 py-4 text-right font-semibold">
              <i class="fas fa-money-bill-wave mr-2"></i>ราคาต่อชั่วโมง
            </th>
            <th class="px-6 py-4 text-center font-semibold">
              <i class="fas fa-cog mr-2"></i>จัดการ
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <?php if(count($rules) > 0): ?>
            <?php foreach($rules as $r): 
              $dayTypeColor = $r['day_type'] === 'weekday' ? 'blue' : 'orange';
              $dayTypeIcon = $r['day_type'] === 'weekday' ? 'briefcase' : 'umbrella-beach';
              $dayTypeText = $r['day_type'] === 'weekday' ? 'จันทร์-ศุกร์' : 'เสาร์-อาทิตย์';
            ?>
            <tr class="hover:bg-gray-50 transition-colors">
              <td class="px-6 py-4">
                <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-semibold 
                             bg-<?= $dayTypeColor ?>-100 text-<?= $dayTypeColor ?>-700">
                  <i class="fas fa-<?= $dayTypeIcon ?> mr-2"></i>
                  <?= $dayTypeText ?>
                </span>
              </td>
              <td class="px-6 py-4 text-center">
                <div class="flex items-center justify-center gap-2">
                  <span class="px-3 py-1.5 bg-green-100 text-green-700 rounded-lg font-semibold">
                    <i class="fas fa-play text-xs mr-1"></i>
                    <?= substr($r['start_time'], 0, 5) ?>
                  </span>
                  <i class="fas fa-arrow-right text-gray-400"></i>
                  <span class="px-3 py-1.5 bg-red-100 text-red-700 rounded-lg font-semibold">
                    <i class="fas fa-stop text-xs mr-1"></i>
                    <?= substr($r['end_time'], 0, 5) ?>
                  </span>
                </div>
              </td>
              <td class="px-6 py-4 text-right">
                <div class="inline-flex flex-col items-end">
                  <span class="text-2xl font-bold text-pink-600">฿<?= number_format($r['price_per_hour'], 0) ?></span>
                  <span class="text-xs text-gray-500">ต่อชั่วโมง</span>
                </div>
              </td>
              <td class="px-6 py-4 text-center">
                <a href="?delete=<?= $r['id'] ?>" 
                   onclick="return confirm('ยืนยันลบกฎราคานี้?\n\nวัน: <?= $dayTypeText ?>\nเวลา: <?= substr($r['start_time'], 0, 5) ?> - <?= substr($r['end_time'], 0, 5) ?>\nราคา: ฿<?= number_format($r['price_per_hour'], 0) ?>')"
                   class="px-4 py-2 bg-red-500 text-white rounded-lg text-sm font-medium hover:bg-red-600 
                          hover:shadow-md transform hover:scale-105 transition-all inline-flex items-center gap-2">
                  <i class="fas fa-trash"></i>
                  <span>ลบ</span>
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Empty State -->
    <?php if(count($rules) === 0): ?>
    <div class="p-12 text-center">
      <i class="fas fa-tag text-6xl text-gray-300 mb-4"></i>
      <h3 class="text-2xl font-bold text-gray-800 mb-2">ยังไม่มีกฎราคา</h3>
      <p class="text-gray-600">เริ่มต้นเพิ่มกฎราคาเพื่อกำหนดอัตราค่าเช่าคอร์ตของคุณ</p>
    </div>
    <?php endif; ?>
  </div>

  <!-- Info Card -->
  <div class="mt-6 bg-gradient-to-r from-blue-50 to-purple-50 rounded-2xl shadow-md p-6 border border-blue-200">
    <div class="flex items-start gap-4">
      <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-purple-600 rounded-xl flex items-center justify-center flex-shrink-0">
        <i class="fas fa-info-circle text-white text-xl"></i>
      </div>
      <div>
        <h3 class="font-bold text-gray-800 mb-2">💡 คำแนะนำในการตั้งราคา</h3>
        <ul class="text-sm text-gray-700 space-y-1">
          <li>• <strong>เวลาเช้า (06:00-12:00)</strong> - ราคาปานกลาง เหมาะกับผู้เล่นประจำ</li>
          <li>• <strong>เวลาบ่าย (12:00-16:00)</strong> - ราคาต่ำ ช่วงที่คนเล่นน้อย</li>
          <li>• <strong>เวลาเย็น (16:00-21:00)</strong> - ราคาสูง Peak Hours มีคนเล่นมาก</li>
          <li>• <strong>ราคาวันหยุด</strong> ควรสูงกว่าวันธรรมดา 20-30%</li>
          <li>• <strong>ตรวจสอบ</strong>ให้แน่ใจว่าไม่มีช่วงเวลาซ้อนทับกัน</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
function toggleTimePicker(type) {
    const picker = document.getElementById(type + 'TimePicker');
    picker.classList.toggle('hidden');
    
    // ปิด picker อื่น
    const otherType = type === 'start' ? 'end' : 'start';
    document.getElementById(otherType + 'TimePicker').classList.add('hidden');
}

function selectTime(type, time) {
    document.getElementById(type + 'TimeDisplay').value = time;
    document.getElementById(type + 'TimeInput').value = time;
    document.getElementById(type + 'TimePicker').classList.add('hidden');
}

// ปิด dropdown เมื่อคลิกนอก
document.addEventListener('click', function(event) {
    if (!event.target.closest('[id$="TimeDisplay"]') && !event.target.closest('[id$="TimePicker"]')) {
        document.getElementById('startTimePicker').classList.add('hidden');
        document.getElementById('endTimePicker').classList.add('hidden');
    }
});
</script>
</body>
</html>