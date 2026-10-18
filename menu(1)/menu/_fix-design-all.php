<?php
declare(strict_types=1);

$root = __DIR__ . '/tenants';
$dirs = array_filter(scandir($root), fn($d) => is_dir("$root/$d") && !in_array($d, ['.', '..']));

echo "Found " . count($dirs) . " tenant folder(s).\n\n";

// محتوای جدید design.php (با سایدبار)
$designContent = <<<'HTML'
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
  /* استایل برای کنار هم قرار دادن سایدبار و ویرایشگر */
  body, html { height: 100%; margin: 0; padding: 0; overflow: hidden; background: #EFE7D6; }
  .design-layout { display: flex; height: 100vh; }
  .sidebar { width: 250px; flex-shrink: 0; overflow-y: auto; z-index: 1000; position: relative; }
  /* تنظیم ویرایشگر برای اینکه بقیه فضا رو پر کنه */
  #app { flex-grow: 1; height: 100vh; overflow-y: auto; position: relative; }
  /* در حالت موبایل سایدبار مخفی بشه تا ویرایشگر خراب نشه */
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
    <!-- لودینگ اولیه -->
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
HTML;

foreach ($dirs as $slug) {
    if ($slug === '_template') continue; // _template رو جدا هندل میکنیم
    $path = "$root/$slug";
    
    // 1. ساخت design.php
    file_put_contents("$path/design.php", $designContent);
    echo "✅ Created: $slug/design.php\n";

    // 2. اصلاح لینک سایدبار
    $sb = "$path/sidebar.php";
    if (file_exists($sb)) {
        $c = file_get_contents($sb);
        // جایگزینی index.html با design.php در بخش طراحی منو
        $c = str_replace("window.location.href='index.html'", "window.location.href='design.php'", $c);
        $c = str_replace('window.location.href="index.html"', 'window.location.href="design.php"', $c);
        $c = str_replace("href='index.html'", "href='design.php'", $c);
        $c = str_replace('href="index.html"', 'href="design.php"', $c);
        file_put_contents($sb, $c);
        echo "✅ Fixed sidebar link: $slug/sidebar.php\n";
    }
}

// 3. همین کارها برای _template
$tpl = "$root/_template";
file_put_contents("$tpl/design.php", $designContent);
echo "✅ Created: _template/design.php\n";
$sb_tpl = "$tpl/sidebar.php";
if (file_exists($sb_tpl)) {
    $c = file_get_contents($sb_tpl);
    $c = str_replace("window.location.href='index.html'", "window.location.href='design.php'", $c);
    $c = str_replace('window.location.href="index.html"', 'window.location.href="design.php"', $c);
    $c = str_replace("href='index.html'", "href='design.php'", $c);
    $c = str_replace('href="index.html"', 'href="design.php"', $c);
    file_put_contents($sb_tpl, $c);
    echo "✅ Fixed sidebar link: _template/sidebar.php\n";
}

echo "\n🎉 Done! Please refresh the page (Ctrl+F5) and test again.\n";