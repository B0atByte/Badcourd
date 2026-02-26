<?php
/**
 * Reusable pagination component
 * Required vars (set before including):
 *   $page          – current page number
 *   $totalPages    – total number of pages
 *   $totalRecords  – total matching records
 *   $per_page      – items per page (0 = show all)
 */
if (!isset($page, $totalPages, $totalRecords, $per_page) || $totalRecords == 0) return;

$_pqp   = $_GET;
$_pBase = basename($_SERVER['PHP_SELF']);

$showFrom = $per_page > 0 ? (($page - 1) * $per_page + 1)           : 1;
$showTo   = $per_page > 0 ? min($page * $per_page, $totalRecords)    : $totalRecords;
?>
<div class="mt-5 flex flex-col sm:flex-row justify-between items-center gap-3 px-1">

  <!-- Info text -->
  <p class="text-sm text-gray-500 order-2 sm:order-1">
    <?php if ($per_page > 0 && $totalPages > 1): ?>
      แสดง <span class="font-medium text-gray-700"><?= number_format($showFrom) ?></span>–<span class="font-medium text-gray-700"><?= number_format($showTo) ?></span>
      จาก <span class="font-medium text-gray-700"><?= number_format($totalRecords) ?></span> รายการ
      <span class="text-gray-400">(หน้า <?= $page ?>/<?= $totalPages ?>)</span>
    <?php else: ?>
      ทั้งหมด <span class="font-medium text-gray-700"><?= number_format($totalRecords) ?></span> รายการ
    <?php endif; ?>
  </p>

  <!-- Page buttons -->
  <?php if ($per_page > 0 && $totalPages > 1): ?>
  <div class="flex gap-1 flex-wrap justify-center order-1 sm:order-2">

    <?php
    // Precompute links
    $_pqp['page'] = 1;             $firstLink = $_pBase . '?' . http_build_query($_pqp);
    $_pqp['page'] = max(1, $page - 1);  $prevLink  = $_pBase . '?' . http_build_query($_pqp);
    $_pqp['page'] = min($totalPages, $page + 1); $nextLink = $_pBase . '?' . http_build_query($_pqp);
    $_pqp['page'] = $totalPages;   $lastLink  = $_pBase . '?' . http_build_query($_pqp);
    $btnBase = 'inline-flex items-center justify-center min-w-[36px] h-9 px-3 border rounded-lg text-sm transition-colors';
    $btnOff  = 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50';
    $btnDis  = 'bg-white border-gray-200 text-gray-300 cursor-default pointer-events-none';
    $btnAct  = 'text-white border-[#005691] font-semibold';
    ?>

    <!-- First -->
    <a href="<?= $firstLink ?>" class="<?= $btnBase ?> <?= $page <= 1 ? $btnDis : $btnOff ?>" title="หน้าแรก">«</a>
    <!-- Prev -->
    <a href="<?= $prevLink ?>" class="<?= $btnBase ?> <?= $page <= 1 ? $btnDis : $btnOff ?>">‹ ก่อน</a>

    <?php
    $startPage = max(1, $page - 2);
    $endPage   = min($totalPages, $page + 2);
    if ($startPage > 1):
        $_pqp['page'] = 1;
    ?>
      <a href="<?= $_pBase . '?' . http_build_query($_pqp) ?>" class="<?= $btnBase ?> <?= $btnOff ?>">1</a>
      <?php if ($startPage > 2): ?><span class="inline-flex items-center px-1 text-gray-400 text-sm">…</span><?php endif; ?>
    <?php endif; ?>

    <?php for ($i = $startPage; $i <= $endPage; $i++):
      $_pqp['page'] = $i;
      $isAct = ($i === $page);
    ?>
      <a href="<?= $_pBase . '?' . http_build_query($_pqp) ?>"
         class="<?= $btnBase ?> <?= $isAct ? $btnAct : $btnOff ?>"
         <?= $isAct ? 'style="background:#005691;"' : '' ?>>
        <?= $i ?>
      </a>
    <?php endfor; ?>

    <?php if ($endPage < $totalPages):
      if ($endPage < $totalPages - 1): ?>
        <span class="inline-flex items-center px-1 text-gray-400 text-sm">…</span>
      <?php endif;
      $_pqp['page'] = $totalPages;
    ?>
      <a href="<?= $_pBase . '?' . http_build_query($_pqp) ?>" class="<?= $btnBase ?> <?= $btnOff ?>"><?= $totalPages ?></a>
    <?php endif; ?>

    <!-- Next -->
    <a href="<?= $nextLink ?>" class="<?= $btnBase ?> <?= $page >= $totalPages ? $btnDis : $btnOff ?>">ถัดไป ›</a>
    <!-- Last -->
    <a href="<?= $lastLink ?>" class="<?= $btnBase ?> <?= $page >= $totalPages ? $btnDis : $btnOff ?>" title="หน้าสุดท้าย">»</a>

  </div>
  <?php endif; ?>

</div>
