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
            'id'       => $user['id'],
            'username' => $user['username'],
            'name'     => $user['username'],
            'role'     => $user['role'],
        ];
        header('Location: /timetable_detail.php');
        exit;
    } else {
        $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    }
}

// ---- Timetable data (today) ----
$date  = date('Y-m-d');
$courts = $pdo->query('SELECT * FROM courts ORDER BY court_type DESC, vip_room_name ASC, court_no ASC')->fetchAll();
$vipCourts    = array_filter($courts, fn($c) => $c['court_type'] === 'vip' || $c['is_vip'] == 1);
$normalCourts = array_filter($courts, fn($c) => !($c['court_type'] === 'vip' || $c['is_vip'] == 1));

$stmt = $pdo->prepare("
    SELECT b.*, c.court_no, c.court_type, c.vip_room_name, c.is_vip
    FROM bookings b JOIN courts c ON b.court_id = c.id
    WHERE b.status = 'booked' AND b.start_datetime BETWEEN ? AND ?
    ORDER BY c.court_type DESC, c.vip_room_name ASC, c.court_no, b.start_datetime
");
$stmt->execute([$date . ' 00:00:00', $date . ' 23:59:59']);
$bookings = $stmt->fetchAll();

$grid        = [];
$gridDetails = [];
foreach ($courts as $c) {
    $grid[$c['id']]        = array_fill(0, 48, '');
    $gridDetails[$c['id']] = array_fill(0, 48, null);
}
foreach ($bookings as $b) {
    $s         = new DateTime($b['start_datetime']);
    $startSlot = (int)$s->format('G') * 2 + ((int)$s->format('i') >= 30 ? 1 : 0);
    $numSlots  = max(1, (int)$b['duration_hours'] * 2);
    for ($i = 0; $i < $numSlots; $i++) {
        $slot = $startSlot + $i;
        if ($slot >= 0 && $slot < 48) {
            $grid[$b['court_id']][$slot]        = 'booked';
            $gridDetails[$b['court_id']][$slot] = $b;
        }
    }
}

$dateObj    = new DateTime($date);
$thaiMonths = [1=>'มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
$thaiDays   = ['อาทิตย์','จันทร์','อังคาร','พุธ','พฤหัสบดี','ศุกร์','เสาร์'];
$thaiDate   = 'วัน'.$thaiDays[(int)$dateObj->format('w')].' ที่ '.$dateObj->format('d').' '.$thaiMonths[(int)$dateObj->format('n')].' '.($dateObj->format('Y')+543);

function loginGetCourtName(array $c): string {
    if ($c['court_type'] === 'vip' || $c['is_vip'] == 1) return $c['vip_room_name'] ?? 'ห้อง VIP';
    return 'คอร์ต ' . $c['court_no'];
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
        * { font-family: 'Prompt', sans-serif; }
        .bk-item {
            border-radius: 6px; padding: 5px 10px; margin-bottom: 4px;
            font-size: 12px; display: flex; justify-content: space-between; align-items: center;
        }
        .bk-booked { background:#004A7C; color:#fff; }
        .bk-vip    { background:#005691; color:#fff; }
        .bk-free   { background:#E8F1F5; color:#888; }
        .court-card { background:#fff; border-radius:10px; padding:12px; border:1px solid #e5e7eb; }
    </style>
</head>
<body style="background:#FAFAFA;" class="min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-sm">

        <!-- Logo & Title -->
        <div class="text-center mb-8">
            <img src="/logo/BPL.png" alt="BPL Logo"
                 class="w-16 h-16 object-contain mx-auto mb-4 rounded-xl shadow">
            <h1 style="color:#005691;" class="text-2xl font-bold">BARGAIN SPORT</h1>
            <p class="text-gray-500 text-sm mt-1">ระบบจองคอร์ตแบดมินตัน</p>
        </div>

        <!-- Login Card -->
        <div class="bg-white rounded-xl shadow p-7 border border-gray-200">

            <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-5 text-sm">
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form method="post" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">ชื่อผู้ใช้</label>
                    <input type="text" name="username" required autofocus placeholder="กรอกชื่อผู้ใช้"
                           class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:border-[#005691] focus:ring-2 focus:ring-[#005691]/20 outline-none transition-all text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">รหัสผ่าน</label>
                    <input type="password" name="password" required placeholder="กรอกรหัสผ่าน"
                           class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:border-[#005691] focus:ring-2 focus:ring-[#005691]/20 outline-none transition-all text-sm">
                </div>
                <button type="submit"
                        style="background:#FF0000;"
                        class="w-full py-2.5 text-white rounded-lg font-medium hover:opacity-90 transition-opacity text-sm mt-2">
                    เข้าสู่ระบบ
                </button>
            </form>
        </div>

        <!-- ปุ่มดูตาราง -->
        <div class="mt-4 text-center">
            <button onclick="document.getElementById('timetableModal').classList.remove('hidden')"
                    class="w-full py-2.5 border border-gray-300 bg-white text-gray-600 rounded-xl text-sm font-medium hover:border-[#005691] hover:text-[#005691] transition-colors">
                ดูตารางการจองวันนี้
            </button>
        </div>

        <p class="text-center mt-5 text-gray-400 text-xs">© <?= date('Y') ?> Boat Patthanapong</p>
    </div>

    <!-- ===== Timetable Modal ===== -->
    <div id="timetableModal"
         class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-3"
         onclick="if(event.target===this)this.classList.add('hidden')">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[90vh] flex flex-col overflow-hidden">

            <!-- Modal header -->
            <div style="background:#005691;" class="px-5 py-4 flex items-center justify-between flex-shrink-0">
                <div>
                    <p class="text-white font-semibold">ตารางการจองวันนี้</p>
                    <p class="text-blue-200 text-xs mt-0.5"><?= $thaiDate ?> &nbsp;&middot;&nbsp; <?= count($bookings) ?> รายการ</p>
                </div>
                <button onclick="document.getElementById('timetableModal').classList.add('hidden')"
                        class="text-white/70 hover:text-white text-2xl leading-none">&times;</button>
            </div>

            <!-- Legend -->
            <div class="px-5 py-2 border-b border-gray-100 flex gap-5 flex-shrink-0 bg-gray-50">
                <div class="flex items-center gap-1.5 text-xs text-gray-500">
                    <div class="w-3 h-3 rounded" style="background:#E8F1F5;border:1px solid #d1d5db;"></div>ว่าง
                </div>
                <div class="flex items-center gap-1.5 text-xs text-gray-500">
                    <div class="w-3 h-3 rounded" style="background:#004A7C;"></div>จองแล้ว (ปกติ)
                </div>
                <div class="flex items-center gap-1.5 text-xs text-gray-500">
                    <div class="w-3 h-3 rounded" style="background:#005691;"></div>จองแล้ว (VIP)
                </div>
            </div>

            <!-- Scrollable content -->
            <div class="flex-1 overflow-y-auto px-5 py-4 space-y-5">

                <?php if (!empty($vipCourts)): ?>
                <div>
                    <h3 class="text-xs font-semibold mb-2" style="color:#004A7C;">ห้อง VIP</h3>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        <?php foreach ($vipCourts as $c):
                            $name = loginGetCourtName($c);
                            $displayed = [];
                        ?>
                        <div class="court-card">
                            <div class="text-xs font-semibold mb-2 pb-1.5 border-b border-gray-100 flex items-center gap-1.5 truncate" style="color:#005691;">
                                <span style="background:#005691;" class="w-4 h-4 rounded flex items-center justify-center text-white font-bold text-xs flex-shrink-0">V</span>
                                <span class="truncate"><?= htmlspecialchars($name) ?></span>
                            </div>
                            <?php
                            for ($slot = 0; $slot < 48; $slot++):
                                $det = $gridDetails[$c['id']][$slot];
                                if ($det && !in_array($det['id'], $displayed)):
                                    $displayed[] = $det['id'];
                                    $sd = new DateTime($det['start_datetime']);
                                    $ed = (clone $sd)->modify('+' . (int)$det['duration_hours'] . ' hour');
                            ?>
                            <div class="bk-item bk-vip">
                                <span><?= $sd->format('H:i') ?>–<?= $ed->format('H:i') ?></span>
                                <span class="opacity-70"><?= $det['duration_hours'] ?>ชม.</span>
                            </div>
                            <?php endif; endfor; ?>
                            <?php if (empty($displayed)): ?>
                            <div class="bk-item bk-free">ว่าง</div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($normalCourts)): ?>
                <div>
                    <h3 class="text-xs font-semibold mb-2" style="color:#005691;">คอร์ตปกติ</h3>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        <?php foreach ($normalCourts as $c):
                            $name = loginGetCourtName($c);
                            $displayed = [];
                        ?>
                        <div class="court-card">
                            <div class="text-xs font-semibold mb-2 pb-1.5 border-b border-gray-100 flex items-center gap-1.5" style="color:#005691;">
                                <span style="background:#E8F1F5;color:#005691;" class="w-4 h-4 rounded flex items-center justify-center font-bold text-xs flex-shrink-0"><?= $c['court_no'] ?></span>
                                <span class="truncate"><?= htmlspecialchars($name) ?></span>
                            </div>
                            <?php
                            for ($slot = 0; $slot < 48; $slot++):
                                $det = $gridDetails[$c['id']][$slot];
                                if ($det && !in_array($det['id'], $displayed)):
                                    $displayed[] = $det['id'];
                                    $sd = new DateTime($det['start_datetime']);
                                    $ed = (clone $sd)->modify('+' . (int)$det['duration_hours'] . ' hour');
                            ?>
                            <div class="bk-item bk-booked">
                                <span><?= $sd->format('H:i') ?>–<?= $ed->format('H:i') ?></span>
                                <span class="opacity-70"><?= $det['duration_hours'] ?>ชม.</span>
                            </div>
                            <?php endif; endfor; ?>
                            <?php if (empty($displayed)): ?>
                            <div class="bk-item bk-free">ว่าง</div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

            </div>

            <!-- Modal footer -->
            <div class="px-5 py-3 border-t border-gray-100 flex justify-between items-center flex-shrink-0 bg-gray-50">
                <a href="/timetable.php" class="text-xs" style="color:#005691;">ดูตารางแบบเต็ม</a>
                <button onclick="document.getElementById('timetableModal').classList.add('hidden')"
                        class="px-4 py-2 bg-gray-100 text-gray-600 text-sm rounded-lg hover:bg-gray-200 transition-colors">
                    ปิด
                </button>
            </div>
        </div>
    </div>

</body>
</html>
