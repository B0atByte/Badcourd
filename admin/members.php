<?php
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../config/db.php';

require_permission('members');

$success = $error = '';

// ── โหลด site settings ──
$settingsRows = $pdo->query("SELECT setting_key, setting_value FROM site_settings")->fetchAll();
$siteSettings = [];
foreach ($settingsRows as $r) $siteSettings[$r['setting_key']] = $r['setting_value'];
$discountEnabled = ($siteSettings['member_discount_enabled'] ?? '1') === '1';

function saveSetting(PDO $pdo, string $key, string $val): void {
    $pdo->prepare("INSERT INTO site_settings (setting_key,setting_value) VALUES(?,?)
                   ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)")
        ->execute([$key, $val]);
}

// ── POST handlers ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'toggle_discount') {
        $newVal = $discountEnabled ? '0' : '1';
        saveSetting($pdo, 'member_discount_enabled', $newVal);
        $discountEnabled = $newVal === '1';
        $success = $discountEnabled ? 'เปิดระบบส่วนลดสมาชิกแล้ว' : 'ปิดระบบส่วนลดสมาชิกแล้ว — คิดราคาเต็มทุกการจอง';

    } elseif ($_POST['action'] === 'adjust_points') {
        $member_id    = (int) $_POST['member_id'];
        $points_change = (int) $_POST['points_change'];
        $description  = trim($_POST['description']);
        if ($points_change === 0)          $error = 'กรุณาระบุจำนวนแต้มที่ต้องการปรับ (ไม่ใช่ 0)';
        elseif (abs($points_change) > 99999) $error = 'จำนวนแต้มต้องไม่เกิน 99,999 ต่อครั้ง';
        elseif (empty($description))       $error = 'กรุณาระบุเหตุผลในการปรับแต้ม';
        if (!$error) try {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE members SET points=GREATEST(0,points+?) WHERE id=?")->execute([$points_change,$member_id]);
            $pdo->prepare("INSERT INTO point_transactions(member_id,points,type,description,created_by) VALUES(?,?,'adjust',?,?)")
                ->execute([$member_id, abs($points_change), $description, $_SESSION['user']['id']]);
            $pdo->commit();
            $success = 'ปรับแต้มสำเร็จ';
        } catch (Exception $e) { $pdo->rollBack(); $error = 'เกิดข้อผิดพลาด: '.$e->getMessage(); }

    } elseif ($_POST['action'] === 'toggle_status') {
        $member_id  = (int) $_POST['member_id'];
        $new_status = $_POST['new_status'] === 'active' ? 'active' : 'inactive';
        $pdo->prepare("UPDATE members SET status=? WHERE id=?")->execute([$new_status,$member_id]);
        $success = 'อัปเดตสถานะสำเร็จ';

    } elseif ($_POST['action'] === 'edit_member') {
        $member_id = (int) $_POST['member_id'];
        $name  = trim($_POST['name']  ?? '');
        $email = trim($_POST['email'] ?? '');
        if (empty($name)) $error = 'กรุณากรอกชื่อ';
        else {
            $pdo->prepare("UPDATE members SET name=?,email=? WHERE id=?")->execute([$name,$email?:null,$member_id]);
            $success = 'อัปเดตข้อมูลสมาชิกเรียบร้อย';
        }

    } elseif ($_POST['action'] === 'delete_member') {
        $member_id = (int) $_POST['member_id'];
        if ($member_id > 0) try {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE bookings SET member_id=NULL WHERE member_id=?")->execute([$member_id]);
            $pdo->prepare("DELETE FROM point_transactions WHERE member_id=?")->execute([$member_id]);
            $pdo->prepare("DELETE FROM members WHERE id=?")->execute([$member_id]);
            $pdo->commit();
            $success = 'ลบสมาชิกเรียบร้อยแล้ว';
        } catch (Exception $e) { $pdo->rollBack(); $error = 'ไม่สามารถลบสมาชิกได้: '.$e->getMessage(); }

    } elseif ($_POST['action'] === 'add_member') {
        $phone = trim($_POST['phone'] ?? '');
        $name  = trim($_POST['name']  ?? '');
        $email = trim($_POST['email'] ?? '');
        if (empty($phone)||empty($name)) $error = 'กรุณากรอกชื่อและเบอร์โทรศัพท์';
        elseif (!preg_match('/^0[0-9]{8,9}$/',$phone)) $error = 'เบอร์โทรศัพท์ไม่ถูกต้อง';
        else {
            $dup = $pdo->prepare("SELECT id FROM members WHERE phone=?"); $dup->execute([$phone]);
            if ($dup->fetch()) $error = 'เบอร์โทรศัพท์นี้มีอยู่ในระบบแล้ว';
            else {
                $pdo->prepare("INSERT INTO members(phone,name,email,points,total_bookings,total_spent,member_level,status) VALUES(?,?,?,0,0,0,'Bronze','active')")
                    ->execute([$phone,$name,$email?:null]);
                $success = 'เพิ่มสมาชิกเรียบร้อยแล้ว';
            }
        }
    }
}

// ── Filter & Pagination ──
$search        = trim($_GET['search'] ?? '');
$level_filter  = $_GET['level']  ?? '';
$status_filter = $_GET['status'] ?? '';
$view          = in_array($_GET['view'] ?? '', ['card','table']) ? $_GET['view'] : 'table';

$per_page_raw = $_GET['per_page'] ?? '25';
$per_page = ($per_page_raw === 'all') ? 0 : (int)$per_page_raw;
if (!in_array($per_page,[0,10,25,50,100])) $per_page = 25;
$page = max(1,(int)($_GET['page'] ?? 1));

$where  = ['1=1'];
$params = [];
if (!empty($search))       { $where[] = '(phone LIKE ? OR name LIKE ?)'; $sp='%'.$search.'%'; $params[]=$sp; $params[]=$sp; }
if (!empty($level_filter)) { $where[] = 'member_level=?'; $params[] = $level_filter; }
if (!empty($status_filter)){ $where[] = 'status=?';       $params[] = $status_filter; }
$whereClause = implode(' AND ',$where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM members WHERE $whereClause");
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();

$totalPages = 1; $offset = 0;
if ($per_page > 0) {
    $totalPages = max(1,(int)ceil($totalRecords/$per_page));
    $page = min($page,$totalPages);
    $offset = ($page-1)*$per_page;
}

$sql = "SELECT * FROM members WHERE $whereClause ORDER BY total_spent DESC, joined_date DESC";
if ($per_page > 0) $sql .= " LIMIT $per_page OFFSET $offset";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$members = $stmt->fetchAll();

$statsStmt = $pdo->query("SELECT COUNT(*) as total_members,
    SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active_members,
    SUM(total_spent) as total_revenue, SUM(points) as total_points FROM members");
$stats = $statsStmt->fetch();

$levelColors = [
    'Bronze'   => ['bg'=>'bg-amber-100',  'text'=>'text-amber-800',  'border'=>'border-amber-200'],
    'Silver'   => ['bg'=>'bg-gray-100',   'text'=>'text-gray-800',   'border'=>'border-gray-300'],
    'Gold'     => ['bg'=>'bg-yellow-100', 'text'=>'text-yellow-800', 'border'=>'border-yellow-300'],
    'Platinum' => ['bg'=>'bg-blue-100',   'text'=>'text-blue-800',   'border'=>'border-blue-300'],
];
$discounts = ['Bronze'=>0,'Silver'=>5,'Gold'=>10,'Platinum'=>15];

// URL helper เพื่อสร้าง link pagination ที่เก็บ params อื่น
$qBase = http_build_query(array_filter(['search'=>$search,'level'=>$level_filter,'status'=>$status_filter,'per_page'=>$per_page_raw,'view'=>$view]));
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
  <title>จัดการสมาชิก - BARGAIN SPORT</title>
</head>
<body style="background:#FAFAFA;" class="min-h-screen">
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/swal_flash.php'; ?>

<div class="max-w-7xl mx-auto px-4 py-8">

  <!-- Header -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
      <h1 style="color:#D32F2F;" class="text-2xl font-bold">สมาชิก</h1>
      <p class="text-gray-500 text-sm mt-0.5">จัดการข้อมูลสมาชิกและระบบแต้มสะสม</p>
    </div>
    <!-- Toggle ปิด/เปิดส่วนลด -->
    <form method="post" class="flex items-center gap-3 bg-white border rounded-xl px-4 py-3
      <?= $discountEnabled ? 'border-green-200' : 'border-red-200' ?>">
      <input type="hidden" name="action" value="toggle_discount">
      <div>
        <p class="text-sm font-semibold <?= $discountEnabled ? 'text-green-700' : 'text-red-600' ?>">
          ระบบส่วนลดสมาชิก: <?= $discountEnabled ? 'เปิดอยู่' : 'ปิดอยู่' ?>
        </p>
        <p class="text-xs text-gray-400"><?= $discountEnabled ? 'ลดราคาตาม level อัตโนมัติ' : 'คิดราคาเต็ม ไม่มีส่วนลด %' ?></p>
      </div>
      <button type="submit"
        class="relative inline-flex h-7 w-12 items-center rounded-full transition-colors focus:outline-none flex-shrink-0
          <?= $discountEnabled ? 'bg-green-500' : 'bg-gray-300' ?>"
        onclick="return confirm('<?= $discountEnabled ? 'ปิดระบบส่วนลดสมาชิก? ลูกค้าจะถูกคิดราคาเต็มทุกการจอง' : 'เปิดระบบส่วนลดสมาชิก? ลูกค้าจะได้รับส่วนลดตาม level' ?>')">
        <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform
          <?= $discountEnabled ? 'translate-x-6' : 'translate-x-1' ?>"></span>
      </button>
    </form>
  </div>

  <!-- Stats -->
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-gray-200 p-5">
      <p class="text-xs text-gray-500 mb-1">สมาชิกทั้งหมด</p>
      <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['total_members']) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-5">
      <p class="text-xs text-gray-500 mb-1">ใช้งานอยู่</p>
      <p class="text-2xl font-bold" style="color:#D32F2F;"><?= number_format($stats['active_members']??0) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-5">
      <p class="text-xs text-gray-500 mb-1">รายได้รวม</p>
      <p class="text-2xl font-bold text-gray-900">฿<?= number_format($stats['total_revenue']??0,0) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-5">
      <p class="text-xs text-gray-500 mb-1">แต้มรวม</p>
      <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['total_points']??0) ?></p>
    </div>
  </div>

  <!-- Search & Filter -->
  <div class="bg-white rounded-xl border border-gray-200 p-4 mb-5">
    <form method="get" class="flex flex-col gap-3">
      <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
      <div class="flex flex-col sm:flex-row gap-3">
        <div class="flex-1">
          <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
            placeholder="🔍 ค้นหาด้วยเบอร์โทรหรือชื่อ..."
            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:border-red-300 focus:ring-2 focus:ring-red-100 outline-none text-sm">
        </div>
        <select name="level" class="px-3 py-2.5 rounded-lg border border-gray-300 outline-none text-sm min-w-[130px]">
          <option value="">ทุกระดับ</option>
          <?php foreach(['Bronze','Silver','Gold','Platinum'] as $lv): ?>
          <option value="<?=$lv?>" <?=$level_filter===$lv?'selected':''?>><?=$lv?></option>
          <?php endforeach; ?>
        </select>
        <select name="status" class="px-3 py-2.5 rounded-lg border border-gray-300 outline-none text-sm min-w-[120px]">
          <option value="">ทุกสถานะ</option>
          <option value="active"   <?=$status_filter==='active'  ?'selected':''?>>Active</option>
          <option value="inactive" <?=$status_filter==='inactive'?'selected':''?>>Inactive</option>
        </select>
      </div>
      <div class="flex flex-wrap gap-2 items-center">
        <label class="text-xs text-gray-500">แสดง</label>
        <select name="per_page" onchange="this.form.submit()" class="px-3 py-2 rounded-lg border border-gray-300 outline-none text-sm">
          <?php foreach([10=>10,25=>25,50=>50,100=>100,0=>'ทั้งหมด'] as $v=>$l): ?>
          <option value="<?=$v===0?'all':$v?>" <?=$per_page===$v?'selected':''?>><?=$l?> รายการ</option>
          <?php endforeach; ?>
        </select>
        <div class="flex-1"></div>
        <button type="submit" style="background:#D32F2F;" class="px-5 py-2 text-white text-sm font-medium rounded-lg hover:opacity-90">ค้นหา</button>
        <?php if($search||$level_filter||$status_filter): ?>
        <a href="/admin/members.php?view=<?=htmlspecialchars($view)?>" class="px-5 py-2 text-gray-600 text-sm font-medium rounded-lg border border-gray-300 hover:bg-gray-50">ล้างตัวกรอง</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Toolbar: count + view toggle + add -->
  <div class="flex items-center justify-between mb-3 gap-3 flex-wrap">
    <span class="text-sm text-gray-600">
      พบ <span style="color:#D32F2F;" class="font-bold"><?= number_format($totalRecords) ?></span> รายการ
      <?php if($per_page>0&&$totalPages>1): ?><span class="text-gray-400 text-xs">· หน้า <?=$page?>/<?=$totalPages?></span><?php endif; ?>
    </span>
    <div class="flex items-center gap-2">
      <!-- View Toggle -->
      <div class="flex rounded-lg border border-gray-200 overflow-hidden">
        <a href="?<?=http_build_query(array_merge($_GET,['view'=>'table','page'=>1]))?>"
          class="px-3 py-1.5 text-xs font-medium flex items-center gap-1 transition-colors
            <?=$view==='table'?'text-white':'bg-white text-gray-500 hover:bg-gray-50'?>"
          style="<?=$view==='table'?'background:#D32F2F':''?>">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 6h18M3 14h18M3 18h18"/>
          </svg>
          ตาราง
        </a>
        <a href="?<?=http_build_query(array_merge($_GET,['view'=>'card','page'=>1]))?>"
          class="px-3 py-1.5 text-xs font-medium flex items-center gap-1 transition-colors border-l border-gray-200
            <?=$view==='card'?'text-white':'bg-white text-gray-500 hover:bg-gray-50'?>"
          style="<?=$view==='card'?'background:#D32F2F':''?>">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
          </svg>
          การ์ด
        </a>
      </div>
      <button type="button" onclick="openAddModal()" style="background:#D32F2F;"
        class="px-4 py-1.5 text-white text-sm font-medium rounded-lg hover:opacity-90 flex items-center gap-1.5">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        เพิ่มสมาชิก
      </button>
    </div>
  </div>

  <!-- ══ TABLE VIEW ══ -->
  <?php if ($view === 'table'): ?>
  <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
      <?php if(count($members)===0): ?>
      <div class="p-12 text-center text-gray-400">ไม่พบข้อมูลสมาชิก</div>
      <?php else: ?>
      <table class="w-full text-sm">
        <thead style="background:#FAFAFA;">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">สมาชิก</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">ระดับ</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">แต้ม</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">จอง</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">ยอดใช้จ่าย</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">ส่วนลด</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">สถานะ</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">จัดการ</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php foreach($members as $m):
            $colors = $levelColors[$m['member_level']] ?? $levelColors['Bronze'];
            $disc   = $discounts[$m['member_level']] ?? 0;
          ?>
          <tr class="hover:bg-gray-50">
            <td class="px-4 py-3">
              <p class="font-medium text-gray-900"><?=htmlspecialchars($m['name'])?></p>
              <p class="text-xs text-gray-400"><?=htmlspecialchars($m['phone'])?></p>
            </td>
            <td class="px-4 py-3">
              <span class="<?=$colors['bg']?> <?=$colors['text']?> <?=$colors['border']?> border px-2 py-1 rounded text-xs font-medium">
                <?=htmlspecialchars($m['member_level'])?>
              </span>
            </td>
            <td class="px-4 py-3 text-right font-semibold text-gray-900"><?=number_format($m['points'])?></td>
            <td class="px-4 py-3 text-right text-gray-600"><?=number_format($m['total_bookings'])?></td>
            <td class="px-4 py-3 text-right font-semibold text-gray-900">฿<?=number_format($m['total_spent'],0)?></td>
            <td class="px-4 py-3 text-center">
              <?php if(!$discountEnabled): ?>
                <span class="text-xs text-gray-400 line-through"><?=$disc?>%</span>
                <span class="text-xs text-red-500 ml-1">ปิด</span>
              <?php elseif($disc>0): ?>
                <span class="text-xs font-semibold text-green-700"><?=$disc?>%</span>
              <?php else: ?>
                <span class="text-xs text-gray-400">-</span>
              <?php endif; ?>
            </td>
            <td class="px-4 py-3 text-center">
              <?php if($m['status']==='active'): ?>
              <span class="bg-green-100 text-green-800 border border-green-200 px-2 py-1 rounded text-xs font-medium">Active</span>
              <?php else: ?>
              <span class="bg-red-100 text-red-800 border border-red-200 px-2 py-1 rounded text-xs font-medium">Inactive</span>
              <?php endif; ?>
            </td>
            <td class="px-4 py-3">
              <div class="flex items-center justify-center gap-2">
                <a href="/members/profile.php?id=<?=$m['id']?>" style="color:#D32F2F;" title="ดูโปรไฟล์">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                </a>
                <button onclick="openEditModal(<?=$m['id']?>,'<?=htmlspecialchars($m['name'],ENT_QUOTES)?>','<?=htmlspecialchars($m['email']??'',ENT_QUOTES)?>')" class="text-blue-500 hover:text-blue-700" title="แก้ไข">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.862 3.487a2.25 2.25 0 113.182 3.182L7.5 19.213l-4 1 1-4 12.362-12.726z"/></svg>
                </button>
                <button onclick="openAdjustModal(<?=$m['id']?>,'<?=htmlspecialchars($m['name'],ENT_QUOTES)?>'  ,<?=$m['points']?>)" class="text-yellow-600" title="ปรับแต้ม">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </button>
                <form method="post" class="inline" id="toggleMember_<?=$m['id']?>">
                  <input type="hidden" name="action" value="toggle_status">
                  <input type="hidden" name="member_id" value="<?=$m['id']?>">
                  <input type="hidden" name="new_status" value="<?=$m['status']==='active'?'inactive':'active'?>">
                  <button type="button"
                    onclick="swalSubmit('toggleMember_<?=$m['id']?>','<?=$m['status']==='active'?'ระงับสมาชิก?':'เปิดใช้งานสมาชิก?'?>','<?=htmlspecialchars($m['name'],ENT_QUOTES)?>','<?=$m['status']==='active'?'ระงับ':'เปิดใช้งาน'?>')"
                    class="<?=$m['status']==='active'?'text-red-600':'text-green-600'?>" title="<?=$m['status']==='active'?'ระงับ':'เปิดใช้งาน'?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <?php if($m['status']==='active'): ?>
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                      <?php else: ?>
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                      <?php endif; ?>
                    </svg>
                  </button>
                </form>
                <button onclick="confirmDelete(<?=$m['id']?>,'<?=htmlspecialchars($m['name'],ENT_QUOTES)?>'  ,<?=(int)$m['total_bookings']?>)" class="text-red-500 hover:text-red-700" title="ลบ">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
    <!-- Pagination table -->
    <?php if($per_page>0&&$totalPages>1): ?>
    <div class="px-4 py-3 border-t border-gray-100 flex justify-between items-center text-sm">
      <span class="text-gray-500">หน้า <?=$page?>/<?=$totalPages?></span>
      <div class="flex gap-2">
        <?php
        $pPrev = http_build_query(array_merge($_GET,['page'=>max(1,$page-1)]));
        $pNext = http_build_query(array_merge($_GET,['page'=>min($totalPages,$page+1)]));
        ?>
        <a href="?<?=$pPrev?>" class="px-3 py-1.5 border border-gray-200 rounded-lg hover:bg-gray-50 <?=$page<=1?'opacity-40 pointer-events-none':''?>">← ก่อนหน้า</a>
        <a href="?<?=$pNext?>" class="px-3 py-1.5 border border-gray-200 rounded-lg hover:bg-gray-50 <?=$page>=$totalPages?'opacity-40 pointer-events-none':''?>">ถัดไป →</a>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- ══ CARD VIEW ══ -->
  <?php else: ?>
  <?php if(count($members)===0): ?>
  <div class="bg-white rounded-xl border border-gray-200 p-12 text-center text-gray-400">ไม่พบข้อมูลสมาชิก</div>
  <?php else: ?>
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php foreach($members as $m):
      $colors = $levelColors[$m['member_level']] ?? $levelColors['Bronze'];
      $disc   = $discounts[$m['member_level']] ?? 0;
    ?>
    <div class="bg-white rounded-xl border border-gray-200 p-5 hover:shadow-md transition-shadow">
      <div class="flex items-start justify-between mb-3">
        <div class="flex-1 min-w-0">
          <h3 class="font-semibold text-gray-900 truncate"><?=htmlspecialchars($m['name'])?></h3>
          <p class="text-sm text-gray-400"><?=htmlspecialchars($m['phone'])?></p>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0 ml-2">
          <span class="<?=$colors['bg']?> <?=$colors['text']?> <?=$colors['border']?> border px-2 py-1 rounded text-xs font-medium">
            <?=htmlspecialchars($m['member_level'])?>
          </span>
          <?php if($m['status']!=='active'): ?>
          <span class="bg-red-100 text-red-600 border border-red-200 px-2 py-0.5 rounded text-xs">Inactive</span>
          <?php endif; ?>
        </div>
      </div>
      <div class="space-y-1.5 mb-4 text-sm">
        <div class="flex justify-between">
          <span class="text-gray-400">แต้มสะสม</span>
          <span class="font-semibold text-gray-900"><?=number_format($m['points'])?> แต้ม</span>
        </div>
        <div class="flex justify-between">
          <span class="text-gray-400">ยอดใช้จ่าย</span>
          <span class="font-semibold text-gray-900">฿<?=number_format($m['total_spent'],0)?></span>
        </div>
        <div class="flex justify-between">
          <span class="text-gray-400">จำนวนจอง</span>
          <span class="font-semibold text-gray-900"><?=number_format($m['total_bookings'])?> ครั้ง</span>
        </div>
        <div class="flex justify-between">
          <span class="text-gray-400">ส่วนลด</span>
          <?php if(!$discountEnabled): ?>
          <span class="text-gray-400 line-through text-xs"><?=$disc?>%</span>
          <?php elseif($disc>0): ?>
          <span class="font-semibold text-green-700"><?=$disc?>%</span>
          <?php else: ?>
          <span class="text-gray-400">-</span>
          <?php endif; ?>
        </div>
      </div>
      <div class="flex gap-2">
        <a href="/members/profile.php?id=<?=$m['id']?>" style="background:#FFEBEE;color:#D32F2F;"
          class="flex-1 py-2 text-center text-xs font-medium rounded-lg hover:opacity-80 transition-opacity">
          ดูโปรไฟล์
        </a>
        <button onclick="openEditModal(<?=$m['id']?>,'<?=htmlspecialchars($m['name'],ENT_QUOTES)?>','<?=htmlspecialchars($m['email']??'',ENT_QUOTES)?>')"
          class="px-3 py-2 bg-blue-50 text-blue-600 text-xs font-medium rounded-lg hover:bg-blue-100 transition-colors">
          แก้ไข
        </button>
        <button onclick="openAdjustModal(<?=$m['id']?>,'<?=htmlspecialchars($m['name'],ENT_QUOTES)?>'  ,<?=$m['points']?>)"
          class="px-3 py-2 bg-yellow-50 text-yellow-700 text-xs font-medium rounded-lg hover:bg-yellow-100 transition-colors">
          แต้ม
        </button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <!-- Pagination card -->
  <?php if($per_page>0&&$totalPages>1): ?>
  <div class="mt-5 flex justify-between items-center text-sm">
    <span class="text-gray-500">หน้า <?=$page?>/<?=$totalPages?></span>
    <div class="flex gap-2">
      <a href="?<?=$pPrev?>" class="px-3 py-1.5 border border-gray-200 rounded-lg hover:bg-gray-50 <?=$page<=1?'opacity-40 pointer-events-none':''?>">← ก่อนหน้า</a>
      <a href="?<?=$pNext?>" class="px-3 py-1.5 border border-gray-200 rounded-lg hover:bg-gray-50 <?=$page>=$totalPages?'opacity-40 pointer-events-none':''?>">ถัดไป →</a>
    </div>
  </div>
  <?php endif; ?>
  <?php endif; ?>
  <?php endif; ?>

</div>

<!-- Add Modal -->
<div id="addModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50">
  <div class="bg-white rounded-xl p-6 max-w-md w-full mx-4 shadow-2xl">
    <h3 class="text-lg font-bold text-gray-900 mb-4">เพิ่มสมาชิกใหม่</h3>
    <form method="post">
      <input type="hidden" name="action" value="add_member">
      <div class="mb-4"><label class="block text-sm font-medium text-gray-700 mb-1">เบอร์โทรศัพท์ <span class="text-red-500">*</span></label>
        <input type="tel" name="phone" required placeholder="0812345678" class="w-full px-3 py-2.5 rounded-lg border border-gray-300 focus:border-red-300 outline-none text-sm"></div>
      <div class="mb-4"><label class="block text-sm font-medium text-gray-700 mb-1">ชื่อ-นามสกุล <span class="text-red-500">*</span></label>
        <input type="text" name="name" required placeholder="ชื่อ-นามสกุล" class="w-full px-3 py-2.5 rounded-lg border border-gray-300 focus:border-red-300 outline-none text-sm"></div>
      <div class="mb-5"><label class="block text-sm font-medium text-gray-700 mb-1">อีเมล <span class="text-gray-400 font-normal">(ไม่บังคับ)</span></label>
        <input type="email" name="email" placeholder="example@email.com" class="w-full px-3 py-2.5 rounded-lg border border-gray-300 focus:border-red-300 outline-none text-sm"></div>
      <p class="text-xs text-gray-400 mb-4">ระดับเริ่มต้น: Bronze | แต้ม: 0 | สถานะ: Active</p>
      <div class="flex gap-3">
        <button type="submit" style="background:#D32F2F;" class="flex-1 px-4 py-2.5 text-white text-sm font-medium rounded-lg hover:opacity-90">เพิ่มสมาชิก</button>
        <button type="button" onclick="closeAddModal()" class="flex-1 px-4 py-2.5 text-gray-700 text-sm font-medium rounded-lg border border-gray-300 hover:bg-gray-50">ยกเลิก</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50">
  <div class="bg-white rounded-xl p-6 max-w-md w-full mx-4 shadow-2xl">
    <h3 class="text-lg font-bold text-gray-900 mb-4">แก้ไขข้อมูลสมาชิก</h3>
    <form method="post">
      <input type="hidden" name="action" value="edit_member">
      <input type="hidden" name="member_id" id="edit_member_id">
      <div class="mb-4"><label class="block text-sm font-medium text-gray-700 mb-1">ชื่อ-นามสกุล <span class="text-red-500">*</span></label>
        <input type="text" name="name" id="edit_name" required class="w-full px-3 py-2.5 rounded-lg border border-gray-300 focus:border-red-300 outline-none text-sm"></div>
      <div class="mb-5"><label class="block text-sm font-medium text-gray-700 mb-1">อีเมล <span class="text-gray-400 font-normal">(ไม่บังคับ)</span></label>
        <input type="email" name="email" id="edit_email" class="w-full px-3 py-2.5 rounded-lg border border-gray-300 focus:border-red-300 outline-none text-sm"></div>
      <div class="flex gap-3">
        <button type="submit" style="background:#D32F2F;" class="flex-1 px-4 py-2.5 text-white text-sm font-medium rounded-lg hover:opacity-90">บันทึก</button>
        <button type="button" onclick="closeEditModal()" class="flex-1 px-4 py-2.5 text-gray-700 text-sm font-medium rounded-lg border border-gray-300 hover:bg-gray-50">ยกเลิก</button>
      </div>
    </form>
  </div>
</div>

<!-- Adjust Points Modal -->
<div id="adjustModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50">
  <div class="bg-white rounded-xl p-6 max-w-md w-full mx-4">
    <h3 class="text-lg font-bold text-gray-900 mb-4">ปรับแต้มสมาชิก</h3>
    <form method="post" id="adjustPointsForm">
      <input type="hidden" name="action" value="adjust_points">
      <input type="hidden" name="member_id" id="adjust_member_id">
      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 mb-1">สมาชิก</label>
        <p class="font-semibold text-gray-900" id="adjust_member_name"></p>
        <p class="text-sm text-gray-500">แต้มปัจจุบัน: <span id="adjust_current_points" class="font-semibold"></span> แต้ม</p>
      </div>
      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 mb-1">ปรับแต้ม</label>
        <input type="number" name="points_change" id="pointsChangeInput" required min="-99999" max="99999"
          placeholder="บวกเพื่อเพิ่ม ลบเพื่อลด"
          class="w-full px-3 py-2.5 rounded-lg border border-gray-300 focus:border-red-300 outline-none text-sm">
      </div>
      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 mb-1">เหตุผล</label>
        <textarea name="description" required rows="2" placeholder="เหตุผลในการปรับแต้ม..."
          class="w-full px-3 py-2.5 rounded-lg border border-gray-300 focus:border-red-300 outline-none text-sm"></textarea>
      </div>
      <div class="flex gap-3">
        <button type="button" onclick="confirmAdjustPoints()" style="background:#D32F2F;" class="flex-1 px-4 py-2.5 text-white text-sm font-medium rounded-lg hover:opacity-90">บันทึก</button>
        <button type="button" onclick="closeAdjustModal()" class="flex-1 px-4 py-2.5 text-gray-700 text-sm font-medium rounded-lg border border-gray-300 hover:bg-gray-50">ยกเลิก</button>
      </div>
    </form>
  </div>
</div>

<form id="deleteForm" method="post" class="hidden">
  <input type="hidden" name="action" value="delete_member">
  <input type="hidden" name="member_id" id="delete_member_id">
</form>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
function openAddModal() { document.getElementById('addModal').classList.remove('hidden'); setTimeout(()=>document.querySelector('#addModal input[name="phone"]').focus(),50); }
function closeAddModal() { document.getElementById('addModal').classList.add('hidden'); }
document.getElementById('addModal').addEventListener('click',function(e){if(e.target===this)closeAddModal();});

function openEditModal(id,name,email) {
  document.getElementById('edit_member_id').value=id;
  document.getElementById('edit_name').value=name;
  document.getElementById('edit_email').value=email;
  document.getElementById('editModal').classList.remove('hidden');
  setTimeout(()=>document.getElementById('edit_name').focus(),50);
}
function closeEditModal() { document.getElementById('editModal').classList.add('hidden'); }
document.getElementById('editModal').addEventListener('click',function(e){if(e.target===this)closeEditModal();});

function openAdjustModal(id,name,pts) {
  document.getElementById('adjust_member_id').value=id;
  document.getElementById('adjust_member_name').textContent=name;
  document.getElementById('adjust_current_points').textContent=pts.toLocaleString('th-TH');
  document.getElementById('adjustModal').classList.remove('hidden');
}
function closeAdjustModal() { document.getElementById('adjustModal').classList.add('hidden'); }
document.getElementById('adjustModal').addEventListener('click',function(e){if(e.target===this)closeAdjustModal();});

function confirmAdjustPoints() {
  const pts=parseInt(document.getElementById('pointsChangeInput').value)||0;
  if(pts===0){Swal.fire({icon:'error',title:'ไม่ถูกต้อง',text:'กรุณาระบุจำนวนแต้ม (ไม่ใช่ 0)',confirmButtonColor:'#D32F2F'});return;}
  if(Math.abs(pts)>99999){Swal.fire({icon:'error',title:'จำนวนเกินกำหนด',text:'ปรับแต้มได้ไม่เกิน 99,999 ต่อครั้ง',confirmButtonColor:'#D32F2F'});return;}
  const action=pts>0?`เพิ่ม ${pts.toLocaleString()} แต้ม`:`ลด ${Math.abs(pts).toLocaleString()} แต้ม`;
  Swal.fire({icon:'question',title:'ยืนยันการปรับแต้ม?',text:action,showCancelButton:true,confirmButtonColor:'#D32F2F',cancelButtonColor:'#6b7280',confirmButtonText:'ยืนยัน',cancelButtonText:'ยกเลิก',reverseButtons:true})
    .then(r=>{if(r.isConfirmed)document.getElementById('adjustPointsForm').submit();});
}

function confirmDelete(id,name,bookings) {
  const note=bookings>0?`<p class="text-sm text-amber-600 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mt-2">⚠️ มีประวัติจอง <b>${bookings} รายการ</b> — ข้อมูลการจองยังคงอยู่</p>`:'';
  Swal.fire({title:'ลบสมาชิก?',html:`<p class="text-gray-600">กำลังจะลบ:</p><p class="font-bold text-gray-900 text-lg my-1">${name}</p>${note}`,icon:'warning',showCancelButton:true,confirmButtonColor:'#ef4444',cancelButtonColor:'#6b7280',confirmButtonText:'ใช่ ลบเลย',cancelButtonText:'ยกเลิก',reverseButtons:true,focusCancel:true})
    .then(r=>{if(r.isConfirmed){document.getElementById('delete_member_id').value=id;document.getElementById('deleteForm').submit();}});
}

document.addEventListener('keydown',function(e){if(e.key==='Escape'){closeAddModal();closeEditModal();closeAdjustModal();}});
</script>
</body>
</html>
