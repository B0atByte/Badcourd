<?php
// ไฟล์นี้ถูก include จาก receipt.php
// ตัวแปรที่ต้องมี: $siteName, $logoSrc, $receiptAddr, $receiptPhone, $receiptTaxId,
//                  $receiptFooter, $receiptNo, $printDate, $courtLabel,
//                  $startDt, $endDt, $bk, $subtotal, $discount, $total
?>
<div style="font-family:'Sarabun','Prompt',sans-serif;background:#fff;width:100%;">

  <!-- Header แดง -->
  <div style="background:#C62828;padding:20px 24px;">
    <div style="display:flex;align-items:center;gap:16px;">
      <?php if ($logoSrc): ?>
      <div style="width:60px;height:60px;border-radius:12px;background:rgba(255,255,255,.2);padding:6px;flex-shrink:0;display:flex;align-items:center;justify-content:center;">
        <img src="<?= htmlspecialchars($logoSrc) ?>" alt="logo"
          style="width:100%;height:100%;object-fit:contain;border-radius:8px;">
      </div>
      <?php endif; ?>
      <div style="flex:1;min-width:0;">
        <div style="color:#fff;font-size:18px;font-weight:700;line-height:1.3;"><?= htmlspecialchars($siteName) ?></div>
        <?php if ($receiptAddr): ?>
        <div style="color:#FFCDD2;font-size:12px;margin-top:3px;line-height:1.5;"><?= htmlspecialchars($receiptAddr) ?></div>
        <?php endif; ?>
        <div style="display:flex;gap:16px;margin-top:3px;">
          <?php if ($receiptPhone): ?>
          <div style="color:#FFCDD2;font-size:12px;">โทร: <?= htmlspecialchars($receiptPhone) ?></div>
          <?php endif; ?>
          <?php if ($receiptTaxId): ?>
          <div style="color:#FFCDD2;font-size:12px;">เลขผู้เสียภาษี: <?= htmlspecialchars($receiptTaxId) ?></div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- ชื่อใบเสร็จ -->
  <div style="background:#f9fafb;padding:12px 24px;border-bottom:1px solid #f3f4f6;display:flex;justify-content:space-between;align-items:center;">
    <div>
      <div style="font-size:16px;font-weight:700;color:#1f2937;">ใบเสร็จรับเงิน</div>
      <div style="font-size:11px;color:#9ca3af;">Receipt</div>
    </div>
    <div style="text-align:right;">
      <div style="font-family:monospace;font-size:13px;font-weight:700;color:#374151;"><?= htmlspecialchars($receiptNo) ?></div>
      <div style="font-size:11px;color:#9ca3af;">วันที่พิมพ์: <?= $printDate ?></div>
    </div>
  </div>

  <!-- ข้อมูลผู้จอง -->
  <div style="padding:16px 24px;border-bottom:1px solid #f3f4f6;">
    <div style="font-size:11px;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px;font-weight:600;">ข้อมูลผู้จอง</div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
      <div>
        <div style="font-size:11px;color:#9ca3af;">ชื่อ</div>
        <div style="font-size:14px;font-weight:600;color:#1f2937;margin-top:2px;"><?= htmlspecialchars($bk['customer_name']) ?></div>
      </div>
      <div>
        <div style="font-size:11px;color:#9ca3af;">เบอร์โทร</div>
        <div style="font-size:14px;font-weight:600;color:#1f2937;margin-top:2px;"><?= htmlspecialchars($bk['customer_phone']) ?></div>
      </div>
    </div>
  </div>

  <!-- รายการ -->
  <div style="padding:16px 24px;border-bottom:1px solid #f3f4f6;">
    <div style="font-size:11px;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em;margin-bottom:12px;font-weight:600;">รายการ</div>
    <div style="display:flex;align-items:flex-start;gap:12px;">
      <div style="background:#FFEBEE;border-radius:8px;padding:10px;flex-shrink:0;">
        <svg width="20" height="20" fill="none" stroke="#C62828" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2 2zm0 5h18M12 7V3m0 0L9 6m3-3l3 3"/>
        </svg>
      </div>
      <div style="flex:1;">
        <div style="font-size:15px;font-weight:700;color:#1f2937;"><?= htmlspecialchars($courtLabel) ?></div>
        <div style="font-size:13px;color:#6b7280;margin-top:3px;">
          <?= $startDt->format('d/m/Y') ?> &nbsp;·&nbsp;
          <?= $startDt->format('H:i') ?>–<?= $endDt->format('H:i') ?> น.
          (<?= $bk['duration_hours'] ?> ชม.)
        </div>
        <?php if ($bk['promo_name']): ?>
        <div style="display:inline-block;margin-top:5px;font-size:11px;padding:2px 8px;border-radius:20px;background:#ede9fe;color:#7c3aed;">
          โปร: <?= htmlspecialchars($bk['promo_name']) ?>
        </div>
        <?php endif; ?>
        <?php if ($bk['member_badminton_package_id']): ?>
        <div style="display:inline-block;margin-top:5px;font-size:11px;padding:2px 8px;border-radius:20px;background:#dbeafe;color:#1d4ed8;">
          ใช้แพ็กเกจ (<?= $bk['used_package_hours'] ?> ชม.)
        </div>
        <?php endif; ?>
      </div>
      <div style="text-align:right;flex-shrink:0;">
        <div style="font-size:14px;font-weight:600;color:#1f2937;">฿<?= number_format($subtotal, 0) ?></div>
        <div style="font-size:11px;color:#9ca3af;">฿<?= number_format($bk['price_per_hour'], 0) ?>/ชม.</div>
      </div>
    </div>
  </div>

  <!-- สรุปราคา -->
  <div style="padding:16px 24px 20px;">
    <div style="font-size:13px;color:#4b5563;display:flex;justify-content:space-between;margin-bottom:6px;">
      <span>ราคา (<?= $bk['duration_hours'] ?> ชม. × ฿<?= number_format($bk['price_per_hour'], 0) ?>)</span>
      <span>฿<?= number_format($subtotal, 0) ?></span>
    </div>
    <?php if ($discount > 0): ?>
    <div style="font-size:13px;color:#16a34a;display:flex;justify-content:space-between;margin-bottom:6px;">
      <span>ส่วนลด<?php if (!empty($bk['promotion_discount_percent']) && $bk['promo_name']): ?> <span style="color:#9ca3af;font-size:11px;">(<?= htmlspecialchars($bk['promo_name']) ?>)</span><?php endif; ?></span>
      <span>-฿<?= number_format($discount, 0) ?></span>
    </div>
    <?php endif; ?>

    <div style="border-top:2px dashed #e5e7eb;margin:12px 0;"></div>

    <div style="display:flex;justify-content:space-between;align-items:center;">
      <span style="font-size:15px;font-weight:700;color:#1f2937;">ยอดชำระ</span>
      <span style="font-size:26px;font-weight:700;color:#C62828;">฿<?= number_format($total, 0) ?></span>
    </div>

    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px;">
      <span style="font-size:12px;color:#9ca3af;">สถานะ</span>
      <?php if ($bk['status'] === 'booked'): ?>
      <span style="font-size:12px;padding:3px 12px;border-radius:20px;background:#dcfce7;color:#16a34a;font-weight:600;">ชำระแล้ว</span>
      <?php else: ?>
      <span style="font-size:12px;padding:3px 12px;border-radius:20px;background:#fee2e2;color:#dc2626;font-weight:600;">ยกเลิก</span>
      <?php endif; ?>
    </div>
  </div>

  <!-- Footer -->
  <?php if ($receiptFooter): ?>
  <div style="padding:10px 24px;border-top:1px solid #f3f4f6;background:#f9fafb;text-align:center;">
    <div style="font-size:12px;color:#9ca3af;"><?= htmlspecialchars($receiptFooter) ?></div>
  </div>
  <?php endif; ?>

  <!-- Ref -->
  <div style="padding:8px 24px;border-top:1px solid #f3f4f6;text-align:center;">
    <div style="font-size:11px;color:#d1d5db;">Booking #<?= $bk['id'] ?> · สร้างเมื่อ <?= (new DateTime($bk['created_at']))->format('d/m/Y H:i') ?></div>
  </div>

</div>
