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
        title: <?= json_encode($_sf_success, JSON_UNESCAPED_UNICODE) ?>,
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
        text: <?= json_encode($_sf_error, JSON_UNESCAPED_UNICODE) ?>,
        confirmButtonColor: '#005691',
    });
    <?php endif; ?>
});

// ยืนยันลบ — รองรับ 2 รูปแบบ:
//   รูปแบบ A (callback): swalDelete(title, text, callbackFn)
//   รูปแบบ B (redirect): swalDelete(url, name, titleText)
function swalDelete(titleOrUrl, textOrName, callbackOrTitle) {
    var isCallback = (typeof callbackOrTitle === 'function');
    Swal.fire({
        title: isCallback ? titleOrUrl : (callbackOrTitle || 'ยืนยันการลบ?'),
        html: textOrName ? '<b>' + textOrName + '</b>' : '',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'ใช่ ลบเลย',
        cancelButtonText: 'ยกเลิก',
        reverseButtons: true,
        focusCancel: true,
    }).then(function (result) {
        if (result.isConfirmed) {
            if (isCallback) {
                callbackOrTitle();
            } else {
                window.location.href = titleOrUrl;
            }
        }
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
