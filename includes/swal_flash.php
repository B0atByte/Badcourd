<?php
// Shared SweetAlert2 flash + utility functions
// Include AFTER header.php on each page
// Reads $success and $error from the calling scope
$_sf_success = isset($success) ? trim((string)$success) : '';
$_sf_error   = isset($error)   ? trim((string)$error)   : '';
?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    <?php if ($_sf_success): ?>
    Swal.fire({
        icon: 'success',
        title: '<?= addslashes($_sf_success) ?>',
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
    });
    <?php endif; ?>
    <?php if ($_sf_error): ?>
    Swal.fire({
        icon: 'error',
        title: 'เกิดข้อผิดพลาด',
        text: '<?= addslashes($_sf_error) ?>',
        confirmButtonColor: '#005691',
    });
    <?php endif; ?>
});

// ยืนยันลบ — นำทางไปยัง URL เมื่อกด confirm
// title (optional) ใช้กำหนดหัวข้อ dialog แทน default 'ยืนยันการลบ?'
function swalDelete(url, name, title) {
    Swal.fire({
        title: title || 'ยืนยันการลบ?',
        html: name ? '<b>' + name + '</b>' : '',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'ใช่ ลบเลย',
        cancelButtonText: 'ยกเลิก',
        reverseButtons: true,
        focusCancel: true,
    }).then(function (result) {
        if (result.isConfirmed) window.location.href = url;
    });
}

// ยืนยัน submit form
function swalSubmit(formId, title, text, confirmText) {
    Swal.fire({
        title: title || 'ยืนยัน?',
        text: text || '',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: confirmText || 'ยืนยัน',
        cancelButtonText: 'ยกเลิก',
        reverseButtons: true,
        focusCancel: true,
    }).then(function (result) {
        if (result.isConfirmed) document.getElementById(formId).submit();
    });
}
</script>
