<?php
declare(strict_types=1);
/* design.php — صفحه طراحی منو با سایدبار */
$slug = basename(__DIR__);
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_name('TEN_SESS');
  session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
  session_start();
}
if (empty($_SESSION['tenant']) || $_SESSION['tenant'] !== $slug) {
  header('Location: ../../login.php'); exit;
}
if (($_SESSION['tenant_role'] ?? 'admin') !== 'admin') {
  http_response_code(403);
  exit('دسترسی غیرمجاز');
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>طراحی منو — پنل کافه</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
<link rel="stylesheet" href="css/style.css">
<style>
  /* ۱. چیدمان کلی صفحه */
  body, html { height: 100%; margin: 0; padding: 0; overflow: hidden; background: #EFE7D6; }
  .design-layout { display: flex; height: 100vh; width: 100vw; direction: rtl; }
  
  /* ۲. کانتینر ویرایشگر */
  #app { flex-grow: 1; height: 100vh; overflow-y: auto; overflow-x: hidden; min-width: 0; position: relative; }
  .menu-item, .item-card, .cat-row { max-width: 100%; box-sizing: border-box; word-break: break-word; }
  
  /* ۳. تزریق استایل‌های سایدبار (کپی شده از پنل ادمین برای جلوگیری از بهم ریختگی) */
  .sidebar { width: 250px; flex-shrink: 0; background: #271D12; color: #FBF6EB; display: flex; flex-direction: column; border-left: 1px solid #3A2C1E; height: 100%; }
  .sidebar .brand { font-size: 18px; font-weight: 800; padding: 20px; text-align: center; color: #fff; border-bottom: 1px solid #3A2C1E; }
  .sidebar .nav-menu { list-style: none; padding: 0; margin: 10px 0; flex-grow: 1; overflow-y: auto; }
  .sidebar .nav-item { padding: 12px 20px; display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 14px; transition: 0.2s; color: #D1C5B4; }
  .sidebar .nav-item:hover { background: rgba(255,255,255,0.05); color: #fff; }
  .sidebar .nav-item.active { background: #B4531F; color: #fff; font-weight: bold; }
  .sidebar .icon { font-size: 18px; }
  .sidebar .sidebar-footer { padding: 20px; border-top: 1px solid #3A2C1E; }

  /* ۴. ریسپانسیو برای موبایل (مخفی کردن سایدبار تا ویرایشگر خراب نشود) */
  @media (max-width: 768px) {
    .sidebar { display: none; }
    #app { width: 100%; }
  }
</style>
<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
</head>
<body>
<div class="design-layout">
  <?php require __DIR__ . '/sidebar.php'; ?>
  <div id="app">
    <div style="display:flex;justify-content:center;align-items:center;height:100%;color:#7D6C54;font-family:Vazirmatn">در حال بارگذاری ویرایشگر…</div>
  </div>
</div>

<!-- کانتینرهای مخفی و اسکریپت‌های اصلی -->
<div id="errbox"></div>
<div id="toasts"></div>
<div id="modal-root"></div>
<div id="print-root"></div>
<input type="file" id="imgpick" accept="image/*" style="display:none">
<input type="file" id="excelpick" accept=".xlsx" style="display:none">

<script>window.__DESIGN_MODE__ = true; try { sessionStorage.setItem('tenAdmin','1'); } catch(e){}</script>
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
</body>
</html>