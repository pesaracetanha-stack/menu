<?php
// بررسی سشن برای نمایش دکمه بازگشت فقط برای مدیر
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('TEN_SESS');
    session_start();
}
$isAdmin = (($_SESSION['tenant_role'] ?? 'admin') === 'admin');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#B4531F">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%F0%9F%8C%BF%3C/text%3E%3C/svg%3E">
<title>صندوق</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
<link rel="stylesheet" href="css/style.css">
<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
<style>
  /* دکمه شناور بازگشت به پنل (فقط برای مدیر) */
  .fab-back {
    position: fixed;
    bottom: 24px;
    left: 24px;
    z-index: 9999;
    display: flex;
    align-items: center;
    gap: 8px;
    background: #271D12;
    color: #FBF6EB;
    padding: 12px 20px;
    border-radius: 50px;
    text-decoration: none;
    font-family: 'Vazirmatn', sans-serif;
    font-weight: 700;
    font-size: 14px;
    box-shadow: 0 4px 12px rgba(39, 29, 18, 0.3);
    transition: all 0.2s ease;
    border: 1px solid rgba(255,255,255,0.1);
  }
  .fab-back:hover {
    background: #B4531F;
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(180, 83, 31, 0.4);
  }
  @media print {
    .fab-back { display: none !important; }
  }
</style>
</head>
<body class="pos-mode">
<div id="errbox"></div>
<div id="app"></div>
<div id="toasts"></div>
<div id="modal-root"></div>
<div id="print-root"></div>

<?php if ($isAdmin): ?>
<!-- دکمه شناور بازگشت - فقط برای مدیر -->
<a href="hub.php" class="fab-back">🏠 بازگشت به پنل</a>
<?php endif; ?>

<!-- المان پنهان برای آپلود لوگو، بنر و عکس‌ها -->
<input type="file" id="imgpick" accept="image/*" style="display:none">

<script src="js/api.js"></script>
<script src="js/core.js"></script>
<script src="js/menu-render.js"></script>
<script src="js/ui-modals.js"></script>
<script src="js/ui-screens.js"></script>
<script src="js/customer.js"></script>
<script src="js/staff.js"></script>
<script src="js/receipt.js"></script>
<script src="js/actions.js"></script>
<script src="js/app.js"></script>
<script src="js/staff-alerts.js"></script>
</body>
</html>