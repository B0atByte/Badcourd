<?php
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: /auth/login.php'); exit;
}

$today   = date('Y-m-d');
$date    = $_GET['date'] ?? $today;
// validate date
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = $today;

$success = $error = '';
$adminId = $_SESSION['user']['id'];

// ──────────────────────────────────────────────────────────
// POST handlers
// ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── สร้างคลาสใหม่ ──────────────────────────────────────
    if ($action === 'create_course') {
        $cdate   = $_POST['course_date'] ?? $date;
        $stime   = $_POST['start_time']  ?? '';
        $etime   = $_POST['end_time']    ?? '';
        $room    = $_POST['room']        ?? 'ห้องร่วม';
        $instr   = trim($_POST['instructor'] ?? '');
        $maxStu  = max(1, (int)($_POST['max_students'] ?? 15));
        $notes   = trim($_POST['notes']  ?? '');

        $validRooms = ['ห้องร่วม','ห้องเล็ก','ห้องใหญ่'];
        // strip tags เพื่อความปลอดภัย ไม่ validate ด้วย in_array เพราะ encoding
        $room = strip_tags($room);
        if (!$room) $room = 'ห้องร่วม';

        if (empty($stime) || empty($etime) || empty($instr)) {
            $error = 'กรุณากรอกข้อมูลให้ครบ (เวลา, ครูผู้สอน)';
        } elseif ($etime <= $stime) {
            $error = 'เวลาสิ้นสุดต้องมากกว่าเวลาเริ่มต้น';
        } else {
            $stmt = $pdo->prepare("INSERT INTO yoga_courses (course_date, start_time, end_time, room, instructor, max_students, notes, created_by) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute([$cdate, $stime, $etime, $room, $instr, $maxStu, $notes ?: null, $adminId]);
            $success = 'สร้างคลาสเรียบร้อยแล้ว';
            $date = $cdate;
        }
    }

    // ── เพิ่มนักเรียนเข้าคลาส ──────────────────────────────
    elseif ($action === 'add_student') {
        $courseId   = (int)$_POST['course_id'];
        $stuName    = trim($_POST['student_name'] ?? '');
        $stuPhone   = trim($_POST['student_phone'] ?? '');
        $pkgId      = (int)($_POST['member_package_id'] ?? 0) ?: null;

        if (empty($stuName) || empty($stuPhone)) {
            $error = 'กรุณากรอกชื่อและเบอร์โทร';
        } else {
            // ตรวจว่าซ้ำไหม
            $dup = $pdo->prepare("SELECT id FROM yoga_bookings WHERE yoga_course_id=? AND student_phone=? AND status != 'cancelled'");
            $dup->execute([$courseId, $stuPhone]);
            if ($dup->fetch()) {
                $error = 'นักเรียนคนนี้อยู่ในคลาสนี้แล้ว';
            } else {
                // ตรวจว่าคลาสเต็มไหม
                $full = $pdo->prepare("SELECT COUNT(*) FROM yoga_bookings WHERE yoga_course_id=? AND status != 'cancelled'");
                $full->execute([$courseId]);
                $enrolled = (int)$full->fetchColumn();
                $maxRow = $pdo->prepare("SELECT max_students FROM yoga_courses WHERE id=?");
                $maxRow->execute([$courseId]);
                $maxStu = (int)$maxRow->fetchColumn();
                if ($enrolled >= $maxStu) {
                    $error = 'คลาสเต็มแล้ว (' . $maxStu . ' คน)';
                } elseif ($pkgId && $pkgId > 0) {
                    // ตรวจแพ็กเกจยังมีครั้งเหลือไหม
                    $pkgRow = $pdo->prepare("SELECT sessions_total - sessions_used AS remaining, expiry_date FROM member_yoga_packages WHERE id=? AND student_phone=?");
                    $pkgRow->execute([$pkgId, $stuPhone]);
                    $pkg = $pkgRow->fetch();
                    if (!$pkg || $pkg['remaining'] <= 0) {
                        $error = 'แพ็กเกจนี้ไม่มีครั้งเหลือแล้ว';
                    } elseif ($pkg['expiry_date'] && $pkg['expiry_date'] < $today) {
                        $error = 'แพ็กเกจนี้หมดอายุแล้ว';
                    } else {
                        $pdo->beginTransaction();
                        $ins = $pdo->prepare("INSERT INTO yoga_bookings (yoga_course_id, student_name, student_phone, member_package_id, status, created_by) VALUES (?,?,?,?,'booked',?)");
                        $ins->execute([$courseId, $stuName, $stuPhone, $pkgId, $adminId]);
                        $pdo->commit();
                        $success = 'เพิ่ม ' . $stuName . ' เข้าคลาสแล้ว (ยังไม่หักครั้ง จนกว่าจะเช็ค)';
                    }
                } else {
                    // ไม่ใช้แพ็กเกจ
                    $ins = $pdo->prepare("INSERT INTO yoga_bookings (yoga_course_id, student_name, student_phone, member_package_id, status, created_by) VALUES (?,?,?,NULL,'booked',?)");
                    $ins->execute([$courseId, $stuName, $stuPhone, $adminId]);
                    $success = 'เพิ่มนักเรียน (ไม่ใช้แพ็กเกจ)';
                }
            }
        }
    }

    // ── เช็คชื่อ (attended) + หักครั้ง ─────────────────────
    elseif ($action === 'attend') {
        $bookId = (int)$_POST['booking_id'];
        try {
            $pdo->beginTransaction();
            $bk = $pdo->prepare("SELECT * FROM yoga_bookings WHERE id=?");
            $bk->execute([$bookId]);
            $bkRow = $bk->fetch();
            if (!$bkRow) throw new Exception('ไม่พบข้อมูลการจอง');
            if ($bkRow['status'] === 'attended') throw new Exception('เช็คชื่อแล้ว');

            // หักครั้งจากแพ็กเกจ
            if ($bkRow['member_package_id']) {
                $upd = $pdo->prepare("UPDATE member_yoga_packages SET sessions_used = sessions_used + 1 WHERE id=? AND (sessions_total - sessions_used) > 0");
                $upd->execute([$bkRow['member_package_id']]);
                if ($upd->rowCount() === 0) throw new Exception('แพ็กเกจไม่มีครั้งเหลือ');
            }
            $pdo->prepare("UPDATE yoga_bookings SET status='attended', attended_at=NOW() WHERE id=?")->execute([$bookId]);
            $pdo->commit();
            $success = 'เช็คชื่อสำเร็จ';
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }

    // ── ยกเลิกการจอง ─────────────────────────────────────
    elseif ($action === 'cancel_booking') {
        $bookId = (int)$_POST['booking_id'];
        $bk = $pdo->prepare("SELECT * FROM yoga_bookings WHERE id=?");
        $bk->execute([$bookId]);
        $bkRow = $bk->fetch();
        if ($bkRow) {
            // ถ้าเช็คแล้ว คืนครั้ง
            if ($bkRow['status'] === 'attended' && $bkRow['member_package_id']) {
                $pdo->prepare("UPDATE member_yoga_packages SET sessions_used = GREATEST(0, sessions_used-1) WHERE id=?")->execute([$bkRow['member_package_id']]);
            }
            $pdo->prepare("UPDATE yoga_bookings SET status='cancelled' WHERE id=?")->execute([$bookId]);
            $success = 'ยกเลิกการจองแล้ว';
        }
    }

    // ── ลบคลาส ──────────────────────────────────────────
    elseif ($action === 'delete_course') {
        $courseId = (int)$_POST['course_id'];
        $pdo->prepare("DELETE FROM yoga_courses WHERE id=?")->execute([$courseId]);
        $success = 'ลบคลาสแล้ว';
    }

    // redirect กัน resubmit ใช้ session flash (session เริ่มไปแล้วตั้งแต่ต้นไฟล์)
    if ($success) $_SESSION['yoga_flash_ok']  = $success;
    if ($error)   $_SESSION['yoga_flash_err'] = $error;
    header("Location: yoga_classes.php?date={$date}"); exit;
}

// ── อ่าน flash จาก session ──────────────────────────────────
if (!empty($_SESSION['yoga_flash_ok']))  { $success = $_SESSION['yoga_flash_ok'];  unset($_SESSION['yoga_flash_ok']); }
if (!empty($_SESSION['yoga_flash_err'])) { $error   = $_SESSION['yoga_flash_err']; unset($_SESSION['yoga_flash_err']); }

// ── Load courses for this date ───────────────────────────
$courses = $pdo->prepare("SELECT * FROM yoga_courses WHERE course_date=? ORDER BY start_time ASC");
$courses->execute([$date]);
$courseList = $courses->fetchAll();

// ── Load bookings per course ─────────────────────────────
$courseIds = array_column($courseList, 'id');
$bookingMap = [];
if ($courseIds) {
    $in = implode(',', array_fill(0, count($courseIds), '?'));
    $bStmt = $pdo->prepare("
        SELECT yb.*, myp.sessions_total, myp.sessions_used,
               (myp.sessions_total - myp.sessions_used) AS remaining,
               myp.expiry_date
        FROM yoga_bookings yb
        LEFT JOIN member_yoga_packages myp ON myp.id = yb.member_package_id
        WHERE yb.yoga_course_id IN ($in) AND yb.status != 'cancelled'
        ORDER BY yb.created_at ASC
    ");
    $bStmt->execute($courseIds);
    foreach ($bStmt->fetchAll() as $bk) {
        $bookingMap[$bk['yoga_course_id']][] = $bk;
    }
}

// ── Package types ─────────────────────────────────────────
$pkgTypes = $pdo->query("SELECT * FROM yoga_package_types WHERE is_active=1 ORDER BY sessions_total ASC")->fetchAll();

// ── Date nav ──────────────────────────────────────────────
$dtObj   = new DateTime($date);
$prevDay = (clone $dtObj)->modify('-1 day')->format('Y-m-d');
$nextDay = (clone $dtObj)->modify('+1 day')->format('Y-m-d');
$isToday = ($date === $today);

$thDays   = ['อาทิตย์','จันทร์','อังคาร','พุธ','พฤหัสบดี','ศุกร์','เสาร์'];
$thMonths = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
$dayName  = $thDays[(int)$dtObj->format('w')];
$dayNum   = $dtObj->format('j');
$monName  = $thMonths[(int)$dtObj->format('n')];
$yearBE   = (int)$dtObj->format('Y') + 543;
$dateLabel = "วัน{$dayName} {$dayNum} {$monName} {$yearBE}";

$roomColors = [
    'ห้องร่วม' => ['bg'=>'#e0f2fe','border'=>'#0284c7','text'=>'#0369a1','badge'=>'bg-sky-100 text-sky-800'],
    'ห้องเล็ก' => ['bg'=>'#dcfce7','border'=>'#16a34a','text'=>'#15803d','badge'=>'bg-green-100 text-green-800'],
    'ห้องใหญ่' => ['bg'=>'#fef9c3','border'=>'#ca8a04','text'=>'#a16207','badge'=>'bg-yellow-100 text-yellow-800'],
];
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>จัดการคลาสโยคะ – BARGAIN SPORT</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>* { font-family: 'Prompt', sans-serif !important; }</style>
</head>
<body style="background:#f8fafc;" class="min-h-screen">
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="max-w-7xl mx-auto px-4 py-6">

  <!-- ── Page header ── -->
  <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
    <div>
      <h1 class="text-2xl font-bold" style="color:#005691;">จัดการคลาสโยคะ</h1>
      <p class="text-gray-500 text-sm mt-0.5"><?= $dateLabel ?><?= $isToday ? ' <span class="inline-block bg-green-100 text-green-700 text-xs px-2 py-0.5 rounded-full font-medium ml-1">วันนี้</span>' : '' ?></p>
    </div>
    <div class="flex gap-2">
      <a href="/admin/yoga_packages.php"
         class="flex items-center gap-1.5 px-4 py-2 text-sm border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50 transition-colors">
        แพ็กเกจสมาชิก
      </a>
      <button onclick="document.getElementById('createModal').classList.remove('hidden')"
              class="flex items-center gap-1.5 px-4 py-2 text-sm text-white rounded-lg font-medium transition-opacity hover:opacity-90"
              style="background:#005691;">
        + สร้างคลาส
      </button>
    </div>
  </div>

  <!-- ── Messages ── -->
  <?php if ($success): ?>
  <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4 text-sm flex items-center gap-2">
    ✅ <?= htmlspecialchars($success) ?>
  </div>
  <?php endif; ?>
  <?php if ($error): ?>
  <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4 text-sm flex items-center gap-2">
    ❌ <?= htmlspecialchars($error) ?>
  </div>
  <?php endif; ?>

  <!-- ── Date Navigator ── -->
  <div class="bg-white rounded-xl border border-gray-200 p-3 mb-5 flex items-center gap-2">
    <a href="?date=<?= $prevDay ?>"
       class="p-2 rounded-lg hover:bg-gray-100 text-gray-600 transition-colors">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    </a>
    <form method="get" class="flex-1 flex justify-center">
      <input type="date" name="date" value="<?= $date ?>"
             onchange="this.form.submit()"
             class="text-center text-sm font-medium text-gray-700 border-0 outline-none bg-transparent cursor-pointer">
    </form>
    <a href="?date=<?= $nextDay ?>"
       class="p-2 rounded-lg hover:bg-gray-100 text-gray-600 transition-colors">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    </a>
    <?php if (!$isToday): ?>
    <a href="?date=<?= $today ?>"
       class="px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50 transition-colors ml-1">
      วันนี้
    </a>
    <?php endif; ?>
    <span class="text-xs text-gray-400 ml-2"><?= count($courseList) ?> คลาส</span>
  </div>

  <!-- ── Course Cards ── -->
  <?php if (empty($courseList)): ?>
  <div class="bg-white rounded-xl border border-dashed border-gray-300 p-16 text-center">
    <div class="text-5xl mb-4">🧘</div>
    <p class="text-gray-500 font-medium">ยังไม่มีคลาสวันนี้</p>
    <p class="text-gray-400 text-sm mt-1 mb-5">กดปุ่ม "สร้างคลาส" เพื่อเพิ่มตารางเรียน</p>
    <button onclick="document.getElementById('createModal').classList.remove('hidden')"
            class="px-5 py-2.5 text-sm text-white rounded-lg font-medium" style="background:#005691;">
      + สร้างคลาส
    </button>
  </div>
  <?php else: ?>
  <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4">
    <?php foreach ($courseList as $c):
      $rc       = $roomColors[$c['room']] ?? $roomColors['ห้องร่วม'];
      $bookings = $bookingMap[$c['id']] ?? [];
      $total    = count($bookings);
      $attended = count(array_filter($bookings, fn($b) => $b['status']==='attended'));
      $pct      = $c['max_students'] > 0 ? round($total / $c['max_students'] * 100) : 0;
      $isFull   = $total >= $c['max_students'];
    ?>
    <div class="bg-white rounded-xl border overflow-hidden shadow-sm" style="border-color:<?= $rc['border'] ?>;">
      <!-- Card header -->
      <div class="px-4 py-3 flex items-start justify-between" style="background:<?= $rc['bg'] ?>;">
        <div>
          <div class="flex items-center gap-2 mb-0.5">
            <span class="text-xs font-semibold px-2 py-0.5 rounded-full border" style="color:<?= $rc['text'] ?>;border-color:<?= $rc['border'] ?>;">
              <?= $c['room'] ?>
            </span>
            <?php if ($isFull): ?>
            <span class="text-xs bg-red-100 text-red-600 px-2 py-0.5 rounded-full font-medium">เต็ม</span>
            <?php endif; ?>
          </div>
          <p class="font-bold text-gray-900 text-lg leading-tight">
            <?= substr($c['start_time'],0,5) ?> – <?= substr($c['end_time'],0,5) ?>
          </p>
          <p class="text-sm text-gray-600">อ.<?= htmlspecialchars($c['instructor']) ?></p>
        </div>
        <div class="text-right">
          <p class="text-2xl font-bold" style="color:<?= $rc['text'] ?>;"><?= $total ?></p>
          <p class="text-xs text-gray-500">/ <?= $c['max_students'] ?> คน</p>
          <!-- progress bar -->
          <div class="w-16 h-1.5 bg-gray-200 rounded-full mt-1.5 overflow-hidden">
            <div class="h-full rounded-full transition-all" style="width:<?= $pct ?>%;background:<?= $rc['border'] ?>;"></div>
          </div>
        </div>
      </div>

      <!-- Students list -->
      <div class="divide-y divide-gray-100">
        <?php foreach ($bookings as $bk): ?>
        <div class="px-4 py-2.5 flex items-center gap-3">
          <!-- Status dot -->
          <div class="w-2 h-2 rounded-full shrink-0 <?= $bk['status']==='attended' ? 'bg-green-500' : 'bg-amber-400' ?>"></div>
          <div class="flex-1 min-w-0">
            <p class="font-medium text-gray-800 text-sm leading-tight truncate"><?= htmlspecialchars($bk['student_name']) ?></p>
            <p class="text-xs text-gray-400"><?= htmlspecialchars($bk['student_phone']) ?>
              <?php if ($bk['member_package_id']): ?>
              · <span class="<?= ($bk['remaining'] ?? 0) <= 2 ? 'text-red-500' : 'text-green-600' ?> font-medium">
                  เหลือ <?= max(0, (int)($bk['remaining'] ?? 0)) ?> ครั้ง
                </span>
              <?php else: ?>
              · <span class="text-gray-400">ไม่มีแพ็กเกจ</span>
              <?php endif; ?>
            </p>
          </div>
          <!-- Actions -->
          <div class="flex items-center gap-1 shrink-0">
            <?php if ($bk['status'] !== 'attended'): ?>
            <form method="post" class="inline">
              <input type="hidden" name="action" value="attend">
              <input type="hidden" name="booking_id" value="<?= $bk['id'] ?>">
              <input type="hidden" name="date" value="<?= $date ?>">
              <button type="submit" title="เช็คชื่อ"
                      class="w-7 h-7 bg-green-100 text-green-700 rounded-lg flex items-center justify-center hover:bg-green-200 transition-colors text-xs font-bold">✓</button>
            </form>
            <?php else: ?>
            <span class="w-7 h-7 bg-green-500 text-white rounded-lg flex items-center justify-center text-xs">✓</span>
            <?php endif; ?>
            <form method="post" class="inline" onsubmit="return confirm('ยกเลิกการจองของ <?= htmlspecialchars($bk['student_name'], ENT_QUOTES) ?> ?')">
              <input type="hidden" name="action" value="cancel_booking">
              <input type="hidden" name="booking_id" value="<?= $bk['id'] ?>">
              <input type="hidden" name="date" value="<?= $date ?>">
              <button type="submit" title="ยกเลิก"
                      class="w-7 h-7 bg-red-50 text-red-500 rounded-lg flex items-center justify-center hover:bg-red-100 transition-colors text-xs">✕</button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>

        <?php if (empty($bookings)): ?>
        <div class="px-4 py-4 text-center text-gray-400 text-xs">ยังไม่มีนักเรียน</div>
        <?php endif; ?>
      </div>

      <!-- Card footer: Add student + delete -->
      <div class="px-4 py-2.5 bg-gray-50 border-t border-gray-100 flex items-center gap-2">
        <button onclick="openAddStudent(<?= $c['id'] ?>, '<?= htmlspecialchars($c['room'].' '.$c['instructor'], ENT_QUOTES) ?>')"
                class="flex-1 flex items-center justify-center gap-1.5 py-1.5 text-xs font-medium rounded-lg border transition-colors hover:bg-white"
                style="color:<?= $rc['text'] ?>;border-color:<?= $rc['border'] ?>;">
          + เพิ่มนักเรียน
        </button>
        <span class="text-xs text-gray-400"><?= $attended ?>/<?= $total ?> เช็คแล้ว</span>
        <form method="post" class="inline" onsubmit="return confirm('ลบคลาสนี้?')">
          <input type="hidden" name="action" value="delete_course">
          <input type="hidden" name="course_id" value="<?= $c['id'] ?>">
          <input type="hidden" name="date" value="<?= $date ?>">
          <button type="submit" title="ลบคลาส"
                  class="w-7 h-7 bg-red-50 text-red-400 rounded-lg flex items-center justify-center hover:bg-red-100 transition-colors text-sm">🗑</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</div><!-- /container -->

<!-- ══════════════════════════════════════════════════════════
     Modal: สร้างคลาสใหม่
══════════════════════════════════════════════════════════ -->
<div id="createModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
  <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
      <h3 class="text-lg font-bold text-gray-900">สร้างคลาสใหม่</h3>
      <button onclick="document.getElementById('createModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 text-xl leading-none">×</button>
    </div>
    <form method="post" class="px-6 py-4 space-y-4">
      <input type="hidden" name="action" value="create_course">
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">วันที่</label>
          <input type="date" name="course_date" value="<?= $date ?>" required
                 class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm outline-none focus:border-blue-400">
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">ห้อง</label>
          <select name="room" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm outline-none focus:border-blue-400">
            <option>ห้องร่วม</option>
            <option>ห้องเล็ก</option>
            <option>ห้องใหญ่</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">เริ่ม</label>
          <input type="time" name="start_time" required value="09:00"
                 class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm outline-none focus:border-blue-400">
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">สิ้นสุด</label>
          <input type="time" name="end_time" required value="10:00"
                 class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm outline-none focus:border-blue-400">
        </div>
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">ครูผู้สอน</label>
        <input type="text" name="instructor" required placeholder="กรุณากรอกชื่อครูผู้สอน"
               class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm outline-none focus:border-blue-400">
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">จำนวนที่นั่งสูงสุด</label>
        <input type="number" name="max_students" value="15" min="1" max="100"
               class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm outline-none focus:border-blue-400">
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">หมายเหตุ (ไม่บังคับ)</label>
        <input type="text" name="notes" placeholder="เพิ่มเติม..."
               class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm outline-none focus:border-blue-400">
      </div>
      <div class="flex gap-3 pt-1">
        <button type="submit" class="flex-1 py-2.5 text-white text-sm font-semibold rounded-lg hover:opacity-90" style="background:#005691;">
          สร้างคลาส
        </button>
        <button type="button" onclick="document.getElementById('createModal').classList.add('hidden')"
                class="flex-1 py-2.5 text-gray-600 text-sm rounded-lg border border-gray-300 hover:bg-gray-50">
          ยกเลิก
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     Modal: เพิ่มนักเรียน
══════════════════════════════════════════════════════════ -->
<div id="addStudentModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
  <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
      <div>
        <h3 class="text-lg font-bold text-gray-900">+ เพิ่มนักเรียน</h3>
        <p class="text-xs text-gray-400" id="addStudentSubtitle"></p>
      </div>
      <button onclick="document.getElementById('addStudentModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 text-xl">×</button>
    </div>
    <form method="post" class="px-6 py-4 space-y-4">
      <input type="hidden" name="action" value="add_student">
      <input type="hidden" name="date" value="<?= $date ?>">
      <input type="hidden" name="course_id" id="addCourseId">
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">ชื่อนักเรียน</label>
        <input type="text" name="student_name" id="stuName" required placeholder="ชื่อ-นามสกุล"
               class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm outline-none focus:border-blue-400">
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">เบอร์โทร</label>
        <div class="flex gap-2">
          <input type="tel" name="student_phone" id="stuPhone" required placeholder="0812345678"
                 class="flex-1 px-3 py-2.5 border border-gray-300 rounded-lg text-sm outline-none focus:border-blue-400"
                 oninput="searchPackages(this.value)">
          <button type="button" onclick="searchPackages(document.getElementById('stuPhone').value)"
                  class="px-3 py-2.5 text-xs rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50">ค้นหา</button>
        </div>
      </div>
      <!-- Package selector (filled by JS) -->
      <div id="packageSection" class="hidden">
        <label class="block text-xs font-medium text-gray-600 mb-1">แพ็กเกจ</label>
        <select name="member_package_id" id="pkgSelect"
                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm outline-none focus:border-blue-400">
          <option value="">— ไม่ใช้แพ็กเกจ —</option>
        </select>
      </div>
      <div id="noPkgNote" class="hidden text-xs text-amber-600 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
        ⚠️ ไม่พบแพ็กเกจที่ใช้งานได้ของเบอร์นี้ — จะบันทึกโดยไม่หักครั้ง
      </div>
      <div class="flex gap-3 pt-1">
        <button type="submit" class="flex-1 py-2.5 text-white text-sm font-semibold rounded-lg hover:opacity-90" style="background:#005691;">
          เพิ่มเข้าคลาส
        </button>
        <button type="button" onclick="document.getElementById('addStudentModal').classList.add('hidden')"
                class="flex-1 py-2.5 text-gray-600 text-sm rounded-lg border border-gray-300 hover:bg-gray-50">
          ยกเลิก
        </button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
// ── Packages data for phone lookup ──────────────────────
const TODAY = '<?= $today ?>';

function openAddStudent(courseId, label) {
  document.getElementById('addCourseId').value = courseId;
  document.getElementById('addStudentSubtitle').textContent = label;
  document.getElementById('stuPhone').value = '';
  document.getElementById('stuName').value  = '';
  document.getElementById('packageSection').classList.add('hidden');
  document.getElementById('noPkgNote').classList.add('hidden');
  document.getElementById('pkgSelect').innerHTML = '<option value="">— ไม่ใช้แพ็กเกจ —</option>';
  document.getElementById('addStudentModal').classList.remove('hidden');
  setTimeout(() => document.getElementById('stuPhone').focus(), 100);
}

async function searchPackages(phone) {
  phone = phone.trim();
  if (phone.length < 9) return;
  const res  = await fetch('/admin/yoga_pkg_ajax.php?phone=' + encodeURIComponent(phone));
  const data = await res.json();

  const sec  = document.getElementById('packageSection');
  const note = document.getElementById('noPkgNote');
  const sel  = document.getElementById('pkgSelect');

  sel.innerHTML = '<option value="">— ไม่ใช้แพ็กเกจ —</option>';

  if (data.student_name) {
    document.getElementById('stuName').value = data.student_name;
  }

  if (data.packages && data.packages.length > 0) {
    data.packages.forEach(p => {
      const opt = document.createElement('option');
      opt.value = p.id;
      const expTxt = p.expiry_date ? ' · หมด ' + p.expiry_date : '';
      opt.textContent = p.type_name + ' — เหลือ ' + p.remaining + ' ครั้ง' + expTxt;
      if (p.remaining <= 0 || (p.expiry_date && p.expiry_date < TODAY)) opt.disabled = true;
      sel.appendChild(opt);
    });
    // auto-select first valid
    const first = data.packages.find(p => p.remaining > 0 && (!p.expiry_date || p.expiry_date >= TODAY));
    if (first) sel.value = first.id;
    sec.classList.remove('hidden');
    note.classList.add('hidden');
  } else if (phone.length >= 9) {
    sec.classList.add('hidden');
    note.classList.remove('hidden');
  }
}
</script>
</body>
</html>
