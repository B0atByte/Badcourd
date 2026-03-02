<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../config/db.php';

$today = date('Y-m-d');
$date = $_GET['date'] ?? $today;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date))
    $date = $today;

$isAdmin = ($_SESSION['user']['role'] ?? '') === 'admin';

// ดึงคลาสของวันที่เลือก
$coursesStmt = $pdo->prepare("
    SELECT yc.*,
           COUNT(yb.id) AS booked_count
    FROM yoga_courses yc
    LEFT JOIN yoga_bookings yb ON yb.yoga_course_id = yc.id AND yb.status != 'cancelled'
    WHERE yc.course_date = ?
    GROUP BY yc.id
    ORDER BY yc.start_time ASC
");
$coursesStmt->execute([$date]);
$courses = $coursesStmt->fetchAll();

// เดิน prev/next
$prevDate = date('Y-m-d', strtotime($date . ' -1 day'));
$nextDate = date('Y-m-d', strtotime($date . ' +1 day'));

// thai date display
$thaiMonths = [
    1 => 'ม.ค.',
    2 => 'ก.พ.',
    3 => 'มี.ค.',
    4 => 'เม.ย.',
    5 => 'พ.ค.',
    6 => 'มิ.ย.',
    7 => 'ก.ค.',
    8 => 'ส.ค.',
    9 => 'ก.ย.',
    10 => 'ต.ค.',
    11 => 'พ.ย.',
    12 => 'ธ.ค.'
];
$thaiDays = ['อา.', 'จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.'];
$dt = new DateTime($date);
$thaiDateStr = $thaiDays[(int) $dt->format('w')] . ' ' . $dt->format('d') . ' ' . $thaiMonths[(int) $dt->format('n')] . ' ' . ($dt->format('Y') + 543);
$isToday = ($date === $today);
?>
<!doctype html>
<html lang="th">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <title>ตารางโยคะ – BARGAIN SPORT</title>
    <style>
        * {
            font-family: 'Prompt', sans-serif !important;
        }
    </style>
</head>

<body style="background:#f8fafc;" class="min-h-screen">
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="max-w-4xl mx-auto px-4 py-8">

        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
            <div>
                <h1 class="text-2xl font-bold" style="color:#005691;">ตารางคลาสโยคะ 🧘</h1>
                <p class="text-gray-500 text-sm mt-0.5">ดูตารางเรียนและที่นั่งว่าง</p>
            </div>
            <?php if ($isAdmin): ?>
                <a href="/admin/yoga_classes.php?date=<?= $date ?>"
                    class="flex items-center gap-1.5 px-4 py-2 text-sm text-white rounded-lg font-medium hover:opacity-90 transition-opacity"
                    style="background:#005691;">
                    ⚙️ จัดการคลาส (Admin)
                </a>
            <?php endif; ?>
        </div>

        <!-- Date Nav -->
        <div class="bg-white rounded-xl border border-gray-200 p-4 mb-5 flex items-center justify-between">
            <a href="?date=<?= $prevDate ?>"
                class="flex items-center gap-1 px-3 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-100 transition-colors font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                ก่อนหน้า
            </a>

            <div class="text-center">
                <div class="flex items-center gap-2 justify-center">
                    <p class="font-semibold text-gray-800">
                        <?= $thaiDateStr ?>
                    </p>
                    <?php if ($isToday): ?>
                        <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700 font-medium">วันนี้</span>
                    <?php endif; ?>
                </div>
                <form method="get" class="mt-1">
                    <input type="date" name="date" value="<?= $date ?>" onchange="this.form.submit()"
                        class="text-xs text-gray-400 border-0 outline-none bg-transparent cursor-pointer hover:text-gray-600">
                </form>
            </div>

            <a href="?date=<?= $nextDate ?>"
                class="flex items-center gap-1 px-3 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-100 transition-colors font-medium">
                ถัดไป
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </a>
        </div>

        <!-- Classes -->
        <?php if (empty($courses)): ?>
            <div class="bg-white rounded-xl border border-gray-200 p-16 text-center">
                <div class="text-5xl mb-4">🧘</div>
                <h3 class="text-xl font-bold text-gray-700 mb-2">ไม่มีคลาสในวันนี้</h3>
                <p class="text-gray-400 text-sm">ลองเลือกวันอื่น หรือติดต่อสอบถามเจ้าหน้าที่</p>
                <a href="?date=<?= $today ?>"
                    class="inline-block mt-5 px-5 py-2 text-sm text-white rounded-lg hover:opacity-90 transition-opacity"
                    style="background:#005691;">กลับวันนี้</a>
            </div>

        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($courses as $c):
                    $remaining = max(0, $c['max_students'] - $c['booked_count']);
                    $pct = $c['max_students'] > 0 ? round($c['booked_count'] / $c['max_students'] * 100) : 0;
                    $isFull = $remaining <= 0;

                    $roomColors = [
                        'ห้องร่วม' => ['bg' => '#EBF4FA', 'text' => '#005691', 'border' => '#B8D8EE'],
                        'ห้องเล็ก' => ['bg' => '#F0FDF4', 'text' => '#15803d', 'border' => '#86efac'],
                        'ห้องใหญ่' => ['bg' => '#FEF9C3', 'text' => '#854d0e', 'border' => '#fde047'],
                    ];
                    $rc = $roomColors[$c['room']] ?? ['bg' => '#F3F4F6', 'text' => '#374151', 'border' => '#D1D5DB'];
                    ?>
                    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden hover:shadow-md transition-shadow">
                        <div class="flex items-stretch">

                            <!-- Color bar -->
                            <div class="w-1.5 flex-shrink-0" style="background:<?= $rc['text'] ?>;"></div>

                            <div class="flex-1 p-5">
                                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">

                                    <!-- Left: Info -->
                                    <div class="flex-1">
                                        <!-- Time & Room -->
                                        <div class="flex flex-wrap items-center gap-2 mb-2">
                                            <span class="text-lg font-bold text-gray-800">
                                                <?= substr($c['start_time'], 0, 5) ?> –
                                                <?= substr($c['end_time'], 0, 5) ?>
                                            </span>
                                            <span class="text-xs px-2.5 py-1 rounded-full font-medium"
                                                style="background:<?= $rc['bg'] ?>;color:<?= $rc['text'] ?>;border:1px solid <?= $rc['border'] ?>;">
                                                <?= htmlspecialchars($c['room']) ?>
                                            </span>
                                            <?php if ($isFull): ?>
                                                <span
                                                    class="text-xs px-2.5 py-1 rounded-full bg-red-100 text-red-700 font-medium">เต็มแล้ว</span>
                                            <?php elseif ($remaining <= 3): ?>
                                                <span
                                                    class="text-xs px-2.5 py-1 rounded-full bg-amber-100 text-amber-700 font-medium">เหลือไม่มาก!</span>
                                            <?php else: ?>
                                                <span
                                                    class="text-xs px-2.5 py-1 rounded-full bg-green-100 text-green-700 font-medium">ว่าง</span>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Instructor -->
                                        <div class="flex items-center gap-1.5 text-sm text-gray-600 mb-3">
                                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                            </svg>
                                            <span class="font-medium">อ.
                                                <?= htmlspecialchars($c['instructor']) ?>
                                            </span>
                                        </div>

                                        <?php if ($c['notes']): ?>
                                            <p class="text-xs text-gray-500 bg-gray-50 rounded-lg px-3 py-2">
                                                📝
                                                <?= htmlspecialchars($c['notes']) ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Right: Capacity -->
                                    <div class="sm:text-right sm:min-w-[140px]">
                                        <p class="text-xs text-gray-400 mb-1">ที่นั่ง</p>
                                        <p class="text-2xl font-bold <?= $isFull ? 'text-red-500' : 'text-gray-800' ?>">
                                            <?= $remaining ?>
                                            <span class="text-sm font-normal text-gray-400">/
                                                <?= $c['max_students'] ?>
                                            </span>
                                        </p>
                                        <!-- Progress bar -->
                                        <div class="w-full sm:w-36 h-1.5 bg-gray-200 rounded-full mt-2 overflow-hidden">
                                            <div class="h-full rounded-full transition-all"
                                                style="width:<?= $pct ?>%;background:<?= $pct >= 100 ? '#ef4444' : ($pct >= 70 ? '#f59e0b' : $rc['text']) ?>;">
                                            </div>
                                        </div>
                                        <p class="text-xs text-gray-400 mt-1">ลงทะเบียนแล้ว
                                            <?= $c['booked_count'] ?> คน
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Summary -->
            <div
                class="mt-5 flex items-center justify-between text-sm text-gray-500 bg-white rounded-xl border border-gray-200 px-5 py-3">
                <span>คลาสทั้งหมด <strong class="text-gray-700">
                        <?= count($courses) ?>
                    </strong> คลาส</span>
                <?php
                $totalSlots = array_sum(array_column($courses, 'max_students'));
                $totalBooked = array_sum(array_column($courses, 'booked_count'));
                ?>
                <span>ที่นั่งว่างรวม <strong style="color:#005691;">
                        <?= max(0, $totalSlots - $totalBooked) ?>
                    </strong> /
                    <?= $totalSlots ?>
                </span>
            </div>
        <?php endif; ?>

    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>

</html>