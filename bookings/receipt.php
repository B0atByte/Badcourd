<?php
require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../config/db.php';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: index.php'); exit; }

// โหลด booking + court
$stmt = $pdo->prepare("
    SELECT b.*, c.court_no, c.vip_room_name, c.court_type, c.is_vip,
           p.name AS promo_name
    FROM bookings b
    JOIN courts c ON b.court_id = c.id
    LEFT JOIN promotions p ON b.promotion_id = p.id
    WHERE b.id = ?
");
$stmt->execute([$id]);
$bk = $stmt->fetch();
if (!$bk) { header('Location: index.php'); exit; }

// โหลด site settings
$rows = $pdo->query("SELECT setting_key, setting_value FROM site_settings")->fetchAll();
$cfg = [];
foreach ($rows as $r) $cfg[$r['setting_key']] = $r['setting_value'];

$siteName      = $cfg['site_name']       ?? 'BARGAIN SPORT';
$siteLogo      = $cfg['site_logo']       ?? '/logo/BPL.png';
$receiptAddr   = $cfg['receipt_address'] ?? '';
$receiptPhone  = $cfg['receipt_phone']   ?? '';
$receiptTaxId  = $cfg['receipt_tax_id']  ?? '';
$receiptFooter = $cfg['receipt_footer']  ?? 'ขอบคุณที่ใช้บริการ';

// แปลง logo → base64 (ป้องกัน CORS ใน html2canvas)
$logoBase64 = '';
$logoAbsPath = __DIR__ . '/..' . $siteLogo;
if (file_exists($logoAbsPath)) {
    $ext  = strtolower(pathinfo($logoAbsPath, PATHINFO_EXTENSION));
    $mime = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png',
             'gif'=>'image/gif','svg'=>'image/svg+xml','webp'=>'image/webp'][$ext] ?? 'image/png';
    $logoBase64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($logoAbsPath));
}
$logoSrc = $logoBase64 ?: ($siteLogo . '?v=' . time());

// คำนวณ
$startDt    = new DateTime($bk['start_datetime']);
$endDt      = (clone $startDt)->modify('+' . $bk['duration_hours'] . ' hour');
$subtotal   = $bk['price_per_hour'] * $bk['duration_hours'];
$discount   = $bk['discount_amount'];
$total      = $bk['total_amount'];
$isVip      = ($bk['court_type'] === 'vip' || $bk['is_vip'] == 1);
$courtLabel = $isVip ? ($bk['vip_room_name'] ?? 'ห้อง VIP') : 'คอร์ต ' . $bk['court_no'];
$printDate  = (new DateTime())->format('d/m/Y H:i');
$receiptNo  = 'REC-' . str_pad($bk['id'], 6, '0', STR_PAD_LEFT);
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ใบเสร็จ <?= htmlspecialchars($receiptNo) ?> – <?= htmlspecialchars($siteName) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
  <style>
    * { font-family: 'Sarabun', 'Prompt', sans-serif; }
    @media print {
      .no-print { display: none !important; }
      body { background: white !important; }
      .receipt-wrap { box-shadow: none !important; border: none !important; margin: 0 !important; max-width: 100% !important; }
      @page { size: A5 portrait; margin: 12mm 10mm; }
    }
    .receipt-wrap { max-width: 480px; }
    .dashed { border-top: 2px dashed #e5e7eb; }
    @keyframes spin { to { transform: rotate(360deg); } }
    .spin { animation: spin .8s linear infinite; display:inline-block; }
  </style>
</head>
<body class="bg-gray-100 min-h-screen py-8 px-4">

  <!-- Toolbar -->
  <div id="toolbar" class="no-print receipt-wrap mx-auto mb-4 flex gap-2 flex-wrap">
    <button onclick="window.print()"
      class="flex items-center gap-2 px-4 py-2.5 text-white text-sm font-semibold rounded-lg hover:opacity-90"
      style="background:#D32F2F;">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
      </svg>
      พิมพ์
    </button>
    <button id="copy-btn" onclick="captureReceipt()"
      class="flex items-center gap-2 px-4 py-2.5 text-white text-sm font-semibold rounded-lg hover:opacity-90"
      style="background:#0284c7;">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
      </svg>
      คัดลอกรูป
    </button>
    <a href="index.php"
      class="flex items-center gap-2 px-4 py-2.5 bg-white text-gray-600 text-sm font-semibold rounded-lg border border-gray-200 hover:bg-gray-50">
      ← กลับ
    </a>
  </div>

  <!-- ใบเสร็จ (แสดงบนหน้าจอ) -->
  <div id="receipt-display" class="receipt-wrap mx-auto bg-white rounded-xl shadow-md overflow-hidden">
    <?php include __DIR__ . '/receipt_body.php'; ?>
  </div>

  <!-- div สำหรับ capture รูป (ซ่อน, ไม่มี rounded corner เพื่อให้ขอบสะอาด) -->
  <div style="position:fixed;left:-9999px;top:0;width:520px;background:#fff;" id="receipt-capture">
    <?php include __DIR__ . '/receipt_body.php'; ?>
  </div>

  <script>
    var LOGO_B64 = <?= json_encode($logoBase64) ?>;

    async function captureReceipt() {
      var btn = document.getElementById('copy-btn');
      var origHtml = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<span class="spin">↻</span> กำลังสร้าง...';

      try {
        // รอ font โหลดเสร็จ
        await document.fonts.ready;

        var el = document.getElementById('receipt-capture');

        var canvas = await html2canvas(el, {
          scale: 2.5,
          useCORS: false,       // ปิด CORS เพราะเราใช้ base64 แล้ว
          allowTaint: false,
          backgroundColor: '#ffffff',
          logging: false,
          imageTimeout: 0,
          width: el.scrollWidth,
          height: el.scrollHeight,
          windowWidth: 520,
        });

        // ลอง copy clipboard
        if (navigator.clipboard && window.ClipboardItem) {
          var blob = await new Promise(function(res) { canvas.toBlob(res, 'image/png'); });
          try {
            await navigator.clipboard.write([new ClipboardItem({ 'image/png': blob })]);
            setBtn(btn, origHtml, '✓ คัดลอกแล้ว! วางใน LINE ได้เลย', '#16a34a');
          } catch(e) {
            // clipboard blocked → fallback download
            downloadCanvas(canvas);
            setBtn(btn, origHtml, '↓ บันทึกรูปแล้ว', '#7c3aed');
          }
        } else {
          downloadCanvas(canvas);
          setBtn(btn, origHtml, '↓ บันทึกรูปแล้ว', '#7c3aed');
        }

      } catch(err) {
        btn.disabled = false;
        btn.innerHTML = origHtml;
        alert('เกิดข้อผิดพลาด: ' + err.message);
      }
    }

    function downloadCanvas(canvas) {
      var a = document.createElement('a');
      a.download = '<?= $receiptNo ?>.png';
      a.href = canvas.toDataURL('image/png');
      a.click();
    }

    function setBtn(btn, origHtml, label, color) {
      btn.disabled = false;
      btn.style.background = color;
      btn.innerHTML = label;
      setTimeout(function() {
        btn.style.background = '#0284c7';
        btn.innerHTML = origHtml;
      }, 3000);
    }
  </script>
</body>
</html>
