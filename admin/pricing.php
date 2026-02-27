<?php
require_once __DIR__ . '/../auth/guard.php';
require_role(['admin']);
require_once __DIR__ . '/../config/db.php';

$success = $error = '';

// ---- สร้างกลุ่มราคา ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_group'])) {
    $groupName = trim($_POST['group_name'] ?? '');
    if (empty($groupName)) {
        $error = 'กรุณากรอกชื่อกลุ่มราคา';
    } else {
        $pdo->prepare('INSERT INTO pricing_groups (name) VALUES (?)')->execute([$groupName]);
        header('Location: pricing.php?group_added=1&tab=group_' . $pdo->lastInsertId()); exit;
    }
}

// ---- ลบกลุ่มราคา ----
if (isset($_GET['delete_group'])) {
    $gid = (int)$_GET['delete_group'];
    $pdo->prepare('UPDATE pricing_rules SET group_id = NULL WHERE group_id = ?')->execute([$gid]);
    $pdo->prepare('UPDATE courts SET pricing_group_id = NULL WHERE pricing_group_id = ?')->execute([$gid]);
    $pdo->prepare('DELETE FROM pricing_groups WHERE id = ?')->execute([$gid]);
    header('Location: pricing.php?group_deleted=1&tab=global'); exit;
}

// ---- เพิ่มกฎราคา ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    $day_type       = in_array($_POST['day_type'] ?? '', ['weekday','weekend']) ? $_POST['day_type'] : '';
    $start_time     = $_POST['start_time'] ?? '';
    $end_time       = $_POST['end_time']   ?? '';
    $price_per_hour = (int)($_POST['price_per_hour'] ?? 0);
    $group_id       = !empty($_POST['group_id']) ? (int)$_POST['group_id'] : null;
    $back_tab       = $_POST['back_tab'] ?? 'global';

    if (!$day_type || !$start_time || !$end_time) {
        $error = 'กรุณากรอกข้อมูลให้ครบถ้วน';
    } elseif ($end_time <= $start_time) {
        $error = 'เวลาสิ้นสุดต้องมากกว่าเวลาเริ่มต้น';
    } elseif ($price_per_hour < 50 || $price_per_hour > 10000) {
        $error = 'ราคาต้องอยู่ระหว่าง 50–10,000 บาท';
    } else {
        try {
            $pdo->prepare('INSERT INTO pricing_rules(group_id,day_type,start_time,end_time,price_per_hour) VALUES(?,?,?,?,?)')
                ->execute([$group_id, $day_type, $start_time, $end_time, $price_per_hour]);
            header("Location: pricing.php?added=1&tab=$back_tab"); exit;
        } catch (PDOException $e) {
            $error = 'เกิดข้อผิดพลาดในการบันทึก';
        }
    }
}

// ---- ลบกฎราคา ----
if (isset($_GET['delete'])) {
    $rid      = (int)$_GET['delete'];
    $back_tab = $_GET['tab'] ?? 'global';
    $pdo->prepare('DELETE FROM pricing_rules WHERE id=?')->execute([$rid]);
    header("Location: pricing.php?deleted=1&tab=$back_tab"); exit;
}

if (isset($_GET['added']))         $success = 'เพิ่มกฎราคาสำเร็จ';
if (isset($_GET['deleted']))       $success = 'ลบกฎราคาสำเร็จ';
if (isset($_GET['group_added']))   $success = 'สร้างกลุ่มราคาสำเร็จ';
if (isset($_GET['group_deleted'])) $success = 'ลบกลุ่มราคาสำเร็จ';

// ---- โหลดข้อมูล ----
$groups = $pdo->query('SELECT * FROM pricing_groups ORDER BY name ASC')->fetchAll();
$rules  = $pdo->query('SELECT * FROM pricing_rules ORDER BY group_id IS NULL DESC, group_id, day_type, start_time')->fetchAll();

$globalRules  = array_values(array_filter($rules, fn($r) => $r['group_id'] === null));
$groupedRules = [];
foreach ($rules as $r) {
    if ($r['group_id'] !== null) $groupedRules[$r['group_id']][] = $r;
}

// stats + courts per group
$courtCountByGroup = [];
$courtsByGroup     = [];
foreach ($groups as $g) {
    $s = $pdo->prepare('SELECT id, court_no, vip_room_name, court_type, is_vip, vip_price, normal_price FROM courts WHERE pricing_group_id = ? ORDER BY court_type DESC, vip_room_name ASC, court_no ASC');
    $s->execute([$g['id']]);
    $rows = $s->fetchAll();
    $courtsByGroup[$g['id']]     = $rows;
    $courtCountByGroup[$g['id']] = count($rows);
}
// courts ที่ใช้กฎ global (ไม่ได้กำหนด pricing_group_id และไม่มีราคาคงที่)
$globalCourtsStmt = $pdo->query('SELECT id, court_no, vip_room_name, court_type, is_vip, vip_price, normal_price FROM courts WHERE pricing_group_id IS NULL ORDER BY court_type DESC, vip_room_name ASC, court_no ASC');
$allNoPricingGroupCourts = $globalCourtsStmt->fetchAll();

$activeTab = htmlspecialchars($_GET['tab'] ?? 'global', ENT_QUOTES);

$timeOptions = [];
for ($h = 6; $h <= 23; $h++) {
    foreach (['00','30'] as $m) $timeOptions[] = sprintf('%02d:%s', $h, $m);
}

// ---- helper: สีราคา ----
function priceColor(float $price): string {
    if ($price <= 150) return '#10b981'; // green
    if ($price <= 300) return '#3b82f6'; // blue
    if ($price <= 500) return '#f59e0b'; // amber
    return '#ef4444'; // red
}

// ---- helper: timeline data ----
// Returns array of [{left%, width%, price, start, end}] for a given set of rules + day_type
function timelineSlots(array $rules, string $day_type): array {
    $DAY_START = 6 * 60;  // 06:00
    $DAY_END   = 23 * 60; // 23:00
    $RANGE     = $DAY_END - $DAY_START;
    $slots = [];
    foreach ($rules as $r) {
        if ($r['day_type'] !== $day_type) continue;
        [$sh, $sm] = explode(':', substr($r['start_time'], 0, 5));
        [$eh, $em] = explode(':', substr($r['end_time'],   0, 5));
        $startMin = (int)$sh * 60 + (int)$sm;
        $endMin   = (int)$eh * 60 + (int)$em;
        $left  = max(0, ($startMin - $DAY_START) / $RANGE * 100);
        $width = max(0, ($endMin - $startMin) / $RANGE * 100);
        $slots[] = [
            'left'  => round($left, 2),
            'width' => round($width, 2),
            'price' => (int)$r['price_per_hour'],
            'start' => substr($r['start_time'], 0, 5),
            'end'   => substr($r['end_time'],   0, 5),
            'color' => priceColor((float)$r['price_per_hour']),
        ];
    }
    return $slots;
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

<div class="max-w-7xl mx-auto px-4 py-6">

  <!-- Header -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
      <h1 style="color:#005691;" class="text-2xl font-bold">ตั้งราคาคอร์ต</h1>
      <p class="text-gray-500 text-sm mt-0.5">
        กำหนดกลุ่มกฎราคาตามช่วงเวลา ·
        <a href="courts.php" style="color:#004A7C;" class="underline">กำหนดให้คอร์ตที่ จัดการคอร์ต</a>
      </p>
    </div>
    <!-- ปุ่มสร้างกลุ่มใหม่ -->
    <button onclick="document.getElementById('newGroupPanel').classList.toggle('hidden')"
            style="background:#005691;"
            class="self-start sm:self-auto px-4 py-2.5 text-white text-sm font-medium rounded-lg hover:opacity-90 transition-opacity flex items-center gap-2">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      สร้างกลุ่มราคาใหม่
    </button>
  </div>

  <!-- Flash -->
  <?php if ($success): ?>
  <div class="mb-4 bg-green-50 border border-green-200 rounded-xl px-4 py-3 text-green-700 text-sm"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
  <div class="mb-4 bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-red-600 text-sm"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- New group panel (collapsible) -->
  <div id="newGroupPanel" class="<?= (isset($_GET['group_added']) || $error) ? '' : 'hidden' ?> mb-5 bg-white rounded-xl border border-gray-200 p-5">
    <p class="text-sm font-semibold text-gray-700 mb-3">ชื่อกลุ่มราคาใหม่</p>
    <form method="post" class="flex gap-2">
      <input type="text" name="group_name" placeholder="เช่น Peak Hours, ราคามาตรฐาน, VIP Premium..." required
             autofocus
             class="flex-1 px-3 py-2.5 border border-gray-300 rounded-lg focus:border-blue-400 outline-none text-sm">
      <button type="submit" name="add_group"
              style="background:#005691;"
              class="px-5 py-2.5 text-white text-sm font-medium rounded-lg hover:opacity-90 transition-opacity whitespace-nowrap">
        + สร้าง
      </button>
      <button type="button" onclick="document.getElementById('newGroupPanel').classList.add('hidden')"
              class="px-4 py-2.5 text-gray-500 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">
        ยกเลิก
      </button>
    </form>
    <p class="text-xs text-gray-400 mt-2">หลังสร้างแล้ว เพิ่มกฎเวลา + ราคาให้กลุ่มได้เลย</p>
  </div>

  <!-- Stats bar -->
  <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
    <div class="bg-white rounded-xl border border-gray-200 px-4 py-3 flex items-center gap-3">
      <div style="background:#EDF4FA;" class="w-9 h-9 rounded-lg flex items-center justify-center">
        <svg class="w-4 h-4" style="color:#005691;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
      </div>
      <div>
        <p class="text-xs text-gray-400">กลุ่มราคา</p>
        <p style="color:#005691;" class="text-xl font-bold leading-tight"><?= count($groups) ?></p>
      </div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 px-4 py-3 flex items-center gap-3">
      <div style="background:#EDF4FA;" class="w-9 h-9 rounded-lg flex items-center justify-center">
        <svg class="w-4 h-4" style="color:#004A7C;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
      </div>
      <div>
        <p class="text-xs text-gray-400">กฎทั้งหมด</p>
        <p class="text-xl font-bold text-gray-800 leading-tight"><?= count($rules) ?></p>
      </div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 px-4 py-3 flex items-center gap-3">
      <div style="background:#EDF4FA;" class="w-9 h-9 rounded-lg flex items-center justify-center">
        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064"/></svg>
      </div>
      <div>
        <p class="text-xs text-gray-400">กฎ Global</p>
        <p class="text-xl font-bold text-gray-800 leading-tight"><?= count($globalRules) ?></p>
      </div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 px-4 py-3 flex items-center gap-3">
      <div style="background:#EDF4FA;" class="w-9 h-9 rounded-lg flex items-center justify-center">
        <svg class="w-4 h-4" style="color:#005691;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      </div>
      <div>
        <p class="text-xs text-gray-400">คอร์ตใช้กลุ่มราคา</p>
        <p class="text-xl font-bold text-gray-800 leading-tight"><?= array_sum($courtCountByGroup) ?></p>
      </div>
    </div>
  </div>

  <!-- Tab bar -->
  <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <!-- Tabs -->
    <div class="border-b border-gray-200 overflow-x-auto">
      <div class="flex min-w-max">
        <!-- Global tab -->
        <button onclick="switchTab('global')" id="tab-btn-global"
                class="tab-btn px-5 py-3.5 text-sm font-medium border-b-2 transition-colors whitespace-nowrap flex items-center gap-2
                       <?= $activeTab === 'global' ? 'border-[#005691] text-[#005691]' : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064"/></svg>
          Global
          <span class="text-xs px-1.5 py-0.5 rounded-full bg-gray-100 text-gray-500"><?= count($globalRules) ?></span>
        </button>
        <!-- Group tabs -->
        <?php foreach ($groups as $g): ?>
        <button onclick="switchTab('group_<?= $g['id'] ?>')" id="tab-btn-group_<?= $g['id'] ?>"
                class="tab-btn px-5 py-3.5 text-sm font-medium border-b-2 transition-colors whitespace-nowrap flex items-center gap-2
                       <?= $activeTab === 'group_'.$g['id'] ? 'border-[#005691] text-[#005691]' : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
          <?= htmlspecialchars($g['name']) ?>
          <span class="text-xs px-1.5 py-0.5 rounded-full bg-gray-100 text-gray-500"><?= count($groupedRules[$g['id']] ?? []) ?></span>
          <?php if ($courtCountByGroup[$g['id']] > 0): ?>
          <span class="text-xs px-1.5 py-0.5 rounded-full" style="background:#EDF4FA;color:#004A7C;"><?= $courtCountByGroup[$g['id'] ] ?> คอร์ต</span>
          <?php endif; ?>
        </button>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Tab contents -->

    <?php
    // Render one tab panel
    function renderTabPanel(string $tabId, ?int $group_id, string $groupName, array $rules, array $timeOptions, array $groups, array $courtCountByGroup, array $tabCourts = []): void {
        $weekdayRules = array_values(array_filter($rules, fn($r) => $r['day_type'] === 'weekday'));
        $weekendRules = array_values(array_filter($rules, fn($r) => $r['day_type'] === 'weekend'));
        $wdSlots = timelineSlots($rules, 'weekday');
        $weSlots = timelineSlots($rules, 'weekend');
        $numCourts = $group_id ? ($courtCountByGroup[$group_id] ?? 0) : 0;
        $panelId = 'panel-' . $tabId;
    ?>
    <div id="<?= $panelId ?>" class="tab-panel <?= /* shown by JS */ '' ?>">
      <div class="p-5 lg:p-6">

        <?php if ($group_id !== null): ?>
        <!-- Group header actions -->
        <div class="flex items-center justify-between mb-5">
          <div>
            <h2 class="text-lg font-semibold text-gray-800"><?= htmlspecialchars($groupName) ?></h2>
            <p class="text-xs text-gray-400 mt-0.5">
              <?= count($rules) ?> กฎราคา
              <?php if ($numCourts > 0): ?>
              · ใช้กับ <strong><?= $numCourts ?></strong> คอร์ต
              <?php else: ?>
              · <span class="text-orange-500">ยังไม่มีคอร์ตใช้กลุ่มนี้</span>
              · <a href="courts.php" style="color:#005691;" class="underline text-xs">ไปกำหนดที่คอร์ต</a>
              <?php endif; ?>
            </p>
          </div>
          <a href="?delete_group=<?= $group_id ?>&tab=global"
             onclick="return confirm('ลบกลุ่ม &quot;<?= htmlspecialchars($groupName, ENT_QUOTES) ?>&quot;?\nกฎจะกลายเป็น Global\nคอร์ตที่ใช้จะถูก reset')"
             class="flex items-center gap-1.5 px-3 py-1.5 bg-red-50 text-red-500 border border-red-200 text-xs rounded-lg hover:bg-red-100 transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            ลบกลุ่มนี้
          </a>
        </div>
        <?php else: ?>
        <div class="mb-5">
          <h2 class="text-lg font-semibold text-gray-800">กฎ Global</h2>
          <p class="text-xs text-gray-400 mt-0.5">ใช้กับคอร์ตที่ไม่ได้กำหนดกลุ่มราคา และไม่มีราคาคงที่</p>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 xl:grid-cols-5 gap-6">

          <!-- Left: Timeline + Rules -->
          <div class="xl:col-span-3 space-y-4">

            <!-- Timeline -->
            <div class="bg-gray-50 rounded-xl border border-gray-200 p-4">
              <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">ภาพรวมช่วงเวลา (06:00–23:00)</p>

              <?php foreach (['weekday' => ['จ.–ศ.', 'bg-blue-50 text-blue-700'], 'weekend' => ['ส.–อ.', 'bg-orange-50 text-orange-700']] as $dt => [$label, $badgeCls]):
                $slots = timelineSlots($rules, $dt);
              ?>
              <div class="mb-3 last:mb-0">
                <div class="flex items-center gap-2 mb-1.5">
                  <span class="text-xs font-medium px-2 py-0.5 rounded-full <?= $badgeCls ?>"><?= $label ?></span>
                  <?php if (count($slots) === 0): ?>
                  <span class="text-xs text-gray-400">ยังไม่มีกฎ</span>
                  <?php endif; ?>
                </div>
                <div class="relative h-8 bg-gray-200 rounded-lg overflow-hidden">
                  <?php foreach ($slots as $slot): ?>
                  <div title="<?= $slot['start'] ?>–<?= $slot['end'] ?> = ฿<?= number_format($slot['price'],0) ?>/ชม."
                       style="position:absolute;left:<?= $slot['left'] ?>%;width:<?= $slot['width'] ?>%;background:<?= $slot['color'] ?>;top:0;bottom:0;"
                       class="flex items-center justify-center overflow-hidden group cursor-default">
                    <?php if ($slot['width'] > 8): ?>
                    <span class="text-white text-xs font-bold drop-shadow" style="font-size:10px;">฿<?= number_format($slot['price'],0) ?></span>
                    <?php endif; ?>
                    <!-- tooltip -->
                    <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 hidden group-hover:block z-10 bg-gray-900 text-white text-xs rounded px-2 py-1 whitespace-nowrap pointer-events-none">
                      <?= $slot['start'] ?>–<?= $slot['end'] ?> · ฿<?= number_format($slot['price'],0) ?>/ชม.
                    </div>
                  </div>
                  <?php endforeach; ?>
                </div>
                <!-- Time labels -->
                <div class="flex justify-between mt-1 px-0.5">
                  <?php foreach (['06', '09', '12', '15', '18', '21', '23'] as $lbl): ?>
                  <span class="text-gray-400" style="font-size:9px;"><?= $lbl ?>:00</span>
                  <?php endforeach; ?>
                </div>
              </div>
              <?php endforeach; ?>

              <!-- Legend -->
              <div class="flex gap-3 mt-3 flex-wrap">
                <?php foreach (['≤150' => '#10b981', '≤300' => '#3b82f6', '≤500' => '#f59e0b', '>500' => '#ef4444'] as $label => $color): ?>
                <div class="flex items-center gap-1">
                  <div class="w-3 h-3 rounded-sm" style="background:<?= $color ?>"></div>
                  <span class="text-xs text-gray-400">฿<?= $label ?></span>
                </div>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- Rules table -->
            <?php if (count($rules) === 0): ?>
            <div class="text-center py-8 text-gray-400">
              <svg class="w-10 h-10 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
              <p class="text-sm font-medium">ยังไม่มีกฎราคา</p>
              <p class="text-xs mt-1">เพิ่มกฎจากฟอร์มด้านขวา</p>
            </div>
            <?php else: ?>
            <div class="rounded-xl border border-gray-200 overflow-hidden">
              <table class="w-full text-sm">
                <thead>
                  <tr class="bg-gray-50 border-b border-gray-100">
                    <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500">วัน</th>
                    <th class="px-4 py-2.5 text-center text-xs font-medium text-gray-500">ช่วงเวลา</th>
                    <th class="px-4 py-2.5 text-right text-xs font-medium text-gray-500">฿/ชม.</th>
                    <th class="px-4 py-2.5 w-12"></th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <?php
                  // weekday first, then weekend
                  $sorted = array_merge(
                      array_values(array_filter($rules, fn($r) => $r['day_type'] === 'weekday')),
                      array_values(array_filter($rules, fn($r) => $r['day_type'] === 'weekend'))
                  );
                  foreach ($sorted as $r):
                    $isWe = $r['day_type'] === 'weekend';
                  ?>
                  <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-2.5">
                      <span class="text-xs px-2 py-0.5 rounded-full font-medium <?= $isWe ? 'bg-orange-100 text-orange-700' : 'bg-blue-100 text-blue-700' ?>">
                        <?= $isWe ? 'เสาร์–อาทิตย์' : 'จันทร์–ศุกร์' ?>
                      </span>
                    </td>
                    <td class="px-4 py-2.5 text-center">
                      <span class="font-mono text-xs text-gray-700"><?= substr($r['start_time'],0,5) ?> – <?= substr($r['end_time'],0,5) ?></span>
                    </td>
                    <td class="px-4 py-2.5 text-right">
                      <span class="font-bold text-sm" style="color:<?= priceColor((float)$r['price_per_hour']) ?>">
                        ฿<?= number_format($r['price_per_hour'],0) ?>
                      </span>
                    </td>
                    <td class="px-4 py-2.5 text-center">
                      <a href="?delete=<?= $r['id'] ?>&tab=<?= $tabId ?>"
                         onclick="return confirm('ลบกฎ <?= $isWe?'เสาร์–อาทิตย์':'จันทร์–ศุกร์' ?> <?= substr($r['start_time'],0,5) ?>–<?= substr($r['end_time'],0,5) ?>?')"
                         class="text-gray-300 hover:text-red-400 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                      </a>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?php endif; ?>

            <!-- Courts using this group/global -->
            <?php
            $hasFixed = array_filter($tabCourts, fn($c) => ($c['vip_price'] > 0 || $c['normal_price'] > 0) && !$group_id);
            $trueGlobal = $group_id === null ? array_values(array_filter($tabCourts, fn($c) => !($c['vip_price'] > 0 || $c['normal_price'] > 0))) : $tabCourts;
            $showCourts = $group_id !== null ? $tabCourts : $tabCourts; // all for group; all no-group for global
            ?>
            <?php if (count($tabCourts) > 0): ?>
            <div class="rounded-xl border border-gray-200 overflow-hidden">
              <div class="px-4 py-2.5 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
                <span class="text-xs font-semibold text-gray-600">
                  <?= $group_id !== null ? 'คอร์ตที่ใช้กลุ่มนี้' : 'คอร์ตที่ไม่มีกลุ่มราคา' ?>
                </span>
                <span class="text-xs text-gray-400"><?= count($tabCourts) ?> คอร์ต</span>
              </div>
              <div class="divide-y divide-gray-50">
                <?php foreach ($tabCourts as $ct):
                    $ctIsVip  = ($ct['court_type'] === 'vip' || $ct['is_vip'] == 1);
                    $ctName   = $ctIsVip ? ($ct['vip_room_name'] ?? 'ห้อง VIP') : 'คอร์ต ' . $ct['court_no'];
                    // ราคาที่จะใช้จริง
                    if ($group_id !== null) {
                        $priceLabel = 'ตามกฎกลุ่ม';
                        $priceCls   = 'text-[#004A7C]';
                    } elseif ($ctIsVip && ($ct['vip_price'] ?? 0) > 0) {
                        $priceLabel = '฿' . number_format($ct['vip_price'], 0) . '/ชม. (คงที่)';
                        $priceCls   = 'text-amber-600';
                    } elseif (!$ctIsVip && ($ct['normal_price'] ?? 0) > 0) {
                        $priceLabel = '฿' . number_format($ct['normal_price'], 0) . '/ชม. (คงที่)';
                        $priceCls   = 'text-amber-600';
                    } else {
                        $priceLabel = 'ตามกฎ Global';
                        $priceCls   = 'text-gray-400';
                    }
                ?>
                <div class="px-4 py-2.5 flex items-center justify-between hover:bg-gray-50 transition-colors">
                  <div class="flex items-center gap-2.5">
                    <?php if ($ctIsVip): ?>
                    <div style="background:#005691;" class="w-7 h-7 rounded flex items-center justify-center text-white font-bold text-xs flex-shrink-0">V</div>
                    <?php else: ?>
                    <div style="background:#E8F1F5;color:#005691;" class="w-7 h-7 rounded flex items-center justify-center font-bold text-xs flex-shrink-0"><?= $ct['court_no'] ?></div>
                    <?php endif; ?>
                    <div>
                      <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($ctName) ?></p>
                      <p class="text-xs text-gray-400"><?= $ctIsVip ? 'ห้อง VIP' : 'คอร์ตปกติ' ?></p>
                    </div>
                  </div>
                  <div class="text-right">
                    <p class="text-xs font-semibold <?= $priceCls ?>"><?= $priceLabel ?></p>
                    <a href="courts.php" class="text-xs text-gray-300 hover:text-[#005691] transition-colors">แก้ไข</a>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
            <?php else: ?>
            <?php if ($group_id !== null): ?>
            <div class="rounded-xl border border-dashed border-gray-200 px-4 py-5 text-center">
              <p class="text-xs text-orange-500 font-medium">ยังไม่มีคอร์ตใช้กลุ่มนี้</p>
              <a href="courts.php" style="color:#005691;" class="text-xs underline mt-1 block">ไปกำหนดที่หน้าจัดการคอร์ต →</a>
            </div>
            <?php endif; ?>
            <?php endif; ?>

          </div><!-- /left -->

          <!-- Right: Add rule form -->
          <div class="xl:col-span-2">
            <div class="bg-white rounded-xl border border-gray-200 p-5 sticky top-20">
              <p class="text-sm font-semibold text-gray-700 mb-4">+ เพิ่มกฎราคา</p>
              <form method="post" class="space-y-3" id="form-<?= $tabId ?>">
                <input type="hidden" name="group_id" value="<?= $group_id ?? '' ?>">
                <input type="hidden" name="back_tab" value="<?= $tabId ?>">

                <!-- Day type -->
                <div>
                  <label class="block text-xs font-medium text-gray-600 mb-1.5">ประเภทวัน</label>
                  <div class="grid grid-cols-2 gap-2">
                    <label class="day-btn flex items-center justify-center gap-2 px-3 py-2.5 border-2 border-blue-200 bg-blue-50 text-blue-700 rounded-lg cursor-pointer text-xs font-medium hover:border-blue-400 transition-colors">
                      <input type="radio" name="day_type" value="weekday" checked class="sr-only">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                      จันทร์–ศุกร์
                    </label>
                    <label class="day-btn flex items-center justify-center gap-2 px-3 py-2.5 border-2 border-gray-200 text-gray-500 rounded-lg cursor-pointer text-xs font-medium hover:border-orange-300 transition-colors">
                      <input type="radio" name="day_type" value="weekend" class="sr-only">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707"/></svg>
                      เสาร์–อาทิตย์
                    </label>
                  </div>
                </div>

                <!-- Time range -->
                <div>
                  <label class="block text-xs font-medium text-gray-600 mb-1.5">ช่วงเวลา</label>
                  <div class="flex items-center gap-2">
                    <div class="flex-1 relative">
                      <input type="text" id="sd-<?= $tabId ?>" readonly value="08:00"
                             onclick="openPicker('<?= $tabId ?>','start')"
                             class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm text-center font-mono cursor-pointer bg-white hover:border-gray-400 outline-none">
                      <input type="hidden" name="start_time" id="sv-<?= $tabId ?>" value="08:00">
                    </div>
                    <span class="text-gray-400 text-sm">–</span>
                    <div class="flex-1 relative">
                      <input type="text" id="ed-<?= $tabId ?>" readonly value="12:00"
                             onclick="openPicker('<?= $tabId ?>','end')"
                             class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm text-center font-mono cursor-pointer bg-white hover:border-gray-400 outline-none">
                      <input type="hidden" name="end_time" id="ev-<?= $tabId ?>" value="12:00">
                    </div>
                  </div>
                </div>

                <!-- Price -->
                <div>
                  <label class="block text-xs font-medium text-gray-600 mb-1.5">ราคา (฿/ชม.)</label>
                  <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-medium">฿</span>
                    <input type="number" name="price_per_hour" required min="50" max="10000" step="10" value="400"
                           class="w-full pl-7 pr-16 py-2.5 border border-gray-300 rounded-lg focus:border-blue-400 outline-none text-sm font-mono">
                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs">/ชม.</span>
                  </div>
                  <!-- Quick price buttons -->
                  <div class="flex gap-1.5 mt-2 flex-wrap">
                    <?php foreach ([150, 200, 300, 400, 500, 600] as $qp): ?>
                    <button type="button" onclick="setPrice(this,'<?= $tabId ?>')" data-price="<?= $qp ?>"
                            class="px-2.5 py-1 text-xs rounded-md border border-gray-200 text-gray-500 hover:border-[#005691] hover:text-[#005691] transition-colors">
                      <?= $qp ?>
                    </button>
                    <?php endforeach; ?>
                  </div>
                </div>

                <button type="submit" name="add"
                        style="background:#004A7C;"
                        class="w-full py-2.5 text-white text-sm font-medium rounded-lg hover:opacity-90 transition-opacity">
                  เพิ่มกฎนี้
                </button>
              </form>
            </div>
          </div><!-- /right -->

        </div><!-- /grid -->
      </div><!-- /p-5 -->
    </div><!-- /panel -->
    <?php } // end renderTabPanel ?>

    <?php
    // Render Global tab
    renderTabPanel('global', null, 'Global', $globalRules, $timeOptions, $groups, $courtCountByGroup, $allNoPricingGroupCourts);
    // Render each group tab
    foreach ($groups as $g) {
        renderTabPanel('group_'.$g['id'], (int)$g['id'], $g['name'], $groupedRules[$g['id']] ?? [], $timeOptions, $groups, $courtCountByGroup, $courtsByGroup[$g['id']] ?? []);
    }
    ?>

  </div><!-- /card -->

  <!-- Time picker modal (shared) -->
  <div id="pickerModal" class="hidden fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4" onclick="closePicker(event)">
    <div class="absolute inset-0 bg-black/30"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-xs" onclick="event.stopPropagation()">
      <div id="pickerHeader" class="px-4 py-3 rounded-t-2xl flex justify-between items-center">
        <span id="pickerTitle" class="text-white text-sm font-semibold"></span>
        <button onclick="closePicker()" class="text-white/80 hover:text-white">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <div class="grid grid-cols-4 gap-1 p-3 max-h-64 overflow-y-auto" id="pickerGrid">
        <?php foreach ($timeOptions as $t): ?>
        <button type="button" data-time="<?= $t ?>"
                onclick="selectTime('<?= $t ?>')"
                class="modal-time-btn py-2 text-xs text-center rounded-lg border border-gray-100 font-mono font-medium hover:bg-blue-50 hover:border-blue-300 transition-colors">
          <?= $t ?>
        </button>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

</div><!-- /container -->

<?php include __DIR__ . '/../includes/footer.php'; ?>

<style>
.tab-btn { outline: none; }
.tab-panel { display: none; }
.tab-panel.active { display: block; }
.day-btn input[type=radio]:checked + svg { display: block; }
</style>

<script>
// ---- Tab switching ----
const ACTIVE_TAB = '<?= $activeTab ?>';
function switchTab(id) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => {
        b.classList.remove('border-[#005691]', 'text-[#005691]');
        b.classList.add('border-transparent', 'text-gray-500');
    });
    var panel = document.getElementById('panel-' + id);
    var btn   = document.getElementById('tab-btn-' + id);
    if (panel) panel.classList.add('active');
    if (btn)   { btn.classList.add('border-[#005691]', 'text-[#005691]'); btn.classList.remove('border-transparent','text-gray-500'); }
    // Update URL hash
    history.replaceState(null, '', '?tab=' + id);
}
document.addEventListener('DOMContentLoaded', function() {
    switchTab(ACTIVE_TAB);
});

// ---- Day type radio styling ----
document.querySelectorAll('.day-btn input[type=radio]').forEach(function(radio) {
    radio.addEventListener('change', function() {
        var form = this.closest('form');
        form.querySelectorAll('.day-btn').forEach(function(lbl) {
            var isWeekend = lbl.querySelector('input').value === 'weekend';
            lbl.classList.remove('border-blue-200','bg-blue-50','text-blue-700','border-orange-300','bg-orange-50','text-orange-700','border-gray-200','text-gray-500');
            if (lbl.querySelector('input').checked) {
                if (isWeekend) lbl.classList.add('border-orange-300','bg-orange-50','text-orange-700');
                else           lbl.classList.add('border-blue-200','bg-blue-50','text-blue-700');
            } else {
                lbl.classList.add('border-gray-200','text-gray-500');
            }
        });
    });
});

// ---- Quick price buttons ----
function setPrice(btn, tabId) {
    var form = document.getElementById('form-' + tabId);
    if (form) form.querySelector('input[name=price_per_hour]').value = btn.dataset.price;
}

// ---- Time picker (modal) ----
var _pickerTabId  = null;
var _pickerType   = null;

function openPicker(tabId, type) {
    _pickerTabId = tabId;
    _pickerType  = type;
    var modal  = document.getElementById('pickerModal');
    var header = document.getElementById('pickerHeader');
    var title  = document.getElementById('pickerTitle');
    var curVal = document.getElementById((type==='start'?'sd-':'ed-') + tabId).value;

    title.textContent = type === 'start' ? 'เลือกเวลาเริ่ม' : 'เลือกเวลาสิ้นสุด';
    header.style.background = type === 'start' ? '#005691' : '#004A7C';

    // Highlight current value
    document.querySelectorAll('.modal-time-btn').forEach(function(b) {
        b.classList.toggle('bg-[#005691]', b.dataset.time === curVal);
        b.classList.toggle('text-white',   b.dataset.time === curVal);
        b.classList.toggle('border-[#005691]', b.dataset.time === curVal);
    });
    modal.classList.remove('hidden');
}

function selectTime(time) {
    if (!_pickerTabId || !_pickerType) return;
    var dispId = (_pickerType === 'start' ? 'sd-' : 'ed-') + _pickerTabId;
    var valId  = (_pickerType === 'start' ? 'sv-' : 'ev-') + _pickerTabId;
    document.getElementById(dispId).value = time;
    document.getElementById(valId).value  = time;
    document.getElementById('pickerModal').classList.add('hidden');
}

function closePicker(e) {
    if (!e || e.target === document.getElementById('pickerModal') || !e.target) {
        document.getElementById('pickerModal').classList.add('hidden');
    }
}
</script>
</body>
</html>
